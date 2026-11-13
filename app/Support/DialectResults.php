<?php

namespace App\Support;

use App\Models\Ad;
use App\Models\Client;
use App\Support\Arabic\Normaliser;
use App\Support\Arabic\Phrases;

/**
 * PS-22 (R5): cost per result by dialect for one client. An ad's dialect is what the agency set, else a guess from
 * dialect marker words in the ad name (shown as a guess), else "not tagged". Ads carry no text body in our data yet,
 * so tagging by hand is the reliable path.
 */
class DialectResults
{
    protected const FROM_TAG = ['gulf' => 'gulf', 'levant' => 'levantine', 'egypt' => 'egyptian', 'maghreb' => 'maghrebi'];

    public static function guess(?string $name): ?string
    {
        $tags = Normaliser::dialectTags((string) $name);

        return count($tags) === 1 ? (self::FROM_TAG[$tags[0]] ?? null) : null;
    }

    /**
     * @return array{rows: list<array{dialect: string, ads: int, spend: float, results: float, cpr: ?float}>, ads: list<array{id: int, name: string, spend: float, dialect: ?string, guess: ?string}>, tagged: int}
     */
    public static function summary(Client $c, string $from, string $to): array
    {
        $ads = MetricsQuery::breakdown($c, $from, $to, 'ad');
        $set = Ad::whereIn('id', array_column($ads, 'ref'))->pluck('dialect', 'id');
        $rows = [];
        $list = [];
        $tagged = 0;
        foreach ($ads as $a) {
            $own = in_array($set[$a['ref']] ?? null, Phrases::DIALECTS, true) ? $set[$a['ref']] : null;
            $guess = $own ? null : static::guess($a['name']);
            $d = $own ?? $guess ?? 'untagged';
            $tagged += $own ? 1 : 0;
            $r = $rows[$d] ?? ['dialect' => $d, 'ads' => 0, 'spend' => 0.0, 'results' => 0.0, 'cpr' => null];
            $r['ads']++;
            $r['spend'] += (float) $a['spend'];
            $r['results'] += (float) $a['main_results'];
            $rows[$d] = $r;
            $list[] = ['id' => (int) $a['ref'], 'name' => (string) $a['name'], 'spend' => (float) $a['spend'], 'dialect' => $own, 'guess' => $guess];
        }
        foreach ($rows as &$r) {
            $r['cpr'] = $r['results'] > 0 ? round($r['spend'] / $r['results'], 2) : null;
        }
        unset($r);
        $rows = array_values($rows);
        usort($rows, fn ($x, $y) => [$x['dialect'] === 'untagged', -$x['spend']] <=> [$y['dialect'] === 'untagged', -$y['spend']]);

        return ['rows' => $rows, 'ads' => array_slice($list, 0, 15), 'tagged' => $tagged];
    }
}
