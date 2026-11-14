<x-filament-panels::page>
<style>
.sal-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}
.sal-card{padding:14px;border-radius:12px;border:1px solid rgba(30,41,59,.22);background:rgba(15,23,42,.03)}
.dark .sal-card{border-color:rgba(148,163,184,.22);background:rgba(30,41,59,.45)}
.sal-in{width:100%;min-height:44px;border-radius:8px;border:1px solid rgba(100,116,139,.45);padding:8px 12px;background:transparent;color:inherit}
.sal-btn{min-height:44px;padding:8px 16px;border-radius:8px;background:#0d9488;color:#fff;font-weight:600;border:0;cursor:pointer}
.sal-btn.ghost{background:transparent;color:inherit;border:1px solid rgba(100,116,139,.45)}
.sal-muted{font-size:13px;opacity:.78;line-height:1.6}
.sal-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
.sal-table th,.sal-table td{padding:9px 8px;border-bottom:1px solid rgba(148,163,184,.2);text-align:start}
.sal-chip{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;background:rgba(20,184,166,.14);color:#0d9488;font-weight:600}
.dark .sal-chip{color:#14b8a6}
</style>

@if (! $client)
    <x-filament::section>
        <p class="sal-muted">{{ __('Add or select a client first to manage rank tracking, silos, backlinks, and local branches.') }}</p>
    </x-filament::section>
@else
    <x-filament::section>
        <x-slot name="heading">{{ __('Client') }}</x-slot>
        <select wire:model.live="clientId" class="sal-in" style="max-width:320px" aria-label="{{ __('Client') }}">
            @foreach ($clients as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Rank tracker & keyword cannibalization detector') }}</x-slot>
        @if ($canManage)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px">
                <label class="sal-muted">{{ __('Keyword') }}
                    <input type="text" wire:model="newKeyword" class="sal-in">
                </label>
                <label class="sal-muted">{{ __('Language') }}
                    <select wire:model="newLocale" class="sal-in">
                        <option value="ar">AR</option>
                        <option value="en">EN</option>
                    </select>
                </label>
                <label class="sal-muted">{{ __('Market code') }}
                    <input type="text" wire:model="newLocationCode" class="sal-in" dir="ltr" maxlength="8">
                </label>
                <label class="sal-muted">{{ __('Device') }}
                    <select wire:model="newDevice" class="sal-in">
                        <option value="mobile">{{ __('Mobile') }}</option>
                        <option value="desktop">{{ __('Desktop') }}</option>
                    </select>
                </label>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                <button type="button" class="sal-btn" wire:click="addTrackedKeyword">{{ __('Track keyword') }}</button>
                <button type="button" class="sal-btn ghost" wire:click="syncRanksFromGsc">{{ __('Refresh positions from Search Console') }}</button>
            </div>
        @endif

        @if ($keywords->isNotEmpty())
            <div style="overflow-x:auto">
                <table class="sal-table">
                    <thead>
                        <tr>
                            <th>{{ __('Keyword') }}</th>
                            <th>{{ __('Market code') }}</th>
                            <th>{{ __('Device') }}</th>
                            <th>{{ __('Latest position') }}</th>
                            <th>{{ __('Ranking URL') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keywords as $kw)
                            @php $snap = $kw->snapshots->first(); @endphp
                            <tr>
                                <td>{{ $kw->keyword }}</td>
                                <td><bdi dir="ltr">{{ $kw->location_code }} · {{ strtoupper($kw->locale) }}</bdi></td>
                                <td><bdi dir="ltr">{{ $kw->device }}</bdi></td>
                                <td><bdi dir="ltr">{{ $snap && $snap->position !== null ? '#'.$snap->position : '—' }}</bdi></td>
                                <td><bdi dir="ltr">{{ $snap?->ranking_url ?: '—' }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h3 style="font-weight:600;margin-top:16px">{{ __('Cannibalization alerts (multiple URLs splitting one query)') }}</h3>
        @if ($cannibalization === [])
            <p class="sal-muted">{{ __('No URL cannibalization detected for current queries.') }}</p>
        @else
            <ul class="sal-muted">
                @foreach ($cannibalization as $item)
                    <li>
                        <strong>{{ $item['query'] }}</strong> — <span class="sal-chip"><bdi dir="ltr">{{ $item['reason'] }}</bdi></span>
                        ({{ count($item['competing_urls']) }} {{ __('URLs') }}:
                        <bdi dir="ltr">{{ implode(', ', array_map(fn ($u) => $u['url'].' ('.$u['share_pct'].'%)', $item['competing_urls'])) }}</bdi>)
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Topical cluster & pillar-spoke planner') }}</x-slot>
        @if ($canManage)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
                <label class="sal-muted">{{ __('Pillar title') }}
                    <input type="text" wire:model="pillarTitle" class="sal-in">
                </label>
                <label class="sal-muted">{{ __('Pillar URL') }}
                    <input type="text" wire:model="pillarUrl" class="sal-in" dir="ltr" placeholder="https://example.sa/ar/guide">
                </label>
            </div>
            <label class="sal-muted" style="display:block;margin-top:10px">{{ __('Spoke articles (one per line: title | url | ar/en | planned/live)') }}
                <textarea wire:model="spokesLines" rows="3" class="sal-in"></textarea>
            </label>
            <label class="sal-muted" style="display:block;margin-top:10px">{{ __('Internal anchor phrases (one per line)') }}
                <textarea wire:model="anchorLines" rows="2" class="sal-in"></textarea>
            </label>
            <div style="margin-top:10px">
                <button type="button" class="sal-btn" wire:click="saveTopicCluster">{{ __('Save topical cluster') }}</button>
            </div>
        @endif

        @if ($clusters->isNotEmpty())
            <div class="sal-grid" style="margin-top:14px">
                @foreach ($clusters as $cl)
                    <div class="sal-card">
                        <strong>{{ $cl->pillar_title }}</strong>
                        <p class="sal-muted"><bdi dir="ltr">{{ $cl->pillar_url }}</bdi></p>
                        <p class="sal-muted">{{ __('Spoke count') }}: <bdi dir="ltr">{{ count((array) $cl->spokes) }}</bdi> · <span class="sal-chip"><bdi dir="ltr">{{ $cl->hreflang_pair_status }}</bdi></span></p>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Backlinks & Google Disavow (.txt)') }}</x-slot>
        @if ($canManage)
            <label class="sal-muted" style="display:block">{{ __('Import Search Console external links CSV (source_url,target_url,anchor_text,link_status)') }}
                <textarea wire:model="backlinksCsv" rows="3" class="sal-in" dir="ltr"></textarea>
            </label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                <button type="button" class="sal-btn" wire:click="importBacklinksCsv">{{ __('Import links CSV') }}</button>
                <button type="button" class="sal-btn ghost" wire:click="downloadDisavowTxt">{{ __('Export Google Disavow .txt') }}</button>
            </div>
        @endif

        @if ($backlinks->isNotEmpty())
            <div style="overflow-x:auto">
                <table class="sal-table">
                    <thead>
                        <tr>
                            <th>{{ __('Referring domain') }}</th>
                            <th>{{ __('Target URL') }}</th>
                            <th>{{ __('Anchor text') }}</th>
                            <th>{{ __('Disavow flag') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backlinks as $bl)
                            <tr>
                                <td><bdi dir="ltr">{{ $bl->source_domain }}</bdi></td>
                                <td><bdi dir="ltr">{{ $bl->target_url }}</bdi></td>
                                <td>{{ $bl->anchor_text ?: '—' }}</td>
                                <td>
                                    @if ($canManage)
                                        <button type="button" class="sal-btn ghost" wire:click="toggleDisavowFlag({{ $bl->id }})">
                                            {{ $bl->is_flagged_disavow ? __('Flagged') : __('Mark disavow') }}
                                        </button>
                                    @else
                                        {{ $bl->is_flagged_disavow ? __('Flagged') : '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Local SEO & Google Business Profile branches') }}</x-slot>
        @if ($canManage)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">
                <label class="sal-muted">{{ __('Branch name (AR)') }}
                    <input type="text" wire:model="branchNameAr" class="sal-in" dir="rtl">
                </label>
                <label class="sal-muted">{{ __('Branch name (EN)') }}
                    <input type="text" wire:model="branchNameEn" class="sal-in" dir="ltr">
                </label>
                <label class="sal-muted">{{ __('Phone (E.164)') }}
                    <input type="text" wire:model="branchPhone" class="sal-in" dir="ltr" placeholder="+966112345678">
                </label>
                <label class="sal-muted">{{ __('Daily hours (24h)') }}
                    <input type="text" wire:model="branchWeekdayHours" class="sal-in" dir="ltr" placeholder="09:00-22:00">
                </label>
                <label class="sal-muted">{{ __('Address (AR)') }}
                    <input type="text" wire:model="branchAddressAr" class="sal-in" dir="rtl">
                </label>
                <label class="sal-muted">{{ __('Address (EN)') }}
                    <input type="text" wire:model="branchAddressEn" class="sal-in" dir="ltr">
                </label>
            </div>
            <div style="margin-top:10px">
                <button type="button" class="sal-btn" wire:click="saveLocalBranch">{{ __('Save branch & check NAP') }}</button>
            </div>
        @endif

        @if ($locations->isNotEmpty())
            <div class="sal-grid" style="margin-top:14px">
                @foreach ($locations as $loc)
                    <div class="sal-card">
                        <strong>{{ $loc->name_ar }} · <bdi dir="ltr">{{ $loc->name_en }}</bdi></strong>
                        <p class="sal-muted"><bdi dir="ltr">{{ $loc->phone_e164 }}</bdi></p>
                        @if (! empty($loc->nap_issues))
                            <p class="sal-muted">{{ __('NAP issues') }}: <bdi dir="ltr">{{ count($loc->nap_issues) }}</bdi></p>
                        @else
                            <p class="sal-muted">{{ __('NAP check clean') }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
@endif
</x-filament-panels::page>
