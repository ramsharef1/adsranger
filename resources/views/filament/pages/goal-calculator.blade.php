@php
    use App\Support\GoalCalculator as G;
    $muted = 'opacity:.65;font-size:12px';
    $field = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $cur = $client?->currency;
    $n = fn ($v, $d = 0) => number_format((float) $v, $d);
    $tag = fn (bool $own) => $own ? __('your last 90 days') : __('starting assumption');
@endphp
<x-filament-panels::page>
    <div style="{{ $muted }}">{{ __('Start from the result you want and work back to the clicks, impressions and budget it takes.') }}</div>

    @if (! $client)
        <x-filament::section><div style="{{ $muted }}">{{ __('No clients yet. Add a client first, then plan its goals.') }}</div></x-filament::section>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:16px;align-items:start">
            <x-filament::section>
                <x-slot name="heading">{{ __('Your goal') }}</x-slot>
                <div style="display:grid;gap:12px">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
                        <div>
                            <label for="gc-client" style="{{ $label }}">{{ __('Client') }}</label>
                            <select id="gc-client" wire:model.live="clientId" style="{{ $field }};width:100%">
                                @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="gc-goal" style="{{ $label }}">{{ __('Goal') }}</label>
                            <select id="gc-goal" wire:model.live="goal" style="{{ $field }};width:100%">
                                <option value="purchases">{{ __('Number of sales') }}</option>
                                <option value="revenue">{{ __('Revenue') }}</option>
                                <option value="leads">{{ __('Number of leads') }}</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
                        <div>
                            <label for="gc-target" style="{{ $label }}">{{ $goal === 'revenue' ? __('Target revenue (:currency)', ['currency' => $cur]) : __('Target') }}</label>
                            <input id="gc-target" type="text" inputmode="decimal" wire:model.live.debounce.400ms="target" placeholder="{{ $goal === 'revenue' ? '20000' : '100' }}" dir="ltr" style="{{ $field }};width:100%">
                        </div>
                        <div>
                            <label for="gc-days" style="{{ $label }}">{{ __('Over how many days') }}</label>
                            <input id="gc-days" type="number" min="1" max="366" wire:model.live.debounce.400ms="days" dir="ltr" style="{{ $field }};width:100%">
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Rates used') }}</x-slot>
                <x-slot name="afterHeader"><x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="resetInputs" style="min-height:44px">{{ __('Reset to client data') }}</x-filament::button></x-slot>
                <div style="display:grid;gap:10px">
                    @foreach (G::steps($goal) as $i => $k)
                        @continue($i === 0)
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                            <label for="gc-rate-{{ $k }}" style="flex:1;min-width:160px;font-size:13px">{{ __(':step from :prev', ['step' => __(G::LABELS[$k]), 'prev' => __(G::LABELS[G::steps($goal)[$i - 1]])]) }}
                                <span style="{{ $muted }};display:block">{{ $tag($fromHistory[$k] ?? false) }}</span></label>
                            <span style="display:inline-flex;align-items:center;gap:4px" dir="ltr"><input id="gc-rate-{{ $k }}" type="text" inputmode="decimal" wire:model.live.debounce.400ms="rates.{{ $k }}" style="{{ $field }};width:90px;text-align:end">%</span>
                        </div>
                    @endforeach
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <label for="gc-cpm" style="flex:1;min-width:160px;font-size:13px">{{ __('Cost per 1,000 impressions (:currency)', ['currency' => $cur]) }}
                            <span style="{{ $muted }};display:block">{{ ($fromHistory['cpm'] ?? false) ? __('your last 90 days') : __('enter your expected CPM') }}</span></label>
                        <input id="gc-cpm" type="text" inputmode="decimal" wire:model.live.debounce.400ms="cpm" dir="ltr" style="{{ $field }};width:110px;text-align:end">
                    </div>
                    @if ($goal === 'revenue')
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                            <label for="gc-aov" style="flex:1;min-width:160px;font-size:13px">{{ __('Average order value (:currency)', ['currency' => $cur]) }}
                                <span style="{{ $muted }};display:block">{{ ($fromHistory['aov'] ?? false) ? __('your last 90 days') : __('enter your average order value') }}</span></label>
                            <input id="gc-aov" type="text" inputmode="decimal" wire:model.live.debounce.400ms="aov" dir="ltr" style="{{ $field }};width:110px;text-align:end">
                        </div>
                    @endif
                    <div style="{{ $muted }}">{{ __('Leave a step empty to skip it (for example when landing page views aren\'t tracked).') }}</div>
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ __('What it takes') }}</x-slot>
            @if ($needsAov)
                <div style="{{ $muted }}">{{ __('Enter the average order value to turn revenue into a number of sales.') }}</div>
            @elseif (! $plan)
                <div style="{{ $muted }}">{{ __('Enter a target above to see the funnel you need.') }}</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Step') }}</th><th style="padding:6px;text-align:end">{{ __('Rate') }}</th><th style="padding:6px;text-align:end">{{ __('Needed') }}</th><th style="padding:6px;text-align:end">{{ __('Per day') }}</th></tr></thead>
                        <tbody>
                        @foreach ($plan['steps'] as $s)
                            <tr style="border-top:1px solid rgba(148,163,184,.2)">
                                <td style="padding:6px">{{ __($s['label']) }}</td>
                                <td style="padding:6px;text-align:end;font-variant-numeric:tabular-nums" dir="ltr">{{ $s['rate'] !== null ? $n($s['rate'], 2).'%' : '—' }}</td>
                                <td style="padding:6px;text-align:end;font-variant-numeric:tabular-nums;font-weight:600">{{ $n($s['needed']) }}</td>
                                <td style="padding:6px;text-align:end;font-variant-numeric:tabular-nums">{{ $n($s['needed'] / $days) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:24px;margin-top:14px">
                    <div><div style="{{ $muted }}">{{ __('Budget needed') }}</div>
                        <div style="font-size:20px;font-weight:700;font-variant-numeric:tabular-nums">@if ($plan['budget'] !== null){{ $n($plan['budget'], 2) }} <span style="font-size:13px">{{ $cur }}</span>@else — @endif</div>
                        @if ($needsCpm)<div style="{{ $muted }}">{{ __('Enter a CPM to see the budget.') }}</div>@endif</div>
                    @if ($plan['per_day'] !== null)
                        <div><div style="{{ $muted }}">{{ __('Per day') }}</div><div style="font-size:20px;font-weight:700;font-variant-numeric:tabular-nums">{{ $n($plan['per_day'], 2) }} <span style="font-size:13px">{{ $cur }}</span></div>
                            @if (! empty($history['spend_per_day']))<div style="{{ $muted }}">{{ __(':x× your average daily spend in the last 90 days', ['x' => $n($plan['per_day'] / $history['spend_per_day'], 1)]) }}</div>@endif</div>
                    @endif
                    @if ($atRecentCost !== null)
                        <div><div style="{{ $muted }}">{{ __('At your recent cost per result') }}</div><div style="font-size:20px;font-weight:700;font-variant-numeric:tabular-nums">{{ $n($atRecentCost, 2) }} <span style="font-size:13px">{{ $cur }}</span></div>
                            <div style="{{ $muted }}">{{ __('A second estimate from what each result cost you in the last 90 days.') }}</div></div>
                    @endif
                </div>
                <div style="{{ $muted }};margin-top:10px">{{ __('Estimates, not a promise: real rates move with the offer, audience, creative and season.') }}</div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
