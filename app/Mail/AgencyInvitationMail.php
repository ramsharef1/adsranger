<?php

namespace App\Mail;

use App\Models\AgencyInvitation;
use App\Support\TeamRoles;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AgencyInvitationMail extends Mailable
{
    public function __construct(public AgencyInvitation $invite, public string $url) {}

    public function envelope(): Envelope
    {
        $ar = $this->invite->agency->language === 'ar';

        return new Envelope(subject: $ar
            ? 'دعوة للانضمام إلى '.$this->invite->agency->name.' على أدز رينجر'
            : 'You are invited to join '.$this->invite->agency->name.' on AdsRanger');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitation', with: [
            'agency' => $this->invite->agency,
            'inviter' => $this->invite->inviter,
            'roleLabel' => TeamRoles::label($this->invite->role),
            'url' => $this->url,
            'expires' => $this->invite->expires_at,
            'ar' => $this->invite->agency->language === 'ar',
        ]);
    }
}
