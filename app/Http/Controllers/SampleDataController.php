<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\DemoData;
use App\Support\TeamRoles;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/** PS-05 (D-296): one click shows the sample clients in this workspace, one click clears them (owner and admins). */
class SampleDataController extends Controller
{
    public function fill(): RedirectResponse
    {
        $a = Filament::getTenant();
        abort_unless($a && TeamRoles::canManageTeam(auth()->user(), $a), 403);
        if (! DemoData::present($a)) {
            DemoData::seed($a);
            AuditLog::record('sample_data.shown', $a, []);
        }

        return back();
    }

    public function clear(): RedirectResponse
    {
        $a = Filament::getTenant();
        abort_unless($a && TeamRoles::canManageTeam(auth()->user(), $a), 403);
        DemoData::remove($a);
        AuditLog::record('sample_data.cleared', $a, []);

        return back();
    }
}
