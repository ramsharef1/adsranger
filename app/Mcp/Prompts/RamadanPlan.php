<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;

class RamadanPlan extends Playbook
{
    protected string $name = 'ramadan_plan';

    protected string $title = 'Ramadan plan «خطة رمضان»';

    protected string $description = 'A Ramadan 2027 plan note for one client: phases, budget split by phase as a share of their own budget, and what to prepare, using season_calendar dates.';

    protected function task(Request $request, string $notes): string
    {
        return 'Call season_calendar for Ramadan 2027, then write a plan note for this client: the phases (before, first 10 days, middle, last 10 nights, Eid), a budget split by phase as percentages of the client\'s own budget, the creative to prepare, and what to watch. Only use numbers from context.facts; percentages are a proposal, label them as such.'
            .($notes !== '' ? ' The person added: '.$notes : '');
    }

    protected function outputType(): ?string
    {
        return null;
    }
}
