<?php

namespace App\Support;

use App\Models\Agency;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * M4.1 & M4.2: Pluggable AI Analyst with 4-hour cache, per-workspace daily live call caps, and honest deterministic fallback (D-229).
 */
class AiAnalyst
{
    /** M4.2 daily live AI call limits per plan tier. */
    public const DAILY_LIMITS = [
        'solo' => 20,
        'starter' => 20,
        'pro' => 75,
        'agency' => 250,
    ];

    public static function dailyLimit(Agency $agency): int
    {
        $tier = PlanGate::tier($agency);

        return self::DAILY_LIMITS[$tier] ?? 20;
    }

    public static function dailyUsed(Agency $agency, ?string $day = null): int
    {
        $day ??= now()->toDateString();

        return (int) Cache::get("ai_daily_calls:{$agency->id}:{$day}", 0);
    }

    public static function analyze(string $prompt, string $locale = 'ar', ?Agency $agency = null): string
    {
        $agency ??= static::resolveAgency();
        $isAr = $locale === 'ar' || preg_match('/\p{Arabic}/u', $prompt) === 1;
        $scope = $agency ? 'agency_'.$agency->id : 'global';

        if (! AiSettings::isConfigured()) {
            $cacheKey = 'ai_analyst_unconfigured_'.md5($scope.'_'.$prompt.'_'.$locale);

            return Cache::remember($cacheKey, now()->addHours(4), fn (): string => static::deterministicFallback($isAr, 'unconfigured'));
        }

        $provider = AiSettings::provider();
        $model = AiSettings::model($provider);
        $cacheKey = 'ai_analyst_live_'.md5($scope.'_'.$provider.'_'.$model.'_'.$prompt.'_'.$locale);

        if (Cache::has($cacheKey)) {
            return (string) Cache::get($cacheKey);
        }

        if ($agency instanceof Agency) {
            $day = now()->toDateString();
            $limit = static::dailyLimit($agency);
            $used = static::dailyUsed($agency, $day);
            if ($used >= $limit) {
                return static::deterministicFallback($isAr, 'capped', $limit);
            }
            Cache::put("ai_daily_calls:{$agency->id}:{$day}", $used + 1, now()->addHours(26));
        }

        $live = static::callLiveProvider($provider, $model, (string) AiSettings::decryptedKey(), $prompt, $isAr);
        if ($live === null) {
            return static::deterministicFallback($isAr, 'error');
        }

        Cache::put($cacheKey, $live, now()->addHours(4));

        return $live;
    }

    protected static function callLiveProvider(string $provider, string $model, string $key, string $prompt, bool $isAr): ?string
    {
        $sys = $isAr
            ? 'أنت محلل أداء إعلاني لوكالات التسويق. أجب بإيجاز استنادًا فقط إلى المعطيات المذكورة دون اختلاق أرقام.'
            : 'You are an agency performance analyst. Reply concisely based strictly on the provided numbers without inventing metrics.';

        try {
            if ($provider === 'openai') {
                $r = Http::timeout(15)->withToken($key)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $sys],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 350,
                ]);

                return $r->successful() ? trim((string) data_get($r->json(), 'choices.0.message.content')) ?: null : null;
            }

            if ($provider === 'anthropic') {
                $r = Http::timeout(15)->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => '2023-06-01',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 350,
                    'system' => $sys,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

                return $r->successful() ? trim((string) data_get($r->json(), 'content.0.text')) ?: null : null;
            }

            if ($provider === 'gemini') {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.urlencode($model).':generateContent?key='.urlencode($key);
                $r = Http::timeout(15)->post($url, [
                    'contents' => [['parts' => [['text' => $sys."\n\n".$prompt]]]],
                ]);

                return $r->successful() ? trim((string) data_get($r->json(), 'candidates.0.content.parts.0.text')) ?: null : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    protected static function deterministicFallback(bool $isAr, string $reason, ?int $limit = null): string
    {
        if ($reason === 'capped') {
            return $isAr
                ? "تحليل قياسي (وضع احتياطي): وصلت مساحة العمل إلى الحد اليومي لمكالمات الذكاء الاصطناعي الحية ({$limit}/يوم)."
                : "Deterministic analysis (fallback): workspace reached its daily live AI call limit ({$limit}/day).";
        }

        if ($reason === 'error') {
            return $isAr
                ? 'تحليل قياسي (وضع احتياطي): تعذّر الاتصال بمزوّد الذكاء الاصطناعي حاليًا، لذا لم يُنفّذ تحليل حي.'
                : 'Deterministic analysis (fallback): AI provider request failed, so live analysis was skipped.';
        }

        return $isAr
            ? 'نص تجريبي: لم يُربط مزوّد ذكاء اصطناعي بعد، لذلك لا يوجد تحليل حقيقي.'
            : 'Sample text: no AI provider is connected yet, so this is not a real analysis.';
    }

    protected static function resolveAgency(): ?Agency
    {
        try {
            $t = Filament::getTenant();

            return $t instanceof Agency ? $t : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
