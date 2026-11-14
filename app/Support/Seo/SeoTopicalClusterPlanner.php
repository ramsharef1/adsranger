<?php

namespace App\Support\Seo;

use App\Models\Client;
use App\Models\SeoTopicCluster;

/**
 * E2-070: Topical cluster & pillar-spoke silo planner with bilingual hreflang pair validation
 * and internal anchor rules.
 */
class SeoTopicalClusterPlanner
{
    /**
     * @param  list<array{title: string, url: string, locale?: string, status?: string}>  $spokes
     * @param  list<string>  $anchorRules
     */
    public static function saveCluster(
        Client $client,
        string $pillarTitle,
        string $pillarUrl,
        array $spokes = [],
        array $anchorRules = [],
        ?string $briefNotes = null,
        ?int $clusterId = null,
    ): SeoTopicCluster {
        $cleanSpokes = [];
        $localesSeen = [];

        foreach (array_slice($spokes, 0, 50) as $s) {
            $title = mb_substr(trim((string) ($s['title'] ?? '')), 0, 200);
            $url = mb_substr(trim((string) ($s['url'] ?? '')), 0, 500);
            if ($title === '' && $url === '') {
                continue;
            }
            $loc = in_array($s['locale'] ?? 'ar', ['ar', 'en'], true) ? $s['locale'] : 'ar';
            $localesSeen[$loc] = true;
            $cleanSpokes[] = [
                'title' => $title,
                'url' => $url,
                'locale' => $loc,
                'status' => in_array($s['status'] ?? 'planned', ['planned', 'live'], true) ? $s['status'] : 'planned',
            ];
        }

        $cleanAnchors = array_values(array_filter(array_map(
            fn ($a) => mb_substr(trim((string) $a), 0, 160),
            array_slice($anchorRules, 0, 30)
        )));

        $pairStatus = isset($localesSeen['ar'], $localesSeen['en'])
            ? 'paired_planned'
            : ($cleanSpokes === [] ? 'unchecked' : 'missing_language_twin');

        $payload = [
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'pillar_title' => mb_substr(trim($pillarTitle), 0, 255),
            'pillar_url' => mb_substr(trim($pillarUrl), 0, 500),
            'spokes' => $cleanSpokes,
            'hreflang_pair_status' => $pairStatus,
            'anchor_rules' => $cleanAnchors,
            'brief_notes' => $briefNotes !== null ? mb_substr(trim($briefNotes), 0, 4000) : null,
        ];

        if ($clusterId !== null) {
            $existing = SeoTopicCluster::where('agency_id', $client->agency_id)
                ->where('client_id', $client->id)
                ->find($clusterId);
            if ($existing) {
                $existing->update($payload);

                return $existing;
            }
        }

        return SeoTopicCluster::create($payload);
    }

    /**
     * Checks live pillar HTML via SafeFetcher for reciprocal ar + en hreflang alternates.
     */
    public static function verifyPillarHreflang(SeoTopicCluster $cluster): string
    {
        if (! preg_match('#^https?://#i', $cluster->pillar_url)) {
            $cluster->update(['hreflang_pair_status' => 'relative_url_unchecked']);

            return 'relative_url_unchecked';
        }

        $res = SafeFetcher::fetch($cluster->pillar_url);
        if (! $res['ok']) {
            $cluster->update(['hreflang_pair_status' => 'unreachable']);

            return 'unreachable';
        }

        preg_match_all('/<link[^>]+rel=["\']alternate["\'][^>]+hreflang=["\']([a-z\-]+)["\']/i', $res['html'], $m);
        $langs = array_map(fn (string $l): string => strtolower(explode('-', $l)[0]), $m[1] ?? []);
        $status = (in_array('ar', $langs, true) && in_array('en', $langs, true))
            ? 'verified_ar_en'
            : 'missing_hreflang_pair';

        $cluster->update(['hreflang_pair_status' => $status]);

        return $status;
    }
}
