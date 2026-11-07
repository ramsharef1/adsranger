<?php

namespace App\Http\Controllers;

use App\Models\WaitlistEntry;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function waitlist(string $lang = 'ar')
    {
        return view('public.waitlist', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    /** P-1 waitlist (D-152, D-159, D-172, D-173): consent is recorded; the same success message shows for new and existing emails (UX-33). */
    public function submitWaitlist(Request $request, string $lang = 'ar')
    {
        $lang = $lang === 'en' ? 'en' : 'ar';
        $target = $lang === 'en' ? '/en/waitlist' : '/waitlist';
        $ar = $lang === 'ar';

        $limiterKey = 'waitlist:'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return redirect($target)->withInput()->withErrors([
                'form' => $ar ? 'محاولات كثيرة. انتظر ساعة ثم حاول مجددًا.' : 'Too many tries. Please wait an hour and try again.',
            ]);
        }
        RateLimiter::hit($limiterKey, 3600);

        if (filled($request->input('website'))) {
            return redirect($target)->with('waitlist_saved', true);
        }

        $agencyMessage = $ar ? 'أدخل اسم وكالتك أو شركتك (حرفان على الأقل).' : 'Enter your agency or company name (at least 2 characters).';
        $data = $request->validate([
            'email' => ['required', 'email:filter', 'max:190'],
            'agency_name' => ['required', 'string', 'min:2', 'max:140'],
            'country' => ['required', Rule::in(WaitlistEntry::COUNTRIES)],
            'consent' => ['accepted'],
            'name' => ['nullable', 'string', 'max:120'],
            'audience' => ['nullable', Rule::in(WaitlistEntry::AUDIENCES)],
            'monthly_spend' => ['nullable', Rule::in(WaitlistEntry::SPEND_RANGES)],
            'networks' => ['nullable', 'array'],
            'networks.*' => [Rule::in(WaitlistEntry::NETWORKS)],
        ], [
            'email.required' => $ar ? 'أدخل بريدك الإلكتروني للعمل.' : 'Enter your work email.',
            'email.email' => $ar ? 'أدخل بريدًا بصيغة name@agency.com.' : 'Enter an email like name@agency.com.',
            'email.max' => $ar ? 'أدخل بريدًا بصيغة name@agency.com.' : 'Enter an email like name@agency.com.',
            'agency_name.required' => $agencyMessage,
            'agency_name.min' => $agencyMessage,
            'agency_name.max' => $agencyMessage,
            'country.required' => $ar ? 'اختر دولتك.' : 'Choose your country.',
            'country.in' => $ar ? 'اختر دولتك.' : 'Choose your country.',
            'consent.accepted' => $ar ? 'ضع علامة في المربع للموافقة على رسائل الإطلاق.' : 'Tick the box to agree to launch emails.',
        ]);

        $name = trim((string) ($data['name'] ?? ''));
        $networks = array_values(array_unique($data['networks'] ?? []));

        WaitlistEntry::updateOrCreate(
            ['email' => strtolower(trim($data['email']))],
            [
                'name' => $name !== '' ? $name : null,
                'agency_name' => trim($data['agency_name']),
                'audience' => $data['audience'] ?? null,
                'country' => $data['country'],
                'monthly_spend' => $data['monthly_spend'] ?? null,
                'networks' => $networks !== [] ? implode(',', $networks) : null,
                'lang' => $lang,
                'consent_at' => now(),
                'consent_text_version' => WaitlistEntry::CONSENT_TEXT_VERSION,
            ]
        );

        return redirect($target)->with('waitlist_saved', true);
    }

    public function ramadan(string $lang = 'ar')
    {
        return view('public.ramadan-2027', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function pricing(string $lang = 'ar')
    {
        return view('public.pricing', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function integrations(string $lang = 'ar')
    {
        return view('public.integrations', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function demoReport(string $lang = 'ar')
    {
        return view('public.demo-report', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function menaCalendar(string $lang = 'ar')
    {
        return view('public.mena-calendar', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function security(string $lang = 'ar')
    {
        return view('public.security', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function ramadanCalculator(string $lang = 'ar')
    {
        return view('public.ramadan-calculator', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    /**
     * Public pages in the sitemap (P-0 set + R-19): Arabic path => view that renders it (its file time is the lastmod).
     *
     * @return array<string, string>
     */
    public static function sitemapPageViews(): array
    {
        return [
            '/' => 'welcome',
            '/waitlist' => 'public/waitlist',
            '/ramadan-2027' => 'public/ramadan-2027',
            '/tools/ramadan-budget-calculator' => 'public/ramadan-calculator',
            '/pricing' => 'public/pricing',
            '/integrations' => 'public/integrations',
            '/demo-report' => 'public/demo-report',
            '/mena-calendar' => 'public/mena-calendar',
            '/security' => 'public/security',
            '/privacy' => 'legal/page',
            '/terms' => 'legal/page',
            '/data-deletion' => 'legal/page',
        ];
    }

    /** R-13: /sitemap.xml is a sitemap index; children are added as sections ship (features, guides, blog, …). */
    public function sitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .'  <sitemap><loc>'.PublicUrl::HOST.'/sitemaps/pages.xml</loc></sitemap>'."\n"
            .'</sitemapindex>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** One <url> per language, each with ar / en / x-default (→ Arabic) alternates and a real lastmod (P-0, D-06). */
    public function sitemapPages(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach (static::sitemapPageViews() as $arPath => $view) {
            $file = resource_path('views/'.$view.'.blade.php');
            $lastmod = is_file($file) ? date('Y-m-d', (int) filemtime($file)) : null;
            $ar = PublicUrl::absolute($arPath, 'ar');
            $en = PublicUrl::absolute($arPath, 'en');
            foreach ([$ar, $en] as $loc) {
                $xml .= "  <url>\n    <loc>{$loc}</loc>\n"
                    .($lastmod ? "    <lastmod>{$lastmod}</lastmod>\n" : '')
                    ."    <xhtml:link rel=\"alternate\" hreflang=\"ar\" href=\"{$ar}\"/>\n"
                    ."    <xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"{$en}\"/>\n"
                    ."    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$ar}\"/>\n"
                    ."  </url>\n";
            }
        }
        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function calculator(Request $request): View
    {
        return view('public.calculator', ['lang' => $request->segment(1) === 'en' ? 'en' : 'ar']);
    }

    public function partners(Request $request): View
    {
        return view('public.partners', ['lang' => $request->segment(1) === 'en' ? 'en' : 'ar']);
    }

    public function resources(Request $request): View
    {
        return view('public.resources', ['lang' => $request->segment(1) === 'en' ? 'en' : 'ar']);
    }
}
