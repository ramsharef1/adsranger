<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Jobs\VerifyOwnLinks;
use App\Models\LinkRedirect;
use App\Models\OwnLink;
use App\Support\Links\OwnLinks;
use App\Support\Links\Reclaim;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** BX-07 link monitor (known links to us, monthly recheck, lost → red badge) + BX-09 link reclamation (404s × GSC → 301s). */
class LinksBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Links';

    protected static ?string $title = 'Links to AdsRanger';

    protected static ?string $slug = 'links';

    protected static ?int $navigationSort = 94;

    protected string $view = 'filament.admin.pages.links-board';

    public string $paste = '';

    public string $gsc = '';

    /** @var array<string, string> md5(404 path) → 301 target typed or suggested (paths can hold dots) */
    public array $targets = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = OwnLinks::lostCount();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function addLinks(): void
    {
        $n = OwnLinks::add($this->paste);
        $this->paste = '';
        Notification::make()->title(__(':n new links added. They are checked on the next run, or press Check now.', ['n' => $n]))->success()->send();
    }

    public function checkOne(int $id): void
    {
        $row = OwnLink::findOrFail($id);
        $status = OwnLinks::check($row);
        Notification::make()->title($status === 'live' ? __('Link is live.') : __('Link not found on that page.'))->{$status === 'live' ? 'success' : 'danger'}()->send();
    }

    public function checkAll(): void
    {
        VerifyOwnLinks::dispatch();
        Notification::make()->title(__('Check queued. Results show here in a few minutes.'))->success()->send();
    }

    public function archive(int $id): void
    {
        OwnLink::whereKey($id)->update(['status' => 'archived']);
    }

    public function restore(int $id): void
    {
        OwnLink::whereKey($id)->update(['status' => 'unchecked']);
    }

    public function importGsc(): void
    {
        $n = Reclaim::importGsc($this->gsc);
        $this->gsc = '';
        Notification::make()->title(__(':n rows read from the Search Console export.', ['n' => $n]))->{$n > 0 ? 'success' : 'warning'}()->send();
    }

    public function addRedirect(string $path): void
    {
        $to = trim($this->targets[md5($path)] ?? '');
        $err = Reclaim::addRedirect($path, $to, auth()->id());
        $msg = match ($err) {
            null => __('301 added.'),
            'from_is_live' => __('That address answers already, so a 301 is not needed.'),
            'target_not_live' => __('The target must be a live page on adsranger.com, not another redirect.'),
            default => __('Enter a target path like /pricing.'),
        };
        Notification::make()->title($msg)->{$err ? 'danger' : 'success'}()->send();
        if (! $err) {
            unset($this->targets[md5($path)]);
        }
    }

    public function ignore(string $path): void
    {
        Reclaim::ignore($path);
    }

    public function disableRedirect(int $id): void
    {
        Reclaim::disableRedirect($id);
    }

    protected function getViewData(): array
    {
        $suggestions = Reclaim::suggestions();
        foreach ($suggestions as $s) {
            $this->targets[md5($s['path'])] ??= (string) $s['suggest'];
        }

        return [
            'links' => OwnLink::orderByRaw("case status when 'lost' then 0 when 'unchecked' then 1 when 'live' then 2 else 3 end")->orderBy('name')->get(),
            'mix' => OwnLinks::mix(),
            'lost' => OwnLinks::lostCount(),
            'suggestions' => $suggestions,
            'redirects' => LinkRedirect::where('active', true)->orderByDesc('id')->get(),
            'renames' => Reclaim::renameCheck(),
            'gscAt' => DB::table('gsc_linked_pages')->max('imported_at'),
        ];
    }
}
