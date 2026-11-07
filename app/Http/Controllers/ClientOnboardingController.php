<?php

namespace App\Http\Controllers;

use App\Models\AgencyWebhook;
use App\Models\BlendedRoasMetric;
use App\Models\ClientOnboardingLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientOnboardingController extends Controller
{
    public function show(string $token): View
    {
        $link = ClientOnboardingLink::with(['agency', 'client'])->where('token', $token)->firstOrFail();

        return view('public.client-onboarding', [
            'link' => $link,
            'agency' => $link->agency,
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $link = ClientOnboardingLink::where('token', $token)->firstOrFail();

        $validated = $request->validate([
            'website_url' => ['nullable', 'url', 'max:255'],
            'granted_networks' => ['nullable', 'array'],
            'granted_networks.*' => ['string', 'max:40'],
            'brand_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $link->update([
            'website_url' => $validated['website_url'] ?? $link->website_url,
            'granted_networks' => array_values($validated['granted_networks'] ?? []),
            'brand_notes' => $validated['brand_notes'] ?? $link->brand_notes,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return redirect()->route('client.onboard.show', ['token' => $link->token])
            ->with('onboarding_completed', true);
    }

    /** #77 & #115 Signed JSON feed for Looker Studio / Google Sheets / Zapier / Make / n8n. */
    public function exportFeed(string $token): JsonResponse
    {
        $webhook = AgencyWebhook::with(['agency', 'client'])
            ->where('secret_token', $token)
            ->where('is_active', true)
            ->firstOrFail();

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
}
