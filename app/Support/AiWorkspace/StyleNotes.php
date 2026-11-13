<?php

namespace App\Support\AiWorkspace;

use App\Models\AiBrainItem;
use App\Models\Client;
use App\Models\User;

/**
 * FS-05 (D-300): when a member edits an AI draft, each word swap ("زبائن" → "عملاء") becomes a short style note in the
 * client brain — shown, deletable, and sent to the AI as a client rule. At most 3 notes per edit; numbers are never notes.
 */
class StyleNotes
{
    /** @return list<array{from: string, to: string}> word-level swaps between two texts (same position, both sides 1–3 words) */
    public static function swaps(string $before, string $after): array
    {
        $a = preg_split('/\s+/u', trim($before), -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('/\s+/u', trim($after), -1, PREG_SPLIT_NO_EMPTY);
        // Trim the shared head and tail, then read the changed middle as runs.
        $out = [];
        $i = 0;
        $j = 0;
        while ($i < count($a) && $j < count($b)) {
            if ($a[$i] === $b[$j]) {
                $i++;
                $j++;

                continue;
            }
            // Find the next word both sides share again (up to 3 words ahead).
            $found = false;
            for ($da = 0; $da <= 3 && ! $found; $da++) {
                for ($db = 0; $db <= 3 && ! $found; $db++) {
                    if ($da + $db === 0) {
                        continue;
                    }
                    if (isset($a[$i + $da], $b[$j + $db]) && $a[$i + $da] === $b[$j + $db]) {
                        $from = self::bare(implode(' ', array_slice($a, $i, $da)));
                        $to = self::bare(implode(' ', array_slice($b, $j, $db)));
                        if ($from !== '' && $to !== '' && ! preg_match('/\d/u', $from.$to)) {
                            $out[] = ['from' => $from, 'to' => $to];
                        }
                        $i += $da;
                        $j += $db;
                        $found = true;
                    }
                }
            }
            if (! $found) {
                break;
            }
        }

        return array_slice(array_values(array_unique($out, SORT_REGULAR)), 0, 3);
    }

    /** Strips edge punctuation (Arabic and Latin) without breaking multibyte letters. */
    protected static function bare(string $s): string
    {
        return (string) preg_replace('/^[\p{P}\s]+|[\p{P}\s]+$/u', '', $s);
    }

    /** Saves the swaps of one edit as rule items; returns how many were new. */
    public static function learn(Client $client, string $before, string $after, ?User $user): int
    {
        $n = 0;
        foreach (self::swaps($before, $after) as $s) {
            $title = mb_substr('«'.$s['to'].'» ← «'.$s['from'].'»', 0, 200);
            if (AiBrainItem::where('client_id', $client->id)->where('type', 'rule')->where('title', $title)->exists()) {
                continue;
            }
            Brain::save($client, ['type' => 'rule', 'title' => $title, 'body' => 'Write «'.$s['to'].'», not «'.$s['from'].'» (learned from an edit).', 'playbook' => 'learned_edit'], $user);
            $n++;
        }

        return $n;
    }
}
