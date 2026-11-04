<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use App\Sync\ReportPending;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Yahoo DSP API (Yahoo Native now runs inside Yahoo DSP). The agency's API client ID + secret from Yahoo:
 * an HS256 client assertion is swapped for a ~10-minute token. Reports are asynchronous CSVs (submit, then poll).
 */
class YahooDspConnector extends KeyConnector
{
    protected const API = 'https://dspapi.admanagerplus.yahoo.com/';

    protected const REPORTS = 'https://api-sched-v3.admanagerplus.yahoo.com/yamplus_api/extreport/';

    protected const TOKEN = 'https://id.b2b.yahooincapis.com/zts/v1/oauth2/token';

    public function keyFields(): array
    {
        return [
            ['client_id', 'API client ID', false, 'Issued by Yahoo when API access is set up for your DSP seat.'],
            ['client_secret', 'API client secret', true, 'Issued with the client ID. Stored encrypted.'],
        ];
    }

    public function keyHelp(): string
    {
        return 'Ask your Yahoo DSP account team for API access (Setup Access guide). They send a client ID and secret for your seat.';
    }

    public static function assertion(string $clientId, string $secret): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $input = $b64(json_encode(['alg' => 'HS256', 'typ' => 'jwt'])).'.'.$b64(json_encode([
            'aud' => 'https://id.b2b.yahooincapis.com/zts/v1', 'iss' => 'idb2b.dsp.dspapi.'.$clientId, 'sub' => 'idb2b.dsp.dspapi.'.$clientId,
            'iat' => $now, 'exp' => $now + 600, 'jti' => (string) Str::uuid()]));

        return $input.'.'.$b64(hash_hmac('sha256', $input, $secret, true));
    }

    protected function fetchToken(array $creds): array
    {
        $res = Http::asForm()->timeout(20)->post(self::TOKEN, ['grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => static::assertion((string) ($creds['client_id'] ?? ''), (string) ($creds['client_secret'] ?? '')),
            'scope' => 'api-client', 'realm' => 'dsp']);
        $tok = $res->json('access_token') ?: throw $this->fail($res, 'rejected the API credentials');

        return ['token' => $tok, 'expires_in' => (int) ($res->json('expires_in') ?: 599)];
    }

    protected function identify(array $creds, ?string $token): array
    {
        return [(string) $creds['client_id'], 'Yahoo DSP API client'];
    }

    protected function req(Connection $c)
    {
        return static::client()->withHeaders(['X-Auth-Method' => 'OAuth2', 'X-Auth-Token' => $this->token($c)]);
    }

    protected function get(Connection $c, string $path, array $q = []): array
    {
        $res = $this->req($c)->get(self::API.$path, $q);
        if ($res->failed() || ! empty($res->json('errors'))) {
            throw $this->fail($res, 'error');
        }

        return (array) ($res->json('response') ?? []);
    }

    public function accounts(Connection $connection): array
    {
        $out = [];
        for ($page = 1; $page <= 20; $page++) {
            $list = $this->get($connection, 'traffic/advertisers', ['page' => $page, 'limit' => 100]);
            foreach ($list as $a) {
                $out[] = ['id' => (string) $a['id'], 'name' => $a['name'] ?? null, 'currency' => $a['currency'] ?? null, 'timezone' => $a['timezone'] ?? null, 'status' => $a['status'] ?? null];
            }
            if (count($list) < 100) {
                break;
            }
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Yahoo DSP connection is missing. Reconnect it.');
        $adv = (string) $account->external_id;
        $tz = $account->timezone ?: 'UTC';

        $campaigns = [];
        foreach ($this->get($c, 'traffic/campaigns', ['accountId' => $adv]) as $x) {
            $sched = $x['budgetSchedules'][0] ?? [];
            $campaigns[(string) $x['id']] = ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'status' => $x['status'] ?? null,
                'lifetime_budget' => is_numeric($sched['scheduleBudget'] ?? null) ? (float) $sched['scheduleBudget'] : null,
                'start_time' => $sched['startDate'] ?? null, 'end_time' => $sched['endDate'] ?? null];
        }

        $meta = (array) ($account->meta ?? []);
        $key = 'yahoo_report:'.$from.':'.$to;
        $id = $meta[$key] ?? null;
        if (! $id) {
            $res = $this->req($c)->post(self::REPORTS, ['reportOption' => ['timezone' => $tz, 'accountIds' => [(int) $adv],
                'dimensionTypeIds' => [4, 5], 'metricTypeIds' => [1, 2, 44, 23]],
                'intervalTypeId' => 2, 'dateTypeId' => 11,
                'startDate' => Carbon::parse($from, $tz)->startOfDay()->format('Y-m-d\TH:i:sP'), 'endDate' => Carbon::parse($to, $tz)->endOfDay()->format('Y-m-d\TH:i:sP')]);
            $id = $res->json('customerReportId') ?: throw $this->fail($res, 'did not accept the report request');
            $meta[$key] = $id;
            $account->update(['meta' => $meta]);
        }
        $st = $this->req($c)->get(self::REPORTS.rawurlencode((string) $id));
        $status = strtoupper((string) $st->json('status'));
        if ($status === 'FAILED' || $st->status() === 404) {
            unset($meta[$key]);
            $account->update(['meta' => $meta]);
            throw new RuntimeException('Yahoo DSP could not build the report. It will be requested again on the next sync.');
        }
        if ($status !== 'SUCCESS' || ! $st->json('url')) {
            throw new ReportPending('Yahoo DSP is preparing the report.');
        }
        $csv = Http::timeout(120)->get((string) $st->json('url'))->body();
        unset($meta[$key]);
        $account->update(['meta' => $meta]);

        $raw = static::parseCsv($csv);
        $converts = [];
        foreach ($raw as $r) {
            $converts[$r['campaign']] = ($converts[$r['campaign']] ?? false) || $r['conversions'] > 0;
            $campaigns[$r['campaign']] ??= ['external_id' => $r['campaign'], 'name' => $r['campaign_name']];
        }
        $rows = array_map(fn ($r) => static::campaignRow($r['date'], $r['campaign'], ['spend' => $r['spend'], 'impressions' => $r['impressions'], 'clicks' => $r['clicks']],
            $converts[$r['campaign']], $r['conversions']), array_filter($raw, fn ($r) => $r['spend'] > 0 || $r['impressions'] > 0));

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => [], 'rows' => array_values($rows)];
    }

    /** Find the columns by header name (Yahoo's exact headers vary by report). */
    public static function parseCsv(string $csv): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', ltrim($csv, "\xEF\xBB\xBF")), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            return [];
        }
        $head = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines), ',', '"', ''));
        $find = function (array $pats, array $not = []) use ($head) {
            foreach ($head as $i => $h) {
                foreach ($pats as $p) {
                    if (preg_match($p, $h) && ! array_filter($not, fn ($n) => preg_match($n, $h))) {
                        return $i;
                    }
                }
            }

            return null;
        };
        $col = ['date' => $find(['/^(day|date|interval)/']), 'campaign' => $find(['/campaign.*id/', '/^campaign$/']), 'name' => $find(['/campaign.*name/']),
            'imps' => $find(['/impression/'], ['/conversion/']), 'clicks' => $find(['/^clicks?$/', '/click/'], ['/conversion|rate|ctr/']),
            'spend' => $find(['/spend|spent|cost/']), 'conv' => $find(['/^conversions?$/', '/conversion/'], ['/rate|cost|view|click/'])];
        if ($col['date'] === null || $col['campaign'] === null) {
            throw new RuntimeException('Yahoo DSP report has unexpected columns: '.mb_substr(implode(', ', $head), 0, 160));
        }
        $num = fn ($v) => (float) str_replace([',', '$'], '', (string) $v);
        $out = [];
        foreach ($lines as $l) {
            $v = str_getcsv($l, ',', '"', '');
            $cid = trim((string) ($v[$col['campaign']] ?? ''));
            if ($cid === '' || ! preg_match('/^\d+$/', $cid)) {
                continue;
            }
            $out[] = ['date' => Carbon::parse(trim((string) $v[$col['date']]))->toDateString(), 'campaign' => $cid,
                'campaign_name' => $col['name'] !== null ? ($v[$col['name']] ?? null) : null,
                'impressions' => (int) $num($v[$col['imps']] ?? 0), 'clicks' => (int) $num($v[$col['clicks']] ?? 0),
                'spend' => $num($v[$col['spend']] ?? 0), 'conversions' => $col['conv'] !== null ? $num($v[$col['conv']] ?? 0) : 0.0];
        }

        return $out;
    }
}
