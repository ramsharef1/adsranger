<?php

namespace App\Filament\Admin\Resources\Releases;

use App\Filament\Admin\Resources\Releases\Pages\CreateRelease;
use App\Filament\Admin\Resources\Releases\Pages\EditRelease;
use App\Filament\Admin\Resources\Releases\Pages\ListReleases;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\Release;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * #76 System admin CRUD for in-app What's new releases (/admin/releases).
 */
class ReleaseResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Release::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = "What's new";

    protected static ?string $modelLabel = 'release';

    protected static ?string $pluralModelLabel = 'Releases';

    protected static ?string $slug = 'releases';

    protected static ?int $navigationSort = 92;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('Release details'))->columns(3)->schema([
                TextInput::make('version')->label(__('Version'))->placeholder('v1.5')->required()->maxLength(40),
                DatePicker::make('date')->label(__('Release date'))->native()->required()->default(now()->toDateString()),
                Toggle::make('is_published')->label(__('Published'))->default(true),
                TextInput::make('title_ar')->label(__('Title (Arabic)'))->required()->maxLength(200)->columnSpan(3),
                TextInput::make('title_en')->label(__('Title (English)'))->required()->maxLength(200)->columnSpan(3),
                Textarea::make('items_ar')
                    ->label(__('Highlights (Arabic, one per line)'))
                    ->rows(4)
                    ->columnSpan(3)
                    ->formatStateUsing(fn (?Release $record): string => $record ? implode("\n", $record->itemsForLocale('ar')) : ''),
                Textarea::make('items_en')
                    ->label(__('Highlights (English, one per line)'))
                    ->rows(4)
                    ->columnSpan(3)
                    ->formatStateUsing(fn (?Release $record): string => $record ? implode("\n", $record->itemsForLocale('en')) : ''),
            ]),
        ]);
    }

    /**
     * Pack `items_ar` and `items_en` textarea lines into the `items` JSON column.
     */
    public static function packItems(array $data): array
    {
        $split = fn (?string $s): array => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $s) ?: []), fn ($v) => $v !== ''));
        $data['items'] = [
            'ar' => $split($data['items_ar'] ?? ''),
            'en' => $split($data['items_en'] ?? ''),
        ];
        unset($data['items_ar'], $data['items_en']);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('date', 'desc')->columns([
            TextColumn::make('version')->label(__('Version'))->badge()->searchable(),
            TextColumn::make('date')->label(__('Release date'))->date('Y-m-d')->sortable(),
            TextColumn::make('title_ar')->label(__('Title (Arabic)'))->searchable()->limit(50),
            TextColumn::make('title_en')->label(__('Title (English)'))->searchable()->limit(50),
            IconColumn::make('is_published')->boolean()->label(__('Published')),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReleases::route('/'),
            'create' => CreateRelease::route('/create'),
            'edit' => EditRelease::route('/{record}/edit'),
        ];
    }
}
