<?php

namespace App\Support;

use App\Models\Client;
use App\Models\PsychologicalHookScore;

class PsychologicalTriggerAnalyzer
{
    public static function analyzeAndScore(Client $client, string $copy, string $dialect = 'gulf'): PsychologicalHookScore
    {
        $text = trim($copy);
        $lower = mb_strtolower($text);

        $urgencyMarkers = ['الآن', 'اليوم', 'فورا', 'محدود', 'قبل انتهاء', 'now', 'today', 'limited', 'hurry'];
        $fomoMarkers = ['لا تفوت', 'باقي', 'فرصة', 'الجميع', 'حصري', 'dont miss', 'exclusive', 'last chance'];
        $authorityMarkers = ['معتمد', 'خبراء', 'ضمان', 'موثوق', 'طبي', 'certified', 'proven', 'guaranteed', 'official'];
        $aspirationMarkers = ['أفضل', 'نجاح', 'تألق', 'مستقبل', 'راحة', 'transform', 'dream', 'upgrade', 'premium'];

        $scoreFor = function (array $markers) use ($lower): int {
            $hits = 0;
            foreach ($markers as $m) {
                if (mb_strpos($lower, $m) !== false) {
                    $hits++;
                }
            }

            // No matching word means 0. The old base of 55 gave every text a pass mark it had not earned.
            return min(100, $hits * 35);
        };

        $triggers = [
            'urgency' => $scoreFor($urgencyMarkers),
            'fomo' => $scoreFor($fomoMarkers),
            'authority' => $scoreFor($authorityMarkers),
            'aspiration' => $scoreFor($aspirationMarkers),
        ];

        $overall = (int) round(array_sum($triggers) / count($triggers));

        // Openers only. The earlier variants added "guaranteed" and "certified", claims the advertiser never made.
        $variants = [
            'gulf' => "لا يطوفك — {$text}",
            'levantine' => "ما تفوّت الفرصة — {$text}",
            'egyptian' => "ما تفوّتش الفرصة — {$text}",
            'en' => "Don't miss it — {$text}",
        ];

        return PsychologicalHookScore::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'dialect' => $dialect,
            'original_copy' => $text,
            'overall_score' => $overall,
            'trigger_scores' => $triggers,
            'dialect_variants' => $variants,
        ]);
    }
}
