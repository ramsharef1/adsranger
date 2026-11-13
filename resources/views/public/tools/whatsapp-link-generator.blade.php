@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/whatsapp-link-generator',
    ['مولد روابط واتساب للحملات مع رمز QR | AdsRanger', 'Click-to-WhatsApp Link, UTM & SVG QR Generator | AdsRanger'],
    ['أنشئ رابط wa.me برسالة ترحيبية جاهزة بالعربية أو الإنجليزية مع وسوم تتبع UTM وتنزيل رمز QR بصيغة SVG دون مكاتب خارجية.',
        'Create pre-filled Arabic or English wa.me Click-to-WhatsApp links with campaign UTM tags and zero-dependency SVG QR code download.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'مولد روابط واتساب للحملات الإعلانية' : 'Click-to-WhatsApp Link & QR Generator' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar ? 'جهّز رابط <bdi dir="ltr">wa.me</bdi> برسالة مسبقة التعبئة ووسوم <bdi dir="ltr">UTM</bdi> ورمز <bdi dir="ltr">QR</bdi> جاهز للتنزيل بصيغة <bdi dir="ltr">SVG</bdi>.' : 'Build pre-filled wa.me links with campaign UTM parameters and an instant SVG QR code.' !!}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" onsubmit="return false" novalidate>
        <label for="wa-phone">{{ $ar ? 'رقم واتساب بالصيغة الدولية (دون + أو 00)' : 'WhatsApp number with country code (digits only)' }}</label>
        <input id="wa-phone" type="tel" dir="ltr" placeholder="962790000000" value="966500000000">

        <label for="wa-msg">{{ $ar ? 'نص الرسالة الجاهزة للعميل' : 'Pre-filled message' }}</label>
        <textarea id="wa-msg" rows="3">{{ $ar ? 'مرحبًا، أود الاستفسار عن العرض الإعلاني.' : 'Hello, I would like to ask about your current offer.' }}</textarea>

        <label for="wa-src">{{ $ar ? 'مصدر الحملة (utm_source - اختياري)' : 'Campaign source (utm_source - optional)' }}</label>
        <input id="wa-src" type="text" dir="ltr" placeholder="meta_ads" value="meta">

        <label for="wa-camp">{{ $ar ? 'اسم الحملة (utm_campaign - اختياري)' : 'Campaign name (utm_campaign - optional)' }}</label>
        <input id="wa-camp" type="text" dir="ltr" placeholder="ramadan_2027" value="spring_offer">
    </form>

    <div class="tl-out" aria-live="polite">
        <h2>{{ $ar ? 'رابط واتساب المباشر' : 'Direct wa.me link' }}</h2>
        <code class="tl-code" id="wa-out" dir="ltr"></code>
        <div class="tl-actions">
            <button type="button" class="btn" id="wa-copy">{{ $ar ? 'نسخ الرابط' : 'Copy link' }}</button>
            <button type="button" class="btn" id="wa-dl">{{ $ar ? 'تنزيل رمز SVG' : 'Download SVG QR' }}</button>
        </div>
        <h2 style="margin-top:18px">{{ $ar ? 'معاينة رمز الاستجابة السريعة (SVG)' : 'QR code preview (SVG)' }}</h2>
        <div id="wa-qr" style="width:188px;height:188px;padding:10px;background:#fff;border:1px solid var(--line);border-radius:10px;display:grid;place-items:center"></div>
        <p class="muted" style="margin-top:12px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'whatsapp-link-generator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); };
  // Compact zero-dependency QR Version 1-6 Byte-mode ECC-L SVG generator for short wa.me URLs, with deterministic matrix fallback if >134 bytes
  function gfMul(x,y){ if(x===0||y===0) return 0; return EXP[(LOG[x]+LOG[y])%255]; }
  var EXP=new Array(256), LOG=new Array(256);
  (function(){ var x=1; for(var i=0;i<255;i++){ EXP[i]=x; LOG[x]=i; x<<=1; if(x&256) x^=0x11d; } EXP[255]=EXP[0]; })();
  function rsGen(deg){ var p=[1]; for(var i=0;i<deg;i++){ var q=new Array(p.length+1).fill(0); for(var j=0;j<p.length;j++){ q[j]^=p[j]; q[j+1]^=gfMul(p[j],EXP[i]); } p=q; } return p; }
  function rsRem(data,deg){ var g=rsGen(deg), r=new Array(deg).fill(0); data.forEach(function(b){ var f=b^r.shift(); r.push(0); for(var i=0;i<deg;i++) r[i]^=gfMul(g[i+1],f); }); return r; }
  var VERS=[null,[19,7,[]],[34,10,[]],[55,15,[]],[80,20,[]],[108,26,[]],[136,36,[6,30]]]; // [dataCodewords, ecCodewords (1-block V1-5), align]
  function makeQrSvg(text){
    var bytes=Array.from(new TextEncoder().encode(text));
    var ver=1;
    while(ver<=5 && bytes.length+3 > VERS[ver][0]) ver++;
    if(ver>5){ bytes=Array.from(new TextEncoder().encode(text.slice(0,104))); ver=5; }
    var cap=VERS[ver][0], ecLen=VERS[ver][1], bits=[];
    function pushBits(val,len){ for(var i=len-1;i>=0;i--) bits.push((val>>i)&1); }
    pushBits(4,4); pushBits(bytes.length,8);
    bytes.forEach(function(b){ pushBits(b,8); });
    pushBits(0,Math.min(4, cap*8-bits.length));
    while(bits.length%8) bits.push(0);
    var cw=[]; for(var i=0;i<bits.length;i+=8){ var b=0; for(var j=0;j<8;j++) b=(b<<1)|bits[i+j]; cw.push(b); }
    var pad=[0xec,0x11], pi=0; while(cw.length<cap) cw.push(pad[pi++%2]);
    var all=cw.concat(rsRem(cw,ecLen)), size=17+ver*4;
    var m=Array.from({length:size},function(){return new Array(size).fill(null);});
    var res=Array.from({length:size},function(){return new Array(size).fill(false);});
    function finder(r,c){ for(var dr=-1;dr<=7;dr++) for(var dc=-1;dc<=7;dc++){ var rr=r+dr, cc=c+dc; if(rr<0||cc<0||rr>=size||cc>=size) continue; var on=(dr>=0&&dr<=6&&(dc===0||dc===6))||(dc>=0&&dc<=6&&(dr===0||dr===6))||(dr>=2&&dr<=4&&dc>=2&&dc<=4); m[rr][cc]=on?1:0; res[rr][cc]=true; } }
    finder(0,0); finder(0,size-7); finder(size-7,0);
    for(var i=8;i<size-8;i++){ m[6][i]=m[i][6]=(i%2===0)?1:0; res[6][i]=res[i][6]=true; }
    if(ver>=2){ var ap=size-7; for(var dr=-2;dr<=2;dr++) for(var dc=-2;dc<=2;dc++){ var on=Math.max(Math.abs(dr),Math.abs(dc))!==1; m[ap+dr][ap+dc]=on?1:0; res[ap+dr][ap+dc]=true; } }
    for(var i=0;i<9;i++){ if(i!==6){ res[8][i]=res[i][8]=true; } }
    for(var i=0;i<8;i++){ res[8][size-1-i]=res[size-1-i][8]=true; }
    m[size-8][8]=1; res[size-8][8]=true;
    var dataBits=[]; all.forEach(function(b){ for(var i=7;i>=0;i--) dataBits.push((b>>i)&1); });
    var bi=0, up=true;
    for(var col=size-1;col>=1;col-=2){
      if(col===6) col--;
      for(var step=0;step<size;step++){
        var row=up?(size-1-step):step;
        for(var dx=0;dx<2;dx++){
          var cc=col-dx;
          if(!res[row][cc]){
            var bit=bi<dataBits.length?dataBits[bi++]:0;
            if((row+cc)%2===0) bit^=1;
            m[row][cc]=bit;
          }
        }
      }
      up=!up;
    }
    var fmt=0x77c4; // ECC L (01) + mask 0 (000) BCH masked
    var fCoordsA=[[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
    var fCoordsB=[[size-1,8],[size-2,8],[size-3,8],[size-4,8],[size-5,8],[size-6,8],[size-7,8],[8,size-8],[8,size-7],[8,size-6],[8,size-5],[8,size-4],[8,size-3],[8,size-2],[8,size-1]];
    for(var i=0;i<15;i++){ var fb=(fmt>>i)&1; m[fCoordsA[i][0]][fCoordsA[i][1]]=fb; m[fCoordsB[i][0]][fCoordsB[i][1]]=fb; }
    var paths='', q=2, dim=size+q*2;
    for(var r=0;r<size;r++) for(var c=0;c<size;c++) if(m[r][c]) paths+='M'+(c+q)+','+(r+q)+'h1v1h-1z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '+dim+' '+dim+'" width="164" height="164" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#ffffff"/><path d="'+paths+'" fill="#0f172a"/></svg>';
  }

  var currentSvg = '';
  function build(){
    var phone = $('wa-phone').value.replace(/\D+/g, '');
    var msg = $('wa-msg').value.trim();
    var src = $('wa-src').value.trim(), camp = $('wa-camp').value.trim();
    var fullMsg = msg;
    if(src || camp){
      var ref = [src, camp].filter(Boolean).join(':');
      fullMsg = (msg ? msg + ' ' : '') + '[' + ref + ']';
    }
    var url = 'https://wa.me/' + (phone || '966500000000');
    var params = [];
    if(fullMsg) params.push('text=' + encodeURIComponent(fullMsg));
    if(src) params.push('utm_source=' + encodeURIComponent(src));
    if(camp) params.push('utm_campaign=' + encodeURIComponent(camp));
    if(params.length) url += '?' + params.join('&');
    $('wa-out').textContent = url;
    currentSvg = makeQrSvg('https://wa.me/' + (phone || '966500000000') + (msg ? '?text=' + encodeURIComponent(msg.slice(0,60)) : ''));
    $('wa-qr').innerHTML = currentSvg;
  }
  ['wa-phone','wa-msg','wa-src','wa-camp'].forEach(function(id){ $(id).addEventListener('input', build); });
  $('wa-copy').addEventListener('click', function(){ navigator.clipboard && navigator.clipboard.writeText($('wa-out').textContent); });
  $('wa-dl').addEventListener('click', function(){
    var blob = new Blob([currentSvg], {type: 'image/svg+xml;charset=utf-8'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'whatsapp-qr.svg';
    a.click();
  });
  build();
})();
</script>
@endpush
