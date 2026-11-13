<x-filament-panels::page>
    @php($rows = $this->rows())
    @php($tz = \Filament\Facades\Filament::getTenant()->timezone ?: 'UTC')
    <x-filament::section>
        <x-slot name="description">{{ __('Who changed what in this workspace, newest first. Only the owner sees this page. Changes are recorded from 13 November 2026.') }}</x-slot>
        @forelse ($rows as $r)
            <div style="display:flex;flex-wrap:wrap;gap:4px 16px;padding:10px 0;border-bottom:1px solid rgba(127,127,127,.2)" data-activity>
                <time datetime="{{ $r->created_at?->toIso8601String() }}" style="font-size:13px;opacity:.7;min-width:150px">{{ $r->created_at?->timezone($tz)->locale(app()->getLocale())->translatedFormat(app()->getLocale() === 'ar' ? 'j F Y، g:i A' : 'j M Y, g:i a') }}</time>
                <span><strong><bdi>{{ $r->user?->name ?? __('AdsRanger (automatic)') }}</bdi></strong> · {!! \App\Filament\Pages\WorkspaceActivity::label($r->action) !!}@if ($r->client_name) · <bdi>{{ $r->client_name }}</bdi>@endif</span>
            </div>
        @empty
            <p style="opacity:.7">{{ __('Nothing recorded yet. Changes made in this workspace will show here.') }}</p>
        @endforelse
        <div style="margin-top:12px">{{ $rows->links() }}</div>
    </x-filament::section>
</x-filament-panels::page>
