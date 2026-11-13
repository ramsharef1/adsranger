@php
    // UX-258: people see labels, never raw codes.
    $stateLabels = ['simulated' => __('Simulated · not sent'), 'queued_offline' => __('Waiting for connection')];
    $channelLabels = ['ctwa' => __('WhatsApp chat'), 'pixel_relay' => __('Website'), 'pos_offline' => __('In-store sales')];
    $eventLabels = ['Purchase' => __('Purchase'), 'Lead' => __('Lead'), 'AddToCart' => __('Add to cart'), 'InitiateCheckout' => __('Checkout started')];
    $models = [
        'first_touch' => __('First touch'),
        'last_touch' => __('Last touch'),
        'linear' => __('Even split'),
        'time_decay' => __('Recent weighs more'),
        'position_based' => __('First + last weigh more'),
    ];
    // UX-260: plain names, each with its rule.
    $skuGroups = [
        'hero' => [__('Winner'), __('Winner: high ad spend and a profit.')],
        'sidekick' => [__('Support'), __('Support: lower ad spend and a profit.')],
        'villain' => [__('Loser'), __('Loser: high ad spend and no profit.')],
        'zombie' => [__('Idle'), __('Idle: lower ad spend and no profit or no orders.')],
    ];
    $cur = (string) ($currency ?? '');
    $money = fn ($v) => number_format((float) $v, 2).($cur !== '' ? ' '.$cur : '');
    $costFields = [
        'cogsPct' => [__('Cost of goods (COGS) %'), '0.5'],
        'shippingFee' => [__('Shipping per order'), '0.5'],
        'returnShippingFee' => [__('Return shipping per returned order'), '0.5'],
        'codSharePct' => [__('COD share %'), '0.5'],
        'codReturnRatePct' => [__('COD return rate %'), '0.5'],
        'bnplSharePct' => [__('Pay-later share % (Tabby / Tamara)'), '0.5'],
        'bnplFeePct' => [__('Pay-later fee %'), '0.25'],
        'gatewayFeePct' => [__('Card gateway fee %'), '0.25'],
    ];
    $periodFields = [
        'periodRevenue' => [__('Revenue'), '0.01'],
        'periodSpend' => [__('Ad spend'), '0.01'],
        'periodOrders' => [__('Orders'), '1'],
    ];
@endphp
<x-filament-panels::page>
    {{-- UX-262: colours from the theme (light + dark), not inline light-only values. --}}
    <style>
        .pah-banner{padding:14px 16px;border-radius:12px;border:1px solid rgba(20,184,166,.35);background:rgba(15,118,110,.10);font-size:13.5px;line-height:1.65}
        .pah-accent{color:#0f766e}
        .dark .pah-accent{color:#5eead4}
        .pah-input{width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent;color:inherit}
        .pah-btn{min-height:44px;padding:0 16px;border-radius:8px;font-weight:600;cursor:pointer}
        .pah-btn-ghost{border:1px solid currentColor;background:transparent}
        .pah-btn-solid{border:0;background:#0f766e;color:#fff}
        .pah-row{display:flex;justify-content:space-between;gap:10px;border-top:1px solid rgba(148,163,184,.2);padding-top:6px}
        .pah-muted{opacity:.75;font-size:12.5px}
        .pah-tile{padding:8px;border-radius:8px;background:rgba(148,163,184,.12)}
    </style>

    @if (! $client)
        <x-filament::section>
            <p style="font-size:14px;opacity:.85">{{ __('Select or create a client workspace first to configure unit economics and attribution.') }}</p>
        </x-filament::section>
    @else
        {{-- UX-259 --}}
        <div class="pah-banner">
            <strong class="pah-accent">{{ $client->name }}</strong> ·
            <span>{{ __('Nothing is sent to the ad platforms yet: events are only saved here until you connect a conversions account.') }}</span>
        </div>

        {{-- #193 Unit economics + estimate (UX-255, UX-256) --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:14px">
            <x-filament::section>
                <x-slot name="heading">{!! __('Unit economics and a <bdi>POAS</bdi> estimate') !!}</x-slot>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">
                    @foreach ($costFields as $prop => [$label, $step])
                        <label><span>{{ $label }}</span>
                            <input type="number" min="0" step="{{ $step }}" wire:model="{{ $prop }}" dir="ltr" class="pah-input">
                        </label>
                    @endforeach
                </div>
                <p class="pah-muted" style="margin:12px 0 6px">{{ __('One period\'s figures (for example last month)') }}</p>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;font-size:13px">
                    @foreach ($periodFields as $prop => [$label, $step])
                        <label><span>{{ $label }}</span>
                            <input type="number" min="0" step="{{ $step }}" wire:model="{{ $prop }}" dir="ltr" class="pah-input">
                        </label>
                    @endforeach
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px">
                    <button type="button" wire:click="previewCodPoas" class="pah-btn pah-btn-ghost pah-accent">{{ __('Calculate') }}</button>
                    <button type="button" wire:click="saveUnitEconomics" class="pah-btn pah-btn-solid">{{ __('Save costs') }}</button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Profit estimate') }}</x-slot>
                @if ($poas === [])
                    <p style="font-size:13.5px;margin:0">{{ __('Enter your costs and one period\'s revenue, spend and orders to calculate profit.') }}</p>
                @else
                    <div style="display:grid;gap:8px;font-size:13.5px">
                        <div class="pah-row" style="border-top:0;padding-top:0">
                            <span>{{ __('Revenue kept after COD returns') }}</span>
                            <strong dir="ltr">{{ $money($poas['realized_revenue'] ?? 0) }}</strong>
                        </div>
                        <div class="pah-row">
                            <span>{{ __('Net profit after ad spend') }}</span>
                            <strong dir="ltr" class="pah-accent">{{ $money($poas['net_profit'] ?? 0) }}</strong>
                        </div>
                        <div class="pah-row">
                            <span>{!! __('Estimated <bdi>POAS</bdi> (from your inputs)') !!}</span>
                            <strong dir="ltr">{{ number_format($poas['poas'] ?? 0, 2) }}x</strong>
                        </div>
                        <div class="pah-row">
                            <span>{!! __('Break-even <bdi>ROAS</bdi>') !!}</span>
                            <strong dir="ltr">{{ isset($poas['break_even_roas']) && $poas['break_even_roas'] !== null ? number_format($poas['break_even_roas'], 2).'x' : '—' }}</strong>
                        </div>
                        <p class="pah-muted" style="margin:4px 0 0">{{ __('An estimate from the rates you entered, not from store data.') }}</p>
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- #194 WhatsApp click IDs + #196 / #197 products --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:14px">
            <x-filament::section>
                <x-slot name="heading">{!! __('<bdi>WhatsApp</bdi> click events (<bdi>ctwa_clid</bdi>)') !!}</x-slot>
                <div style="display:grid;gap:10px;font-size:13px">
                    <input type="text" wire:model="ctwaClid" placeholder="ctwa_clid" dir="ltr" class="pah-input">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <input type="number" min="0" step="0.01" wire:model="ctwaValue" aria-label="{{ __('Value') }}" placeholder="{{ __('Value') }}" dir="ltr" class="pah-input">
                        <button type="button" wire:click="recordCtwaEvent" class="pah-btn pah-btn-solid">{{ __('Save event') }}</button>
                    </div>
                    @foreach ($capiEvents as $ev)
                        <div class="pah-row">
                            <span>{{ $eventLabels[$ev->event_name] ?? $ev->event_name }} · {{ $channelLabels[$ev->channel] ?? $ev->channel }}</span>
                            <span class="pah-accent" style="font-weight:600">{{ $stateLabels[$ev->delivery_state] ?? $ev->delivery_state }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{!! __('Product profit (<bdi>SKU</bdi>) and out-of-stock spend') !!}</x-slot>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center;font-size:12.5px;margin-bottom:10px">
                    @foreach ($skuGroups as $key => [$label, $rule])
                        <div class="pah-tile" title="{{ $rule }}" tabindex="0" aria-label="{{ $rule }}"><strong>{{ $skuMatrix['counts'][$key] ?? 0 }}</strong><div>{{ $label }}</div></div>
                    @endforeach
                </div>
                <div style="font-size:13px;display:grid;gap:6px">
                    <div class="pah-row" style="border-top:0;padding-top:0">
                        <span>{{ __('Ad spend on out-of-stock products') }}</span>
                        <strong dir="ltr">{{ $money($oosBleed['wasted_spend'] ?? 0) }}</strong>
                    </div>
                    <div class="pah-row">
                        <span>{{ __('Leaked or high-bleed promo codes') }}</span>
                        <strong dir="ltr">{{ count($promoBleed['flagged_codes'] ?? []) }}</strong>
                    </div>
                    <div class="pah-row">
                        <span>{{ __('Co-purchased bundle pairs') }}</span>
                        <strong dir="ltr">{{ count($bundles ?? []) }}</strong>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- #204 attribution model · #205 custom KPI --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:14px">
            <x-filament::section>
                <x-slot name="heading">{{ __('Attribution model and buyer survey') }}</x-slot>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
                    @foreach ($models as $m => $label)
                        <button type="button" wire:click="setMtaModel('{{ $m }}')" aria-pressed="{{ $activeMtaModel === $m ? 'true' : 'false' }}"
                            class="pah-btn {{ $activeMtaModel === $m ? 'pah-btn-solid' : 'pah-btn-ghost pah-accent' }}" style="font-size:12.5px;padding:0 12px">{{ $label }}</button>
                    @endforeach
                </div>
                <div style="font-size:13px">
                    <span>{{ __('Survey responses collected') }}:</span>
                    <strong dir="ltr">{{ $hdyhau['total_responses'] ?? 0 }}</strong>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Custom KPI formula') }}</x-slot>
                <div style="display:grid;gap:8px;font-size:13px">
                    <input type="text" wire:model="kpiName" placeholder="{{ __('KPI name') }}" class="pah-input">
                    <input type="text" wire:model="kpiFormula" dir="ltr" aria-label="{{ __('Formula') }}" class="pah-input">
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <button type="button" wire:click="previewFormula" class="pah-btn pah-btn-ghost pah-accent">{{ __('Preview formula') }}</button>
                        <button type="button" wire:click="saveCustomKpi" class="pah-btn pah-btn-solid">{{ __('Save KPI') }}</button>
                    </div>
                    @if ($formulaPreviewError)
                        <div role="alert" style="font-size:12.5px">{{ $formulaPreviewError }}</div>
                    @elseif ($formulaPreviewValue !== null)
                        <div dir="ltr" class="pah-accent" style="font-weight:600">= {{ number_format($formulaPreviewValue, 2) }}</div>
                    @endif
                    @foreach ($customKpis as $kpi)
                        <div class="pah-row">
                            <span>{{ $kpi['name'] }}</span>
                            <strong dir="ltr">{{ $kpi['formatted'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
