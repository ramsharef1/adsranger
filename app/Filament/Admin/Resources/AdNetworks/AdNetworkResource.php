<?php

namespace App\Filament\Admin\Resources\AdNetworks;

use App\Filament\Admin\Resources\AdNetworks\Pages\EditAdNetwork;
use App\Filament\Admin\Resources\AdNetworks\Pages\ListAdNetworks;
use App\Models\AdNetwork;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

class AdNetworkResource extends Resource
{
    protected static ?string $model = AdNetwork::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Ad Networks';

    protected static ?string $modelLabel = 'ad network';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 90;

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
        $record = $schema->getRecord();
        $def = $record instanceof AdNetwork ? $record->definition() : [];
        $key = $record?->key ?? 'network';

        $steps = '<ol style="list-style:decimal;padding-inline-start:1.25rem;display:grid;gap:.4rem">'
            .collect($def['steps'] ?? [])->map(fn ($s) => '<li>'.e($s).'</li>')->implode('')
            .'</ol>';
        if (! empty($def['console'])) {
            $steps .= '<p style="margin-top:.75rem"><a href="'.e($def['console']).'" target="_blank" rel="noopener noreferrer" style="color:#14B8A6;font-weight:600;text-decoration:underline">Open the '.e($def['name'] ?? '').' developer console &#8599;</a></p>';
        }

        $redirect = url('/app/connect/'.$key.'/callback');

        $keysTab = function (string $env, string $label) use ($def): Tab {
            $fields = collect($def['fields'] ?? [])->map(fn (array $f) => static::keyField($env, $f))->all();

            return Tab::make($label)->schema($fields ?: [
                Placeholder::make('none_'.$env)->hiddenLabel()->content('This network needs no keys.'),
            ]);
        };

        return $schema->columns(1)->components([
            Section::make('How to get these keys')
                ->description(($def['category'] ?? '').' network · build stage '.($def['stage'] ?? '-'))
                ->icon('heroicon-o-question-mark-circle')
                ->collapsible()
                ->schema([
                    Placeholder::make('how_to')->hiddenLabel()->content(new HtmlString($steps)),
                    Placeholder::make('redirect_url')
                        ->label('Redirect URL (paste this into the network console)')
                        ->content(new HtmlString('<code style="user-select:all;font-size:.85rem">'.e($redirect).'</code>')),
                ]),
            Section::make('Status')->columns(3)->schema([
                Select::make('status')->options(AdNetwork::STATUSES)->required()
                    ->helperText('Set to Approved / Live once the network approves the app.'),
                Select::make('environment')->label('Keys in use')
                    ->options(['sandbox' => 'Sandbox / test', 'live' => 'Live'])->required()
                    ->helperText('Which key set AdsRanger uses for this network.'),
                Toggle::make('enabled')->label('Visible to agencies')
                    ->helperText('When off, agencies cannot connect this network.'),
            ]),
            Section::make('Plans')->description('Which plans include this network (layer 2 of the access rules).')->schema([
                CheckboxList::make('plans')->hiddenLabel()->relationship('plans', 'name')->columns(3),
                Placeholder::make('last_test')->label('Last key test')
                    ->content(fn ($record) => $record?->last_tested_at ? $record->last_tested_at->diffForHumans().' · '.$record->last_test_result : 'Not tested yet. Use "Test keys" at the top.'),
            ]),
            Tabs::make('keys')->tabs([
                $keysTab('live', 'Live keys'),
                $keysTab('sandbox', 'Sandbox / test keys'),
            ]),
        ]);
    }

    protected static function keyField(string $env, array $field): TextInput
    {
        [$name, $label, $secret, $help] = $field;

        $input = TextInput::make("credentials_{$env}.{$name}")
            ->label($label)
            ->helperText($help)
            ->maxLength(4000);

        if ($secret) {
            $input->password()
                ->autocomplete('new-password')
                ->placeholder(function ($record) use ($env, $name) {
                    $value = $record?->{"credentials_{$env}"}[$name] ?? null;

                    return $value ? 'Saved ••••'.mb_substr($value, -4).' · type to replace' : 'Not set';
                });
        }

        return $input;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('stage')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('category')->badge()->color('gray'),
                TextColumn::make('stage')->sortable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (?string $state) => AdNetwork::STATUSES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'live' => 'success',
                        'testing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('environment')->label('Keys in use'),
                ToggleColumn::make('enabled')->label('Visible'),
                TextColumn::make('updated_at')->label('Updated')->since(),
            ])
            ->recordActions([
                EditAction::make()->label('Keys'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdNetworks::route('/'),
            'edit' => EditAdNetwork::route('/{record}/edit'),
        ];
    }
}
