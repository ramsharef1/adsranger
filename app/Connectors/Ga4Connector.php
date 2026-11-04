<?php

namespace App\Connectors;

use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Ga4Connector extends GoogleAdsConnector
{
    protected string $scope = 'https://www.googleapis.com/auth/analytics.readonly';

    public function accounts(Connection $connection): array
    {
        $token = $this->freshToken($connection);
        $out = [];
        $pageToken = null;
        do {
            $res = Http::withToken($token)->timeout(30)->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', array_filter(['pageSize' => 200, 'pageToken' => $pageToken]));
            if ($res->failed()) {
                throw new RuntimeException('GA4: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
            }
            foreach ($res->json('accountSummaries', []) as $acc) {
                foreach ($acc['propertySummaries'] ?? [] as $p) {
                    $out[] = ['id' => str_replace('properties/', '', $p['property']), 'name' => ($acc['displayName'] ?? '').' / '.($p['displayName'] ?? ''),
                        'currency' => null, 'timezone' => null, 'status' => $p['propertyType'] ?? null];
                }
            }
            $pageToken = $res->json('nextPageToken');
        } while ($pageToken);

        return $out;
    }
}
