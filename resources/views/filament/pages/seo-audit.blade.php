<x-filament-panels::page>
@php
    $bd = fn (string $t) => (string) \App\Support\Seo\ToolCopy::html($t, $ar);
    $levelColor = ['critical' => '#b91c1c', 'warning' => '#b45309', 'info' => '#475569'];
    $tz = \Filament\Facades\Filament::getTenant()?->timezone ?: 'UTC';
    $fmtDate = fn ($d) => $d ? $d->timezone($tz)->translatedFormat($ar ? 'j F Y، H:i' : 'j F Y, H:i') : '';
@endphp
<style>
.sa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px}
.sa-card{padding:12px 14px;border-radius:12px;border:1px solid rgba(148,163,184,.25);background:rgba(148,163,184,.06)}
.sa-card strong{display:block;font-size:24px;line-height:1.2}
.sa-card span{font-size:12.5px;opacity:.8}
.sa-ring{width:96px;height:96px;border-radius:50%;display:grid;place-items:center;font-size:28px;font-weight:700;border:8px solid #0f766e}
.sa-ring.mid{border-color:#b45309}.sa-ring.low{border-color:#b91c1c}
.sa-cats{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.sa-cats span{font-size:12.5px;padding:4px 10px;border-radius:999px;border:1px solid rgba(148,163,184,.35)}
.sa-issue{border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:12px 14px;margin-top:10px}
.sa-issue summary{cursor:pointer;display:flex;flex-wrap:wrap;gap:8px;align-items:center;min-height:44px;font-size:14.5px}
.sa-chip{font-size:12px;padding:2px 10px;border-radius:999px;color:#fff;font-weight:600}
.sa-issue p{font-size:13.5px;margin:8px 0 0;line-height:1.7}
.sa-issue ul{margin:8px 0 0;padding:0;list-style:none;font-size:13px}
.sa-issue li{padding:4px 0;border-top:1px solid rgba(148,163,184,.18);word-break:break-all}
.sa-form{display:grid;gap:10px;max-width:560px}
.sa-form input[type=text],.sa-form input[type=url]{min-height:44px;border-radius:8px;border:1px solid rgba(148,163,184,.5);padding:8px 12px;background:transparent;width:100%}
.sa-form label{font-size:13.5px;display:flex;gap:8px;align-items:flex-start;line-height:1.6}
.sa-btn{min-height:44px;padding:8px 16px;border-radius:8px;background:#0f766e;color:#fff;font-weight:600;border:0;cursor:pointer}
.sa-btn.ghost{background:transparent;color:inherit;border:1px solid rgba(148,163,184,.5)}
.sa-btn[disabled]{opacity:.5;cursor:default}
.sa-muted{font-size:13px;opacity:.75;line-height:1.6}
.sa-err{font-size:13px;color:#b91c1c}
.sa-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.sa-tabs button{min-height:44px;padding:6px 14px;border-radius:999px;border:1px solid rgba(148,163,184,.4);background:transparent;cursor:pointer}
.sa-tabs button.on{background:#0f766e;color:#fff;border-color:#0f766e}
</style>

@if (! $client)
    <x-filament::section>
        <p class="sa-muted">{{ __('Add or select a client first to audit its website.') }}</p>
    </x-filament::section>
@else
    <x-filament::section>
        <x-slot name="heading">{{ $client->name }} · {{ __('Sites') }}</x-slot>
        @if ($sites->isNotEmpty())
            <div class="sa-tabs" role="tablist">
                @foreach ($sites as $s)
                    <button type="button" role="tab" wire:click="$set('siteId', {{ $s->id }})" class="{{ $site && $site->id === $s->id ? 'on' : '' }}" aria-selected="{{ $site && $site->id === $s->id ? 'true' : 'false' }}"><bdi dir="ltr">{{ $s->host }}</bdi></button>
                @endforeach
            </div>
        @endif
        @if ($canRun)
            <div class="sa-form">
                <input type="url" wire:model="newSiteUrl" placeholder="https://example.com" dir="ltr" aria-label="{{ __('Site address') }}">
                <label><input type="checkbox" wire:model="consent" style="margin-top:5px"> <span>{{ __('I confirm I own this site or have its owner\'s permission to audit it.') }}</span></label>
                @error('newSiteUrl')<div class="sa-err">{{ $message }}</div>@enderror
                <div><button type="button" class="sa-btn" wire:click="addSite">{{ __('Add site') }}</button></div>
                <p class="sa-muted">{{ __('Your plan allows :sites and :pages a month. Used this month: :used.', ['sites' => trans_choice('{1} one site|{2} two sites|[3,10] :n sites|[11,*] :n sites', $limits['sites'], ['n' => $limits['sites']]), 'pages' => trans_choice('{1} one page|{2} two pages|[3,10] :n pages|[11,*] :n pages', $limits['pages'], ['n' => number_format($limits['pages'])]), 'used' => number_format($pagesUsed)]) }}</p>
            </div>
        @elseif ($sites->isEmpty())
            <p class="sa-muted">{{ __('Add the client\'s site to start auditing it.') }} {{ __('Only the workspace owner or an admin can add sites and run audits.') }}</p>
        @endif
    </x-filament::section>

    @if ($site)
        <x-filament::section>
            <x-slot name="heading"><bdi dir="ltr">{{ $site->url }}</bdi></x-slot>
            <x-slot name="description">
                @if ($audit)
                    {{ __('Last audit: :date', ['date' => $fmtDate($audit->finished_at ?? $audit->created_at)]) }}
                @else
                    {{ __('No audit yet.') }}
                @endif
            </x-slot>

            <div @if ($audit && $audit->isOpen()) wire:poll.5s @endif>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                @if ($canRun)
                    @if ($audit && $audit->isOpen())
                        <button type="button" class="sa-btn ghost" wire:click="stopAudit">{{ __('Stop the audit') }}</button>
                    @else
                        <input type="text" wire:model="keyword" placeholder="{{ __('Target keyword (optional)') }}" aria-label="{{ __('Target keyword (optional)') }}" style="min-height:44px;border-radius:8px;border:1px solid rgba(148,163,184,.5);padding:8px 12px;background:transparent;min-width:220px">
                        <button type="button" class="sa-btn" wire:click="runAudit" wire:confirm="{{ __('Run the audit now? It opens up to :pages of this site and counts them against this month\'s limit.', ['pages' => trans_choice('{1} one page|{2} two pages|[3,10] :n pages|[11,*] :n pages', min((int) config('seo_audit.max_pages_per_run'), max(0, $limits['pages'] - $pagesUsed)), ['n' => min((int) config('seo_audit.max_pages_per_run'), max(0, $limits['pages'] - $pagesUsed))])]) }}" @disabled($pagesUsed >= $limits['pages'])>{{ __('Audit now') }}</button>
                    @endif
                @endif
                @if ($audit && $audit->status === 'done')
                    <button type="button" class="sa-btn ghost" wire:click="downloadCsv">{{ __('Export CSV') }}</button>
                @endif
            </div>
            <p class="sa-muted" style="margin-top:8px">{{ __('Uses up to :pages of this month\'s limit (remaining :left).', ['pages' => trans_choice('{1} one page|{2} two pages|[3,10] :n pages|[11,*] :n pages', (int) config('seo_audit.max_pages_per_run'), ['n' => (int) config('seo_audit.max_pages_per_run')]), 'left' => trans_choice('{1} one page|{2} two pages|[3,10] :n pages|[11,*] :n pages', max(0, $limits['pages'] - $pagesUsed), ['n' => number_format(max(0, $limits['pages'] - $pagesUsed))])]) }}</p>

            @if ($pagesUsed >= $limits['pages'])
                <p class="sa-muted" style="margin-top:8px;color:#b45309">{{ __('Audits are paused: you have reached this month\'s page limit.') }} {{ __('It renews on :date.', ['date' => now()->addMonthNoOverflow()->startOfMonth()->translatedFormat('j F')]) }}</p>
            @endif

            @if ($audit)
                @switch($audit->status)
                    @case('queued')
                        <p class="sa-muted" style="margin-top:12px" aria-live="polite">{{ __('The audit is queued.') }}</p>
                        @break
                    @case('running')
                        <p class="sa-muted" style="margin-top:12px" aria-live="polite">{{ __('Auditing: :done of :total.', ['done' => $audit->pages_done, 'total' => trans_choice('{1} one page|{2} two pages|[3,10] :n pages|[11,*] :n pages', $audit->pages_planned, ['n' => $audit->pages_planned])]) }}</p>
                        <div style="height:8px;border-radius:4px;background:rgba(148,163,184,.25);margin-top:6px;max-width:420px"><div style="height:8px;border-radius:4px;background:#0f766e;width:{{ $audit->pages_planned > 0 ? min(100, (int) round($audit->pages_done / $audit->pages_planned * 100)) : 0 }}%"></div></div>
                        @break
                    @case('blocked')
                        <p class="sa-muted" style="margin-top:12px">{!! $bd(__('The site\'s robots.txt blocks the audit. Allow the user agent AdsRangerBot and try again.')) !!}</p>
                        @break
                    @case('site_down')
                        <p class="sa-muted" style="margin-top:12px">{{ __('The site did not respond. Try again in an hour.') }}</p>
                        @break
                    @case('failed')
                        <p class="sa-muted" style="margin-top:12px">{{ __('The audit could not finish. Try again shortly.') }}</p>
                        @break
                    @case('stopped')
                        <p class="sa-muted" style="margin-top:12px">{{ __('The audit was stopped.') }}</p>
                        @break
                    @case('done')
                        @php $band = $audit->score >= 80 ? '' : ($audit->score >= 50 ? 'mid' : 'low'); @endphp
                        <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin-top:14px">
                            <div class="sa-ring {{ $band }}" role="img" aria-label="{{ __('Site health score') }} {{ $audit->score }}" title="{{ __('Site health score: 100 minus the average deduction per page audited (:c points per critical finding, :w per warning). Not a Google ranking.', ['c' => $weights['critical'], 'w' => $weights['warning']]) }}">{{ $audit->score }}</div>
                            <div class="sa-grid" style="flex:1 1 320px">
                                <div class="sa-card"><strong>{{ $audit->critical_count }}</strong><span>{{ __('Critical') }}</span></div>
                                <div class="sa-card"><strong>{{ $audit->warning_count }}</strong><span>{{ __('Warnings') }}</span></div>
                                <div class="sa-card"><strong>{{ $audit->info_count }}</strong><span>{{ __('Info') }}</span></div>
                                <div class="sa-card"><strong>{{ $audit->pages_done }}</strong><span>{{ __('Pages audited') }}</span></div>
                            </div>
                        </div>
                        <p class="sa-muted" style="margin-top:8px">{{ __('Site health score: 100 minus the average deduction per page audited (:c points per critical finding, :w per warning). Not a Google ranking.', ['c' => $weights['critical'], 'w' => $weights['warning']]) }}@if ($audit->critical_count > 0) · {{ __('A critical finding needs fixing.') }}@endif · {{ $fmtDate($audit->finished_at) }}</p>
                        <div class="sa-cats">
                            @foreach (\App\Support\Seo\Audit\Catalog::CATEGORIES as $key => [$catAr, $catEn])
                                @continue($key === 'speed') {{-- UX-250: no speed rule until A2 --}}
                                <span>{!! $bd($ar ? $catAr : $catEn) !!}: {{ $categories[$key] }}</span>
                            @endforeach
                        </div>

                        @if ($groups === [])
                            <p class="sa-muted" style="margin-top:14px">{{ __('No issues found in this audit.') }}</p>
                        @else
                            <h3 style="font-size:15px;margin:18px 0 0">{{ __('Issues') }}</h3>
                            @foreach ($groups as $g)
                                <details class="sa-issue">
                                    <summary>
                                        <span class="sa-chip" style="background:{{ $levelColor[$g['level']] }}">{{ \App\Support\Seo\Audit\Catalog::levelLabel($g['level'], $ar) }}</span>
                                        <strong>{!! $bd($g['title']) !!}</strong>
                                        <span class="sa-muted">· {{ trans_choice('{1} :n page|[2,*] :n pages', $g['count'], ['n' => $g['count']]) }} · <bdi dir="ltr">{{ $g['code'] }}</bdi></span>
                                    </summary>
                                    <p><strong>{{ __('Why it matters') }}:</strong> {!! $bd($g['why']) !!}</p>
                                    <p><strong>{{ __('How to fix it') }}:</strong> {!! $bd($g['fix']) !!}</p>
                                    <ul>
                                        @foreach ($g['pages'] as $pg)
                                            <li><bdi dir="ltr"><a href="{{ $pg['url'] }}" target="_blank" rel="noopener">{{ $pg['url'] }}</a></bdi>@if ($pg['detail']) · <bdi dir="ltr">{{ $pg['detail'] }}</bdi>@endif</li>
                                        @endforeach
                                        @if ($g['count'] > count($g['pages']))
                                            <li class="sa-muted">{{ __('… and :n more in the CSV file.', ['n' => $g['count'] - count($g['pages'])]) }}</li>
                                        @endif
                                    </ul>
                                </details>
                            @endforeach
                        @endif
                        @break
                @endswitch
            @endif
            </div>
        </x-filament::section>
    @endif
@endif
</x-filament-panels::page>
