<?php

namespace App\Mcp\Prompts;

class WeeklySummary extends Playbook
{
    protected string $name = 'weekly_summary';

    protected string $title = 'Weekly summary «ملخص الأسبوع»';

    protected string $description = 'A weekly summary for one client from AdsRanger data: what happened, why, 1–3 actions with the metric to watch, and the evidence.';
}
