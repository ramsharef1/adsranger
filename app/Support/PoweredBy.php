<?php

namespace App\Support;

use App\Models\Agency;

/**
 * BX-05 (D-278, narrowed by D-280): a small "Powered by AdsRanger" line on client reports only (/r pages, the monthly
 * PDF; report emails follow the same switch, as plain text). On by default; the agency switches it off in its profile.
 * Never on AI Workspace shares (D-273). The link is nofollow.
 */
class PoweredBy
{
    public static function on(?Agency $agency): bool
    {
        // Null (never set) counts as on; the database may hand back 0 / "0" rather than false.
        return $agency === null || $agency->report_powered_by === null || (bool) $agency->report_powered_by;
    }

    public static function html(?Agency $agency, bool $link = true): string
    {
        if (! static::on($agency)) {
            return '';
        }
        $brand = '<bdi dir="ltr">AdsRanger</bdi>';
        if ($link) {
            $brand = '<a href="https://adsranger.com/" rel="nofollow noopener" target="_blank">'.$brand.'</a>';
        }

        return __('report.powered_by', ['brand' => $brand]);
    }
}
