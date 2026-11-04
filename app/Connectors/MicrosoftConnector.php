<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use App\Support\ResultResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Microsoft Advertising API v13, REST endpoints (the SOAP API shuts down 31 Jan 2027). */
class MicrosoftConnector extends Connector
{
    protected const CM = 'https://clientcenter.api.bingads.microsoft.com/CustomerManagement/v13/';

    protected const REP = 'https://reporting.api.bingads.microsoft.com/Reporting/v13/';

    protected const SCOPE = 'https://ads.microsoft.com/msads.manage offline_access';

    public function supportsConnect(): bool
    {
        return (bool) ($this->cred('client_id') && $this->cred('client_secret') && $this->cred('developer_token'));
    }

    public function supportsSync(): bool
    {
        return true;
    }

    protected function tokenUrl(): string
    {
        return 'https://login.microsoftonline.com/'.$this->cred('tenant', 'common').'/oauth2/v2.0/token';
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://login.microsoftonline.com/'.$this->cred('tenant', 'common').'/oauth2/v2.0/authorize?'.http_build_query([
            'client_id' => $this->cred('client_id'), 'response_type' => 'code', 'redirect_uri' => $this->redirectUri(), 'scope' => self::SCOPE, 'state' => $state, 'prompt' => 'select_account']);
    }

    public function exchange(string $code): array
    {
        $res = Http::asForm()->timeout(20)->post($this->tokenUrl(), ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(),
            'client_id' => $this->cred('client_id'), 'client_secret' => $this->cred('client_secret'), 'scope' => self::SCOPE]);
        $token = $res->json('access_token') ?: throw new RuntimeException('Microsoft did not return a token: '.($res->json('error_description') ?? $res->json('error')));
        $user = Http::withToken($token)->withHeaders(['DeveloperToken' => $this->cred('developer_token')])->timeout(20)->post(self::CM.'User/Query', ['UserId' => null]);

        return ['access_token' => $token, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in'),
            'user_id' => (string) ($user->json('User.Id') ?? ''), 'user_name' => $user->json('User.UserName') ?? $user->json('User.ContactInfo.Email'), 'scopes' => $res->json('scope')];
    }

    protected function refresh(string $refreshToken): array
    {
        return $this->oauthRefresh($this->tokenUrl(), $refreshToken, ['scope' => self::SCOPE]);
    }

    protected function post(Connection $c, string $url, array $body, array $headers = []): array
    {
        $res = Http::withToken($this->token($c))->withHeaders($headers + ['DeveloperToken' => $this->cred('developer_token')])
            ->timeout(60)->retry(2, 2000, throw: false)->post($url, $body);
        if ($res->failed()) {
            $err = $res->json('OperationErrors.0.Message') ?? $res->json('Errors.0.Message') ?? $res->json('Message') ?? 'HTTP '.$res->status();
            throw new RuntimeException('Microsoft Ads: '.$err);
        }

        return (array) $res->json();
    }

    public function accounts(Connection $connection): array
    {
        $uid = $connection->external_user_id ?: (string) ($this->post($connection, self::CM.'User/Query', ['UserId' => null])['User']['Id'] ?? '');
        $d = $this->post($connection, self::CM.'Accounts/Search', ['Predicates' => [['Field' => 'UserId', 'Operator' => 'Equals', 'Value' => $uid]],
            'Ordering' => null, 'PageInfo' => ['Index' => 0, 'Size' => 1000]]);

        return array_map(fn ($a) => ['id' => (string) $a['Id'], 'name' => $a['Name'] ?? null, 'currency' => $a['CurrencyCode'] ?? null, 'timezone' => null,
            'status' => $a['AccountLifeCycleStatus'] ?? null, 'meta' => ['customer_id' => (string) ($a['ParentCustomerId'] ?? ''), 'number' => $a['Number'] ?? null]],
            $d['Accounts'] ?? []);
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Microsoft Ads connection is missing. Reconnect it.');
        $headers = ['CustomerId' => (string) ($account->meta['customer_id'] ?? ''), 'CustomerAccountId' => $account->external_id];
        $f = Carbon::parse($from);
        $t = Carbon::parse($to);
        $day = fn (Carbon $d) => ['Day' => $d->day, 'Month' => $d->month, 'Year' => $d->year];
        $req = $this->post($c, self::REP.'GenerateReport/Submit', ['ReportRequest' => [
            'Type' => 'CampaignPerformanceReportRequest', 'Format' => 'Csv', 'ReportName' => 'AdsRanger daily', 'ReturnOnlyCompleteData' => false,
            'ExcludeColumnHeaders' => false, 'ExcludeReportHeader' => true, 'ExcludeReportFooter' => true, 'Aggregation' => 'Daily',
            'Columns' => ['TimePeriod', 'CampaignId', 'CampaignName', 'CampaignStatus', 'Spend', 'Impressions', 'Clicks', 'Conversions', 'Revenue', 'PhoneCalls'],
            'Scope' => ['AccountIds' => [(int) $account->external_id]],
            'Time' => ['CustomDateRangeStart' => $day($f), 'CustomDateRangeEnd' => $day($t)],
        ]], $headers);
        $rid = $req['ReportRequestId'] ?? throw new RuntimeException('Microsoft Ads did not accept the report request.');
        $url = null;
        for ($i = 0; $i < 40; $i++) {
            $st = $this->post($c, self::REP.'GenerateReport/Poll', ['ReportRequestId' => $rid], $headers)['ReportRequestStatus'] ?? [];
            if (($st['Status'] ?? '') === 'Success') {
                $url = $st['ReportDownloadUrl'] ?? null;
                break;
            }
            if (($st['Status'] ?? '') === 'Error') {
                throw new RuntimeException('Microsoft Ads report failed.');
            }
            sleep(3);
        }
        $campaigns = [];
        $rows = [];
        if (! $url) {
            return ['campaigns' => [], 'ad_sets' => [], 'ads' => [], 'rows' => []]; // no data in range
        }
        $csv = static::unzip(Http::timeout(120)->get($url)->body());
        $lines = array_values(array_filter(preg_split("/\r\n|\n|\r/", ltrim($csv, "\xEF\xBB\xBF")), fn ($l) => trim($l) !== ''));
        $head = str_getcsv(array_shift($lines) ?? '', ',', '"', '');
        foreach ($lines as $line) {
            $r = array_combine($head, array_pad(str_getcsv($line, ',', '"', ''), count($head), null)) ?: [];
            if (empty($r['CampaignId']) || empty($r['TimePeriod'])) {
                continue;
            }
            $cid = (string) $r['CampaignId'];
            $campaigns[$cid] = ['external_id' => $cid, 'name' => $r['CampaignName'] ?? null, 'status' => $r['CampaignStatus'] ?? null];
            $n = fn ($k) => (float) str_replace(',', '', (string) ($r[$k] ?? 0));
            $m = ResultResolver::google(['cost_micros' => $n('Spend') * 1_000_000, 'impressions' => $n('Impressions'), 'clicks' => $n('Clicks'),
                'conversions' => $n('Conversions'), 'conversions_value' => $n('Revenue')]);
            $m['calls'] = $n('PhoneCalls');
            $rows[] = ['date' => Carbon::parse($r['TimePeriod'])->toDateString(), 'level' => 'campaign', 'campaign' => $cid, 'ad_set' => null, 'ad' => null] + $m;
        }

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => [], 'rows' => $rows];
    }

    protected static function unzip(string $body): string
    {
        if (! str_starts_with($body, "PK")) {
            return $body;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'msr');
        file_put_contents($tmp, $body);
        $zip = new \ZipArchive;
        $out = '';
        if ($zip->open($tmp) === true) {
            $out = (string) $zip->getFromIndex(0);
            $zip->close();
        }
        @unlink($tmp);

        return $out;
    }

    public function test(): array
    {
        if (! $this->cred('developer_token')) {
            return [false, 'Developer Token is missing.'];
        }

        return $this->oauthClientTest($this->tokenUrl(), ['scope' => self::SCOPE]);
    }
}
