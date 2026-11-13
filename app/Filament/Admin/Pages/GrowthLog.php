<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * R2 (D-289): where launch-list signups came from (GR-12 landing / tool / UTM), referral counts (GR-15), the monthly
 * "does an AI assistant cite us" log (GR-28) and the win / loss log (NB-48 = WK-23). Counts only, no visitor tracking.
 */
class GrowthLog extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Growth log';

    protected static ?string $title = 'Growth log';

    protected static ?string $slug = 'growth-log';

    protected static ?int $navigationSort = 89;

    protected string $view = 'filament.admin.pages.growth-log';

    public string $question = '';

    public string $assistant = '';

    public string $cited = 'no';

    public string $checkNote = '';

    public string $prospect = '';

    public string $country = '';

    public string $outcome = 'lost';

    public string $reason = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function addCheck(): void
    {
        $this->validate([
            'question' => ['required', 'string', 'max:300'],
            'assistant' => ['required', 'string', 'max:40'],
            'cited' => ['required', 'in:yes,no,tool,page'],
            'checkNote' => ['nullable', 'string', 'max:300'],
        ]);
        DB::table('ai_answer_checks')->insert([
            'checked_on' => now()->toDateString(), 'question' => $this->question, 'assistant' => $this->assistant,
            'cited' => $this->cited, 'note' => $this->checkNote ?: null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->reset('question', 'checkNote');
    }

    public function addProspect(): void
    {
        $this->validate([
            'prospect' => ['required', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'size:2'],
            'outcome' => ['required', 'in:open,won,lost'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);
        DB::table('prospect_log')->insert([
            'name' => $this->prospect, 'country' => $this->country !== '' ? strtoupper($this->country) : null, 'outcome' => $this->outcome,
            'reason' => $this->reason ?: null, 'logged_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->reset('prospect', 'country', 'reason');
    }

    /** @return list<object{v: string, n: int}> */
    public static function top(string $column, int $limit = 10): array
    {
        return DB::table('waitlist_entries')->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw("$column as v, count(*) as n")->groupBy($column)->orderByDesc('n')->limit($limit)->get()->all();
    }

    protected function getViewData(): array
    {
        $total = DB::table('waitlist_entries')->count();

        return [
            'total' => $total,
            'withOrigin' => DB::table('waitlist_entries')->whereNotNull('origin_landing')->count(),
            'landings' => self::top('origin_landing'),
            'tools' => self::top('origin_tool'),
            'sources' => self::top('utm_source'),
            'campaigns' => self::top('utm_campaign'),
            'referrers' => DB::table('waitlist_entries as r')->join('waitlist_entries as o', 'o.ref_code', '=', 'r.referred_by')
                ->selectRaw('o.email as owner, count(*) as n')->groupBy('o.email')->orderByDesc('n')->limit(10)->get(),
            'referred' => DB::table('waitlist_entries')->whereNotNull('referred_by')->count(),
            'checks' => DB::table('ai_answer_checks')->orderByDesc('checked_on')->orderByDesc('id')->limit(40)->get(),
            'prospects' => DB::table('prospect_log')->orderByDesc('logged_on')->orderByDesc('id')->limit(40)->get(),
            'lossReasons' => DB::table('prospect_log')->where('outcome', 'lost')->whereNotNull('reason')
                ->selectRaw('reason, count(*) as n')->groupBy('reason')->orderByDesc('n')->limit(8)->get(),
        ];
    }
}
