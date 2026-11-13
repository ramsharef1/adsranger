<?php

namespace App\Support\Reach;

use App\Models\ReachLedgerEntry;
use App\Models\ReachOffer;
use App\Models\ReachPostbackEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * FR-REACH R-1 universal S2S conversion postback (/reach/v1/postback/{token}).
 * Trust: the source IP must be on the endpoint's allow-list when it has one, a `sig` (or X-Reach-Signature) must be a
 * valid HMAC-SHA256 of the sorted query when sent, and at least one of the two must pass. Idempotent per
 * (endpoint, conversion id, type). Status reversed / rejected / declined nets the earlier credit back out.
 */
class Postbacks
{
    public const REVERSAL = ['reversed', 'rejected', 'declined', 'refunded', 'cancelled'];

    public static function canonical(array $params): string
    {
        unset($params['sig']);
        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function sign(array $params, string $secret): string
    {
        return hash_hmac('sha256', static::canonical($params), $secret);
    }

    /** @return 'ok'|'bad_ip'|'bad_signature'|'unsigned' */
    public static function trust(ReachPostbackEndpoint $endpoint, Request $request): string
    {
        $params = $request->query() + $request->post();
        $sig = (string) ($params['sig'] ?? $request->header('X-Reach-Signature', ''));
        $ips = (array) ($endpoint->allowed_ips ?? []);

        if ($ips !== [] && ! IpUtils::checkIp((string) $request->ip(), $ips)) {
            return 'bad_ip';
        }
        if ($sig !== '' && ! hash_equals(static::sign($params, $endpoint->secret), strtolower($sig))) {
            return 'bad_signature';
        }

        return ($ips !== [] || $sig !== '') ? 'ok' : 'unsigned';
    }

    /**
     * R-4: the network preset with this endpoint's overrides on top.
     *
     * @return array{subid: string, trust: string, fields: array<string, string>, macros: array<string, string>}
     */
    public static function preset(ReachPostbackEndpoint $endpoint): array
    {
        $base = (array) config('reach.network_presets.'.$endpoint->network, []);
        $fields = array_merge(
            ['conversion_id' => 'conversion_id', 'payout' => 'payout', 'status' => 'status', 'offer_id' => 'offer_id'],
            (array) ($base['fields'] ?? []),
            array_filter((array) ($endpoint->param_map ?? []), fn ($v) => is_string($v) && $v !== '')
        );

        return [
            'subid' => (string) ($endpoint->subid_param ?: ($base['subid'] ?? 'subid')),
            'trust' => (string) ($base['trust'] ?? 'hmac'),
            'fields' => $fields,
            'macros' => (array) ($base['macros'] ?? []),
        ];
    }

    /**
     * R-4: reads the network's own parameter names (case-insensitive) into the canonical keys handle() uses.
     *
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public static function normalise(ReachPostbackEndpoint $endpoint, array $p): array
    {
        $preset = static::preset($endpoint);
        $lower = array_change_key_case($p, CASE_LOWER);
        $out = [];
        foreach ($preset['fields'] + ['subid' => $preset['subid']] as $canonical => $param) {
            $value = $lower[strtolower($param)] ?? null;
            if ($value !== null && $value !== '' && ! isset($p[$canonical])) {
                $out[$canonical] = $value;
            }
        }

        return $out + $p;
    }

    /** R-4: the postback URL to paste at the network, with that network's macros (overrides applied). */
    public static function template(ReachPostbackEndpoint $endpoint, string $receiverUrl): string
    {
        $preset = static::preset($endpoint);
        $pairs = [];
        foreach ($preset['fields'] + ['subid' => $preset['subid']] as $param) {
            $pairs[$param] = $preset['macros'][$param] ?? '{'.$param.'}';
        }

        return $receiverUrl.'?'.implode('&', array_map(fn ($k, $v) => $k.'='.$v, array_keys($pairs), $pairs));
    }

    /** @return array{ok: bool, result: string, entry_id?: int} */
    public static function handle(ReachPostbackEndpoint $endpoint, array $p): array
    {
        $p = static::normalise($endpoint, $p);
        $conversionId = mb_substr(trim((string) ($p['conversion_id'] ?? $p['txn_id'] ?? $p['cid'] ?? '')), 0, 96);
        if ($conversionId === '') {
            return ['ok' => false, 'result' => 'missing_conversion_id'];
        }
        $endpoint->forceFill(['last_received_at' => now()])->save();
        $status = strtolower(trim((string) ($p['status'] ?? 'approved')));

        $original = ReachLedgerEntry::where('postback_endpoint_id', $endpoint->id)->where('external_conversion_id', $conversionId)
            ->where('conversion_type', 'conversion')->first();

        if (in_array($status, self::REVERSAL, true)) {
            if (! $original) {
                return ['ok' => true, 'result' => 'nothing_to_reverse'];
            }
            if ($original->wallet_state === 'refunded') {
                return ['ok' => true, 'result' => 'duplicate', 'entry_id' => $original->id];
            }
            $refund = Wallet::refund($original, $conversionId, $endpoint->id);

            return ['ok' => true, 'result' => 'refunded', 'entry_id' => $refund->id];
        }
        if ($original) {
            return ['ok' => true, 'result' => 'duplicate', 'entry_id' => $original->id];
        }

        $offerRef = trim((string) ($p['offer_id'] ?? $p['offer'] ?? ''));
        $offer = $offerRef === '' ? null : ReachOffer::where('network', $endpoint->network)->where('external_id', $offerRef)->first();
        $at = isset($p['ts']) && ctype_digit((string) $p['ts']) ? Carbon::createFromTimestamp((int) $p['ts']) : now();
        if ($at->isFuture()) {
            $at = now();
        }
        $coupon = isset($p['coupon']) ? TextCleanCode::code((string) $p['coupon']) : null;
        $who = Attribution::resolve($p['subid'] ?? $p['sub_id'] ?? $p['aff_sub'] ?? null, $coupon, $offer, $at);
        if (! $who) {
            Cache::increment('reach:unattributed:'.now()->toDateString());

            return ['ok' => true, 'result' => 'unattributed'];
        }

        $gross = OfferImporter::number($p['payout'] ?? null) ?? ($offer?->payout_amount ?? 0.0);
        $share = $offer && $offer->payout_amount > 0 ? $offer->publisher_payout_amount / $offer->payout_amount : (float) config('reach.publisher_share');
        $entry = Wallet::creditConversion($who['publisher'], [
            'offer_id' => $offer?->id, 'postback_endpoint_id' => $endpoint->id, 'external_conversion_id' => $conversionId,
            'gross_amount' => Reach::amount($gross), 'net_commission' => Reach::amount($gross * $share),
            'attribution_tier' => $who['tier'], 'placement' => $who['placement'], 'coupon_code' => $coupon, 'converted_at' => $at,
            'meta' => ['network_status' => $status],
        ]);

        return ['ok' => true, 'result' => 'credited', 'entry_id' => $entry->id];
    }
}
