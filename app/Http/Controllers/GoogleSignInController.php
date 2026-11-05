<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\GoogleSignIn;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** #74 /app/oauth/google/{redirect,callback} (registered inside the /app panel, see AppPanelProvider). */
class GoogleSignInController extends Controller
{
    public function redirect(): Response
    {
        abort_unless(GoogleSignIn::enabled(), 404);
        if (Filament::auth()->check()) {
            return redirect(Filament::getUrl());
        }

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(GoogleSignIn::enabled(), 404);
        $login = Filament::getLoginUrl();

        try {
            $g = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::info('google sign-in callback failed', ['error' => class_basename($e)]);

            return $this->refuse($login, __('Google sign-in didn\'t finish. Try again.'));
        }

        [$outcome, $user] = GoogleSignIn::resolve($g);

        return match ($outcome) {
            'login' => $this->login($user),
            'two_step' => $this->twoStep($user),
            'signup' => $this->signup($g->getId(), strtolower(trim((string) $g->getEmail())), (string) $g->getName()),
            'closed' => $this->refuse(Filament::getRegistrationUrl() ?? $login, __('No AdsRanger account uses this Google email.')),
            'disposable' => $this->refuse($login, __('Temporary inbox addresses can\'t be used. Use your work or personal email.')),
            'taken' => $this->refuse($login, __('We can\'t start a new trial with this email. If you already have an account, sign in or reset your password.')),
            'pending' => $this->refuse($login, __('Confirm your email with the link we sent you first. Then you can continue with Google.')),
            'admin' => $this->refuse($login, __('This account signs in with its password and two-step verification.')),
            default => $this->refuse($login, __('Google says this email isn\'t verified, so we can\'t use it to sign in.')),
        };
    }

    protected function login(User $user): RedirectResponse
    {
        Filament::auth()->login($user);
        session()->regenerate();
        GoogleSignIn::audit($user, ['new' => false]);

        return redirect()->intended(Filament::getUrl());
    }

    protected function twoStep(User $user): RedirectResponse
    {
        GoogleSignIn::hold(GoogleSignIn::TWO_STEP_SESSION, ['user_id' => $user->getKey()]);

        return redirect(url(Filament::getCurrentOrDefaultPanel()->getPath().'/oauth/google/two-step'));
    }

    protected function signup(string $id, string $email, string $name): RedirectResponse
    {
        GoogleSignIn::hold(GoogleSignIn::SIGNUP_SESSION, ['google_id' => $id, 'email' => $email, 'name' => $name]);

        return redirect(url(Filament::getCurrentOrDefaultPanel()->getPath().'/oauth/google/finish'));
    }

    protected function refuse(string $to, string $message): RedirectResponse
    {
        Notification::make()->title($message)->danger()->persistent()->send();

        return redirect($to);
    }
}
