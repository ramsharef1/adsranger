<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\SocialInboxMessage;
use App\Models\SocialModerationRule;
use App\Models\SocialPost;
use App\Support\CurrentClient;
use App\Support\OrganicCalendarManager;
use App\Filament\Concerns\HeldBackModule;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class OrganicSocialPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'organic';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $slug = 'organic-social';

    protected static ?int $navigationSort = 36;

    protected string $view = 'filament.pages.organic-social';

    public static function getNavigationLabel(): string
    {
        return __('Organic social');
    }

    public function getTitle(): string
    {
        return __('Organic social & inbox');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Create & plan');
    }

    public function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $clients = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect();
        $current = CurrentClient::resolve();

        $posts = $agency ? SocialPost::query()
            ->where('agency_id', $agency->id)
            ->when($current, fn ($q) => $q->where('client_id', $current->id))
            ->latest()
            ->limit(20)
            ->get() : collect();

        $inbox = $agency ? SocialInboxMessage::query()
            ->where('agency_id', $agency->id)
            ->when($current, fn ($q) => $q->where('client_id', $current->id))
            ->latest()
            ->limit(20)
            ->get() : collect();

        $rulesCount = $agency ? SocialModerationRule::query()
            ->where('agency_id', $agency->id)
            ->when($current, fn ($q) => $q->where('client_id', $current->id))
            ->where('is_active', true)
            ->count() : 0;

        $bestTime = $current ? OrganicCalendarManager::recommendBestTimeToPost($current) : [
            'best_day' => 'Thursday',
            'recommended_hour' => '19:00',
            'avg_engagements' => 0.0,
        ];

        return [
            'clients' => $clients,
            'currentClient' => $current,
            'posts' => $posts,
            'inbox' => $inbox,
            'rulesCount' => $rulesCount,
            'bestTime' => $bestTime,
        ];
    }
}
