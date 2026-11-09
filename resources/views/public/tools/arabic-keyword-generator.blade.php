@php
// SEO slice 0 step 0.4 · /tools/arabic-keyword-generator (PR-015 Part 2 §2.1). Ideas and spellings only, no volumes.
// Runs in the browser on the shared Arabic text layer (normalise.js + normalise.json). Text block + FAQ: Growth.
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/arabic-keyword-generator',
    ['مولد كلمات مفتاحية عربية مجاني | AdsRanger', 'Free Arabic Keyword Generator | AdsRanger'],
    ['اكتب كلمة واحدة عن خدمتك، واحصل على صيغها العربية الشائعة: الهمزات والتاء المربوطة والعربيزي وأسماء المدن والأسئلة. مجانًا ودون تسجيل، ولا نحفظ شيئًا.',
        'Type one word about your service and get its common Arabic forms: spellings, Arabizi, city names and questions. Free, no sign-up, nothing stored.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$countries = ['' => $ar ? 'كل دول المنطقة' : 'All MENA', 'SA' => $ar ? 'السعودية' : 'Saudi Arabia', 'AE' => $ar ? 'الإمارات' : 'UAE', 'KW' => $ar ? 'الكويت' : 'Kuwait',
    'QA' => $ar ? 'قطر' : 'Qatar', 'BH' => $ar ? 'البحرين' : 'Bahrain', 'OM' => $ar ? 'عُمان' : 'Oman', 'EG' => $ar ? 'مصر' : 'Egypt', 'JO' => $ar ? 'الأردن' : 'Jordan',
    'LB' => $ar ? 'لبنان' : 'Lebanon', 'IQ' => $ar ? 'العراق' : 'Iraq', 'MA' => $ar ? 'المغرب' : 'Morocco', 'DZ' => $ar ? 'الجزائر' : 'Algeria', 'TN' => $ar ? 'تونس' : 'Tunisia'];
$t = $ar ? [
    'word' => 'الكلمة أو العبارة', 'country' => 'الدولة', 'run' => 'اعرض الصيغ', 'copy' => 'انسخ الكل', 'csv' => 'حمّل CSV', 'copied' => 'نُسخت الصيغ.',
    'g_spell' => 'صيغ الكتابة', 'g_arabizi' => 'العربيزي', 'g_arabic' => 'بالحروف العربية', 'g_cities' => 'مع المدن', 'g_q' => 'أسئلة', 'g_rel' => 'كلمات قريبة',
    'count' => 'عدد الصيغ: ', 'empty' => 'اكتب كلمة واحدة على الأقل.', 'long' => 'العبارة أطول من 60 حرفًا.',
    'note' => 'هذه صيغ محتملة وليست أحجام بحث. تحقّق من الطلب على كل صيغة قبل اعتمادها.',
] : [
    'word' => 'Word or phrase', 'country' => 'Country', 'run' => 'Show forms', 'copy' => 'Copy all', 'csv' => 'Download CSV', 'copied' => 'Forms copied.',
    'g_spell' => 'Spellings', 'g_arabizi' => 'Arabizi', 'g_arabic' => 'In Arabic letters', 'g_cities' => 'With cities', 'g_q' => 'Questions', 'g_rel' => 'Related words',
    'count' => 'Forms shown: ', 'empty' => 'Type at least one word.', 'long' => 'The phrase is over 60 characters.',
    'note' => 'These are possible forms, not search volumes. Check demand for each before you use it.',
];
@endphp
@extends('layouts.public')

@push('styles')
@include('public.tools.partials.styles')
@endpush

@section('hero')
<h1>{{ $ar ? 'مولد الكلمات المفتاحية العربية' : 'Arabic keyword generator' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'يكتب الناس الكلمة نفسها بأكثر من شكل. أدخل كلمة عن خدمتك، وسنعرض الصيغ التي قد يبحث بها جمهورك.' : 'People type the same word in more than one way. Enter a word about your service and we\'ll show the forms your audience may search with.' }}</p>
@endsection

@section('content')
<section class="tl">
    <form class="card" id="kg-form" novalidate>
        <label for="kg-word">{{ $t['word'] }}</label>
        <input id="kg-word" type="text" maxlength="80" value="{{ $ar ? 'عياده اسنان' : 'عيادة أسنان' }}" aria-describedby="kg-err">
        <label for="kg-country">{{ $t['country'] }}</label>
        <select id="kg-country">@foreach ($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select>
        <p class="tl-err" id="kg-err" hidden></p>
        <div class="tl-actions">
            <button type="submit" class="btn">{{ $t['run'] }}</button>
            <button type="button" class="btn btn-ghost" id="kg-copy" disabled>{{ $t['copy'] }}</button>
            <button type="button" class="btn btn-ghost" id="kg-csv" disabled>{{ $t['csv'] }}</button>
        </div>
    </form>
    <div aria-live="polite">
        <div class="tl-out" id="kg-out" hidden></div>
        <p id="kg-count" style="font-weight:600;margin-top:12px"></p>
        <p class="muted">{{ $t['note'] }}</p>
        <p class="muted">{{ $privacy }}</p>
    </div>
</section>
<p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'arabic-keyword-generator', 'ar' => $ar])
@endsection

@push('scripts')
@include('public.tools.partials.arabic-engine')
<script type="module">
const N = window.ArNorm, R = @json($arabicRules), T = @json($t), $ = (id) => document.getElementById(id);
let groups = [];
const uniq = (list, seed) => Array.from(new Set(list.map((s) => s.replace(/\s+/g, ' ').trim()))).filter((s) => s && s !== seed);
function spellings(seed) {
    const out = [seed], words = seed.split(' ');
    out.push(...N.variants(seed).filter((v) => v.type === 'spelling').map((v) => v.text));
    const swapEnd = (w, a, b) => (w.endsWith(a) ? w.slice(0, -1) + b : w);
    out.push(words.map((w) => swapEnd(w, 'ة', 'ه')).join(' '), words.map((w) => swapEnd(w, 'ه', 'ة')).join(' '));
    out.push(words.map((w) => swapEnd(w, 'ى', 'ي')).join(' '), words.map((w) => swapEnd(w, 'ي', 'ى')).join(' '));
    out.push(words.map((w) => w.replace(/^[أإآ]/, 'ا')).join(' '));
    out.push(words.map((w) => (/^ا[^ل]/.test(w) ? 'أ' + w.slice(1) : w)).join(' '));
    out.push(words.map((w) => swapEnd(swapEnd(w.replace(/^ا(?!ل)/, 'أ'), 'ه', 'ة'), 'ي', 'ى')).join(' '));
    out.push(words[0].startsWith('ال') ? [words[0].slice(2), ...words.slice(1)].join(' ') : ['ال' + words[0], ...words.slice(1)].join(' '));
    return uniq(out, seed).filter((s) => N.key(s) !== '').slice(0, 12);
}
function run(e) {
    if (e) { e.preventDefault(); }
    const seed = $('kg-word').value.replace(/\s+/g, ' ').trim(), err = $('kg-err'), country = $('kg-country').value;
    if (seed === '') { err.textContent = T.empty; err.hidden = false; return; }
    if (Array.from(seed).length > 60) { err.textContent = T.long; err.hidden = false; return; }
    err.hidden = true;
    const arabic = /[؀-ۿ]/.test(seed);
    const bases = arabic ? [seed] : N.arabiziToArabic(seed).slice(0, 4);
    const base = bases[0] || seed;
    const cityList = country ? (R.cities[country] || []) : Object.values(R.cities).map((c) => c[0]);
    const firstCity = cityList[0] || '';
    const rel = Object.entries(R.related_words).find(([head]) => N.key(head) === N.key(base));
    groups = [
        [T.g_spell, arabic ? spellings(seed) : []],
        [arabic ? T.g_arabizi : T.g_arabic, arabic ? N.arabicToArabizi(seed) : bases],
        [T.g_cities, cityList.slice(0, 6).map((c) => `${base} في ${c}`)],
        [T.g_q, Object.values(R.question_templates).flat().map((q) => q.replace('{kw}', base).replace('{city}', firstCity)).filter((q) => !q.includes('{'))],
        [T.g_rel, rel ? rel[1] : []],
    ].filter(([, list]) => list.length > 0);
    const out = $('kg-out'); out.innerHTML = '';
    groups.forEach(([name, list]) => {
        const h = document.createElement('h3'); h.textContent = name; out.appendChild(h);
        const ul = document.createElement('ul'); ul.className = 'tl-list';
        list.forEach((s) => { const li = document.createElement('li'); li.textContent = s; li.dir = /[؀-ۿ]/.test(s) ? 'rtl' : 'ltr'; ul.appendChild(li); });
        out.appendChild(ul);
    });
    out.hidden = false;
    $('kg-count').textContent = T.count + groups.reduce((n, [, l]) => n + l.length, 0);
    $('kg-copy').disabled = false; $('kg-csv').disabled = false;
}
$('kg-form').addEventListener('submit', run);
$('kg-copy').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(groups.map(([, l]) => l.join('\n')).join('\n')); $('kg-count').textContent = T.copied; } catch (e) { /* clipboard blocked: nothing to do */ }
});
$('kg-csv').addEventListener('click', () => {
    const q = (v) => '"' + String(v).replace(/"/g, '""') + '"';
    const lines = ['group,form'].concat(groups.flatMap(([g, l]) => l.map((s) => q(g) + ',' + q(s))));
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
    a.download = 'arabic-keywords.csv'; a.click(); URL.revokeObjectURL(a.href);
});
run();
</script>
@endpush
