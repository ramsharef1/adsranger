<x-filament-panels::page>
@php
    $money = fn (?float $v, string $cur) => \App\Support\ResultResolver::money($v, $cur);
@endphp
<style>
.st-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.st-card{padding:14px;border-radius:12px;border:1px solid rgba(30,41,59,.22);background:rgba(15,23,42,.03)}
.dark .st-card{border-color:rgba(148,163,184,.22);background:rgba(30,41,59,.45)}
.st-card strong{display:block;font-size:22px;line-height:1.25;color:#0f172a}
.dark .st-card strong{color:#f8fafc}
.st-card span{font-size:12.5px;opacity:.78}
.st-in{width:100%;min-height:44px;border-radius:8px;border:1px solid rgba(100,116,139,.45);padding:8px 12px;background:transparent;color:inherit}
.st-btn{min-height:44px;padding:8px 16px;border-radius:8px;background:#0d9488;color:#fff;font-weight:600;border:0;cursor:pointer}
.st-btn.ghost{background:transparent;color:inherit;border:1px solid rgba(100,116,139,.45)}
.st-muted{font-size:13px;opacity:.78;line-height:1.6}
.st-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
.st-table th,.st-table td{padding:9px 8px;border-bottom:1px solid rgba(148,163,184,.2);text-align:start}
.st-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;background:rgba(20,184,166,.14);color:#0d9488;font-weight:600}
.dark .st-badge{color:#14b8a6}
</style>

@if (! $client)
    <x-filament::section>
        <p class="st-muted">{{ __('Add or select a client first to inspect search telemetry and server logs.') }}</p>
    </x-filament::section>
@else
    <x-filament::section>
        <x-slot name="heading">{{ __('Client & date window') }}</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">
            <label class="st-muted">{{ __('Client') }}
                <select wire:model.live="clientId" class="st-in">
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="st-muted">{{ __('From date') }}
                <input type="date" wire:model.live="startDate" class="st-in" dir="ltr">
            </label>
            <label class="st-muted">{{ __('To date') }}
                <input type="date" wire:model.live="endDate" class="st-in" dir="ltr">
            </label>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Search Console: striking distance (#4–#15) & query decay') }}</x-slot>
        @if ($canManage)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
                <label class="st-muted">{{ __('Property site URL') }}
                    <input type="text" wire:model="siteUrl" class="st-in" dir="ltr" placeholder="https://example.sa">
                </label>
                <label class="st-muted">{{ __('OAuth access token (optional if importing CSV)') }}
                    <input type="password" wire:model="gscAccessToken" class="st-in" dir="ltr" autocomplete="off">
                </label>
            </div>
            <label class="st-muted" style="display:block;margin-top:10px">{{ __('Or paste Search Console CSV export (query,page,clicks,impressions,ctr,position,date)') }}
                <textarea wire:model="gscCsv" rows="3" class="st-in" dir="ltr"></textarea>
            </label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                <button type="button" class="st-btn" wire:click="importGscCsv">{{ __('Import CSV') }}</button>
                <button type="button" class="st-btn ghost" wire:click="syncGscApi">{{ __('Sync Search Console API') }}</button>
                @if ($gscStatus !== '')
                    <span class="st-badge"><bdi dir="ltr">{{ $gscStatus }}</bdi></span>
                @endif
            </div>
        @endif

        <h3 style="font-weight:600;margin-top:16px">{{ __('Striking-distance queries (#4–#15)') }}</h3>
        @if ($striking === [])
            <p class="st-muted">{{ __('No striking-distance queries recorded for this window yet.') }}</p>
        @else
            <div style="overflow-x:auto">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th>{{ __('Query') }}</th>
                            <th>{{ __('Page') }}</th>
                            <th>{{ __('Position') }}</th>
                            <th>{{ __('Impressions') }}</th>
                            <th>{{ __('Clicks') }}</th>
                            <th>{{ __('Potential extra clicks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($striking as $row)
                            <tr>
                                <td>{{ $row['query'] }}</td>
                                <td><bdi dir="ltr">{{ $row['page_url'] }}</bdi></td>
                                <td><bdi dir="ltr">#{{ $row['position'] }}</bdi></td>
                                <td><bdi dir="ltr">{{ number_format($row['impressions']) }}</bdi></td>
                                <td><bdi dir="ltr">{{ number_format($row['clicks']) }}</bdi></td>
                                <td><bdi dir="ltr">+{{ number_format($row['estimated_extra_clicks']) }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h3 style="font-weight:600;margin-top:16px">{{ __('Decaying queries (14d vs prior 14d)') }}</h3>
        @if ($decay === [])
            <p class="st-muted">{{ __('No decaying queries flagged in the current window.') }}</p>
        @else
            <div style="overflow-x:auto">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th>{{ __('Query') }}</th>
                            <th>{{ __('Prior clicks') }}</th>
                            <th>{{ __('Recent clicks') }}</th>
                            <th>{{ __('Click delta') }}</th>
                            <th>{{ __('Position shift') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($decay as $row)
                            <tr>
                                <td>{{ $row['query'] }}</td>
                                <td><bdi dir="ltr">{{ $row['prior_clicks'] }}</bdi></td>
                                <td><bdi dir="ltr">{{ $row['recent_clicks'] }}</bdi></td>
                                <td><bdi dir="ltr">{{ $row['click_delta_pct'] }}%</bdi></td>
                                <td><bdi dir="ltr">{{ $row['prior_position'] }} → {{ $row['recent_position'] }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('3-way funnel reconciliation: Ad Claims vs GA4 vs Store') }}</x-slot>
        @if ($canManage)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:12px">
                <label class="st-muted">{{ __('GA4 Property ID') }}
                    <input type="text" wire:model="ga4PropertyId" class="st-in" dir="ltr" placeholder="123456789">
                </label>
                <label class="st-muted">{{ __('GA4 OAuth access token') }}
                    <input type="password" wire:model="ga4AccessToken" class="st-in" dir="ltr" autocomplete="off">
                </label>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
                <button type="button" class="st-btn ghost" wire:click="syncGa4Api">{{ __('Sync GA4 Data API') }}</button>
                @if ($ga4Status !== '')
                    <span class="st-badge"><bdi dir="ltr">{{ $ga4Status }}</bdi></span>
                @endif
            </div>
        @endif

        @if ($reconciliation)
            <div class="st-grid">
                @if (! $client->hide_spend)
                    <div class="st-card">
                        <strong><bdi dir="ltr">{{ $money($reconciliation['ad_spend'], $reconciliation['currency']) }}</bdi></strong>
                        <span>{{ __('Ad spend') }}</span>
                    </div>
                @endif
                <div class="st-card">
                    <strong><bdi dir="ltr">{{ number_format($reconciliation['ad_conversions'], 1) }}</bdi></strong>
                    <span>{{ __('Ad claimed conversions') }} · <bdi dir="ltr">{{ $money($reconciliation['ad_revenue'], $reconciliation['currency']) }}</bdi></span>
                </div>
                <div class="st-card">
                    <strong><bdi dir="ltr">{{ number_format($reconciliation['ga4_key_events'], 1) }}</bdi></strong>
                    <span>{{ __('GA4 key events') }} · <bdi dir="ltr">{{ $money($reconciliation['ga4_revenue'], $reconciliation['currency']) }}</bdi></span>
                </div>
                <div class="st-card">
                    <strong><bdi dir="ltr">{{ number_format($reconciliation['store_orders']) }}</bdi></strong>
                    <span>{{ __('Verified store orders') }} · <bdi dir="ltr">{{ $money($reconciliation['store_revenue'], $reconciliation['currency']) }}</bdi></span>
                </div>
            </div>
            <p class="st-muted" style="margin-top:10px">
                {{ __('Reconciliation status') }}: <span class="st-badge"><bdi dir="ltr">{{ $reconciliation['status'] }}</bdi></span>
                @if ($reconciliation['ad_vs_store_pct'] !== null)
                    · {{ __('Ad vs Store gap') }}: <bdi dir="ltr">{{ $reconciliation['ad_vs_store_pct'] }}%</bdi>
                @endif
            </p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Server access log analyzer (streamed .log / .gz)') }}</x-slot>
        @if ($canManage)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
                <label class="st-muted">{{ __('Upload .log or .gz access log') }}
                    <input type="file" wire:model="logUpload" class="st-in" accept=".log,.txt,.gz">
                </label>
                <label class="st-muted">{{ __('Log label') }}
                    <input type="text" wire:model="logFilename" class="st-in" dir="ltr">
                </label>
            </div>
            <label class="st-muted" style="display:block;margin-top:10px">{{ __('Or paste access log lines') }}
                <textarea wire:model="logPaste" rows="4" class="st-in" dir="ltr"></textarea>
            </label>
            <div style="margin-top:10px">
                <button type="button" class="st-btn" wire:click="analyzeAccessLog">{{ __('Analyze access log') }}</button>
            </div>
        @endif

        @if ($latestLog)
            <div class="st-grid" style="margin-top:14px">
                <div class="st-card"><strong><bdi dir="ltr">{{ number_format($latestLog->total_lines) }}</bdi></strong><span>{{ __('Parsed lines') }} · <bdi dir="ltr">{{ $latestLog->filename }}</bdi></span></div>
                @foreach (['Googlebot', 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Bingbot'] as $bot)
                    <div class="st-card"><strong><bdi dir="ltr">{{ number_format((int) ($latestLog->bot_hits[$bot] ?? 0)) }}</bdi></strong><span><bdi dir="ltr">{{ $bot }}</bdi></span></div>
                @endforeach
            </div>
            @if (! empty($latestLog->wasted_budget_paths))
                <h3 style="font-weight:600;margin-top:14px">{{ __('Crawl waste paths (3xx / 4xx / 5xx & parameter traps)') }}</h3>
                <ul class="st-muted">
                    @foreach (array_slice($latestLog->wasted_budget_paths, 0, 8) as $w)
                        <li><bdi dir="ltr">{{ $w['path'] }}</bdi> — <bdi dir="ltr">{{ $w['hits'] }}</bdi> {{ __('hits') }} (<bdi dir="ltr">{{ implode(', ', $w['bots'] ?? []) }}</bdi>)</li>
                    @endforeach
                </ul>
            @endif
            @if (! empty($latestLog->orphan_candidates))
                <h3 style="font-weight:600;margin-top:14px">{{ __('Orphan page candidates (crawled 200 OK, absent from site map / GSC)') }}</h3>
                <ul class="st-muted">
                    @foreach (array_slice($latestLog->orphan_candidates, 0, 8) as $o)
                        <li><bdi dir="ltr">{{ $o['path'] }}</bdi> — <bdi dir="ltr">{{ $o['bot_hits'] }}</bdi> {{ __('hits') }}</li>
                    @endforeach
                </ul>
            @endif
        @else
            <p class="st-muted" style="margin-top:12px">{{ __('No server log analyzed for this client yet.') }}</p>
        @endif
    </x-filament::section>
@endif
</x-filament-panels::page>
