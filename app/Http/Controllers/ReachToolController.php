<?php

namespace App\Http\Controllers;

use App\Support\Seo\AiCrawlerChecker;
use App\Support\Seo\ToolRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * FR-REACH R-3: /tools/ai-crawler-revenue-checker (AI Bot Traffic & Unmonetized Revenue Checker). Behind the reach
 * module (ReachEnabled: 404 while off, noindex always), registered in config/seo_tools.php `module_gated`, so it is
 * never in the sitemap, menus, /tools hub, share cards or /llms.txt. Same visitor limits as the other URL tools
 * (salted IP hash, 5 a day, 10 an hour); nothing about the URL or the result is stored.
 */
class ReachToolController extends Controller
{
    public const SLUG = 'ai-crawler-revenue-checker';

    public function show(string $lang = 'ar'): Response
    {
        ToolRateLimiter::recordToolUse(self::SLUG);

        return response()->view('public.tools.'.self::SLUG, ['lang' => $lang === 'en' ? 'en' : 'ar', 'noindex' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function run(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:500', 'regex:#^https?://#i']]);
        $who = hash('sha256', config('app.key').'|'.$request->ip());
        $day = 'ai-crawler-day:'.$who.':'.now()->toDateString();
        $hour = 'ai-crawler-hour:'.$who;
        if (RateLimiter::tooManyAttempts($day, 5) || RateLimiter::tooManyAttempts($hour, 10)) {
            return response()->json(['error' => 'limit'], 429);
        }
        RateLimiter::hit($day, 86400);
        RateLimiter::hit($hour, 3600);
        ToolRateLimiter::recordToolUse(self::SLUG.'-run');

        return response()->json(AiCrawlerChecker::check($data['url']));
    }
}
