@php
// Website P-8 "Sample report" (U-8, U-18, UX-149, UX-158; D-116, D-177): made-up demo data, fictional furniture store.
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/demo-report';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$title = $ar ? 'نموذج تقرير للعميل ببيانات تجريبية | AdsRanger' : 'Sample Client Report with Demo Data | AdsRanger';
$meta = $ar
    ? 'نموذج تقرير للعميل من AdsRanger ببيانات تجريبية: الإنفاق والنتائج حسب هدف الحملة وتكلفة النتيجة. قيد التطوير.'
    : 'A sample AdsRanger client report with demo data: spend, results by campaign goal and cost per result. In development.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
$money = fn (string $amount) => $ar ? '<bdi dir="ltr">'.$amount.' USD</bdi>' : $amount.' USD';
$t = $ar ? [
    'h1' => 'تقرير يفهمه عملاؤك',
    'lead' => 'نتائج كل حملة حسب هدفها الحقيقي، بالعربية أو الإنجليزية، عبر رابط للقراءة فقط. قيد التطوير.',
    'banner' => 'نموذج ببيانات تجريبية مختلَقة، وليس عميلًا حقيقيًا.',
    'client' => 'مفروشات سِدرة (متجر أثاث وهمي، بيانات تجريبية)',
    'period' => 'الفترة: أكتوبر 2026 · العملة: <bdi dir="ltr">USD</bdi>',
    'kpis' => [
        ['الإنفاق الإعلاني', $money('18,450'), 'مقابل ميزانية شهرية مستهدفة '.$money('19,000')],
        ['النتائج حسب الهدف', '2,340', 'رسائل <bdi dir="ltr">WhatsApp</bdi>، ومكالمات مؤهلة، ومشتريات من الموقع، ونماذج عملاء محتملين'],
        ['تكلفة النتيجة', $money('7.88'), 'مقابل تكلفة مستهدفة '.$money('9.00')],
        ['مؤشر صحة العميل', '88 / 100', 'جيد'],
    ],
    'table_title' => 'أداء الحملات حسب الهدف',
    'table_label' => 'جدول الحملات',
    'cols' => ['الحملة', 'المنصة', 'الهدف', 'النتائج', 'تكلفة النتيجة', 'الإنفاق'],
    'demo' => 'تجريبي',
    'rows' => [
        ['أطقم غرف المعيشة', 'Meta', 'محادثات WhatsApp', '1,120', '6.10', '6,832'],
        ['بحث «كنب مودرن»', 'Google Ads', 'مكالمات مؤهلة', '485', '11.40', '5,529'],
        ['فيديو غرف النوم', 'TikTok', 'مشتريات من الموقع', '410', '8.90', '3,649'],
        ['عروض المجالس', 'Snapchat', 'نماذج عملاء محتملين', '325', '7.50', '2,440'],
    ],
    'cta' => 'انضم إلى قائمة الإطلاق',
] : [
    'h1' => 'A client report your clients can read',
    'lead' => 'Results by each campaign\'s real goal, in Arabic or English, through a read-only link. In development.',
    'banner' => 'Sample with made-up demo data. Not a real client.',
    'client' => 'Sidra Home Furnishings (fictional store, sample data)',
    'period' => 'Period: October 2026 · Currency: USD',
    'kpis' => [
        ['Ad spend', $money('18,450'), 'Against a monthly target of '.$money('19,000')],
        ['Results by goal', '2,340', 'WhatsApp conversations, qualified calls, website purchases and lead forms'],
        ['Cost per result', $money('7.88'), 'Against a target of '.$money('9.00')],
        ['Client health score', '88 / 100', 'Healthy'],
    ],
    'table_title' => 'Campaign results by goal',
    'table_label' => 'Campaign table',
    'cols' => ['Campaign', 'Platform', 'Goal', 'Results', 'Cost per result', 'Spend'],
    'demo' => 'demo',
    'rows' => [
        ['Living-room sets', 'Meta', 'WhatsApp conversations', '1,120', '6.10', '6,832'],
        ['"Modern sofa" search', 'Google Ads', 'Qualified calls', '485', '11.40', '5,529'],
        ['Bedroom video', 'TikTok', 'Website purchases', '410', '8.90', '3,649'],
        ['Majlis offers', 'Snapchat', 'Lead forms', '325', '7.50', '2,440'],
    ],
    'cta' => 'Join the launch list',
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.dr-banner{background:var(--info-bg);color:var(--ink);border:1px solid var(--acc);border-radius:12px;padding:12px 16px;margin:16px 0}
.dr-head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;border-bottom:1px solid var(--line);padding-bottom:12px}
.dr-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr));gap:12px;margin:16px 0;list-style:none;padding:0}
.dr-kpis li{border:1px solid var(--line);border-radius:12px;padding:14px}
.dr-kpis .v{display:block;font-size:22px;font-weight:700;color:var(--acc);margin:4px 0}
.dr-kpis .n{font-size:13px;color:var(--muted)}
.dr-table{overflow-x:auto}
.dr-table table{width:100%;border-collapse:collapse;font-size:15px}
.dr-table th,.dr-table td{padding:12px 10px;border-bottom:1px solid var(--line);text-align:start}
.dr-table th{color:var(--muted);font-weight:600;font-size:14px}
.dr-cards{display:none;list-style:none;padding:0;margin:0}
.dr-cards li{border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:10px}
.dr-cards dl{display:grid;grid-template-columns:auto 1fr;gap:4px 12px;margin:8px 0 0}
.dr-cards dt{color:var(--muted)}
.dr-cards dd{margin:0}
@media (max-width:599px){.dr-table{display:none}.dr-cards{display:block}}
</style>
@endpush

@section('hero')
<h1>{{ $t['h1'] }}</h1>
<p class="muted" style="font-size:17px;max-width:760px">{{ $t['lead'] }}</p>
<p class="muted" style="max-width:760px">{!! $ar ? 'تعرض البيانات من <a href="'.$u('/integrations').'">المنصات التي نخطط لربطها</a>.' : 'Data comes from <a href="'.$u('/integrations').'">the platforms we plan to connect</a>.' !!}</p>
@endsection

@section('content')
<p class="dr-banner" role="note">{{ $t['banner'] }}</p>
<section class="card" aria-labelledby="dr-client">
    <div class="dr-head">
        <h2 id="dr-client" style="margin:0;font-size:19px">{{ $t['client'] }}</h2>
        <span class="muted" style="font-size:14px">{!! $t['period'] !!}</span>
    </div>
    <ul class="dr-kpis">
        @foreach ($t['kpis'] as [$label, $value, $note])
            <li><span class="n">{{ $label }}</span><span class="v">{!! $value !!}</span><span class="n">{!! $note !!}</span></li>
        @endforeach
    </ul>
    <h3 style="margin-top:20px">{{ $t['table_title'] }}</h3>
    <div class="dr-table" tabindex="0" role="region" aria-label="{{ $t['table_label'] }}">
        <table>
            <thead><tr>@foreach ($t['cols'] as $col)<th scope="col">{{ $col }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($t['rows'] as [$name, $net, $goal, $results, $cpr, $spend])
                    <tr>
                        <th scope="row">{{ $name }} <span class="badge badge-neutral">{{ $t['demo'] }}</span></th>
                        <td><bdi dir="ltr">{{ $net }}</bdi></td>
                        <td>{{ $goal }}</td>
                        <td>{{ $results }}</td>
                        <td>{!! $money($cpr) !!}</td>
                        <td>{!! $money($spend) !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <ul class="dr-cards">
        @foreach ($t['rows'] as [$name, $net, $goal, $results, $cpr, $spend])
            <li>
                <strong>{{ $name }}</strong> <span class="badge badge-neutral">{{ $t['demo'] }}</span>
                <dl>
                    <dt>{{ $t['cols'][1] }}</dt><dd><bdi dir="ltr">{{ $net }}</bdi></dd>
                    <dt>{{ $t['cols'][2] }}</dt><dd>{{ $goal }}</dd>
                    <dt>{{ $t['cols'][3] }}</dt><dd>{{ $results }}</dd>
                    <dt>{{ $t['cols'][4] }}</dt><dd>{!! $money($cpr) !!}</dd>
                    <dt>{{ $t['cols'][5] }}</dt><dd>{!! $money($spend) !!}</dd>
                </dl>
            </li>
        @endforeach
    </ul>
</section>
<p style="margin-top:16px">{!! $ar
    ? 'يفتح عميلك التقرير برابط للقراءة فقط. <a href="'.$u('/security').'">كيف نحمي بيانات عملائك</a>'
    : 'Your client opens the report with a read-only link. <a href="'.$u('/security').'">How we protect your clients\' data</a>' !!}</p>
{{-- CL-1 rev 2 E-07 (Growth 08e §7): how to read the report. --}}
<section style="margin-top:24px;max-width:820px">
    <h2>{{ $ar ? 'كيف تقرأ هذا التقرير' : 'How to read this report' }}</h2>
    <ol>
        @foreach ($ar ? [
            ['النتائج حسب الهدف:', 'تُعدّ كل حملة بما صُممت له، فرسائل WhatsApp لا تُخلط بالمبيعات.'],
            ['الإنفاق مقابل الخطة:', 'ترى الإنفاق الفعلي بجانب الميزانية المستهدفة، وتكلفة النتيجة بجانب المستهدفة.'],
            ['مؤشر صحة العميل:', 'رقم من 0 إلى 100 يلخّص أداء العميل مقابل مستهدفاته، مع كلمة واحدة تشرحه.'],
        ] : [
            ['Results by goal:', 'each campaign is counted by what it was built for, so WhatsApp messages aren\'t mixed with sales.'],
            ['Spend against plan:', 'actual spend next to the target budget, and cost per result next to its target.'],
            ['Client health score:', 'a 0–100 number that sums up the client against their targets, with one word to explain it.'],
        ] as [$head, $body])
            <li style="margin-bottom:8px"><strong>{{ $head }}</strong> {{ $body }}</li>
        @endforeach
    </ol>
    <p>{!! $ar ? 'اعرف <a href="'.$u('/pricing').'">أي باقة تشمل هذا التقرير</a>.' : 'See <a href="'.$u('/pricing').'">which plan includes this report</a>.' !!}</p>
</section>
<p style="margin-top:24px"><a class="btn" href="{{ $u('/waitlist') }}">{{ $t['cta'] }}</a></p>
@endsection
