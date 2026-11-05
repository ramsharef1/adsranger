<?php

namespace App\Support;

/**
 * #12 Calls split. Meta call ads report three counts: calls placed, calls confirmed (the person tapped "Call" on the confirm
 * screen) and calls that connected for 20 seconds or more. Each client picks which one counts as the result of its call
 * campaigns (`clients.call_result`); cost per result follows. Other networks report one call count, which is used as is.
 */
class CallResults
{
    public const BASES = ['placed', 'confirmed', '20s'];

    /** Networks that report the three-way split. */
    public const SPLIT_NETWORKS = ['meta'];

    public static function options(): array
    {
        return ['placed' => __('Calls placed'), 'confirmed' => __('Calls confirmed'), '20s' => __('Calls 20s+')];
    }

    public static function basis(?string $b): string
    {
        return in_array($b, self::BASES, true) ? $b : 'placed';
    }

    /** Result count of one aggregated row for the chosen basis (only call results on split networks change). */
    public static function results(object $r, string $basis): float
    {
        if (($r->result_type ?? null) !== 'call' || $basis === 'placed' || ! in_array($r->network ?? null, self::SPLIT_NETWORKS, true)) {
            return (float) $r->results;
        }

        return (float) ($basis === 'confirmed' ? $r->calls_confirmed : $r->calls_20s);
    }

    /**
     * Card data for the client page, from MetricsQuery totals. Null when the period has no calls.
     *
     * @return array{placed: float, confirmed: float, connected: float, confirm_rate: ?float, connect_rate: ?float, spend: float, cost_placed: ?float, cost_connected: ?float, split: bool}|null
     */
    public static function summary(array $t): ?array
    {
        $placed = (float) $t['calls'];
        $confirmed = (float) $t['calls_confirmed'];
        $connected = (float) $t['calls_20s'];
        if ($placed + $confirmed + $connected <= 0) {
            return null;
        }
        $spend = (float) ($t['type_spend']['call'] ?? 0);

        return [
            'placed' => $placed, 'confirmed' => $confirmed, 'connected' => $connected,
            'confirm_rate' => $placed > 0 && $confirmed > 0 ? $confirmed / $placed * 100 : null,
            'connect_rate' => $placed > 0 && $connected > 0 ? $connected / $placed * 100 : null,
            'spend' => $spend,
            'cost_placed' => $spend > 0 && $placed > 0 ? $spend / $placed : null,
            'cost_connected' => $spend > 0 && $connected > 0 ? $spend / $connected : null,
            'split' => $confirmed > 0 || $connected > 0,
        ];
    }
}
