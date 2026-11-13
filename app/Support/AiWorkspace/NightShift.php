<?php

namespace App\Support\AiWorkspace;

use App\Models\Agency;
use App\Models\AiKey;
use App\Models\AiNightRun;
use App\Models\AiNightShift;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\Modules;

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
        if (! Modules::on('ai_workspace', null, $agency) && ! Modules::isPreviewAgency($agency)) {
            return $fail(__('AI Workspace is not available for this workspace yet.'));
        }
        if (! $key) {
            return $fail(__('No key for this provider. Add one in My API keys.'));
        }

        $clients = Client::where('agency_id', $agency->id)->whereIn('id', (array) $shift->client_ids)->limit((int) config('ai_workspace.night_shift.max_clients', 25))->get();
        $items = 0;
        $cost = 0.0;
        $checked = 0;
        foreach ($clients as $client) {
            $r = static::client($client, $key);
            $cost += $r['cost'];
            if (! $r['ok']) {
                $run->update(['status' => $checked ? 'partial' : 'failed', 'error' => $r['error'], 'clients_checked' => $checked, 'items_found' => $items, 'cost_usd' => $cost, 'finished_at' => now()]);

                return $run;
            }
            $checked++;
            $items += $r['items'];
        }
        $run->update(['clients_checked' => $checked, 'items_found' => $items, 'cost_usd' => $cost, 'finished_at' => now()]);
        AuditLog::record('ai_workspace.night_shift', $agency, ['run' => $run->id, 'clients' => $checked, 'items' => $items]);

        return $run;
    }

    /** @return array{ok: bool, items: int, cost: float, error?: string} */
    protected static function client(Client $client, AiKey $key): array
    {
        $pack = ContextPack::for($client);
        $alerts = Alert::where('client_id', $client->id)->where('status', 'open')->latest('last_seen_at')->limit(20)->get()
            ->map(fn (Alert $a) => ['level' => ['high' => 'critical', 'medium' => 'warning'][$a->severity] ?? 'info', 'title' => strip_tags((string) $a->title), 'concerns' => strip_tags((string) $a->entity_name)])->all();
        if ($pack['facts'] === [] && $alerts === []) {
            return ['ok' => true, 'items' => 0, 'cost' => 0.0];
        }
        $max = (int) config('ai_workspace.night_shift.max_items_per_client', 3);
        $system = "You are the night shift for a marketing agency inside AdsRanger.\n- ".implode("\n- ", Playbooks::rules('night')).
            "\n- Return {\"items\": [...]} with at most $max items, most important first. Each item: level (critical, warning or info), title, why (with numbers from facts), action (one specific step), campaign (name or null). Return {\"items\": []} if nothing needs attention.";
        $r = OwnKey::complete($key, $system, json_encode(['context' => ['client' => $pack['client'], 'currency' => $pack['currency'], 'data_until' => $pack['data_until'],
            'facts' => $pack['facts'], 'client_rules' => $pack['rules'], 'open_alerts' => $alerts]], JSON_UNESCAPED_UNICODE), true);
        if (! $r['ok']) {
            return ['ok' => false, 'items' => 0, 'cost' => $r['cost'], 'error' => ($r['billing'] ?? false) ? __('Your key is out of credit.') : ($r['error'] ?? __('The provider answered with an error.'))];
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

        return ['ok' => true, 'items' => $found, 'cost' => $r['cost']];
    }
}
