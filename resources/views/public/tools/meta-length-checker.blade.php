@php
// SEO slice 0 step 0.4 · /tools/meta-length-checker (PR-015 Part 2 §2.3). Browser only: nothing is sent or stored.
// Text block + FAQ wait for Growth's copy (UX-216…224); noindex until config('seo_tools.public') (Rami approves).
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/tools/meta-length-checker';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$title = $ar ? 'فاحص طول العنوان والوصف للصفحات العربية | AdsRanger' : 'Title and Meta Description Length Checker | AdsRanger';
$meta = $ar
    ? 'الصق عناوين صفحاتك وأوصافها، واعرف أيها طويل أو قصير أو مكرر، وأيها يبدأ بكلمة لاتينية. حتى 50 صفحة في كل مرة. مجانًا ودون تسجيل، ولا نحفظ شيئًا.'
    : 'Paste up to 50 titles and descriptions to see which are too long, too short, duplicated or start with a Latin word. Free, no sign-up, nothing stored.';
$selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
$schema = [
    ['@type' => 'WebPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'فاحص طول العنوان والوصف' : 'Title and description length checker', 'item' => $selfUrl],
    ]],
];
$t = $ar ? [
    'paste' => 'العناوين والأوصاف', 'paste_hint' => 'صفحة في كل سطر: العنوان | الوصف (أو افصل بينهما بمسافة Tab).',
    'run' => 'افحص', 'csv' => 'حمّل النتائج CSV', 'limit' => 'الحد 50 صفحة في كل مرة.', 'empty' => 'الصق سطرًا واحدًا على الأقل.',
    'cols' => ['#', 'العنوان', 'أحرف · بكسل', 'الوصف', 'أحرف · بكسل', 'الحالة'],
    'ok' => 'مناسب', 'long' => 'طويل', 'short' => 'قصير', 'dup' => 'مكرر', 'latin' => 'يبدأ بكلمة لاتينية', 'nodesc' => 'بلا وصف',
    'sum_long' => 'العناوين الطويلة', 'sum_of' => 'من', 'title_word' => 'العنوان', 'desc_word' => 'الوصف',
    'note' => 'القياس بالبكسل تقديري، ويختلف قليلًا بين الأجهزة.', 'privacy' => 'دون تسجيل، ولا نحفظ أي شيء تدخله.',
    'other' => 'للمعاينة المرئية لصفحة واحدة، استخدم', 'other_link' => 'معاينة نتائج البحث',
    'example' => "حاسبة ميزانية إعلانات رمضان 2027 | AdsRanger | احسب تقديرًا لميزانية إعلانات رمضان 2027 وتكلفة النتيجة وعدد النتائج من أرقامك أنت.\nAdsRanger تقويم المواسم | مواسم 2027",
] : [
    'paste' => 'Titles and descriptions', 'paste_hint' => 'One page per line: title | description (or separate them with a Tab).',
    'run' => 'Check', 'csv' => 'Download results (CSV)', 'limit' => 'The limit is 50 pages at a time.', 'empty' => 'Paste at least one line.',
    'cols' => ['#', 'Title', 'Chars · px', 'Description', 'Chars · px', 'Status'],
    'ok' => 'OK', 'long' => 'Long', 'short' => 'Short', 'dup' => 'Duplicate', 'latin' => 'Starts with a Latin word', 'nodesc' => 'No description',
    'sum_long' => 'Long titles', 'sum_of' => 'of', 'title_word' => 'Title', 'desc_word' => 'Description',
    'note' => 'Pixel widths are estimates and vary a little between devices.', 'privacy' => 'No sign-up. Nothing you enter is stored.',
    'other' => 'For a visual preview of one page, use the', 'other_link' => 'search results preview',
    'example' => "Ramadan 2027 Ad Budget Calculator for Agencies | AdsRanger | Estimate a Ramadan 2027 ad budget, cost per result and results from your own numbers.\nSeasons calendar | 2027 seasons",
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.ml{padding-top:24px}
.ml label{display:flex;align-items:flex-end;min-height:44px}
.ml textarea{min-height:160px;font-size:15px}
.ml-hint{font-size:13px;color:var(--muted);margin:4px 0 0}
.ml-err{font-size:14px;color:var(--bad);margin:8px 0 0}
.ml-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
.ml-table{position:relative;overflow-x:auto;max-width:100%;border:1px solid var(--line);border-radius:12px;margin-top:16px}
.ml-table table{width:100%;border-collapse:collapse;min-width:640px;font-size:14px}
.ml-table th,.ml-table td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:start;vertical-align:top}
.ml-chip{display:inline-block;margin:2px;padding:2px 8px;border-radius:999px;border:1px solid var(--line);font-size:13px}
.ml-chip.bad{border-color:var(--bad)}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'فاحص طول العنوان والوصف' : 'Title and description length checker' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'الصق عناوين صفحاتك وأوصافها، واعرف أيها طويل أو قصير أو مكرر، وأيها يبدأ بكلمة لاتينية. حتى 50 صفحة في كل مرة.' : 'Paste your page titles and descriptions to see which are too long, too short, duplicated or start with a Latin word. Up to 50 pages at a time.' }}</p>
@endsection

@section('content')
<section class="ml">
    <form class="card" id="ml-form" onsubmit="return false" novalidate>
        <label for="ml-in">{{ $t['paste'] }}</label>
        <textarea id="ml-in" aria-describedby="ml-in-hint ml-err">{{ $t['example'] }}</textarea>
        <p class="ml-hint" id="ml-in-hint">{{ $t['paste_hint'] }}</p>
        <p class="ml-err" id="ml-err" hidden></p>
        <div class="ml-actions">
            <button type="button" class="btn" id="ml-run">{{ $t['run'] }}</button>
            <button type="button" class="btn btn-ghost" id="ml-csv" disabled>{{ $t['csv'] }}</button>
        </div>
    </form>
    <div aria-live="polite">
        <p id="ml-sum" style="margin-top:16px;font-weight:600"></p>
        <div class="ml-table" id="ml-wrap" tabindex="0" role="region" aria-label="{{ $ar ? 'نتائج الفحص' : 'Check results' }}" hidden>
            <table>
                <caption class="sr">{{ $ar ? 'نتائج الفحص' : 'Check results' }}</caption>
                <thead><tr>@foreach ($t['cols'] as $c)<th scope="col">{{ $c }}</th>@endforeach</tr></thead>
                <tbody id="ml-rows"></tbody>
            </table>
        </div>
    </div>
    <p class="muted" style="margin-top:12px">{{ $t['note'] }}</p>
    <p class="muted">{{ $t['privacy'] }}</p>
    <p>{{ $t['other'] }} <a href="{{ $u('/tools/serp-preview') }}">{{ $t['other_link'] }}</a>.</p>
    <p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a></p>
</section>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'meta-length-checker', 'ar' => $ar])
@endsection

@push('scripts')
@include('public.tools.partials.serp-engine')
<script>
(function(){
  var S = window.ArSerp, W = S.W, T = @json($t), $ = function (id) { return document.getElementById(id); }, rows = [];
  function parse(line) {
    var parts = line.indexOf('\t') >= 0 ? line.split('\t') : line.split(' | ');
    return { title: (parts.shift() || '').trim(), desc: parts.join(' | ').trim() };
  }
  window.ArMetaCheck = function (text) {
    var lines = text.split(/\r?\n/).map(function (l) { return l.trim(); }).filter(Boolean);
    if (lines.length === 0) { return { error: T.empty }; }
    if (lines.length > W.bulk_max_rows) { return { error: T.limit }; }
    var list = lines.map(parse), seenT = {}, seenD = {};
    list.forEach(function (r) { seenT[r.title] = (seenT[r.title] || 0) + 1; if (r.desc) { seenD[r.desc] = (seenD[r.desc] || 0) + 1; } });
    return { rows: list.map(function (r) {
      var tPx = S.measure(r.title, W.title.font_px), dPx = S.measure(r.desc, W.description.font_px), st = [];
      if (tPx > W.title.max_px.desktop) { st.push([T.title_word + ': ' + T.long, 1]); } else if (S.chars(r.title) < W.title.min_chars) { st.push([T.title_word + ': ' + T.short, 1]); }
      if (S.latinFirst(r.title)) { st.push([T.latin, 1]); }
      if (seenT[r.title] > 1) { st.push([T.title_word + ': ' + T.dup, 1]); }
      if (!r.desc) { st.push([T.nodesc, 1]); } else {
        if (dPx > W.description.max_px.desktop) { st.push([T.desc_word + ': ' + T.long, 1]); } else if (S.chars(r.desc) < W.description.min_chars) { st.push([T.desc_word + ': ' + T.short, 1]); }
        if (seenD[r.desc] > 1) { st.push([T.desc_word + ': ' + T.dup, 1]); }
      }
      if (st.length === 0) { st.push([T.ok, 0]); }
      return { title: r.title, desc: r.desc, tc: S.chars(r.title), tp: tPx, dc: S.chars(r.desc), dp: dPx, status: st, longTitle: tPx > W.title.max_px.desktop };
    }) };
  };
  function run() {
    var res = window.ArMetaCheck($('ml-in').value), err = $('ml-err'), body = $('ml-rows');
    body.innerHTML = ''; rows = [];
    if (res.error) { err.textContent = res.error; err.hidden = false; $('ml-wrap').hidden = true; $('ml-sum').textContent = ''; $('ml-csv').disabled = true; return; }
    err.hidden = true; rows = res.rows;
    rows.forEach(function (r, i) {
      var tr = document.createElement('tr');
      [String(i + 1), r.title, r.tc + ' · ' + r.tp, r.desc, r.dc + ' · ' + r.dp].forEach(function (v) { var td = document.createElement('td'); td.textContent = v; tr.appendChild(td); });
      var td = document.createElement('td');
      r.status.forEach(function (s) { var c = document.createElement('span'); c.className = 'ml-chip' + (s[1] ? ' bad' : ''); c.textContent = s[0]; td.appendChild(c); });
      tr.appendChild(td); body.appendChild(tr);
    });
    $('ml-wrap').hidden = false; $('ml-csv').disabled = false;
    $('ml-sum').textContent = T.sum_long + ': ' + rows.filter(function (r) { return r.longTitle; }).length + ' ' + T.sum_of + ' ' + rows.length;
  }
  function csv() {
    var q = function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; };
    var lines = [T.cols.map(q).join(',')].concat(rows.map(function (r, i) { return [i + 1, r.title, r.tc + ' · ' + r.tp, r.desc, r.dc + ' · ' + r.dp, r.status.map(function (s) { return s[0]; }).join(' / ')].map(q).join(','); }));
    var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }), a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = 'meta-length-check.csv'; a.click(); URL.revokeObjectURL(a.href);
  }
  $('ml-run').addEventListener('click', run); $('ml-csv').addEventListener('click', csv);
  run();
})();
</script>
@endpush
