<?php

namespace App\Support\Import;

/** PS-01: which network an export came from, by the column names only it uses (English or Arabic Ads Manager). */
class NetworkGuess
{
    /** Normalised header names that only one network's export uses. */
    public const SIGNS = [
        'meta' => ['amount spent', 'reporting starts', 'reporting ends', 'ad set name', 'result indicator', 'cost per result', 'cpm (cost per 1,000 impressions)',
            'المبلغ الذي تم إنفاقه', 'المبلغ المنفق', 'بدء إعداد التقارير', 'انتهاء إعداد التقارير', 'اسم مجموعة الإعلانات', 'اسم المجموعة الإعلانية', 'مؤشر النتائج', 'التكلفة لكل نتيجة'],
        'google_ads' => ['impr.', 'conv.', 'avg. cpc', 'interactions', 'cost / conv.', 'search impr. share', 'campaign type', 'conv. value', 'all conv.', 'interaction rate'],
        'tiktok' => ['video views at 25%', 'video views at 100%', 'cpc (destination)', 'ctr (destination)', 'clicks (destination)', 'by day', 'cost per 1,000 people reached'],
        'snapchat' => ['paid impressions', 'swipe ups', 'swipe up rate', 'ecpsu', 'story opens', 'attachment avg view time'],
    ];

    /** @param  list<string>  $normalisedHeaders */
    public static function guess(array $normalisedHeaders): ?string
    {
        $best = null;
        $bestScore = 0;
        foreach (self::SIGNS as $network => $signs) {
            $score = count(array_intersect($normalisedHeaders, $signs));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$network, $score];
            }
        }

        return $best;
    }
}
