@php
    $h = fn (string $s) => \App\Support\Seo\ToolCopy::html($s, app()->getLocale() === 'ar');
    $money = fn (float $v, string $cur = 'USD') => \App\Support\ResultResolver::money($v, $cur);
    $cell = 'padding:6px;text-align:start;vertical-align:top';
    $input = 'width:100%;min-height:44px;border:1px solid rgba(127,127,127,.35);border-radius:8px;padding:6px 10px;background:transparent';
@endphp
<x-filament-panels::page>
    <div role="note" style="padding:12px 14px;border-radius:10px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4)">
        <strong>{{ __('reach_ui.estimate_badge') }}</strong> · {{ __('reach_ui.billing_awaits_launch') }}
    </div>

    <x-filament::section :heading="__('reach_ui.admin.totals')">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
            @foreach (['pending' => __('reach_ui.wallet.pending'), 'cleared' => __('reach_ui.wallet.cleared'), 'reserve' => __('reach_ui.wallet.reserve')] as $k => $label)
                <div><div style="opacity:.7;font-size:13px">{{ $label }}</div><strong><bdi dir="ltr">{{ $money($totals[$k]) }}</bdi></strong></div>
            @endforeach
            <div><div style="opacity:.7;font-size:13px">{{ __('reach_ui.admin.conversions') }}</div><strong><bdi dir="ltr">{{ $totals['conversions'] }}</bdi></strong></div>
            <div><div style="opacity:.7;font-size:13px">{{ __('reach_ui.admin.unattributed_today') }}</div><strong><bdi dir="ltr">{{ $totals['unattributed'] }}</bdi></strong></div>
        </div>
        <div style="margin-top:12px"><x-filament::button size="sm" color="gray" wire:click="clearDue" style="min-height:44px">{{ __('reach_ui.admin.clear_due') }}</x-filament::button></div>
    </x-filament::section>

    <x-filament::section :heading="__('reach_ui.admin.publishers')">
        <p style="opacity:.75;font-size:13px">
            @foreach (\App\Models\ReachPublisher::STATUSES as $st)
                {{ __('reach_ui.status.'.$st) }}: <bdi dir="ltr">{{ $statusCounts[$st] ?? 0 }}</bdi>@if (! $loop->last) · @endif
            @endforeach
        </p>
        @if ($publishers->isEmpty())
            <p style="opacity:.7">{{ __('reach_ui.admin.no_publishers') }}</p>
        @else
            <div style="overflow-x:auto"><table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr><th style="{{ $cell }}">{{ __('reach_ui.admin.name') }}</th><th style="{{ $cell }}">{{ __('reach_ui.admin.domain') }}</th><th style="{{ $cell }}">{{ __('reach_ui.admin.ref') }}</th><th style="{{ $cell }}">{{ __('reach_ui.admin.status') }}</th><th style="{{ $cell }}"></th></tr></thead>
                <tbody>
                @foreach ($publishers as $p)
                    <tr style="border-top:1px solid rgba(127,127,127,.2)">
                        <td style="{{ $cell }}">{{ $p->name }}</td>
                        <td style="{{ $cell }}"><bdi dir="ltr">{{ $p->domain_or_app }}</bdi></td>
                        <td style="{{ $cell }}"><bdi dir="ltr">{{ $p->ref }}</bdi></td>
                        <td style="{{ $cell }}">{{ __('reach_ui.status.'.$p->status) }}</td>
                        <td style="{{ $cell }};white-space:nowrap">
                            @foreach (['verified', 'rejected', 'paused'] as $st)
                                @if ($p->status !== $st)
                                    <x-filament::button size="xs" color="gray" wire:click="setPublisherStatus({{ $p->id }}, @js($st))" style="min-height:36px">{{ __('reach_ui.action.'.$st) }}</x-filament::button>
                                @endif
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('reach_ui.admin.import')">
        <x-slot name="description">{{ $h(__('reach_ui.admin.import_help')) }}</x-slot>
        <textarea wire:model="csv" rows="6" dir="ltr" style="{{ $input }};font-family:monospace" placeholder="title,network,external_id,payout,type,coupon,url"></textarea>
        <div style="margin-top:8px"><x-filament::button wire:click="importCsv" style="min-height:44px">{{ __('reach_ui.admin.import_button') }}</x-filament::button></div>
        @if ($importResult)
            <p style="margin-top:8px">{{ __('reach_ui.admin.imported', ['created' => $importResult['created'], 'updated' => $importResult['updated']]) }}</p>
            @foreach ($importResult['errors'] as $line => $err)
                <p style="color:rgb(var(--danger-600))">{{ __('reach_ui.admin.line', ['line' => $line]) }} · {{ $h(__('reach_ui.error.'.$err)) }}</p>
            @endforeach
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('reach_ui.admin.manual_offer')">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">
            <label>{{ __('reach_ui.field.title') }}<input wire:model="offer.title" style="{{ $input }}"></label>
            <label>{{ __('reach_ui.field.network') }}<select wire:model="offer.network" style="{{ $input }}">@foreach (config('reach.networks') as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select></label>
            <label>{{ __('reach_ui.field.external_id') }}<input wire:model="offer.external_id" dir="ltr" style="{{ $input }}"></label>
            <label>{{ __('reach_ui.field.payout') }}<input wire:model="offer.payout_amount" inputmode="decimal" dir="ltr" style="{{ $input }}"></label>
            <label>{{ __('reach_ui.field.publisher_payout') }}<input wire:model="offer.publisher_payout_amount" inputmode="decimal" dir="ltr" style="{{ $input }}" placeholder="70%"></label>
            <label>{{ __('reach_ui.field.currency') }}<input wire:model="offer.currency" maxlength="3" dir="ltr" style="{{ $input }}"></label>
            <label>{{ __('reach_ui.field.type') }}<select wire:model="offer.offer_type" style="{{ $input }}">@foreach (config('reach.offer_types') as $t)<option value="{{ $t }}">{{ strtoupper($t) }}</option>@endforeach</select></label>
            <label>{{ __('reach_ui.field.coupon') }}<input wire:model="offer.coupon_code" dir="ltr" style="{{ $input }}"></label>
            <label>{{ __('reach_ui.field.url') }}<input wire:model="offer.landing_url" type="url" dir="ltr" style="{{ $input }}"></label>
            <label style="display:flex;gap:8px;align-items:center;min-height:44px"><input type="checkbox" wire:model="offer.allow_incent"> {{ __('reach_ui.field.allow_incent') }}</label>
        </div>
        <div style="margin-top:8px"><x-filament::button wire:click="saveOffer" style="min-height:44px">{{ __('reach_ui.admin.save_offer') }}</x-filament::button></div>
        @if ($offers->isNotEmpty())
            <div style="overflow-x:auto;margin-top:12px"><table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr><th style="{{ $cell }}">#</th><th style="{{ $cell }}">{{ __('reach_ui.field.title') }}</th><th style="{{ $cell }}">{{ __('reach_ui.field.network') }}</th><th style="{{ $cell }}">{{ __('reach_ui.field.payout') }}</th><th style="{{ $cell }}">{{ __('reach_ui.field.publisher_payout') }}</th><th style="{{ $cell }}">{{ __('reach_ui.admin.codes') }}</th></tr></thead>
                <tbody>
                @foreach ($offers as $o)
                    <tr style="border-top:1px solid rgba(127,127,127,.2)">
                        <td style="{{ $cell }}"><bdi dir="ltr">{{ $o->id }}</bdi></td>
                        <td style="{{ $cell }}">{{ $o->title }}@if ($o->allow_incent) · {{ __('reach_ui.field.allow_incent') }}@endif</td>
                        <td style="{{ $cell }}"><bdi dir="ltr">{{ $o->network }} · {{ strtoupper($o->offer_type) }}</bdi></td>
                        <td style="{{ $cell }}"><bdi dir="ltr">{{ $money($o->payout_amount, $o->currency) }}</bdi></td>
                        <td style="{{ $cell }}"><bdi dir="ltr">{{ $money($o->publisher_payout_amount, $o->currency) }}</bdi></td>
                        <td style="{{ $cell }}"><bdi dir="ltr">{{ $o->codes_count }}</bdi></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('reach_ui.admin.codes')">
        <x-slot name="description">{{ $h(__('reach_ui.admin.codes_help')) }}</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px">
            <label>{{ __('reach_ui.admin.offer_id') }}<input wire:model="code.offer_id" inputmode="numeric" dir="ltr" style="{{ $input }}"></label>
            <label>{{ __('reach_ui.field.coupon') }}<input wire:model="code.code" dir="ltr" style="{{ $input }}"></label>
            <label>{{ __('reach_ui.admin.tier') }}<select wire:model="code.tier" style="{{ $input }}">@foreach (\App\Models\ReachCouponCode::TIERS as $t)<option value="{{ $t }}">{{ $h(__('reach_ui.tier.'.$t)) }}</option>@endforeach</select></label>
            <label>{{ __('reach_ui.admin.ref') }}<input wire:model="code.publisher_ref" dir="ltr" style="{{ $input }}"></label>
        </div>
        <div style="margin-top:8px"><x-filament::button wire:click="addCode" style="min-height:44px">{{ __('reach_ui.admin.save_code') }}</x-filament::button></div>
    </x-filament::section>

    <x-filament::section :heading="__('reach_ui.admin.postbacks')">
        <x-slot name="description">{{ $h(__('reach_ui.admin.postbacks_help')) }}</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">
            <label>{{ __('reach_ui.field.network') }}<select wire:model="endpoint.network" style="{{ $input }}">@foreach (config('reach.networks') as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select></label>
            <label>{{ __('reach_ui.admin.label') }}<input wire:model="endpoint.label" style="{{ $input }}"></label>
            <label>{{ __('reach_ui.admin.ips') }}<input wire:model="endpoint.ips" dir="ltr" style="{{ $input }}" placeholder="203.0.113.10, 198.51.100.0/24"></label>
        </div>
        <div style="margin-top:8px"><x-filament::button wire:click="createEndpoint" style="min-height:44px">{{ __('reach_ui.admin.create_endpoint') }}</x-filament::button></div>
        @if ($newEndpoint)
            <div style="margin-top:10px;padding:10px;border-radius:8px;border:1px solid rgba(127,127,127,.35)">
                <p><strong>{{ __('reach_ui.admin.shown_once') }}</strong></p>
                <p><bdi dir="ltr" style="word-break:break-all">{{ $newEndpoint['url'] }}</bdi></p>
                <p>{{ __('reach_ui.admin.secret') }}: <bdi dir="ltr" style="word-break:break-all">{{ $newEndpoint['secret'] }}</bdi></p>
            </div>
        @endif
        @foreach ($endpoints as $e)
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(127,127,127,.2)">
                <span><bdi dir="ltr">{{ $e->network }}</bdi> · {{ $e->label }} · {{ $e->active ? __('reach_ui.admin.active') : __('reach_ui.admin.inactive') }} · {{ $e->allowed_ips ? __('reach_ui.admin.ip_list') : __('reach_ui.admin.signature_only') }}@if ($e->last_received_at) · <bdi dir="ltr">{{ $e->last_received_at->timezone('Asia/Amman')->format('Y-m-d H:i') }}</bdi>@endif</span>
                <x-filament::button size="sm" color="gray" wire:click="toggleEndpoint({{ $e->id }})" style="min-height:44px">{{ $e->active ? __('reach_ui.admin.disable') : __('reach_ui.admin.enable') }}</x-filament::button>
            </div>
        @endforeach
    </x-filament::section>

    <x-filament::section :heading="__('reach_ui.admin.billing')">
        <x-slot name="description">{{ $h(__('reach_ui.admin.billing_help')) }}</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">
            <label>{{ __('reach_ui.admin.advertiser') }}<select wire:model="billing.advertiser_id" style="{{ $input }}"><option value="">—</option>@foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
            <label>{{ __('reach_ui.admin.billing_mode') }}<select wire:model="billing.billing_mode" style="{{ $input }}">@foreach (config('reach.billing_modes') as $m)<option value="{{ $m }}">{{ __('reach_ui.billing.'.$m) }}</option>@endforeach</select></label>
            <label>{{ __('reach_ui.admin.credit_cap') }}<input wire:model="billing.credit_cap" inputmode="decimal" dir="ltr" style="{{ $input }}"></label>
        </div>
        <div style="margin-top:8px"><x-filament::button wire:click="saveBilling" style="min-height:44px">{{ __('reach_ui.admin.save_billing') }}</x-filament::button></div>
        @foreach ($billings as $b)
            <div style="padding:8px 0;border-bottom:1px solid rgba(127,127,127,.2)">
                {{ $b->advertiser?->name }} · {{ __('reach_ui.billing.'.$b->billing_mode) }} · {{ __('reach_ui.admin.escrow') }} <bdi dir="ltr">{{ $money($b->escrow_balance, $b->currency) }}</bdi> · {{ __('reach_ui.admin.credit_cap') }} <bdi dir="ltr">{{ $money($b->credit_cap, $b->currency) }}</bdi>
            </div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
