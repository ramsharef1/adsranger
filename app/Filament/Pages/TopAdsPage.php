<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\TopAds;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/**
 * #51 Top ads with thumbnails (Starter). Best ads across the clients the member can see, with filters for client,
 * network and period, sorted by results, cost per result, CTR or spend. Read-only, so every role can use it.
 */
class TopAdsPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = 'Results';

    protected static ?string $navigationLabel = 'Top ads';

    protected static ?string $title = 'Top ads';

    protected static ?string $slug = 'top-ads';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.top-ads';

    /** Client id, or empty for all visible clients. */
    public $clientId = null;

    public string $network = '';

    public string $period = '30';

    public string $sort = 'results';

    public function mount(): void
    {
        $this->clientId = (int) request()->query('client') ?: null;
    }

    protected function clients()
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->orderBy('name')->get();
    }

    protected function getViewData(): array
    {
        $this->period = array_key_exists($this->period, TopAds::PERIODS) ? $this->period : '30';
        $this->sort = in_array($this->sort, TopAds::SORTS, true) ? $this->sort : 'results';
        $clients = $this->clients();
        $cid = (int) $this->clientId;
        $chosen = $cid ? $clients->where('id', $cid) : $clients;
        [$from, $to] = TopAds::range($this->period);
        $agency = Filament::getTenant();
        $all = TopAds::list($agency, $chosen, $from, $to, null, $this->sort, 500);
        $networks = TopAds::networks($all);
        if ($this->network !== '' && ! isset($networks[$this->network])) {
            $this->network = '';
        }
        $ads = array_slice($this->network === '' ? $all : array_values(array_filter($all, fn ($r) => $r['network'] === $this->network)), 0, 48);

        return ['clients' => $clients, 'ads' => $ads, 'networks' => $networks, 'from' => $from, 'to' => $to, 'base' => $agency->currency ?: 'USD'];
    }
}
