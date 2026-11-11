<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WorkspaceMagicLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class MagicLoginController extends Controller
{
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $link = WorkspaceMagicLink::findByToken($token);
        if (! $link || ! $link->isUsable() || ! $link->agency || ! $link->user) {
            return response()->view('invite.magic-setup', ['state' => 'invalid', 'link' => null, 'token' => $token], 410);
        }

        return response()->view('invite.magic-setup', [
            'state' => 'ready',
            'link' => $link,
            'token' => $token,
            'agency' => $link->agency,
            'user' => $link->user,
        ]);
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        $link = WorkspaceMagicLink::findByToken($token);
        abort_unless($link && $link->isUsable() && $link->agency && $link->user, 410);
        abort_if($link->user->isSuperAdmin() || $link->user->hasTwoStep(), 410);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $user = $link->user;
        $user->forceFill(['name' => trim($data['name']), 'email_verified_at' => $user->email_verified_at ?? now(), 'password' => $data['password']])->save();

        $link->forceFill(['used_at' => now()])->save();
        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::record('agency.magic_link_used', $link->agency, ['user_id' => $user->id]);

        return redirect('/app/'.$link->agency->slug);
    }
}
