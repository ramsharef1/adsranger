<?php

namespace App\Support\AiWorkspace;

/**
 * AI Workspace M1 item 4 (spec v2 §4, v1 §16.3): the output contract for each client-facing output type. A reply that
 * doesn't fit is sent back with the exact paths that failed ("missing: actions[1].metric"); after 2 tries it is kept only
 * as an internal "needs formatting" draft. M1 has two types: weekly summary and ad copy set.
 */
class OutputContract
{
    public const MAX_TRIES = 2;

    /** @return array<string, array<string, mixed>> */
    public static function types(): array
    {
        return [
            'weekly_summary' => [
                'required' => ['finding' => 'string', 'why' => 'string', 'actions' => 'list', 'watch' => 'string', 'evidence' => 'list'],
                'items' => ['actions' => ['action' => 'string', 'metric' => 'string'], 'evidence' => ['label' => 'string', 'value' => 'number', 'source' => 'string', 'date' => 'string']],
                'min' => ['actions' => 1, 'evidence' => 1],
            ],
            'ad_copy_set' => [
                'required' => ['platform' => 'string', 'dialect' => 'string', 'headlines' => 'list', 'primary_texts' => 'list'],
                'items' => [],
                'min' => ['headlines' => 1, 'primary_texts' => 1],
                // Headline / primary text limits per platform (characters). Unknown platform: the strictest set.
                'limits' => ['meta' => [40, 125], 'google_ads' => [30, 90], 'tiktok' => [100, 100], 'snapchat' => [34, 100], 'default' => [30, 90]],
            ],
            // FS-13 (R7 part 4): alt text for images A1 found without it.
            'alt_text_set' => [
                'required' => ['items' => 'list'],
                'items' => ['items' => ['image' => 'string', 'alt_ar' => 'string', 'alt_en' => 'string']],
                'min' => ['items' => 1],
            ],
            // DS-29 / NM-03 (D-301): rules proposed from a pasted brand guide; a person adds each one.
            'brand_rules_set' => [
                'required' => ['rules' => 'list'],
                'items' => ['rules' => ['rule' => 'string', 'kind' => 'string']],
                'min' => ['rules' => 1],
            ],
            // FS-15 (R7 part 4): negative keywords with their reason and other forms.
            'negatives_set' => [
                'required' => ['negatives' => 'list'],
                'items' => ['negatives' => ['term' => 'string', 'reason' => 'string']],
                'min' => ['negatives' => 0],
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return isset(static::types()[$type]);
    }

    /** @return list<string> problems as paths, empty when the output fits */
    public static function validate(string $type, mixed $output): array
    {
        $c = static::types()[$type] ?? null;
        if ($c === null) {
            return ["unknown output type: $type"];
        }
        if (! is_array($output)) {
            return ['not an object'];
        }
        $out = [];
        foreach ($c['required'] as $field => $kind) {
            if (! array_key_exists($field, $output) || $output[$field] === null || $output[$field] === '' || ($output[$field] === [] && ($c['min'][$field] ?? 1) > 0)) {
                $out[] = "missing: $field";

                continue;
            }
            if ($kind === 'list' && ! array_is_list((array) $output[$field])) {
                $out[] = "not a list: $field";

                continue;
            }
            if ($kind === 'string' && ! is_string($output[$field])) {
                $out[] = "not text: $field";
            }
            if ($kind === 'list' && count($output[$field]) < ($c['min'][$field] ?? 0)) {
                $out[] = "too few: $field (at least ".$c['min'][$field].')';
            }
            foreach ($kind === 'list' ? array_values((array) $output[$field]) : [] as $i => $item) {
                foreach ($c['items'][$field] ?? [] as $sub => $subKind) {
                    $v = is_array($item) ? ($item[$sub] ?? null) : null;
                    if ($v === null || $v === '') {
                        $out[] = "missing: {$field}[$i].$sub";
                    } elseif ($subKind === 'number' && ! is_numeric($v)) {
                        $out[] = "not a number: {$field}[$i].$sub";
                    }
                }
            }
        }
        if ($type === 'ad_copy_set' && $out === []) {
            [$h, $p] = $c['limits'][$output['platform']] ?? $c['limits']['default'];
            foreach ($output['headlines'] as $i => $t) {
                if (mb_strlen((string) $t) > $h) {
                    $out[] = "too long: headlines[$i] (".mb_strlen((string) $t)." of $h)";
                }
            }
            foreach ($output['primary_texts'] as $i => $t) {
                if (mb_strlen((string) $t) > $p) {
                    $out[] = "too long: primary_texts[$i] (".mb_strlen((string) $t)." of $p)";
                }
            }
        }

        return $out;
    }

    /** Plain text of a structured output, for the gate's text checks. */
    public static function text(string $type, array $output): string
    {
        $lines = [];
        array_walk_recursive($output, function ($v, $k) use (&$lines) {
            if (is_string($v) && ! in_array($k, ['source', 'date', 'platform', 'dialect', 'image', 'kind'], true)) {
                $lines[] = $v;
            }
        });

        return implode("\n", $lines);
    }
}
