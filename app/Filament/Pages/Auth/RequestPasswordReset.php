<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\HtmlString;

/**
 * "Forgot password?" (#158, FR-001): the same message whether or not the email has an account,
 * and whether or not a link was sent a minute ago, so the form never reveals who is registered.
 * Copy and layout follow UX-45 (LTR email input) and UX-47 (glossary title, help line, back link).
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function getFailureNotification(string $status): ?Notification
    {
        if (in_array($status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return $this->getSentNotification(Password::RESET_LINK_SENT);
        }

        return parent::getFailureNotification($status);
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->extraInputAttributes(['dir' => 'ltr']);
    }

    public function getTitle(): string|Htmlable
    {
        return __('Reset password');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Reset password');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $help = e(__('We\'ll send you a reset link.'));

        return new HtmlString(filament()->hasLogin() ? $help.'<br>'.$this->loginAction->toHtml() : $help);
    }

    public function loginAction(): Action
    {
        return parent::loginAction()->label(__('Back to sign in'));
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()->label(__('Send reset link'));
    }
}
