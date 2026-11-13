@php
// Free tool · /tools/arabizi-converter (PR-015 §2.1 shared library). Arabizi → Arabic candidates in the browser.
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/arabizi-converter',
    ['محوّل العربيزي إلى العربية | AdsRanger', 'Arabizi to Arabic Converter | AdsRanger'],
    ['اكتب كلمة بالعربيزي، مثل 7elw أو mat3am، واحصل على صيغها المحتملة بالحروف العربية. مجانًا ودون تسجيل، ولا نحفظ شيئًا.',
        'Type a word in Arabizi, like 7elw or mat3am, and get its likely forms in Arabic letters. Free, no sign-up, nothing stored.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$t = $ar ? ['in' => 'النص بالعربيزي', 'hint' => 'الأرقام: 2 = ء · 3 = ع · 5 = خ · 6 = ط · 7 = ح · 8 = ق أو غ · 9 = ص', 'out' => 'الصيغ المحتملة', 'empty' => 'اكتب كلمة بالعربيزي.', 'note' => 'قد تحمل الكلمة الواحدة أكثر من صيغة؛ اختر الأقرب إلى ما يقصده جمهورك.', 'copy' => 'انسخ الصيغ']
    : ['in' => 'Arabizi text', 'hint' => 'Digits: 2 = ء · 3 = ع · 5 = خ · 6 = ط · 7 = ح · 8 = ق or غ · 9 = ص', 'out' => 'Likely forms', 'empty' => 'Type a word in Arabizi.', 'note' => 'One word can have more than one form; pick the one your audience means.', 'copy' => 'Copy forms'];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'محوّل العربيزي' : 'Arabizi converter' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'يكتب كثيرون العربية بحروف لاتينية وأرقام. حوّل ما يكتبه جمهورك إلى الحروف العربية.' : 'Many people write Arabic with Latin letters and digits. Turn what your audience types into Arabic letters.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="az-form" onsubmit="return false" novalidate>
        <label for="az-in">{{ $t['in'] }}</label>
        <input id="az-in" type="text" dir="ltr" maxlength="60" value="mat3am 7elw" aria-describedby="az-hint">
        <p class="tl-hint" id="az-hint">{{ $t['hint'] }}</p>
        <div class="tl-actions"><button type="button" class="btn btn-ghost" id="az-copy">{{ $t['copy'] }}</button></div>
    </form>
    <div class="tl-out" aria-live="polite">
        <h2>{{ $t['out'] }}</h2>
        <ul class="tl-list" id="az-out" dir="rtl"></ul>
        <p class="muted" style="margin-top:12px">{{ $t['note'] }}</p>
        <p class="muted">{{ $privacy }}</p>
    </div>
</section>
<p><a href="{{ $u('/tools/arabic-keyword-generator') }}">{{ $ar ? 'مولد الكلمات المفتاحية العربية' : 'Arabic keyword generator' }}</a></p>
<p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'arabizi-converter', 'ar' => $ar])
@endsection
@push('scripts')
@include('public.tools.partials.arabic-engine')
<script type="module">
const N = window.ArNorm, T = @json($t), $ = (id) => document.getElementById(id);
let forms = [];
function run() {
    const v = $('az-in').value.trim(), ul = $('az-out'); ul.innerHTML = '';
    forms = v ? N.arabiziToArabic(v) : [];
    (forms.length ? forms : [T.empty]).forEach((f) => { const li = document.createElement('li'); li.textContent = f; ul.appendChild(li); });
}
$('az-in').addEventListener('input', run);
$('az-copy').addEventListener('click', async () => { try { await navigator.clipboard.writeText(forms.join('\n')); } catch (e) { /* clipboard blocked */ } });
run();
</script>
@endpush
