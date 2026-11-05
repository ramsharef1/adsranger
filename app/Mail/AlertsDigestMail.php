<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\User;
use App\Support\MetricsQuery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Morning digest at 08:00 workspace time: yesterday per client + open alerts. */
class AlertsDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Agency $agency, public User $user, public string $day) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->agency->language === 'ar' ? 'ملخص الصباح · ' : 'Morning digest · ').$this->agency->name.' · '.$this->day);
    }

    public function content(): Content
    {
        $clients = Client::visibleTo($this->user, $this->agency)->where('status', 'active')->orderBy('name')->get();
        $rows = $clients->map(fn (Client $c) => ['client' => $c, 't' => MetricsQuery::totals($c, $this->day, $this->day)])->all();
        $alerts = Alert::where('agency_id', $this->agency->id)->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('client_id')->orWhereIn('client_id', $clients->pluck('id')))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")->limit(30)->get()->all();

        return new Content(view: 'mail.alerts', with: ['agency' => $this->agency, 'alerts' => $alerts, 'digest' => ['day' => $this->day, 'rows' => $rows]]);
    }
}
