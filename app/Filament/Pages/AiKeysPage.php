<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AiKey;
use App\Models\AuditLog;
use App\Support\AiWorkspace\OwnKey;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * AI Workspace M1 slice 4 (Product M1 screens §2 "My API keys", #159 vault): the agency's own provider keys. A key is
 * tested before it is kept, shown once (never again, only its last 4), and needs a monthly spend cap before the AI box
 * or night shift can use it. Owner + admin manage keys.
 */
class AiKeysPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'ai_workspace';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'AI Workspace';

    protected static ?string $navigationLabel = 'My API keys';

    protected static ?string $title = 'My API keys';

    protected static ?string $slug = 'ai-keys';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.ai-keys';

    public string $provider = 'openai';

    public string $apiKey = '';

    public string $model = '';

    public $cap = '';

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
        $models = array_keys((array) config("ai_workspace.own_key_models.{$this->provider}", []));
        $this->validate([
            'provider' => ['required', 'in:'.implode(',', array_keys(AiKey::PROVIDERS))],
            'apiKey' => ['required', 'string', 'min:20', 'max:300'],
            'model' => ['required', 'in:'.implode(',', $models)],
            'cap' => ['required', 'numeric', 'min:1', 'max:1000'],
        ], [], ['apiKey' => __('API key'), 'cap' => __('Monthly spend cap')]);

        [$ok, $msg] = OwnKey::test($this->provider, trim($this->apiKey));
        if (! $ok) {
            $this->addError('apiKey', $msg);

            return;
        }
        $agency = Filament::getTenant();
        $key = AiKey::updateOrCreate(['agency_id' => $agency->getKey(), 'provider' => $this->provider], [
            'api_key' => trim($this->apiKey), 'last4' => mb_substr(trim($this->apiKey), -4), 'model' => $this->model, 'monthly_cap_usd' => round((float) $this->cap, 2),
            'tested_at' => now(), 'test_ok' => true, 'test_message' => $msg, 'created_by' => auth()->id(),
        ]);
        AuditLog::record('ai_workspace.key_saved', $agency, ['provider' => $this->provider, 'last4' => $key->last4, 'cap' => $key->monthly_cap_usd]);
        $this->reset('apiKey', 'cap', 'model');
        Notification::make()->title(__('Key saved. It won\'t be shown again.'))->success()->send();
    }

    public function updateCap(int $id, $cap): void
    {
        abort_unless($this->canManage(), 403);
        $cap = round((float) $cap, 2);
        if ($cap < 1 || $cap > 1000) {
            Notification::make()->title(__('Use a cap between 1 and 1,000 USD.'))->danger()->send();

            return;
        }
        $key = AiKey::where('agency_id', Filament::getTenant()->getKey())->findOrFail($id);
        $key->update(['monthly_cap_usd' => $cap]);
        AuditLog::record('ai_workspace.key_cap', Filament::getTenant(), ['provider' => $key->provider, 'cap' => $cap]);
        Notification::make()->title(__('Cap updated.'))->success()->send();
    }

    public function remove(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $key = AiKey::where('agency_id', Filament::getTenant()->getKey())->findOrFail($id);
        $key->delete();
        AuditLog::record('ai_workspace.key_removed', Filament::getTenant(), ['provider' => $key->provider, 'last4' => $key->last4]);
        Notification::make()->title(__('Key deleted.'))->success()->send();
    }

    protected function getViewData(): array
    {
        return ['full' => $this->full(), 'keys' => AiKey::where('agency_id', Filament::getTenant()->getKey())->orderBy('provider')->get(), 'canManage' => $this->canManage(),
            'models' => (array) config("ai_workspace.own_key_models.{$this->provider}", [])];
    }
}
