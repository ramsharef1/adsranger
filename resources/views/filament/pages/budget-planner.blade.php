@php
    use App\Support\ResultResolver as R;
    $muted = 'opacity:.65;font-size:12px';
    $input = 'width:110px;padding:4px 8px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;text-align:end';
    $cur = $client?->currency;
    $thisMonth = $pace['month'] ?? null;
    $tot = ['plan' => 0, 'spend' => 0, 'tres' => 0, 'res' => 0];
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <label style="{{ $muted }}" for="bp-client">Client</label>
            <select id="bp-client" wire:model.live="clientId" style="padding:5px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px">
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-left" wire:click="setYear({{ $year - 1 }})" aria-label="Previous year">{{ $year - 1 }}</x-filament::button>
            <strong style="font-size:15px">{{ $year }}</strong>
            <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="setYear({{ $year + 1 }})" aria-label="Next year">{{ $year + 1 }}</x-filament::button>
        </div>
        @if ($client && $canManage)
            <div style="display:flex;gap:6px">
                <x-filament::button size="sm" color="gray" wire:click="fillFromClient">Fill empty months from client budget</x-filament::button>
                <x-filament::button size="sm" icon="heroicon-o-check" wire:click="save">Save plan</x-filament::button>
            </div>
        @endif
    </div>

    @if (! $client)
        <x-filament::section><div style="{{ $muted }}">No clients yet. Add a client first, then plan its budget month by month.</div></x-filament::section>
    @else
        <x-filament::section>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="{{ $muted }}">
                        <th style="padding:6px;text-align:start">Month</th>
                        <th style="padding:6px;text-align:end">Planned budget ({{ $cur }})</th>
                        <th style="padding:6px;text-align:end">Actual spend</th>
                        <th style="padding:6px;text-align:end">Spend vs plan</th>
                        <th style="padding:6px;text-align:end">Target results</th>
                        <th style="padding:6px;text-align:end">Actual results</th>
                        <th style="padding:6px;text-align:end">Results vs target</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($plan as $k => $v)
                        @php
                            $a = $actual[$k] ?? null;
                            $isNow = $k === $thisMonth;
                            $spend = $isNow ? $pace['forecast_spend'] : ($a['spend'] ?? null);
                            $res = $isNow ? $pace['forecast_results'] : ($a['results'] ?? null);
                            $pb = is_numeric($v['budget']) ? (float) $v['budget'] : null;
                            $pr = is_numeric($v['results']) ? (float) $v['results'] : null;
                            $tot['plan'] += $pb ?? 0; $tot['spend'] += $a['spend'] ?? 0; $tot['tres'] += $pr ?? 0; $tot['res'] += $a['results'] ?? 0;
                        @endphp
                        <tr style="border-top:1px solid rgba(148,163,184,.2);{{ $isNow ? 'background:rgba(20,184,166,.06)' : '' }}">
                            <td style="padding:6px;font-weight:600">{{ \Carbon\Carbon::parse($k.'-01')->format('M Y') }}@if ($isNow)<div style="{{ $muted }}">this month, forecast</div>@endif</td>
                            <td style="padding:6px;text-align:end">
                                @if ($canManage)
                                    <input type="number" min="0" step="0.01" wire:model.blur="plan.{{ $k }}.budget" style="{{ $input }}" aria-label="Planned budget {{ $k }}">
                                @else {{ $pb !== null ? R::money($pb) : '–' }} @endif
                                @error("plan.$k.budget")<div style="color:#dc2626;font-size:12px">{{ $message }}</div>@enderror
                            </td>
                            <td style="padding:6px;text-align:end">{{ $spend !== null ? R::money($spend) : '–' }}</td>
                            <td style="padding:6px;text-align:end">{{ $pb && $spend !== null ? round($spend / $pb * 100).'%' : '–' }}</td>
                            <td style="padding:6px;text-align:end">
                                @if ($canManage)
                                    <input type="number" min="0" step="1" wire:model.blur="plan.{{ $k }}.results" style="{{ $input }}" aria-label="Target results {{ $k }}">
                                @else {{ $pr !== null ? number_format($pr) : '–' }} @endif
                                @error("plan.$k.results")<div style="color:#dc2626;font-size:12px">{{ $message }}</div>@enderror
                            </td>
                            <td style="padding:6px;text-align:end">{{ $res !== null ? number_format($res) : '–' }}</td>
                            <td style="padding:6px;text-align:end">{{ $pr && $res !== null ? round($res / $pr * 100).'%' : '–' }}</td>
                        </tr>
                    @endforeach
                    <tr style="border-top:2px solid rgba(148,163,184,.35);font-weight:700">
                        <td style="padding:6px">Year</td>
                        <td style="padding:6px;text-align:end">{{ R::money($tot['plan']) }}</td>
                        <td style="padding:6px;text-align:end">{{ R::money($tot['spend']) }}</td>
                        <td style="padding:6px;text-align:end">{{ $tot['plan'] ? round($tot['spend'] / $tot['plan'] * 100).'%' : '–' }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($tot['tres']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($tot['res']) }}</td>
                        <td style="padding:6px;text-align:end">{{ $tot['tres'] ? round($tot['res'] / $tot['tres'] * 100).'%' : '–' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div style="{{ $muted }};margin-top:8px">
                Amounts in {{ $cur }}. Actuals are converted at each day's rate. Results = the main result type of each day. Year totals use actuals only (no forecast).
                An empty month uses the client's monthly budget ({{ $client->monthly_budget ? R::money($client->monthly_budget, $cur) : 'not set' }}) for pacing.
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
