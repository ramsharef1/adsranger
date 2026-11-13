<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\PublicSite;
use App\Support\Site\ReindexGate;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** CS-20 (D-279, D-290): held guides, posts and glossary terms, their gate checks, Growth's "ready" tick and the release. */
class ReindexGateBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Re-index gate';

    protected static ?string $title = 'Re-index gate';

    protected static ?string $slug = 'reindex-gate';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.admin.pages.reindex-gate';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function checkNow(): void
    {
        dispatch(fn () => ReindexGate::run());
        Notification::make()->title(__('Check queued. Refresh in a few minutes.'))->success()->send();
    }

    public function ready(string $path, bool $on = true): void
    {
        ReindexGate::markReady($path, auth()->id(), $on);
    }

    public function release(string $path): void
    {
        ReindexGate::release($path, auth()->id())
            ? Notification::make()->title(__('Released: back in the sitemap.'))->success()->send()
            : Notification::make()->title(__('Not released: it must pass every check and be ticked ready.'))->warning()->send();
    }

    public function hold(string $path): void
    {
        ReindexGate::hold($path);
    }

    protected function getViewData(): array
    {
        $rows = DB::table('page_gate')->orderByRaw('released_at is not null')->orderBy('kind')->orderBy('path')->get();

        return [
            'rows' => $rows,
            'heldAll' => ! PublicSite::articlesPublic(),
            'bands' => ReindexGate::BANDS,
            'minOut' => ReindexGate::MIN_OUT_LINKS,
            'lastCheck' => $rows->max('checked_at'),
        ];
    }
}
