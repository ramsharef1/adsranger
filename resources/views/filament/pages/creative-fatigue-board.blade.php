<x-filament-panels::page>
@php
    $bd = fn (string $t) => (string) \App\Support\Seo\ToolCopy::html($t, $ar);
    $statusLabel = ['fatigued' => __('Tired'), 'watch' => __('Watch')];
    $statusColor = ['fatigued' => '#b91c1c', 'watch' => '#b45309'];
@endphp
<style>
.cf-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 0;border-top:1px solid rgba(148,163,184,.2)}
.cf-chip{font-size:12px;padding:2px 10px;border-radius:999px;color:#fff;font-weight:600}
.cf-muted{font-size:13px;opacity:.75;line-height:1.6}
.cf-btn{display:inline-flex;align-items:center;min-height:44px;padding:8px 16px;border-radius:8px;background:#0f766e;color:#fff;font-weight:600;border:0;cursor:pointer;text-decoration:none}
.cf-btn.ghost{background:transparent;color:inherit;border:1px solid rgba(148,163,184,.5)}
.cf-peak{padding:12px 14px;border-radius:12px;border:1px solid rgba(180,83,9,.35);background:rgba(180,83,9,.08);font-size:13.5px;line-height:1.65}
</style>

@foreach ($peaks as $p)
    <div class="cf-peak" role="note">
        <strong>{{ $ar ? $p['ar'] : $p['label'] }}</strong> · {{ __('Peak window: limits are tighter until :date.', ['date' => $p['end']]) }}
        <span class="cf-muted">({{ __('frequency :f, click-through drop :c%', ['f' => $tight['frequency'], 'c' => $tight['ctr_drop_pct']]) }})</span>
    </div>
@endforeach

<x-filament::section>
    <x-slot name="heading">{{ __('Ads that are tiring') }}</x-slot>
    <p class="cf-muted" style="margin:0">{{ __('An ad is tired when people see it too often and its click-through rate falls against its own previous 3 weeks. From ad-level data only; ads with under 1,000 impressions are skipped.') }} {{ __('Normal limits: frequency :f, click-through drop :c%.', ['f' => $normal['frequency'], 'c' => $normal['ctr_drop_pct']]) }}</p>
    @if ($canManage)
        <div style="margin-top:10px"><button type="button" class="cf-btn ghost" wire:click="checkNow" wire:loading.attr="disabled">{{ __('Check now') }}</button></div>
    @endif

    @forelse ($alerts as $a)
        <div class="cf-row">
            <span class="cf-chip" style="background:{{ $statusColor[$a->status] ?? '#475569' }}">{{ $statusLabel[$a->status] ?? $a->status }}</span>
            <div style="flex:1 1 260px;min-width:0">
                <strong>{{ $a->client?->name }}</strong> · <bdi dir="ltr">{{ $a->ad_name ?: $a->ad_ref }}</bdi>@if ($a->network) · <bdi dir="ltr">{{ $a->network }}</bdi>@endif
                <div class="cf-muted">
                    @if ($a->frequency !== null){{ __('Frequency') }}: <bdi dir="ltr">{{ number_format($a->frequency, 1) }}</bdi> · @endif
                    {{ __('Click-through rate') }}: <bdi dir="ltr">{{ number_format($a->ctr_recent, 2) }}% ← {{ number_format($a->ctr_baseline, 2) }}%</bdi> (<bdi dir="ltr">−{{ number_format($a->ctr_drop_pct, 0) }}%</bdi>)
                    @if ($a->thruplay_drop_pct !== null) · {{ __('ThruPlay rate') }}: <bdi dir="ltr">−{{ number_format($a->thruplay_drop_pct, 0) }}%</bdi>@endif
                    @unless ($a->client?->hide_spend) · {{ __('Spend, last 7 days') }}: <bdi dir="ltr">{{ \App\Support\ResultResolver::money($a->spend_recent, $a->client?->currency) }}</bdi>@endunless
                </div>
            </div>
            @if ($canManage)
                <a class="cf-btn" href="{{ \App\Filament\Pages\StudioPage::getUrl(['fatigue' => $a->id]) }}">{{ __('Draft a replacement') }}</a>
            @endif
        </div>
    @empty
        <p class="cf-muted" style="margin-top:12px">{{ __('No tired ads right now.') }}</p>
    @endforelse
</x-filament::section>
</x-filament-panels::page>
