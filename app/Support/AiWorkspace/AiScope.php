<?php

namespace App\Support\AiWorkspace;

use App\Models\Client;

/** FS-02 (D-299): what may go to AI about one client. Everything is on by default; the agency unticks parts per client. */
class AiScope
{
    /** part => the context-pack fact labels it covers (rules = the client's saved rules). */
    public const PARTS = [
        'spend' => ['Spend', 'Cost per result', 'ROAS', 'Revenue'],
        'results' => ['Results', 'CTR %', 'Impressions', 'Link clicks'],
        'rules' => [],
    ];

    /** @return list<string> */
    public static function allowed(Client $c): array
    {
        $s = $c->ai_scope;

        return is_array($s) ? array_values(array_intersect(array_keys(self::PARTS), $s)) : array_keys(self::PARTS);
    }

    /**
     * The context pack with the parts this client allows (facts by label, incl. their "change %" lines; rules).
     *
     * @param  array<string, mixed>  $pack
     * @return array<string, mixed>
     */
    public static function apply(Client $c, array $pack): array
    {
        $allowed = self::allowed($c);
        $labels = collect(self::PARTS)->only($allowed)->flatten()->all();
        $pack['facts'] = array_values(array_filter($pack['facts'], function (array $f) use ($labels) {
            $base = preg_replace('/ change %$/', '', $f['label']);

            return in_array($base, $labels, true);
        }));
        if (! in_array('rules', $allowed, true)) {
            $pack['rules'] = [];
        }
        $pack['scope'] = $allowed;

        return $pack;
    }
}
