<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AiKey;
use App\Models\PlatformSetting;
use App\Support\AiWorkspace\GoldenSet;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/** WS-09 / FS-12 (D-300): the Arabic golden set, offline result + on-demand provider runs (cost shown first, never scheduled). */
class AiScorecardBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'AI scorecard';

    protected static ?string $title = 'AI scorecard';

    protected static ?string $slug = 'ai-scorecard';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.admin.pages.ai-scorecard';

    public ?int $keyId = null;

    public ?float $estimate = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function estimateRun(): void
    {
        $key = AiKey::find($this->keyId);
        $this->estimate = $key ? GoldenSet::estimate($key) : null;
    }

    public function run(): void
    {
        $key = AiKey::find($this->keyId);
        if (! $key || $this->estimate === null) {
            return;
        }
        $id = $key->id;
        dispatch(fn () => GoldenSet::live(AiKey::findOrFail($id)));
        $this->estimate = null;
        Notification::make()->title(__('Run queued. Refresh in a few minutes.'))->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'offline' => GoldenSet::offline(),
            'keys' => AiKey::with('agency')->orderBy('provider')->get(),
            'scorecard' => (array) PlatformSetting::get(GoldenSet::SCORECARD_KEY, []),
            'version' => GoldenSet::load()['version'],
        ];
    }
}
