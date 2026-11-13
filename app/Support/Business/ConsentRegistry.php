<?php

namespace App\Support\Business;

use App\Models\PartnerApplication;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** NB-32: every consent text version we store, where it lives and how many people gave it. Counts only. */
class ConsentRegistry
{
    /** @return list<array{version: string, where: string, count: int}> */
    public static function rows(): array
    {
        $rows = [];
        $group = function (string $table, string $column, string $where) use (&$rows): void {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                return;
            }
            foreach (DB::table($table)->whereNotNull($column)->selectRaw("$column as v, count(*) as n")->groupBy($column)->get() as $r) {
                $rows[] = ['version' => (string) $r->v, 'where' => $where, 'count' => (int) $r->n];
            }
        };
        $group('waitlist_entries', 'consent_text_version', __('Waitlist'));
        $group('partner_applications', 'consent_text_version', __('Partner applications'));
        $group('ai_connections', 'ownership_version', __('AI connections (your AI, your output)'));
        if (Schema::hasTable('seo_sites')) {
            $rows[] = ['version' => 'site-audit (O-373)', 'where' => __('Site audit ownership'), 'count' => (int) DB::table('seo_sites')->whereNotNull('consent_at')->count()];
        }
        if ($rows === []) {
            $rows[] = ['version' => WaitlistEntry::CONSENT_TEXT_VERSION, 'where' => __('Waitlist'), 'count' => 0];
            $rows[] = ['version' => PartnerApplication::CONSENT_TEXT_VERSION, 'where' => __('Partner applications'), 'count' => 0];
        }

        return $rows;
    }
}
