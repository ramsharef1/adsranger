<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt as BasePrompt;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/** "Check your email" (#158, 911 part 3 point 7): names the address in <bdi dir=ltr>; resend stays throttled. */
class EmailVerificationPrompt extends BasePrompt
{
    public function getTitle(): string|Htmlable
    {
        return __('Check your email');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Check your email');
    }

    public function content(Schema $schema): Schema
    {
        $email = e(filament()->auth()->user()->getEmailForVerification());

        return $schema->components([
            Text::make(new HtmlString(e(__('We sent a confirmation link to')).' <bdi dir="ltr">'.$email.'</bdi>. '.e(__('Open it to start your trial.')))),
            Text::make(new HtmlString(e(__('Didn\'t get it?')).' '.$this->resendNotificationAction->toHtml())),
        ]);
    }
}
