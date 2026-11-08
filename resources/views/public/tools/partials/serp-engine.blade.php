{{-- Shared width engine for the SERP preview and the length checker (PR-015 §5.2-3): one width table
     (resources/seo/serp_widths.json), canvas measureText, estimates only. Nothing leaves the browser. --}}
<script>
window.ArSerp = (function (W) {
  var ctx = document.createElement('canvas').getContext('2d');
  function measure(text, px) { ctx.font = px + 'px ' + W.font; return Math.round(ctx.measureText(text).width); }
  function fit(text, px, max) {
    if (measure(text, px) <= max) { return { text: text, cut: false, after: '' }; }
    var words = text.split(/\s+/), out = '';
    for (var i = 0; i < words.length; i++) {
      var next = out ? out + ' ' + words[i] : words[i];
      if (measure(next + ' …', px) > max) { return { text: (out || words[i].slice(0, 8)) + ' …', cut: true, after: out.split(/\s+/).pop() || '' }; }
      out = next;
    }
    return { text: out, cut: false, after: '' };
  }
  function firstStrong(text) { var m = text.match(/[A-Za-z؀-ۿ]/); return m ? m[0] : ''; }
  function latinFirst(text) { return /[؀-ۿ]/.test(text) && /[A-Za-z]/.test(firstStrong(text)); }
  function mixedDigits(text) { return /[٠-٩۰-۹]/.test(text) && /[0-9]/.test(text); }
  function chars(text) { return Array.from(text).length; }
  return { W: W, measure: measure, fit: fit, latinFirst: latinFirst, mixedDigits: mixedDigits, chars: chars };
})(@json($widths));
</script>
