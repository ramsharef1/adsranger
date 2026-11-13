@php
    $ar = $lang === 'ar';
@endphp
<x-filament-panels::page>
    <style>
        @media print { .fi-sidebar, .fi-topbar, .ar-ribbon, .ar-bottom, [data-intake-controls], [data-context-bar], .fi-header { display:none !important } .fi-main { padding:0 !important } }
        .intake-line { border-bottom:1px solid rgba(148,163,184,.6); height:28px }
    </style>
    <div data-intake-controls style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">
        <label style="display:grid;gap:4px;font-size:13px;font-weight:600">{{ __('Language') }}
            <select wire:model.live="lang" style="min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent"><option value="ar">العربية</option><option value="en">English</option></select></label>
        <fieldset style="display:flex;flex-wrap:wrap;gap:4px 12px;border:0;padding:0;margin:0"><legend style="font-size:13px;font-weight:600">{{ __('Sections') }}</legend>
            @foreach ($catalog as $k => $s)<label style="display:flex;gap:6px;align-items:center;min-height:44px;font-size:13px"><input type="checkbox" wire:model.live="sections" value="{{ $k }}">{{ app()->getLocale() === 'ar' ? $s['ar'] : $s['en'] }}</label>@endforeach
        </fieldset>
        <x-filament::button type="button" onclick="window.print()" icon="heroicon-o-printer" style="min-height:44px">{{ __('Print or save as PDF') }}</x-filament::button>
    </div>

    <article dir="{{ $ar ? 'rtl' : 'ltr' }}" lang="{{ $ar ? 'ar' : 'en' }}" data-intake-form style="background:#fff;color:#0F1F1E;border:1px solid #DDE5E4;border-radius:14px;padding:24px;display:grid;gap:18px">
        <header><h2 style="margin:0;font-size:22px"><bdi>{{ $agency->name }}</bdi> · {{ $ar ? 'نموذج بيانات العميل' : 'Client intake form' }}</h2>
            <div style="color:#556664;font-size:13px">{{ $ar ? 'اسم العميل:' : 'Client name:' }} ______________________ · {{ $ar ? 'التاريخ:' : 'Date:' }} ____________</div></header>
        @foreach ($catalog as $k => $s)
            @continue(! in_array($k, $sections, true))
            <section style="display:grid;gap:10px"><h3 style="margin:0;font-size:16px;color:#0B5C56">{{ $ar ? $s['ar'] : $s['en'] }}</h3>
                @foreach ($s['q'] as $q)<div style="display:grid;gap:4px"><div style="font-size:14px">{{ $ar ? $q['ar'] : $q['en'] }}</div><div class="intake-line"></div><div class="intake-line"></div></div>@endforeach
            </section>
        @endforeach
    </article>
</x-filament-panels::page>
