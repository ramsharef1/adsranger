<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\AdminShell;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * A-12 (FR-029) live viewer in /admin › Guards: checks every Done/Live row in config/feature_register.php
 * against real classes, routes, views, configs, and PHPUnit test files.
 */
class RegisterGuardBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Register-to-code guard';

    protected static ?string $title = 'Register-to-code guard';

    protected static ?string $slug = 'register-guard';

    protected static ?int $navigationSort = 94;

    protected string $view = 'filament.admin.pages.register-guard';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    protected function getViewData(): array
    {
        return AdminShell::registerAudit();
    }
}
