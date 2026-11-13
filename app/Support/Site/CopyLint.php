<?php

namespace App\Support\Site;

/**
 * GB-06 + GB-38 (IDEA-053): Growth's public-copy rules (claude/site/copy_rules_v1.json, CR-01…CR-21) as a check
 * on rendered pages. Text = visible text of <main>, the <title> and the meta description. "error" fails
 * PublicCopyLintTest; "warn" is reported only. Not built yet: CR-12 (Latin outside <bdi>, covered in part by
 * ArabicQualityTest) and CR-21 (in-development label, needs Growth's product-page list). CR-22 / CR-23 added in cl101.
 */
class CopyLint
{
    /** @return list<array{id: string, lang: string, severity: string, pattern: string, allow_paths?: list<string>, allow_sentence?: string}> */
    public static function textRules(): array
    {
        return [
            ['id' => 'CR-01', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/semrush|ahrefs|\bmoz\b|similarweb|ubersuggest|spyfu|سيمراش|أهرفس/iu'],
            ['id' => 'CR-02', 'lang' => 'en', 'severity' => 'error', 'pattern' => '/\bguarantee(d|s)?\b|\bultimate\b|\bskyrocket|#1\b|\bmagic(al)?\b|\bpurpose-built\b/iu', 'allow_paths' => ['/en/tools/gulf-ad-disclosure-checklist']],
            ['id' => 'CR-03', 'lang' => 'en', 'severity' => 'warn', 'pattern' => '/\bbest\b(?! guess)|\bpowerful\b|\bperfect\b|\binstantly\b|\bboost\b/iu'],
            ['id' => 'CR-04', 'lang' => 'ar', 'severity' => 'error', 'pattern' => '/مضمون|الأفضل|أقوى|سحري|بضغطة زر/u', 'allow_paths' => ['/tools/gulf-ad-disclosure-checklist']],
            // Allowed (D-241): read-only client / report links and the owner's role preview; /for-clients is about those links only.
            ['id' => 'CR-05', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/read-only|قراءة فقط/iu', 'allow_sentence' => '/client link|report link|read-only links?|رابط|روابط|preview|معاينة/iu', 'allow_paths' => ['/for-clients', '/en/for-clients']],
            ['id' => 'CR-06', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/\$\s?\d|\d\s?USD\s*\/\s*(mo|month)|دولار شهري/iu', 'allow_paths' => ['/tools/*', '/en/tools/*']],
            ['id' => 'CR-07', 'lang' => 'ar', 'severity' => 'error', 'pattern' => '/(?<![\p{Arabic}\w])[وبلك][A-Za-z]/u'],
            ['id' => 'CR-08', 'lang' => 'ar', 'severity' => 'error', 'pattern' => '/جوجل|غوغل|ميتا|فيسبوك|فيس بوك|إنستغرام|انستغرام|انستقرام|تيك ?توك|سناب ?شات|واتساب|واتس اب|لينكد ?إن|يوتيوب/u'],
            // Narrowed from Growth's v1: a verb after "AdsRanger" is usually another subject's («تقارير AdsRanger يختصر»), and AdsRangerBot is masculine.
            ['id' => 'CR-09', 'lang' => 'ar', 'severity' => 'error', 'pattern' => '/(?<![\p{Arabic}\p{L}])ي\p{Arabic}+ AdsRanger(?![A-Za-z])(?! (?:في|من|على))|AdsRanger هو(?!\p{Arabic})/u'],
            ['id' => 'CR-13', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/available now|متاح الآن|متوفر الآن/iu'],
            ['id' => 'CR-14', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/\bcompliant\b|non-compliant|متوافق مع (الأنظمة|القانون)|approved by/iu'],
            ['id' => 'CR-15', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/(launch\w*|الإطلاق)[^.؟!]{0,40}(january|february|march|april|may|june|july|august|september|october|november|december|يناير|فبراير|مارس|أبريل|مايو|يونيو|يوليو|أغسطس|سبتمبر|أكتوبر|نوفمبر|ديسمبر)\s*2027/iu', 'allow_paths' => ['/ramadan-2027', '/en/ramadan-2027', '/mena-calendar', '/en/mena-calendar']],
            ['id' => 'CR-16', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/[٠-٩]/u'],
            // CR-22 / CR-23 (Growth, Rami 14:28 "Errors now, with the rewording"): no email promises until a provider is set up
            // (D-266); no "free forever" and no "unlimited" without a fair-use note in the same sentence (UX-270).
            ['id' => 'CR-22', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/we(\'|’)ll (email|notify) you|email you (at|when)|get (an|one) email|سنراسلك|نراسلك|نخبرك عند|سنرسل (لك|إليك)/iu'],
            ['id' => 'CR-23', 'lang' => 'all', 'severity' => 'error', 'pattern' => '/free forever|مجان(ي|ًا|ية) (للأبد|إلى الأبد|دائمًا)|\bunlimited\b|غير محدود/iu', 'allow_sentence' => '/fair use|الاستخدام العادل/iu'],
        ];
    }

    /** @return array{lang: string, title: string, meta: string, text: string, has_faq_schema: bool, has_faq_block: bool, alternates: array<string, string>} */
    public static function parse(string $html): array
    {
        $lang = preg_match('/<html[^>]*\blang="([a-z]{2})/i', $html, $m) ? strtolower($m[1]) : 'ar';
        $title = preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) ? static::clean($m[1]) : '';
        $meta = preg_match('/<meta[^>]+name="description"[^>]+content="([^"]*)"/i', $html, $m) ? static::clean($m[1]) : '';
        $main = preg_match('/<main\b[^>]*>(.*)<\/main>/is', $html, $m) ? $m[1] : (preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $b) ? $b[1] : $html);
        $main = preg_replace('/<(script|style|template|svg|pre|code)\b.*?<\/\1>/is', ' ', $main);
        $text = static::clean(preg_replace('/<(br|\/p|\/li|\/h\d|\/div|\/td|\/th|\/summary|\/dt|\/dd)\b[^>]*>/i', "\n", $main));
        preg_match_all('/<link[^>]+rel="alternate"[^>]+hreflang="([a-z-]+)"[^>]+href="([^"]+)"/i', $html, $alts, PREG_SET_ORDER);

        return [
            'lang' => $lang,
            'title' => $title,
            'meta' => $meta,
            'text' => $text,
            'has_faq_schema' => str_contains($html, '"FAQPage"'),
            'has_faq_block' => (bool) preg_match('/<h[23][^>]*>[^<]*(الأسئلة الشائعة|أسئلة شائعة|Frequently asked|FAQ)/iu', $main),
            'alternates' => collect($alts)->mapWithKeys(fn ($a) => [strtolower($a[1]) => $a[2]])->all(),
        ];
    }

    /** @return list<array{rule: string, severity: string, found: string}> */
    public static function check(string $path, string $html): array
    {
        $p = static::parse($html);
        $out = [];
        $all = $p['title']."\n".$p['meta']."\n".$p['text'];

        foreach (static::textRules() as $r) {
            if ($r['lang'] !== 'all' && $r['lang'] !== $p['lang']) {
                continue;
            }
            if (static::pathAllowed($path, $r['allow_paths'] ?? [])) {
                continue;
            }
            foreach (preg_split('/(?<=[.!?؟])\s+|\n+/u', $all) as $sentence) {
                if (! preg_match($r['pattern'], $sentence, $hit)) {
                    continue;
                }
                if (isset($r['allow_sentence']) && preg_match($r['allow_sentence'], $sentence)) {
                    continue;
                }
                $out[] = ['rule' => $r['id'], 'severity' => $r['severity'], 'found' => mb_substr(trim($sentence), 0, 140)];
            }
        }

        if ($p['lang'] === 'ar' && preg_match('/^[A-Za-z]/', $p['title'])) {
            $out[] = ['rule' => 'CR-10', 'severity' => 'error', 'found' => $p['title']];
        }
        if (mb_strlen($p['title']) > 60) {
            $out[] = ['rule' => 'CR-17', 'severity' => 'error', 'found' => mb_strlen($p['title']).' chars: '.$p['title']];
        }
        $metaLen = mb_strlen($p['meta']);
        if ($metaLen < 70 || $metaLen > 160) {
            $out[] = ['rule' => 'CR-18', 'severity' => 'warn', 'found' => $metaLen.' chars'];
        }
        if ($p['has_faq_block'] && ! $p['has_faq_schema']) {
            $out[] = ['rule' => 'CR-19', 'severity' => 'error', 'found' => 'FAQ heading without FAQPage schema'];
        }
        // GB-38 parity: every page names its language twin (hreflang ar + en).
        if (! isset($p['alternates']['ar'], $p['alternates']['en'])) {
            $out[] = ['rule' => 'CR-20', 'severity' => 'error', 'found' => 'hreflang: '.implode(', ', array_keys($p['alternates']) ?: ['none'])];
        }

        return $out;
    }

    protected static function pathAllowed(string $path, array $allow): bool
    {
        foreach ($allow as $a) {
            if ($a === $path || (str_ends_with($a, '/*') && str_starts_with($path, substr($a, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    protected static function clean(string $s): string
    {
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[ \t\x{00A0}]+/u", ' ', preg_replace("/\n\s*\n+/", "\n", $s)));
    }
}
