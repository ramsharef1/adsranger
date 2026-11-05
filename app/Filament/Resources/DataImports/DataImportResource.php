<?php

namespace App\Filament\Resources\DataImports;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\DataImports\Pages\ListDataImports;
use App\Models\AdNetwork;
use App\Models\DataImport;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class DataImportResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = DataImport::class;

    protected static ?string $tenantRelationshipName = 'dataImports';

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Imports';

    protected static ?string $modelLabel = 'import';

    protected static ?string $slug = 'imports';

    protected static ?int $navigationSort = 23;

    public static function canCreate(): bool
    {
        return false;
    }

    /** Networks offered in the import form: API networks + import-first networks. */
    public static function networkOptions(): array
    {
        return AdNetwork::orderBy('name')->pluck('name', 'key')->all() + config('adsranger.import_networks');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No imports yet')
            ->emptyStateDescription('Import a CSV export or a Google Sheet from any network, including ones without an API.')
            ->columns([
                TextColumn::make('created_at')->label('When')->since(),
                TextColumn::make('network')->formatStateUsing(fn (string $state) => static::networkOptions()[$state] ?? $state)->weight('bold'),
                TextColumn::make('source')->badge()->color('gray'),
                TextColumn::make('filename')->label('File / sheet')->limit(40),
                TextColumn::make('rows')->numeric(),
                TextColumn::make('user.name')->label('By'),
            ])
            ->recordActions([
                Action::make('mapping')->label('Columns')->icon('heroicon-o-table-cells')->color('gray')
                    ->modalHeading('How columns were matched')->modalSubmitAction(false)
                    ->modalContent(fn (DataImport $r) => new HtmlString('<ul style="line-height:1.9">'.collect($r->mapping ?? [])->map(fn ($col, $f) => '<li><strong>'.e($f).'</strong> ← '.e($col).'</li>')->implode('').'</ul>')),
                Action::make('delete')->label('Delete')->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn () => TeamRoles::canManageData(auth()->user(), Filament::getTenant()))
                    ->requiresConfirmation()->modalDescription('Deletes this import and every data row it added.')
                    ->action(function (DataImport $r) {
                        $r->delete();
                        Notification::make()->title('Import deleted')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListDataImports::route('/')];
    }
}
