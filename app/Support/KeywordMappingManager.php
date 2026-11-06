<?php

namespace App\Support;

use App\Models\Client;
use App\Models\IntentKeywordMap;

class KeywordMappingManager
{
    public static function generateForClient(Client $client, string $service, string $locale = 'ar'): IntentKeywordMap
    {
        $base = trim($service);
        $isAr = str_starts_with(strtolower($locale), 'ar');

        $buckets = $isAr ? [
            'transactional' => ["شراء {$base}", "حجز {$base} الآن", "افضل سعر {$base}"],
            'commercial' => ["افضل {$base}", "مقارنة {$base}", "شركة {$base} موثوقة"],
            'informational' => ["ما هو {$base}", "كيف يعمل {$base}", "مزايا {$base}"],
        ] : [
            'transactional' => ["buy {$base}", "book {$base} online", "{$base} pricing"],
            'commercial' => ["best {$base} provider", "top {$base} agency", "{$base} reviews"],
            'informational' => ["what is {$base}", "how {$base} works", "{$base} guide"],
        ];

        $adGroups = [
            'google_ads' => [
                [
                    'ad_group_name' => $base . ' - High Intent Exact',
                    'match_type' => 'exact',
                    'keywords' => array_map(fn ($k) => "[{$k}]", $buckets['transactional']),
                ],
                [
                    'ad_group_name' => $base . ' - Commercial Phrase',
                    'match_type' => 'phrase',
                    'keywords' => array_map(fn ($k) => "\"{$k}\"", $buckets['commercial']),
                ],
            ],
            'microsoft_ads' => [
                [
                    'ad_group_name' => $base . ' - Bing Transactional',
                    'match_type' => 'exact',
                    'keywords' => array_map(fn ($k) => "[{$k}]", $buckets['transactional']),
                ],
            ],
        ];

        $negatives = $isAr
            ? ['مجانا', 'وظائف', 'تحميل', 'مستعمل']
            : ['free', 'jobs', 'career', 'torrent'];

        return IntentKeywordMap::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'target_service' => $base,
            'locale' => $locale,
            'intent_buckets' => $buckets,
            'ad_group_structures' => $adGroups,
            'negative_keywords' => $negatives,
        ]);
    }
}