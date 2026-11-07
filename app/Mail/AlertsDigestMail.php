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

    /** @var array{rows: array<int, array{client: Client, t: array}>, alerts: array<int, Alert>}|null */
    private ?array $data = null;

    /** UX-191: the subject starts with Arabic text (no direction flip), names the alert count and yesterday's spend. */
    public function envelope(): Envelope
    {
        $d = $this->data();
        $count = count($d['alerts']);
        $currencies = collect($d['rows'])->pluck('client.currency')->unique();
        $spend = $currencies->count() === 1
            ? number_format(collect($d['rows'])->sum(fn ($r) => (float) ($r['t']['spend'] ?? 0)), 0).' '.$currencies->first()
            : null;
        if ($this->agency->language === 'ar') {
            $alerts = match (true) {
                $count === 0 => 'لا تنبيهات',
                $count === 1 => 'تنبيه واحد',
                $count === 2 => 'تنبيهان',
                $count <= 10 => $count.' تنبيهات',
                default => $count.' تنبيهًا',
            };
            $subject = 'ملخّص الصباح في '.$this->agency->name.': '.$alerts.($spend ? '، وإنفاق الأمس '.$spend : '');
        } else {
            $subject = 'Morning digest for '.$this->agency->name.': '.$count.' '.($count === 1 ? 'alert' : 'alerts').($spend ? ', yesterday\'s spend '.$spend : '');
        }

        return new Envelope(subject: $subject);
    }

    /** @return array{rows: array<int, array{client: Client, t: array}>, alerts: array<int, Alert>} */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $clients = Client::visibleTo($this->user, $this->agency)->where('status', 'active')->orderBy('name')->get();
        $rows = $clients->map(fn (Client $c) => ['client' => $c, 't' => MetricsQuery::totals($c, $this->day, $this->day)])->all();
        $alerts = Alert::where('agency_id', $this->agency->id)->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('client_id')->orWhereIn('client_id', $clients->pluck('id')))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")->limit(30)->get()->all();

        return $this->data = ['rows' => $rows, 'alerts' => $alerts];
    }

    public function content(): Content
    {
        $d = $this->data();

        return new Content(view: 'mail.alerts', with: ['agency' => $this->agency, 'alerts' => $d['alerts'], 'digest' => ['day' => $this->day, 'rows' => $d['rows']]]);
    }
}
