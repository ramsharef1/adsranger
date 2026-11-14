<div style="display:grid;gap:16px;margin-top:16px">
    <x-filament::section>
        <x-slot name="heading">{{ __('Search term n-gram bleed miner (1/2/3-word stems)') }}</x-slot>
        @if ($canManageStores)
            <div style="display:grid;gap:8px">
                <textarea wire:model="searchTermCsv" rows="3" dir="ltr" class="pah-input" placeholder="search_term,campaign_name,network,impressions,clicks,spend,conversions,revenue,stat_date"></textarea>
                <div>
                    <button type="button" wire:click="importSearchTermsCsv" class="pah-btn pah-btn-solid">{{ __('Import search terms CSV') }}</button>
                </div>
            </div>
        @endif
        @if (! empty($ngramBleed))
            <div style="margin-top:12px;display:grid;gap:6px;font-size:13px">
                @foreach (array_slice($ngramBleed, 0, 8) as $stem)
                    <div class="pah-row">
                        <span><strong>{{ $stem['ngram'] }}</strong> (<bdi dir="ltr">{{ $stem['words'] }}w · {{ $stem['clicks'] }} clicks</bdi>)</span>
                        @if (! $client->hide_spend)
                            <strong dir="ltr">{{ \App\Support\ResultResolver::money($stem['spend'], $currency) }}</strong>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p style="font-size:13px;opacity:.75;margin-top:8px">{{ __('No zero-conversion search stems above threshold yet.') }}</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Profit-labeled shopping feeds (/feeds/{token}.xml)') }}</x-slot>
        @if ($canManageStores)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;font-size:13px">
                <select wire:model="feedChannel" class="pah-input">
                    <option value="google_merchant">Google Merchant</option>
                    <option value="meta_catalog">Meta Catalog</option>
                    <option value="tiktok_catalog">TikTok Catalog</option>
                    <option value="snap_catalog">Snapchat Catalog</option>
                </select>
                <input type="text" wire:model="feedBrandAr" placeholder="{{ __('Brand prefix (AR)') }}" class="pah-input" dir="rtl">
                <input type="text" wire:model="feedBrandEn" placeholder="{{ __('Brand prefix (EN)') }}" class="pah-input" dir="ltr">
            </div>
            <div style="margin-top:8px">
                <button type="button" wire:click="createShoppingFeed" class="pah-btn pah-btn-solid">{{ __('Generate feed link') }}</button>
            </div>
        @endif
        @if ($productFeeds->isNotEmpty())
            <div style="margin-top:10px;display:grid;gap:6px;font-size:13px">
                @foreach ($productFeeds as $pf)
                    <div class="pah-row">
                        <span><bdi dir="ltr">{{ $pf->channel }}</bdi></span>
                        <code dir="ltr" style="font-size:12px">{{ url('/feeds/'.$pf->feed_token.'.xml') }}</code>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Deterministic root-cause performance investigator (14d vs prior 14d)') }}</x-slot>
        @if (! empty($investigator))
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;font-size:13px">
                <div class="pah-row"><span>{{ __('ROAS delta') }}</span><strong dir="ltr">{{ $investigator['deltas']['roas_delta'] }}</strong></div>
                <div class="pah-row"><span>{{ __('POAS delta') }}</span><strong dir="ltr">{{ $investigator['deltas']['poas_delta'] }}</strong></div>
                <div class="pah-row"><span>{{ __('CPC impact on ROAS') }}</span><strong dir="ltr">{{ $investigator['roas_waterfall']['cpc_effect'] }}</strong></div>
                <div class="pah-row"><span>{{ __('CVR impact on ROAS') }}</span><strong dir="ltr">{{ $investigator['roas_waterfall']['cvr_effect'] }}</strong></div>
                <div class="pah-row"><span>{{ __('AOV impact on ROAS') }}</span><strong dir="ltr">{{ $investigator['roas_waterfall']['aov_effect'] }}</strong></div>
                <div class="pah-row"><span>{{ __('Primary driver') }}</span><strong dir="ltr">{{ $investigator['primary_root_cause'] }}</strong></div>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Cross-channel rule engine (dry-run & approval queue, max 20% delta)') }}</x-slot>
        @if ($canManageStores)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:8px;font-size:13px">
                <input type="text" wire:model="ruleName" placeholder="{{ __('Strategy name') }}" class="pah-input">
                <select wire:model="ruleField" class="pah-input" dir="ltr">
                    <option value="poas">poas</option>
                    <option value="roas">roas</option>
                    <option value="cpa">cpa</option>
                    <option value="cod_return_rate_pct">cod_return_rate_pct</option>
                    <option value="min_stock_qty">min_stock_qty</option>
                    <option value="oos_sku_count">oos_sku_count</option>
                </select>
                <select wire:model="ruleOp" class="pah-input" dir="ltr">
                    <option value="<">&lt;</option>
                    <option value="<=">&lt;=</option>
                    <option value=">">&gt;</option>
                    <option value=">=">&gt;=</option>
                </select>
                <input type="number" step="0.1" wire:model="ruleTarget" class="pah-input" dir="ltr">
                <input type="number" step="1" min="1" max="20" wire:model="ruleMaxDeltaPct" class="pah-input" dir="ltr" placeholder="<=20%">
                <select wire:model="ruleMode" class="pah-input">
                    <option value="dry_run">{{ __('Dry run') }}</option>
                    <option value="approval_queue">{{ __('Approval queue') }}</option>
                </select>
            </div>
            <div style="margin-top:8px">
                <button type="button" wire:click="createAutomationStrategy" class="pah-btn pah-btn-solid">{{ __('Save strategy') }}</button>
            </div>
        @endif
        @if (isset($automationStrategies) && $automationStrategies->isNotEmpty())
            <div style="margin-top:10px;display:grid;gap:6px;font-size:13px">
                @foreach ($automationStrategies as $strat)
                    @php $latestRun = $strat->runs->first(); @endphp
                    <div class="pah-row">
                        <span>
                            <strong>{{ $strat->name }}</strong>
                            (<bdi dir="ltr">{{ $strat->execution_mode }} · max {{ $strat->max_budget_delta_pct }}%</bdi>)
                            @if ($latestRun)
                                · <bdi dir="ltr">{{ $latestRun->status }}</bdi>
                            @endif
                        </span>
                        @if ($canManageStores)
                            <button type="button" wire:click="runAutomationStrategy({{ $strat->id }})" class="pah-btn">{{ __('Evaluate now') }}</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Customer cohort LTV & CAC payback curves') }}</x-slot>
        @if ($canManageStores)
            <div style="display:grid;gap:8px">
                <textarea wire:model="cohortCsv" rows="3" dir="ltr" class="pah-input" placeholder="cohort_month,acquisition_key,customers_count,cac_amount,d30_ltv,d60_ltv,d90_ltv,d180_ltv"></textarea>
                <div>
                    <button type="button" wire:click="importCohortsCsv" class="pah-btn pah-btn-solid">{{ __('Import cohort CSV') }}</button>
                </div>
            </div>
        @endif
        @if (! empty($cohortSummary))
            <div style="margin-top:10px;display:grid;gap:6px;font-size:13px">
                @foreach ($cohortSummary as $ch)
                    <div class="pah-row">
                        <span><bdi dir="ltr">{{ $ch['cohort_month'] }} · {{ $ch['acquisition_key'] }}</bdi> ({{ $ch['customers_count'] }})</span>
                        <span>
                            @if (! $client->hide_spend)
                                <bdi dir="ltr">CAC {{ \App\Support\ResultResolver::money($ch['cac_amount'], $currency) }} · D90 {{ \App\Support\ResultResolver::money($ch['d90_ltv'], $currency) }}</bdi> ·
                            @endif
                            <bdi dir="ltr">{{ $ch['payback_days'] !== null ? $ch['payback_days'].'d' : '—' }}</bdi>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Ad landing URL & pixel watchdog (SafeFetcher)') }}</x-slot>
        @if ($canManageStores)
            <div style="display:grid;grid-template-columns:1fr auto;gap:8px">
                <input type="url" wire:model="watchedUrlInput" dir="ltr" placeholder="https://example.com/offer" class="pah-input">
                <button type="button" wire:click="addAndCheckWatchedUrl" class="pah-btn pah-btn-solid">{{ __('Watch & check URL') }}</button>
            </div>
        @endif
        @if (isset($watchedUrls) && $watchedUrls->isNotEmpty())
            <div style="margin-top:10px;display:grid;gap:6px;font-size:13px">
                @foreach ($watchedUrls as $wu)
                    <div class="pah-row">
                        <span>
                            <bdi dir="ltr">{{ $wu->url }}</bdi> ·
                            <bdi dir="ltr">HTTP {{ $wu->last_http_status ?? '—' }} · {{ $wu->redirect_hops }} hops</bdi>
                            @if ($wu->has_noindex)
                                · <strong>{{ __('noindex detected') }}</strong>
                            @endif
                            @if ($wu->broken_whatsapp_link)
                                · <strong>{{ __('Broken WhatsApp link') }}</strong>
                            @endif
                            @if (! empty($wu->missing_pixels))
                                · <bdi dir="ltr">{{ implode(', ', $wu->missing_pixels) }}</bdi>
                            @endif
                        </span>
                        @if ($canManageStores)
                            <button type="button" wire:click="recheckWatchedUrl({{ $wu->id }})" class="pah-btn">{{ __('Recheck') }}</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</div>
