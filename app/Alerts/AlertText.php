<?php

namespace App\Alerts;

use App\Support\ArabicCount;

/**
 * UX-121: alert titles and details are stored in English (AlertEngine builds them with live numbers). This turns
 * them into Arabic at display time, so old rows are translated too. Each pattern keeps the numbers, dates, currency
 * codes and names from the stored text; unknown text (for example a network's own error message) stays as stored.
 * Values are wrapped in Unicode isolates (no HTML), so the result is safe in tables, emails and push text.
 */
class AlertText
{
    private const LRI = "\u{2066}";

    private const FSI = "\u{2068}";

    private const PDI = "\u{2069}";

    /** @var array<string, string> English pattern => Arabic template ($1, $2 … are isolated values; ~1 … are names). */
    private const TITLES = [
        '/^Tracking break: (?<n1>.+)$/s' => 'انقطاع في التتبع: ~1',
        '/^Spend with no results: (?<n1>.+)$/s' => 'إنفاق دون نتائج: ~1',
        '/^Cost per result up (\d+)%: (?<n1>.+)$/s' => 'ارتفاع تكلفة النتيجة $1%: ~1',
        '/^Over-pacing (\d+)%: (?<n1>.+)$/s' => 'وتيرة إنفاق أعلى من الخطة بنسبة $1%: ~1',
        '/^Under-pacing (\d+)%: (?<n1>.+)$/s' => 'وتيرة إنفاق أقل من الخطة بنسبة $1%: ~1',
        '/^Wasted search terms: (?<n1>.+)$/s' => 'عبارات بحث مهدرة: ~1',
        '/^Broken landing page$/' => 'صفحة هبوط معطّلة',
        '/^Tracking issue: (?<n1>.+)$/s' => 'مشكلة في التتبع: ~1',
        '/^Under-delivery: (?<n1>.+)$/s' => 'عرض أقل من الميزانية: ~1',
        '/^Ending soon: (?<n1>.+)$/s' => 'ينتهي قريبًا: ~1',
        '/^Connection problem: (?<n1>.+)$/s' => 'مشكلة في الربط: ~1',
        '/^Account health: (?<n1>.+)$/s' => 'حالة الحساب: ~1',
        '/^CTR down (\d+)%: (?<n1>.+)$/s' => 'انخفاض نسبة النقر (CTR) بنسبة $1%: ~1',
        '/^ROAS down (\d+)%: (?<n1>.+)$/s' => 'انخفاض العائد على الإنفاق الإعلاني (ROAS) بنسبة $1%: ~1',
        '/^Daily spend spike \+(\d+)%: (?<n1>.+)$/s' => 'قفزة في الإنفاق اليومي بنسبة $1%: ~1',
        '/^High ad frequency \(([\d.,]+)\): (?<n1>.+)$/s' => 'معدل تكرار مرتفع ($1): ~1',
        '/^Cost per result (\d+)% above target: (?<n1>.+)$/s' => 'تكلفة النتيجة أعلى من المستهدف بنسبة $1%: ~1',
        '/^Cost per click up (\d+)%: (?<n1>.+)$/s' => 'ارتفاع تكلفة النقرة $1%: ~1',
        '/^Active campaign with 0 impressions: (?<n1>.+)$/s' => 'حملة نشطة دون أي ظهور: ~1',
        '/^No fresh data: (?<n1>.+)$/s' => 'لا بيانات حديثة: ~1',
        '/^Exchange rate moved (\d+)%: (?<n1>.+)$/s' => 'تغيّر سعر الصرف بنسبة $1%: ~1',
    ];

    private const DETAILS = [
        '/^0 results on (\S+) after results on each of the previous (\d+) days\. Check the pixel \/ conversion tags\.$/' => 'لا نتائج في $1 بعد نتائج في كل يوم من آخر {days:$2} قبله. تحقّق من البكسل ووسوم التحويل.',
        '/^([\d,.]+ [A-Z]{3}) spent in the last (\d+) days with 0 results\.$/' => 'أُنفق $1 خلال آخر {days:$2} دون أي نتيجة.',
        '/^Last 7 days ([\d,.]+) vs ([\d,.]+ [A-Z]{3}) the week before\.$/' => 'آخر 7 أيام: $1 مقابل $2 في الأسبوع السابق.',
        '/^Forecast ([\d,.]+) vs budget ([\d,.]+ [A-Z]{3}) for (?<n1>.+?)\. Spend about ([\d,.]+) a day to land on budget\.$/s' => 'الإنفاق المتوقع $1 مقابل ميزانية $2 لشهر ~1. أنفِق نحو $3 يوميًا لتصل إلى الميزانية.',
        '/^([\d,.]+ [A-Z]{3}) on (\d+) search terms with no conversions in the last 7 days\. Review them in Google Ads → Search terms\.$/' => '$1 على {search_terms:$2} دون أي تحويل خلال آخر 7 أيام. راجعها في Google Ads، قسم عبارات البحث.',
        '/^(\S+) — redirects to another site: (\S*)\. Down since (\S+ \S+) UTC\. Ads sent (\d+) clicks here in 30 days\.$/' => '$1 — تنقل إلى موقع آخر: $2. معطّلة منذ $3 بتوقيت غرينتش. أرسلت الإعلانات {clicks:$4} إلى هذه الصفحة خلال 30 يومًا.',
        '/^(\S+) — (?<n1>.+?)\. Down since (\S+ \S+) UTC\. Ads sent (\d+) clicks here in 30 days\.$/s' => '$1 — ~1. معطّلة منذ $2 بتوقيت غرينتش. أرسلت الإعلانات {clicks:$3} إلى هذه الصفحة خلال 30 يومًا.',
        '/^(\S+) — (?<n1>.+?)\. Ads sent (\d+) clicks here in 30 days\.$/s' => '$1 — ~1. أرسلت الإعلانات {clicks:$2} إلى هذه الصفحة خلال 30 يومًا.',
        '/^([\d,.]+ [A-Z]{3}) in (\d+) days on a (?<n1>.+?) goal with 0 results\.$/s' => '$1 خلال {days:$2} على هدف ~1 دون أي نتيجة.',
        '/^Spent ([\d,.]+) of a ([\d,.]+) daily budget \((\d+%)\) on (\S+)\.$/' => 'أُنفق $1 من ميزانية يومية قدرها $2 ($3) في $4.',
        '/^Campaign ends .+ \((\S+)\)\.$/s' => 'تنتهي الحملة في $1.',
        '/^Ad set ends .+ \((\S+)\)\.$/s' => 'تنتهي المجموعة الإعلانية في $1.',
        '/^Last upload (\d+) days ago\. Export the latest report from the ad platform and import it\.$/' => 'آخر رفع للبيانات قبل {days:$1}. صدّر أحدث تقرير من منصة الإعلانات واستورده.',
        '/^1 ([A-Z]{3}) = ([\d.,]+) ([A-Z]{3}) now, ([\d.,]+) [A-Z]{3} on (\d{4}-\d{2}-\d{2})\. The monthly budget of ([\d.,]+) [A-Z]{3} now covers ([\d.,]+) [A-Z]{3} of spend instead of ([\d.,]+) [A-Z]{3}\.$/' => '1 $1 = $2 $3 الآن، و$4 $3 في $5. ميزانية الشهر $6 $3 تغطي الآن إنفاقًا قدره $7 $1 بدلًا من $8 $1.',
        '/^1 ([A-Z]{3}) = ([\d.,]+) ([A-Z]{3}) now, ([\d.,]+) [A-Z]{3} on (\d{4}-\d{2}-\d{2})\.$/' => '1 $1 = $2 $3 الآن، و$4 $3 في $5.',
        '/^The connection needs attention: (?<n1>.+)$/s' => 'يحتاج الربط إلى مراجعة: ~1',
        '/^The access token expired on (\S+)\. Reconnect\.$/' => 'انتهت صلاحية رمز الوصول في $1. أعد الربط.',
        '/^Sync failed (\d+) times in a row: (?<n1>.+)$/s' => 'فشلت المزامنة {times:$1} متتالية: ~1',
        '/^Last 7 days ([\d,.]+%) vs ([\d,.]+%) the week before\.$/' => 'آخر 7 أيام: $1 مقابل $2 في الأسبوع السابق.',
        '/^Last 7 days ([\d,.]+x) vs ([\d,.]+x) the week before\.$/' => 'آخر 7 أيام: $1 مقابل $2 في الأسبوع السابق.',
        '/^Spent ([\d,.]+ [A-Z]{3}) on (\S+) vs ([\d,.]+) daily average\.$/' => 'أُنفق $1 في $2 مقابل متوسط يومي قدره $3.',
        '/^Reached people saw ads ([\d,.]+) times on average over the last 7 days\. Refresh creatives or widen audiences\.$/' => 'شاهد من وصلت إليهم الإعلانات $1 مرة في المتوسط خلال آخر 7 أيام. جدّد التصميمات الإعلانية أو وسّع الجمهور.',
        '/^Last 7 days ([\d,.]+) vs target ([\d,.]+ [A-Z]{3})\.$/' => 'آخر 7 أيام: $1 مقابل المستهدف $2.',
        '/^0 impressions on (\S+) after ([\d,]+) impressions in the previous 7 days\.$/' => 'لا ظهور في $1 بعد {times:$2} ظهور خلال الأيام السبعة السابقة.',
        '/^Account spending limit (\d+)% used \(([\d,.]+) of ([\d,.]+ ?[A-Z]{0,3})\)\.$/' => 'استُخدم $1% من حد الإنفاق في الحساب ($2 من $3).',
        '/^(\d+) ad\(s\) rejected or with issues on (?<n1>.+)\.$/s' => 'إعلانات مرفوضة أو بها مشكلات على ~1: $1.',
    ];

    public static function title(?string $text, ?string $locale = null): string
    {
        return static::translate((string) $text, self::TITLES, $locale);
    }

    public static function detail(?string $text, ?string $locale = null): string
    {
        return static::translate((string) $text, self::DETAILS, $locale);
    }

    /** @param  array<string, string>  $map */
    protected static function translate(string $text, array $map, ?string $locale): string
    {
        if ($text === '' || ($locale ?? app()->getLocale()) !== 'ar') {
            return $text;
        }
        foreach ($map as $pattern => $template) {
            if (! preg_match($pattern, $text, $m)) {
                continue;
            }
            $raw = [];
            foreach ($m as $k => $v) {
                if (is_int($k) && $k > 0 && ! static::isNamed($m, $k)) {
                    $raw[] = $v;
                }
            }
            $values = array_map(fn (string $v): string => self::LRI.$v.self::PDI, $raw);
            // UX-143: {days:$2} etc. become a count phrase with the right Arabic noun form.
            $out = preg_replace_callback('/\{(\w+):\$(\d)\}/', fn (array $p): string => ArabicCount::phrase($raw[(int) $p[2] - 1] ?? '', $p[1]), $template);
            foreach ($values as $i => $v) {
                $out = str_replace('$'.($i + 1), $v, $out);
            }

            return isset($m['n1']) ? str_replace('~1', self::FSI.$m['n1'].self::PDI, $out) : $out;
        }

        return $text;
    }

    /** True when numbered group $k is the same capture as the named group n1. */
    protected static function isNamed(array $m, int $k): bool
    {
        if (! isset($m['n1'])) {
            return false;
        }
        $keys = array_keys($m);
        $pos = array_search('n1', $keys, true);

        return $pos !== false && ($keys[$pos + 1] ?? null) === $k;
    }
}
