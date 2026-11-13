<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AiBrainItem;
use App\Models\AiKey;
use App\Models\AiNightRun;
use App\Models\AiNightShift;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\AiWorkspace\NightShift;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * AI Workspace M1 item 8 (Product M1 screens §8): «المناوب الليلي» v0. Settings (on / off, hour in the workspace time
 * zone, key, clients), the morning list (read + list only, D-88 levels, no approve button) and the run log.
 */
class NightShiftPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'ai_workspace';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-moon';

    protected static string|UnitEnum|null $navigationGroup = 'AI Workspace';

    protected static ?string $navigationLabel = 'Night shift';

    protected static ?string $title = 'Night shift';

    protected static ?string $slug = 'ai-night-shift';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.night-shift';

    public bool $enabled = false;

    public int $runHour = 5;

    public string $provider = '';

    /** @var list<int|string> */
    public array $clientIds = [];

    protected function shift(): AiNightShift
    {
        return AiNightShift::firstOrNew(['agency_id' => Filament::getTenant()->getKey()], ['client_ids' => []]);
    }

    public function mount(): void
    {
        $s = $this->shift();
        $this->enabled = (bool) $s->enabled;
        $this->runHour = (int) ($s->run_hour ?? 5);
        $this->provider = (string) ($s->provider ?? AiKey::where('agency_id', Filament::getTenant()->getKey())->value('provider') ?? '');
        $this->clientIds = array_map('strval', (array) $s->client_ids);
    }

    /** D-286: keys, the AI box and the night shift come with Pro and up. */
    protected function full(): bool
    {
        return PlanGate::allows(Filament::getTenant(), 'ai_workspace_full');
    }

    protected function canManage(): bool
    {
        return $this->full() && TeamRoles::canManageTeam(auth()->user(), Filament::getTenant());
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);
        $agency = Filament::getTenant();
        $this->validate(['runHour' => ['integer', 'between:0,23'], 'provider' => ['nullable', 'in:'.implode(',', array_keys(AiKey::PROVIDERS))]]);
        if ($this->enabled && ! AiKey::where('agency_id', $agency->getKey())->where('provider', $this->provider)->exists()) {
            $this->addError('provider', __('Add a key with a monthly cap first (My API keys).'));

            return;
        }
        $ids = Client::where('agency_id', $agency->getKey())->whereIn('id', array_map('intval', $this->clientIds))->pluck('id')->all();
        $s = $this->shift();
        $s->fill(['enabled' => $this->enabled, 'run_hour' => $this->runHour, 'provider' => $this->provider ?: null, 'client_ids' => $ids])->save();
        AuditLog::record('ai_workspace.night_shift_settings', $agency, ['enabled' => $this->enabled, 'hour' => $this->runHour, 'clients' => count($ids)]);
        Notification::make()->title(__('Night shift settings saved.'))->success()->send();
    }

    public function runNow(): void
    {
        abort_unless($this->full() && TeamRoles::isOwner(auth()->user(), Filament::getTenant()), 403);
        $s = $this->shift();
        if (! $s->exists || ! $s->provider) {
            Notification::make()->title(__('Save the settings first.'))->danger()->send();

            return;
        }
        $run = NightShift::run($s->fresh('agency'));
        Notification::make()->title($run->status === 'ok' ? __('Night shift finished.') : __('Night shift stopped: :why', ['why' => $run->error]))
            ->{$run->status === 'ok' ? 'success' : 'warning'}()->send();
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $runs = AiNightRun::where('agency_id', $agency->getKey())->latest('started_at')->limit(10)->get();
        $last = $runs->firstWhere('status', '!=', 'failed');
        $items = $last ? AiBrainItem::with('client')->where('agency_id', $agency->getKey())->where('playbook', 'night_shift')
            ->whereBetween('created_at', [$last->started_at, $last->finished_at ?? now()])->get()
            ->sortBy(fn ($i) => array_search($i->meta['level'] ?? 'info', ['critical', 'warning', 'info'], true))->values() : collect();

        return ['full' => $this->full(), 'runs' => $runs, 'last' => $last, 'items' => $items, 'keys' => AiKey::where('agency_id', $agency->getKey())->get(),
            'clients' => Client::visibleTo(auth()->user(), $agency)->orderBy('name')->get(), 'canManage' => $this->canManage(),
            'isOwner' => TeamRoles::isOwner(auth()->user(), $agency), 'tz' => $agency->timezone ?: 'UTC'];
    }
}
