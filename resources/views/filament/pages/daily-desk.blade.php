@php
    use App\Support\ResultResolver as R;
    use App\Filament\Resources\Clients\ClientResource;
    $muted = 'opacity:.65;font-size:12px';
    $pill = fn ($on) => 'padding:5px 12px;border-radius:99px;font-size:13px;cursor:pointer;border:1px solid '.($on ? '#14B8A6' : 'rgba(148,163,184,.35)').';background:'.($on ? 'rgba(20,184,166,.12)' : 'transparent');
@endphp
<x-filament-panels::page>
    @include('filament.partials.onboarding-checklist')
    @if ($valueCard && $valueCard['hours'] > 0)
        <x-value-card :card="$valueCard" />
    @endif
    @if ($approvals->isNotEmpty())
        {{-- DS-35 (D-305): risky actions waiting for a second owner or admin. --}}
        <x-filament::section :heading="__('Waiting for your approval')" data-approvals>
            <div style="display:grid;gap:8px">
                @foreach ($approvals as $ap)
                    <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;font-size:14px;border-bottom:1px solid rgba(148,163,184,.2);padding-bottom:6px" wire:key="ap-{{ $ap->id }}">
                        <span><strong>{{ \App\Support\TwoPerson::actionLabels()[$ap->action] ?? $ap->action }}</strong>@if ($ap->subject_name) · <bdi>{{ $ap->subject_name }}</bdi>@endif
                            · {{ __('asked by :name', ['name' => $ap->requester?->name ?? '—']) }} · {{ __('expires :when', ['when' => $ap->expires_at->locale(app()->getLocale())->diffForHumans()]) }}</span>
                        <span style="display:flex;gap:6px">
                            <x-filament::button size="xs" color="danger" wire:click="decideApproval({{ $ap->id }}, true)" wire:confirm="{{ __('Approve this? It cannot be undone once they go ahead.') }}">{{ __('Approve') }}</x-filament::button>
                            <x-filament::button size="xs" color="gray" wire:click="decideApproval({{ $ap->id }}, false)">{{ __('Decline') }}</x-filament::button>
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
    @if ($licences->isNotEmpty())
        {{-- XS-24 / NB-36 (D-305): licences expiring within 30 days. --}}
        <x-filament::section :heading="__('Licences to renew')" data-licences>
            <ul style="margin:0;padding-inline-start:18px;display:grid;gap:4px;font-size:14px">
                @foreach ($licences as $l)
                    <li><a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('view', ['record' => $l->client_id]) }}" style="color:#0F766E;text-decoration:underline"><bdi>{{ $l->client?->name }}</bdi></a>
                        · {{ \App\Models\ClientLicence::kindLabels()[$l->kind] ?? $l->kind }} <bdi dir="ltr">{{ $l->number }}</bdi>
                        · {{ $l->expires_on->isPast() ? __('expired on :date', ['date' => $l->expires_on->locale(app()->getLocale())->translatedFormat('j M Y')]) : __('expires on :date', ['date' => $l->expires_on->locale(app()->getLocale())->translatedFormat('j M Y')]) }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
    @if ($weekList)
        {{-- XS-01 (R4): the clients that need a touch this week, with one line of reason each. --}}
        <x-filament::section :heading="__('This week\'s clients')" data-week-list>
            <x-slot name="description">{{ __('Ranked: open Critical alerts first, then warnings, reports going out soon and clients nobody has talked to in 14 days.') }}</x-slot>
            <ol style="margin:0;padding-inline-start:20px;display:grid;gap:6px;font-size:14px">
                @foreach ($weekList as $w)
                    <li><a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('view', ['record' => $w['client']->id]) }}" style="color:#0F766E;text-decoration:underline;font-weight:600"><bdi>{{ $w['client']->name }}</bdi></a>
                        <span style="opacity:.8"> · {{ implode(' · ', $w['reasons']) }}</span></li>
                @endforeach
            </ol>
        </x-filament::section>
    @endif
    @if ($followUps->isNotEmpty())
        {{-- XS-02 (R4, D-303): contacts due today or earlier. --}}
        <x-filament::section :heading="__('Follow-ups due')" data-follow-ups>
            <div style="display:grid;gap:6px">
                @foreach ($followUps as $f)
                    <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;font-size:14px;border-bottom:1px solid rgba(148,163,184,.2);padding-bottom:6px" wire:key="fu-{{ $f->id }}">
                        <span><a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('view', ['record' => $f->client_id]) }}" style="color:#0F766E;text-decoration:underline"><bdi>{{ $f->client?->name }}</bdi></a>
                            · {{ \App\Models\ClientContact::kindLabels()[$f->kind] ?? '' }} · <bdi>{{ $f->next_on->locale(app()->getLocale())->translatedFormat('j M') }}</bdi>
                            @if ($f->note)<span style="opacity:.75"> · {{ \Illuminate\Support\Str::limit($f->note, 80) }}</span>@endif</span>
                        <x-filament::button size="xs" color="gray" wire:click="followUpDone({{ $f->id }})">{{ __('Done') }}</x-filament::button>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
    @if ($brainsToCheck->isNotEmpty())
        {{-- WS-34 (D-301): monthly check of each client brain by a person. --}}
        <x-filament::section :heading="__('Client brains to check')" data-brains-to-check>
            <x-slot name="description">{{ __('Nobody has checked these in 30 days. Old rules make your AI write the wrong thing.') }}</x-slot>
            <ul style="margin:0;padding-inline-start:18px;display:grid;gap:4px;font-size:13.5px">
                @foreach ($brainsToCheck as $bc)
                    <li><a href="{{ \App\Filament\Pages\ClientBrainPage::getUrl() }}?client={{ $bc->id }}" style="color:#0F766E;text-decoration:underline"><bdi>{{ $bc->name }}</bdi></a></li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
    @if ($night->isNotEmpty())
        {{-- EG-26 / FS-08: overnight AI notes on the agency's own key; read-only suggestions. --}}
        <x-filament::section :heading="__('Night shift notes')" data-night-notes>
            <x-slot name="description">{{ __('Written overnight by your own AI from your data. Suggestions only; nothing was changed.') }}</x-slot>
            <div style="display:grid;gap:10px">
                @foreach ($night as $n)
                    <div style="display:grid;gap:4px;padding-bottom:8px;border-bottom:1px solid rgba(148,163,184,.2)" wire:key="nn-{{ $n->id }}">
                        <div style="font-size:12px;opacity:.7"><bdi>{{ $clientNames[$n->client_id] ?? '' }}</bdi> · {{ ['critical' => __('Critical'), 'warning' => __('Warning')][$n->meta['level'] ?? ''] ?? __('Info') }}</div>
                        <strong><bdi>{{ $n->title }}</bdi></strong>
                        <div style="font-size:13px;white-space:pre-line" dir="auto">{{ $n->clean ?? $n->original }}</div>
                        {{-- NM-04 (R7 end): show me why: where each number came from. --}}
                        @if (! empty($n->meta['sources']))
                            <details data-night-why style="font-size:12.5px">
                                <summary style="cursor:pointer;min-height:32px">{{ __('Show me why') }}</summary>
                                <ul style="margin:4px 0 0;padding-inline-start:18px">
                                    @foreach ($n->meta['sources'] as $src)
                                        <li><bdi dir="ltr">{{ $src['raw'] }}</bdi> ← {{ __($src['label']) }} · <bdi dir="ltr">{{ $src['period'] }}</bdi> · {{ $src['source'] }}@if ($src['date']) · <bdi dir="ltr">{{ $src['date'] }}</bdi>@endif</li>
                                    @endforeach
                                </ul>
                                @if (! empty($n->meta['campaign']))<div>{{ __('Campaign') }}: <bdi>{{ $n->meta['campaign'] }}</bdi></div>@endif
                            </details>
                        @endif
                        <div style="display:flex;gap:8px">
                            <x-filament::button size="xs" color="gray" wire:click="nightFeedback({{ $n->id }}, true)">{{ __('Useful') }}</x-filament::button>
                            <x-filament::button size="xs" color="gray" wire:click="nightFeedback({{ $n->id }}, false)">{{ __('Not useful') }}</x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
            <button type="button" wire:click="setDay('yesterday')" style="{{ $pill($day === 'yesterday') }}">{{ __('Yesterday') }}</button>
            <button type="button" wire:click="setDay('today')" style="{{ $pill($day === 'today') }}">{{ __('Today (live)') }}</button>
            <input type="date" wire:model.live="date" wire:change="setDay('custom')" max="{{ now($tz)->toDateString() }}"
                   style="padding:4px 8px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshToday">{{ __('Refresh today') }}</x-filament::button>
        </div>
        <div style="{{ $muted }}">{{ $from }} · {{ $tz }} · @if ($synced) {{ __('data updated :when', ['when' => $synced->diffForHumans()]) }} @else {{ __('no sync yet') }} @endif</div>
    </div>

    <x-filament::section>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}">
                    <th style="padding:6px;text-align:start">{{ __('Client') }}</th><th style="padding:6px;text-align:end">{{ __('Spend') }}</th>
                    <th style="padding:6px;text-align:start">{{ __('Main result') }}</th><th style="padding:6px;text-align:end">{{ __('Cost / result') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('Leads') }}</th><th style="padding:6px;text-align:end">{{ __('Messages') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('Calls') }}</th><th style="padding:6px;text-align:end">{{ __('Purchases') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('ROAS') }}</th><th style="padding:6px;text-align:start">{{ __('Alerts') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    @php $t = $r['t']; $c = $r['client']; @endphp
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px"><a href="{{ ClientResource::getUrl('view', ['record' => $c]) }}" style="color:#14B8A6;font-weight:600">{{ $c->name }}</a></td>
                        <td style="padding:6px;text-align:end">{{ R::money($t['spend'], $c->currency) }}</td>
                        <td style="padding:6px">{{ number_format($t['main_results']) }} <span style="{{ $muted }}">{{ R::label($t['main_type'], app()->getLocale()) }}</span></td>
                        <td style="padding:6px;text-align:end">{{ R::money($t['cpr']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['leads']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['messages']) }}</td>
                        <td style="padding:6px;text-align:end" title="{{ __('placed / confirmed / 20s+') }}">{{ number_format($t['calls']) }}@if ($t['calls_confirmed'] || $t['calls_20s'])<span style="{{ $muted }}"> · {{ number_format($t['calls_confirmed']) }} · {{ number_format($t['calls_20s']) }}</span>@endif</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['purchases']) }}</td>
                        <td style="padding:6px;text-align:end">{{ $t['roas'] ? number_format($t['roas'], 2).'×' : '–' }}</td>
                        <td style="padding:6px">
                            @foreach ($r['alerts'] as $al)
                                <x-filament::badge :color="\App\Support\Severity::color($al->severity)" :icon="\App\Support\Severity::icon($al->severity)" style="display:inline-flex">{{ $al->n }} {{ \App\Support\Severity::label($al->severity) }}</x-filament::badge>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="padding:12px;{{ $muted }}">{{ __('No active clients yet.') }} <a href="{{ ClientResource::getUrl('create') }}" style="color:#14B8A6">{{ __('Add a client') }}</a> {{ __('and link its ad accounts.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
