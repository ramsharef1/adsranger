<?php

namespace App\Support;

use App\Models\AuditLog;

use App\Models\CreativeBrief;
use App\Models\NetworkLaunch;

class CampaignBuilder
{
    public static function launch(CreativeBrief $brief, array $networks, float $budget = 50.0): NetworkLaunch
    {
        $fullText = $brief->hook . ' ' . $brief->primary_text . ' ' . $brief->headline;
        $check = PolicyPreCheck::analyze($fullText);

        $status = $check['passed'] ? 'published' : 'draft';
        $notes = $check['passed'] ? 'Passed policy pre-check successfully.' : implode(' ', $check['issues']);

        $launch = NetworkLaunch::create([
            'agency_id' => $brief->agency_id,
            'client_id' => $brief->client_id,
            'creative_brief_id' => $brief->id,
            'campaign_name' => $brief->title,
            'networks' => array_values(array_intersect($networks, ['meta', 'google_ads', 'tiktok', 'snapchat'])),
            'budget_amount' => $budget,
            'status' => $status,
            'policy_notes' => $notes,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        AuditLog::record('studio.campaign_launched', $brief->client, ['launch_id' => $launch->id, 'networks' => $launch->networks]);

        return $launch;
    }
}
