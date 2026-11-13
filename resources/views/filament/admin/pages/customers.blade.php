@php
    $cell = 'padding:6px;text-align:start;vertical-align:top';
    $tz = 'Asia/Amman';
    $d = fn ($c) => $c ? $c->timezone($tz)->format('Y-m-d') : '—';
@endphp
<x-filament-panels::page>
    <x-filament::section :heading="__('Customer health')">
        <x-slot name="description">{{ __('Workspaces with the most warning signs first. Signals for a talk, never a block.') }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $cell }}">{{ __('Workspace') }}</th><th style="{{ $cell }}">{{ __('plan.plan') }}</th>
                    <th style="{{ $cell }}">{{ __('Signed up') }}</th><th style="{{ $cell }}">{{ __('Last sign-in') }}</th>
                    <th style="{{ $cell }}">{{ __('Ad accounts') }}</th><th style="{{ $cell }}">{{ __('Last sync') }}</th>
                    <th style="{{ $cell }}">{{ __('Reports (30 days)') }}</th><th style="{{ $cell }}">{{ __('Signs') }}</th>
                </tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr style="border-top:1px solid rgba(127,127,127,.2)">
                            <td style="{{ $cell }}"><bdi>{{ $r['name'] }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $r['plan'] }}</bdi>@if ($r['trial_days'] !== null) · {{ __(':n days of trial left', ['n' => $r['trial_days']]) }}@endif</td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $d($r['created']) }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $d($r['last_login']) }}</bdi></td>
                            <td style="{{ $cell }}">{{ $r['accounts'] }}</td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $d($r['last_sync']) }}</bdi></td>
                            <td style="{{ $cell }}">{{ $r['reports30'] }}</td>
                            <td style="{{ $cell }};{{ $r['flags'] ? 'color:#dc2626' : 'color:#0d9488' }}">{!! $r['flags'] ? collect($r['flags'])->map(fn ($f) => e($f))->implode('<br>') : e(__('OK')) !!}</td>
                        </tr>
                    @empty
                        <tr><td style="{{ $cell }}" colspan="8">{{ __('No workspaces yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('From sign-up to first report')">
        <div style="display:flex;gap:18px;flex-wrap:wrap">
            @foreach ($funnel as $f)
                <div><div style="font-size:13px;opacity:.75">{{ $f['step'] }}</div><div style="font-size:20px;font-weight:600">{{ $f['reached'] }}</div>
                    <div style="font-size:12px;opacity:.75">{{ $f['median_hours'] !== null ? __('median :n hours', ['n' => $f['median_hours']]) : '—' }}</div></div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('Trial extension requests')">
        @forelse ($requests as $q)
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:6px 0;border-top:1px solid rgba(127,127,127,.2)">
                <strong><bdi>{{ $q->agency_name }}</bdi></strong>
                <span style="opacity:.75"><bdi>{{ $q->reason }}</bdi></span>
                <input type="number" min="1" max="30" wire:model="grantDays.{{ $q->id }}" placeholder="7" style="width:70px" class="fi-input">
                <x-filament::button size="sm" wire:click="grant({{ $q->id }})">{{ __('Grant') }}</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="decline({{ $q->id }})">{{ __('Decline') }}</x-filament::button>
            </div>
        @empty
            <p style="opacity:.75">{{ __('No open requests.') }}</p>
        @endforelse
    </x-filament::section>

    <x-filament::section :heading="__('Upgrade moments')">
        <x-slot name="description">{{ __('How often a workspace hit a plan limit or a locked feature.') }}</x-slot>
        @forelse ($moments as $m)
            <div><bdi dir="ltr">{{ $m->feature }}</bdi> · {{ $m->kind === 'limit' ? __('limit') : __('locked') }} · {{ __(':n times', ['n' => $m->hits]) }} · {{ __(':n workspaces', ['n' => $m->workspaces]) }}</div>
        @empty
            <p style="opacity:.75">{{ __('None yet.') }}</p>
        @endforelse
    </x-filament::section>

    <x-filament::section :heading="__('Notes')">
        <form wire:submit="addNote" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;margin-bottom:12px">
            <select wire:model="noteAgency" class="fi-input" style="min-width:180px">
                <option value="">{{ __('Workspace') }}</option>
                @foreach ($agencies as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <textarea wire:model="noteBody" rows="2" style="min-width:260px;flex:1" class="fi-input" placeholder="{{ __('Note') }}"></textarea>
            <label style="font-size:13px">{{ __('Next check') }} <input type="date" wire:model="noteNext" class="fi-input"></label>
            <x-filament::button type="submit" size="sm">{{ __('Add note') }}</x-filament::button>
        </form>
        @foreach ($notes as $n)
            <div style="padding:6px 0;border-top:1px solid rgba(127,127,127,.2)">
                <strong><bdi>{{ $n->agency_name }}</bdi></strong>
                @if ($n->next_check_on)<span style="font-size:12px;{{ $n->next_check_on <= now()->toDateString() ? 'color:#dc2626;font-weight:600' : 'opacity:.75' }}"> · {{ __('Next check') }} <bdi dir="ltr">{{ $n->next_check_on }}</bdi></span>@endif
                <div style="white-space:pre-line"><bdi>{{ $n->body }}</bdi></div>
            </div>
        @endforeach
    </x-filament::section>

    <x-filament::section :heading="__('Usage ledger')">
        <x-slot name="description"><bdi dir="ltr">{{ $ledgerMonth }}</bdi> · {{ __('Saved on the 1st of each month.') }}</x-slot>
        @if ($ledger->isEmpty())
            <p style="opacity:.75">{{ __('Not saved yet for this month.') }}</p>
        @else
            <div style="overflow-x:auto"><table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr>@foreach ([__('Workspace'), __('Seats'), __('Clients'), __('Ad accounts'), __('Reports sent'), __('Link views'), __('AI saves'), __('Night runs')] as $h)<th style="{{ $cell }}">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>@foreach ($ledger as $l)<tr style="border-top:1px solid rgba(127,127,127,.2)"><td style="{{ $cell }}"><bdi>{{ $l->agency_name }}</bdi></td>
                    @foreach (['seats', 'clients', 'ad_accounts', 'reports_sent', 'link_views', 'ai_saves', 'night_runs'] as $k)<td style="{{ $cell }}">{{ $l->$k }}</td>@endforeach</tr>@endforeach</tbody>
            </table></div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('Plan matrix')" collapsible collapsed>
        <x-slot name="description">{{ __('Limits from Plans, features from the plan map. A change there shows here.') }}</x-slot>
        <div style="overflow-x:auto"><table style="width:100%;font-size:13px;border-collapse:collapse">
            <thead><tr><th style="{{ $cell }}"></th>@foreach ($matrix['tiers'] as $t)<th style="{{ $cell }}">{{ \App\Support\PlanGate::tierShort($t) }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach (['seats' => __('Seats'), 'clients' => __('Clients'), 'ad_accounts' => __('Ad accounts')] as $k => $label)
                    <tr style="border-top:1px solid rgba(127,127,127,.2)"><td style="{{ $cell }};font-weight:600">{{ $label }}</td>@foreach ($matrix['tiers'] as $t)<td style="{{ $cell }}">{{ $matrix['limits'][$k][$t] ?? '—' }}</td>@endforeach</tr>
                @endforeach
                @foreach ($matrix['features'] as $f)
                    <tr style="border-top:1px solid rgba(127,127,127,.2)"><td style="{{ $cell }}">{{ $f['label'] }}</td>@foreach ($matrix['tiers'] as $t)<td style="{{ $cell }};{{ $f['tiers'][$t] ? 'color:#0d9488' : 'opacity:.4' }}">{{ $f['tiers'][$t] ? '✓' : '—' }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table></div>
    </x-filament::section>

    <x-filament::section :heading="__('Consent registry')">
        <x-slot name="description">{{ __('Which version of each consent text people agreed to, and how many.') }}</x-slot>
        @foreach ($consents as $c)
            <div>{{ $c['where'] }} · <bdi dir="ltr">{{ $c['version'] }}</bdi> · {{ $c['count'] }}</div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
