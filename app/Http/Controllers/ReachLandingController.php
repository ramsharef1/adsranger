<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** FR-REACH R-2: /reach and /en/reach landing (noindex, out of the sitemap, menus and share cards; 404 while off). */
class ReachLandingController extends Controller
{
    public function __invoke(Request $request, string $lang = 'ar'): Response
    {
        $ref = strtolower((string) $request->query('ref', ''));
        if (preg_match('/^[a-z0-9]{8}$/', $ref)) {
            $request->session()->put('reach_ref', $ref);
        }
        app()->setLocale($lang === 'en' ? 'en' : 'ar');

        return response()->view('reach.landing', ['ar' => $lang !== 'en', 'ref' => (string) $request->session()->get('reach_ref', '')]);
    }
}
