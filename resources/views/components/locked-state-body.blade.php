@props(['feature', 'tier', 'canUpgrade' => false])
{{-- LockedState body (EX-64 kit, EX-50 points 3-4): name, what it does, which plan, an example sample (never real data), one action by role. --}}
@php
    use App\Support\PlanGate;
    $tierLabel = PlanGate::tierLabel($tier);
    $muted = 'opacity:.7;font-size:13px';
@endphp
    <div style="display:grid;gap:14px;max-width:640px">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <x-filament::icon icon="heroicon-o-lock-closed" style="width:20px;height:20px;opacity:.7" />
            <strong style="font-size:16px">{{ PlanGate::featureText($feature, 'name') }}</strong>
            <x-plan-badge :tier="$tier" />
        </div>
        <p style="font-size:14px;line-height:1.7;margin:0">{{ PlanGate::featureText($feature, 'what') }}</p>
        <p style="font-size:14px;font-weight:600;margin:0">{{ __('plan.available_on', ['tier' => $tierLabel]) }}</p>

        <div style="border:1px dashed rgba(148,163,184,.55);border-radius:12px;padding:14px;display:grid;gap:8px">
            <span style="{{ $muted }}">{{ __('plan.example') }} · {{ __('plan.example_note') }}</span>
            @foreach ([86, 64, 72, 40] as $w)
                <div aria-hidden="true" style="height:10px;border-radius:99px;width:{{ $w }}%;background:rgba(148,163,184,.25)"></div>
            @endforeach
        </div>

        @if ($canUpgrade)
            <div><x-filament::button tag="a" :href="\App\Filament\Pages\PlanUsage::getUrl()" icon="heroicon-o-arrow-up-circle">{{ __('plan.upgrade_to', ['tier' => $tierLabel]) }}</x-filament::button></div>
        @else
            <p style="{{ $muted }};margin:0">{{ __('plan.ask_owner') }}</p>
        @endif
    </div>
