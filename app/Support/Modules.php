<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\PlatformSetting;
use App\Models\User;
use Filament\Facades\Filament;

/** ADSR-110 step 0.2 & M3.1: is a held-back module available to this user in this workspace? (config/modules.php + per-agency overrides) */
class Modules
{
    /** @return array<string, array{label: string, description: string, beta_requestable: bool}> */
    public static function catalog(): array
    {
        return [
            'studio' => [
                'label' => __('Ad Studio & Brief Links'),
                'description' => __('Brand kits, bilingual ad drafts, placement previews, and client sign-off links.'),
                'beta_requestable' => true,
            ],
            'organic' => [
                'label' => __('Organic Social Workspace'),
                'description' => __('Content calendar, inbox triage, and engagement summaries.'),
                'beta_requestable' => true,
            ],
            'intelligence_hub' => [
                'label' => __('AI Intelligence Hub'),
                'description' => __('Ask the Platform, keyword intent mapping, and competitor creative radar.'),
                'beta_requestable' => true,
            ],
            'agency_ops_hub' => [
                'label' => __('Agency Operations Pack'),
                'description' => __('Margin tracker, budget reallocation checks, and team workload overview.'),
                'beta_requestable' => true,
            ],
            'webhooks' => [
                'label' => __('Outbound Webhooks & JSON Feeds'),
                'description' => __('Signed alert webhooks and Looker Studio / Sheets JSON export feeds.'),
                'beta_requestable' => true,
            ],
            'client_onboarding' => [
                'label' => __('Client Onboarding Links'),
                'description' => __('Shareable intake questionnaires for new agency clients.'),
                'beta_requestable' => true,
            ],
            'whatsapp_reports' => [
                'label' => __('WhatsApp Scheduled Reports'),
                'description' => __('Automated WhatsApp report summaries alongside email schedules.'),
                'beta_requestable' => true,
            ],
            'self_serve_deletion' => [
                'label' => __('Self-Serve Workspace Deletion'),
                'description' => __('Owner-initiated workspace closure and account deletion controls.'),
                'beta_requestable' => false,
            ],
            'view_as' => [
                'label' => __('View As Role Preview'),
                'description' => __('Read-only workspace preview from another team member\'s role.'),
                'beta_requestable' => false,
            ],
            'seo_audit' => [
                'label' => __('Site audit (SEO)'),
                'description' => __('Arabic-first site audit: RTL, language links, titles, indexing, broken links.'),
                'beta_requestable' => false,
            ],
            'ai_workspace' => [
                'label' => __('AI Workspace (connector + client brain)'),
                'description' => __('Your own AI with your clients\' data: client brain, quality checks, and the AdsRanger connector.'),
                'beta_requestable' => false,
            ],
            'lead_desk' => [
                'label' => __('Lead page for the client\'s front desk'),
                'description' => __('A private link where the client\'s staff log leads and WhatsApp chats, so reports show response time and real sales.'),
                'beta_requestable' => false,
            ],
            'panel_shell' => [
                'label' => __('New panel layout'),
                'description' => __('Areas in the header, a shorter side menu per area, the season line and a bottom bar on phones.'),
                'beta_requestable' => false,
            ],
            'reach' => [
                'label' => __('AdsRanger Reach (publishers)'),
                'description' => __('Publisher offers, coupon attribution, conversion postbacks and estimate wallets. No money moves before launch.'),
                'beta_requestable' => false,
            ],
            'custom_domains' => [
                'label' => __('Custom report domain'),
                'description' => __('Client report links on the agency\'s own domain.'),
                'beta_requestable' => false,
            ],
        ];
    }

    public const GLOBAL_KEY = 'modules.global';

    public const PREVIEW_KEY = 'modules.preview_agencies';

    /**
     * A-10 (FR-030): a super admin can turn a module on or off for everyone in /admin › Platform settings, without a
     * deploy. A module with no switch there follows config/modules.php (the server .env).
     */
    public static function enabled(string $module): bool
    {
        $global = static::globalSwitches();
        if (array_key_exists($module, $global)) {
            return (bool) $global[$module];
        }

        return (bool) config("modules.enabled.$module", false);
    }

    /** @return array<string, bool> module => on/off set in /admin (modules absent here follow the config) */
    public static function globalSwitches(): array
    {
        return once(function (): array {
            try {
                return array_map(fn ($v) => (bool) $v, (array) PlatformSetting::get(self::GLOBAL_KEY, []));
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /** @return list<string> preview workspace slugs: config (.env) plus the ones added in /admin */
    public static function previewAgencies(): array
    {
        $extra = once(function (): array {
            try {
                return array_map('strval', (array) PlatformSetting::get(self::PREVIEW_KEY, []));
            } catch (\Throwable) {
                return [];
            }
        });

        return array_values(array_unique(array_merge((array) config('modules.preview_agencies', []), $extra)));
    }

    public static function isPreviewAgency(?Agency $agency): bool
    {
        return $agency instanceof Agency && in_array($agency->slug, static::previewAgencies(), true);
    }

    /** M3.1: Super Admin per-agency override stored in `agencies.module_overrides`. */
    public static function hasAgencyOverride(string $module, ?Agency $agency = null): bool
    {
        $agency ??= static::tenant();
        if (! $agency instanceof Agency) {
            return false;
        }
        $overrides = (array) ($agency->module_overrides ?? []);

        return ! empty($overrides[$module]);
    }

    /** M3.1: When a module is overridden ON for an agency, unlock its plan-feature gate regardless of tier. */
    public static function overridesFeature(Agency $agency, string $feature): bool
    {
        $featureToModule = [
            'studio' => 'studio',
            'organic' => 'organic',
            'intelligence_hub' => 'intelligence_hub',
            'agency_ops_hub' => 'agency_ops_hub',
            'webhooks' => 'webhooks',
            'client_onboarding' => 'client_onboarding',
            'whatsapp_reports' => 'whatsapp_reports',
            'scheduled_whatsapp' => 'whatsapp_reports',
            'view_as' => 'view_as',
        ];
        $module = $featureToModule[$feature] ?? (array_key_exists($feature, (array) config('modules.enabled', [])) ? $feature : null);

        return $module !== null && static::hasAgencyOverride($module, $agency);
    }

    public static function on(string $module, ?User $user = null, ?Agency $agency = null): bool
    {
        if (static::enabled($module)) {
            return true;
        }
        $agency ??= static::tenant();
        if (static::hasAgencyOverride($module, $agency)) {
            return true;
        }
        $user ??= auth()->user();
        if ($user instanceof User && $user->isSuperAdmin()) {
            return true;
        }

        return static::isPreviewAgency($agency);
    }

    /** True when this user sees the module only as a preview (not globally enabled and not overridden on for the agency). */
    public static function previewOnly(string $module, ?User $user = null, ?Agency $agency = null): bool
    {
        $agency ??= static::tenant();

        return ! static::enabled($module)
            && ! static::hasAgencyOverride($module, $agency)
            && static::on($module, $user, $agency);
    }

    protected static function tenant(): ?Agency
    {
        try {
            $t = Filament::getTenant();
        } catch (\Throwable) {
            return null;
        }

        return $t instanceof Agency ? $t : null;
    }
}
