<?php

namespace App\Support\AiWorkspace;

use App\Models\AiBrainItem;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use InvalidArgumentException;

/**
 * AI Workspace M1 items 3–6: `save_to_client`. Every save passes the rule clean-up and the quality gate; structured
 * outputs must fit their contract (2 tries, then an internal "needs formatting" draft). Used by the connector, the AI box
 * and night shift alike, so quality never depends on where the text was written (spec v1 §11.8).
 */
class Brain
{
    /**
     * @param  array{type: string, title: string, body?: ?string, output?: ?array, output_type?: ?string, url?: ?string, ai?: ?string,
     *   playbook?: ?string, playbook_version?: ?string, try?: int, client_facing?: bool, allowed_client_ids?: ?list<int>}  $data
     * @return array{ok: bool, item?: AiBrainItem, errors?: list<string>, tries_left?: int}
     */
    public static function save(Client $client, array $data, ?User $user = null): array
    {
        $type = $data['type'] ?? '';
        if (! in_array($type, AiBrainItem::TYPES, true)) {
            throw new InvalidArgumentException("Unknown brain item type: $type");
        }
        if (isset($data['ai']) && ! array_key_exists($data['ai'], AiBrainItem::PROVIDERS)) {
            throw new InvalidArgumentException('Unknown AI: '.$data['ai']);
        }
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        if ($title === '') {
            throw new InvalidArgumentException('A title is required.');
        }

        $outputType = $data['output_type'] ?? null;
        $output = $data['output'] ?? null;
        $state = 'ok';
        if ($outputType !== null) {
            $errors = OutputContract::validate($outputType, $output);
            if ($errors !== []) {
                $try = max(1, (int) ($data['try'] ?? 1));
                if ($try < OutputContract::MAX_TRIES) {
                    return ['ok' => false, 'errors' => $errors, 'tries_left' => OutputContract::MAX_TRIES - $try];
                }
                $state = 'needs_formatting';
            }
        }

        $original = $outputType !== null && is_array($output)
            ? json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : (string) ($data['body'] ?? '');
        $text = $outputType !== null && is_array($output) ? OutputContract::text($outputType, $output) : $original;
        $clean = $type === 'chat_link' ? ['text' => $text, 'hidden' => 0, 'generic' => [], 'actions' => [], 'no_fix' => false] : CleanUp::pass($text);
        $gate = QualityGate::check($clean['text'], ContextPack::for($client)['facts'], [
            'type' => $state === 'ok' ? $outputType : null, 'output' => $output, 'client_facing' => (bool) ($data['client_facing'] ?? false),
            'allowed_client_ids' => $data['allowed_client_ids'] ?? null, 'client_id' => $client->id,
        ]);
        if ($state === 'needs_formatting') {
            $gate['band'] = 'blocked';
            $gate['blocking'] = true;
        }

        $item = AiBrainItem::create([
            'agency_id' => $client->agency_id, 'client_id' => $client->id, 'user_id' => $user?->id, 'type' => $type, 'title' => mb_substr($title, 0, 250),
            'original' => $original, 'clean' => $gate['text'], 'output' => $output, 'output_type' => $outputType,
            'url' => $type === 'chat_link' ? static::safeUrl($data['url'] ?? null) : null,
            'ai_provider' => $data['ai'] ?? null, 'playbook' => $data['playbook'] ?? null, 'playbook_version' => $data['playbook_version'] ?? null,
            'state' => $state, 'score' => $gate['score'], 'band' => $gate['band'], 'blocking' => $gate['blocking'], 'checks' => $gate['checks'],
            'cleanup' => ['hidden' => $clean['hidden'], 'generic' => $clean['generic'], 'actions' => $clean['actions'], 'no_fix' => $clean['no_fix'],
                'fixed' => $gate['fixed'], 'suggested' => $gate['suggested']],
        ]);
        AuditLog::record('ai_workspace.saved', $client, ['item' => $item->id, 'type' => $type, 'ai' => $data['ai'] ?? null, 'band' => $gate['band']]);

        return ['ok' => true, 'item' => $item];
    }

    /** Chat links: https only, shown as a plain link; anything else is dropped. */
    protected static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return preg_match('#^https://[^\s<>"]+$#i', $url) ? mb_substr($url, 0, 500) : null;
    }
}
