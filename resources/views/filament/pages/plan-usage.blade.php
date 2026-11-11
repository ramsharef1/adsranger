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

            @if ($canUpgrade)
                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:4px">
                    <button type="button" wire:click="openUpgradeModal" style="min-height:44px;padding:0 18px;border-radius:10px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">
                        {{ __('Request Plan Upgrade') }}
                    </button>
                    @if ($pendingUpgrade)
                        <span style="font-size:13px;padding:6px 12px;border-radius:99px;background:rgba(20,184,166,.15);font-weight:600">
                            {{ __('Pending upgrade request: :plan', ['plan' => \App\Support\PlanGate::tierLabel($pendingUpgrade->target_plan)]) }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>

    @if ($showUpgradeModal && $canUpgrade)
        <x-filament::section>
            <x-slot name="heading">{{ __('Request Plan Upgrade') }}</x-slot>
            <x-slot name="description">{{ __('Select your target plan tier. Our team reviews and activates plan changes directly for your workspace.') }}</x-slot>
            <form wire:submit="submitUpgradeRequest" style="display:grid;gap:14px;max-width:520px">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">{{ __('Target plan tier') }}</label>
                    <select wire:model="targetPlan" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        @foreach (array_keys($byTier) as $tKey)
                            <option value="{{ $tKey }}">{{ \App\Support\PlanGate::tierLabel($tKey) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">{{ __('Optional note') }}</label>
                    <textarea wire:model="upgradeNote" rows="3" maxlength="500" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:14px"></textarea>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="submit" style="min-height:44px;padding:0 18px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">{{ __('Submit upgrade request') }}</button>
                    <button type="button" wire:click="closeUpgradeModal" style="min-height:44px;padding:0 16px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;cursor:pointer">{{ __('Cancel') }}</button>
                </div>
            </form>
        </x-filament::section>
    @endif

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

    @if (! empty($betaModules))
        <x-filament::section>
            <x-slot name="heading">{{ __('Available Beta Modules — Request Early Access') }}</x-slot>
            <x-slot name="description">{{ __('Beta modules stay hidden from your navigation until enabled for your workspace.') }}</x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                @foreach ($betaModules as $mKey => $meta)
                    <div style="border:1px solid rgba(148,163,184,.35);border-radius:12px;padding:14px;display:flex;flex-direction:column;justify-content:space-between;gap:10px">
                        <div>
                            <strong style="font-size:14px">{{ $meta['label'] }}</strong>
                            <p style="font-size:13px;opacity:.75;margin:6px 0 0;line-height:1.6">{{ $meta['description'] }}</p>
                        </div>
                        @if ($canUpgrade)
                            <div>
                                @if (in_array($mKey, $requestedModules, true))
                                    <span style="display:inline-block;padding:6px 12px;border-radius:8px;background:rgba(20,184,166,.15);font-size:12.5px;font-weight:600">{{ __('Early access requested') }}</span>
                                @else
                                    <button type="button" wire:click="requestModuleEarlyAccess('{{ $mKey }}')" style="min-height:44px;padding:0 14px;border-radius:8px;border:1px solid #0f766e;background:transparent;font-size:13px;font-weight:600;cursor:pointer">
                                        {{ __('Request Early Access') }}
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
