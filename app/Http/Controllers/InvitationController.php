<?php

namespace App\Http\Controllers;

use App\Models\AgencyInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** Accepting an agency invite: existing users sign in and join; new users set a name + password. */
class InvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invite = AgencyInvitation::findByToken($token);
        if (! $invite || ! $invite->isUsable()) {
            return response()->view('invite.page', ['state' => 'invalid', 'invite' => null], 410);
        }

        $user = $request->user();

        // EH-11: a link invite has no email; whoever opens it signs in or creates an account, then joins (one use).
        if ($invite->via_link && $invite->email === null) {
            $state = ! $user ? 'register' : ($invite->agency->members()->whereKey($user->id)->exists() ? 'member' : 'join');

            return view('invite.page', compact('state', 'invite', 'token', 'user'));
        }

        $existing = User::where('email', $invite->email)->exists();

        if (! $user && $existing) {
            return redirect()->guest('/app/login');
        }

        $state = ! $user ? 'register' : ($user->email === $invite->email ? 'join' : 'mismatch');

        return view('invite.page', compact('state', 'invite', 'token', 'user'));
    }

    public function accept(Request $request, string $token)
    {
        $invite = AgencyInvitation::findByToken($token);
        abort_unless($invite && $invite->isUsable(), 410);

        $user = $request->user();
        $byLink = $invite->via_link && $invite->email === null;

        if (! $user) {
            $rules = [
                'name' => ['required', 'string', 'max:120'],
                'password' => ['required', 'confirmed', Password::min(10)],
            ];
            if ($byLink) {
                // D-293: the link is the proof (handed to one person, 24 h, one use); the email is marked confirmed.
                $rules['email'] = ['required', 'email:filter', 'max:190', 'unique:users,email'];
            } else {
                abort_if(User::where('email', $invite->email)->exists(), 409);
            }
            $data = $request->validate($rules, [
                'email.unique' => __('This email already has an account. Sign in first, then open the link again.'),
            ]);
            $email = $byLink ? strtolower(trim($data['email'])) : $invite->email;
            $user = DB::transaction(function () use ($data, $email) {
                $u = new User(['name' => $data['name'], 'email' => $email, 'password' => $data['password']]);
                $u->email_verified_at = now();
                $u->save();

                return $u;
            });
            Auth::login($user);
            $request->session()->regenerate();
        }

        abort_unless($byLink || $user->email === $invite->email, 403);

        $invite->accept($user);

        return redirect('/app/'.$invite->agency->slug);
    }
}
