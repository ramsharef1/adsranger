<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Lead;
use App\Support\AiWorkspace\Redactor;

/**
 * XS-13 / XS-23 (R4, D-306): leads and WhatsApp chats logged by the client's own staff (no login) or the agency. The
 * label is a first name or a short note; phone numbers and emails are removed before saving. Reports get response time
 * (speed-to-lead) and WhatsApp results that count: qualified chats and sales.
 */
class LeadDesk
{
    /** @return array<string, array{ar: string, en: string}> */
    public static function channels(): array
    {
        return ['whatsapp' => ['ar' => 'WhatsApp', 'en' => 'WhatsApp'], 'call' => ['ar' => 'مكالمة', 'en' => 'Call'], 'form' => ['ar' => 'نموذج', 'en' => 'Form'],
            'walk_in' => ['ar' => 'زيارة', 'en' => 'Walk-in'], 'other' => ['ar' => 'أخرى', 'en' => 'Other']];
    }

    /** @return array<string, array{ar: string, en: string}> */
    public static function outcomes(): array
    {
        return ['qualified' => ['ar' => 'مهتم', 'en' => 'Qualified'], 'sale' => ['ar' => 'بيع', 'en' => 'Sale'], 'not_relevant' => ['ar' => 'غير مناسب', 'en' => 'Not relevant']];
    }

    public static function cleanLabel(?string $label): ?string
    {
        $l = trim(strip_tags((string) $label));
        if ($l === '') {
            return null;
        }
        $l = trim((string) preg_replace('/\[(phone|email|id) hidden\]/', '', Redactor::clean($l)['text']));

        return $l === '' ? null : mb_substr($l, 0, 60);
    }

    public static function log(Client $c, string $channel, ?string $label, string $via): Lead
    {
        return Lead::create(['agency_id' => $c->agency_id, 'client_id' => $c->id, 'channel' => in_array($channel, Lead::CHANNELS, true) ? $channel : 'other',
            'label' => static::cleanLabel($label), 'received_at' => now(), 'logged_via' => $via === 'app' ? 'app' : 'desk']);
    }

    public static function mark(Lead $l, string $what, ?float $value = null): void
    {
        if ($what === 'contacted') {
            $l->contacted_at ??= now();
        } elseif (in_array($what, Lead::OUTCOMES, true)) {
            $l->contacted_at ??= now();
            $l->outcome = $what;
            if ($what === 'sale' && $value !== null && $value > 0) {
                $l->value = round($value, 2);
            }
        }
        $l->save();
    }

    /**
     * @return array{leads: int, contacted: int, within_hour: ?float, median_minutes: ?int, whatsapp: int, whatsapp_qualified: int, qualified: int, sales: int, sales_value: float, spend: float, cost_per_contacted: ?float, cost_per_sale: ?float}|null
     */
    public static function summary(Client $c, string $from, string $to): ?array
    {
        $rows = Lead::where('client_id', $c->id)->whereBetween('received_at', [$from.' 00:00:00', $to.' 23:59:59'])->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $contacted = $rows->whereNotNull('contacted_at');
        $mins = $contacted->map(fn (Lead $l) => (int) $l->received_at->diffInMinutes($l->contacted_at))->sort()->values();
        $spend = (float) MetricsQuery::totals($c, $from, $to)['spend'];
        $sales = $rows->where('outcome', 'sale');

        return ['leads' => $rows->count(), 'contacted' => $contacted->count(),
            'within_hour' => $rows->count() ? round($mins->filter(fn ($m) => $m <= 60)->count() / $rows->count() * 100, 1) : null,
            'median_minutes' => $mins->isNotEmpty() ? (int) $mins[intdiv($mins->count() - 1, 2)] : null,
            'whatsapp' => $rows->where('channel', 'whatsapp')->count(),
            'whatsapp_qualified' => $rows->where('channel', 'whatsapp')->whereIn('outcome', ['qualified', 'sale'])->count(),
            'qualified' => $rows->whereIn('outcome', ['qualified', 'sale'])->count(), 'sales' => $sales->count(), 'sales_value' => (float) $sales->sum('value'),
            'spend' => $spend, 'cost_per_contacted' => $contacted->count() ? round($spend / $contacted->count(), 2) : null,
            'cost_per_sale' => $sales->count() ? round($spend / $sales->count(), 2) : null];
    }
}
