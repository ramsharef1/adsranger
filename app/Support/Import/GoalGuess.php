<?php

namespace App\Support\Import;

use App\Support\TextClean;

/** PS-03 (D-290): the result type a campaign is most likely after, from Meta's result indicator or name cues. A guess the user confirms. */
class GoalGuess
{
    /** Ordered: the first matching cue wins. */
    public const NAME_CUES = [
        'message' => '/رسائل|رساله|رسالة|واتساب|واتس|محادث|whats ?app|(?<![a-z])wa(?![a-z])|messag|(?<![a-z])msg|(?<![a-z])chat|(?<![a-z])dm(?![a-z])/iu',
        'call' => '/اتصال|مكالم|(?<![a-z])calls?(?![a-z])/iu',
        'lead' => '/حجز|حجوزات|تسجيل|عملاء محتمل|استمار|(?<![a-z])leads?(?![a-z])|(?<![a-z])forms?(?![a-z])|booking|signup|sign-up/iu',
        'purchase' => '/طلب|طلبات|شراء|مبيعات|متجر|(?<![a-z])sales(?![a-z])|purchase|(?<![a-z])orders?(?![a-z])|(?<![a-z])shop(?![a-z])|(?<![a-z])conv(?![a-z])|catalog|(?<![a-z])dpa(?![a-z])/iu',
        'app_install' => '/تحميل التطبيق|تثبيت|\binstalls?\b|\bapp\b/iu',
        'thruplay' => '/مشاهد|فيديو|\bvideo\b|\bviews?\b|thruplay/iu',
        'reach' => '/وصول|انتشار|\breach\b|awareness|\bbrand\b/iu',
        'link_click' => '/زيارات|زيارة|\btraffic\b|clicks?\b/iu',
    ];

    /** Meta's "Result indicator" values (actions:…, offsite_conversion.fb_pixel_…) to our result types. */
    public static function fromIndicator(?string $indicator): ?string
    {
        $i = strtolower(trim((string) $indicator));
        if ($i === '') {
            return null;
        }
        $map = [
            'purchase' => 'purchase', 'lead' => 'lead', 'messaging' => 'message', 'onsite_conversion.total_messaging' => 'message',
            'complete_registration' => 'registration', 'link_click' => 'link_click', 'landing_page_view' => 'landing_page_view',
            'video_thruplay' => 'thruplay', 'thruplay' => 'thruplay', 'reach' => 'reach', 'app_install' => 'app_install',
            'mobile_app_install' => 'app_install', 'page_like' => 'page_like', 'post_engagement' => 'engagement', 'call' => 'call',
        ];
        foreach ($map as $needle => $type) {
            if (str_contains($i, $needle)) {
                return $type;
            }
        }

        return array_key_exists($i, (array) config('results.labels')) ? $i : null;
    }

    public static function fromName(?string $name): ?string
    {
        $n = (string) TextClean::invisible((string) $name);
        foreach (self::NAME_CUES as $type => $re) {
            if (preg_match($re, $n)) {
                return $type;
            }
        }

        return null;
    }
}
