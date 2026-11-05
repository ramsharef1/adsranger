@php
    use App\Support\ResultResolver as R;
    use App\Support\GoogleInsights as G;
    $muted = 'opacity:.65;font-size:12px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px';
    $field = 'padding:5px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $pill = fn ($on) => 'padding:5px 12px;border-radius:99px;font-size:13px;cursor:pointer;border:1px solid '.($on ? '#14B8A6' : 'rgba(148,163,184,.35)').';background:'.($on ? 'rgba(20,184,166,.12)' : 'transparent');
    $th = 'padding:6px;text-align:end';
    $pct = fn ($v) => $v === null ? '–' : number_format($v * 100, 1).'%';
    $cur = $client?->currency;
    $qsColor = fn ($q) => $q === null ? 'gray' : ($q >= 7 ? 'success' : ($q >= 5 ? 'warning' : 'danger'));
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <label style="{{ $muted }}" for="g-client">{{ __('Client') }}</label>
            <select id="g-client" wire:model.live="clientId" style="{{ $field }}">
                @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            @foreach (['7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'] as $k => $label)
                <button type="button" wire:click="setPeriod('{{ $k }}')" style="{{ $pill($period === $k) }}">{{ __($label) }}</button>
            @endforeach
        </div>
        @if ($client)<div style="{{ $muted }}">{{ $from }} → {{ $to }} · {{ $cur }}</div>@endif
    </div>

    @if (! $client)
        <x-filament::section><div style="{{ $muted }}">{{ __('No active clients yet.') }}</div></x-filament::section>
    @elseif (! $hasGoogle)
        <x-filament::section><div style="{{ $muted }}">{{ __('This client has no Google Ads account yet. Connect Google Ads in Networks and link an account to this client.') }}</div></x-filament::section>
    @else
        {{-- #46 Search terms --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Search terms') }}</x-slot>
            @if ($waste['count'])
                <div style="{{ $card }};border-color:#f59e0b;margin-bottom:10px">
                    {!! __(':cost spent on :count search terms with :clicks+ clicks and no conversions in this period. Review them and add the irrelevant ones as negatives.', ['cost' => '<strong><bdi>'.e(R::money($waste['cost'], $cur)).'</bdi></strong>', 'count' => '<strong>'.e($waste['count']).'</strong>', 'clicks' => e(config('insights.search_terms.waste_min_clicks'))]) !!}
                </div>
            @endif
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px">
                <button type="button" wire:click="$toggle('wasteOnly')" style="{{ $pill($wasteOnly) }}">{{ __('Only wasted spend') }}</button>
                @if ($canManage)
                    <span style="{{ $muted }};margin-inline-start:8px">{{ __(':count selected', ['count' => count($selected)]) }}</span>
                    <select wire:model="match" style="{{ $field }}" aria-label="{{ __('Match type for negatives') }}">
                        <option value="EXACT">{{ __('Exact match') }}</option><option value="PHRASE">{{ __('Phrase match') }}</option>
                    </select>
                    <x-filament::button size="sm" color="danger" wire:click="addNegatives" :disabled="! count($selected)"
                        wire:confirm="{{ __('Add the selected search terms as negative keywords in Google Ads? This changes the live campaigns.') }}">{{ __('Add as negatives') }}</x-filament::button>
                @endif
            </div>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="{{ $muted }}">@if ($canManage)<th style="padding:6px"></th>@endif<th style="padding:6px;text-align:start">{{ __('Search term') }}</th>
                        <th style="padding:6px;text-align:start">{{ __('Campaign') }}</th><th style="{{ $th }}">{{ __('Clicks') }}</th><th style="{{ $th }}">{{ __('CTR') }}</th><th style="{{ $th }}">{{ __('Cost') }}</th>
                        <th style="{{ $th }}">{{ __('Conv.') }}</th><th style="{{ $th }}">{{ __('Cost / conv.') }}</th><th style="padding:6px;text-align:start">{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @forelse ($terms as $t)
                        <tr style="border-top:1px solid rgba(148,163,184,.2);{{ $t['waste'] ? 'background:rgba(245,158,11,.06)' : '' }}">
                            @if ($canManage)<td style="padding:6px">@if ($t['status'] !== 'EXCLUDED')<input type="checkbox" wire:model.live="selected" value="{{ $t['key'] }}" aria-label="{{ __('Select :term', ['term' => $t['term']]) }}">@endif</td>@endif
                            <td style="padding:6px" dir="auto">{{ $t['term'] }}</td>
                            <td style="padding:6px;{{ $muted }}">{{ $t['campaign_name'] }}</td>
                            <td style="{{ $th }}">{{ number_format($t['clicks']) }}</td>
                            <td style="{{ $th }}">{{ $t['ctr'] !== null ? number_format($t['ctr'], 2).'%' : '–' }}</td>
                            <td style="{{ $th }}">{{ R::money($t['cost'], $cur) }}</td>
                            <td style="{{ $th }}">{{ number_format($t['conversions'], 1) }}</td>
                            <td style="{{ $th }}">{{ R::money($t['cpa'], $cur) }}</td>
                            <td style="padding:6px">{{ __(G::label($t['status'])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="padding:12px;{{ $muted }}">{{ $wasteOnly ? __('No wasted search terms in this period.') : __('No search terms yet. They arrive with the next sync of Search campaigns.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if (count($history))
                <div style="margin-top:10px;font-size:12px">
                    <div style="{{ $muted }}">{{ __('Recent negatives') }}</div>
                    @foreach ($history as $h)
                        <div>{{ \Illuminate\Support\Carbon::parse($h->created_at)->diffForHumans() }} · <strong dir="auto">{{ $h->text }}</strong> ({{ strtolower($h->match_type) }}) {{ __('by :name', ['name' => $h->user_name ?? '—']) }} ·
                            {{ ['ok' => __('added'), 'demo' => __('demo (not sent to Google)'), 'error' => __('failed: :error', ['error' => $h->error])][$h->status] ?? $h->status }}</div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- #47 Impression share --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Impression share (Search campaigns)') }}</x-slot>
            @if (! $is)
                <div style="{{ $muted }}">{{ __('No impression share data yet for this period.') }}</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Campaign') }}</th><th style="{{ $th }}">{{ __('Impression share') }}</th>
                            <th style="{{ $th }}">{{ __('Lost to budget') }}</th><th style="{{ $th }}">{{ __('Lost to rank') }}</th><th style="{{ $th }}">{{ __('Top') }}</th><th style="{{ $th }}">{{ __('Absolute top') }}</th>
                            <th style="padding:6px;text-align:start">{{ __('What to do') }}</th></tr></thead>
                        <tbody>
                        @foreach ($is as $r)
                            <tr style="border-top:1px solid rgba(148,163,184,.2)">
                                <td style="padding:6px">{{ $r['name'] }}</td><td style="{{ $th }}">{{ $pct($r['search_is']) }}</td>
                                <td style="{{ $th }}">{{ $pct($r['lost_budget']) }}</td><td style="{{ $th }}">{{ $pct($r['lost_rank']) }}</td>
                                <td style="{{ $th }}">{{ $pct($r['top_is']) }}</td><td style="{{ $th }}">{{ $pct($r['abs_top_is']) }}</td>
                                <td style="padding:6px;font-size:12px">
                                    @if (($r['lost_budget'] ?? 0) >= 0.2) {{ __('Budget limits delivery: raise the budget if cost per conversion is on target.') }}
                                    @elseif (($r['lost_rank'] ?? 0) >= 0.3) {{ __('Ad rank is low: improve Quality Score, ad relevance or bids.') }}
                                    @else <span style="{{ $muted }}">{{ __('Healthy') }}</span> @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="{{ $muted }};margin-top:6px">{{ __('Average of the daily values. Google reports shares below 10% as 10%.') }}</div>
            @endif
        </x-filament::section>

        {{-- #47 Quality Score --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Keyword Quality Score') }}</x-slot>
            @if (! $kw['rows'])
                <div style="{{ $muted }}">{{ __('No keyword data yet. It is refreshed with the nightly sync.') }}</div>
            @else
                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:8px;font-size:13px">
                    <div>{{ __('Average QS (by impressions):') }} <strong>{{ $kw['avg_qs'] ? number_format($kw['avg_qs'], 1) : '–' }}</strong> / 10</div>
                    <div>{{ __('Keywords with QS 4 or lower:') }} <strong>{{ $kw['low'] }}</strong></div>
                    <div style="{{ $muted }}">{{ __('Snapshot :when, metrics for the last 30 days.', ['when' => $kw['snapshot'] ? \Illuminate\Support\Carbon::parse($kw['snapshot'])->diffForHumans() : '']) }}</div>
                </div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Keyword') }}</th><th style="padding:6px;text-align:start">{{ __('Match') }}</th>
                            <th style="padding:6px;text-align:start">{{ __('QS') }}</th><th style="padding:6px;text-align:start">{{ __('Expected CTR') }}</th><th style="padding:6px;text-align:start">{{ __('Ad relevance') }}</th>
                            <th style="padding:6px;text-align:start">{{ __('Landing page') }}</th><th style="{{ $th }}">{{ __('Cost') }}</th><th style="{{ $th }}">{{ __('Conv.') }}</th></tr></thead>
                        <tbody>
                        @foreach (array_slice($kw['rows'], 0, 60) as $k)
                            <tr style="border-top:1px solid rgba(148,163,184,.2)">
                                <td style="padding:6px" dir="auto">{{ $k['text'] }}<div style="{{ $muted }}">{{ $k['campaign_name'] }}</div></td>
                                <td style="padding:6px">{{ __(G::label($k['match_type'])) }}</td>
                                <td style="padding:6px"><x-filament::badge :color="$qsColor($k['qs'])">{{ $k['qs'] ?? '–' }}</x-filament::badge></td>
                                <td style="padding:6px">{{ __(G::label($k['ctr_qs'])) }}</td><td style="padding:6px">{{ __(G::label($k['creative_qs'])) }}</td>
                                <td style="padding:6px">{{ __(G::label($k['landing_qs'])) }}</td>
                                <td style="{{ $th }}">{{ R::money($k['cost_c'], $cur) }}</td><td style="{{ $th }}">{{ number_format($k['conversions'], 1) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- #48 PMax --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Performance Max asset groups (last 30 days)') }}</x-slot>
            @if (! $groups)
                <div style="{{ $muted }}">{{ __('No Performance Max asset groups for this client.') }}</div>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px">
                    @foreach ($groups as $g)
                        <div style="{{ $card }}">
                            <div style="display:flex;justify-content:space-between;gap:8px"><strong>{{ $g['name'] }}</strong>
                                <x-filament::badge :color="['EXCELLENT' => 'success', 'GOOD' => 'success', 'AVERAGE' => 'warning', 'POOR' => 'danger'][$g['ad_strength']] ?? 'gray'">{{ __('Ad strength: :value', ['value' => __(G::label($g['ad_strength']))]) }}</x-filament::badge></div>
                            <div style="{{ $muted }}">{{ $g['campaign_name'] }} · {{ __(G::label($g['status'])) }}</div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-top:8px;font-size:13px">
                                <div><div style="{{ $muted }}">{{ __('Cost') }}</div>{{ R::money($g['cost_c'], $cur) }}</div>
                                <div><div style="{{ $muted }}">{{ __('Conv.') }}</div>{{ number_format($g['conversions'], 1) }}</div>
                                <div><div style="{{ $muted }}">{{ __('Cost / conv.') }}</div>{{ R::money($g['cpa'], $cur) }}</div>
                            </div>
                            <div style="margin-top:8px;font-size:12px">{{ __('Assets:') }}
                                @foreach ($g['labels'] as $lab => $n)
                                    <x-filament::badge :color="['BEST' => 'success', 'GOOD' => 'success', 'LOW' => 'danger', 'LEARNING' => 'warning'][$lab] ?? 'gray'" style="display:inline-flex">{{ $n }} {{ __(strtolower(G::label($lab))) }}</x-filament::badge>
                                @endforeach
                            </div>
                            @php $lows = array_filter($g['asset_list'], fn ($a) => ($a['label'] ?? null) === 'LOW'); @endphp
                            @if ($lows)
                                <div style="{{ $muted }};margin-top:6px">{{ __('Replace:') }} @foreach (array_slice($lows, 0, 4) as $a)<span dir="auto">“{{ $a['text'] ?: __(strtolower(G::label($a['type']))) }}”</span>@if (! $loop->last), @endif @endforeach</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div style="{{ $muted }};margin-top:6px">{{ __('Asset ratings come from Google where it still reports them; newer API versions may leave assets unrated.') }}</div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
