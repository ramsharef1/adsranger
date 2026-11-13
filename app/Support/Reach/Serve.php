<?php

namespace App\Support\Reach;

use App\Models\ReachOffer;
use App\Models\ReachWidget;
use App\Support\Arabic\Normaliser;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * FR-REACH R-2 stateless offer picker for /reach/v1/serve, the offerwall and the AI feed. Deterministic: the same
 * widget, country, OS, page keywords, local time window and counters always give the same offers. No LLM call, no
 * visitor data stored (only per-day counters). Candidates are cached per widget × country × OS.
 *
 * Score = log(1 + publisher payout) + 3 × page-keyword hits + time-window boost + UCB1 click bonus.
 *  - Payday window (25th → 3rd, local): shopping / finance / travel CPS offers +1.
 *  - Meal times (11:00–14:00, 19:00–22:00 local): food offers +1.5.
 *  - UCB1: CTR + sqrt(2 ln N / n) from the cached view / click counters; an offer never shown gets the top bonus.
 */
class Serve
{
    public static function cache(): Repository
    {
        return Cache::store(config('reach.cache_store'));
    }

    public static function widget(?string $key): ?ReachWidget
    {
        if (! is_string($key) || ! preg_match('/^rw_[a-z0-9]{16}$/', $key)) {
            return null;
        }
        $id = static::cache()->remember('reach:w:'.$key, 300, fn () => ReachWidget::where('public_key', $key)
            ->whereHas('publisher', fn ($q) => $q->where('status', 'verified'))->value('id') ?? 0);

        return $id ? ReachWidget::with('publisher')->find($id) : null;
    }

    public static function os(?string $ua): string
    {
        $ua = strtolower((string) $ua);

        return match (true) {
            str_contains($ua, 'android') => 'android',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'ios',
            str_contains($ua, 'windows') => 'windows',
            str_contains($ua, 'mac os') => 'mac',
            default => 'other',
        };
    }

    public static function country(Request $request): string
    {
        $c = strtoupper((string) $request->header('CF-IPCountry', ''));

        return preg_match('/^[A-Z]{2}$/', $c) && $c !== 'XX' ? $c : 'ZZ';
    }

    /** AI agent label from the user agent, or null for a person (or an unknown bot). */
    public static function aiAgent(?string $ua): ?string
    {
        foreach (config('reach.ai_agents') as $needle => $label) {
            if ($ua !== null && stripos($ua, $needle) !== false) {
                return $label;
            }
        }

        return null;
    }

    /** @return list<string> normalised keywords from a page URL (path + query values), Arabic or Latin */
    public static function keywords(?string $url): array
    {
        // parse_url() fails on raw (unencoded) Arabic, so percent-encode non-ASCII bytes first; decoded again below.
        $url = (string) preg_replace_callback('/[^\x21-\x7e]+/', fn ($m) => rawurlencode($m[0]), trim((string) $url));
        $parts = parse_url($url) ?: [];
        $text = urldecode(($parts['path'] ?? '').' '.str_replace(['=', '&'], ' ', $parts['query'] ?? ''));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];

        return array_values(array_unique(array_filter(array_map(fn ($w) => Normaliser::key($w), $words), fn ($w) => mb_strlen($w) >= 3)));
    }

    /**
     * @param  array{country?: string, os?: string, url?: ?string, now?: ?Carbon, limit?: int}  $ctx
     * @return list<ReachOffer>
     */
    public static function pick(ReachWidget $widget, array $ctx = []): array
    {
        $country = $ctx['country'] ?? 'ZZ';
        $os = $ctx['os'] ?? 'other';
        $limit = $ctx['limit'] ?? (int) config('reach.max_offers_per_view');
        $now = ($ctx['now'] ?? now())->copy()->timezone((string) ($widget->settings['timezone'] ?? 'Asia/Riyadh'));
        $keywords = static::keywords($ctx['url'] ?? null);

        $ids = static::cache()->remember("reach:cand:{$widget->id}:{$country}:{$os}", (int) config('reach.serve_cache_seconds'),
            fn () => static::candidates($widget, $country, $os)->pluck('id')->all());
        if ($ids === []) {
            return [];
        }
        $offers = ReachOffer::whereIn('id', $ids)->where('status', 'active')->get();
        $views = static::counters($offers->pluck('id')->all(), 'v');
        $clicks = static::counters($offers->pluck('id')->all(), 'c');
        $total = max(1, array_sum($views));

        $scored = $offers->map(function (ReachOffer $o) use ($keywords, $now, $views, $clicks, $total): array {
            $kwHits = count(array_intersect($keywords, (array) $o->keywords));
            $n = $views[$o->id] ?? 0;
            $ucb = $n === 0 ? 2.0 : min(2.0, (($clicks[$o->id] ?? 0) / $n) + sqrt(2 * log($total) / $n));

            return ['offer' => $o, 'score' => round(log(1 + $o->publisher_payout_amount) + 3 * $kwHits + static::timeBoost($o, $now) + $ucb, 6)];
        })->sort(fn ($a, $b) => [$b['score'], $a['offer']->id] <=> [$a['score'], $b['offer']->id]);

        return $scored->take($limit)->pluck('offer')->values()->all();
    }

    public static function timeBoost(ReachOffer $o, Carbon $localNow): float
    {
        $boost = 0.0;
        $day = (int) $localNow->format('j');
        if (($day >= 25 || $day <= 3) && $o->offer_type === 'cps' && in_array($o->category, ['shopping', 'finance', 'travel', 'electronics', 'fashion'], true)) {
            $boost += 1.0;
        }
        $hour = (int) $localNow->format('G');
        if ($o->category === 'food' && (($hour >= 11 && $hour < 14) || ($hour >= 19 && $hour < 22))) {
            $boost += 1.5;
        }

        return $boost;
    }

    /** @return Collection<int, ReachOffer> */
    public static function candidates(ReachWidget $widget, string $country, string $os)
    {
        $publisher = $widget->publisher;
        $blocked = array_map(fn ($d) => preg_replace('/^www\./', '', strtolower(trim($d))), (array) $publisher->blocked_domains);
        $allowedCategories = array_values(array_filter((array) $publisher->category_filters));
        $unsafe = config('reach.unsafe_categories');

        return ReachOffer::where('status', 'active')
            // Hard incent firewall: an incentivised placement (app offerwall) only ever carries offers that allow it.
            ->when($widget->isIncentivised(), fn ($q) => $q->where('allow_incent', true))
            ->get()
            ->filter(function (ReachOffer $o) use ($country, $os, $blocked, $allowedCategories, $unsafe, $publisher): bool {
                if ($o->countries && ! in_array($country, $o->countries, true)) {
                    return false;
                }
                if ($o->offer_type === 'cpi' && ! in_array($os, ['android', 'ios'], true)) {
                    return false;
                }
                if ($publisher->family_safe && in_array($o->category, $unsafe, true)) {
                    return false;
                }
                if ($allowedCategories !== [] && ! in_array($o->category, $allowedCategories, true)) {
                    return false;
                }
                $host = $o->landingHost();

                return ! ($host && collect($blocked)->contains(fn ($b) => $b !== '' && ($host === $b || str_ends_with($host, '.'.$b))));
            })->values();
    }

    /** @param  list<int>  $ids  @return array<int, int> */
    public static function counters(array $ids, string $kind): array
    {
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = (int) static::cache()->get("reach:{$kind}:{$id}", 0);
        }

        return $out;
    }

    public static function count(string $key, int $ttl = 2_764_800): void
    {
        $cache = static::cache();
        if (! $cache->has($key)) {
            $cache->put($key, 0, $ttl);
        }
        $cache->increment($key);
    }

    /** Per publisher per day: human views and AI-agent reads (by agent). */
    public static function recordView(int $publisherId, ?string $aiAgent): void
    {
        $day = now()->toDateString();
        static::count($aiAgent ? "reach:ai:{$publisherId}:{$day}:{$aiAgent}" : "reach:human:{$publisherId}:{$day}");
    }

    /** @return array{human: int, ai: int, agents: array<string, int>} */
    public static function stats(int $publisherId, int $days = 1): array
    {
        $out = ['human' => 0, 'ai' => 0, 'agents' => []];
        for ($i = 0; $i < $days; $i++) {
            $day = now()->subDays($i)->toDateString();
            $out['human'] += (int) static::cache()->get("reach:human:{$publisherId}:{$day}", 0);
            foreach (array_unique(array_values(config('reach.ai_agents'))) as $agent) {
                $n = (int) static::cache()->get("reach:ai:{$publisherId}:{$day}:{$agent}", 0);
                if ($n > 0) {
                    $out['agents'][$agent] = ($out['agents'][$agent] ?? 0) + $n;
                    $out['ai'] += $n;
                }
            }
        }

        return $out;
    }

    /** Impression token: a click is only accepted for an offer actually served on this widget in the last 30 minutes. */
    public static function impressionToken(ReachWidget $widget, int $offerId, ?int $slot = null): string
    {
        $slot ??= intdiv(now()->timestamp, 900);

        return substr(hash_hmac('sha256', "{$widget->id}|{$offerId}|{$slot}", (string) config('app.key')), 0, 24);
    }

    public static function validImpression(ReachWidget $widget, int $offerId, string $token): bool
    {
        $slot = intdiv(now()->timestamp, 900);
        foreach ([$slot, $slot - 1] as $s) {
            if (hash_equals(static::impressionToken($widget, $offerId, $s), $token)) {
                return true;
            }
        }

        return false;
    }

    /** Landing URL with the Sub-ID the network sends back in its postback: {ref}_{placement}. */
    public static function trackedUrl(ReachOffer $offer, ReachWidget $widget, string $placement): ?string
    {
        if (! $offer->landing_url) {
            return null;
        }
        $param = config('reach.subid_params.'.$offer->network, 'subid');
        $subId = $widget->publisher->ref.'_'.(preg_replace('/[^A-Za-z0-9\-]/', '', $placement) ?: 'main');

        return $offer->landing_url.(str_contains($offer->landing_url, '?') ? '&' : '?').$param.'='.rawurlencode($subId);
    }
}
