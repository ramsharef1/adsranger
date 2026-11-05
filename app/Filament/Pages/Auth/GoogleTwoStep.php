<?php

namespace App\Filament\Pages\Auth;

use App\Auth\AppAuthentication;
use App\Models\User;
use App\Support\GoogleSignIn;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * #74 /app/oauth/google/two-step: Google sign-in never skips two-step verification. The Google identity is held
 * in the session (10 minutes) and the session only starts after a valid authenticator code or recovery code.
 *
 * @property-read Schema $form
 */
class GoogleTwoStep extends SimplePage
{
    public ?array $data = [];

    public function mount(): void
    {
        if (! $this->heldUser()) {
            $this->redirect(Filament::getLoginUrl());

            return;
        }
        $this->form->fill();
    }

    protected function heldUser(): ?User
    {
        $held = GoogleSignIn::held(GoogleSignIn::TWO_STEP_SESSION);
        $user = $held ? User::find($held['user_id']) : null;

        return ($user && $user->hasTwoStep() && ! $user->isSuperAdmin()) ? $user : null;
    }

    #[Exposed]
    public function verify(): void
    {
        $user = $this->heldUser();
        if (! $user) {
            $this->redirect(Filament::getLoginUrl());

            return;
        }

        $key = 'adsr:google-2fa:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Notification::make()->title(trans_choice('{1} Too many attempts. Try again in :count minute.|[2,*] Too many attempts. Try again in :count minutes.', max(1, (int) ceil(RateLimiter::availableIn($key) / 60))))->danger()->send();

            return;
        }
        RateLimiter::hit($key, 300);

        $this->form->validate();

        RateLimiter::clear($key);
        session()->forget(GoogleSignIn::TWO_STEP_SESSION);
        Filament::auth()->login($user);
        session()->regenerate();
        GoogleSignIn::audit($user, ['new' => false, 'two_step' => true]);

        $this->redirect(session()->pull('url.intended', Filament::getUrl()));
    }

    public function form(Schema $schema): Schema
    {
        $user = $this->heldUser();

        return $schema->components($user ? AppAuthentication::configured()->getChallengeFormComponents($user) : [])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('verify')
            ->footer([
                Actions::make([
                    Action::make('verify')->label(__('filament-panels::auth/pages/login.multi_factor.form.actions.authenticate.label'))->submit('verify'),
                ])->fullWidth()->key('form-actions'),
            ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-panels::auth/pages/login.multi_factor.heading');
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('filament-panels::auth/pages/login.multi_factor.subheading');
    }
}
