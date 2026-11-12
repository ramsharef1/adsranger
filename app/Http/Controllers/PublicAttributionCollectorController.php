<?php

namespace App\Http\Controllers;

use App\Models\ClientUnitEconomic;
use App\Models\PostPurchaseSurvey;
use App\Support\Attribution\ProfitAndAttributionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicAttributionCollectorController extends Controller
{
    public function status(string $token): JsonResponse
    {
        $econ = ClientUnitEconomic::with(['agency', 'client'])->where('collector_token', $token)->first();
        abort_unless($econ && $econ->client && $econ->client->status !== 'archived' && ProfitAndAttributionManager::moduleLiveFor($econ->agency), 404);

        return response()->json([
            'ok' => true,
            'relay' => 'ready',
            'mode' => 'simulated',
        ])->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }

    public function collect(Request $request, string $token): JsonResponse
    {
        $econ = ClientUnitEconomic::with(['agency', 'client'])->where('collector_token', $token)->first();
        abort_unless($econ && $econ->client && $econ->client->status !== 'archived' && ProfitAndAttributionManager::moduleLiveFor($econ->agency), 404);

        $validated = $request->validate([
            'event_name' => ['nullable', 'string', 'max:64'],
            'event_dedup_id' => ['nullable', 'string', 'max:128'],
            'fbclid' => ['nullable', 'string', 'max:191'],
            'gclid' => ['nullable', 'string', 'max:191'],
            'ttclid' => ['nullable', 'string', 'max:191'],
            'ScCid' => ['nullable', 'string', 'max:191'],
            'ctwa_clid' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'hashed_contact_sha256' => ['nullable', 'string', 'size:64'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'delivery_mode' => ['nullable', 'string', 'in:simulated,queued_offline'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $ingested = ProfitAndAttributionManager::ingestCollectorRelay($econ, $validated);
        $event = $ingested['event'];

        return response()->json([
            'ok' => true,
            'duplicate' => $ingested['duplicate'],
            'event_id' => $event->id,
            'channel' => $event->channel,
            'delivery_state' => $event->delivery_state,
            'free_window_expires_at' => $event->free_window_expires_at?->toIso8601String(),
        ])->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }

    public function showSurvey(Request $request, string $token): Response
    {
        $survey = PostPurchaseSurvey::with(['agency', 'client'])
            ->where('survey_token', $token)
            ->where('is_active', true)
            ->first();
        abort_unless($survey && $survey->client && $survey->client->status !== 'archived' && ProfitAndAttributionManager::moduleLiveFor($survey->agency), 404);

        $lang = in_array($request->query('lang'), ['ar', 'en'], true)
            ? (string) $request->query('lang')
            : ($survey->agency?->language === 'en' ? 'en' : 'ar');
        app()->setLocale($lang);

        $options = $survey->options_json ?: [
            'tiktok',
            'meta_instagram',
            'snapchat',
            'google_search',
            'whatsapp',
            'friend_recommendation',
        ];

        return response()->view('public.post-purchase-survey', [
            'survey' => $survey,
            'client' => $survey->client,
            'lang' => $lang,
            'options' => $options,
        ])->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'private, no-store');
    }

    public function submitSurvey(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $survey = PostPurchaseSurvey::with(['agency', 'client'])
            ->where('survey_token', $token)
            ->where('is_active', true)
            ->first();
        abort_unless($survey && $survey->client && $survey->client->status !== 'archived' && ProfitAndAttributionManager::moduleLiveFor($survey->agency), 404);

        $validated = $request->validate([
            'channel_choice' => ['required', 'string', 'max:80'],
            'order_ref' => ['nullable', 'string', 'max:120'],
            'order_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        $saved = ProfitAndAttributionManager::recordSurveyResponse($survey, $validated);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'response_id' => $saved->id,
                'channel_choice' => $saved->channel_choice,
            ])->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return redirect()->route('public.survey.show', ['token' => $survey->survey_token])
            ->with('survey_submitted', true)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
