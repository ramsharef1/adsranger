@php
    use App\Filament\Pages\MenaCalendarPage;
    use App\Filament\Pages\RamadanReadinessPage;
    use App\Filament\Resources\Clients\ClientResource;
    use App\Support\RamadanPhases;
    use Carbon\Carbon;

    $cycle = $board['selected_cycle'];
    $hours = $board['hours'];
    $locale = app()->getLocale();
    $paceChip = fn (string $status): array => match ($status) {
        'on' => [__('On pace'), 'hub-chip hub-ok'],
        'under' => [__('Under-pacing'), 'hub-chip hub-warn'],
        'over' => [__('Over-pacing'), 'hub-chip hub-bad'],
        default => ['—', 'hub-chip'],
    };
    $stateChip = fn (string $state): array => match ($state) {
        'active' => [__('Active'), 'hub-chip hub-ok'],
        'completed' => [__('Completed'), 'hub-chip'],
        default => [__('Upcoming'), 'hub-chip'],
    };
@endphp
<x-filament-panels::page>
    @include('filament.pages.partials.hub-styles')
    <style data-ramadan-phases-styles>
        .hub .rp-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;justify-content:space-between}
        .hub .rp-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
        .hub .rp-links{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
        .hub .rp-field{display:grid;gap:4px;min-width:190px;max-width:100%}
        .hub .rp-touch-link{display:inline-flex;align-items:center;min-height:44px;min-width:44px;padding:0 14px;border:1px solid var(--h-control);border-radius:10px;background:var(--h-card);color:var(--h-ac);font-size:14px;font-weight:600;text-decoration:none;box-sizing:border-box}
        .hub .rp-touch-link:hover{border-color:var(--h-ac)}
        .hub .rp-meta-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:12px}
        @media (min-width:768px){.hub .rp-meta-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .hub .rp-phases-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:12px}
        @media (min-width:640px){.hub .rp-phases-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (min-width:1100px){.hub .rp-phases-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media (min-width:1540px){.hub .rp-phases-grid{grid-template-columns:repeat(6,minmax(0,1fr))}}
        .hub .rp-phase-card{background:var(--h-soft);border:1px solid var(--h-line);border-radius:12px;padding:12px;min-width:0;display:flex;flex-direction:column;gap:8px}
        .hub .rp-phase-card[data-phase-state="active"]{border-color:var(--h-ac);background:var(--h-ac-soft)}
        .hub .rp-client-head{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:12px}
        .hub .rp-client-link{display:inline-flex;align-items:center;min-height:44px;color:var(--h-ac);font-size:16px;font-weight:700;text-decoration:underline}
        .hub .rp-metric-list{display:grid;gap:6px;margin-top:2px}
        .hub .rp-metric-row{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:6px;padding-top:6px;border-top:1px solid var(--h-line);font-size:13px}
        .hub .rp-metric-row:first-child{border-top:0;padding-top:0}
        .hub .rp-delta{font-size:12px;color:var(--h-mu);font-weight:500}
    </style>

    <div class="hub space-y-6" data-ramadan-phases>
        @if ($cycle === null)
            <div class="bg-white border rounded-xl p-4">
                <p>{{ __('No Ramadan date found in the next 13 months.') }}</p>
            </div>
        @else
            <div class="bg-white border rounded-xl p-4 space-y-4">
                <div class="rp-toolbar">
                    <div class="rp-filters">
                        <div class="rp-field">
                            <label for="rp-cycle" class="text-xs font-semibold">{{ __('Ramadan season') }}</label>
                            <select id="rp-cycle" wire:model.live="cycleStart">
                                @foreach ($board['cycles'] as $cOpt)
                                    <option value="{{ $cOpt['key'] }}">
                                        {{ $cOpt['hijri_label'] }} · {{ $cOpt['ramadan']['start'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rp-field">
                            <label for="rp-client" class="text-xs font-semibold">{{ __('Client') }}</label>
                            <select id="rp-client" wire:model.live="clientFilter">
                                <option value="">{{ __('All clients') }}</option>
                                @foreach ($board['visible_clients'] as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="rp-links">
                        <a href="{{ RamadanReadinessPage::getUrl() }}" class="rp-touch-link">{{ __('Ramadan readiness') }}</a>
                        <a href="{{ MenaCalendarPage::getUrl() }}" class="rp-touch-link">{{ __('MENA calendar') }}</a>
                    </div>
                </div>

                <div class="rp-meta-grid">
                    <div class="bg-gray-50 border rounded-lg p-3 space-y-2" data-ramadan-meta>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold">{{ __('Ramadan') }} · <bdi>{{ $cycle['hijri_label'] }}</bdi></span>
                            <span class="hub-chip {{ $cycle['ramadan']['status'] === 'confirmed' ? 'hub-ok' : 'hub-warn' }}" data-ramadan-status="{{ $cycle['ramadan']['status'] }}">
                                {{ $cycle['ramadan']['status'] === 'confirmed' ? __('Confirmed') : __('Expected') }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-600">
                            <bdi>{{ Carbon::parse($cycle['ramadan']['start'])->locale($locale)->translatedFormat('j M Y') }} → {{ Carbon::parse($cycle['ramadan']['end'])->locale($locale)->translatedFormat('j M Y') }}</bdi>
                            · <bdi>{{ trans_choice(':count day|:count days', $cycle['ramadan']['days'], ['count' => $cycle['ramadan']['days']]) }}</bdi>
                        </div>
                        @if ($cycle['ramadan']['status'] === 'confirmed' && $cycle['ramadan']['calc_start'] !== $cycle['ramadan']['start'])
                            <div class="hub-note">{{ __('Calculated') }}: <bdi dir="ltr">{{ $cycle['ramadan']['calc_start'] }}</bdi></div>
                        @endif
                        @if (! empty($cycle['ramadan']['source']))
                            <div class="hub-note">{{ __('Source') }}: <bdi>{{ $cycle['ramadan']['source'] }}</bdi></div>
                        @endif
                    </div>

                    <div class="bg-gray-50 border rounded-lg p-3 space-y-2" data-eid-meta>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold">{{ __('Eid al-Fitr') }}</span>
                            <span class="hub-chip {{ $cycle['eid_fitr']['status'] === 'confirmed' ? 'hub-ok' : 'hub-warn' }}" data-eid-status="{{ $cycle['eid_fitr']['status'] }}">
                                {{ $cycle['eid_fitr']['status'] === 'confirmed' ? __('Confirmed') : __('Expected') }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-600">
                            <bdi>{{ Carbon::parse($cycle['eid_fitr']['start'])->locale($locale)->translatedFormat('j M Y') }} → {{ Carbon::parse($cycle['eid_fitr']['end'])->locale($locale)->translatedFormat('j M Y') }}</bdi>
                            · <bdi>{{ trans_choice(':count day|:count days', $cycle['eid_fitr']['days'], ['count' => $cycle['eid_fitr']['days']]) }}</bdi>
                        </div>
                        @if ($cycle['eid_fitr']['status'] === 'confirmed' && $cycle['eid_fitr']['calc_start'] !== $cycle['eid_fitr']['start'])
                            <div class="hub-note">{{ __('Calculated') }}: <bdi dir="ltr">{{ $cycle['eid_fitr']['calc_start'] }}</bdi></div>
                        @endif
                        @if (! empty($cycle['eid_fitr']['source']))
                            <div class="hub-note">{{ __('Source') }}: <bdi>{{ $cycle['eid_fitr']['source'] }}</bdi></div>
                        @endif
                    </div>

                    <div class="bg-gray-50 border rounded-lg p-3 space-y-2" data-ramadan-hours-meta>
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold">{{ __('Ramadan hours') }}</span>
                            @if ($hours['active'])
                                <span class="hub-chip hub-ok" data-ramadan-hours="active">{{ __('Active now') }}</span>
                            @elseif ($hours['enabled'])
                                <span class="hub-chip" data-ramadan-hours="scheduled">{{ __('Switches on in Ramadan') }}</span>
                            @else
                                <span class="hub-chip hub-warn" data-ramadan-hours="off">{{ __('Switched off') }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-600">
                            {!! __('Daily summary after iftar: :time · Quiet hours: :quiet', [
                                'time' => '<bdi dir="ltr">'.e($hours['digest_at']).'</bdi>',
                                'quiet' => '<bdi dir="ltr">'.e($hours['quiet_start'].'–'.$hours['quiet_end']).'</bdi>',
                            ]) !!}
                        </div>
                        <div class="hub-note"><bdi dir="ltr">{{ $board['timezone'] }}</bdi></div>
                    </div>
                </div>
            </div>

            @if ($board['rows'] === [])
                <div class="bg-white border rounded-xl p-4" data-ramadan-empty>
                    <p>{{ __('Add a client first.') }}</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($board['rows'] as $row)
                        @php $client = $row['client']; @endphp
                        <section class="bg-white border rounded-xl p-4" data-client-phase-row="{{ $client->id }}">
                            <div class="rp-client-head">
                                <a href="{{ ClientResource::getUrl('view', ['record' => $client->id]) }}" class="rp-client-link">
                                    <bdi>{{ $client->name }}</bdi>
                                </a>
                                @if ($row['hide_spend'])
                                    <span class="hub-chip" data-hide-spend="1">{{ __('Spend hidden') }}</span>
                                @endif
                            </div>

                            <div class="rp-phases-grid">
                                @foreach ($row['phases'] as $phase)
                                    @php
                                        [$sText, $sClass] = $stateChip($phase['state']);
                                        [$pText, $pClass] = $paceChip($phase['pace_status']);
                                    @endphp
                                    <div class="rp-phase-card" data-phase-key="{{ $phase['key'] }}" data-phase-state="{{ $phase['state'] }}">
                                        <div>
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="font-semibold text-sm">{{ $phase['label'] }}</span>
                                                <span class="{{ $sClass }}">{{ $sText }}</span>
                                            </div>
                                            <div class="hub-note mt-1">
                                                <bdi>{{ Carbon::parse($phase['start'])->locale($locale)->translatedFormat('j M') }} → {{ Carbon::parse($phase['end'])->locale($locale)->translatedFormat('j M') }}</bdi>
                                                · <bdi>{{ trans_choice(':count day|:count days', $phase['days'], ['count' => $phase['days']]) }}</bdi>
                                            </div>
                                        </div>

                                        <div class="rp-metric-list">
                                            @if (! $row['hide_spend'])
                                                <div class="rp-metric-row">
                                                    <span class="text-gray-600">{{ __('Spend') }}</span>
                                                    <span class="font-semibold">
                                                        @if ($phase['has_data'] && $phase['spend_formatted'] !== null)
                                                            <bdi>{{ $phase['spend_formatted'] }}</bdi>
                                                            @if ($phase['spend_delta_pct'] !== null)
                                                                <span class="rp-delta">(<bdi>{{ RamadanPhases::formatDelta($phase['spend_delta_pct']) }}</bdi>)</span>
                                                            @endif
                                                        @else
                                                            —
                                                        @endif
                                                    </span>
                                                </div>
                                            @endif

                                            <div class="rp-metric-row">
                                                <span class="text-gray-600">{{ $phase['result_label'] ?: __('Results') }}</span>
                                                <span class="font-semibold">
                                                    @if ($phase['has_data'] && $phase['results_formatted'] !== null)
                                                        <bdi>{{ $phase['results_formatted'] }}</bdi>
                                                        @if ($phase['results_delta_pct'] !== null)
                                                            <span class="rp-delta">(<bdi>{{ RamadanPhases::formatDelta($phase['results_delta_pct']) }}</bdi>)</span>
                                                        @endif
                                                    @else
                                                        —
                                                    @endif
                                                </span>
                                            </div>

                                            @if (! $row['hide_spend'])
                                                <div class="rp-metric-row">
                                                    <span class="text-gray-600">{{ __('Cost per result') }}</span>
                                                    <span class="font-semibold">
                                                        @if ($phase['has_data'] && $phase['cpr_formatted'] !== null)
                                                            <bdi>{{ $phase['cpr_formatted'] }}</bdi>
                                                            @if ($phase['cpr_delta_pct'] !== null)
                                                                <span class="rp-delta">(<bdi>{{ RamadanPhases::formatDelta($phase['cpr_delta_pct']) }}</bdi>)</span>
                                                            @endif
                                                        @else
                                                            —
                                                        @endif
                                                    </span>
                                                </div>

                                                <div class="rp-metric-row">
                                                    <span class="text-gray-600">{{ __('Planned budget') }}</span>
                                                    <span class="font-medium">
                                                        @if ($phase['planned_formatted'] !== null)
                                                            <bdi>{{ $phase['planned_formatted'] }}</bdi>
                                                        @else
                                                            —
                                                        @endif
                                                    </span>
                                                </div>

                                                <div class="rp-metric-row">
                                                    <span class="text-gray-600">{{ __('Pace') }}</span>
                                                    <span>
                                                        @if ($phase['pace_pct'] !== null)
                                                            <span class="font-semibold"><bdi>{{ round($phase['pace_pct']) }}%</bdi></span>
                                                            <span class="{{ $pClass }}">{{ $pText }}</span>
                                                        @else
                                                            —
                                                        @endif
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                <div class="hub-note">
                    {{ __('Change percentages compare the daily average of each phase with the previous phase. Planned budgets prorate by day from the monthly budget plan (or the client\'s monthly budget). Phases without synced or imported data show —.') }}
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
