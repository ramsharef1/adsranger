<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use UnitEnum;

/** A-18 (IDEA-015): the workspace owner reads who did what in this workspace (rows recorded from cl96 on). */
class WorkspaceActivity extends Page
{
    use TranslatesPageLabels;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $title = 'Activity log';

    protected static ?string $slug = 'activity';

    protected static ?int $navigationSort = 87;

    protected string $view = 'filament.pages.workspace-activity';

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Agency && auth()->user()?->roleIn($tenant) === 'owner';
    }

    public function rows(): LengthAwarePaginator
    {
        $rows = AuditLog::with('user:id,name')->where('agency_id', Filament::getTenant()->getKey())
            ->latest('created_at')->latest('id')->paginate(50);

        $clientIds = $rows->getCollection()->where('subject_type', Client::class)->pluck('subject_id')->unique()->all();
        $clients = $clientIds ? Client::whereIn('id', $clientIds)->pluck('name', 'id') : collect();
        $rows->getCollection()->each(fn (AuditLog $r) => $r->setAttribute('client_name', $r->subject_type === Client::class ? $clients[$r->subject_id] ?? null : null));

        return $rows;
    }

    /** Plain words for an action code (never the raw code on screen, UX-258); Latin runs isolated in Arabic. */
    public static function label(string $action): string
    {
        // Codes contain dots, so they can't be translation-key paths: read the two arrays and look up directly.
        $actions = (array) __('activity.actions');
        $groups = (array) __('activity.groups');
        $group = $groups[strtok($action, '.')] ?? null;
        $text = $actions[$action] ?? ($group !== null ? __('activity.other', ['group' => $group]) : __('activity.unknown'));
        $html = e($text);

        return app()->getLocale() === 'ar' ? preg_replace('/[A-Za-z][A-Za-z0-9 .()\-]*[A-Za-z0-9)]|[A-Za-z]/', '<bdi>$0</bdi>', $html) : $html;
    }
}
