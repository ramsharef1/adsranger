<?php

namespace App\Support;

use App\Models\Client;

/**
 * #131: Dialect-aware ad copy generator (Gulf, Levantine, Egyptian, MSA Arabic + English + French).
 */
class StudioCopyGenerator
{
    public const DIALECTS = [
        'gulf' => 'Arabic · Gulf (خليجي)',
        'levantine' => 'Arabic · Levantine (شامي)',
        'egyptian' => 'Arabic · Egyptian (مصري)',
        'msa' => 'Arabic · Modern Standard (فصحى)',
        'en' => 'English',
        'fr' => 'Français',
    ];

    public const PLACEMENTS = [
        'feed' => 'Feed (1:1 · Meta / IG / LinkedIn)',
        'story' => 'Stories & Reels (9:16 · TikTok / Snap / IG)',
        'search' => 'Search RSA (Google / Microsoft)',
        'native' => 'Native (16:9 · Taboola / Outbrain)',
    ];

    /** @return array{hook:string,primary_text:string,headline:string,cta:string} */
    public static function generate(Client $client, string $dialect, string $objective = 'lead', ?string $offer = null): array
    {
        $name = $client->name;
        $offerText = trim((string) $offer) ?: ($dialect === 'en' ? 'Book your consultation today' : ($dialect === 'fr' ? 'Profitez de notre offre maintenant' : 'احجز استشارتك اليوم'));

        return match ($dialect) {
            'gulf' => [
                'hook' => "تبغى نتائج أسرع مع {$name}؟ ✨",
                'primary_text' => "جهزنا لك في {$name} عرضاً خاصاً يناسب احتياجك: {$offerText}. تواصل معنا الحين وخلك بالمقدمة.",
                'headline' => "{$name} · {$offerText}",
                'cta' => $objective === 'purchase' ? 'اطلب الحين' : 'تواصل معنا',
            ],
            'levantine' => [
                'hook' => "بدك أفضل نتيجة مع {$name}؟ ✨",
                'primary_text' => "مع {$name} صار فيك تحصل على {$offerText} بخطوات بسيطة وسريعة. احكي معنا اليوم وابدأ فوراً.",
                'headline' => "{$name} · {$offerText}",
                'cta' => $objective === 'purchase' ? 'اطلب هلق' : 'تواصل معنا',
            ],
            'egyptian' => [
                'hook' => "بتدور على أحسن تجربة مع {$name}؟ ✨",
                'primary_text' => "دلوقتي مع {$name} وفرنالك {$offerText} بأفضل قيمة وأسرع خدمة. ابعتلنا دلوقتي واعرف التفاصيل.",
                'headline' => "{$name} · {$offerText}",
                'cta' => $objective === 'purchase' ? 'اطلب دلوقتي' : 'كلمنا الآن',
            ],
            'msa' => [
                'hook' => "اكتشف الحل الأمثل مع {$name}",
                'primary_text' => "يقدّم لكم {$name} تجربة متكاملة: {$offerText}. تواصلوا معنا اليوم للبدء بخطوات واضحة ومضمونة.",
                'headline' => "{$name} · {$offerText}",
                'cta' => $objective === 'purchase' ? 'تسوق الآن' : 'سجّل الآن',
            ],
            'fr' => [
                'hook' => "Passez au niveau supérieur avec {$name}",
                'primary_text' => "Découvrez l'offre {$name} : {$offerText}. Des résultats mesurables dès la première semaine.",
                'headline' => "{$name} · {$offerText}",
                'cta' => $objective === 'purchase' ? 'Acheter' : 'En savoir plus',
            ],
            default => [
                'hook' => "Ready for faster results with {$name}?",
                'primary_text' => "{$name} brings you {$offerText} with clear tracking and measurable ROI from day one.",
                'headline' => "{$name} · {$offerText}",
                'cta' => $objective === 'purchase' ? 'Shop Now' : 'Learn More',
            ],
        };
    }
}
