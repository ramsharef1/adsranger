<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\User;
use App\Support\Trial;
use App\Support\TrialNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * D-61 trial lifecycle mails to the workspace owner (queued, AR/EN by the workspace language, EX-53 layout):
 * `ending` (3 days before the end), `ended` (read-only starts), `deletion` (7 days before the data is deleted).
 * Sent once each by App\Support\TrialLifecycle (timestamps on the agency).
 */
class TrialLifecycleMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const KINDS = ['ending', 'ended', 'deletion'];

    public function __construct(public Agency $agency, public User $owner, public string $kind, public string $url) {}

    protected function lang(): string
    {
        return $this->agency->language === 'en' ? 'en' : 'ar';
    }

    public function envelope(): Envelope
    {
        $l = $this->lang();
        $subject = match ($this->kind) {
            'ending' => trans_choice('{1} Your AdsRanger trial ends tomorrow|[2,*] Your AdsRanger trial ends in :count days', max(1, (int) config('trial.ending_notice_days', 3)), [], $l),
            'ended' => __('Your AdsRanger trial has ended: your workspace is read-only', [], $l),
            default => __('Your AdsRanger workspace data will be deleted on :date', ['date' => TrialNotice::date(Trial::deleteOn($this->agency), $l)], $l),
        };

        return new Envelope(subject: $subject, replyTo: [new Address(config('adsranger.signup.support_email'))]);
    }

    public function content(): Content
    {
        $l = $this->lang();
        $a = $this->agency;
        $d = fn ($date) => TrialNotice::date($date, $l);
        $p = ['agency' => $a->name, 'end' => $d($a->trial_ends_at), 'until' => $d(Trial::readOnlyUntil($a)), 'delete' => $d(Trial::deleteOn($a))];
        $hello = __('Hello :name,', ['name' => $this->owner->name], $l);

        [$lines, $button] = match ($this->kind) {
            'ending' => [[$hello,
                __('Your Pro trial for :agency ends on :end.', $p, $l),
                __('After that the workspace is read-only until :until: you can still see everything and download your data, but you can\'t change anything, and syncs and alerts stop.', $p, $l),
                __('Choose a plan to keep working without a break.', [], $l)], __('See plans', [], $l)],
            'ended' => [[$hello,
                __('Your Pro trial for :agency ended on :end. The workspace is now read-only until :until.', $p, $l),
                __('You can view and download your data. Changes, syncs and alerts are paused.', [], $l),
                __('After :until the workspace closes. We keep its data until :delete, then delete it.', $p, $l)], __('See plans', [], $l)],
            default => [[$hello,
                __('We will delete the data of :agency on :delete: clients, ad accounts, results, alerts and reports.', $p, $l),
                __('You can download all workspace data until then.', [], $l)], __('Download all workspace data', [], $l)],
        };

        return new Content(view: 'mail.notice', with: [
            'ar' => $l === 'ar', 'lines' => $lines, 'button' => $button, 'url' => $this->url,
            'small' => [__('You get this email because you own the :agency workspace.', $p, $l)],
            'help' => TrialNotice::helpHtml($l),
        ]);
    }
}
