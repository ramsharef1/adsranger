<?php

namespace App\Support\Seo;

class SiteAuditRunner
{
    /**
     * @param array<string, string> $pagesMap url_or_id => html
     * @return array{pages_audited: int, average_score: int, simulated: bool, duplicate_titles: list<string>, duplicate_descriptions: list<string>, issue_counts: array<string, int>, pages: array<string, array<string, mixed>>}
     */
    public static function auditBatch(array $pagesMap, ?string $keyword = null, ?string $country = null, bool $simulated = false): array
    {
        $results = [];
        $issueCounts = [];
        $titles = [];
        $descs = [];
        $totalScore = 0;

        foreach ($pagesMap as $url => $html) {
            $audit = PageAudit::auditHtml((string) $html, $keyword, $country);
            $results[$url] = $audit;
            $totalScore += $audit["score"];

            if (preg_match("/<title[^>]*>(.*?)<\\/title>/is", (string) $html, $tm)) {
                $t = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES | ENT_HTML5, "UTF-8"));
                if ($t !== "") {
                    $titles[$t][] = (string) $url;
                }
            }
            if (preg_match("/<meta[^>]*name=[\"']description[\"'][^>]*content=[\"']([^\"']*)[\"']/is", (string) $html, $dm)) {
                $d = trim(html_entity_decode($dm[1], ENT_QUOTES | ENT_HTML5, "UTF-8"));
                if ($d !== "") {
                    $descs[$d][] = (string) $url;
                }
            }
            foreach ($audit["issues"] as $iss) {
                $issueCounts[$iss] = ($issueCounts[$iss] ?? 0) + 1;
            }
        }

        $dupTitles = [];
        foreach ($titles as $t => $urls) {
            if (count($urls) > 1) {
                $dupTitles[] = $t;
                $issueCounts["duplicate_title"] = ($issueCounts["duplicate_title"] ?? 0) + count($urls);
            }
        }

        $dupDescs = [];
        foreach ($descs as $d => $urls) {
            if (count($urls) > 1) {
                $dupDescs[] = $d;
                $issueCounts["duplicate_meta_description"] = ($issueCounts["duplicate_meta_description"] ?? 0) + count($urls);
            }
        }

        $count = count($results);
        return [
            "pages_audited" => $count,
            "average_score" => $count > 0 ? (int) round($totalScore / $count) : 0,
            "simulated" => $simulated,
            "duplicate_titles" => $dupTitles,
            "duplicate_descriptions" => $dupDescs,
            "issue_counts" => $issueCounts,
            "pages" => $results,
        ];
    }
}
