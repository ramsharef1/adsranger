<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AgencyWebhook;
use App\Models\BlendedRoasMetric;
use App\Models\ClientOnboardingLink;
use App\Support\Modules;
use App\Support\Webhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientOnboardingController extends Controller
{
    /** DB-05 (D-292): built now, shown only when Business's legal line is in config (CLIENT_INTAKE_LEGAL_LINE). */
    /** @return array<string, string> */
    public static function intakeLabels(): array
    {
        return [
            'offers' => __('What you sell and your current offers'),
            'audiences' => __('Who your customers are'),
            'competitors' => __('Businesses you compete with'),
            'brand_words' => __('Words that describe your brand'),
            'do' => __('Things your ads should always do'),
            'dont' => __('Things your ads must never do'),
            'contacts' => __('Who approves ads and who gets reports (roles, not passwords)'),
        ];
    }

    public static function intakeLive(): bool
    {
        return filled(config('services.client_intake.legal_line'));
    }

    public function show(string $token): View
    {
        $link = ClientOnboardingLink::with(['agency', 'client'])->where('token', $token)->firstOrFail();
        abort_unless(static::onboardingLive($link->agency), 404);

        return view('public.client-onboarding', [
            'link' => $link,
            'agency' => $link->agency,
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $link = ClientOnboardingLink::with('agency')->where('token', $token)->firstOrFail();
        abort_unless(static::onboardingLive($link->agency), 404);

        $validated = $request->validate([
            'website_url' => ['nullable', 'url', 'max:255'],
            'granted_networks' => ['nullable', 'array'],
            'granted_networks.*' => ['string', 'max:40'],
            'brand_notes' => ['nullable', 'string', 'max:2000'],
            'intake' => ['nullable', 'array'],
            'intake.*' => ['nullable', 'string', 'max:1500'],
        ]);
        // DB-05: the longer intake only counts once Business's legal line is set (D-292); before that the fields are not shown.
        $intake = static::intakeLive()
            ? array_filter(array_intersect_key($validated['intake'] ?? [], static::intakeLabels()), fn ($v) => filled($v))
            : null;

        $link->update([
            'website_url' => $validated['website_url'] ?? $link->website_url,
            'granted_networks' => array_values($validated['granted_networks'] ?? []),
            'brand_notes' => $validated['brand_notes'] ?? $link->brand_notes,
            'intake' => $intake ?: $link->intake,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return redirect()->route('client.onboard.show', ['token' => $link->token])
            ->with('onboarding_completed', true);
    }

    /** #77 & #115 Signed JSON feed for Looker Studio / Google Sheets / Zapier / Make / n8n. */
    public function exportFeed(string $token): JsonResponse
    {
        $webhook = AgencyWebhook::findByFeedToken($token);
        abort_unless($webhook, 404);
        $webhook->loadMissing(['agency', 'client']);
        abort_unless($webhook->agency && Webhooks::liveFor($webhook->agency), 404); // D-229: own module

        $webhook->update(['last_triggered_at' => now()]);

        $metricsQuery = BlendedRoasMetric::where('agency_id', $webhook->agency_id)
            ->orderByDesc('metric_date')
            ->limit(30);

        if ($webhook->client_id) {
            $metricsQuery->where('client_id', $webhook->client_id);
        }

        $rows = $metricsQuery->get([
            'client_id',
            'metric_date',
            'total_ad_spend',
            'total_store_revenue',
            'mer_ratio',
            'efficiency_band',
        ]);

        return response()->json([
            'workspace' => $webhook->agency?->name,
            'feed_name' => $webhook->name,
            'events' => $webhook->events ?? [],
            'generated_at' => now()->toIso8601String(),
            'rows' => $rows,
        ]);
    }

    /** #105 links only open where the client_onboarding module is on (or a preview workspace), D-229. */
    protected static function onboardingLive(?Agency $agency): bool
    {
        return $agency !== null && (Modules::enabled('client_onboarding') || Modules::isPreviewAgency($agency));
    }
}
