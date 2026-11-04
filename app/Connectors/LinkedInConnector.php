<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** LinkedIn Marketing API (versioned REST). Campaign groups → campaigns, LinkedIn campaigns → ad sets. */
class LinkedInConnector extends Connector
{
    protected const API = 'https://api.linkedin.com/rest/';

    protected const TOKEN = 'https://www.linkedin.com/oauth/v2/accessToken';

    /** Tested default for the LinkedIn-Version header. Each version lives about a year; bump before it sunsets (ADSR-050). */
    public const DEFAULT_VERSION = '202610';

    /** A stored override older than this many months is ignored (LinkedIn sunsets versions after about a year). */
    protected const MAX_AGE_MONTHS = 11;

    protected const OBJECTIVES = [
        'LEAD_GENERATION' => 'lead', 'WEBSITE_CONVERSION' => 'conversion', 'WEBSITE_VISIT' => 'link_click', 'BRAND_AWARENESS' => 'impressions',
        'VIDEO_VIEW' => 'thruplay', 'ENGAGEMENT' => 'engagement', 'JOB_APPLICANT' => 'lead', 'TALENT_LEAD' => 'lead', 'CREATIVE_ENGAGEMENT' => 'engagement',
    ];

    public function supportsConnect(): bool
    {
        return (bool) ($this->cred('client_id') && $this->cred('client_secret'));
    }

    public function supportsSync(): bool
    {
        return true;
    }

    /** The stored yyyymm override if it is well-formed, not in the future and not near its sunset; otherwise the tested default. */
    public function version(): string
    {
        $v = trim((string) $this->cred('api_version', ''));
        if (preg_match('/^\d{6}$/', $v) && $v >= now()->subMonths(self::MAX_AGE_MONTHS)->format('Ym') && $v <= now()->format('Ym')) {
            return $v;
        }

        return self::DEFAULT_VERSION;
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query(['response_type' => 'code', 'client_id' => $this->cred('client_id'),
            'redirect_uri' => $this->redirectUri(), 'state' => $state, 'scope' => 'r_ads r_ads_reporting']);
    }

    public function exchange(string $code): array
    {
        $res = Http::asForm()->timeout(20)->post(self::TOKEN, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(),
            'client_id' => $this->cred('client_id'), 'client_secret' => $this->cred('client_secret')]);
        $token = $res->json('access_token') ?: throw new RuntimeException('LinkedIn did not return a token: '.($res->json('error_description') ?? $res->json('error')));

        return ['access_token' => $token, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in'),
            'user_id' => null, 'user_name' => 'LinkedIn ad access', 'scopes' => $res->json('scope')];
    }

    protected function refresh(string $refreshToken): array
    {
        return $this->oauthRefresh(self::TOKEN, $refreshToken);
    }

    /** GET with a raw (Rest.li-encoded) query string. */
    protected function get(Connection $c, string $pathAndQuery): array
    {
        $res = Http::withToken($this->token($c))->withHeaders(['LinkedIn-Version' => $this->version(), 'X-Restli-Protocol-Version' => '2.0.0'])
            ->timeout(60)->retry(2, 2000, throw: false)->get(self::API.$pathAndQuery);
        if ($res->failed()) {
            throw new RuntimeException('LinkedIn: '.($res->json('message') ?? 'HTTP '.$res->status()));
        }

        return (array) $res->json();
    }

    protected function search(Connection $c, string $path): array
    {
        $out = [];
        $token = null;
        for ($i = 0; $i < 50; $i++) {
            $d = $this->get($c, $path.(str_contains($path, '?') ? '&' : '?').'pageSize=500'.($token ? '&pageToken='.rawurlencode($token) : ''));
            array_push($out, ...($d['elements'] ?? []));
            $token = $d['metadata']['nextPageToken'] ?? null;
            if (! $token) {
                break;
            }
        }

        return $out;
    }

    public function accounts(Connection $connection): array
    {
        $out = [];
        foreach ($this->get($connection, 'adAccountUsers?q=authenticatedUser')['elements'] ?? [] as $u) {
            $id = preg_replace('/\D/', '', (string) ($u['account'] ?? ''));
            if (! $id) {
                continue;
            }
            $a = $this->get($connection, "adAccounts/$id");
            $out[] = ['id' => $id, 'name' => $a['name'] ?? null, 'currency' => $a['currency'] ?? null, 'timezone' => null, 'status' => $a['status'] ?? null];
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The LinkedIn connection is missing. Reconnect it.');
        $id = $account->external_id;
        $ts = fn ($ms) => $ms ? Carbon::createFromTimestampMs($ms)->toIso8601String() : null;

        $campaigns = array_map(fn ($g) => ['external_id' => (string) $g['id'], 'name' => $g['name'] ?? null, 'status' => $g['status'] ?? null,
            'start_time' => $ts($g['runSchedule']['start'] ?? null), 'end_time' => $ts($g['runSchedule']['end'] ?? null)],
            $this->search($c, "adAccounts/$id/adCampaignGroups?q=search"));
        $types = [];
        $sets = array_map(function ($x) use (&$types, $ts) {
            $group = preg_replace('/\D/', '', (string) ($x['campaignGroup'] ?? ''));
            $types[(string) $x['id']] = [self::OBJECTIVES[strtoupper((string) ($x['objectiveType'] ?? ''))] ?? 'link_click', $group];

            return ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'campaign' => $group ?: null, 'status' => $x['status'] ?? null,
                'optimization_goal' => $x['objectiveType'] ?? null, 'daily_budget' => isset($x['dailyBudget']['amount']) ? (float) $x['dailyBudget']['amount'] : null,
                'start_time' => $ts($x['runSchedule']['start'] ?? null), 'end_time' => $ts($x['runSchedule']['end'] ?? null)];
        }, $this->search($c, "adAccounts/$id/adCampaigns?q=search"));

        $rows = [];
        $fields = 'dateRange,pivotValues,impressions,clicks,landingPageClicks,costInLocalCurrency,externalWebsiteConversions,oneClickLeads,conversionValueInLocalCurrency,videoCompletions,totalEngagements';
        foreach (static::windows($from, $to, 90) as [$f, $t]) {
            $f = Carbon::parse($f);
            $t = Carbon::parse($t);
            $range = "(start:(year:{$f->year},month:{$f->month},day:{$f->day}),end:(year:{$t->year},month:{$t->month},day:{$t->day}))";
            $q = "adAnalytics?q=analytics&pivot=CAMPAIGN&timeGranularity=DAILY&dateRange=$range&accounts=List(urn%3Ali%3AsponsoredAccount%3A$id)&fields=$fields";
            foreach ($this->get($c, $q)['elements'] ?? [] as $e) {
                $set = preg_replace('/\D/', '', (string) ($e['pivotValues'][0] ?? ''));
                [$type, $group] = $types[$set] ?? ['link_click', null];
                $d = $e['dateRange']['start'] ?? null;
                if (! $d) {
                    continue;
                }
                $m = ['spend' => (float) ($e['costInLocalCurrency'] ?? 0), 'impressions' => (int) ($e['impressions'] ?? 0), 'clicks' => (int) ($e['clicks'] ?? 0),
                    'link_clicks' => (int) ($e['landingPageClicks'] ?? 0), 'leads' => (float) ($e['oneClickLeads'] ?? 0),
                    'purchases' => 0.0, 'thruplays' => (float) ($e['videoCompletions'] ?? 0), 'revenue' => (float) ($e['conversionValueInLocalCurrency'] ?? 0)];
                $conv = (float) ($e['externalWebsiteConversions'] ?? 0);
                $res = match ($type) { 'conversion' => $conv, 'engagement' => (float) ($e['totalEngagements'] ?? 0), default => null };
                $rows[] = ['date' => sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']), 'level' => 'ad_set', 'campaign' => $group, 'ad_set' => $set, 'ad' => null]
                    + static::metrics($m, $type, $res);
            }
        }

        return ['campaigns' => $campaigns, 'ad_sets' => $sets, 'ads' => [], 'rows' => $rows];
    }

    public function test(): array
    {
        return $this->oauthClientTest(self::TOKEN);
    }
}
