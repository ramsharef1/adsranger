<?php

namespace App\Support;

use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * #79 data export: CSV files (UTF-8 with a BOM so Excel shows Arabic), headers in the user's language (EX-43),
 * scoped to what the user can see (EX-49). Large tables are read in chunks and written straight to the stream.
 * Money stays in each row's own currency with its code: no row mixes currencies (EX-18).
 */
class DataExport
{
    public const BOM = "\xEF\xBB\xBF";

    public const CHUNK = 2000;

    /** Client ids the user may export, or null = the whole workspace (owners, admins, super admins). */
    public static function visibleClientIds(?User $user, Agency $agency): ?array
    {
        if (! $user || $user->isSuperAdmin() || in_array($user->roleIn($agency), ['owner', 'admin'], true)) {
            return null;
        }

        return Client::visibleTo($user, $agency)->pluck('id')->all();
    }

    /** Neutralise spreadsheet formulas in text cells (CSV injection). */
    public static function cell(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($v)) {
            return "'".$v;
        }

        return $v;
    }

    /** @param resource $out */
    public static function row($out, array $cells): void
    {
        fputcsv($out, array_map([static::class, 'cell'], $cells), ',', '"', '');
    }

    protected static function num(mixed $v, int $dp = 2): string
    {
        $f = (float) $v;

        return $f == floor($f) && $dp <= 2 ? (string) (int) round($f) : rtrim(rtrim(number_format($f, $dp, '.', ''), '0'), '.');
    }

    protected static function money(mixed $v, ?string $cur = null): string
    {
        return number_format((float) $v, ResultResolver::decimals((float) $v, $cur), '.', ''); // DS-24
    }

    protected static function networkName(?string $key): string
    {
        return (string) (config("ad_networks.$key.name") ?? $key);
    }

    /**
     * Daily metrics by date × ad account × campaign. $clientIds null = the whole workspace (unassigned rows too).
     * $withSpend false drops Spend and Cost per result (client-facing export of a client with hide_spend on).
     *
     * @param  resource  $out
     */
    public static function writeMetrics($out, Agency $agency, ?array $clientIds, string $from, string $to, bool $withSpend = true, bool $withClient = true): int
    {
        $lang = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $head = [__('Date')];
        if ($withClient) {
            $head[] = __('Client');
        }
        array_push($head, __('Network'), __('Ad account'), __('Ad account ID'), __('Campaign'), __('Currency'));
        if ($withSpend) {
            $head[] = __('Spend');
        }
        array_push($head, __('Impressions'), __('Reach'), __('Link clicks'), __('Clicks'), __('Results'), __('Result type'));
        if ($withSpend) {
            $head[] = __('Cost per result');
        }
        array_push($head, __('Leads'), __('Messages'), __('Calls placed'), __('Calls confirmed'), __('Calls 20s+'), __('Purchases'), __('Revenue'));
        static::row($out, $head);

        $sum = fn (string $c) => "SUM(m.$c) as $c";
        $q = DB::table('daily_metrics as m')
            ->leftJoin('ad_accounts as a', 'a.id', '=', 'm.ad_account_id')
            ->leftJoin('campaigns as c', 'c.id', '=', 'm.campaign_ref')
            ->leftJoin('clients as cl', 'cl.id', '=', 'm.client_id')
            ->where('m.agency_id', $agency->id)
            ->whereBetween('m.date', [$from, $to])
            ->when($clientIds !== null, fn ($w) => $w->whereIn('m.client_id', $clientIds ?: [0]))
            ->selectRaw(implode(',', ['m.date', 'cl.name as client_name', 'm.network', 'a.name as account_name', 'a.external_id as account_ext',
                'c.name as campaign_ref_name', 'm.campaign_name', 'm.currency', 'm.result_type',
                ...array_map($sum, ['spend', 'impressions', 'reach', 'link_clicks', 'clicks', 'results', 'leads', 'messages', 'calls', 'calls_confirmed', 'calls_20s', 'purchases', 'revenue'])]))
            ->groupBy('m.date', 'cl.name', 'm.network', 'a.name', 'a.external_id', 'c.name', 'm.campaign_name', 'm.currency', 'm.result_type')
            ->orderBy('m.date')->orderBy('cl.name')->orderBy('m.network')->orderBy('a.name')->orderBy('a.external_id')
            ->orderBy('c.name')->orderBy('m.campaign_name')->orderBy('m.currency')->orderBy('m.result_type');

        $n = 0;
        foreach ($q->lazy(static::CHUNK) as $r) {
            $cells = [substr((string) $r->date, 0, 10)];
            if ($withClient) {
                $cells[] = $r->client_name ?? '';
            }
            array_push($cells, static::networkName($r->network), $r->account_name ?? '', $r->account_ext ?? '', $r->campaign_ref_name ?? $r->campaign_name ?? '', $r->currency ?? '');
            if ($withSpend) {
                $cells[] = static::money($r->spend, $r->currency);
            }
            array_push($cells, static::num($r->impressions), static::num($r->reach), static::num($r->link_clicks), static::num($r->clicks),
                static::num($r->results), ResultResolver::label($r->result_type ?: 'conversion', $lang));
            if ($withSpend) {
                $cells[] = (float) $r->results > 0 ? static::money((float) $r->spend / (float) $r->results, $r->currency) : '';
            }
            array_push($cells, static::num($r->leads), static::num($r->messages), static::num($r->calls), static::num($r->calls_confirmed), static::num($r->calls_20s), static::num($r->purchases), static::money($r->revenue, $r->currency));
            static::row($out, $cells);
            $n++;
        }

        return $n;
    }

    /** @param resource $out */
    public static function writeClients($out, Agency $agency, ?array $clientIds): int
    {
        static::row($out, [__('Client'), __('Status'), __('Currency'), __('Market'), __('Vertical'), __('Monthly budget'), __('Target cost per result'),
            __('Target results'), __('Target ROAS'), __('Hide spend from this client'), __('Ad accounts'), __('Created')]);
        $n = 0;
        Client::where('agency_id', $agency->id)->when($clientIds !== null, fn ($w) => $w->whereIn('id', $clientIds ?: [0]))
            ->withCount('adAccounts')->orderBy('name')->orderBy('id')
            ->lazy(static::CHUNK)->each(function (Client $c) use ($out, &$n) {
                static::row($out, [$c->name, __(ucfirst((string) $c->status)), $c->currency, $c->market, $c->vertical,
                    $c->monthly_budget !== null ? static::money($c->monthly_budget) : '', $c->target_cpr !== null ? static::money($c->target_cpr) : '',
                    $c->target_results ?? '', $c->target_roas ?? '', $c->hide_spend ? __('Yes') : __('No'), $c->ad_accounts_count,
                    $c->created_at?->toDateString()]);
                $n++;
            });

        return $n;
    }

    /** @param resource $out */
    public static function writeAdAccounts($out, Agency $agency, ?array $clientIds): int
    {
        static::row($out, [__('Ad account'), __('Network'), __('Ad account ID'), __('Client'), __('Currency'), __('Time zone'), __('Tracked'), __('Status'), __('Last synced')]);
        $n = 0;
        AdAccount::where('agency_id', $agency->id)->when($clientIds !== null, fn ($w) => $w->whereIn('client_id', $clientIds ?: [0]))
            ->with(['network', 'client'])->orderBy('name')->orderBy('id')
            ->lazy(static::CHUNK)->each(function (AdAccount $a) use ($out, &$n) {
                static::row($out, [$a->name, $a->network?->name ?? '', $a->external_id, $a->client?->name ?? '', $a->currency, $a->timezone,
                    $a->is_active ? __('Yes') : __('No'), $a->status, $a->last_synced_at?->toDateTimeString()]);
                $n++;
            });

        return $n;
    }

    /** Generic table dump for the workspace archive. @param resource $out */
    public static function writeTable($out, string $table, array $columns, callable $scope, ?callable $map = null, ?array $header = null): int
    {
        static::row($out, $header ?? $columns);
        $n = 0;
        $q = DB::table($table)->select($columns);
        $scope($q);
        foreach ($q->orderBy('id')->lazy(static::CHUNK) as $r) {
            $r = (array) $r;
            static::row($out, $map ? $map($r) : array_values($r));
            $n++;
        }

        return $n;
    }

    /**
     * Owner's "download all workspace data" (#79, Meta App Review / GDPR-style): one ZIP of CSVs for the whole
     * workspace, all dates. Returns the path of a temp file the caller deletes after sending.
     */
    public static function workspaceZip(Agency $agency): string
    {
        $dir = storage_path('app/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $zipPath = $dir.'/'.uniqid('ws-'.$agency->id.'-', true).'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $tmp = [];
        $add = function (string $name, callable $write) use ($zip, &$tmp, $dir) {
            $path = $dir.'/'.uniqid('part-', true).'.csv';
            $fh = fopen($path, 'w');
            fwrite($fh, static::BOM);
            $write($fh);
            fclose($fh);
            $zip->addFile($path, $name);
            $tmp[] = $path;
        };
        $agencyScope = fn ($q) => $q->where('agency_id', $agency->id);

        $add('clients.csv', fn ($fh) => static::writeClients($fh, $agency, null));
        $add('ad_accounts.csv', fn ($fh) => static::writeAdAccounts($fh, $agency, null));
        $add('daily_metrics.csv', fn ($fh) => static::writeMetrics($fh, $agency, null, '1970-01-01', '2999-12-31'));
        $add('campaigns.csv', fn ($fh) => static::writeTable($fh, 'campaigns', ['id', 'ad_account_id', 'external_id', 'name', 'status', 'objective', 'created_at'], $agencyScope));
        $add('alerts.csv', fn ($fh) => static::writeTable($fh, 'alerts', ['id', 'client_id', 'ad_account_id', 'rule_key', 'severity', 'title', 'detail', 'status', 'created_at', 'resolved_at'], $agencyScope));
        $add('members.csv', fn ($fh) => static::writeTable($fh, 'agency_user', ['id', 'user_id', 'role', 'created_at'],
            fn ($q) => $q->where('agency_id', $agency->id),
            function (array $r) {
                $u = DB::table('users')->where('id', $r['user_id'])->first(['name', 'email']);

                return [$r['id'], $r['user_id'], $r['role'], $r['created_at'], $u->name ?? '', $u->email ?? ''];
            }, ['id', 'user_id', 'role', 'joined_at', 'name', 'email']));
        $add('client_links.csv', fn ($fh) => static::writeTable($fh, 'client_links', ['id', 'client_id', 'label', 'expires_at', 'revoked_at', 'views', 'last_viewed_at', 'created_at'], $agencyScope));
        $add('report_schedules.csv', fn ($fh) => static::writeTable($fh, 'report_schedules', ['id', 'client_id', 'monthly_day', 'weekly_day', 'lang', 'recipients', 'attach_pdf', 'include_link', 'active', 'last_sent_at', 'created_at'], $agencyScope));
        $add('report_sends.csv', fn ($fh) => static::writeTable($fh, 'report_sends', ['id', 'client_id', 'report_schedule_id', 'kind', 'period', 'email', 'status', 'sent_at', 'created_at'], $agencyScope));
        $zip->addFromString('README.txt', "AdsRanger workspace export\nWorkspace: {$agency->name}\nCreated: ".now()->toDateTimeString()." UTC\n\n"
            ."CSV files are UTF-8 with a BOM. Money is in each row's own currency (Currency column).\n"
            ."Network passwords, tokens and API keys are never exported.\n");
        $zip->close();
        foreach ($tmp as $p) {
            @unlink($p);
        }

        return $zipPath;
    }
}
