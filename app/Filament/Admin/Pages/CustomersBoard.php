<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Agency;
use App\Models\Plan;
use App\Support\Business\ConsentRegistry;
use App\Support\Business\CustomerHealth;
use App\Support\Business\TrialRequests;
use App\Support\Business\UpgradeMoments;
use App\Support\PlanGate;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * R2 (D-289): one page for the business view — customer health (WS-15 / NB-05), signup → first report funnel (WS-21),
 * fair-use signals (NB-14), upgrade moments (PS-27), notes with a next-check date (WS-16), trial extension requests
 * (PS-32), consent registry (WS-26), the monthly usage ledger (EG-33 / NB-13) and the plan matrix (EH-06).
 */
class CustomersBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $title = 'Customers';

    protected static ?string $slug = 'customers';

    protected static ?int $navigationSort = 88;

    protected string $view = 'filament.admin.pages.customers';

    public ?int $noteAgency = null;

    public string $noteBody = '';

    public ?string $noteNext = null;

    /** @var array<int, int> request id → days to grant */
    public array $grantDays = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = DB::table('trial_extension_requests')->where('status', 'open')->count();

        return $n > 0 ? (string) $n : null;
    }

    public function addNote(): void
    {
        $this->validate([
            'noteAgency' => ['required', 'integer', 'exists:agencies,id'],
            'noteBody' => ['required', 'string', 'max:2000'],
            'noteNext' => ['nullable', 'date'],
        ]);
        DB::table('agency_notes')->insert([
            'agency_id' => $this->noteAgency, 'body' => $this->noteBody, 'next_check_on' => $this->noteNext ?: null,
            'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->noteBody = '';
        $this->noteNext = null;
    }

    public function grant(int $id): void
    {
        TrialRequests::decide($id, true, (int) ($this->grantDays[$id] ?? 7), auth()->id());
    }

    public function decline(int $id): void
    {
        TrialRequests::decide($id, false, 0, auth()->id());
    }

    /**
     * EH-06: every tier side by side — limits from the plans table, gated features from config/plan_features.php.
     *
     * @return array{tiers: list<string>, limits: array<string, array<string, ?int>>, features: list<array{key: string, label: string, tiers: array<string, bool>}>}
     */
    public static function planMatrix(): array
    {
        $rank = (array) config('plan_features.tiers');
        $tiers = array_keys($rank);
        $plans = Plan::query()->whereIn('key', $tiers)->get()->keyBy('key');
        $limits = [];
        foreach (['seats', 'clients', 'ad_accounts'] as $k) {
            foreach ($tiers as $t) {
                $limits[$k][$t] = $plans->has($t) ? (int) $plans[$t]->$k : null;
            }
        }
        $features = [];
        foreach ((array) config('plan_features.features') as $key => $f) {
            $need = $rank[$f['tier']] ?? 99;
            $features[] = ['key' => $key, 'label' => PlanGate::featureText($key, 'name'), 'tiers' => collect($tiers)->mapWithKeys(fn ($t) => [$t => $rank[$t] >= $need])->all()];
        }
        usort($features, fn ($a, $b) => array_sum($b['tiers']) <=> array_sum($a['tiers']));

        return ['tiers' => $tiers, 'limits' => $limits, 'features' => $features];
    }

    protected function getViewData(): array
    {
        $month = now()->subMonthNoOverflow()->format('Y-m');

        return [
            'rows' => CustomerHealth::rows(),
            'funnel' => CustomerHealth::funnel(),
            'moments' => UpgradeMoments::top(),
            'requests' => DB::table('trial_extension_requests')->join('agencies', 'agencies.id', '=', 'trial_extension_requests.agency_id')
                ->where('trial_extension_requests.status', 'open')->orderBy('trial_extension_requests.created_at')
                ->get(['trial_extension_requests.*', 'agencies.name as agency_name']),
            'notes' => DB::table('agency_notes')->join('agencies', 'agencies.id', '=', 'agency_notes.agency_id')
                ->orderByRaw('agency_notes.next_check_on is null')->orderBy('agency_notes.next_check_on')->orderByDesc('agency_notes.created_at')
                ->limit(40)->get(['agency_notes.*', 'agencies.name as agency_name']),
            'agencies' => Agency::query()->orderBy('name')->pluck('name', 'id'),
            'consents' => ConsentRegistry::rows(),
            'matrix' => self::planMatrix(),
            'ledgerMonth' => $month,
            'ledger' => DB::table('usage_ledger')->join('agencies', 'agencies.id', '=', 'usage_ledger.agency_id')
                ->where('month', $month)->orderBy('agencies.name')->get(['usage_ledger.*', 'agencies.name as agency_name']),
        ];
    }
}
