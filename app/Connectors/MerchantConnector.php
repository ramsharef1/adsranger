<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Google Merchant Center (Merchant API): product performance on free listings + Shopping, and account issues. */
class MerchantConnector extends GoogleAdsConnector
{
    protected string $scope = 'https://www.googleapis.com/auth/content';

    protected const API = 'https://merchantapi.googleapis.com/';

    protected function v(): string
    {
        return $this->cred('merchant_api_version', 'v1');
    }

    protected function call(Connection $c, string $method, string $path, array $body = []): array
    {
        $req = Http::withToken($this->freshToken($c))->timeout(60)->retry(2, 2000, throw: false);
        $res = $method === 'post' ? $req->post(self::API.$path, $body) : $req->get(self::API.$path, $body);
        if ($res->failed()) {
            throw new RuntimeException('Merchant Center: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }

        return (array) $res->json();
    }

    public function accounts(Connection $connection): array
    {
        $out = [];
        $token = null;
        for ($i = 0; $i < 20; $i++) {
            $d = $this->call($connection, 'get', 'accounts/'.$this->v().'/accounts', array_filter(['pageSize' => 250, 'pageToken' => $token]));
            foreach ($d['accounts'] ?? [] as $a) {
                $out[] = ['id' => (string) ($a['accountId'] ?? preg_replace('/\D/', '', (string) $a['name'])), 'name' => $a['accountName'] ?? null,
                    'currency' => null, 'timezone' => $a['timeZone']['id'] ?? null, 'status' => null];
            }
            if (! ($token = $d['nextPageToken'] ?? null)) {
                break;
            }
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Merchant Center connection is missing. Reconnect it.');
        $id = $account->external_id;
        $camp = 'merchant-'.$id;
        $ads = [];
        $rows = [];
        $body = ['query' => "SELECT date, offer_id, title, clicks, impressions, conversions, conversion_value FROM product_performance_view WHERE date BETWEEN '$from' AND '$to'", 'pageSize' => 1000];
        for ($i = 0; $i < 100; $i++) {
            $d = $this->call($c, 'post', 'reports/'.$this->v()."/accounts/$id/reports:search", $body);
            foreach ($d['results'] ?? [] as $r) {
                $p = $r['productPerformanceView'] ?? [];
                if (empty($p['impressions']) && empty($p['clicks'])) {
                    continue;
                }
                $offer = (string) ($p['offerId'] ?? 'unknown');
                $ads[$offer] = ['external_id' => $offer, 'name' => $p['title'] ?? $offer, 'campaign' => $camp, 'status' => 'ACTIVE'];
                $dt = $p['date'] ?? [];
                $rows[] = ['date' => sprintf('%04d-%02d-%02d', $dt['year'] ?? 1970, $dt['month'] ?? 1, $dt['day'] ?? 1), 'level' => 'ad', 'campaign' => $camp, 'ad_set' => null, 'ad' => $offer]
                    + static::metrics(['impressions' => (int) ($p['impressions'] ?? 0), 'clicks' => (int) ($p['clicks'] ?? 0), 'link_clicks' => (int) ($p['clicks'] ?? 0),
                        'purchases' => (float) ($p['conversions'] ?? 0), 'revenue' => ((float) ($p['conversionValue']['amountMicros'] ?? 0)) / 1_000_000], 'purchase');
                if (! $account->currency && isset($p['conversionValue']['currencyCode'])) {
                    $account->update(['currency' => $p['conversionValue']['currencyCode']]);
                }
            }
            if (! ($body['pageToken'] = $d['nextPageToken'] ?? null)) {
                break;
            }
        }

        return ['campaigns' => [['external_id' => $camp, 'name' => 'Free listings & Shopping', 'status' => 'ACTIVE', 'objective' => 'MERCHANT']],
            'ad_sets' => [], 'ads' => array_values($ads), 'rows' => $rows];
    }

    public function health(AdAccount $account): array
    {
        if (! $account->connection) {
            return [];
        }
        $out = [];
        $d = $this->call($account->connection, 'get', 'accounts/'.$this->v()."/accounts/{$account->external_id}/issues", ['pageSize' => 100]);
        foreach ($d['accountIssues'] ?? [] as $iss) {
            $sev = strtoupper((string) ($iss['severity'] ?? ''));
            if (in_array($sev, ['CRITICAL', 'ERROR'], true)) {
                $out[] = [$sev === 'CRITICAL' ? 'critical' : 'warning', 'Merchant Center: '.($iss['title'] ?? 'account issue')];
            }
        }

        return array_slice($out, 0, 10);
    }
}
