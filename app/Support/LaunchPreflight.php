<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientLicence;
use App\Support\Seo\Audit\PageParser;
use App\Support\Seo\CapiPixelChecker;
use App\Support\Seo\SafeFetcher;
use Carbon\Carbon;

/**
 * XS-11 (R4): one pass / fix list before a campaign goes live: the link has UTM tags, the landing page opens, its
 * language matches the ad, a tracking tag is on the page, risky claim words, a licence number when the client has one,
 * and a season starting within 14 days. Checks only; it never changes or launches anything.
 */
class LaunchPreflight
{
    /** @return list<array{key: string, ok: bool, level: string, message: string}> */
    public static function run(Client $c, string $url, string $adText, ?bool $fetch = true, ?string $adName = null): array
    {
        $out = [];
        $add = function (string $key, bool $ok, string $level, string $message) use (&$out) {
            $out[] = ['key' => $key, 'ok' => $ok, 'level' => $ok ? 'ok' : $level, 'message' => $message];
        };
        $adArabic = preg_match_all('/\p{Arabic}/u', $adText) > preg_match_all('/[A-Za-z]/', $adText);

        // 1. UTM tags
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $missing = array_values(array_diff(['utm_source', 'utm_medium', 'utm_campaign'], array_keys($q)));
        $add('utm', $missing === [], 'warning', $missing === [] ? __('The link has utm_source, utm_medium and utm_campaign.')
            : __('Missing in the link: :tags. Results in analytics will be harder to tell apart.', ['tags' => implode(', ', $missing)]));

        // 2–4. Landing page: opens, language matches the ad, a tracking tag is present
        if ($fetch) {
            $r = SafeFetcher::fetch($url);
            if (! $r['ok'] || ($r['status'] ?? 0) >= 400) {
                $add('page', false, 'critical', __('The landing page did not open (:why).', ['why' => $r['status'] ?? ($r['error'] ?? 'error')]));
            } else {
                $add('page', true, 'ok', __('The landing page opens (:status).', ['status' => $r['status']]));
                $p = PageParser::parse($r['html'], $r['url']);
                $pageArabic = str_starts_with(strtolower((string) ($p['lang'] ?? '')), 'ar') || strtolower((string) ($p['dir'] ?? '')) === 'rtl';
                $add('language', $pageArabic === $adArabic, 'warning', $pageArabic === $adArabic
                    ? __('The page language matches the ad (:lang).', ['lang' => $adArabic ? __('Arabic') : __('English')])
                    : __('The ad is in :ad but the page is in :page. Send Arabic ads to the Arabic page.', ['ad' => $adArabic ? __('Arabic') : __('English'), 'page' => $pageArabic ? __('Arabic') : __('English')]));
                $tags = CapiPixelChecker::inspectHtml($r['html'])['detected'] ?? [];
                $add('tracking', $tags !== [], 'critical', $tags !== [] ? __('Tracking found: :tags.', ['tags' => implode(', ', array_column($tags, 'label'))])
                    : __('No Meta Pixel, Google tag or TikTok pixel found on the page. Results may not be counted.'));
            }
        }

        // 5. Claim words that ad platforms often reject
        $policy = PolicyPreCheck::analyze($adText);
        $add('policy', $policy['passed'], 'warning', $policy['passed'] ? __('No risky claim words found.') : __('Words platforms often reject: :list', ['list' => implode(' · ', $policy['issues'])]));

        // 6. Licence / ad-permit reminder when the client has a general licence or a matching ad_name permit (neutral reminder only; never judges compliance)
        $lic = SectorPacks::matchingLicences($c, [$adName, $adText, $url], true);
        if ($lic->isNotEmpty()) {
            $shown = $lic->first(fn (ClientLicence $l): bool => str_contains($adText, $l->number));
            $hasAdPermit = $lic->contains(fn (ClientLicence $l): bool => filled($l->ad_name));
            $list = $lic->map(fn (ClientLicence $l): string => filled($l->ad_name) ? "{$l->ad_name} ({$l->number})" : $l->number)->implode(', ');
            $okMessage = ($shown && filled($shown->ad_name))
                ? __('The licence number is in the ad (:ad: :n).', ['ad' => $shown->ad_name, 'n' => $shown->number])
                : __('The licence number is in the ad.');
            $warnMessage = $hasAdPermit
                ? __('Permit reminder (:n): check whether this ad needs to display the permit number.', ['n' => $list])
                : __('This client has a licence number (:n); check whether the ad must show it.', ['n' => $lic->pluck('number')->implode(', ')]);
            $add('licence', (bool) $shown, 'warning', $shown ? $okMessage : $warnMessage);
        }

        // 7. A season starting soon
        $tz = $c->agency?->timezone ?: 'UTC';
        $soon = collect(MenaCalendar::events(now($tz)->toDateString(), now($tz)->addDays(14)->toDateString(), $c->agency?->country ?: null))
            ->first(fn ($e) => $e['start'] >= now($tz)->toDateString());
        if ($soon) {
            $add('season', false, 'info', __(':season starts on :date: costs and behaviour change; check budgets and creatives.', ['season' => app()->getLocale() === 'ar' ? $soon['ar'] : $soon['label'],
                'date' => Carbon::parse($soon['start'])->locale(app()->getLocale())->translatedFormat('j M')]));
        }

        return $out;
    }
}
