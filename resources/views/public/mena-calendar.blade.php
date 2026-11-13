@php
// Website P-9 "MENA ad calendar 2027" (U-19, UX-158; D-95 sourced table in config/mena_calendar_2027.php; D-176, D-190).
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/mena-calendar';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$title = $ar ? 'تقويم مواسم الإعلانات 2027 في المنطقة | AdsRanger' : 'MENA Ad Calendar 2027: Ramadan, Eid and Sales | AdsRanger';
$meta = $ar
    ? 'أهم مواعيد الإعلان في المنطقة لعام 2027، من رمضان والعيد إلى مواسم التخفيضات، مع موعد بدء التخطيط ومصدر لكل موعد.'
    : 'Key 2027 advertising dates across MENA, from Ramadan and Eid to sales seasons, with when to start planning and a source for each date.';
$selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
$schema = [
    ['@type' => 'WebPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'تقويم المواسم' : 'Seasons calendar', 'item' => $selfUrl],
    ]],
];
$months = $ar
    ? ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
    : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$fmt = function (?string $iso, ?string $alt = null) use ($months, $ar): string {
    if (! $iso) {
        return '';
    }
    [$y, $m, $d] = array_map('intval', explode('-', $iso));
    if ($alt) {
        [, $m2, $d2] = array_map('intval', explode('-', $alt));
        $days = $m2 === $m ? $d.($ar ? ' أو ' : ' or ').$d2 : $d.' '.$months[$m - 1].($ar ? ' أو ' : ' or ').$d2;
        return $days.' '.$months[($m2 ?: $m) - 1].' '.$y;
    }
    return $d.' '.$months[$m - 1].' '.$y;
};
$countryNames = $ar
    ? ['JO' => 'الأردن', 'SA' => 'السعودية', 'AE' => 'الإمارات', 'KW' => 'الكويت', 'QA' => 'قطر', 'BH' => 'البحرين', 'OM' => 'عُمان', 'EG' => 'مصر', 'IQ' => 'العراق', 'LB' => 'لبنان', 'PS' => 'فلسطين', 'SY' => 'سوريا', 'MA' => 'المغرب', 'DZ' => 'الجزائر', 'TN' => 'تونس', 'LY' => 'ليبيا', 'SD' => 'السودان']
    : ['JO' => 'Jordan', 'SA' => 'Saudi Arabia', 'AE' => 'UAE', 'KW' => 'Kuwait', 'QA' => 'Qatar', 'BH' => 'Bahrain', 'OM' => 'Oman', 'EG' => 'Egypt', 'IQ' => 'Iraq', 'LB' => 'Lebanon', 'PS' => 'Palestine', 'SY' => 'Syria', 'MA' => 'Morocco', 'DZ' => 'Algeria', 'TN' => 'Tunisia', 'LY' => 'Libya', 'SD' => 'Sudan'];
$today = now('Asia/Amman')->toDateString();
$rows = collect(config('mena_calendar_2027.rows', []))
    ->filter(fn ($r) => ! empty($r['date']) && max($r['end'] ?? $r['date'], $r['alt_date'] ?? $r['date']) >= $today)
    ->sortBy('date')->values();
$usedCodes = $rows->pluck('country_codes')->flatten()->unique()->sort()->values();
$countriesOf = function (array $r) use ($ar, $countryNames): string {
    if ($r['country_codes'] === []) {
        return $ar ? 'كل دول المنطقة' : 'All MENA';
    }
    if (! $ar) {
        return $r['countries_en'] ?? implode(', ', array_map(fn ($c) => $countryNames[$c] ?? $c, $r['country_codes']));
    }
    return implode('، ', array_map(fn ($c) => $countryNames[$c] ?? $c, $r['country_codes']));
};
$noteOf = function (array $r) use ($ar): ?string {
    if (! $ar) {
        return $r['note_en'] ?? null;
    }
    if (! empty($r['note_ar'])) {
        return $r['note_ar'];
    }
    return ($r['note_en'] ?? null) === 'Falls in Ramadan' ? 'يقع في رمضان' : null;
};
$t = $ar ? [
    'h1' => 'تقويم مواسم الإعلانات في المنطقة 2027',
    'lead' => 'أهم مواعيد المعلنين في المنطقة، مع موعد بدء التخطيط لكل منها. المواعيد الهجرية متوقعة وتعتمد على رؤية الهلال في كل دولة، ولكل موعد رابط لمصدره.',
    'country' => 'الدولة', 'all_countries' => 'كل الدول', 'period' => 'الفترة',
    'periods' => ['all' => 'الكل', 'month' => 'هذا الشهر', '3m' => 'الأشهر الثلاثة القادمة'],
    'cols' => ['التاريخ', 'المناسبة', 'الدول', 'الحالة', 'ابدأ التخطيط', 'المصدر'],
    'expected' => 'متوقع', 'fixed' => 'ثابت', 'table' => 'جدول المواعيد', 'none' => 'لا مواعيد تطابق هذا الاختيار.',
    'guide' => 'دليل رمضان 2027', 'calc' => 'حاسبة ميزانية رمضان', 'plan_each' => '4 أسابيع قبل كل موعد',
] : [
    'h1' => 'MENA ad calendar 2027',
    'lead' => 'Key dates for advertisers across the region, with when to start planning. Hijri dates are expected and depend on each country\'s moon sighting. Every date links to its source.',
    'country' => 'Country', 'all_countries' => 'All countries', 'period' => 'Period',
    'periods' => ['all' => 'All', 'month' => 'This month', '3m' => 'Next 3 months'],
    'cols' => ['Date', 'Event', 'Countries', 'Status', 'Start planning', 'Source'],
    'expected' => 'expected', 'fixed' => 'fixed', 'table' => 'Calendar table', 'none' => 'No dates match this choice.',
    'guide' => 'Ramadan 2027 guide', 'calc' => 'Ramadan budget calculator', 'plan_each' => '4 weeks before each',
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.mc-filters{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0}
.mc-filters>div{min-width:200px}
.mc-filters label{margin:0 0 6px}
.mc-table{overflow-x:auto}
.mc-table table{width:100%;border-collapse:collapse;font-size:15px}
.mc-table th,.mc-table td{padding:10px;border-bottom:1px solid var(--line);text-align:start;vertical-align:top}
.mc-table thead th{color:var(--muted);font-weight:600;font-size:14px}
.mc-cards{display:none;list-style:none;padding:0;margin:0}
.mc-cards li{border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:10px}
.mc-cards p{margin:4px 0}
.mc-note{display:block;font-size:13px;color:var(--muted)}
.mc-table a,.mc-cards a,.mc-links a{display:inline-flex;align-items:center;min-height:44px}
@media (max-width:599px){.mc-table{display:none}.mc-cards{display:block}}
</style>
@endpush

@section('hero')
<h1>{{ $t['h1'] }}</h1>
<p class="muted" style="font-size:17px">{{ $t['lead'] }}</p>
@endsection

@section('content')
<section style="padding-top:24px;max-width:820px">
    <h2>{{ $ar ? 'كيف تستخدم التقويم' : 'How to use the calendar' }}</h2>
    <p>{!! $ar
        ? 'صفِّ المواسم حسب الدولة، وابدأ التخطيط من التاريخ المكتوب بجانب كل موسم. التواريخ المتوقعة تتحدد برؤية الهلال في كل دولة، وتُحسب حسب تقويم أم القرى، ونحدّثها حين تُعلن رسميًا. وللتخطيط لرمضان، جرّب <a href="'.$u('/tools/ramadan-budget-calculator').'">حاسبة ميزانية رمضان</a>.'
        : 'Filter seasons by country and start planning from the date shown next to each one. Expected dates follow the moon sighting in each country and are based on the Umm al-Qura calendar; we update them when they\'re announced. For Ramadan, try the <a href="'.$u('/tools/ramadan-budget-calculator').'">Ramadan budget calculator</a>.' !!}</p>
    <p class="mc-links"><a href="{{ $u('/ramadan-2027') }}">{{ $t['guide'] }}</a> · <a href="{{ $u('/tools/ramadan-budget-calculator') }}">{{ $t['calc'] }}</a></p>
</section>

<div class="mc-filters" id="mc-filters" hidden>
    <div>
        <label for="mc-country">{{ $t['country'] }}</label>
        <select id="mc-country">
            <option value="">{{ $t['all_countries'] }}</option>
            @foreach ($usedCodes as $code)<option value="{{ $code }}">{{ $countryNames[$code] ?? $code }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="mc-period">{{ $t['period'] }}</label>
        <select id="mc-period">
            @foreach ($t['periods'] as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
        </select>
    </div>
</div>
<p id="mc-none" hidden role="status">{{ $t['none'] }}</p>

<div class="mc-table" tabindex="0" role="region" aria-label="{{ $t['table'] }}">
    <table>
        <thead><tr>@foreach ($t['cols'] as $col)<th scope="col">{{ $col }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $r)
                @php $note = $noteOf($r); @endphp
                <tr id="{{ $r['id'] }}" data-mc data-date="{{ $r['date'] }}" data-codes="{{ implode(',', $r['country_codes']) }}">
                    <td>{{ $fmt($r['date'], $r['alt_date']) }}@if ($r['end']) – {{ $fmt($r['end']) }}@endif</td>
                    <th scope="row" style="font-weight:600">{{ $ar ? $r['event_ar'] : $r['event_en'] }}@if ($note)<span class="mc-note">{{ $note }}</span>@endif</th>
                    <td>{{ $countriesOf($r) }}</td>
                    <td><span class="badge {{ $r['status'] === 'fixed' ? '' : 'badge-neutral' }}">{{ $r['status'] === 'fixed' ? $t['fixed'] : $t['expected'] }}</span></td>
                    <td>{{ $r['plan_from'] ? $fmt($r['plan_from']) : $t['plan_each'] }}</td>
                    <td>@if ($r['source_url'])<a href="{{ $r['source_url'] }}" rel="noopener"><bdi dir="ltr">{{ $r['source_name'] ?: parse_url($r['source_url'], PHP_URL_HOST) }}</bdi></a>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<ul class="mc-cards">
    @foreach ($rows as $r)
        @php $note = $noteOf($r); @endphp
        <li id="m-{{ $r['id'] }}" data-mc data-date="{{ $r['date'] }}" data-codes="{{ implode(',', $r['country_codes']) }}">
            <h2 style="font-size:17px;margin:0">{{ $ar ? $r['event_ar'] : $r['event_en'] }}</h2>
            <p><strong>{{ $fmt($r['date'], $r['alt_date']) }}@if ($r['end']) – {{ $fmt($r['end']) }}@endif</strong> <span class="badge {{ $r['status'] === 'fixed' ? '' : 'badge-neutral' }}">{{ $r['status'] === 'fixed' ? $t['fixed'] : $t['expected'] }}</span></p>
            @if ($note)<p class="mc-note">{{ $note }}</p>@endif
            <p>{{ $t['cols'][2] }}: {{ $countriesOf($r) }}</p>
            <p>{{ $t['cols'][4] }}: {{ $r['plan_from'] ? $fmt($r['plan_from']) : $t['plan_each'] }}</p>
            @if ($r['source_url'])<p>{{ $t['cols'][5] }}: <a href="{{ $r['source_url'] }}" rel="noopener"><bdi dir="ltr">{{ $r['source_name'] ?: parse_url($r['source_url'], PHP_URL_HOST) }}</bdi></a></p>@endif
        </li>
    @endforeach
</ul>
<p style="margin-top:18px">{!! $ar ? 'نزّل هذا التقويم بصيغة <bdi dir="ltr">CSV</bdi> أو <bdi dir="ltr">JSON</bdi> من صفحة <a href="'.e(\App\Support\PublicUrl::path('/data', 'ar')).'">البيانات المفتوحة</a>.' : 'Download this calendar as CSV or JSON from our <a href="'.e(\App\Support\PublicUrl::path('/data', 'en')).'">open data</a> page.' !!}</p>
@include('public.partials.cite', ['citeTitle' => $ar ? 'تقويم المواسم الإعلانية 2027' : 'MENA ad calendar 2027', 'citeUrl' => \App\Support\PublicUrl::absolute('/mena-calendar', $ar ? 'ar' : 'en'), 'citeLicense' => 'CC BY 4.0'])
@endsection

@push('scripts')
<script>
(function(){
  var f=document.getElementById('mc-filters');if(!f){return;}f.hidden=false;
  var c=document.getElementById('mc-country'),p=document.getElementById('mc-period'),none=document.getElementById('mc-none');
  var today=new Date('{{ $today }}T00:00:00');
  function apply(){
    var code=c.value,per=p.value,end=null,shown=0;
    if(per==='month'){end=new Date(today.getFullYear(),today.getMonth()+1,1);}
    if(per==='3m'){end=new Date(today.getFullYear(),today.getMonth()+3,today.getDate());}
    document.querySelectorAll('[data-mc]').forEach(function(el){
      var codes=el.getAttribute('data-codes'),d=new Date(el.getAttribute('data-date')+'T00:00:00');
      var ok=(!code||codes===''||codes.split(',').indexOf(code)!==-1)&&(!end||d<end);
      el.hidden=!ok;if(ok){shown++;}
    });
    none.hidden=shown>0;
  }
  c.addEventListener('change',apply);p.addEventListener('change',apply);
})();
</script>
@endpush
