{{-- AR/EN switch on auth pages (UX-3). Keeps the current page, changes ?lang=. --}}
<div class="ar-lang-switch">
    <a href="{{ request()->fullUrlWithQuery(['lang' => __('ui.switch_locale')]) }}" lang="{{ __('ui.switch_locale') }}" hreflang="{{ __('ui.switch_locale') }}">{{ __('ui.switch_to') }}</a>
</div>
