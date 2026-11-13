<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\LiveVerification;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/** A-11 (FR-031): which ad network is really verified against the live API, and which is not yet. */
class LiveVerificationBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Live verification';

    protected static ?string $title = 'Live verification board';

    protected static ?string $slug = 'live-verification';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.admin.pages.live-verification';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    protected function getViewData(): array
    {
        $rows = LiveVerification::board();

        return ['rows' => $rows, 'verified' => count(array_filter($rows, fn ($r) => $r['level'] === 'verified')), 'days' => LiveVerification::VERIFIED_DAYS];
    }
}
