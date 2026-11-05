<?php

namespace App\Filament\Pages\Auth;

use App\Support\GoogleSignIn;
use App\Support\Signup;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;

/**
 * #74 /app/oauth/google/finish: the short step after "Continue with Google" for a new email while signup is open.
 * Asks only agency name + country (+ consent); the Google email is already verified, so the agency (Starter plan on
 * the 14-day Pro trial, D-61) is created at once. All /app/register rules are checked again on submit.
 *
 * @property-read Schema $form
 */
class GoogleFinishSignup extends SimplePage
{
    public ?array $data = [];

    public string $email = '';

    public function mount(): void
    {
        $held = GoogleSignIn::held(GoogleSignIn::SIGNUP_SESSION);
        if (! GoogleSignIn::enabled() || ! $held) {
            $this->redirect(Filament::getLoginUrl());

            return;
        }
        if (! Signup::isOpen()) {
            $this->redirect(Filament::getRegistrationUrl() ?? Filament::getLoginUrl());

            return;
        }
        $this->email = $held['email'];
        $this->form->fill();
    }

    #[Exposed]
    public function finish(): void
    {
        $held = GoogleSignIn::held(GoogleSignIn::SIGNUP_SESSION);
        if (! $held) {
            $this->redirect(Filament::getLoginUrl());

            return;
        }
        if (! Signup::isOpen()) {
            session()->forget(GoogleSignIn::SIGNUP_SESSION);
            $this->redirect(Filament::getRegistrationUrl() ?? Filament::getLoginUrl());

            return;
        }

        $key = Register::throttleKey();
        $max = (int) config('adsranger.signup.attempts_per_hour', 5);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            Notification::make()->title(__('Too many attempts. Try again in :minutes minutes.', ['minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60))]))->danger()->send();

            return;
        }
        RateLimiter::hit($key, 3600);

        $email = $held['email'];
        if (Signup::isDisposable($email) || Signup::emailTaken($email) || Signup::domainHasTrial($email)) {
            session()->forget(GoogleSignIn::SIGNUP_SESSION);
            Notification::make()->title(__('We can\'t start a new trial with this email. If you already have an account, sign in or reset your password.'))->danger()->persistent()->send();
            $this->redirect(Filament::getLoginUrl());

            return;
        }

        $data = $this->form->getState();
        $user = GoogleSignIn::createAccount($held, $data['agency_name'], $data['country'] ?? null, app()->getLocale());
        session()->forget(GoogleSignIn::SIGNUP_SESSION);

        Filament::auth()->login($user);
        session()->regenerate();
        GoogleSignIn::audit($user, ['new' => true]);

        $this->redirect(Filament::getUrl());
    }

    public function form(Schema $schema): Schema
    {
        $countries = collect(config('adsranger.countries'))->map(fn ($n) => __($n))->all();

        return $schema->components([
            TextInput::make('agency_name')->label(__('Agency name'))->required()->maxLength(120)->autofocus(),
            Select::make('country')->label(__('Country'))->options($countries)->searchable()->required(),
            Checkbox::make('consent')->accepted()->dehydrated(false)
                ->label(new HtmlString(__('I agree to the :terms and the :privacy.', [
                    'terms' => '<a href="/terms" target="_blank" style="text-decoration:underline">'.e(__('Terms of use')).'</a>',
                    'privacy' => '<a href="/privacy" target="_blank" style="text-decoration:underline">'.e(__('Privacy policy')).'</a>',
                ]))),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(new HtmlString(e(__('Signed in with Google as')).' <bdi dir="ltr">'.e($this->email).'</bdi>')),
            $this->getFormContentComponent(),
            Text::make(__('14 days on Pro · up to 5 ad accounts and 3 team members · no card needed')),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('finish')
            ->footer([
                Actions::make([
                    Action::make('finish')->label(__('Create account and start your trial'))->submit('finish'),
                ])->fullWidth()->key('form-actions'),
            ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('Almost done');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Almost done');
    }
}
