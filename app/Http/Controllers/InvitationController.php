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

        if (! $user) {
            abort_if(User::where('email', $invite->email)->exists(), 409);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'password' => ['required', 'confirmed', Password::min(10)],
            ]);
            $user = DB::transaction(function () use ($data, $invite) {
                $u = new User(['name' => $data['name'], 'email' => $invite->email, 'password' => $data['password']]);
                $u->email_verified_at = now();
                $u->save();

                return $u;
            });
            Auth::login($user);
            $request->session()->regenerate();
        }

        abort_unless($user->email === $invite->email, 403);

        $invite->accept($user);

        return redirect('/app/'.$invite->agency->slug);
    }
}
