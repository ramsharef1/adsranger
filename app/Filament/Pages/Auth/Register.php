<?php

namespace App\Filament\Pages\Auth;

use App\Support\GoogleSignIn;
use App\Support\Signup;
use App\Support\TrialNotice;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\Events\Registered;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

/**
 * /app/register (#158, FR-001). Flag `signup.open` off (default): the invite-only copy (D-53), no form, nothing saved.
 * Flag on: name, email, password (min 10), agency name, country, consent; honeypot; 5 tries per IP per hour;
 * disposable domains refused; one trial per business domain (D-61). The agency is created only after the email
 * is verified (App\Support\Signup::completeVerified).
 */
class Register extends BaseRegister
{
    public bool $open = false;

    public function mount(): void
    {
        $this->open = Signup::isOpen();
        if ($this->open) {
            parent::mount();
        }
    }

    public static function throttleKey(): string
    {
        return 'adsr:signup:ip:'.sha1((string) request()->ip());
    }

    public function register(): ?RegistrationResponse
    {
        // Re-check the flag on every POST: a stale form never creates an account once signup is closed.
        if (! Signup::isOpen()) {
            $this->open = false;

            return null;
        }

        $key = static::throttleKey();
        $max = (int) config('adsranger.signup.attempts_per_hour', 5);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            Notification::make()->title(__('Too many attempts. Try again in :minutes minutes.', ['minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60))]))->danger()->send();

            return null;
        }
        RateLimiter::hit($key, 3600);

        // Honeypot: people never see this field; anything typed in it means a bot. Nothing is saved.
        if (filled($this->data['website'] ?? null)) {
            return null;
        }

        $user = $this->wrapInDatabaseTransaction(function (): Model {
            $data = $this->form->getState();

            return $this->handleRegistration($data);
        });

        event(new Registered($user));
        $this->sendEmailVerificationNotification($user);
        Filament::auth()->login($user);
        session()->regenerate();

        return app(RegistrationResponse::class);
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $user = new ($this->getUserModel())([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
        ]);
        $user->signup_payload = [
            'agency_name' => $data['agency_name'],
            'country' => $data['country'] ?? null,
            'locale' => app()->getLocale() === 'en' ? 'en' : 'ar',
        ];
        $user->save();

        return $user;
    }

    public function form(Schema $schema): Schema
    {
        if (! $this->open) {
            return $schema->components([]);
        }

        $countries = collect(config('adsranger.countries'))->map(fn ($n) => __($n))->all();

        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            TextInput::make('agency_name')->label(__('Agency name'))->required()->maxLength(120),
            Select::make('country')->label(__('Country'))->options($countries)->searchable()->required(),
            Checkbox::make('consent')->accepted()->dehydrated(false)
                ->label(new HtmlString(__('I agree to the :terms and the :privacy.', [
                    'terms' => '<a href="/terms" target="_blank" style="text-decoration:underline">'.e(__('Terms of use')).'</a>',
                    'privacy' => '<a href="/privacy" target="_blank" style="text-decoration:underline">'.e(__('Privacy policy')).'</a>',
                ]))),
            // Honeypot (no label a person would fill; hidden from view, screen readers and autofill).
            TextInput::make('website')->label('Website')->hiddenLabel()->dehydrated(false)
                ->extraAttributes(['style' => 'position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden', 'aria-hidden' => 'true'])
                ->extraInputAttributes(['tabindex' => '-1', 'autocomplete' => 'off']),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/register.form.email.label'))
            ->email()->required()->maxLength(190)
            ->extraInputAttributes(['dir' => 'ltr'])
            ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                if (Signup::isDisposable((string) $value)) {
                    $fail(__('Temporary inbox addresses can\'t be used. Use your work or personal email.'));

                    return;
                }
                // One message for "already registered" and "this domain already has a trial", so the form
                // doesn't say which (no account disclosure, like the reset page).
                if (Signup::emailTaken((string) $value) || Signup::domainHasTrial((string) $value)) {
                    $fail(__('We can\'t start a new trial with this email. If you already have an account, sign in or reset your password.'));
                }
            });
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rule(Password::min(10))
            ->helperText(__('At least 10 characters'))
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()->extraInputAttributes(['dir' => 'ltr']);
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->open) {
            // D-53: the support@ mailto until /waitlist is live (config adsranger.waitlist_url), then the waitlist link.
            return $schema->components([
                Text::make(__('AdsRanger is in a private beta.')),
                Text::make(TrialNotice::contactHtml()),
            ]);
        }

        return $schema->components([
            RenderHook::make(PanelsRenderHook::AUTH_REGISTER_FORM_BEFORE),
            $this->getFormContentComponent(),
            // ADSR-031 part 2 (D-61): the trial line under the button.
            Text::make(__('14 days on Pro · up to 5 ad accounts and 3 team members · no card needed')),
            // #74: sign up with Google instead (same trial rules; only while signup is open and the keys are set).
            View::make('auth.google-button')->visible(fn (): bool => GoogleSignIn::enabled()),
            RenderHook::make(PanelsRenderHook::AUTH_REGISTER_FORM_AFTER),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->open ? __('Create account') : __('Sign-up is by invitation for now');
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->getTitle();
    }

    public function getRegisterFormAction(): Action
    {
        return parent::getRegisterFormAction()->label(__('Create account and start your trial'));
    }
}
