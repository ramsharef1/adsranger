@php
    $line = 'padding:6px 0;border-top:1px solid rgba(127,127,127,.2)';
    $citedLabel = ['yes' => __('Cited us'), 'no' => __('Not cited'), 'tool' => __('Cited a free tool'), 'page' => __('Cited a page')];
    $outcomeLabel = ['open' => __('Open'), 'won' => __('Won'), 'lost' => __('Lost')];
@endphp
<x-filament-panels::page>
    <x-filament::section :heading="__('Where launch-list signups came from')">
        <x-slot name="description">{{ __(':n signups, :m with a known first page.', ['n' => $total, 'm' => $withOrigin]) }}</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
            @foreach ([__('First page') => $landings, __('Free tool used') => $tools, __('Source (utm)') => $sources, __('Campaign (utm)') => $campaigns] as $label => $list)
                <div>
                    <div style="font-weight:600;margin-bottom:4px">{{ $label }}</div>
                    @forelse ($list as $r)
                        <div style="font-size:13px"><bdi dir="ltr">{{ \Illuminate\Support\Str::limit($r->v, 50) }}</bdi> · {{ $r->n }}</div>
                    @empty
                        <div style="font-size:13px;opacity:.75">—</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('Shared by people on the list')">
        <x-slot name="description">{{ __(':n signups came from a shared link.', ['n' => $referred]) }}</x-slot>
        @forelse ($referrers as $r)
            <div style="font-size:13px"><bdi dir="ltr">{{ $r->owner }}</bdi> · {{ $r->n }}</div>
        @empty
            <p style="opacity:.75">{{ __('None yet.') }}</p>
        @endforelse
    </x-filament::section>

    <x-filament::section :heading="__('AI assistant answers (monthly check)')">
        <x-slot name="description">{{ __('Ask an AI assistant a buyer question by hand and note whether it cites us.') }}</x-slot>
        <form wire:submit="addCheck" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <input wire:model="question" class="fi-input" style="flex:1;min-width:240px" placeholder="{{ __('Question asked') }}">
            <input wire:model="assistant" class="fi-input" style="width:140px" placeholder="{{ __('Assistant') }}">
            <select wire:model="cited" class="fi-input">@foreach ($citedLabel as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <input wire:model="checkNote" class="fi-input" style="min-width:180px" placeholder="{{ __('Note') }}">
            <x-filament::button type="submit" size="sm">{{ __('Add') }}</x-filament::button>
        </form>
        @foreach ($checks as $c)
            <div style="{{ $line }};font-size:13px"><bdi dir="ltr">{{ $c->checked_on }}</bdi> · <bdi>{{ $c->assistant }}</bdi> · <strong>{{ $citedLabel[$c->cited] ?? $c->cited }}</strong> · <bdi>{{ $c->question }}</bdi>@if ($c->note) · <span style="opacity:.75"><bdi>{{ $c->note }}</bdi></span>@endif</div>
        @endforeach
    </x-filament::section>

    <x-filament::section :heading="__('Wins and losses')">
        <x-slot name="description">{{ __('One line per agency we talked to, with the reason in their words.') }}</x-slot>
        <form wire:submit="addProspect" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <input wire:model="prospect" class="fi-input" style="min-width:200px" placeholder="{{ __('Agency') }}">
            <input wire:model="country" class="fi-input" style="width:70px" maxlength="2" placeholder="SA">
            <select wire:model="outcome" class="fi-input">@foreach ($outcomeLabel as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <input wire:model="reason" class="fi-input" style="flex:1;min-width:220px" placeholder="{{ __('Reason') }}">
            <x-filament::button type="submit" size="sm">{{ __('Add') }}</x-filament::button>
        </form>
        @if ($lossReasons->isNotEmpty())
            <div style="margin-bottom:10px;font-size:13px"><strong>{{ __('Top loss reasons') }}:</strong> @foreach ($lossReasons as $l)<bdi>{{ $l->reason }}</bdi> ({{ $l->n }})@if (! $loop->last) · @endif @endforeach</div>
        @endif
        @foreach ($prospects as $p)
            <div style="{{ $line }};font-size:13px"><bdi dir="ltr">{{ $p->logged_on }}</bdi> · <bdi>{{ $p->name }}</bdi>@if ($p->country) · <bdi dir="ltr">{{ $p->country }}</bdi>@endif · <strong>{{ $outcomeLabel[$p->outcome] ?? $p->outcome }}</strong>@if ($p->reason) · <bdi>{{ $p->reason }}</bdi>@endif</div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
