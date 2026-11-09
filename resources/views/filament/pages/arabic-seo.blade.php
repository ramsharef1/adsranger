@php
    $muted = 'opacity:.65;font-size:12px';
    $field = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $badge = 'display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid rgba(148,163,184,.35)';
    $w = $serpCheck['widths'] ?? ['title_px' => 0, 'title_chars' => 0, 'description_px' => 0, 'description_chars' => 0, 'title_truncated' => false, 'description_truncated' => false];
@endphp
<x-filament-panels::page>
    <div style="{{ $muted }}">
        {{ __('Arabic keyword clustering (S1), SERP pixel readiness (S2), live page & site audit (S3), and Search Console query opportunities (GSC).') }}
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:16px;align-items:start">
        <x-filament::section>
            <x-slot name="heading">{{ __('S1 · Arabic keyword generator & dialect cluster') }}</x-slot>
            <div style="display:grid;gap:12px">
                <div>
                    <label for="seo-seed" style="{{ $label }}">{{ __('Seed keyword') }}</label>
                    <input id="seo-seed" type="text" wire:model.live.debounce.300ms="seedKeyword" style="{{ $field }}">
                </div>
                <div style="font-size:13px">
                    <div><strong>{{ __('Canonical stem') }}:</strong> {{ $kwCluster['normalised_key'] ?: '—' }}</div>
                    <div style="margin-top:4px">
                        <strong>{{ __('Detected dialects') }}:</strong>
                        @forelse ($kwCluster['dialect_tags'] as $d)
                            <span style="{{ $badge }}">{{ $d }}</span>
                        @empty
                            <span style="{{ $muted }}">{{ __('Standard Arabic (MSA)') }}</span>
                        @endforelse
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    @foreach ($kwCluster['variants'] as $v)
                        <span style="{{ $badge }}">{{ $v }}</span>
                    @endforeach
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('S2 · Arabic SERP pixel & snippet readiness') }}</x-slot>
            <div style="display:grid;gap:12px">
                <div>
                    <label for="seo-title" style="{{ $label }}">{{ __('Meta title') }} ({{ $w['title_px'] }}px · {{ $w['title_chars'] }} {{ __('chars') }})</label>
                    <input id="seo-title" type="text" wire:model.live.debounce.300ms="serpTitle" style="{{ $field }}">
                </div>
                <div>
                    <label for="seo-desc" style="{{ $label }}">{{ __('Meta description') }} ({{ $w['description_px'] }}px · {{ $w['description_chars'] }} {{ __('chars') }})</label>
                    <textarea id="seo-desc" rows="2" wire:model.live.debounce.300ms="serpDescription" style="{{ $field }}"></textarea>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <span style="{{ $badge }}">{{ __('Readiness score') }}: {{ $serpCheck['score'] }}/100</span>
                    @if ($w['title_truncated'])
                        <span style="{{ $badge }};color:#dc2626">{{ __('Title exceeds 580px') }}</span>
                    @endif
                    @if ($w['description_truncated'])
                        <span style="{{ $badge }};color:#d97706">{{ __('Description exceeds 920px') }}</span>
                    @endif
                    @if ($serpCheck['keyword_in_title'] || $serpCheck['keyword_in_description'])
                        <span style="{{ $badge }};color:#0f766e">{{ __('Seed keyword matched') }}</span>
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('S3 · On-page Arabic SEO audit') }}</x-slot>
        <div style="display:grid;gap:12px">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input id="seo-audit-url" type="url" wire:model="auditUrl" placeholder="https://adsranger.com/" dir="ltr" style="{{ $field }};flex:1;min-width:220px">
                <x-filament::button wire:click="runSiteAudit" wire:loading.attr="disabled" wire:target="runSiteAudit">{{ __('Run page audit') }}</x-filament::button>
            </div>
            @error('auditUrl')<div style="color:#dc2626;font-size:12px">{{ $message }}</div>@enderror
            @if ($audit)
                <div style="font-size:13px">
                    <strong>{{ __('Audit score') }}:</strong> {{ $audit['average_score'] }} / 100
                    @if (!empty($audit['issue_counts']))
                        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                            @foreach ($audit['issue_counts'] as $issue => $cnt)
                                <span style="{{ $badge }};color:#dc2626">{{ $issue }} ({{ $cnt }})</span>
                            @endforeach
                        </div>
                    @else
                        <div style="margin-top:6px;color:#0f766e">{{ __('No core Arabic on-page issues detected.') }}</div>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            {{ __('GSC · Arabic search queries & CTR opportunities') }}
            @if ($gsc['source'] === 'simulated')
                <span style="{{ $badge }};margin-inline-start:8px">{{ __('Sample preview data (connect Google Search Console for live rows)') }}</span>
            @endif
        </x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="border-bottom:1px solid rgba(148,163,184,.35);text-align:start">
                        <th style="padding:8px">{{ __('Search query') }}</th>
                        <th style="padding:8px">{{ __('Dialect') }}</th>
                        <th style="padding:8px">{{ __('Clicks') }}</th>
                        <th style="padding:8px">{{ __('Impressions') }}</th>
                        <th style="padding:8px">{{ __('CTR') }}</th>
                        <th style="padding:8px">{{ __('Avg. position') }}</th>
                        <th style="padding:8px">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gsc['rows'] as $row)
                        <tr style="border-bottom:1px solid rgba(148,163,184,.18)">
                            <td style="padding:8px;font-weight:600">{{ $row['query'] }}</td>
                            <td style="padding:8px">{{ $row['dialects'] ? implode(', ', $row['dialects']) : 'MSA' }}</td>
                            <td style="padding:8px">{{ $row['clicks'] }}</td>
                            <td style="padding:8px">{{ $row['impressions'] }}</td>
                            <td style="padding:8px">{{ $row['ctr'] }}%</td>
                            <td style="padding:8px">{{ $row['position'] }}</td>
                            <td style="padding:8px">
                                @if ($row['opportunity'])
                                    <span style="{{ $badge }};color:#d97706">{{ __('CTR opportunity') }}</span>
                                @else
                                    <span style="{{ $badge }};color:#0f766e">{{ __('Healthy') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
