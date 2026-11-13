{{-- PS-25 (R5): shortcuts by key position (event.code), so they work with the Arabic keyboard layout on.
     "/" or Ctrl / ⌘ + K focus the panel search. --}}
<script>
    (() => {
        if (window.__arKeys) return;
        window.__arKeys = true;
        const typing = (el) => el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
        document.addEventListener('keydown', (e) => {
            const search = document.querySelector('.fi-global-search-field input, .fi-global-search input[type="search"]');
            if (! search) return;
            const modK = (e.ctrlKey || e.metaKey) && ! e.altKey && e.code === 'KeyK';
            const slash = ! e.ctrlKey && ! e.metaKey && ! e.altKey && e.code === 'Slash' && ! typing(document.activeElement);
            if (modK || slash) {
                e.preventDefault();
                search.focus();
                search.select?.();
            }
        }, true);
    })();
</script>
