<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * M4.1: Super-admin AI provider selector, model picker, encrypted API key storage, and honest connection test (D-229).
 */
class AiSettings
{
    public const KEY_PROVIDER = 'ai.provider';

    public const KEY_MODEL = 'ai.model';

    public const KEY_API_ENCRYPTED = 'ai.api_key_encrypted';

    /** @return array<string, array{label: string, models: array<string, string>}> */
    public static function providers(): array
    {
        return [
            'openai' => [
                'label' => 'OpenAI',
                'models' => [
                    'gpt-4o-mini' => 'GPT-4o mini',
                    'gpt-4o' => 'GPT-4o',
                ],
            ],
            'anthropic' => [
                'label' => 'Anthropic',
                'models' => [
                    'claude-3-5-haiku-latest' => 'Claude 3.5 Haiku',
                    'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet',
                ],
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'models' => [
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                    'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                ],
            ],
        ];
    }

    public static function provider(): string
    {
        $p = (string) PlatformSetting::get(self::KEY_PROVIDER, 'openai');

        return array_key_exists($p, static::providers()) ? $p : 'openai';
    }

    public static function model(?string $provider = null): string
    {
        $provider ??= static::provider();
        $models = static::providers()[$provider]['models'] ?? [];
        $saved = (string) PlatformSetting::get(self::KEY_MODEL, '');

        return array_key_exists($saved, $models) ? $saved : (string) array_key_first($models);
    }

    public static function saveConfig(string $provider, string $model, ?string $newPlainKey = null): void
    {
        $provider = array_key_exists($provider, static::providers()) ? $provider : 'openai';
        $models = static::providers()[$provider]['models'];
        $model = array_key_exists($model, $models) ? $model : (string) array_key_first($models);

        PlatformSetting::put(self::KEY_PROVIDER, $provider);
        PlatformSetting::put(self::KEY_MODEL, $model);

        if ($newPlainKey !== null && trim($newPlainKey) !== '') {
            PlatformSetting::put(self::KEY_API_ENCRYPTED, Crypt::encryptString(trim($newPlainKey)));
        }
    }

    public static function clearApiKey(): void
    {
        PlatformSetting::put(self::KEY_API_ENCRYPTED, null);
    }

    public static function decryptedKey(): ?string
    {
        $enc = PlatformSetting::get(self::KEY_API_ENCRYPTED);
        if (! is_string($enc) || $enc === '') {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function maskedKey(): ?string
    {
        $plain = static::decryptedKey();
        if ($plain === null || $plain === '') {
            return null;
        }
        $tail = substr($plain, -4);

        return '••••••••'.$tail;
    }

    public static function isConfigured(): bool
    {
        return static::decryptedKey() !== null;
    }

    /**
     * M4.1: Honest 1-click connection test (D-229). Never fakes success when unconfigured or rejected.
     *
     * @return array{ok: bool, mode: string, message: string}
     */
    public static function testConnection(): array
    {
        $provider = static::provider();
        $model = static::model($provider);
        $key = static::decryptedKey();

        if (! $key) {
            return [
                'ok' => false,
                'mode' => 'fallback',
                'message' => __('No API key is stored yet. Deterministic fallback mode is active.'),
            ];
        }

        try {
            $resp = match ($provider) {
                'openai' => Http::timeout(8)->withToken($key)->get('https://api.openai.com/v1/models'),
                'anthropic' => Http::timeout(8)->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => '2023-06-01',
                ])->get('https://api.anthropic.com/v1/models'),
                'gemini' => Http::timeout(8)->get('https://generativelanguage.googleapis.com/v1beta/models?key='.urlencode($key)),
                default => null,
            };

            if ($resp && $resp->successful()) {
                return [
                    'ok' => true,
                    'mode' => 'live',
                    'message' => __('Connected to :provider (:model) successfully.', ['provider' => static::providers()[$provider]['label'], 'model' => $model]),
                ];
            }

            $status = $resp ? $resp->status() : 0;

            return [
                'ok' => false,
                'mode' => 'fallback',
                'message' => __('Provider check failed (HTTP :status). Deterministic fallback remains active.', ['status' => $status]),
            ];
        } catch (\Throwable) {
            return [
                'ok' => false,
                'mode' => 'fallback',
                'message' => __('Could not reach :provider. Deterministic fallback remains active.', ['provider' => static::providers()[$provider]['label']]),
            ];
        }
    }
}
