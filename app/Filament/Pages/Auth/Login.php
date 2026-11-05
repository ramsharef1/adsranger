<?php

namespace App\Filament\Pages\Auth;

use App\Support\Signup;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
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
}
