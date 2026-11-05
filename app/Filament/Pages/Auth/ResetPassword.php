<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Schemas\Components\Component;

/** "Choose a new password" (#158): email and password inputs are LTR in both languages (UX-45). */
class ResetPassword extends BaseResetPassword
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->extraInputAttributes(['dir' => 'ltr']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->extraInputAttributes(['dir' => 'ltr']);
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()->extraInputAttributes(['dir' => 'ltr']);
    }
}
