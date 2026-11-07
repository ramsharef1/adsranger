<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/** #106: WhatsApp summary message builder, E.164 phone normaliser, wa.me deep-link and Cloud API sender. */
class WhatsAppReport
{
    /** Normalise a phone string into E.164 (+digits, 8..15 digits) or return null if invalid. */
    public static function normalizePhone(string $raw): ?string
    {
        $clean = preg_replace('/[\s\-\(\)]+/', '', trim($raw));
        if (str_starts_with($clean, '00')) {
            $clean = '+'.substr($clean, 2);
        } elseif ($clean !== '' && $clean[0] !== '+') {
            $clean = '+'.$clean;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $clean) ? $clean : null;
    }

    /** Build the plain-text WhatsApp report summary in AR or EN (respecting hide_spend). */
    public static function message(ReportSchedule $s, string $kind, string $period, ?string $linkUrl = null): string
    {
        $c = $s->client;
        $lang = $s->lang === 'en' ? 'en' : 'ar';
        [$from, $to] = static::periodRange($kind, $period, $s->agency?->timezone ?: 'UTC');
        $t = MetricsQuery::totals($c, $from, $to);

        $lines = [];
        if ($lang === 'ar') {
            $title = $kind === 'monthly' ? "التقرير الشهري ({$period})" : "الملخص الأسبوعي ({$period})";
            $lines[] = "📊 *{$c->name}* — {$title}";
            if (! $c->hide_spend) {
                $lines[] = 'الإنفاق: '.number_format($t['spend'], 2).' '.$c->currency;
            }
            $lines[] = 'النتائج: '.number_format($t['main_results']);
            if (! $c->hide_spend && $t['cpr'] !== null) {
                $lines[] = 'تكلفة النتيجة: '.number_format($t['cpr'], 2).' '.$c->currency;
            }
            if ($t['roas'] !== null) {
                $lines[] = 'العائد (ROAS): '.number_format($t['roas'], 2).'x';
            }
            if ($linkUrl) {
                $lines[] = "الرابط المباشر: {$linkUrl}";
            }
        } else {
            $title = $kind === 'monthly' ? "Monthly report ({$period})" : "Weekly summary ({$period})";
            $lines[] = "📊 *{$c->name}* — {$title}";
            if (! $c->hide_spend) {
                $lines[] = 'Spend: '.number_format($t['spend'], 2).' '.$c->currency;
            }
            $lines[] = 'Results: '.number_format($t['main_results']);
            if (! $c->hide_spend && $t['cpr'] !== null) {
                $lines[] = 'Cost per result: '.number_format($t['cpr'], 2).' '.$c->currency;
            }
            if ($t['roas'] !== null) {
                $lines[] = 'ROAS: '.number_format($t['roas'], 2).'x';
            }
            if ($linkUrl) {
                $lines[] = "Live report: {$linkUrl}";
            }
        }

        return implode("\n", $lines);
    }

    /** Direct wa.me click-to-chat URL for sharing in 1 click from the dashboard. */
    public static function waMeUrl(string $phone, string $text): string
    {
        $digits = ltrim(static::normalizePhone($phone) ?? $phone, '+');

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    /** Send via Meta WhatsApp Cloud API when configured; returns true when sent. */
    public static function send(string $phone, string $text): bool
    {
        $phoneId = config('reports.whatsapp_phone_id');
        $token = config('reports.whatsapp_token');
        if (! $phoneId || ! $token) {
            // ADSR-110 rule: no fake success. Without Cloud API keys nothing is sent, so say so.
            throw new \RuntimeException('WhatsApp not configured (no Cloud API keys); nothing was sent.');
        }
        $res = Http::withToken($token)->timeout(15)
            ->post("https://graph.facebook.com/v21.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($phone, '+'),
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $text],
            ]);
        if ($res->failed()) {
            throw new \RuntimeException('WhatsApp API HTTP '.$res->status().': '.$res->body());
        }

        return true;
    }

    /** @return array{0:string,1:string} */
    protected static function periodRange(string $kind, string $period, string $tz): array
    {
        if ($kind === 'monthly' && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01', $tz)->startOfMonth();

            return [$start->toDateString(), $start->endOfMonth()->toDateString()];
        }
        $end = CarbonImmutable::now($tz)->subDay();

        return [$end->subDays(6)->toDateString(), $end->toDateString()];
    }
}
