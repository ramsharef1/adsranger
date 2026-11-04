<?php

namespace App\Mail;

use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Immediate email for new high-severity alerts (opt-in per user). */
class AlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Agency $agency, public array $alerts) {}

    public function envelope(): Envelope
    {
        $n = count($this->alerts);

        return new Envelope(subject: ($this->agency->language === 'ar' ? "تنبيه عاجل ($n) · " : "$n high alert".($n > 1 ? 's' : '').' · ').$this->agency->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.alerts', with: ['agency' => $this->agency, 'alerts' => $this->alerts, 'digest' => null]);
    }
}
