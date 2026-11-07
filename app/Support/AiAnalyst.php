<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class AiAnalyst
{
    public static function analyze(string $prompt, string $locale = 'ar'): string
    {
        $cacheKey = 'ai_analyst_'.md5($prompt.'_'.$locale);

        return Cache::remember($cacheKey, now()->addHours(4), function () use ($prompt, $locale) {
            $isAr = $locale === 'ar' || preg_match('/\p{Arabic}/u', $prompt) === 1;

            // No AI provider is connected yet (#159): say so plainly instead of returning canned "insights" (ADSR-110 §3: no fake success).
            return $isAr
                ? 'نص تجريبي: لم يُربط مزوّد ذكاء اصطناعي بعد، لذلك لا يوجد تحليل حقيقي.'
                : 'Sample text: no AI provider is connected yet, so this is not a real analysis.';
        });
    }
}
