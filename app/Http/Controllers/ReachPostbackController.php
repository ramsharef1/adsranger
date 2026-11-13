<?php

namespace App\Http\Controllers;

use App\Models\ReachPostbackEndpoint;
use App\Support\Reach\Postbacks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-REACH R-1: universal S2S conversion postback. 404 while the module is off or the token is unknown. */
class ReachPostbackController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $endpoint = ReachPostbackEndpoint::findActive($token);
        abort_unless($endpoint, 404);

        $trust = Postbacks::trust($endpoint, $request);
        if ($trust !== 'ok') {
            return response()->json(['ok' => false, 'result' => $trust], 403);
        }
        $out = Postbacks::handle($endpoint, $request->query() + $request->post());

        return response()->json($out, $out['ok'] ? 200 : 422)->header('Cache-Control', 'no-store');
    }
}
