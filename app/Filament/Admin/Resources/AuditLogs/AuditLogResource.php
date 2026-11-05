<?php

namespace App\Filament\Admin\Resources\AuditLogs;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Read-only view of every audited change on the platform. */
class AuditLogResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $slug = 'audit-log';

    protected static ?int $navigationSort = 95;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('created_at')->label(__('When'))->dateTime('Y-m-d H:i')->sortable(),
            TextColumn::make('user.email')->label(__('By'))->placeholder(__('system'))->searchable(),
            TextColumn::make('action')->badge()->searchable(),
            TextColumn::make('subject')->label(__('On'))
                ->state(fn (AuditLog $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : '—'),
            TextColumn::make('details')->label(__('Details'))->wrap()
                ->state(fn (AuditLog $r) => Str::limit(collect($r->meta ?? [])->map(fn ($v, $k) => $k.': '.(is_array($v) ? implode(', ', $v) : $v))->implode(' · '), 140)),
            TextColumn::make('ip')->label(__('IP'))->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('action')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
