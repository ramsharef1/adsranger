@php
    use App\Support\ResultResolver as R;
    $cur = $c->currency;
    $muted = 'opacity:.72;font-size:13px';
    $chg = function ($now, $prev) { if (! $prev) { return null; } return round(($now / $prev - 1) * 100); };
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
        <div><h2 style="margin:0;font-size:22px;font-weight:700"><bdi>{{ $c->name }}</bdi></h2><div style="{{ $muted }}"><bdi dir="ltr">{{ $from }} → {{ $to }}</bdi> · {{ __('vs the 30 days before') }}</div></div>
        <div style="display:flex;gap:8px;align-items:center">
            @if ($present)<span data-presentation-tag style="background:#0F2B29;color:#E9F3F1;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:600">{{ __('Presentation mode') }}</span>@endif
            <x-filament::button color="gray" size="sm" wire:click="$toggle('present')" style="min-height:44px">{{ $present ? __('Leave presentation mode') : __('Presentation mode') }}</x-filament::button>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        @if (! $c->hide_spend)
            <x-filament::section><div style="{{ $muted }}">{{ __('Spend') }}</div><strong style="font-size:22px">{{ R::money($t['spend'], $cur) }}</strong>@if (($p = $chg($t['spend'], $prev['spend'])) !== null)<div style="{{ $muted }}">{{ ($p > 0 ? '+' : '').$p }}%</div>@endif</x-filament::section>
        @endif
        <x-filament::section><div style="{{ $muted }}">{{ $result($t['main_type']) ?: __('Results') }}</div><strong style="font-size:22px">{{ number_format($t['main_results']) }}</strong>@if (($p = $chg($t['main_results'], $prev['main_results'])) !== null)<div style="{{ $muted }}">{{ ($p > 0 ? '+' : '').$p }}%</div>@endif</x-filament::section>
        @if (! $c->hide_spend && $t['cpr'] !== null)
            <x-filament::section><div style="{{ $muted }}">{{ __('Cost per result') }}</div><strong style="font-size:22px">{{ R::money($t['cpr'], $cur) }}</strong>@if ($c->target_cpr)<div style="{{ $muted }}">{{ __('target :v', ['v' => R::money($c->target_cpr, $cur)]) }}</div>@endif</x-filament::section>
        @endif
    </div>

    <x-filament::section><x-slot name="heading">{{ __('What worked') }}</x-slot>
        @forelse ($top as $r)
            <div style="padding:4px 0;font-size:14px"><bdi>{{ $r['name'] }}</bdi> · {{ number_format($r['main_results']) }} {{ $result($r['main_type']) }}@if (! $c->hide_spend && $r['cpr'] !== null) · {{ R::money($r['cpr'], $cur) }}@endif</div>
        @empty
            <p style="{{ $muted }};margin:0">{{ __('No campaign with results in this period.') }}</p>
        @endforelse
    </x-filament::section>

    @if ($alerts->isNotEmpty())
        <x-filament::section><x-slot name="heading">{{ __('Open issues') }}</x-slot>
            @foreach ($alerts as $al)<div style="padding:4px 0;font-size:14px">{{ \App\Support\Severity::label($al->severity) }} · {{ $al->localTitle() }}</div>@endforeach
        </x-filament::section>
    @endif

    @if ($season)
        <x-filament::section><x-slot name="heading">{{ __('Next season') }}</x-slot>
            <div style="font-size:14px">{{ app()->getLocale() === 'ar' ? $season['ar'] : $season['label'] }} · <bdi>{{ \Carbon\Carbon::parse($season['start'])->locale(app()->getLocale())->translatedFormat('j F') }}</bdi></div>
        </x-filament::section>
    @endif

    <x-filament::section><x-slot name="heading">{{ __('Decisions needed') }}</x-slot>
        @forelse ($decisions as $d)<div style="padding:4px 0;font-size:14px">• {{ $d }}</div>@empty<p style="{{ $muted }};margin:0">{{ __('Nothing waiting for a decision.') }}</p>@endforelse
    </x-filament::section>

    @if ($notes->isNotEmpty())
        <x-filament::section data-internal-notes><x-slot name="heading">{{ __('Internal notes (hidden in presentation mode)') }}</x-slot>
            @foreach ($notes as $n)<div style="padding:4px 0;font-size:13.5px;white-space:pre-line" dir="auto"><bdi>{{ $n->happened_on->toDateString() }}</bdi> · {{ $n->note }}</div>@endforeach
        </x-filament::section>
    @endif
</x-filament-panels::page>
