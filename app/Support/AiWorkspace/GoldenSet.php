<?php

namespace App\Support\AiWorkspace;

use App\Models\AiKey;
use App\Models\PlatformSetting;

/**
 * WS-09 / FS-12 (D-300): a fixed Arabic set (MSA, Gulf, Levant, Egyptian, Arabizi, English) checked by the quality gate.
 * Offline, the saved answers must keep their expected band (a test fails on any change). On demand in /admin, the same
 * tasks go to one provider key Rami picks, cost shown first; the provider scorecard keeps the result. Never scheduled.
 */
class GoldenSet
{
    public const SCORECARD_KEY = 'ai.scorecard';

    /** @return array{version: string, facts: list<array<string, mixed>>, items: list<array<string, string>>} */
    public static function load(): array
    {
        return json_decode((string) file_get_contents(resource_path('ai/golden_set_v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<array{id: string, band: string, expect: string, ok: bool, score: int}> */
    public static function offline(): array
    {
        $g = self::load();
        $out = [];
        foreach ($g['items'] as $i) {
            $r = QualityGate::check($i['saved_answer'], $g['facts'], ['client_facing' => true]);
            $out[] = ['id' => $i['id'], 'band' => $r['band'], 'expect' => $i['expect'], 'ok' => $r['band'] === $i['expect'], 'score' => $r['score']];
        }

        return $out;
    }

    protected static function system(): string
    {
        return "You write for a marketing agency inside AdsRanger.\n- ".implode("\n- ", Playbooks::rules(null));
    }

    /** Estimated USD for one live run on this key. */
    public static function estimate(AiKey $key): float
    {
        $g = self::load();

        return round(array_sum(array_map(fn ($i) => OwnKey::estimate($key, self::system().json_encode(['facts' => $g['facts'], 'task' => $i['task']], JSON_UNESCAPED_UNICODE)), $g['items'])), 4);
    }

    /**
     * Sends each task (the "good" ones: those whose saved answer is expected ready) to the provider and gates the answer.
     *
     * @return array{at: string, provider: string, model: string, total: int, passed: int, avg_score: int, cost: float, seconds: float, failed: list<string>}
     */
    public static function live(AiKey $key): array
    {
        $g = self::load();
        $tasks = array_values(array_filter($g['items'], fn ($i) => $i['expect'] === 'ready'));
        $passed = 0;
        $scores = [];
        $cost = 0.0;
        $failed = [];
        $t0 = microtime(true);
        foreach ($tasks as $i) {
            $r = OwnKey::complete($key, self::system(), json_encode(['context' => ['facts' => $g['facts'], 'currency' => 'SAR'], 'task' => $i['task']], JSON_UNESCAPED_UNICODE), false);
            $cost += $r['cost'];
            if (! $r['ok']) {
                $failed[] = $i['id'].': '.($r['error'] ?? 'error');
                $scores[] = 0;

                continue;
            }
            $gate = QualityGate::check($r['text'], $g['facts'], ['client_facing' => true]);
            $scores[] = $gate['score'];
            if ($gate['band'] !== 'blocked') {
                $passed++;
            } else {
                $failed[] = $i['id'];
            }
        }
        $result = ['at' => now()->toIso8601String(), 'provider' => $key->provider, 'model' => $key->model, 'total' => count($tasks), 'passed' => $passed,
            'avg_score' => $scores ? (int) round(array_sum($scores) / count($scores)) : 0, 'cost' => round($cost, 4),
            'seconds' => round(microtime(true) - $t0, 1), 'failed' => $failed, 'set' => $g['version']];
        $all = (array) PlatformSetting::get(self::SCORECARD_KEY, []);
        $all[$key->provider.'|'.$key->model] = $result;
        PlatformSetting::put(self::SCORECARD_KEY, $all);

        return $result;
    }
}
