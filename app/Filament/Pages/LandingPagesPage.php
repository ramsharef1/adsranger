<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\GatedByPlan;
use App\Jobs\CheckLandingPages;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\LandingPage;
use App\Support\LandingPages;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** Landing page health (#49): broken, slow, redirecting or insecure pages behind the ads. */
class LandingPagesPage extends Page
{
    use GatedByPlan;

    protected static string $planFeature = 'landing_pages';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Landing pages';

    protected static ?string $title = 'Landing page health';

    protected static ?string $slug = 'landing-pages';

    protected static ?int $navigationSort = 26;

    protected string $view = 'filament.pages.landing-pages';

    public ?int $clientId = null;

    public string $newUrl = '';

    public ?int $newClient = null;

    protected function visibleIds(): array
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    protected function canManage(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    public function add(): void
    {
        $a = Filament::getTenant();
        if (! $this->canManage()) {
            abort(403);
        }
        $this->validate(['newUrl' => 'required|url:http,https|max:1000', 'newClient' => 'nullable|integer']);
        if ($this->newClient && ! in_array($this->newClient, $this->visibleIds(), true)) {
            abort(403);
        }
        $url = LandingPages::normalize($this->newUrl);
        if (! $url) {
            $this->addError('newUrl', 'Enter a full http(s) address.');

            return;
        }
        $lp = LandingPage::firstOrCreate(['agency_id' => $a->id, 'url_hash' => sha1($url)], ['url' => $url, 'client_id' => $this->newClient, 'source' => 'manual', 'last_seen_at' => now()]);
        LandingPages::check($lp);
        AuditLog::record('landing_page.added', $a, ['landing_page_id' => $lp->id]);
        $this->newUrl = '';
        Notification::make()->title('Added and checked')->success()->send();
    }

    public function checkOne(int $id): void
    {
        if (! $this->canManage()) {
            abort(403);
        }
        $lp = $this->row($id);
        $key = 'lp-check:'.$lp->id;
        if (Cache::has($key)) {
            Notification::make()->title('Checked less than a minute ago')->info()->send();

            return;
        }
        Cache::put($key, 1, 60);
        LandingPages::check($lp);
        Notification::make()->title('Checked: '.LandingPages::stateLabel($lp->state)[0])->send();
    }

    public function checkAll(): void
    {
        $a = Filament::getTenant();
        if (! $this->canManage()) {
            abort(403);
        }
        $key = 'lp-check-all:'.$a->id;
        if (Cache::has($key)) {
            Notification::make()->title('A full check ran less than 30 minutes ago')->info()->send();

            return;
        }
        Cache::put($key, 1, 1800);
        CheckLandingPages::dispatch($a->id);
        Notification::make()->title('Checking all pages in the background')->body('Reload in a few minutes.')->success()->send();
    }

    public function remove(int $id): void
    {
        if (! $this->canManage()) {
            abort(403);
        }
        $lp = $this->row($id);
        AuditLog::record('landing_page.removed', Filament::getTenant(), ['url' => $lp->url]);
        $lp->delete();
    }

    protected function row(int $id): LandingPage
    {
        $vis = $this->visibleIds();

        return LandingPage::where('agency_id', Filament::getTenant()->id)
            ->where(fn ($q) => $q->whereIn('client_id', $vis)->orWhereNull('client_id'))->findOrFail($id);
    }

    protected function getViewData(): array
    {
        $vis = $this->visibleIds();
        $q = LandingPage::with('client')->where('agency_id', Filament::getTenant()->id)
            ->where(fn ($w) => $w->whereIn('client_id', $vis)->orWhereNull('client_id'));
        if ($this->clientId) {
            $q->where('client_id', $this->clientId);
        }
        $pages = $q->orderByRaw("case state when 'broken' then 0 when 'slow' then 1 when 'redirects' then 2 when 'insecure' then 3 when 'unchecked' then 4 else 5 end")
            ->orderByDesc('clicks_30d')->limit(300)->get();

        return ['pages' => $pages, 'clients' => Client::whereIn('id', $vis)->orderBy('name')->get(), 'canManage' => $this->canManage(),
            'counts' => $pages->countBy('state')->all()];
    }
}
