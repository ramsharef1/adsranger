<?php

namespace App\Filament\Pages;

use App\Connectors\ConnectorRegistry;
use App\Filament\Concerns\GatedByPlan;
use App\Models\AdAccount;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\GoogleInsights;
use App\Support\Periods;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnitEnum;

/** Google Ads depth: impression share + Quality Score (#47), search terms + negatives (#46), Performance Max asset groups (#48). */
class GoogleAdsPage extends Page
{
    use GatedByPlan;

    protected static string $planFeature = 'google_depth';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Google Ads';

    protected static ?string $title = 'Google Ads: search terms, Quality Score, PMax';

    protected static ?string $slug = 'google';

    protected static ?int $navigationSort = 25;

    protected string $view = 'filament.pages.google-ads';

    public ?int $clientId = null;

    public string $period = '30d';

    public bool $wasteOnly = false;

    /** @var array<int,string> selected search-term keys "account|campaign|term" */
    public array $selected = [];

    public string $match = 'EXACT';

    public function mount(): void
    {
        $this->clientId = (int) request()->query('client') ?: $this->clients()->first()?->id;
    }

    protected function clients()
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->where('status', 'active')->orderBy('name')->get();
    }

    protected function client(): ?Client
    {
        return $this->clientId ? Client::visibleTo(auth()->user(), Filament::getTenant())->with('agency')->find($this->clientId) : null;
    }

    public function updatedClientId(): void
    {
        $this->selected = [];
    }

    public function setPeriod(string $p): void
    {
        $this->period = in_array($p, ['7d', '30d', '90d'], true) ? $p : '30d';
    }

    /** Add the selected search terms as campaign negatives in Google Ads. Owner / admin / manager only; every change is logged. */
    public function addNegatives(): void
    {
        $a = Filament::getTenant();
        $c = $this->client();
        if (! $c || ! TeamRoles::canManageData(auth()->user(), $a)) {
            abort(403);
        }
        $match = $this->match === 'PHRASE' ? 'PHRASE' : 'EXACT';
        $byAccount = [];
        foreach (array_slice(array_unique($this->selected), 0, 200) as $key) {
            [$acct, $camp, $term] = explode('|', (string) $key, 3) + [null, null, null];
            if ($acct && $camp && $term !== null && $term !== '') {
                $byAccount[(int) $acct][] = ['campaign' => $camp, 'text' => $term, 'match' => $match];
            }
        }
        $ok = 0;
        $errors = [];
        foreach ($byAccount as $acctId => $items) {
            // The account must belong to this agency AND to the selected (visible) client.
            $acct = AdAccount::with(['network', 'connection'])->where('agency_id', $a->id)->where('client_id', $c->id)->find($acctId);
            if (! $acct || ($acct->network->key ?? '') !== 'google_ads') {
                continue;
            }
            $demo = ($acct->connection->environment ?? null) === 'demo';
            try {
                if (! $demo) {
                    ConnectorRegistry::for($acct->network)->addNegatives($acct, $items);
                }
                $status = $demo ? 'demo' : 'ok';
                $ok += count($items);
            } catch (Throwable $e) {
                $status = 'error';
                $errors[] = mb_substr($e->getMessage(), 0, 200);
            }
            foreach ($items as $i) {
                DB::table('negative_keyword_actions')->insert(['agency_id' => $a->id, 'client_id' => $c->id, 'ad_account_id' => $acct->id, 'user_id' => auth()->id(),
                    'campaign_external' => $i['campaign'], 'text' => mb_substr($i['text'], 0, 298), 'match_type' => $match, 'status' => $status,
                    'error' => $status === 'error' ? end($errors) : null, 'created_at' => now(), 'updated_at' => now()]);
                if ($status !== 'error') {
                    DB::table('search_terms')->where('ad_account_id', $acct->id)->where('campaign_external', $i['campaign'])->where('term', $i['text'])->update(['status' => 'EXCLUDED']);
                }
            }
        }
        AuditLog::record('google.negatives_added', $a, ['client_id' => $c->id, 'count' => $ok, 'match' => $match, 'errors' => count($errors)]);
        $this->selected = [];
        $errors
            ? Notification::make()->title('Some negatives were not added')->body(implode(' · ', array_unique($errors)))->danger()->send()
            : Notification::make()->title("$ok negative keyword(s) added as ".strtolower($match).' match')->success()->send();
    }

    protected function getViewData(): array
    {
        $c = $this->client();
        $data = ['clients' => $this->clients(), 'client' => $c, 'canManage' => TeamRoles::canManageData(auth()->user(), Filament::getTenant())];
        if (! $c) {
            return $data;
        }
        [$from, $to] = Periods::range($this->period, $c->agency->timezone ?: 'UTC');

        return $data + [
            'from' => $from, 'to' => $to,
            'hasGoogle' => $c->adAccounts()->whereHas('network', fn ($q) => $q->where('key', 'google_ads'))->exists(),
            'is' => GoogleInsights::impressionShare($c, $from, $to),
            'terms' => GoogleInsights::searchTerms($c, $from, $to, $this->wasteOnly),
            'waste' => GoogleInsights::wasteTotal($c, $from, $to),
            'kw' => GoogleInsights::keywords($c),
            'groups' => GoogleInsights::assetGroups($c),
            'history' => DB::table('negative_keyword_actions')->leftJoin('users', 'users.id', '=', 'negative_keyword_actions.user_id')
                ->where('negative_keyword_actions.client_id', $c->id)->where('negative_keyword_actions.agency_id', $c->agency_id)
                ->orderByDesc('negative_keyword_actions.id')->limit(10)->get(['negative_keyword_actions.*', 'users.name as user_name']),
        ];
    }
}
