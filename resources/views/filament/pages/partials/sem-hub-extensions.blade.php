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
</div>
