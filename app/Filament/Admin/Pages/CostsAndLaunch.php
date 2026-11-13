<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\CostLine;
use App\Models\LaunchCheck;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use UnitEnum;

/**
 * A-4 (R2-70): this month's platform costs against the monthly cap (1,000 USD, config costs.monthly_cap_usd), typed by a
 * super admin. A-8 (IDEA-049): the launch go / no-go list, each line with an owner, a date and a status.
 */
class CostsAndLaunch extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Costs & launch';

    protected static ?string $title = 'Costs & launch readiness';

    protected static ?string $slug = 'costs-launch';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.admin.pages.costs-launch';

    public string $costMonth = '';

    public string $costCategory = 'server';

    public ?float $costAmount = null;

    public string $costNote = '';

    public string $checkTitle = '';

    public string $checkOwner = 'E1';

    public ?string $checkDue = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function mount(): void
    {
        $this->costMonth = now()->format('Y-m');
    }

    public function addCost(): void
    {
        abort_unless(static::canAccess(), 403);
        $v = Validator::make(
            ['month' => $this->costMonth, 'category' => $this->costCategory, 'amount' => $this->costAmount],
            ['month' => ['required', 'date_format:Y-m'], 'category' => ['required', 'in:'.implode(',', CostLine::CATEGORIES)], 'amount' => ['required', 'numeric', 'min:0', 'max:100000']]
        );
        if ($v->fails()) {
            Notification::make()->title(__('Enter a month, a category and an amount in USD.'))->warning()->send();

            return;
        }
        $line = CostLine::create([
            'month' => Carbon::createFromFormat('Y-m', $this->costMonth)->startOfMonth()->toDateString(),
            'category' => $this->costCategory,
            'amount_usd' => round((float) $this->costAmount, 2),
            'note' => trim($this->costNote) !== '' ? mb_substr(trim($this->costNote), 0, 200) : null,
            'created_by' => auth()->id(),
        ]);
        AuditLog::record('platform.cost_added', null, ['id' => $line->id, 'category' => $line->category, 'amount_usd' => $line->amount_usd]);
        $this->costAmount = null;
        $this->costNote = '';
        Notification::make()->title(__('Cost added'))->success()->send();
    }

    public function addCheck(): void
    {
        abort_unless(static::canAccess(), 403);
        if (trim($this->checkTitle) === '' || trim($this->checkOwner) === '') {
            return;
        }
        LaunchCheck::create([
            'title' => mb_substr(trim($this->checkTitle), 0, 200),
            'owner' => mb_substr(trim($this->checkOwner), 0, 40),
            'due_on' => $this->checkDue ?: null,
            'status' => 'open',
            'sort' => (int) LaunchCheck::max('sort') + 1,
        ]);
        $this->checkTitle = '';
        $this->checkDue = null;
    }

    public function setCheckStatus(int $id, string $status): void
    {
        abort_unless(static::canAccess(), 403);
        if (in_array($status, LaunchCheck::STATUSES, true)) {
            LaunchCheck::whereKey($id)->update(['status' => $status]);
        }
    }

    public function setCheckDue(int $id, ?string $date): void
    {
        abort_unless(static::canAccess(), 403);
        $date = $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
        LaunchCheck::whereKey($id)->update(['due_on' => $date]);
    }

    protected function getViewData(): array
    {
        $cap = (float) config('costs.monthly_cap_usd', 1000);
        $month = now()->startOfMonth();
        $lines = CostLine::where('month', '>=', $month->copy()->subMonths(2)->toDateString())->orderByDesc('month')->orderBy('category')->get();
        $thisMonth = $lines->filter(fn (CostLine $l) => $l->month->isSameMonth($month));
        $total = round((float) $thisMonth->sum('amount_usd'), 2);
        $checks = LaunchCheck::orderBy('sort')->get();

        return [
            'cap' => $cap,
            'total' => $total,
            'share' => $cap > 0 ? min(100, (int) round($total / $cap * 100)) : 0,
            'byCategory' => $thisMonth->groupBy('category')->map(fn ($g) => round((float) $g->sum('amount_usd'), 2))->all(),
            'lines' => $lines,
            'checks' => $checks,
            'checksDone' => $checks->where('status', 'done')->count(),
            'categories' => CostLine::CATEGORIES,
        ];
    }
}
