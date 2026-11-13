<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadDeskLink;
use App\Support\LeadDesk;
use App\Support\Modules;
use Illuminate\Http\Request;

/** XS-13 / XS-23 (R4, D-306): the client's front desk logs leads on a private link, without logging in. */
class LeadDeskController extends Controller
{
    protected function link(string $token): LeadDeskLink
    {
        $link = LeadDeskLink::with('client.agency')->where('id', LeadDeskLink::findActive($token)?->id)->first();
        abort_unless($link && $link->client && $link->client->status !== 'archived', 404);
        abort_unless(Modules::on('lead_desk', null, $link->client->agency), 404);

        return $link;
    }

    public function show(string $token)
    {
        $link = $this->link($token);
        $link->forceFill(['last_used_at' => now()])->save();
        $c = $link->client;
        $tz = $c->agency?->timezone ?: 'UTC';
        $leads = Lead::where('client_id', $c->id)->where('received_at', '>=', now($tz)->subDays(2)->startOfDay()->setTimezone('UTC'))->latest('received_at')->limit(50)->get();
        $ar = ($c->reportSchedule?->lang ?? $c->agency?->language ?? 'ar') !== 'en';

        return response()->view('public.lead-desk', ['link' => $link, 'client' => $c, 'leads' => $leads, 'ar' => $ar, 'tz' => $tz, 'token' => $token])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, string $token)
    {
        $link = $this->link($token);
        $data = $request->validate(['channel' => ['required', 'in:'.implode(',', Lead::CHANNELS)], 'label' => ['nullable', 'string', 'max:60']]);
        LeadDesk::log($link->client, $data['channel'], $data['label'] ?? null, 'desk');

        return redirect('/l/'.$token);
    }

    public function update(Request $request, string $token, int $lead)
    {
        $link = $this->link($token);
        $data = $request->validate(['mark' => ['required', 'in:contacted,qualified,sale,not_relevant'], 'value' => ['nullable', 'numeric', 'min:0', 'max:100000000']]);
        $l = Lead::where('client_id', $link->client_id)->findOrFail($lead);
        LeadDesk::mark($l, $data['mark'], isset($data['value']) ? (float) $data['value'] : null);

        return redirect('/l/'.$token);
    }
}
