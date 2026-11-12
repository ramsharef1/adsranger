@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/cod-poas-calculator',
    ['حاسبة أرباح الدفع عند الاستلام (POAS) | AdsRanger', 'MENA COD & BNPL Profit / POAS Calculator | AdsRanger'],
    ['احسب نقطة التعادل الحقيقية وصافي الربح وعائد POAS لمتاجر الدفع عند الاستلام وخدمات التقسيط في الخليج والأردن ومصر.',
        'Calculate Break-Even ROAS, Real Net Profit, and POAS for MENA e-commerce factoring COD delivery rates, return shipping, and Tabby/Tamara fees.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$curs = ['SAR', 'AED', 'JOD', 'EGP', 'KWD', 'USD'];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'حاسبة أرباح الدفع عند الاستلام وعائد الربح (POAS)' : 'MENA COD & BNPL Profit / POAS Calculator' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'اعرف ربحك الفعلي بعد احتساب نسبة تسليم طلبات الدفع عند الاستلام وتكلفة شحن المرتجعات وعمولة بوابات التقسيط.' : 'Factor COD delivery rates, round-trip return shipping, COGS, and Tabby/Tamara gateway fees to see real net profit and POAS.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="cod-form" onsubmit="return false" novalidate>
        <label for="cod-cur">{{ $ar ? 'العملة' : 'Currency' }}</label>
        <select id="cod-cur">@foreach ($curs as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>

        <label for="cod-aov">{{ $ar ? 'متوسط قيمة الطلب (AOV)' : 'Average order value (AOV)' }}</label>
        <input id="cod-aov" type="text" inputmode="decimal" dir="ltr" value="250">

        <label for="cod-cogs">{{ $ar ? 'نسبة تكلفة البضاعة المباعة (%)' : 'Product COGS (%)' }}</label>
        <input id="cod-cogs" type="text" inputmode="decimal" dir="ltr" value="32">

        <label for="cod-ship">{{ $ar ? 'تكلفة شحن الطلب الواحد' : 'Outbound shipping cost per order' }}</label>
        <input id="cod-ship" type="text" inputmode="decimal" dir="ltr" value="22">

        <label for="cod-ret">{{ $ar ? 'تكلفة شحن الطلب المرتجع' : 'Return shipping fee per undelivered order' }}</label>
        <input id="cod-ret" type="text" inputmode="decimal" dir="ltr" value="18">

        <label for="cod-deliv">{{ $ar ? 'نسبة نجاح التسليم والتحصيل (%)' : 'COD / order delivery success rate (%)' }}</label>
        <input id="cod-deliv" type="text" inputmode="decimal" dir="ltr" value="78">

        <label for="cod-fee">{{ $ar ? 'عمولة بوابة الدفع / تابي / تمارا (%)' : 'BNPL / payment gateway fee (%)' }}</label>
        <input id="cod-fee" type="text" inputmode="decimal" dir="ltr" value="4.5">

        <label for="cod-orders">{{ $ar ? 'إجمالي الطلبات المسجلة من الإعلانات' : 'Total placed orders from ads' }}</label>
        <input id="cod-orders" type="text" inputmode="decimal" dir="ltr" value="120">

        <label for="cod-spend">{{ $ar ? 'الإنفاق الإعلاني' : 'Ad spend' }}</label>
        <input id="cod-spend" type="text" inputmode="decimal" dir="ltr" value="6500">
    </form>
    <div class="tl-out" aria-live="polite">
        <ul class="tl-kv">
            <li><span>{{ $ar ? 'الطلبات المسلّمة فعليًا' : 'Delivered orders' }}</span><strong id="o-deliv" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'الإيراد المحصّل الفعلي' : 'Realized net revenue' }}</span><strong id="o-rev" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'ربح الطلب المتوقع قبل الإعلانات' : 'Contribution margin per placed order' }}</span><strong id="o-unit" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'نقطة تعادل العائد الإعلاني (Break-Even ROAS)' : 'Break-even platform ROAS' }}</span><strong id="o-beroas" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'عائد الربح على الإنفاق (POAS)' : 'Profit on Ad Spend (POAS)' }}</span><strong id="o-poas" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'صافي الربح النهائي بعد الإعلانات' : 'Real net profit after ads' }}</span><strong id="o-net" dir="ltr"></strong></li>
        </ul>
        <p class="muted" style="margin-top:14px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'cod-poas-calculator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); };
  function v(id){ var x = parseFloat($(id).value.replace(/,/g,'')); return isNaN(x) ? 0 : x; }
  function f(n, d){ return n.toLocaleString('en-US', {minimumFractionDigits:d, maximumFractionDigits:d}); }
  function run(){
    var cur = $('cod-cur').value, dec = (cur === 'JOD' || cur === 'KWD') ? 3 : 2;
    var aov = Math.max(0, v('cod-aov')), cogsPct = Math.min(100, Math.max(0, v('cod-cogs'))) / 100;
    var ship = Math.max(0, v('cod-ship')), ret = Math.max(0, v('cod-ret'));
    var deliv = Math.min(100, Math.max(0, v('cod-deliv'))) / 100;
    var fee = Math.min(100, Math.max(0, v('cod-fee'))) / 100;
    var orders = Math.max(0, v('cod-orders')), spend = Math.max(0, v('cod-spend'));

    var deliveredOrders = orders * deliv;
    var returnedOrders = orders * (1 - deliv);
    var realizedRev = deliveredOrders * aov;
    var cogsCost = deliveredOrders * aov * cogsPct;
    var gatewayCost = realizedRev * fee;
    var shippingTotal = (orders * ship) + (returnedOrders * ret);
    var grossContribution = realizedRev - cogsCost - gatewayCost - shippingTotal;
    var unitMargin = orders > 0 ? (grossContribution / orders) : 0;
    var beRoas = unitMargin > 0 ? (aov / unitMargin) : 0;
    var poas = spend > 0 ? (grossContribution / spend) : 0;
    var netProfit = grossContribution - spend;

    $('o-deliv').textContent = Math.round(deliveredOrders) + ' / ' + Math.round(orders);
    $('o-rev').textContent = f(realizedRev, dec) + ' ' + cur;
    $('o-unit').textContent = f(unitMargin, dec) + ' ' + cur;
    $('o-beroas').textContent = unitMargin > 0 ? f(beRoas, 2) + '×' : '—';
    $('o-poas').textContent = spend > 0 ? f(poas, 2) + '×' : '—';
    $('o-net').textContent = f(netProfit, dec) + ' ' + cur;
    $('o-net').style.color = netProfit >= 0 ? 'var(--acc)' : 'var(--bad)';
  }
  ['cod-aov','cod-cogs','cod-ship','cod-ret','cod-deliv','cod-fee','cod-orders','cod-spend'].forEach(function(id){ $(id).addEventListener('input', run); });
  $('cod-cur').addEventListener('change', run);
  run();
})();
</script>
@endpush
