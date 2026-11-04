<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ $plan?->name ?? ucfirst($agency->plan) }} plan</x-slot>
        <x-slot name="description">
            Status: {{ ucfirst($agency->status) }}@if (! is_null($trialDays)) · {{ $trialDays }} day(s) left in your trial @endif
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
        <x-slot name="heading">Networks in your plan</x-slot>
        <p style="font-size:14px;line-height:1.8">{{ $networks->isEmpty() ? 'None yet — networks appear as they are switched on.' : $networks->implode(' · ') }}</p>
        <p style="font-size:13px;opacity:.7;margin-top:8px">Need a bigger plan or another network? Contact support@adsranger.com. Online upgrades come with billing.</p>
    </x-filament::section>
</x-filament-panels::page>
