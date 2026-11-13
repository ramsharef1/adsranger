<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\Arabic\Phrases;
use App\Support\CurrentClient;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/** XS-18 + XS-17 (R5, D-307): Arabic CTA and offer phrases by sector, season and dialect; rule-based ad line variants. */
class ArabicPhrasesPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Arabic phrases';

    protected static ?string $title = 'Arabic phrases and variants';

    protected static ?string $slug = 'arabic-phrases';

    protected static ?int $navigationSort = 23;

    protected string $view = 'filament.pages.arabic-phrases';

    public ?int $clientId = null;

    public string $dialect = 'msa';

    public string $sector = '';

    public string $season = 'any';

    public string $kind = '';

    public string $line = '';

    public function mount(): void
    {
        $this->clientId = CurrentClient::id() ?: null;
        $this->applyClient();
    }

    public function updatedClientId(): void
    {
        $this->applyClient();
    }

    protected function applyClient(): void
    {
        $c = $this->clientId ? Client::visibleTo(auth()->user(), Filament::getTenant())->find($this->clientId) : null;
        $this->dialect = Phrases::dialectOf($c ?? new Client(['market' => Filament::getTenant()?->country]));
        $this->sector = (string) ($c?->vertical ?? '');
    }

    protected function getViewData(): array
    {
        $dialect = in_array($this->dialect, Phrases::DIALECTS, true) ? $this->dialect : 'msa';
        $season = in_array($this->season, Phrases::SEASONS, true) ? $this->season : 'any';

        return [
            'clients' => Client::visibleTo(auth()->user(), Filament::getTenant())->whereIn('status', ['active', 'paused'])->orderBy('name')->pluck('name', 'id'),
            'phrases' => Phrases::find($dialect, $this->sector ?: null, $season, in_array($this->kind, ['cta', 'offer'], true) ? $this->kind : null),
            'variants' => mb_strlen($this->line) > 300 ? [] : Phrases::variants($this->line, $dialect, $this->sector ?: null),
            'dialects' => Phrases::dialectLabels(), 'seasons' => Phrases::seasonLabels(), 'sectors' => config('adsranger.verticals'),
            'draft' => Phrases::isDraft(),
        ];
    }
}
