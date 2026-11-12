<x-filament-panels::page>
    @if (! $client)
        <x-filament::section>
            <p style="font-size:14px;opacity:.85">{{ __('Select or create a client workspace first to configure unit economics and attribution.') }}</p>
        </x-filament::section>
    @else
        {{-- D-229 Honesty Banner (Brand Teal + Slate, zero orange) --}}
        <div style="padding:14px 16px;border-radius:12px;border:1px solid rgba(20,184,166,.35);background:rgba(15,118,110,.10);color:#0f172a;font-size:13.5px;line-height:1.65">
            <strong style="color:#0f766e">{{ $client->name }}</strong> ·
            <span>{!! __('Outbound <bdi>CAPI</bdi> and <bdi>RFM</bdi> relays stay in honest <bdi>simulated</bdi> or <bdi>queued_offline</bdi> mode until live platform conversion tokens are connected.') !!}</span>
        </div>

        {{-- #193 COD-Adjusted POAS & Net Margin Engine --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:14px">
            <x-filament::section>
                <x-slot name="heading">{!! __('COD-Adjusted <bdi>POAS</bdi> & Unit Economics') !!}</x-slot>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">
                    <label>
                        <span>{!! __('<bdi>COGS</bdi> %') !!}</span>
                        <input type="number" step="0.5" wire:model="cogsPct" style="width:100%;min-height:44px;padding:8px;border-radius:8px;border:1px solid rgba(148,163,184,.4)">
                    </label>
                    <label>
                        <span>{{ __('COD share %') }}</span>
                        <input type="number" step="0.5" wire:model="codSharePct" style="width:100%;min-height:44px;padding:8px;border-radius:8px;border:1px solid rgba(148,163,184,.4)">
                    </label>
                    <label>
                        <span>{{ __('COD return rate %') }}</span>
                        <input type="number" step="0.5" wire:model="codReturnRatePct" style="width:100%;min-height:44px;padding:8px;border-radius:8px;border:1px solid rgba(148,163,184,.4)">
                    </label>
                    <label>
                        <span>{!! __('<bdi>Tabby</bdi> / <bdi>Tamara</bdi> fee %') !!}</span>
                        <input type="number" step="0.25" wire:model="bnplFeePct" style="width:100%;min-height:44px;padding:8px;border-radius:8px;border:1px solid rgba(148,163,184,.4)">
                    </label>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px">
                    <button type="button" wire:click="previewCodPoas" style="min-height:44px;padding:0 16px;border-radius:8px;border:1px solid #0f766e;background:transparent;color:#0f766e;font-weight:600;cursor:pointer">
                        {!! __('Recalculate <bdi>POAS</bdi>') !!}
                    </button>
                    <button type="button" wire:click="saveUnitEconomics" style="min-height:44px;padding:0 16px;border-radius:8px;border:0;background:#0f766e;color:#ffffff;font-weight:600;cursor:pointer">
                        {{ __('Save unit economics') }}
                    </button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Net Margin & Break-Even Snapshot') }}</x-slot>
                <div style="display:grid;gap:8px;font-size:13.5px">
                    <div style="display:flex;justify-content:space-between">
                        <span>{{ __('Realized revenue after COD returns') }}</span>
                        <strong dir="ltr">{{ number_format($poas['realized_revenue'] ?? 0, 2) }} {{ $poas['currency'] ?? 'USD' }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span>{{ __('Net profit after ad spend') }}</span>
                        <strong dir="ltr" style="color:#0f766e">{{ number_format($poas['net_profit'] ?? 0, 2) }} {{ $poas['currency'] ?? 'USD' }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span>{!! __('Real <bdi>POAS</bdi> (Net Profit / Spend)') !!}</span>
                        <strong dir="ltr">{{ number_format($poas['poas'] ?? 0, 2) }}x</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span>{!! __('Break-even <bdi>ROAS</bdi>') !!}</span>
                        <strong dir="ltr">{{ isset($poas['break_even_roas']) && $poas['break_even_roas'] !== null ? number_format($poas['break_even_roas'], 2).'x' : '—' }}</strong>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- #194 Click-to-WhatsApp (ctwa_clid) CAPI Bridge & #196/#197 SKU Matrix --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:14px">
            <x-filament::section>
                <x-slot name="heading">{!! __('WhatsApp <bdi>ctwa_clid</bdi> & <bdi>CAPI</bdi> Bridge') !!}</x-slot>
                <div style="display:grid;gap:10px;font-size:13px">
                    <input type="text" wire:model="ctwaClid" placeholder="ctwa_clid" dir="ltr" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4)">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <input type="number" step="1" wire:model="ctwaValue" placeholder="0.00" dir="ltr" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4)">
                        <button type="button" wire:click="recordCtwaEvent" style="min-height:44px;padding:0 14px;border-radius:8px;border:0;background:#0f766e;color:#ffffff;font-weight:600;cursor:pointer">
                            {!! __('Queue <bdi>CAPI</bdi> event') !!}
                        </button>
                    </div>
                    @foreach ($capiEvents as $ev)
                        <div style="display:flex;justify-content:space-between;border-top:1px solid rgba(148,163,184,.2);padding-top:6px">
                            <span dir="ltr">{{ $ev->event_name }} · {{ $ev->channel }}</span>
                            <span dir="ltr" style="color:#0f766e;font-weight:600">{{ $ev->delivery_state }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{!! __('<bdi>SKU</bdi> Profit Matrix & Out-of-Stock Bleed Guard') !!}</x-slot>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center;font-size:12.5px;margin-bottom:10px">
                    <div style="padding:8px;border-radius:8px;background:rgba(15,118,110,.12)"><strong>{{ $skuMatrix['counts']['hero'] ?? 0 }}</strong><div>{{ __('Hero') }}</div></div>
                    <div style="padding:8px;border-radius:8px;background:rgba(20,184,166,.12)"><strong>{{ $skuMatrix['counts']['sidekick'] ?? 0 }}</strong><div>{{ __('Sidekick') }}</div></div>
                    <div style="padding:8px;border-radius:8px;background:rgba(15,23,42,.08)"><strong>{{ $skuMatrix['counts']['villain'] ?? 0 }}</strong><div>{{ __('Villain') }}</div></div>
                    <div style="padding:8px;border-radius:8px;background:rgba(148,163,184,.15)"><strong>{{ $skuMatrix['counts']['zombie'] ?? 0 }}</strong><div>{{ __('Zombie') }}</div></div>
                </div>
                <div style="font-size:13px;display:grid;gap:6px">
                    <div style="display:flex;justify-content:space-between">
                        <span>{{ __('Out-of-stock ad bleed flagged') }}</span>
                        <strong dir="ltr">{{ number_format($oosBleed['wasted_spend'] ?? 0, 2) }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span>{{ __('Leaked or high-bleed promo codes') }}</span>
                        <strong dir="ltr">{{ count($promoBleed['flagged_codes'] ?? []) }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span>{{ __('Co-purchased bundle pairs') }}</span>
                        <strong dir="ltr">{{ count($bundles ?? []) }}</strong>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- #204 MTA Switcher, #205 Custom Formula Builder & #206 Hash-Only POS CSV Matcher --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:14px">
            <x-filament::section>
                <x-slot name="heading">{!! __('<bdi>MTA</bdi> Model Switcher & <bdi>HDYHAU</bdi> Survey') !!}</x-slot>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
                    @foreach (['first_touch', 'last_touch', 'linear', 'time_decay', 'position_based'] as $m)
                        <button type="button" wire:click="setMtaModel('{{ $m }}')" style="min-height:44px;padding:0 12px;border-radius:8px;border:1px solid #0f766e;background:{{ $activeMtaModel === $m ? '#0f766e' : 'transparent' }};color:{{ $activeMtaModel === $m ? '#ffffff' : '#0f766e' }};font-size:12.5px;cursor:pointer">
                            <bdi>{{ $m }}</bdi>
                        </button>
                    @endforeach
                </div>
                <div style="font-size:13px">
                    <span>{{ __('Survey responses collected') }}:</span>
                    <strong dir="ltr">{{ $hdyhau['total_responses'] ?? 0 }}</strong>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{!! __('Custom KPI Formula & <bdi>SHA-256</bdi> Offline Matcher') !!}</x-slot>
                <div style="display:grid;gap:8px;font-size:13px">
                    <input type="text" wire:model="kpiName" placeholder="{{ __('KPI name') }}" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4)">
                    <input type="text" wire:model="kpiFormula" dir="ltr" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4)">
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <button type="button" wire:click="previewFormula" style="min-height:44px;padding:0 14px;border-radius:8px;border:1px solid #0f766e;background:transparent;color:#0f766e;font-weight:600;cursor:pointer">
                            {{ __('Preview formula') }}
                        </button>
                        <button type="button" wire:click="saveCustomKpi" style="min-height:44px;padding:0 14px;border-radius:8px;border:0;background:#0f766e;color:#ffffff;font-weight:600;cursor:pointer">
                            {{ __('Save KPI') }}
                        </button>
                    </div>
                    @if ($formulaPreviewValue !== null)
                        <div dir="ltr" style="color:#0f766e;font-weight:600">= {{ number_format($formulaPreviewValue, 4) }}</div>
                    @endif
                    @foreach ($customKpis as $kpi)
                        <div style="display:flex;justify-content:space-between;border-top:1px solid rgba(148,163,184,.2);padding-top:6px">
                            <span>{{ $kpi['name'] }}</span>
                            <strong dir="ltr">{{ $kpi['formatted'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
