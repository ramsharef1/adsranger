<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\CurrentClient;
use App\Support\LaunchPreflight;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/** XS-11 (R4): campaign launch pre-flight: one pass / fix list before going live. Checks only. */
class LaunchCheckPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Launch check';

    protected static ?string $title = 'Campaign launch check';

    protected static ?string $slug = 'launch-check';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.launch-check';

    public ?int $clientId = null;

    public string $url = '';

    public string $adName = '';

    public string $adText = '';

    /** @var list<array{key: string, ok: bool, level: string, message: string}>|null */
    public ?array $results = null;

    public function mount(): void
    {
        $this->clientId = CurrentClient::id() ?: Client::visibleTo(auth()->user(), Filament::getTenant())->where('status', 'active')->orderBy('name')->value('id');
    }

    public function check(): void
    {
        $this->validate(['clientId' => 'required|integer', 'url' => 'required|url:http,https|max:1000', 'adName' => 'nullable|string|max:160', 'adText' => 'required|string|max:3000'],
            [], ['url' => __('Landing page link'), 'adName' => __('Ad or listing name (optional)'), 'adText' => __('Ad text')]);
        $c = Client::visibleTo(auth()->user(), Filament::getTenant())->findOrFail($this->clientId);
        $this->results = LaunchPreflight::run($c, trim($this->url), $this->adText, true, trim($this->adName) !== '' ? trim($this->adName) : null);
    }

    protected function getViewData(): array
    {
        return ['clients' => Client::visibleTo(auth()->user(), Filament::getTenant())->where('status', 'active')->orderBy('name')->pluck('name', 'id')];
    }
}
