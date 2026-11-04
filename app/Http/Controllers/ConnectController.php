<?php

namespace App\Http\Controllers;

use App\Connectors\ConnectorRegistry;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Connection;
use App\Support\NetworkAccess;
use App\Support\PlanLimits;
use App\Support\TeamRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** OAuth connect flow: /app/connect/{network}/start?agency=slug → network → /app/connect/{network}/callback */
class ConnectController extends Controller
{
    public function start(Request $request, string $network)
    {
        $net = AdNetwork::where('key', $network)->firstOrFail();
        $agency = Agency::where('slug', (string) $request->query('agency'))->firstOrFail();
        $user = $request->user();

        abort_unless($user->canAccessTenant($agency) && TeamRoles::canManageData($user, $agency), 403);
        abort_unless(NetworkAccess::allows($user, $agency, $net), 403, 'This network is not available for your workspace.');

        $connector = ConnectorRegistry::for($net);
        if (! $connector->supportsConnect()) {
            return redirect('/app/'.$agency->slug.'/networks')->with('connect_error', $net->name.' cannot be connected yet.');
        }

        $state = Str::random(40);
        $request->session()->put('oauth_connect', ['state' => $state, 'agency_id' => $agency->id, 'network' => $net->key, 'at' => now()->timestamp]);

        return redirect()->away($connector->authorizeUrl($state));
    }

    public function callback(Request $request, string $network)
    {
        $saved = $request->session()->pull('oauth_connect');
        $net = AdNetwork::where('key', $network)->firstOrFail();
        abort_unless($saved && hash_equals($saved['state'], (string) $request->query('state')) && $saved['network'] === $net->key, 419, 'This connect link expired. Please start again.');
        abort_if(now()->timestamp - $saved['at'] > 900, 419, 'This connect link expired. Please start again.');

        $agency = Agency::findOrFail($saved['agency_id']);
        $user = $request->user();
        abort_unless($user->canAccessTenant($agency) && TeamRoles::canManageData($user, $agency), 403);
        $back = '/app/'.$agency->slug.'/connections';

        $code = (string) ($request->query('code') ?? $request->query('oauth_verifier') ?? $request->query('auth_code'));
        if ($request->query('error') || $request->query('denied') || $code === '') {
            return redirect($back)->with('connect_error', 'Connection cancelled: '.Str::limit((string) ($request->query('error_description') ?? $request->query('error')), 160));
        }

        $connector = ConnectorRegistry::for($net);
        try {
            $tok = $connector->exchange($code);
        } catch (\Throwable $e) {
            return redirect($back)->with('connect_error', Str::limit($e->getMessage(), 200));
        }

        $connection = Connection::create([
            'agency_id' => $agency->id, 'ad_network_id' => $net->id, 'created_by' => $user->id,
            'status' => 'active', 'environment' => $net->environment,
            'external_user_id' => $tok['user_id'], 'external_user_name' => $tok['user_name'],
            'access_token' => $tok['access_token'], 'refresh_token' => $tok['refresh_token'],
            'token_expires_at' => $tok['expires_in'] ? now()->addSeconds((int) $tok['expires_in'] - 60) : null,
            'scopes' => $tok['scopes'],
        ]);
        AuditLog::record('connection.created', $agency, ['network' => $net->key, 'connection_id' => $connection->id]);

        $found = static::importAccounts($connector, $connection, $agency);

        return redirect($back)->with('connect_ok', $net->name.' connected. '.$found.' account(s) found.');
    }

    /** Save the ad accounts a new connection can reach. New accounts are tracked while the plan allows. Returns how many were found. */
    public static function importAccounts(\App\Connectors\Connector $connector, Connection $connection, Agency $agency): int
    {
        $found = 0;
        try {
            foreach ($connector->accounts($connection) as $a) {
                $acc = AdAccount::firstOrNew(['connection_id' => $connection->id, 'external_id' => $a['id']]);
                $acc->fill(['agency_id' => $agency->id, 'ad_network_id' => $connection->ad_network_id, 'name' => $a['name'], 'currency' => $a['currency'], 'timezone' => $a['timezone'], 'status' => $a['status']] + (isset($a['meta']) ? ['meta' => $a['meta']] : []));
                if (! $acc->exists) {
                    $acc->is_active = PlanLimits::canActivateAdAccount($agency);
                }
                $acc->save();
                $found++;
            }
            $connection->update(['last_synced_at' => now(), 'last_error' => null]);
        } catch (\Throwable $e) {
            $connection->update(['status' => 'error', 'last_error' => Str::limit($e->getMessage(), 500)]);
        }

        return $found;
    }
}
