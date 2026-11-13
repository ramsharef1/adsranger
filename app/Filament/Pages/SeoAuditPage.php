<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Jobs\RunSeoAudit;
use App\Models\Client;
use App\Models\SeoAudit;
use App\Models\SeoSite;
use App\Support\CurrentClient;
use App\Support\Seo\Audit\Catalog;
use App\Support\Seo\Audit\PageParser;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Site audit A1 (FR-089 / #183, SEO_Slice0_Spec 0.3; screen per PR-014 §2). Module `seo_audit` (off: super-admin + preview
 * workspaces only). Owner + admin add sites and run audits; account managers and analysts view (PR-014 §0 answer 7).
 */
class SeoAuditPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'seo_audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Site audit';

    protected static ?string $title = 'Site audit';

    protected static ?string $slug = 'seo-audit';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.seo-audit';

    public string $newSiteUrl = '';

    public bool $consent = false;

    public string $keyword = '';

    public ?int $siteId = null;

    public function mount(): void
    {
        $sites = $this->sites();
        $this->siteId ??= $sites->first()?->id;
    }

    protected function client(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user) {
            return null;
        }
        $current = CurrentClient::resolve();
        if ($current && Client::visibleTo($user, $agency)->whereKey($current->id)->exists()) {
            return $current;
        }

        return Client::visibleTo($user, $agency)->orderBy('name')->first();
    }

    protected function canRun(): bool
    {
        return TeamRoles::canManageTeam(auth()->user(), Filament::getTenant());
    }

    /** @return Collection<int, SeoSite> */
    protected function sites()
    {
        $client = $this->client();
        if (! $client) {
            return collect();
        }

        return SeoSite::with('latestAudit')->where('agency_id', $client->agency_id)->where('client_id', $client->id)->orderBy('host')->get();
    }

    /** @return array{sites: int, pages: int} */
    protected function planLimits(): array
    {
        $plan = (string) (Filament::getTenant()?->plan ?? 'starter');
        $p = config("seo_audit.plans.$plan", config('seo_audit.plans.starter'));

        return ['sites' => (int) ($p['sites'] ?? 1), 'pages' => (int) ($p['pages'] ?? 500)];
    }

    protected function pagesUsedThisMonth(): int
    {
        $agency = Filament::getTenant();

        return $agency ? (int) SeoAudit::where('agency_id', $agency->id)->where('created_at', '>=', now()->startOfMonth())->sum('pages_done') : 0;
    }

    public function addSite(): void
    {
        $this->resetErrorBag('newSiteUrl');
        $client = $this->client();
        if (! $client || ! $this->canRun()) {
            return;
        }
        $v = Validator::make(['url' => trim($this->newSiteUrl), 'consent' => $this->consent], [
            'url' => ['required', 'string', 'max:300', 'regex:#^https?://[^\s/]+\.[^\s/]+#i'],
            'consent' => ['accepted'],
        ]);
        if ($v->fails()) {
            $this->addError('newSiteUrl', $v->errors()->has('consent') ? __('Confirm you own this site or have its owner\'s permission before the first audit.') : __('Enter the full site address, such as https://example.sa'));

            return;
        }
        $url = PageParser::normalise(trim($this->newSiteUrl));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $count = SeoSite::where('agency_id', $client->agency_id)->count();
        if ($count >= $this->planLimits()['sites']) {
            $this->addError('newSiteUrl', trans_choice('{1} Your plan allows one site.|[2,*] Your plan allows :n sites.', $this->planLimits()['sites'], ['n' => $this->planLimits()['sites']]).' '.__('Remove one or move to a bigger plan.'));

            return;
        }
        if (SeoSite::where('agency_id', $client->agency_id)->where('client_id', $client->id)->where('host', $host)->exists()) {
            $this->addError('newSiteUrl', __('This site is already added.'));

            return;
        }
        $site = SeoSite::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'url' => $url,
            'host' => $host,
            'consent_user_id' => auth()->id(),
            'consent_at' => now(),
        ]);
        $this->siteId = $site->id;
        $this->newSiteUrl = '';
        $this->consent = false;
        Notification::make()->title(__('Site added'))->success()->send();
    }

    public function runAudit(): void
    {
        $site = $this->selectedSite();
        if (! $site || ! $this->canRun()) {
            return;
        }
        if (! $site->hasConsent()) {
            Notification::make()->title(__('Confirm you own this site or have its owner\'s permission before the first audit.'))->warning()->send();

            return;
        }
        if ($site->latestAudit?->isOpen()) {
            return;
        }
        $limits = $this->planLimits();
        if ($this->pagesUsedThisMonth() >= $limits['pages']) {
            Notification::make()->title(__('Audits are paused: you have reached this month\'s page limit.'))->warning()->send();

            return;
        }
        $audit = SeoAudit::create([
            'agency_id' => $site->agency_id,
            'seo_site_id' => $site->id,
            'requested_by' => auth()->id(),
            'status' => 'queued',
            'pages_planned' => min((int) config('seo_audit.max_pages_per_run', 200), max(1, $limits['pages'] - $this->pagesUsedThisMonth())),
        ]);
        RunSeoAudit::dispatch($audit->id, trim($this->keyword) !== '' ? trim($this->keyword) : null);
        Notification::make()->title(__('Audit queued'))->success()->send();
    }

    public function stopAudit(): void
    {
        $site = $this->selectedSite();
        $audit = $site?->latestAudit;
        if ($audit && $audit->isOpen() && $this->canRun()) {
            $audit->forceFill(['status' => 'stopped', 'finished_at' => now()])->save();
        }
    }

    public function downloadCsv(): ?StreamedResponse
    {
        $site = $this->selectedSite();
        $audit = $site?->latestAudit;
        if (! $audit || $audit->status !== 'done') {
            return null;
        }
        $ar = app()->getLocale() === 'ar';
        $rows = $audit->issues()->with('page')->orderByRaw("case level when 'critical' then 0 when 'warning' then 1 else 2 end")->get();

        return response()->streamDownload(function () use ($rows, $ar): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['code', $ar ? 'الخطورة' : 'level', $ar ? 'المشكلة' : 'issue', $ar ? 'الصفحة' : 'page', $ar ? 'التفصيل' : 'detail', $ar ? 'كيف تصلحها' : 'fix']);
            foreach ($rows as $row) {
                $d = Catalog::describe($row->code, $ar);
                fputcsv($out, [$row->code, Catalog::levelLabel($row->level, $ar), $d['title'], $row->page?->url ?? '', (string) $row->detail, $d['fix']]);
            }
            fclose($out);
        }, 'site-audit-'.$site->host.'-'.$audit->created_at->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function selectedSite(): ?SeoSite
    {
        $sites = $this->sites();

        return $sites->firstWhere('id', $this->siteId) ?? $sites->first();
    }

    protected function getViewData(): array
    {
        $ar = app()->getLocale() === 'ar';
        $client = $this->client();
        $sites = $this->sites();
        $site = $this->selectedSite();
        $audit = $site?->latestAudit;
        $groups = [];
        $categories = array_fill_keys(array_keys(Catalog::CATEGORIES), 0);
        if ($audit && $audit->status === 'done') {
            $issues = $audit->issues()->with('page')->get();
            foreach ($issues as $issue) {
                $code = $issue->code;
                $groups[$code] ??= ['code' => $code, 'level' => $issue->level] + Catalog::describe($code, $ar) + ['pages' => [], 'count' => 0];
                $groups[$code]['count']++;
                if (count($groups[$code]['pages']) < 25) {
                    $groups[$code]['pages'][] = ['url' => $issue->page?->url ?? '', 'detail' => $issue->detail];
                }
                $categories[Catalog::category($code)]++;
            }
            $order = [Catalog::CRITICAL => 0, Catalog::WARNING => 1, Catalog::INFO => 2];
            uasort($groups, fn (array $a, array $b) => [$order[$a['level']], -$a['count']] <=> [$order[$b['level']], -$b['count']]);
        }
        $limits = $this->planLimits();

        return [
            'ar' => $ar,
            'client' => $client,
            'sites' => $sites,
            'site' => $site,
            'audit' => $audit,
            'groups' => array_values($groups),
            'categories' => $categories,
            'canRun' => $this->canRun(),
            'limits' => $limits,
            'pagesUsed' => $this->pagesUsedThisMonth(),
            'weights' => config('seo_audit.score_weights'),
        ];
    }
}
