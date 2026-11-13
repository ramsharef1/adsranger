{{-- BX-03 "Cite this": a ready citation + a plain HTML link, with a stable #cite anchor. Params: $ar, $citeTitle, $citeUrl, $citeLicense (optional). --}}
@php
    $today = now('Asia/Amman');
    $date = $ar ? $today->locale('ar')->translatedFormat('j F Y') : $today->format('j F Y');
    $text = $ar
        ? 'المصدر: '.$citeTitle.'، AdsRanger، '.$citeUrl.' (اطّلعتُ عليه في '.$date.')'.(! empty($citeLicense) ? '. الترخيص: '.$citeLicense.'.' : '.')
        : 'AdsRanger ('.$today->format('Y').'). '.$citeTitle.'. Retrieved '.$date.', from '.$citeUrl.(! empty($citeLicense) ? '. Licence: '.$citeLicense.'.' : '.');
    $html = '<a href="'.$citeUrl.'">'.e($citeTitle).' · AdsRanger</a>';
@endphp
<section id="cite" class="card cite" style="padding:20px;margin-top:24px" aria-labelledby="cite-h">
    <h2 id="cite-h" style="font-size:19px;margin:0 0 8px">{{ $ar ? 'استشهد بهذه الصفحة' : 'Cite this page' }}</h2>
    <p class="muted" style="margin:0 0 8px">{{ $ar ? 'يمكنك الاقتباس من هذه الصفحة مع ذكر المصدر ورابطه.' : 'You can quote this page with credit and a link.' }}</p>
    <p style="margin:0 0 8px"><span data-cite-text dir="auto">{{ $text }}</span></p>
    <button type="button" class="btn btn-ghost" data-copy="{{ $text }}" style="min-height:44px">{{ $ar ? 'نسخ الاقتباس' : 'Copy citation' }}</button>
    <details style="margin-top:12px">
        <summary style="cursor:pointer;min-height:44px;display:flex;align-items:center">{{ $ar ? 'رابط HTML لموقعك' : 'HTML link for your site' }}</summary>
        <pre style="white-space:pre-wrap;word-break:break-all;margin:8px 0" dir="ltr"><code>{{ $html }}</code></pre>
        <button type="button" class="btn btn-ghost" data-copy="{{ $html }}" style="min-height:44px">{{ $ar ? 'نسخ الرابط' : 'Copy link' }}</button>
    </details>
    <p role="status" aria-live="polite" class="muted" data-copied hidden style="margin:8px 0 0">{{ $ar ? 'نُسخ.' : 'Copied.' }}</p>
</section>
@once
@push('scripts')
<script>
document.addEventListener('click',function(e){var b=e.target.closest('[data-copy]');if(!b){return;}
  var t=b.getAttribute('data-copy'),s=b.closest('section').querySelector('[data-copied]');
  (navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).catch(function(){var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();try{document.execCommand('copy');}catch(x){}a.remove();})
  .then(function(){if(s){s.hidden=false;setTimeout(function(){s.hidden=true;},2500);}});});
</script>
@endpush
@endonce
