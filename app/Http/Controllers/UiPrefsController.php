<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Mission shell (D-312): a person's own look (mission / classic) and mode (simple / expert), kept in users.ui_prefs. */
class UiPrefsController extends Controller
{
    public function set(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'look' => ['sometimes', 'in:mission,classic'],
            'mode' => ['sometimes', 'in:simple,expert'],
        ]);
        if ($data !== []) {
            $u = $r->user();
            $u->forceFill(['ui_prefs' => array_merge((array) $u->ui_prefs, $data)])->save();
        }

        return back();
    }
}
