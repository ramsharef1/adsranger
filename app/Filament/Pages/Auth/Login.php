<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;

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
}
