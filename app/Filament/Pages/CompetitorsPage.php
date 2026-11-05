<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\GatedByPlan;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Competitor;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Competitor watch (#50). Meta's Ad Library API only returns ads about social issues, elections or politics outside the EU,
 * so for MENA commercial ads this page keeps the watch list, opens the public libraries filtered per country and keeps review notes.
 */
class CompetitorsPage extends Page
{
    use GatedByPlan;
    use TranslatesPageLabels;

    protected static string $planFeature = 'competitors';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Competitor watch';

    protected static ?string $title = 'Competitor watch';

    protected static ?string $slug = 'competitors';

    protected static ?int $navigationSort = 27;

    protected string $view = 'filament.pages.competitors';

    public ?int $clientId = null;

    public array $form = ['name' => '', 'facebook_page_id' => '', 'website' => '', 'countries' => []];

    /** @var array<int,string> notes being edited, by competitor id */
    public array $notes = [];

    public function mount(): void
    {
        $this->clientId = (int) request()->query('client') ?: $this->clients()->first()?->id;
    }

    protected function clients()
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->orderBy('name')->get();
    }

    protected function canManage(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    protected function competitor(int $id): Competitor
    {
        $vis = $this->clients()->pluck('id');

        return Competitor::where('agency_id', Filament::getTenant()->id)->whereIn('client_id', $vis)->findOrFail($id);
    }

    public function add(): void
    {
        $a = Filament::getTenant();
        if (! $this->canManage() || ! $this->clients()->contains('id', $this->clientId)) {
            abort(403);
        }
        $this->validate([
            'form.name' => 'required|string|max:120',
            'form.facebook_page_id' => ['nullable', 'regex:/^\d{5,25}$/'],
            'form.website' => 'nullable|url:http,https|max:200',
            'form.countries' => 'array|max:12',
            'form.countries.*' => 'string|size:2',
        ], ['form.facebook_page_id.regex' => __('The Facebook page ID is the number in the Ad Library link (view_all_page_id=…).')],
            ['form.name' => 'name', 'form.website' => 'website']);
        $valid = array_keys(config('adsranger.countries'));
        $c = Competitor::create(['agency_id' => $a->id, 'client_id' => $this->clientId, 'name' => trim($this->form['name']),
            'facebook_page_id' => $this->form['facebook_page_id'] ?: null, 'website' => $this->form['website'] ?: null,
            'countries' => array_values(array_intersect($this->form['countries'], $valid)) ?: null, 'created_by' => auth()->id()]);
        AuditLog::record('competitor.added', $a, ['competitor_id' => $c->id, 'client_id' => $this->clientId]);
        $this->form = ['name' => '', 'facebook_page_id' => '', 'website' => '', 'countries' => []];
        Notification::make()->title(__('Competitor added'))->success()->send();
    }

    public function saveNotes(int $id): void
    {
        if (! $this->canManage()) {
            abort(403);
        }
        $c = $this->competitor($id);
        $text = mb_substr((string) ($this->notes[$id] ?? ''), 0, 5000);
        $c->update(['notes' => $text, 'reviewed_at' => now()]);
        Notification::make()->title(__('Notes saved'))->success()->send();
    }

    public function markReviewed(int $id): void
    {
        if (! $this->canManage()) {
            abort(403);
        }
        $this->competitor($id)->update(['reviewed_at' => now()]);
    }

    public function remove(int $id): void
    {
        if (! $this->canManage()) {
            abort(403);
        }
        $c = $this->competitor($id);
        AuditLog::record('competitor.removed', Filament::getTenant(), ['name' => $c->name, 'client_id' => $c->client_id]);
        $c->delete();
    }

    protected function getViewData(): array
    {
        $clients = $this->clients();
        $list = $this->clientId && $clients->contains('id', $this->clientId)
            ? Competitor::with('client')->where('agency_id', Filament::getTenant()->id)->where('client_id', $this->clientId)->orderBy('name')->get() : collect();
        foreach ($list as $c) {
            $this->notes[$c->id] ??= (string) $c->notes;
        }

        return ['clients' => $clients, 'list' => $list, 'canManage' => $this->canManage(), 'countries' => config('adsranger.countries')];
    }
}
