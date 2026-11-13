<?php

namespace App\Support\AiWorkspace;

use App\Jobs\ResumeNightShift;
use App\Models\Agency;
use App\Models\AiKey;
use App\Models\AiNightRun;
use App\Models\AiNightShift;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\Modules;
use App\Support\PlanGate;
use App\Support\Severity;

/**
 * AI Workspace M1 item 8 (spec v1 §13.1 N1, Product M1 screens §8): night shift v0. Each night, at the workspace's chosen
 * hour, the agency's own key reviews each chosen client's numbers and open alerts and writes up to 3 items with a D-88
 * level. M1 = read + morning list only: no approve button, nothing changes in any account. Every item passes the gate.
 */
class NightShift
{
    protected const LEVELS = ['critical', 'warning', 'info'];

    /** Workspaces whose run hour is now (their time zone) and that haven't run today. */
    public static function due(): array
    {
        return AiNightShift::with('agency')->where('enabled', true)->get()->filter(function (AiNightShift $s) {
            $now = now($s->agency?->timezone ?: 'UTC');

            return $s->agency && (int) $now->format('G') === (int) $s->run_hour && $s->last_run_on?->toDateString() !== $now->toDateString();
        })->values()->all();
    }

    public static function run(AiNightShift $shift): AiNightRun
    {
        $agency = $shift->agency;
        $shift->update(['last_run_on' => now($agency->timezone ?: 'UTC')->toDateString()]);
        $run = AiNightRun::create(['agency_id' => $agency->id, 'provider' => $shift->provider, 'status' => 'ok', 'started_at' => now()]);
        $key = AiKey::where('agency_id', $agency->id)->where('provider', $shift->provider)->first();

        $fail = fn (string $why) => tap($run)->update(['status' => 'failed', 'error' => $why, 'finished_at' => now()]);
        if (AiKill::off('night_shift')) { // FS-49
            return $fail(__('The night shift is paused for everyone for now.'));
        }
        if (! Modules::on('ai_workspace', null, $agency) && ! Modules::isPreviewAgency($agency)) {
            return $fail(__('AI Workspace is not available for this workspace yet.'));
        }
        if (! PlanGate::allows($agency, 'ai_workspace_full')) {
            return $fail(__('The night shift comes with :plan.', ['plan' => PlanGate::tierLabel('pro')]));
        }
        if (! $key) {
            return $fail(__('No key for this provider. Add one in My API keys.'));
        }

        $ids = Client::where('agency_id', $agency->id)->where('status', 'active')->whereIn('id', (array) $shift->client_ids)->limit((int) config('ai_workspace.night_shift.max_clients', 25))->pluck('id')->all();

        return static::work($run, $key, $ids);
    }

    /** FS-09 (D-297): the provider was down or rate-limited; try the clients still left, up to 6 hours after the first try. */
    public static function resume(AiNightRun $run, array $clientIds): AiNightRun
    {
        $key = AiKey::where('agency_id', $run->agency_id)->where('provider', $run->provider)->first();
        if (! $key || AiKill::off('night_shift')) {
            $run->update(['status' => 'failed', 'error' => __('The night shift could not finish.'), 'finished_at' => now()]);

            return $run;
        }

        return static::work($run, $key, $clientIds);
    }

    /** @param list<int> $clientIds */
    protected static function work(AiNightRun $run, AiKey $key, array $clientIds): AiNightRun
    {
        $agency = Agency::find($run->agency_id);
        $items = (int) $run->items_found;
        $cost = (float) $run->cost_usd;
        $checked = (int) $run->clients_checked;
        $redacted = (int) $run->redacted;
        $clients = Client::whereIn('id', $clientIds)->orderBy('id')->get();
        foreach ($clients as $i => $client) {
            $r = static::client($client, $key);
            $cost += $r['cost'];
            $redacted += (int) ($r['redacted'] ?? 0);
            if (! $r['ok']) {
                $left = $clients->slice($i)->pluck('id')->all();
                $maxRetries = (int) config('ai_workspace.night_shift.retry_hours', 6) * 2;
                if (($r['transient'] ?? false) && (int) $run->retries < $maxRetries) {
                    $run->update(['status' => 'waiting', 'error' => __('Your AI provider is not answering. Trying again in 30 minutes (up to 6 hours); never on our credits.'),
                        'retries' => (int) $run->retries + 1, 'clients_checked' => $checked, 'items_found' => $items, 'cost_usd' => $cost, 'redacted' => $redacted]);
                    ResumeNightShift::dispatch($run->id, $left)->delay(now()->addMinutes(30));

                    return $run;
                }
                $error = ($r['transient'] ?? false) ? __('Your AI provider did not answer for 6 hours. The night shift stopped for today.') : $r['error'];
                $run->update(['status' => $checked ? 'partial' : 'failed', 'error' => $error, 'clients_checked' => $checked, 'items_found' => $items, 'cost_usd' => $cost, 'redacted' => $redacted, 'finished_at' => now()]);

                return $run;
            }
            $checked++;
            $items += $r['items'];
        }
        $run->update(['status' => 'ok', 'error' => null, 'clients_checked' => $checked, 'items_found' => $items, 'cost_usd' => $cost, 'redacted' => $redacted, 'finished_at' => now()]);
        if ($agency) {
            AuditLog::record('ai_workspace.night_shift', $agency, ['run' => $run->id, 'clients' => $checked, 'items' => $items, 'redacted' => $redacted]);
        }

        return $run;
    }

    /** @return array{ok: bool, items: int, cost: float, error?: string} */
    protected static function client(Client $client, AiKey $key): array
    {
        $pack = ContextPack::forAi($client);
        $alerts = Alert::where('client_id', $client->id)->where('status', 'open')->latest('last_seen_at')->limit(20)->get()
            ->map(fn (Alert $a) => ['level' => Severity::normalize($a->severity), 'title' => strip_tags((string) $a->title), 'concerns' => strip_tags((string) $a->entity_name)])->all();
        // FS-07 (R7 part 4): new Critical site-audit issues from an audit that finished in the last day.
        $siteIssues = SiteContext::newCritical($client, now()->subDay());
        if ($pack['facts'] === [] && $alerts === [] && $siteIssues === []) {
            return ['ok' => true, 'items' => 0, 'cost' => 0.0];
        }
        $max = (int) config('ai_workspace.night_shift.max_items_per_client', 3);
        $system = "You are the night shift for a marketing agency inside AdsRanger.\n- ".implode("\n- ", Playbooks::rules('night')).
            "\n- Return {\"items\": [...]} with at most $max items, most important first. Each item: level (critical, warning or info), title, why (with numbers from facts), action (one specific step), campaign (name or null). New site issues in new_site_issues come from AdsRanger's site audit; mention them only when they matter for this client's ads or search. Return {\"items\": []} if nothing needs attention.";
        $r = OwnKey::complete($key, $system, json_encode(['context' => ['client' => $pack['client'], 'currency' => $pack['currency'], 'data_until' => $pack['data_until'],
            'facts' => $pack['facts'], 'client_rules' => $pack['rules'], 'open_alerts' => $alerts, 'new_site_issues' => $siteIssues]], JSON_UNESCAPED_UNICODE), true);
        if (! $r['ok']) {
            return ['ok' => false, 'items' => 0, 'cost' => $r['cost'], 'transient' => (bool) ($r['transient'] ?? false), 'redacted' => (int) ($r['redacted'] ?? 0),
                'error' => ($r['billing'] ?? false) ? __('Your key is out of credit.') : ($r['error'] ?? __('The provider answered with an error.'))];
        }
        $found = 0;
        foreach (array_slice((array) (OwnKey::json($r['text'])['items'] ?? []), 0, $max) as $it) {
            if (! is_array($it) || empty($it['title'])) {
                continue;
            }
            $level = in_array($it['level'] ?? '', self::LEVELS, true) ? $it['level'] : 'info';
            Brain::save($client, ['type' => 'output', 'title' => mb_substr((string) $it['title'], 0, 200),
                'body' => trim(($it['why'] ?? '')."\n".($it['action'] ?? '')), 'ai' => AiKey::AS_ASSISTANT[$key->provider] ?? null,
                'playbook' => 'night_shift', 'playbook_version' => Playbooks::VERSION, 'meta' => ['level' => $level, 'campaign' => $it['campaign'] ?? null]]);
            $found++;
        }

        return ['ok' => true, 'items' => $found, 'cost' => $r['cost'], 'redacted' => (int) ($r['redacted'] ?? 0)];
    }
}
