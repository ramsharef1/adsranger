<?php

namespace App\Http\Controllers;

use App\Models\PartnerApplication;
use App\Models\WaitlistEntry;
use App\Support\PublicUrl;
use App\Support\Seo\Hreflang;
use Illuminate\Http\JsonResponse;
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

    /** Website P-23 /about, P-24 /contact, P-25 /partners (D-216, D-219) and the business-owners hub (D-213). */
    public function about(string $lang = 'ar')
    {
        return view('public.about', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function contact(string $lang = 'ar')
    {
        return view('public.contact', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function partners(string $lang = 'ar')
    {
        return view('public.partners', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function businessOwners(string $lang = 'ar')
    {
        return view('public.business-owners', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    /** P-25 form: same rules as the waitlist (5 per IP per hour, honeypot, unticked consent); one row per application. */
    public function submitPartners(Request $request, string $lang = 'ar')
    {
        $lang = $lang === 'en' ? 'en' : 'ar';
        $ar = $lang === 'ar';
        $target = $ar ? '/partners' : '/en/partners';
        $key = 'partners:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return redirect($target)->withInput()->withErrors([
                'form' => $ar ? 'محاولات كثيرة. انتظر ساعة ثم حاول مجددًا.' : 'Too many tries. Please wait an hour and try again.',
            ]);
        }
        RateLimiter::hit($key, 3600);
        if (filled($request->input('website'))) {
            return redirect($target)->with('partners_saved', true);
        }
        $agencyMessage = $ar ? 'أدخل اسم وكالتك (حرفان على الأقل).' : 'Enter your agency name (at least 2 characters).';
        $data = $request->validate([
            'email' => ['required', 'email:filter', 'max:190'],
            'agency_name' => ['required', 'string', 'min:2', 'max:140'],
            'country' => ['required', Rule::in(WaitlistEntry::COUNTRIES)],
            'clients_range' => ['required', Rule::in(PartnerApplication::CLIENT_RANGES)],
            'consent' => ['accepted'],
            'name' => ['nullable', 'string', 'max:120'],
            'monthly_spend' => ['nullable', Rule::in(WaitlistEntry::SPEND_RANGES)],
            'networks' => ['nullable', 'array'],
            'networks.*' => [Rule::in(WaitlistEntry::NETWORKS)],
        ], [
            'email.required' => $ar ? 'أدخل بريدك الإلكتروني للعمل.' : 'Enter your work email.',
            'email.email' => $ar ? 'أدخل بريدًا بصيغة name@agency.com.' : 'Enter an email like name@agency.com.',
            'email.max' => $ar ? 'أدخل بريدًا بصيغة name@agency.com.' : 'Enter an email like name@agency.com.',
            'agency_name.required' => $agencyMessage, 'agency_name.min' => $agencyMessage, 'agency_name.max' => $agencyMessage,
            'country.required' => $ar ? 'اختر دولتك.' : 'Choose your country.',
            'country.in' => $ar ? 'اختر دولتك.' : 'Choose your country.',
            'clients_range.required' => $ar ? 'اختر عدد العملاء الذين تديرهم.' : 'Choose how many clients you manage.',
            'clients_range.in' => $ar ? 'اختر عدد العملاء الذين تديرهم.' : 'Choose how many clients you manage.',
            'consent.accepted' => $ar ? 'ضع علامة في المربع للموافقة على التواصل بخصوص برنامج الشركاء.' : 'Tick the box to agree to be contacted about the partner program.',
        ]);
        $name = trim((string) ($data['name'] ?? ''));
        $networks = array_values(array_unique($data['networks'] ?? []));
        PartnerApplication::create([
            'email' => strtolower(trim($data['email'])),
            'agency_name' => trim($data['agency_name']),
            'country' => $data['country'],
            'clients_range' => $data['clients_range'],
            'name' => $name !== '' ? $name : null,
            'networks' => $networks !== [] ? implode(',', $networks) : null,
            'monthly_spend' => $data['monthly_spend'] ?? null,
            'lang' => $lang,
            'consent_at' => now(),
            'consent_text_version' => PartnerApplication::CONSENT_TEXT_VERSION,
        ]);

        return redirect($target)->with('partners_saved', true);
    }

    public function ramadanCalculator(string $lang = 'ar')
    {
        \App\Support\Seo\ToolRateLimiter::recordToolUse('ramadan-budget-calculator');
        return view('public.ramadan-calculator', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    /** Free tools (SEO slice 0 step 0.4 + D-90 ad tools). Until config('seo_tools.public') is on they answer but stay noindex. */
    public function toolsHub(Request $request): Response
    {
        $lang = $request->route('lang', 'ar') === 'en' ? 'en' : 'ar';
        $public = (bool) config('seo_tools.public');
        $response = response()->view('public.tools.index', [
            'lang' => $lang,
            'noindex' => ! $public,
        ]);
        if (! $public) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        return $response;
    }

    public function seoTool(string $lang = 'ar', string $tool = 'serp-preview'): Response
    {
        \App\Support\Seo\ToolRateLimiter::recordToolUse($tool);
        $public = (bool) config('seo_tools.public');
        $response = response()->view('public.tools.'.$tool, [
            'lang' => $lang === 'en' ? 'en' : 'ar',
            'noindex' => ! $public,
            'widths' => json_decode((string) file_get_contents(resource_path('seo/serp_widths.json')), true),
            'arabicRules' => json_decode((string) file_get_contents(resource_path('arabic/normalise.json')), true),
        ]);
        if (! $public) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * Free hreflang checker run (PR-015 §2.4): checks the URL and its language versions server-side through SafeFetcher.
     * Limits per visitor (salted hash of the IP, never stored raw): 5 runs a day (D-243) and 10 an hour (D-239).
     * Nothing about the URL or the result is stored.
     */
    public function hreflangRun(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:500', 'regex:#^https?://#i']]);
        $who = hash('sha256', config('app.key').'|'.$request->ip());
        foreach ([['hreflang-day:'.$who.':'.now()->toDateString(), 5, 86400], ['hreflang-hour:'.$who, 10, 3600]] as [$key, $max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return response()->json(['error' => 'limit'], 429);
            }
        }
        RateLimiter::hit('hreflang-day:'.$who.':'.now()->toDateString(), 86400);
        RateLimiter::hit('hreflang-hour:'.$who, 3600);
        \App\Support\Seo\ToolRateLimiter::recordToolUse('hreflang-checker-run');

        return response()->json(Hreflang::check($data['url']));
    }

    public function pageAuditRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            "url" => ["required", "string", "max:500", "regex:#^https?://#i"],
            "keyword" => ["nullable", "string", "max:120"],
        ]);
        $who = hash("sha256", config("app.key")."|".$request->ip());
        foreach ([["page-audit-day:".$who.":".now()->toDateString(), 5, 86400], ["page-audit-hour:".$who, 10, 3600]] as [$key, $max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return response()->json(["error" => "limit"], 429);
            }
        }
        RateLimiter::hit("page-audit-day:".$who.":".now()->toDateString(), 86400);
        RateLimiter::hit("page-audit-hour:".$who, 3600);
        \App\Support\Seo\ToolRateLimiter::recordToolUse('page-audit-run');

        $fetched = \App\Support\Seo\SafeFetcher::fetch($data["url"]);
        if (! ($fetched["ok"] ?? false) || ($fetched["status"] ?? 0) !== 200) {
            $err = ($fetched["error"] ?? "") === "blocked" ? "blocked" : (($fetched["status"] ?? null) ? "not_200" : "fetch_failed");
            return response()->json(["ok" => false, "error" => $err, "status" => $fetched["status"] ?? null, "url" => $fetched["url"] ?? $data["url"]]);
        }

        $audit = \App\Support\Seo\PageAudit::auditHtml((string) ($fetched["body"] ?? ""), $data["keyword"] ?? null);
        return response()->json([
            "ok" => true,
            "url" => $fetched["url"],
            "status" => $fetched["status"],
            "score" => $audit["score"],
            "checks" => $audit["checks"],
            "issues" => $audit["issues"],
            "serp" => $audit["serp"],
        ]);
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
            '/about' => 'public/about',
            '/contact' => 'public/contact',
            '/partners' => 'public/partners',
            '/solutions/business-owners' => 'public/business-owners',
            '/features' => 'public/product/show',
            '/reports' => 'public/product/show',
            '/studio' => 'public/product/show',
            '/search' => 'public/product/show',
            '/ai' => 'public/product/show',
            '/glossary' => 'public/glossary/index',
            '/blog' => 'public/blog/index',
            '/guides' => 'public/guides/index',
            '/solutions/agencies' => 'public/solution-role',
            '/solutions/freelancers' => 'public/solution-role',
            '/for-clients' => 'public/solution-role',
            '/for-teams' => 'public/solution-role',
            '/roadmap' => 'public/solution-role',
            '/privacy' => 'legal/page',
            '/terms' => 'legal/page',
            '/data-deletion' => 'legal/page',
        ] + (config('seo_tools.public') ? (['/tools' => 'public/tools/index'] + collect(config('seo_tools.tools', []))->mapWithKeys(fn (string $t) => ['/tools/'.$t => 'public/tools/'.$t])->all()) : []);
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

    public function heldPartners(Request $request): View
    {
        return view('public.partners-held', ['lang' => $request->segment(1) === 'en' ? 'en' : 'ar']);
    }

    public function resources(Request $request): View
    {
        return view('public.resources', ['lang' => $request->segment(1) === 'en' ? 'en' : 'ar']);
    }

    public function requestAuditEmail(Request $request, string $lang = 'ar')
    {
        $lang = $lang === 'en' ? 'en' : 'ar';
        $tool = in_array($request->input('tool'), ['page-audit', 'hreflang-checker'], true) ? $request->input('tool') : 'page-audit';
        $target = ($lang === 'en' ? '/en/tools/' : '/tools/').$tool;
        if (filled($request->input('website'))) {
            return redirect($target)->with('tool_report_saved', true);
        }
        $data = $request->validate([
            'email' => ['required', 'email:filter', 'max:190'],
            'agency_name' => ['required', 'string', 'min:2', 'max:140'],
            'country' => ['required', Rule::in(WaitlistEntry::COUNTRIES)],
            'consent' => ['accepted'],
        ]);
        WaitlistEntry::updateOrCreate(
            ['email' => strtolower(trim($data['email']))],
            [
                'agency_name' => trim($data['agency_name']),
                'country' => $data['country'],
                'lang' => $lang,
                'consent_at' => now(),
                'consent_text_version' => WaitlistEntry::CONSENT_TEXT_VERSION,
            ]
        );

        return redirect($target)->with('tool_report_saved', true);
    }

    public function productPage(string $lang = 'ar', string $slug = 'features'): Response
    {
        abort_unless(isset(\App\Support\Seo\PublicCatalog::products()[$slug]), 404);
        return response()->view('public.product.show', ['lang' => $lang === 'en' ? 'en' : 'ar', 'slug' => $slug]);
    }

    public function platformPage(string $lang = 'ar', string $platform = 'meta'): Response
    {
        abort_unless(isset(\App\Support\Seo\PublicCatalog::platforms()[$platform]), 404);
        return response()->view('public.platform-show', ['lang' => $lang === 'en' ? 'en' : 'ar', 'platform' => $platform]);
    }

    public function glossaryIndex(string $lang = 'ar'): Response
    {
        return response()->view('public.glossary.index', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function glossaryTerm(string $lang = 'ar', string $term = 'roas'): Response
    {
        abort_unless(isset(\App\Support\Seo\PublicCatalog::glossary()[$term]), 404);
        return response()->view('public.glossary.show', ['lang' => $lang === 'en' ? 'en' : 'ar', 'term' => $term]);
    }

    public function blogIndex(string $lang = 'ar'): Response
    {
        return response()->view('public.blog.index', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function blogShow(string $lang = 'ar', string $slug = ''): Response
    {
        abort_unless(isset(\App\Support\Seo\PublicCatalog::posts()[$slug]), 404);
        return response()->view('public.blog.show', ['lang' => $lang === 'en' ? 'en' : 'ar', 'slug' => $slug]);
    }

    public function guidesIndex(string $lang = 'ar'): Response
    {
        return response()->view('public.guides.index', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function guidesShow(string $lang = 'ar', string $slug = ''): Response
    {
        abort_unless(isset(\App\Support\Seo\PublicCatalog::guides()[$slug]), 404);
        return response()->view('public.guides.show', ['lang' => $lang === 'en' ? 'en' : 'ar', 'slug' => $slug]);
    }

    public function solutionOrRolePage(string $lang = 'ar', string $pageKey = 'roadmap'): Response
    {
        abort_unless(isset(\App\Support\Seo\PublicCatalog::solutionsAndRoles()[$pageKey]), 404);
        return response()->view('public.solution-role', ['lang' => $lang === 'en' ? 'en' : 'ar', 'pageKey' => $pageKey]);
    }
}
