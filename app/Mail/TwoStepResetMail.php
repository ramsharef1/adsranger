<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

/** Security mail (EX-53, no tracking, no marketing): a super admin turned off this user's two-step verification. */
class TwoStepResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $lang, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your AdsRanger two-step verification was reset', [], $this->lang),
            replyTo: [new Address(config('adsranger.signup.support_email'))]);
    }

    public function content(): Content
    {
        $l = $this->lang;

        return new Content(view: 'mail.notice', with: [
            'ar' => $l === 'ar',
            'lines' => [__('Hello :name,', ['name' => $this->user->name], $l),
                __('An AdsRanger administrator turned off two-step verification on your account. Your recovery codes no longer work.', [], $l),
                __('Sign in with your password and set up two-step verification again in your profile.', [], $l)],
            'button' => __('Sign in', [], $l), 'url' => $this->url,
            'small' => [__('If you didn\'t ask for this, reply to this email or write to support right away.', [], $l)],
            'help' => new HtmlString(e(__('Questions? Write to', [], $l)).' <bdi dir="ltr">'.e(config('adsranger.signup.support_email')).'</bdi>'),
        ]);
    }
}
