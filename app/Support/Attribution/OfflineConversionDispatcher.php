<?php

namespace App\Support\Attribution;

use App\Models\Client;
use App\Models\OfflineConversionDispatch;
use App\Support\TextClean;
use Illuminate\Support\Facades\Http;
use Throwable;

class OfflineConversionDispatcher
{
    public static function normalizeAndHashPhone(?string $phone): ?string
    {
        $raw = TextClean::western(trim((string) $phone));
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if ($digits === '' || strlen($digits) < 7) {
            return null;
        }

        return hash('sha256', $digits);
    }

    public static function normalizeAndHashEmail(?string $email): ?string
    {
        $clean = strtolower(trim((string) $email));
        if ($clean === '' || ! str_contains($clean, '@')) {
            return null;
        }

        return hash('sha256', $clean);
    }

    /**
     * Records a qualified/won offline deal with SHA-256 hashed PII + ctwa_clid/gclid.
     * Honesty gate (A-2 / WR-06 / WR-07): without live API credentials, never fakes success —
     * explicitly stores 'queued_until_credentials'. When credentials are provided, dispatches
     * via the network's HTTP contract.
     *
     * @param  array{
     *   lead_identifier: string,
     *   deal_stage?: string,
     *   deal_value?: float|int|string,
     *   currency?: string,
     *   ctwa_clid?: ?string,
     *   gclid?: ?string,
     *   phone?: ?string,
     *   email?: ?string,
     *   hashed_phone?: ?string,
     *   hashed_email?: ?string,
     *   target_network?: string
     * }  $payload
     * @param  array<string, string>  $credentials
     */
    public static function queueOrSend(Client $client, array $payload, array $credentials = []): OfflineConversionDispatch
    {
        $stage = in_array($payload['deal_stage'] ?? 'qualified', OfflineConversionDispatch::STAGES, true)
            ? (string) ($payload['deal_stage'] ?? 'qualified')
            : 'qualified';
        $network = in_array($payload['target_network'] ?? 'meta_capi', OfflineConversionDispatch::NETWORKS, true)
            ? (string) ($payload['target_network'] ?? 'meta_capi')
            : 'meta_capi';
        $value = max(0.0, (float) TextClean::western((string) ($payload['deal_value'] ?? 0)));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? $client->currency ?: 'SAR')));

        $hashedPhone = ! empty($payload['hashed_phone'])
            ? strtolower(trim((string) $payload['hashed_phone']))
            : self::normalizeAndHashPhone($payload['phone'] ?? null);
        $hashedEmail = ! empty($payload['hashed_email'])
            ? strtolower(trim((string) $payload['hashed_email']))
            : self::normalizeAndHashEmail($payload['email'] ?? null);

        $ctwa = trim((string) ($payload['ctwa_clid'] ?? '')) ?: null;
        $gclid = trim((string) ($payload['gclid'] ?? '')) ?: null;

        $dispatch = OfflineConversionDispatch::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'lead_identifier' => trim((string) $payload['lead_identifier']),
            'deal_stage' => $stage,
            'deal_value' => round($value, 3),
            'currency' => $currency,
            'ctwa_clid' => $ctwa,
            'gclid' => $gclid,
            'hashed_phone' => $hashedPhone,
            'hashed_email' => $hashedEmail,
            'target_network' => $network,
            'status' => 'queued_until_credentials',
        ]);

        if (! self::hasCredentialsFor($network, $credentials)) {
            return $dispatch;
        }

        return self::sendLive($dispatch, $credentials);
    }

    /**
     * @param  array<string, string>  $credentials
     */
    public static function hasCredentialsFor(string $network, array $credentials): bool
    {
        if ($network === 'meta_capi') {
            return ! empty($credentials['pixel_id']) && ! empty($credentials['access_token']);
        }
        if ($network === 'google_offline') {
            return ! empty($credentials['customer_id'])
                && ! empty($credentials['conversion_action'])
                && ! empty($credentials['access_token'])
                && ! empty($credentials['developer_token']);
        }

        return false;
    }

    /**
     * @param  array<string, string>  $credentials
     */
    public static function sendLive(OfflineConversionDispatch $dispatch, array $credentials): OfflineConversionDispatch
    {
        try {
            if ($dispatch->target_network === 'meta_capi') {
                $pixelId = rawurlencode((string) $credentials['pixel_id']);
                $userData = array_filter([
                    'ph' => $dispatch->hashed_phone ? [$dispatch->hashed_phone] : null,
                    'em' => $dispatch->hashed_email ? [$dispatch->hashed_email] : null,
                    'ctwa_clid' => $dispatch->ctwa_clid,
                ]);
                $res = Http::timeout(10)->post("https://graph.facebook.com/v20.0/{$pixelId}/events", [
                    'access_token' => (string) $credentials['access_token'],
                    'data' => [[
                        'event_name' => $dispatch->deal_stage === 'won' ? 'Purchase' : 'Lead',
                        'event_time' => now()->timestamp,
                        'action_source' => $dispatch->ctwa_clid ? 'business_messaging' : 'system_generated',
                        'messaging_channel' => $dispatch->ctwa_clid ? 'whatsapp' : null,
                        'user_data' => $userData,
                        'custom_data' => [
                            'currency' => $dispatch->currency,
                            'value' => (float) $dispatch->deal_value,
                            'lead_event_source' => 'LeadDesk',
                        ],
                    ]],
                ]);

                if ($res->successful()) {
                    $dispatch->update([
                        'status' => 'sent',
                        'provider_response' => ['http_status' => $res->status(), 'events_received' => $res->json('events_received')],
                    ]);
                } else {
                    $dispatch->update([
                        'status' => 'failed',
                        'provider_response' => ['http_status' => $res->status()],
                    ]);
                }

                return $dispatch->fresh();
            }

            $customerId = preg_replace('/\D+/', '', (string) $credentials['customer_id']) ?? '';
            $conversionRow = array_filter([
                'gclid' => $dispatch->gclid,
                'conversionAction' => (string) $credentials['conversion_action'],
                'conversionDateTime' => now()->format('Y-m-d H:i:sP'),
                'conversionValue' => (float) $dispatch->deal_value,
                'currencyCode' => $dispatch->currency,
                'userIdentifiers' => array_values(array_filter([
                    $dispatch->hashed_phone ? ['hashedPhoneNumber' => $dispatch->hashed_phone] : null,
                    $dispatch->hashed_email ? ['hashedEmail' => $dispatch->hashed_email] : null,
                ])) ?: null,
            ], fn ($v): bool => $v !== null);

            $res = Http::withToken((string) $credentials['access_token'])
                ->withHeaders(['developer-token' => (string) $credentials['developer_token']])
                ->timeout(10)
                ->post("https://googleads.googleapis.com/v17/customers/{$customerId}:uploadClickConversions", [
                    'conversions' => [$conversionRow],
                    'partialFailure' => true,
                ]);

            if ($res->successful()) {
                $dispatch->update([
                    'status' => 'sent',
                    'provider_response' => ['http_status' => $res->status(), 'results_count' => count((array) $res->json('results', []))],
                ]);
            } else {
                $dispatch->update([
                    'status' => 'failed',
                    'provider_response' => ['http_status' => $res->status()],
                ]);
            }
        } catch (Throwable $e) {
            $dispatch->update([
                'status' => 'failed',
                'provider_response' => ['error' => 'network_exception'],
            ]);
        }

        return $dispatch->fresh();
    }
}
