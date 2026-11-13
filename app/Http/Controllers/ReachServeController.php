<?php

namespace App\Http\Controllers;

use App\Models\ReachCouponCode;
use App\Models\ReachOffer;
use App\Models\ReachPublisher;
use App\Models\ReachWidget;
use App\Support\Reach\Attribution;
use App\Support\Reach\Serve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * FR-REACH R-2 stateless endpoints (module reach; ReachEnabled 404s them while off). No cookies, no visitor data:
 * only per-day counters. CORS-open so a publisher's page can call them.
 */
class ReachServeController extends Controller
{
    /** Web JS tag: offers for one placement. */
    public function serve(Request $request): JsonResponse
    {
        $widget = Serve::widget($request->query('w'));
        if (! $widget || $widget->type !== 'web_js') {
            return $this->json(['ok' => false, 'offers' => []], 404);
        }

        return $this->json(['ok' => true] + $this->payload($widget, $request));
    }

    /** App offerwall (WebView or JSON): incentivised, so only allow_incent offers (hard firewall in Serve). */
    public function offerwall(Request $request): JsonResponse|Response
    {
        $widget = Serve::widget($request->query('w'));
        if (! $widget || ! $widget->isIncentivised()) {
            return $this->json(['ok' => false, 'offers' => []], 404);
        }
        $payload = $this->payload($widget, $request, 10);
        if ($request->query('format') === 'html') {
            return response()->view('reach.offerwall', ['payload' => $payload, 'ar' => $request->query('lang') !== 'en'])
                ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $this->json(['ok' => true] + $payload);
    }

    public function click(Request $request): RedirectResponse|JsonResponse
    {
        $widget = Serve::widget($request->query('w'));
        $offer = $widget ? ReachOffer::where('status', 'active')->find((int) $request->query('o')) : null;
        $placement = mb_substr((string) $request->query('p', $widget?->placement ?? 'main'), 0, 32);
        // 0x0 / stuffed clicks: no valid impression token from a real serve in the last 30 min → no redirect, no count.
        $token = (string) $request->query('t');
        // AI-feed links are read later by agents, so they carry the widget's fixed AI token (slot 0) instead.
        $valid = $offer && $widget && ($placement === 'ai'
            ? hash_equals(Serve::impressionToken($widget, $offer->id, 0), $token)
            : Serve::validImpression($widget, $offer->id, $token));
        if (! $valid) {
            return $this->json(['ok' => false], 404);
        }
        $url = Serve::trackedUrl($offer, $widget, $placement);
        if (! $url) {
            return $this->json(['ok' => false], 404);
        }
        Serve::count('reach:c:'.$offer->id);

        return redirect()->away($url, 302)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    /** Copy intent: the visitor pressed "copy code". Pool offers hand out a rotating code (tier 3 attribution). */
    public function copy(Request $request): JsonResponse
    {
        $widget = Serve::widget($request->input('w'));
        $offer = $widget ? ReachOffer::where('status', 'active')->find((int) $request->input('o')) : null;
        if (! $widget || ! $offer || ! Serve::validImpression($widget, $offer->id, (string) $request->input('t'))) {
            return $this->json(['ok' => false], 404);
        }
        $code = Attribution::copyPoolCode($offer, $widget->publisher, mb_substr((string) $request->input('p', $widget->placement), 0, 32))?->code
            ?? $offer->coupon_code;

        return $this->json(['ok' => $code !== null, 'code' => $code]);
    }

    /** Machine-readable offers for AI agents (schema.org Offer), with this publisher's tracked AI codes. */
    public function aiFeed(Request $request): JsonResponse
    {
        $widget = Serve::widget($request->query('w'));
        if (! $widget) {
            return $this->json(['ok' => false], 404);
        }
        Serve::recordView($widget->publisher_id, Serve::aiAgent($request->userAgent()) ?? 'other-agent');
        $items = collect($this->aiOffers($widget))->map(fn (array $o, int $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'item' => array_filter([
            '@type' => 'Offer', 'name' => $o['title'], 'url' => $o['url'], 'category' => $o['category'],
            'description' => $o['code'] ? 'Promo code: '.$o['code'] : null,
        ])])->values()->all();

        return $this->json(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $widget->publisher->name, 'itemListElement' => $items]);
    }

    /** Plain-text offers block a publisher can include from its /llms.txt. */
    public function llms(string $ref, Request $request): Response
    {
        $publisher = ReachPublisher::where('ref', $ref)->where('status', 'verified')->first();
        $widget = $publisher?->widgets()->where('type', 'web_js')->first();
        abort_unless($widget, 404);
        Serve::recordView($publisher->id, Serve::aiAgent($request->userAgent()) ?? 'other-agent');
        $lines = ['# Offers from '.$publisher->name, ''];
        foreach ($this->aiOffers($widget) as $o) {
            $lines[] = '- ['.$o['title'].']('.$o['url'].')'.($o['code'] ? ' — promo code: '.$o['code'] : '');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=300']);
    }

    public function tag(): Response
    {
        return response((string) file_get_contents(resource_path('reach/tag.js')), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /** @return array{offers: list<array<string, mixed>>, skim: array<string, string>, badge: ?string} */
    private function payload(ReachWidget $widget, Request $request, ?int $limit = null): array
    {
        $agent = Serve::aiAgent($request->userAgent());
        $offers = Serve::pick($widget, array_filter([
            'country' => Serve::country($request), 'os' => Serve::os($request->userAgent()),
            'url' => (string) ($request->query('u') ?: $request->headers->get('referer')), 'limit' => $limit,
        ]));
        Serve::recordView($widget->publisher_id, $agent);
        $placement = mb_substr((string) $request->query('p', $widget->placement), 0, 32);

        $out = [];
        foreach ($offers as $o) {
            Serve::count('reach:v:'.$o->id);
            $t = Serve::impressionToken($widget, $o->id);
            $out[] = [
                'id' => $o->id, 'title' => $o->title, 'type' => $o->offer_type, 'category' => $o->category,
                'has_code' => $o->coupon_code !== null || $o->codes()->where('tier', 'pool')->exists(),
                'click' => url('/reach/v1/click').'?'.http_build_query(['w' => $widget->public_key, 'o' => $o->id, 'p' => $placement, 't' => $t]),
                't' => $t,
            ];
        }
        // Auto-skim: merchant host → tracked click URL; the tag sends the page's own outbound links there on click.
        $skim = [];
        if ($widget->type === 'web_js' && ($widget->settings['auto_skim'] ?? true)) {
            $country = Serve::country($request);
            $os = Serve::os($request->userAgent());
            $hosts = Serve::cache()->remember("reach:skim:{$widget->id}:{$country}:{$os}", (int) config('reach.serve_cache_seconds'),
                fn () => Serve::candidates($widget, $country, $os)->filter->landingHost()->unique(fn ($o) => $o->landingHost())->take(50)
                    ->mapWithKeys(fn ($o) => [$o->landingHost() => $o->id])->all());
            foreach ($hosts as $host => $offerId) {
                $skim[$host] = url('/reach/v1/click').'?'.http_build_query(['w' => $widget->public_key, 'o' => $offerId, 'p' => 'skim', 't' => Serve::impressionToken($widget, $offerId)]);
            }
        }

        return ['offers' => $out, 'skim' => $skim, 'badge' => $widget->publisher->referral_code];
    }

    /** @return list<array{title: string, url: string, category: ?string, code: ?string}> */
    private function aiOffers(ReachWidget $widget): array
    {
        $codes = ReachCouponCode::where('tier', 'ai_bot')->where('publisher_id', $widget->publisher_id)->get()->keyBy('offer_id');

        return collect(Serve::pick($widget, ['limit' => 10]))->map(fn (ReachOffer $o) => [
            'title' => $o->title, 'category' => $o->category, 'code' => $codes[$o->id]->code ?? null,
            'url' => url('/reach/v1/click').'?'.http_build_query(['w' => $widget->public_key, 'o' => $o->id, 'p' => 'ai', 't' => Serve::impressionToken($widget, $o->id, 0)]),
        ])->all();
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Access-Control-Allow-Origin', '*')->header('Cache-Control', 'no-store');
    }
}
