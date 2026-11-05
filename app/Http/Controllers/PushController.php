<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PushSubscription;
use App\Support\CriticalPush;
use App\Support\PlanGate;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** #175: a phone / browser subscribes to Critical push for the signed-in user (Pro+ workspaces only). */
class PushController extends Controller
{
    public function subscribe(Request $r): JsonResponse
    {
        $agency = Filament::getTenant();
        abort_unless($agency && PlanGate::allows($agency, 'phone_push'), 403);
        $data = $r->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth' => ['required', 'string', 'max:40'],
        ]);
        $sub = PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $r->user()->id,
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'],
            'auth' => $data['keys']['auth'],
            'user_agent' => substr((string) $r->userAgent(), 0, 190),
        ]);
        AuditLog::record('push.subscribed', $agency, ['subscription_id' => $sub->id]);

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $r): JsonResponse
    {
        $agency = Filament::getTenant();
        $endpoint = (string) $r->input('endpoint');
        $q = PushSubscription::where('user_id', $r->user()->id);
        $endpoint !== '' ? $q->where('endpoint_hash', hash('sha256', $endpoint))->delete() : $q->delete();
        AuditLog::record('push.unsubscribed', $agency, []);

        return response()->json(['ok' => true]);
    }

    public function test(Request $r): JsonResponse
    {
        $agency = Filament::getTenant();
        abort_unless($agency && PlanGate::allows($agency, 'phone_push'), 403);
        $loc = $agency->language === 'en' ? 'en' : 'ar';
        $n = CriticalPush::push($r->user()->load('pushSubscriptions'), [
            'title' => __('Test · Phone alerts', [], $loc),
            'body' => __('Phone alerts work on this device.', [], $loc),
        ], CriticalPush::listUrl($agency), 'adsr-test');

        return response()->json(['ok' => $n > 0, 'sent' => $n]);
    }
}
