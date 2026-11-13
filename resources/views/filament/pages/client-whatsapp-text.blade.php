{{-- XS-08 (R4): copy-ready WhatsApp text, Arabic and English. --}}
<div style="display:grid;gap:14px" data-whatsapp-text>
    @foreach ($texts as $lang => $text)
        <div x-data="{ done: false }" style="display:grid;gap:6px">
            <label style="font-size:13px;font-weight:600" for="wa-{{ $lang }}">{{ $lang === 'ar' ? __('Arabic') : __('English') }}</label>
            <textarea id="wa-{{ $lang }}" x-ref="t" readonly rows="7" dir="{{ $lang === 'ar' ? 'rtl' : 'ltr' }}" style="width:100%;font:inherit;font-size:14px;line-height:1.7;padding:8px;border:1px solid rgba(148,163,184,.4);border-radius:10px;background:transparent">{{ $text }}</textarea>
            <div><x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard && navigator.clipboard.writeText($refs.t.value).then(() => { done = true; setTimeout(() => done = false, 2000) }).catch(() => $refs.t.select())">
                <span x-show="! done">{{ __('Copy') }}</span><span x-show="done" x-cloak>{{ __('Copied') }}</span></x-filament::button></div>
        </div>
    @endforeach
</div>
