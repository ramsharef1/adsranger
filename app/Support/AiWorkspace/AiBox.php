<?php

namespace App\Support\AiWorkspace;

use App\Models\AiBrainItem;
use App\Models\AiKey;
use App\Models\Client;
use App\Models\User;

/**
 * AI Workspace M1 item 8 (Product M1 screens §5): the in-app AI box on the agency's own key. Playbook envelope → the
 * provider (JSON for structured types) → one repair try with the exact failed fields → quality gate → saved clean card.
 */
class AiBox
{
    /** @return array{ok: bool, item?: AiBrainItem, cost: float, error?: string, billing?: bool} */
    public static function run(Client $client, AiKey $key, string $playbook, string $notes, ?User $user, ?string $dialect = null, ?string $platform = null): array
    {
        $def = Playbooks::all()[$playbook] ?? null;
        if (! $def) {
            return ['ok' => false, 'cost' => 0.0, 'error' => __('Choose a playbook.')];
        }
        $type = $def['output_type'];
        $env = Playbooks::envelope($client, $playbook, $notes, $dialect, $platform);
        $system = "You work inside AdsRanger for a marketing agency.\n- ".implode("\n- ", $env['rules']);
        $user_ = json_encode(['context' => $env['context'], 'task' => $env['task'], 'output_schema' => $env['output_schema']], JSON_UNESCAPED_UNICODE);

        $cost = 0.0;
        $output = null;
        $text = '';
        for ($try = 1; $try <= OutputContract::MAX_TRIES; $try++) {
            $r = OwnKey::complete($key, $system, $user_, $type !== null);
            $cost += $r['cost'];
            if (! $r['ok']) {
                return ['ok' => false, 'cost' => $cost, 'error' => $r['error'] ?? __('The provider answered with an error.'), 'billing' => $r['billing'] ?? false];
            }
            $text = $r['text'];
            if ($type === null) {
                break;
            }
            $output = OwnKey::json($text);
            $errors = OutputContract::validate($type, $output);
            if ($errors === []) {
                break;
            }
            if ($try < OutputContract::MAX_TRIES) {
                $user_ .= "\n\nYour last answer did not fit output_schema. Fix these and return only the JSON object:\n- ".implode("\n- ", $errors);
            }
        }

        $saved = Brain::save($client, [
            'type' => 'output', 'title' => ($def[app()->getLocale() === 'ar' ? 'title_ar' : 'title_en']).' · '.now($client->agency?->timezone ?: 'UTC')->format('Y-m-d'),
            'body' => $type === null ? $text : null, 'output_type' => $type, 'output' => $type === null ? null : ($output ?? ['raw' => mb_substr($text, 0, 5000)]),
            'try' => OutputContract::MAX_TRIES, 'ai' => AiKey::AS_ASSISTANT[$key->provider] ?? null, 'playbook' => $playbook, 'playbook_version' => Playbooks::VERSION,
        ], $user);

        return ['ok' => true, 'item' => $saved['item'], 'cost' => $cost];
    }
}
