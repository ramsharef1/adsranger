<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\Import\ImportReview;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** PS-02 / PS-03 (D-296): one table to confirm which client and which goal each imported campaign belongs to. */
class ImportReviewPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Sort imported campaigns';

    protected static ?string $title = 'Sort imported campaigns';

    protected static ?string $slug = 'import-review';

    protected static ?int $navigationSort = 41;

    protected string $view = 'filament.pages.import-review';

    /** @var array<string, array{client_id: ?string, result_type: ?string}> */
    public array $choices = [];

    public static function canAccess(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();
        $n = $tenant ? Cache::remember('import-review:'.$tenant->id, 300, fn () => count(ImportReview::pending($tenant))) : 0;

        return $n > 0 ? (string) $n : null;
    }

    public function mount(): void
    {
        $this->prefill();
    }

    /** Pre-fills each row with its suggestion; nothing is saved until Save (D-296). */
    public function prefill(): void
    {
        $this->choices = [];
        foreach (ImportReview::pending(Filament::getTenant()) as $p) {
            $this->choices[$p['key']] = [
                'client_id' => (string) ($p['client_id'] ?? $p['suggest_client'] ?? ''),
                'result_type' => (string) ($p['suggest_goal'] ?? $p['result_type']),
            ];
        }
    }

    public function save(): void
    {
        $r = ImportReview::apply(Filament::getTenant(), $this->choices);
        Cache::forget('import-review:'.Filament::getTenant()->id);
        Notification::make()->title(__('Saved: :c campaigns, :r rows', ['c' => $r['campaigns'], 'r' => $r['rows']]))->success()->send();
        $this->prefill();
    }

    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();

        return [
            'rows' => ImportReview::pending($tenant),
            'clients' => Client::where('agency_id', $tenant->id)->where('status', '!=', 'archived')->orderBy('name')->pluck('name', 'id'),
            'goals' => collect((array) config('results.labels'))->map(fn ($l) => app()->getLocale() === 'ar' ? $l[1] : $l[0]),
        ];
    }
}
