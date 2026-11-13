<?php

namespace App\Support\AiWorkspace;

use App\Models\Agency;
use App\Models\AiKey;
use App\Models\AuditLog;
use App\Support\CriticalPush;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * AI Workspace M1 slice 4 (spec v1 §2 step 5, Product M1 screens §5): calls the agency's own API key. Every call is
 * checked against the key's monthly cap first; spend is counted only from the token counts the provider returns
 * (an estimate from our price table, labelled so; the provider's bill is the real figure). Errors are told as they are.
 */
class OwnKey
{
    /** @return array{0: string, 1: float, 2: float} [label, input USD / 1M tokens, output USD / 1M tokens] */
    public static function model(string $provider, string $model): ?array
    {
        // Model ids contain dots (gpt-4.1-mini), so no config() dot path past the provider.
        return config('ai_workspace.own_key_models')[$provider][$model] ?? null;
    }

    public static function modelLabel(string $provider, string $model): string
    {
        return static::model($provider, $model)[0] ?? $model;
    }

    /** Rough token count: Arabic packs fewer characters per token than English, so count it more heavily. */
    public static function tokens(string $text): int
    {
        $arabic = preg_match_all('/\p{Arabic}/u', $text);
        $other = mb_strlen($text) - $arabic;

        return (int) ceil($arabic / 2.5 + $other / 4);
    }

    /** Worst-case estimate before a run: the full prompt plus the most output we allow. */
    public static function estimate(AiKey $key, string $prompt): float
    {
        [, $in, $out] = static::model($key->provider, $key->model) ?? ['', 5.0, 15.0];

        return round(static::tokens($prompt) * $in / 1e6 + (int) config('ai_workspace.max_output_tokens', 1500) * $out / 1e6, 4);
    }

    /** Check the key with a free call (list models). @return array{0: bool, 1: string} */
    public static function test(string $provider, string $apiKey): array
    {
        try {
            $r = match ($provider) {
                'openai' => Http::timeout(10)->withToken($apiKey)->get('https://api.openai.com/v1/models'),
                'anthropic' => Http::timeout(10)->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])->get('https://api.anthropic.com/v1/models'),
                'gemini' => Http::timeout(10)->withHeaders(['x-goog-api-key' => $apiKey])->get('https://generativelanguage.googleapis.com/v1beta/models'),
            };
        } catch (ConnectionException) {
            return [false, __('The provider did not answer. Try again.')];
        }
        if ($r->successful()) {
            return [true, __('Key works.')];
        }

        return [false, in_array($r->status(), [401, 403], true) ? __('The provider rejected this key.') : __('The provider answered with an error (HTTP :code).', ['code' => $r->status()])];
    }

    /**
     * @return array{ok: bool, text?: string, cost: float, error?: string, billing?: bool}
     */
    public static function complete(AiKey $key, string $system, string $user, bool $json): array
    {
        // FS-01 (D-299): phones, emails and ID-like numbers never leave for the AI; the count goes back to the caller.
        $rs = Redactor::clean($system);
        $ru = Redactor::clean($user);
        [$system, $user] = [$rs['text'], $ru['text']];
        $redacted = Redactor::total($rs['counts']) + Redactor::total($ru['counts']);
        $estimate = static::estimate($key, $system.$user);
        if ($estimate > $key->remaining()) {
            return ['ok' => false, 'cost' => 0.0, 'error' => __('This would go over the monthly cap you set for this key. Raise the cap or wait for next month.')];
        }
        $max = (int) config('ai_workspace.max_output_tokens', 1500);
        try {
            $r = match ($key->provider) {
                'openai' => Http::timeout(60)->withToken($key->api_key)->post('https://api.openai.com/v1/chat/completions', array_filter([
                    'model' => $key->model, 'max_tokens' => $max,
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                    'response_format' => $json ? ['type' => 'json_object'] : null,
                ])),
                'anthropic' => Http::timeout(60)->withHeaders(['x-api-key' => $key->api_key, 'anthropic-version' => '2023-06-01'])->post('https://api.anthropic.com/v1/messages', [
                    'model' => $key->model, 'max_tokens' => $max, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]],
                ]),
                'gemini' => Http::timeout(60)->withHeaders(['x-goog-api-key' => $key->api_key])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($key->model).':generateContent', [
                        'systemInstruction' => ['parts' => [['text' => $system]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                        'generationConfig' => array_filter(['maxOutputTokens' => $max, 'responseMimeType' => $json ? 'application/json' : null]),
                    ]),
            };
        } catch (ConnectionException) {
            return ['ok' => false, 'cost' => 0.0, 'transient' => true, 'redacted' => $redacted, 'error' => __('The provider did not answer. Nothing was counted against your cap.')];
        }

        if ($r->failed()) {
            $msg = (string) ($r->json('error.message') ?? $r->json('error.type') ?? '');
            $billing = in_array($r->status(), [402, 429], true) || preg_match('/quota|billing|credit|insufficient|exhausted/i', $msg);

            // FS-09: a server error or a plain rate limit is worth waiting for; billing is not.
            return ['ok' => false, 'cost' => 0.0, 'billing' => (bool) $billing, 'redacted' => $redacted,
                'transient' => ! $billing && ($r->status() >= 500 || $r->status() === 429),
                'error' => $billing ? __('Your provider account is out of credit or over its limit.') : __('The provider answered with an error (HTTP :code).', ['code' => $r->status()])];
        }

        [$text, $in, $out] = match ($key->provider) {
            'openai' => [$r->json('choices.0.message.content'), $r->json('usage.prompt_tokens'), $r->json('usage.completion_tokens')],
            'anthropic' => [collect($r->json('content', []))->where('type', 'text')->pluck('text')->implode(''), $r->json('usage.input_tokens'), $r->json('usage.output_tokens')],
            'gemini' => [collect($r->json('candidates.0.content.parts', []))->pluck('text')->implode(''), $r->json('usageMetadata.promptTokenCount'), $r->json('usageMetadata.candidatesTokenCount')],
        };
        [, $pin, $pout] = static::model($key->provider, $key->model) ?? ['', 5.0, 15.0];
        $cost = round((int) $in * $pin / 1e6 + (int) $out * $pout / 1e6, 6);
        $key->addSpend($cost);
        static::capNotice($key);

        return ['ok' => trim((string) $text) !== '', 'text' => trim((string) $text), 'cost' => $cost, 'redacted' => $redacted, 'error' => trim((string) $text) === '' ? __('The provider returned an empty answer.') : null];
    }

    /** EG-23 (D-299): once a month, tell the owner when a key passes 80 % of its cap (phone push + the keys page; no email). */
    public static function capNotice(AiKey $key): void
    {
        $month = now()->format('Y-m');
        $cap = (float) $key->monthly_cap_usd;
        if ($cap <= 0 || $key->cap_noticed_month === $month || $key->spentThisMonth() < 0.8 * $cap) {
            return;
        }
        $key->forceFill(['cap_noticed_month' => $month])->save();
        $agency = Agency::find($key->agency_id);
        $owner = $agency?->owner;
        if ($owner && config('push.enabled')) {
            CriticalPush::push($owner, [
                'title' => __('An AI key is at 80% of its monthly cap'),
                'body' => __(':provider key: :spent of :cap USD used this month. At the cap, AI requests on this key stop until next month.',
                    ['provider' => ucfirst($key->provider), 'spent' => number_format($key->spentThisMonth(), 2), 'cap' => number_format($cap, 2)]),
            ], url('/app/'.$agency->slug.'/ai-keys'), 'adsr-ai-cap-'.$key->id);
        }
        if ($agency) {
            AuditLog::record('ai_workspace.cap_80', $agency, ['key' => $key->id]);
        }
    }

    /** Pull the JSON object out of a reply (some models wrap it in a code fence). */
    public static function json(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text)));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }
}
