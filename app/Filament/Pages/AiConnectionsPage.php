<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AiConnection;
use App\Models\Client;
use App\Support\AiWorkspace\ToolCatalogue;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * AI Workspace M1 slice 2 (Product M1 screens §2): «الربط». Each connected AI with its state, the clients it may read,
 * last use and today's calls; disconnect kills the token at once; the owner restores a paused one after review.
 * Members see their own connections; owner + admin see everyone's in this workspace.
 */
class AiConnectionsPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'ai_workspace';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'AI Workspace';

    protected static ?string $navigationLabel = 'Connections';

    protected static ?string $title = 'Connections';

    protected static ?string $slug = 'ai-connections';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.ai-connections';

    protected function manager(): bool
    {
        return TeamRoles::canManageTeam(auth()->user(), Filament::getTenant());
    }

    protected function find(int $id): AiConnection
    {
        $c = AiConnection::where('agency_id', Filament::getTenant()->getKey())->findOrFail($id);
        abort_unless($this->manager() || $c->user_id === auth()->id(), 403);

        return $c;
    }

    public function disconnect(int $id): void
    {
        $c = $this->find($id);
        $c->revoke(auth()->user());
        Notification::make()->title(__(':ai stops reading your data at once. What you saved stays.', ['ai' => $c->providerName()]))->success()->send();
    }

    public function restore(int $id): void
    {
        abort_unless(TeamRoles::isOwner(auth()->user(), Filament::getTenant()), 403);
        $c = $this->find($id);
        abort_unless($c->status === 'paused', 404);
        $c->restore(auth()->user());
        Notification::make()->title(__('Connection restored.'))->success()->send();
    }

    /** FS-10: switch one connector tool on / off for this workspace (owner + admins). */
    public function toggleTool(string $tool): void
    {
        abort_unless($this->manager() && array_key_exists($tool, ToolCatalogue::tools()), 403);
        ToolCatalogue::toggle(Filament::getTenant(), $tool);
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $connections = AiConnection::with('user')->where('agency_id', $agency->getKey())->where('status', '!=', 'revoked')
            ->when(! $this->manager(), fn ($q) => $q->where('user_id', auth()->id()))->latest()->get();
        $names = Client::where('agency_id', $agency->getKey())->pluck('name', 'id');

        return ['connections' => $connections, 'clientNames' => $names, 'url' => 'https://'.config('ai_workspace.mcp_host'),
            'cap' => (int) config('ai_workspace.calls_per_day'), 'isOwner' => TeamRoles::isOwner(auth()->user(), $agency), 'tz' => $agency->timezone ?: 'UTC',
            'tools' => collect(ToolCatalogue::tools())->map(fn ($d, $name) => ['description' => $d, 'off' => ToolCatalogue::off($agency, $name)])->all(),
            'toolCalls' => $this->manager() ? ToolCatalogue::recent($agency) : [], 'canManage' => $this->manager()];
    }
}
