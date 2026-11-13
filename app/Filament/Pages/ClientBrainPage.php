<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AiBrainItem;
use App\Models\AiKey;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\AiWorkspace\AiBox;
use App\Support\AiWorkspace\AiScope;
use App\Support\AiWorkspace\ContextPack;
use App\Support\AiWorkspace\OwnKey;
use App\Support\AiWorkspace\Playbooks;
use App\Support\AiWorkspace\StyleNotes;
use App\Support\CurrentClient;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * AI Workspace M1 item 3 (Product M1 screens §3–§4): the client brain «ذاكرة العميل». Saved items as clean cards with the
 * quality score, filters, "show original", and what the client's AI sees (context pack). Module `ai_workspace` (off).
 * Owner + admin can remove items; organising / editing and the Free read-only line come with the plan records.
 */
class ClientBrainPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'ai_workspace';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string|UnitEnum|null $navigationGroup = 'AI Workspace';

    protected static ?string $navigationLabel = 'Client brain';

    protected static ?string $title = 'Client brain';

    protected static ?string $slug = 'ai-brain';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.client-brain';

    public $clientId = null;

    public string $type = '';

    public string $ai = '';

    /** @var list<int> */
    public array $original = [];

    // AI box (M1 item 8): runs a playbook on the workspace's own key.
    public string $boxKey = '';

    public string $boxPlaybook = 'weekly_summary';

    public string $boxNotes = '';

    public string $boxDialect = 'gulf';

    public string $boxPlatform = 'meta';

    public ?string $boxError = null;

    public ?string $boxBilling = null;

    public function mount(): void
    {
        $this->clientId = CurrentClient::id() ?: $this->clients()->first()?->id;
        $this->loadScope();
    }

    public function updatedClientId(): void
    {
        $this->loadScope();
    }

    protected function loadScope(): void
    {
        $client = $this->client();
        $this->scope = $client ? AiScope::allowed($client) : [];
    }

    protected function clients(): Collection
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->orderBy('name')->get();
    }

    protected function client(): ?Client
    {
        return $this->clientId ? $this->clients()->firstWhere('id', (int) $this->clientId) : null;
    }

    public function toggleOriginal(int $id): void
    {
        $this->original = in_array($id, $this->original, true) ? array_values(array_diff($this->original, [$id])) : [...$this->original, $id];
    }

    public function runBox(): void
    {
        $client = $this->client();
        abort_unless($client && TeamRoles::canManageData(auth()->user(), Filament::getTenant()), 403);
        abort_unless(PlanGate::allows(Filament::getTenant(), 'ai_workspace_full'), 403);
        $this->boxError = $this->boxBilling = null;
        $key = AiKey::where('agency_id', $client->agency_id)->where('provider', $this->boxKey)->first();
        if (! $key) {
            $this->boxError = __('Add your own API key to run AI here.');

            return;
        }
        $r = AiBox::run($client, $key, $this->boxPlaybook, $this->boxNotes, auth()->user(), $this->boxDialect, $this->boxPlatform);
        if (! $r['ok']) {
            $this->boxError = $r['error'];
            $this->boxBilling = ($r['billing'] ?? false) ? config("ai_workspace.own_key_billing.{$key->provider}") : null;

            return;
        }
        $this->boxNotes = '';
        $this->type = '';
        Notification::make()->success()
            ->title(($r['cached'] ?? false) ? __('Same request in the last 4 hours: the saved answer is shown, no new cost.')
                : __('Saved to the client brain · cost on your key about :usd USD (estimate).', ['usd' => number_format($r['cost'], 4)]))
            ->body(($r['redacted'] ?? 0) ? __('Personal data hidden before sending: :n', ['n' => $r['redacted']]) : null)->send();
    }

    /** @var list<string> FS-02 (D-299): what may go to AI about this client */
    public array $scope = [];

    public function saveScope(): void
    {
        $client = $this->client();
        abort_unless($client && TeamRoles::canManageData(auth()->user(), Filament::getTenant()), 403);
        $parts = array_values(array_intersect(array_keys(AiScope::PARTS), $this->scope));
        $client->forceFill(['ai_scope' => count($parts) === count(AiScope::PARTS) ? null : $parts])->save();
        AuditLog::record('ai_workspace.scope', $client, ['scope' => $parts]);
        Notification::make()->title(__('Saved. Your AI gets only what is ticked for this client.'))->success()->send();
    }

    public ?int $editingId = null;

    public string $editText = '';

    public function startEdit(int $id): void
    {
        $item = AiBrainItem::where('agency_id', Filament::getTenant()->id)->where('client_id', $this->client()?->id)->findOrFail($id);
        $this->editingId = $item->id;
        $this->editText = (string) ($item->clean ?? $item->original);
    }

    /** FS-05 (D-300): save the member's edit; word swaps become style notes for this client. */
    public function saveEdit(): void
    {
        $client = $this->client();
        abort_unless($client && TeamRoles::canManageData(auth()->user(), Filament::getTenant()), 403);
        $item = AiBrainItem::where('client_id', $client->id)->findOrFail($this->editingId);
        $before = (string) ($item->clean ?? $item->original);
        $after = trim(strip_tags($this->editText));
        if ($after === '' || $after === $before) {
            $this->editingId = null;

            return;
        }
        $item->forceFill(['clean' => $after, 'meta' => array_merge($item->meta ?? [], ['edited_by' => auth()->id(), 'edited_at' => now()->toIso8601String()])])->save();
        $n = $item->type === 'rule' ? 0 : StyleNotes::learn($client, $before, $after, auth()->user());
        $this->editingId = null;
        Notification::make()->success()->title(__('Saved.'))
            ->body($n ? __('Style notes learned from your edit: :n. They are in this client\'s rules and can be deleted.', ['n' => $n]) : null)->send();
    }

    public function remove(int $id): void
    {
        $client = $this->client();
        abort_unless($client && TeamRoles::canManageTeam(auth()->user(), Filament::getTenant()), 403);
        abort_unless(PlanGate::allows(Filament::getTenant(), 'ai_workspace_full'), 403);
        $item = AiBrainItem::where('client_id', $client->id)->findOrFail($id);
        $item->delete();
        AuditLog::record('ai_workspace.removed', $client, ['item' => $id, 'type' => $item->type]);
        Notification::make()->title(__('Removed from the client brain.'))->success()->send();
    }

    protected function getViewData(): array
    {
        $client = $this->client();
        $items = $client
            ? AiBrainItem::with('user')->where('client_id', $client->id)
                ->when(in_array($this->type, AiBrainItem::TYPES, true), fn ($q) => $q->where('type', $this->type))
                ->when(array_key_exists($this->ai, AiBrainItem::PROVIDERS), fn ($q) => $q->where('ai_provider', $this->ai))
                ->latest()->limit(100)->get()
            : collect();

        $keys = $client ? AiKey::where('agency_id', $client->agency_id)->orderBy('provider')->get() : collect();
        if ($keys->isNotEmpty() && ! $keys->contains('provider', $this->boxKey)) {
            $this->boxKey = $keys->first()->provider;
        }
        $key = $keys->firstWhere('provider', $this->boxKey);
        $estimate = ($client && $key && isset(Playbooks::all()[$this->boxPlaybook]))
            ? OwnKey::estimate($key, json_encode(Playbooks::envelope($client, $this->boxPlaybook, $this->boxNotes, $this->boxDialect, $this->boxPlatform), JSON_UNESCAPED_UNICODE))
            : null;

        return ['clients' => $this->clients(), 'client' => $client, 'items' => $items, 'pack' => $client ? ContextPack::forAi($client) : null, 'scopeParts' => AiScope::PARTS,
            'full' => $full = PlanGate::allows(Filament::getTenant(), 'ai_workspace_full'),
            'canManage' => $full && TeamRoles::canManageTeam(auth()->user(), Filament::getTenant()),
            'canRun' => TeamRoles::canManageData(auth()->user(), Filament::getTenant()), 'keys' => $keys, 'key' => $key, 'estimate' => $estimate];
    }
}
