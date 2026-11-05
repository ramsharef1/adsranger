<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\NetworkRequest;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/** #127 Tally of network requests and suggestions from every workspace, most-requested first (super admins). */
class NetworkRequests extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Network requests';

    protected static ?string $title = 'Network requests';

    protected static ?string $slug = 'network-requests';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.admin.pages.network-requests';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    protected function getViewData(): array
    {
        $rows = NetworkRequest::query()
            ->selectRaw('network_key, max(network_name) as network_name, count(distinct agency_id) as workspaces, count(*) as members, max(created_at) as last_at')
            ->groupBy('network_key')->orderByDesc('workspaces')->orderByDesc('members')->get();
        $notes = NetworkRequest::with('agency:id,name')->whereNotNull('note')->orderByDesc('created_at')->limit(50)->get();

        return ['rows' => $rows, 'notes' => $notes];
    }
}
