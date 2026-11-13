<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use App\Models\AiConnection;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\Client as OAuthClient;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI Workspace connect, step 2–3 (Product M1 screens §2): runs on the OAuth "approve" button. Records which workspace
 * and clients this AI may read (default none), and the required ownership tick `ai-own-v1` with user + time, before
 * Passport issues the code. A previous connection of the same AI for this person is replaced.
 */
class RecordAiConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $agency = Agency::query()->whereKey((int) $request->input('agency_id'))->whereHas('members', fn ($q) => $q->whereKey($user->id))->first();
        $errors = [];
        if (! $agency) {
            $errors['agency_id'] = __('Choose the workspace this AI may read.');
        } elseif (! Modules::on('ai_workspace', $user, $agency)) {
            $errors['agency_id'] = __('AI Workspace is not available for this workspace yet.');
        }
        if (! $request->boolean('ownership')) {
            $errors['ownership'] = __('Please confirm that this AI is your own account.');
        }
        $auth = $request->session()->get('authRequest');
        $authRequest = $auth ? unserialize($auth, ['allowed_classes' => [AuthorizationRequest::class, \Laravel\Passport\Bridge\Client::class, Scope::class, User::class]]) : null;
        if (! $authRequest instanceof AuthorizationRequest) {
            $errors['auth_token'] = __('This request expired. Start the connection again from your AI.');
        }
        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $wanted = array_map('intval', (array) $request->input("clients.{$agency->id}", []));
        $allowed = Client::visibleTo($user, $agency)->whereIn('id', $wanted)->pluck('id')->map(fn ($i) => (int) $i)->values()->all();
        $oauthClient = OAuthClient::find($authRequest->getClient()->getIdentifier());

        AiConnection::query()->where('user_id', $user->id)->where('oauth_client_id', $oauthClient->getKey())->where('status', '!=', 'revoked')
            ->get()->each(fn (AiConnection $old) => $old->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $user->id]));
        $connection = AiConnection::create([
            'agency_id' => $agency->id, 'user_id' => $user->id, 'oauth_client_id' => $oauthClient->getKey(), 'client_name' => mb_substr((string) $oauthClient->name, 0, 250),
            'provider' => AiConnection::guessProvider($oauthClient->name), 'status' => 'waiting', 'allowed_client_ids' => $allowed,
            'ownership_version' => (string) config('ai_workspace.ownership_version'), 'ownership_at' => now(),
        ]);
        AuditLog::record('ai_workspace.connected', $connection, ['ai' => $connection->providerName(), 'clients' => count($allowed), 'ownership' => $connection->ownership_version]);

        return $next($request);
    }
}
