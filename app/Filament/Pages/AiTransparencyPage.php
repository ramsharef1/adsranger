<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\AiWorkspace\AiKill;
use App\Support\AiWorkspace\AiScope;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/**
 * FS-38 (D-299): what each AI feature sends, to whom and what it cannot do — written from the real code paths, so it
 * stays true. In the app now; Growth + Business write the public /ai-transparency page when the module goes on.
 */
class AiTransparencyPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'ai_workspace';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static string|UnitEnum|null $navigationGroup = 'AI Workspace';

    protected static ?string $navigationLabel = 'What AI sees';

    protected static ?string $title = 'What AI sees';

    protected static ?string $slug = 'ai-transparency';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.ai-transparency';

    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();
        $limited = Client::where('agency_id', $tenant->id)->whereNotNull('ai_scope')->get(['id', 'name', 'ai_scope'])
            ->map(fn (Client $c) => ['name' => $c->name, 'parts' => AiScope::allowed($c)])->all();

        return [
            'tools' => collect(AiKill::TOOLS)->map(fn ($label, $tool) => ['label' => $label, 'off' => AiKill::off($tool)])->all(),
            'limited' => $limited,
        ];
    }
}
