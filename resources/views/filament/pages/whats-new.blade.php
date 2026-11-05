@php
    $muted = 'opacity:.68;font-size:13px';
    $badge = 'display:inline-flex;align-items:center;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:600;background:rgba(20,184,166,.14);color:#0F766E;border:1px solid rgba(20,184,166,.35)';
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:4px">
        <div style="{{ $muted }}">{{ __('New features, connector updates, and improvements across AdsRanger.') }}</div>
        @if ($isSuperAdmin)
            <a href="{{ url('/admin/releases') }}" style="font-size:13px;color:#0F766E;font-weight:600;text-decoration:none">
                {{ __('Manage releases') }} →
            </a>
        @endif
    </div>

    @forelse ($releases as $release)
        <x-filament::section>
            <x-slot name="heading">
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
                    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                        <span style="{{ $badge }}"><bdo dir="ltr">{{ $release->version }}</bdo></span>
                        <span>{{ $release->title() }}</span>
                    </div>
                    <span style="{{ $muted }}">
                        {{ $release->date?->translatedFormat('j M Y') }}
                    </span>
                </div>
            </x-slot>

            @php $items = $release->itemsForLocale(); @endphp
            @if (count($items))
                <ul style="margin:0;padding-inline-start:20px;display:grid;gap:6px;line-height:1.65">
                    @foreach ($items as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @else
                <div style="{{ $muted }}">{{ __('No release notes listed for this version.') }}</div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <div style="{{ $muted }}">{{ __('No releases published yet.') }}</div>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
