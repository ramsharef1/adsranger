@php
    // EH-17 (D-293): the /admin announcement, for every workspace, closable per user (remembered in this browser only).
    $note = \App\Support\Announcements::current();
@endphp
@if ($note)
    @php $key = 'adsr-announcement-'.$note->id; $text = app()->getLocale() === 'ar' ? $note->body_ar : $note->body_en; @endphp
    <div x-data="{ shown: true, key: @js($key) }"
         x-init="try { shown = localStorage.getItem(key) !== '1' } catch (e) { shown = true }"
         x-show="shown" role="status" data-announcement
         style="display:flex;gap:10px;align-items:center;justify-content:space-between;padding:8px 16px;font-size:14px;background:{{ $note->tone === 'warning' ? 'rgba(245,158,11,.14)' : 'rgba(13,148,136,.12)' }};border-bottom:1px solid rgba(148,163,184,.3)">
        <span style="display:flex;gap:6px;align-items:center">
            <x-filament::icon :icon="$note->tone === 'warning' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-megaphone'" style="width:18px;height:18px;opacity:.8" />
            <bdi>{{ $text }}</bdi>
        </span>
        <button type="button" x-on:click="shown = false; try { localStorage.setItem(key, '1') } catch (e) {}"
                aria-label="{{ __('Close') }}" title="{{ __('Close') }}"
                style="background:transparent;border:0;cursor:pointer;min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center">
            <x-filament::icon icon="heroicon-m-x-mark" style="width:18px;height:18px" />
        </button>
    </div>
@endif
