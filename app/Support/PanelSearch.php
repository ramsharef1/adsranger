<?php

namespace App\Support;

use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Support\Arabic\Normaliser;

/**
 * PS-23 / XS-46 / FS-52 (R5): Arabic-aware panel search. «الريّان», «الريان», "rayan" and "al rayyan" find the same
 * client or campaign: Arabic is normalised (hamza, ta marbuta, ya, diacritics, tatweel, digits), Arabizi is read as
 * Arabic, and Arabic is matched against Latin names through its Arabizi forms. Only clients this person can see.
 */
class PanelSearch
{
    /** @return list<string> forms of the query to look for, each already folded */
    public static function needles(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $out = [static::fold($q)];
        if (preg_match('/^[a-z0-9\s\'\-]+$/i', $q)) {
            foreach (array_slice(Normaliser::arabiziToArabic($q), 0, 3) as $form) {
                $out[] = static::fold($form);
            }
        } elseif (preg_match('/\p{Arabic}/u', $q)) {
            foreach (Normaliser::arabicToArabizi($q) as $form) {
                // Arabic has no short vowels: "الريان" → "alryan" must still find "Al Rayyan", so compare consonants.
                $sk = static::skeleton(static::fold($form));
                if (mb_strlen($sk) >= 3) {
                    $out[] = '~'.$sk;
                }
            }
        }

        return array_values(array_unique(array_filter($out, fn ($n) => mb_strlen($n) >= 2)));
    }

    /** Normalised, lower case, without spaces or dashes, doubled Latin letters squeezed ("al rayyan" → "alrayan"). */
    public static function fold(string $text): string
    {
        $t = str_replace([' ', '-', '_', '.', '\''], '', Normaliser::key($text));

        return (string) preg_replace('/([a-z])\1+/', '$1', $t);
    }

    public static function skeleton(string $latin): string
    {
        return (string) preg_replace('/[aeiou]/', '', $latin);
    }

    public static function matches(string $name, array $needles): bool
    {
        $hay = static::fold($name);
        $sk = null;
        foreach ($needles as $n) {
            if (str_starts_with($n, '~')) {
                $sk ??= static::skeleton($hay);
                if (str_contains($sk, substr($n, 1))) {
                    return true;
                }
            } elseif ($n !== '' && str_contains($hay, $n)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{clients: list<Client>, campaigns: list<array{campaign: Campaign, client: Client}>} */
    public static function find(Agency $a, ?User $user, string $q, int $limit = 8): array
    {
        $needles = static::needles($q);
        if (! $needles) {
            return ['clients' => [], 'campaigns' => []];
        }
        $clients = Client::visibleTo($user, $a)->where('status', '!=', 'archived')->get(['id', 'name', 'status', 'agency_id']);
        $hitClients = $clients->filter(fn (Client $c) => static::matches((string) $c->name, $needles))->take($limit)->values()->all();

        $byAccount = AdAccount::whereIn('client_id', $clients->pluck('id'))->pluck('client_id', 'id');
        $campaigns = [];
        Campaign::whereIn('ad_account_id', $byAccount->keys())->whereNotNull('name')->orderByDesc('id')->limit(3000)->get(['id', 'name', 'ad_account_id'])
            ->each(function (Campaign $cp) use (&$campaigns, $needles, $byAccount, $clients, $limit) {
                if (count($campaigns) < $limit && static::matches((string) $cp->name, $needles)) {
                    $campaigns[] = ['campaign' => $cp, 'client' => $clients->firstWhere('id', $byAccount[$cp->ad_account_id])];
                }
            });

        return ['clients' => $hitClients, 'campaigns' => $campaigns];
    }
}
