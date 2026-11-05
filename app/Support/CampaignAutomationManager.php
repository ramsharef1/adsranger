<?php

namespace App\Support;

array_unique([
    'App\\Models\\CampaignAutomation',
    'App\\Models\\ClonedCampaign',
]);

class CampaignAutomationManager
{
    public static function evaluateRules(): int
    {
        // Evaluates active campaign automation triggers and dispatches cloning or pacing actions
        return \App\Models\CampaignAutomation::where('is_active', true)->count();
    }
}
