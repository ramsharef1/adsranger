<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\CampaignName;
use App\Support\LandingPages;
use App\Support\UtmBuilder;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

/**
 * UTM builder + link checker (#86, Starter). Builds tracked links with per-network presets; one address per line in
 * "Ad content" makes one link per ad. The check fetches the landing page through LandingPages::probe() (public hosts
 * only, no DNS rebinding) and stores nothing. Read-only for the workspace, so every role can use it.
 */
class UtmBuilderPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'UTM builder';

    protected static ?string $title = 'UTM builder';

    protected static ?string $slug = 'utm-builder';

    protected static ?int $navigationSort = 26;

    protected string $view = 'filament.pages.utm-builder';

    public string $baseUrl = '';

    public string $preset = '';

    public string $source = '';

    public string $medium = '';

    public string $campaign = '';

    /** One value per line = one link per line. */
    public string $content = '';

    public string $term = '';

    public string $utmId = '';

    /** @var array<string, mixed>|null */
    public ?array $check = null;

    /** @var array{client: string, objective: string, market: string, extra: string, date: string, gregorian: bool, hijri: bool, sep: string} XS-21 (R5) */
    public array $name = ['client' => '', 'objective' => 'LEADS', 'market' => '', 'extra' => '', 'date' => '', 'gregorian' => false, 'hijri' => true, 'sep' => '_'];

    public function useName(): void
    {
        $this->campaign = strtolower(CampaignName::build($this->name));
    }

    public function updatedPreset(string $key): void
    {
        $p = UtmBuilder::presets()[$key] ?? null;
        if (! $p) {
            return;
        }
        $this->source = $p['source'];
        $this->medium = $p['medium'];
        $this->campaign = $p['campaign'] ?? $this->campaign;
        $this->content = $p['content'] ?? $this->content;
        $this->term = $p['term'] ?? '';
        $this->utmId = $p['id'] ?? '';
    }

    public function updatedBaseUrl(): void
    {
        $this->check = null;
    }

    /** @return list<string> */
    public function links(): array
    {
        if (trim($this->baseUrl) === '') {
            return [];
        }
        $contents = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->content) ?: []), fn ($v) => $v !== ''));
        $contents = array_slice($contents ?: [''], 0, 50);
        $out = [];
        foreach ($contents as $c) {
            $url = UtmBuilder::build($this->baseUrl, $this->values($c));
            if ($url !== null) {
                $out[] = $url;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return array<string, string> */
    protected function values(string $content): array
    {
        return ['utm_source' => $this->source, 'utm_medium' => $this->medium, 'utm_campaign' => $this->campaign,
            'utm_content' => $content, 'utm_term' => $this->term, 'utm_id' => $this->utmId];
    }

    public function checkLink(): void
    {
        $this->validate(['baseUrl' => 'required|url:http,https|max:1000'], [], ['baseUrl' => __('Landing page address')]);
        $key = 'utm-check:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('baseUrl', __('Too many checks. Try again in a minute.'));

            return;
        }
        RateLimiter::hit($key, 60);
        // Check the page itself; the utm values don't change what loads.
        $v = LandingPages::probe(LandingPages::normalize($this->baseUrl) ?? $this->baseUrl);
        [$label, $color] = LandingPages::stateLabel($v['state']);
        $this->check = $v + ['label' => $label, 'color' => $color, 'url' => $this->baseUrl];
    }

    protected function getViewData(): array
    {
        $first = trim(strtok($this->content, "\n") ?: '');

        return [
            'presets' => UtmBuilder::presets(),
            'links' => $this->links(),
            'invalid' => trim($this->baseUrl) !== '' && UtmBuilder::build($this->baseUrl, []) === null,
            'warnings' => trim($this->baseUrl) === '' ? [] : UtmBuilder::warnings($this->baseUrl, $this->values($first)),
            'tenant' => Filament::getTenant(),
            'builtName' => CampaignName::build(['date' => $this->name['date'] ?: now(Filament::getTenant()?->timezone ?: 'UTC')->toDateString()] + $this->name),
        ];
    }
}
