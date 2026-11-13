<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Models\CreativeFatigueAlert;
use App\Support\Studio\CreativeFatigueDetector;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * E2-066 creative fatigue board (module studio, off): open fatigue alerts for the clients this user can see, the peak
 * window in force, and a link to draft a replacement in Creative Studio. Spend is left out for clients with hide_spend.
 */
class CreativeFatigueBoardPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'studio';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-battery-50';

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Creative fatigue';

    protected static ?string $title = 'Creative fatigue';

    protected static ?string $slug = 'creative-fatigue';

    protected static ?int $navigationSort = 49;

    protected string $view = 'filament.pages.creative-fatigue-board';

    protected function clients()
    {
        $agency = Filament::getTenant();
        $user = auth()->user();

        return $agency && $user ? Client::visibleTo($user, $agency)->where('status', 'active')->orderBy('name')->get() : collect();
    }

    protected function canManage(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    public function checkNow(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $open = 0;
        foreach ($this->clients() as $client) {
            $open += CreativeFatigueDetector::run($client)['open'];
        }
        Notification::make()->title(trans_choice('{0} No tired ads found.|{1} One ad needs attention.|{2} Two ads need attention.|[3,10] :n ads need attention.|[11,*] :n ads need attention.', $open, ['n' => $open]))->success()->send();
    }

    protected function getViewData(): array
    {
        $clients = $this->clients();
        $alerts = CreativeFatigueAlert::with('client')->whereIn('client_id', $clients->pluck('id'))->whereNull('resolved_at')
            ->orderByRaw("CASE status WHEN 'fatigued' THEN 0 ELSE 1 END")->orderByDesc('spend_recent')->limit(100)->get();
        $peaks = [];
        foreach ($clients as $c) {
            if ($p = CreativeFatigueDetector::peakWindow($c, now($c->agency?->timezone ?: 'UTC')->subDay())) {
                $peaks[$p['key']] = $p;
            }
        }

        return [
            'ar' => app()->getLocale() === 'ar',
            'alerts' => $alerts,
            'peaks' => array_values($peaks),
            'canManage' => $this->canManage(),
            'normal' => CreativeFatigueDetector::thresholds(null),
            'tight' => CreativeFatigueDetector::thresholds(['key' => 'peak']),
        ];
    }
}
