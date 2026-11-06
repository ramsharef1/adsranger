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
            return min(100, 55 + ($hits * 15));
        };

        $triggers = [
            'urgency' => $scoreFor($urgencyMarkers),
            'fomo' => $scoreFor($fomoMarkers),
            'authority' => $scoreFor($authorityMarkers),
            'aspiration' => $scoreFor($aspirationMarkers),
        ];

        $overall = (int) round(array_sum($triggers) / count($triggers));

        $variants = [
            'gulf' => "لا يطوفك العرض الحصري — {$text} (مضمون ومعتمد في الخليج)",
            'levantine' => "ما تروّح الفرصة عليك اليوم — {$text} (بأعلى جودة وثقة)",
            'egyptian' => "إلحق العرض الحصري دلوقتي — {$text} (بضمان كامل)",
            'en' => "Don't miss this exclusive offer today — {$text} (100% Guaranteed)",
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