<?php

namespace App\Notifications;

use Filament\Auth\Notifications\VerifyEmail as FilamentVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * #158 "Confirm your email" mail (EX-53): queued, in the language of the page the person signed up from,
 * RTL for Arabic, one button, the plain link as a fallback, no tracking pixel, no marketing.
 * Bound in place of Filament's VerifyEmail, so the signup page and the "resend" link both send this one.
 */
class VerifyEmail extends FilamentVerifyEmail
{
    public function __construct()
    {
        $this->locale = app()->getLocale() === 'en' ? 'en' : 'ar';
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $ar = $this->locale !== 'en';

        return (new MailMessage)
            ->subject($ar ? 'أكّد بريدك الإلكتروني لبدء استخدام أدز رينجر' : 'Confirm your email to start using AdsRanger')
            ->replyTo(config('adsranger.signup.support_email'))
            ->view('mail.verify-email', ['url' => $url, 'ar' => $ar, 'name' => $notifiable->name]);
    }
}
