{{-- Panel styles that a theme build would hold (no Node on the server): UX-4 font fallback, UX-7 44 px targets on auth pages. --}}
<style>
    :root { --font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif; }
    .fi-simple-layout .fi-input-wrp, .fi-simple-layout .fi-btn { min-height: 44px; }
    .fi-simple-layout .fi-input-wrp .fi-input { min-height: 42px; }
    .fi-simple-layout .fi-input-wrp .fi-icon-btn { min-width: 44px; min-height: 44px; }
    .fi-simple-layout .fi-fo-checkbox-list-option-label,
    .fi-simple-layout .fi-fo-field-label { min-height: 44px; display: inline-flex; align-items: center; gap: .5rem; cursor: pointer; }
    .fi-simple-layout a.fi-link { min-height: 44px; display: inline-flex; align-items: center; }
    .ar-lang-switch { display: flex; justify-content: center; margin-top: 1rem; }
    .ar-lang-switch a { min-height: 44px; min-width: 44px; padding: 0 .9rem; display: inline-flex; align-items: center; border-radius: .5rem; font-size: .875rem; }
    .ar-lang-switch a:hover { background: rgba(148, 163, 184, .15); }
</style>
