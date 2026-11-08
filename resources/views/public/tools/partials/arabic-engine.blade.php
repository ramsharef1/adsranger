{{-- The Arabic text layer in the browser (SEO slice 0 step 0.1): the same normalise.js and rules file as the
     server, inlined; exposes window.ArNorm. Load page scripts as type="module" after this so they run in order. --}}
<script type="module">
{!! preg_replace('/^export\s+/m', '', file_get_contents(resource_path('js/arabic/normalise.js'))) !!}
window.ArNorm = createNormaliser(@json($arabicRules));
</script>
