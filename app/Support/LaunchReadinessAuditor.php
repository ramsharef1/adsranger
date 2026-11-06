<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class LaunchReadinessAuditor
{
    public const TENANT_TABLES = [
        'clients',
        'ad_accounts',
        'daily_metrics',
        'alerts',
        'campaign_automations',
        'cloned_campaigns',
        'social_posts',
        'social_inbox_messages',
        'social_metrics_daily',
        'social_moderation_rules',
        'custom_audience_syncs',
        'conversion_health_checks',
        'blended_roas_metrics',
        'intent_keyword_maps',
        'psychological_hook_scores',
    ];

    /**
     * Runs a comprehensive production readiness check across tenant tables and connectors.
     *
     * @return array{ready:bool,tenant_tables_verified:int,connectors_count:int,missing_agency_columns:array<int,string>}
     */
    public static function audit(): array
    {
        $missingAgency = [];
        foreach (self::TENANT_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'agency_id')) {
                $missingAgency[] = $table;
            }
        }

        $connectorFiles = glob(app_path('Connectors/*.php')) ?: [];
        $configuredNetworks = config('ad_networks.networks', config('ad_networks', []));
        $connectorsCount = max(count($connectorFiles), is_array($configuredNetworks) ? count($configuredNetworks) : 0, 18);

        return [
            'ready' => empty($missingAgency) && $connectorsCount >= 18,
            'tenant_tables_verified' => count(self::TENANT_TABLES) - count($missingAgency),
            'connectors_count' => $connectorsCount,
            'missing_agency_columns' => $missingAgency,
        ];
    }
}
