<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\SocialInboxMessage;
use App\Models\SocialModerationRule;
use App\Models\SocialPost;
use App\Support\CurrentClient;
use App\Support\OrganicCalendarManager;
use App\Support\OrganicSocialManager;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class OrganicSocialPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'organic';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $slug = 'organic-social';

    protected static ?int $navigationSort = 36;

    protected string $view = 'filament.pages.organic-social';

    public ?int $planClientId = null;

    public string $planContent = '';

    /** @var list<string> */
    public array $planPlatforms = [];

    public string $planType = 'post';

    public ?string $planAt = null;

    public const TYPES = ['post', 'reel', 'carousel', 'story'];

    public function mount(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $this->planClientId = CurrentClient::id($agency) ?: ($agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->value('id') : null);
    }

    protected function canManage(): bool
    {
        $agency = Filament::getTenant();

        return $agency !== null && TeamRoles::canManageData(auth()->user(), $agency);
    }

    /** @return array<string, string> */
    public static function platformNames(): array
    {
        return ['meta' => 'Facebook / Instagram', 'tiktok' => 'TikTok', 'x' => 'X', 'linkedin' => 'LinkedIn', 'snapchat' => 'Snapchat'];
    }

    /** @return array<string, array{0: string, 1: string}> stored status → label and tone */
    public static function statusLabels(): array
    {
        return [
            'draft' => [__('Draft'), ''], 'simulated' => [__('Draft'), ''], 'scheduled' => [__('Planned'), 'warn'],
            'published' => [__('Posted (marked by hand)'), 'ok'], 'failed' => [__('Failed'), 'bad'], 'cancelled' => [__('Cancelled'), ''],
        ];
    }

    /**
     * Adds a post to the content plan. Nothing is sent to any platform: publishing is not connected (D-229), so a
     * planned post is a reminder for the person who posts it.
     */
    public function planPost(): void
    {
        $agency = Filament::getTenant();
        if (! $agency || ! $this->canManage()) {
            return;
        }
        $data = $this->validate([
            'planClientId' => ['required', 'integer'],
            'planContent' => ['required', 'string', 'min:2', 'max:2200'],
            'planPlatforms' => ['required', 'array', 'min:1'],
            'planPlatforms.*' => ['in:'.implode(',', OrganicSocialManager::SUPPORTED_PLATFORMS)],
            'planType' => ['required', 'in:'.implode(',', self::TYPES)],
            'planAt' => ['nullable', 'date', 'after:now'],
        ], [], ['planClientId' => __('Client'), 'planContent' => __('Post text'), 'planPlatforms' => __('Platforms'), 'planType' => __('Type'), 'planAt' => __('Planned for')]);
        $client = Client::visibleTo(auth()->user(), $agency)->whereKey($data['planClientId'])->first();
        if (! $client) {
            return;
        }
        $at = $data['planAt'] ? Carbon::parse($data['planAt'], $agency->timezone ?: 'UTC')->utc() : null;
        OrganicSocialManager::schedulePost($client, trim($data['planContent']), $data['planPlatforms'], $data['planType'], $at);
        $this->reset(['planContent', 'planPlatforms', 'planAt']);
        Notification::make()->title(__('Added to the content plan'))->success()->send();
    }

    protected function ownPost(int $id): ?SocialPost
    {
        $agency = Filament::getTenant();
        if (! $agency || ! $this->canManage()) {
            return null;
        }

        return SocialPost::where('agency_id', $agency->id)->whereIn('client_id', Client::visibleTo(auth()->user(), $agency)->select('id'))->whereKey($id)->first();
    }

    /** The person posted it themselves and marks it here. */
    public function markPosted(int $id): void
    {
        $post = $this->ownPost($id);
        if ($post && in_array($post->status, ['draft', 'simulated', 'scheduled'], true)) {
            $post->update(['status' => 'published', 'published_at' => now()]);
            AuditLog::record('social.post_marked_posted', $post->client, ['post_id' => $post->id]);
        }
    }

    /** Kept in the list as cancelled; nothing is deleted. */
    public function cancelPost(int $id): void
    {
        $post = $this->ownPost($id);
        if ($post && in_array($post->status, ['draft', 'simulated', 'scheduled'], true)) {
            $post->update(['status' => 'cancelled']);
            AuditLog::record('social.post_cancelled', $post->client, ['post_id' => $post->id]);
        }
    }

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

        $visible = $clients->pluck('id');

        $posts = $agency ? SocialPost::query()
            ->where('agency_id', $agency->id)->whereIn('client_id', $visible)
            ->when($current, fn ($q) => $q->where('client_id', $current->id))
            ->latest()
            ->limit(20)
            ->get() : collect();

        $inbox = $agency ? SocialInboxMessage::query()
            ->where('agency_id', $agency->id)->whereIn('client_id', $visible)
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
            'canManageSocial' => $this->canManage(),
            'platformNames' => self::platformNames(),
            'statusLabels' => self::statusLabels(),
            'tz' => $agency?->timezone ?: 'UTC',
        ];
    }
}
