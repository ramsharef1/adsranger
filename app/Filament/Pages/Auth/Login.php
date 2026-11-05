<?php

namespace App\Filament\Pages\Auth;

use App\Support\GoogleSignIn;
use App\Support\Signup;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/** Agency app sign-in: email and password type left to right in both languages (UX-45); labels stay RTL. */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->extraInputAttributes(['dir' => 'ltr']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->extraInputAttributes(['dir' => 'ltr']);
    }

    /** The "sign up" link shows only while signup is open (D-41: no public signup CTA while invite-only). */
    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication) || Signup::isOpen()) {
            return parent::getSubheading();
        }

        return null;
    }

    /** #74: "Continue with Google" under the form on /app/login (not during the code step, never on /admin). */
    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            ...$schema->getComponents(withHidden: true),
            View::make('auth.google-button')->visible(fn (): bool => $this->showsGoogle() && blank($this->userUndertakingMultiFactorAuthentication)),
        ]);
    }

    protected function showsGoogle(): bool
    {
        return GoogleSignIn::enabled();
    }
}
