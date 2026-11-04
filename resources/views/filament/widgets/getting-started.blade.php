<x-filament-widgets::widget>
    @if ($done < $total)
    <x-filament::section>
        <x-slot name="heading">Getting started · {{ $done }} of {{ $total }} done</x-slot>
        <div style="display:grid;gap:10px">
            @foreach ($steps as [$title, $hint, $isDone, $url])
                <a href="{{ $url }}" style="display:flex;gap:12px;align-items:flex-start;padding:10px 12px;border-radius:10px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit;{{ $isDone ? 'opacity:.6' : '' }}">
                    <span style="flex:none;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;{{ $isDone ? 'background:#14B8A6;color:#0B1020' : 'border:2px solid #14B8A6;color:#14B8A6' }}">{{ $isDone ? '✓' : $loop->iteration }}</span>
                    <span><strong style="display:block;font-size:14px">{{ $title }}</strong><span style="font-size:13px;opacity:.75">{{ $hint }}</span></span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
    @endif
</x-filament-widgets::widget>
