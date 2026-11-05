<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Release;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * #76 What's new page (/app/{agency}/whats-new): bilingual product updates fed by the `releases` table.
 */
class WhatsNewPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = "What's new";

    protected static ?string $title = "What's new";

    protected static ?string $slug = 'whats-new';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.pages.whats-new';

    protected function getViewData(): array
    {
        return [
            'releases' => Release::feed(),
            'isSuperAdmin' => (bool) auth()->user()?->isSuperAdmin(),
        ];
    }
}
