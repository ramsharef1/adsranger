<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\PartnerApplication;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/** Super-admin viewer for /partners design-partner applications (Website P-25, D-219). */
class PartnerApplications extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Partner applications';

    protected static ?string $title = 'Partner applications';

    protected static ?string $slug = 'partner-applications';

    protected static ?int $navigationSort = 94;

    protected string $view = 'filament.admin.pages.partner-applications';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    protected function getViewData(): array
    {
        return [
            'entries' => PartnerApplication::query()->orderByDesc('created_at')->limit(250)->get(),
            'total' => PartnerApplication::count(),
        ];
    }
}
