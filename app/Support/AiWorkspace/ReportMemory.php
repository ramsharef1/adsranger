<?php

namespace App\Support\AiWorkspace;

use App\Models\AiBrainItem;
use App\Models\Client;
use App\Support\MetricsQuery;
use App\Support\WhatsAppReport;
use Throwable;

/**
 * EG-25 (R7): when a report goes out, its headline numbers become one dated "learning" item in the client brain, so the
 * AI knows what the client was told last month without a new pull. Numbers only, from our own data; one item per period.
 */
class ReportMemory
{
    public static function remember(?Client $client, string $kind, string $period): ?AiBrainItem
    {
        if (! $client) {
            return null;
        }
        $title = ($kind === 'monthly' ? 'Report sent · month ' : 'Report sent · week ').$period;
        if (AiBrainItem::where('client_id', $client->id)->where('playbook', 'report_memory')->where('title', $title)->exists()) {
            return null;
        }
        try {
            [$from, $to] = WhatsAppReport::periodRange($kind, $period, $client->agency?->timezone ?: 'UTC');
            $t = MetricsQuery::totals($client, $from, $to);
            $cur = $client->currency ?: 'USD';
            $lines = ["Period: $from to $to"];
            if (! $client->hide_spend) {
                $lines[] = 'Spend: '.number_format((float) $t['spend'], 2).' '.$cur;
            }
            $lines[] = 'Results: '.number_format((float) ($t['main_results'] ?? 0));
            if (! $client->hide_spend && ($t['cpr'] ?? null)) {
                $lines[] = 'Cost per result: '.number_format((float) $t['cpr'], 2).' '.$cur;
            }
            if ($t['roas'] ?? null) {
                $lines[] = 'ROAS: '.number_format((float) $t['roas'], 2);
            }

            return Brain::save($client, ['type' => 'learning', 'title' => $title, 'body' => implode("\n", $lines), 'playbook' => 'report_memory'])['item'] ?? null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
