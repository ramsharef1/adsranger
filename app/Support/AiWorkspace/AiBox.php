<?php

namespace App\Support\AiWorkspace;

use App\Models\AiBrainItem;
use App\Models\AiKey;
use App\Models\Client;
use App\Models\User;
use App\Support\Arabic\Normaliser;
use Illuminate\Support\Facades\Cache;

/**
 * AI Workspace M1 item 8 (Product M1 screens §5): the in-app AI box on the agency's own key. Playbook envelope → the
 * provider (JSON for structured types) → one repair try with the exact failed fields → quality gate → saved clean card.
 */
class AiBox
{
    public const SHORT_TOKENS = 400;

    public const SHORT_RULE = 'Keep it short: at most 120 words in total (or the fewest items the schema allows).';

    /** @return array{ok: bool, item?: AiBrainItem, cost: float, error?: string, billing?: bool} */
    public static function run(Client $client, AiKey $key, string $playbook, string $notes, ?User $user, ?string $dialect = null, ?string $platform = null, bool $short = false): array
    {
        if (AiKill::off('ai_box')) { // FS-49
            return ['ok' => false, 'cost' => 0.0, 'error' => __('The AI box is paused for everyone for now. Try again later.')];
        }
        $def = Playbooks::all()[$playbook] ?? null;
        if (! $def) {
            return ['ok' => false, 'cost' => 0.0, 'error' => __('Choose a playbook.')];
        }
        $type = $def['output_type'];
        $env = Playbooks::envelope($client, $playbook, $notes, $dialect, $platform);
        $material = isset(Playbooks::MATERIAL[$playbook]) ? trim(mb_substr(strip_tags($notes), 0, Playbooks::notesMax($playbook))) : '';
        if (($stop = static::precheck($playbook, $env['context'], $material)) !== null) {
            return ['ok' => false, 'cost' => 0.0, 'error' => $stop];
        }

        // FS-15: the text layer decides first; with nothing unclear left, no AI call and no cost.
        $negatives = $playbook === 'dialect_negatives' ? Negatives::classify(Negatives::split($material)) : null;
        if ($negatives !== null && $negatives['unclear'] === []) {
            return static::save($client, $key, $playbook, $def, 'negatives_set', null, Negatives::output($negatives['clear']), [], $user, 0.0, 0, '');
        }
        // NM-07 (R7 end): short mode keeps the answer and its cost small on the agency's own key.
        $system = "You work inside AdsRanger for a marketing agency.\n- ".implode("\n- ", [...$env['rules'], ...($short ? [self::SHORT_RULE] : [])]);
        $user_ = json_encode(['context' => $env['context'], 'task' => $env['task'], 'output_schema' => $env['output_schema']], JSON_UNESCAPED_UNICODE);

        // EG-24: the same request on the same data within 4 hours returns the saved answer, with no new call.
        $cacheKey = 'ai-box:'.$client->id.':'.hash('sha256', $key->provider.'|'.$key->model.'|'.$system.'|'.$user_);
        if (($cachedId = Cache::get($cacheKey)) && ($cached = AiBrainItem::where('client_id', $client->id)->find($cachedId))) {
            return ['ok' => true, 'item' => $cached, 'cost' => 0.0, 'cached' => true];
        }

        $cost = 0.0;
        $redacted = 0;
        $output = null;
        $text = '';
        for ($try = 1; $try <= OutputContract::MAX_TRIES; $try++) {
            $r = OwnKey::complete($key, $system, $user_, $type !== null, $short ? self::SHORT_TOKENS : null);
            $cost += $r['cost'];
            $redacted = max($redacted, (int) ($r['redacted'] ?? 0));
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

        $meta = [];
        if ($negatives !== null && is_array($output)) {
            // Keep only the AI's picks that were in the unclear list, then add the text layer's own and widen all of them.
            $unclear = array_flip(array_map(fn ($t) => Normaliser::key($t), $negatives['unclear']));
            $picked = array_values(array_filter((array) ($output['negatives'] ?? []), fn ($n) => is_array($n) && isset($unclear[Normaliser::key((string) ($n['term'] ?? ''))])));
            $output = Negatives::output([...$negatives['clear'], ...array_map(fn ($n) => ['term' => (string) $n['term'], 'reason' => (string) ($n['reason'] ?? 'ai')], $picked)]);
        }
        if ($playbook === 'translate') {
            $meta['glossary'] = Glossary::check($material, $text, (string) $env['context']['to']); // FS-06
        }
        $r = static::save($client, $key, $playbook, $def, $type, $type === null ? $text : null, $type === null ? null : ($output ?? ['raw' => mb_substr($text, 0, 5000)]), $meta, $user, $cost, $redacted, $material);
        Cache::put($cacheKey, $r['item']->id, now()->addHours(4));

        return $r;
    }

    /** @return array{ok: true, item: AiBrainItem, cost: float, redacted: int, glossary?: array} */
    protected static function save(Client $client, AiKey $key, string $playbook, array $def, ?string $type, ?string $body, ?array $output, array $meta, ?User $user, float $cost, int $redacted, string $material): array
    {
        $saved = Brain::save($client, [
            'type' => 'output', 'title' => ($def[app()->getLocale() === 'ar' ? 'title_ar' : 'title_en']).' · '.now($client->agency?->timezone ?: 'UTC')->format('Y-m-d'),
            'body' => $body, 'output_type' => $type, 'output' => $output, 'material' => $material,
            'try' => OutputContract::MAX_TRIES, 'ai' => AiKey::AS_ASSISTANT[$key->provider] ?? null, 'playbook' => $playbook, 'playbook_version' => Playbooks::VERSION,
            'meta' => ['redacted' => $redacted] + $meta,
        ], $user);

        return ['ok' => true, 'item' => $saved['item'], 'cost' => $cost, 'redacted' => $redacted] + (isset($meta['glossary']) ? ['glossary' => $meta['glossary']] : []);
    }

    /** R7 part 4: what a playbook needs before anything is sent; null when it can run. */
    protected static function precheck(string $playbook, array $context, string $material): ?string
    {
        return match ($playbook) {
            'translate' => $material === '' ? __('Paste the text to translate.') : null,
            'dialect_negatives' => Negatives::split($material) === [] ? __('Paste the search terms, one per line.') : null,
            'alt_text' => ! array_key_exists('site', $context) || $context['site'] === null ? __('Run a site audit for this client first.')
                : (($context['images'] ?? []) === [] ? __('The last site audit found no images without alt text.') : null),
            'audit_summary' => ($context['audit'] ?? null) === null ? __('Run a site audit for this client first.') : null,
            default => null,
        };
    }
}
