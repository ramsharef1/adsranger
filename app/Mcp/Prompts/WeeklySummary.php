<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;

class WeeklySummary extends Playbook
{
    protected string $name = 'weekly_summary';

    protected string $title = 'Weekly summary «ملخص الأسبوع»';

    protected string $description = 'A weekly summary for one client from AdsRanger data: what happened, why, 1–3 actions with the metric to watch, and the evidence.';

    protected function task(Request $request, string $notes): string
    {
        return 'Write this client\'s weekly summary: last 7 days vs the 7 before. One finding, why it happened, 1–3 specific actions each with the metric to watch, and the evidence numbers with source and date.'
            .($notes !== '' ? ' The person added: '.$notes : '');
    }

    protected function outputType(): ?string
    {
        return 'weekly_summary';
    }
}
