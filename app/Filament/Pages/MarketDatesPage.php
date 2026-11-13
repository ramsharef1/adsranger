<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\MarketDate;
use App\Support\MarketDates;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * PS-21 / XS-19 / XS-20 (R5, D-307): the workspace's paydays and school / exam periods per country. The agency enters
 * or confirms them; they show on client charts and the client calendar as "usually".
 */
class MarketDatesPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Paydays and school dates';

    protected static ?string $title = 'Paydays and school dates';

    protected static ?string $slug = 'market-dates';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.market-dates';

    protected function canEdit(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    protected function getHeaderActions(): array
    {
        $countries = config('adsranger.countries');
        $country = Select::make('country')->label(__('Country'))->options($countries)->searchable()->required()->default(Filament::getTenant()?->country ?: 'SA');
        $source = TextInput::make('source')->label(__('Source (link or note, optional)'))->maxLength(255);

        return [
            Action::make('addPayday')->label(__('Add payday'))->icon('heroicon-o-banknotes')->visible(fn () => $this->canEdit())
                ->modalDescription(__('The day salaries usually arrive in this country. Shown on charts and the calendar as "usually", never as a fixed rule.'))
                ->schema([
                    $country,
                    Select::make('day_of_month')->label(__('Day of the month'))->required()
                        ->options([0 => __('Last day of the month')] + collect(range(1, 31))->mapWithKeys(fn ($d) => [$d => (string) $d])->all()),
                    TextInput::make('label')->label(__('Label (optional)'))->maxLength(120)->placeholder(__('e.g. Government salaries')),
                    $source,
                ])
                ->action(function (array $data): void {
                    $this->store(['kind' => 'payday', 'country' => $data['country'], 'day_of_month' => (int) $data['day_of_month'], 'label' => $data['label'] ?? null, 'source' => $data['source'] ?? null]);
                }),
            Action::make('addPeriod')->label(__('Add school or exam period'))->icon('heroicon-o-academic-cap')->color('gray')->visible(fn () => $this->canEdit())
                ->schema([
                    $country,
                    Select::make('kind')->label(__('Type'))->options(['school_holiday' => __('School holiday'), 'exams' => __('Exams')])->required()->default('school_holiday'),
                    TextInput::make('label')->label(__('Label (optional)'))->maxLength(120)->placeholder(__('e.g. Mid-year break')),
                    DatePicker::make('starts_on')->label(__('From'))->required()->native(false),
                    DatePicker::make('ends_on')->label(__('To'))->required()->native(false)->afterOrEqual('starts_on'),
                    $source,
                ])
                ->action(function (array $data): void {
                    $this->store(['kind' => $data['kind'], 'country' => $data['country'], 'label' => $data['label'] ?? null, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'source' => $data['source'] ?? null]);
                }),
        ];
    }

    /** @param  array<string, mixed>  $row */
    protected function store(array $row): void
    {
        $a = Filament::getTenant();
        abort_unless($this->canEdit() && array_key_exists($row['country'], config('adsranger.countries')), 403);
        foreach (['label', 'source'] as $k) {
            $row[$k] = trim(strip_tags((string) ($row[$k] ?? ''))) ?: null;
        }
        MarketDate::create($row + ['agency_id' => $a->id, 'created_by' => auth()->id()]);
        Notification::make()->title(__('Saved.'))->success()->send();
    }

    public function remove(int $id): void
    {
        abort_unless($this->canEdit(), 403);
        MarketDate::where('agency_id', Filament::getTenant()->id)->whereKey($id)->delete();
    }

    protected function getViewData(): array
    {
        $rows = MarketDate::where('agency_id', Filament::getTenant()->id)->orderBy('country')->orderBy('kind')->orderBy('starts_on')->orderBy('day_of_month')->get();

        return ['rows' => $rows, 'labels' => MarketDates::kindLabels(), 'countries' => config('adsranger.countries'), 'canEdit' => $this->canEdit()];
    }
}
