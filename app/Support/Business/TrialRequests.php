<?php

namespace App\Support\Business;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Trial;
use App\Support\WebPush;
use Illuminate\Support\Facades\DB;

/** PS-32 (D-289): an owner asks for more trial time with one reason; Rami grants days by hand in /admin › Customers. */
class TrialRequests
{
    public static function open(Agency $a): ?object
    {
        return DB::table('trial_extension_requests')->where('agency_id', $a->id)->where('status', 'open')->first();
    }

    public static function latest(Agency $a): ?object
    {
        return DB::table('trial_extension_requests')->where('agency_id', $a->id)->orderByDesc('id')->first();
    }

    public static function ask(Agency $a, User $user, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '' || self::open($a)) {
            return false;
        }
        $id = DB::table('trial_extension_requests')->insertGetId(['agency_id' => $a->id, 'user_id' => $user->id, 'reason' => mb_substr($reason, 0, 300), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        AuditLog::record('trial.extension_requested', $a, ['request' => $id]);
        if (config('push.enabled')) {
            foreach (PushSubscription::whereIn('user_id', User::where('role', 'super_admin')->pluck('id'))->get() as $sub) {
                try {
                    WebPush::send($sub, ['title' => __('Trial extension request'), 'body' => mb_substr($a->name.': '.$reason, 0, 300), 'url' => '/admin/customers']);
                } catch (\Throwable) {
                }
            }
        }

        return true;
    }

    public static function decide(int $id, bool $grant, int $days, ?int $adminId): void
    {
        $r = DB::table('trial_extension_requests')->where('id', $id)->where('status', 'open')->first();
        if (! $r) {
            return;
        }
        $a = Agency::find($r->agency_id);
        if ($grant && $a) {
            Trial::extend($a, max(1, min(30, $days)));
        }
        DB::table('trial_extension_requests')->where('id', $id)->update(['status' => $grant ? 'granted' : 'declined', 'days' => $grant ? $days : null, 'handled_by' => $adminId, 'updated_at' => now()]);
        AuditLog::record($grant ? 'trial.extension_granted' : 'trial.extension_declined', $a, ['request' => $id, 'days' => $grant ? $days : null]);
    }
}
