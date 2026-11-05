<?php

namespace App\Mail;

use App\Models\Alert;
use App\Models\User;
use App\Support\Severity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

/** #24: an alert was assigned to this member (sent to the assignee only, never to the person who assigned it). */
class AlertAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Alert $alert, public User $assignee, public string $by, public string $url) {}

    public function envelope(): Envelope
    {
        $l = $this->lang();

        return new Envelope(subject: __('Alert assigned to you · :client', ['client' => $this->clientName()], $l));
    }

    public function content(): Content
    {
        $l = $this->lang();
        $rule = __(config("alerts.rules.{$this->alert->rule_key}.label") ?? $this->alert->rule_key, [], $l);

        return new Content(view: 'mail.notice', with: [
            'ar' => $l === 'ar',
            'lines' => [__('Hello :name,', ['name' => $this->assignee->name], $l),
                __(':by assigned an alert to you.', ['by' => $this->by], $l),
                Severity::labelIn($this->alert->severity, $l).' · '.$rule.($this->alert->entity_name ? ' · '.$this->alert->entity_name : '')],
            'button' => __('Open the alert', [], $l), 'url' => $this->url,
            'small' => [],
            'help' => new HtmlString(e(__('You can resolve, snooze or reassign it from the alert page.', [], $l))),
        ]);
    }

    protected function lang(): string
    {
        return $this->alert->agency?->language === 'en' ? 'en' : 'ar';
    }

    protected function clientName(): string
    {
        return $this->alert->client?->name ?? (string) $this->alert->agency?->name;
    }
}
