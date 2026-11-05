<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __(':plan plan', ['plan' => \App\Support\PlanGate::tierLabel($agency->plan)]) }}</x-slot>
        <x-slot name="description">
            {{ __('Status: :status', ['status' => __(config('adsranger.agency_statuses')[$agency->status] ?? ucfirst($agency->status))]) }}@if (! is_null($trialDays)) · {{ trans_choice(':count day left in your trial|:count days left in your trial', $trialDays) }} @endif@if ($trialEnded) · {{ __('Trial ended') }} @endif
        </x-slot>
        <div style="display:grid;gap:14px">
            @foreach ($rows as [$label, $used, $limit])
                @php $pct = $limit > 0 ? min(100, round($used / $limit * 100)) : 0; @endphp
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:14px"><span>{{ $label }}</span><strong>{{ $used }} / {{ $limit }}</strong></div>
                    <div style="height:8px;border-radius:99px;background:rgba(148,163,184,.25);margin-top:6px;overflow:hidden">
                        <div style="height:8px;width:{{ $pct }}%;background:{{ $pct >= 100 ? '#ef4444' : '#14B8A6' }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
    <x-filament::section>
        <x-slot name="heading">{{ __('plan.unlocks') }}</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            @php $prev = null; @endphp
            @foreach ($byTier as $t => $features)
                <div style="border:1px solid {{ $t === $tier ? '#14B8A6' : 'rgba(148,163,184,.35)' }};border-radius:12px;padding:12px;display:grid;gap:6px;align-content:start">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:6px">
                        <strong>{{ \App\Support\PlanGate::tierLabel($t) }}</strong>
                        @if ($t === $tier)
                            <x-filament::badge color="success" style="display:inline-flex">{{ __('plan.current') }}</x-filament::badge>
                        @endif
                    </div>
                    @if (isset($prices[$t]))
                        <div style="font-size:13px;opacity:.75">{{ __('plan.per_month', ['price' => $prices[$t]]) }}</div>
                    @endif
                    @if ($prev === null)
                        <div style="font-size:13px;line-height:1.7">{{ __('plan.core') }}</div>
                    @else
                        <div style="font-size:13px;opacity:.75">{{ __('plan.included_below', ['tier' => \App\Support\PlanGate::tierLabel($prev)]) }}</div>
                    @endif
                    @if ($features)
                        <ul style="font-size:13px;line-height:1.7;margin:0;padding-inline-start:18px;list-style:disc">
                            @foreach ($features as $f)
                                <li>{{ \App\Support\PlanGate::featureText($f, 'name') }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @php $prev = $t; @endphp
            @endforeach
        </div>
        <p style="font-size:13px;opacity:.7;margin-top:8px">{{ __('plan.contact') }}</p>
    </x-filament::section>
    <x-filament::section>
        <x-slot name="heading">{{ __('Networks in your plan') }}</x-slot>
        <p style="font-size:14px;line-height:1.8">{{ $networks->isEmpty() ? __('None yet — networks appear as they are switched on.') : $networks->implode(' · ') }}</p>
        <p style="font-size:13px;opacity:.7;margin-top:8px">{{ __('Need a bigger plan or another network? Contact support@adsranger.com. Online upgrades come with billing.') }}</p>
    </x-filament::section>
</x-filament-panels::page>
