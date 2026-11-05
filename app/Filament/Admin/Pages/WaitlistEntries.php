<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\WaitlistEntry;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/** Super-admin viewer for public /waitlist agency signups. */
class WaitlistEntries extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Waitlist';

    protected static ?string $title = 'Agency waitlist';

    protected static ?string $slug = 'waitlist';

    protected static ?int $navigationSort = 93;

    protected string $view = 'filament.admin.pages.waitlist-entries';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    protected function getViewData(): array
    {
        return [
            'entries' => WaitlistEntry::query()->orderByDesc('created_at')->limit(250)->get(),
            'total' => WaitlistEntry::count(),
        ];
    }
}
