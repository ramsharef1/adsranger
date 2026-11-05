<?php

namespace App\Filament\Admin\Resources\Plans;

use App\Filament\Admin\Resources\Plans\Pages\EditPlan;
use App\Filament\Admin\Resources\Plans\Pages\ListPlans;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PlanResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Plans';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            // Own key: «الباقة» (glossary D-43 via plan.plan); the calendar's button uses "Plan season" («الخطة») so bare "Plan" never collides with lang/en/plan.php.
            Section::make(__('plan.plan'))->columns(3)->schema([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(60),
                TextInput::make('key')->disabled(),
                TextInput::make('price_usd_month')->label(__('Price (USD / month)'))->numeric()->helperText(__('Shown later on the pricing page; billing comes later.')),
                TextInput::make('seats')->label(__('Team seats'))->numeric()->required()->minValue(1),
                TextInput::make('ad_accounts')->label(__('Tracked ad accounts'))->numeric()->required()->minValue(1),
                TextInput::make('clients')->label(__('Clients'))->numeric()->required()->minValue(1),
                Toggle::make('is_active')->label(__('Available for new agencies')),
            ]),
            Section::make(__('Networks in this plan'))->schema([
                CheckboxList::make('networks')->hiddenLabel()->relationship('networks', 'name')->columns(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->paginated(false)->columns([
            TextColumn::make('name')->label(__('Name'))->weight('bold'),
            TextColumn::make('price_usd_month')->label(__('USD / mo'))->placeholder('—'),
            TextColumn::make('seats'),
            TextColumn::make('ad_accounts')->label(__('Ad accounts')),
            TextColumn::make('clients'),
            TextColumn::make('networks_count')->counts('networks')->label(__('Networks')),
            IconColumn::make('is_active')->boolean()->label(__('Active')),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPlans::route('/'), 'edit' => EditPlan::route('/{record}/edit')];
    }
}
