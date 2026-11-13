@php
    // PS-05 (D-296): an empty workspace can show sample data in one click; while it shows, one line says so and clears it.
    $agency = \Filament\Facades\Filament::getTenant();
    $canManage = $agency && \App\Support\TeamRoles::canManageTeam(auth()->user(), $agency);
    $present = $agency && \App\Support\DemoData::present($agency);
    $empty = $agency && ! $present && ! \App\Models\Client::where('agency_id', $agency->id)->exists();
@endphp
@if ($canManage && ($present || $empty))
    <form method="post" action="{{ url('/app/'.$agency->slug.'/sample-data/'.($present ? 'clear' : 'fill')) }}" data-sample-data
          style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:8px 16px;font-size:14px;background:{{ $present ? 'rgba(245,158,11,.14)' : 'rgba(13,148,136,.10)' }};border-bottom:1px solid rgba(148,163,184,.3)">
        @csrf
        <span>{{ $present ? __('Sample data is showing in this workspace. Its clients start with «Demo ·» and every file made from them is marked as sample data.') : __('Nothing here yet. Try the screens with sample clients first; you can clear them in one click.') }}</span>
        <x-filament::button type="submit" size="sm" :color="$present ? 'warning' : 'primary'" style="min-height:40px">{{ $present ? __('Clear sample data') : __('Show sample data here') }}</x-filament::button>
    </form>
@endif
