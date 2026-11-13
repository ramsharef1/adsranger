<?php

namespace App\Mcp\Prompts;

class RamadanPlan extends Playbook
{
    protected string $name = 'ramadan_plan';

    protected string $title = 'Ramadan plan «خطة رمضان»';

    protected string $description = 'A Ramadan 2027 plan note for one client: phases, budget split by phase as a share of their own budget, and what to prepare, using season_calendar dates.';
}
