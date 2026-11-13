<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\BrandKit;
use App\Models\Client;
use App\Models\CreativeAsset;
use App\Models\CreativeBrief;
use App\Support\Arabic\Phrases;
use App\Support\StudioCopyGenerator;
use App\Support\TeamRoles;
use App\Support\Trial;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Wave 4 Studio Slice 1: #134 Brand Kit, #135 Asset Library, #131 AI Dialect Ad Copy, #136 Placement Previews, #54 Briefs & Client Approval Links.
 */
class StudioPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'studio';

    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Creative Studio';

    protected static ?string $title = 'Creative Studio';

    protected static ?string $slug = 'studio';

    protected static ?int $navigationSort = 48;

    protected string $view = 'filament.pages.studio';

    public ?int $clientId = null;

    // Brand Kit (#134)
    public string $primaryColor = '#14B8A6';

    public string $headingFont = 'IBM Plex Sans Arabic';

    public string $tone = 'professional';

    public string $defaultDialect = 'gulf';

    public string $bannedWordsInput = '';

    // AI Dialect Copy & Brief Builder (#131, #136, #54)
    public string $briefTitle = '';

    public string $objective = 'lead';

    public string $dialect = 'gulf';

    public string $placement = 'feed';

    public string $offer = '';

    public string $hook = '';

    public string $primaryText = '';

    public string $headline = '';

    public string $cta = 'تواصل معنا';

    // Asset Library (#135)
    public string $assetTitle = '';

    public string $assetFormat = 'image';

    public string $assetRatio = '1:1';

    public string $assetUrl = '';

    public string $assetTags = '';

    public function mount(): void
    {
        $agency = Filament::getTenant();
        $clients = Client::visibleTo(auth()->user(), $agency)->where('status', 'active')->orderBy('name')->get();
        $this->clientId = $clients->first()?->id;
        $this->loadClientKit();
    }

    public function updatedClientId(): void
    {
        $this->loadClientKit();
    }

    protected function currentClient(): ?Client
    {
        $agency = Filament::getTenant();
        if (! $agency || ! $this->clientId) {
            return null;
        }

        return Client::visibleTo(auth()->user(), $agency)->whereKey($this->clientId)->first();
    }

    protected function canEdit(): bool
    {
        $agency = Filament::getTenant();

        return $agency && TeamRoles::canManageData(auth()->user(), $agency) && ! Trial::isReadOnly($agency);
    }

    protected function loadClientKit(): void
    {
        $c = $this->currentClient();
        if (! $c) {
            return;
        }
        $kit = BrandKit::where('client_id', $c->id)->first();
        $this->primaryColor = $kit?->primary_color ?: '#14B8A6';
        $this->headingFont = $kit?->heading_font ?: 'IBM Plex Sans Arabic';
        $this->tone = $kit?->tone ?: 'professional';
        $own = Phrases::dialectOf($c); // XS-16 (R5): the client's dialect when the kit has none
        $this->defaultDialect = $kit?->default_dialect ?: (array_key_exists($own, StudioCopyGenerator::DIALECTS) ? $own : 'msa');
        $this->dialect = $this->defaultDialect;
        $this->bannedWordsInput = implode(', ', (array) ($kit?->banned_words ?? []));
        $this->generateDraft();
    }

    public function saveBrandKit(): void
    {
        $c = $this->currentClient();
        abort_unless($c && $this->canEdit(), 403);

        $words = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,،\n]+/u', $this->bannedWordsInput) ?: []))));
        BrandKit::updateOrCreate(
            ['agency_id' => $c->agency_id, 'client_id' => $c->id],
            [
                'primary_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', trim($this->primaryColor)) ? trim($this->primaryColor) : '#14B8A6',
                'heading_font' => mb_substr(trim($this->headingFont) ?: 'IBM Plex Sans Arabic', 0, 80),
                'tone' => in_array($this->tone, ['professional', 'friendly', 'urgent', 'luxury'], true) ? $this->tone : 'professional',
                'default_dialect' => array_key_exists($this->defaultDialect, StudioCopyGenerator::DIALECTS) ? $this->defaultDialect : 'gulf',
                'banned_words' => $words,
            ]
        );
        AuditLog::record('studio.brand_kit_saved', $c);
        Notification::make()->title(__('Brand kit saved'))->success()->send();
    }

    public function generateDraft(): void
    {
        $c = $this->currentClient();
        if (! $c) {
            return;
        }
        $draft = StudioCopyGenerator::generate($c, $this->dialect, $this->objective, $this->offer);
        $this->hook = $draft['hook'];
        $this->primaryText = $draft['primary_text'];
        $this->headline = $draft['headline'];
        $this->cta = $draft['cta'];
        if ($this->briefTitle === '') {
            $this->briefTitle = $c->name.' · '.strtoupper($this->dialect).' '.ucfirst($this->objective);
        }
    }

    public function saveBrief(): void
    {
        $c = $this->currentClient();
        abort_unless($c && $this->canEdit(), 403);
        if (trim($this->primaryText) === '' || trim($this->headline) === '') {
            Notification::make()->title(__('Headline and primary text are required.'))->danger()->send();

            return;
        }

        $brief = CreativeBrief::create([
            'agency_id' => $c->agency_id,
            'client_id' => $c->id,
            'title' => mb_substr(trim($this->briefTitle) ?: ($c->name.' Brief'), 0, 160),
            'objective' => $this->objective,
            'dialect' => array_key_exists($this->dialect, StudioCopyGenerator::DIALECTS) ? $this->dialect : 'gulf',
            'placement' => array_key_exists($this->placement, StudioCopyGenerator::PLACEMENTS) ? $this->placement : 'feed',
            'hook' => mb_substr(trim($this->hook), 0, 250) ?: null,
            'primary_text' => trim($this->primaryText),
            'headline' => mb_substr(trim($this->headline), 0, 160),
            'cta' => mb_substr(trim($this->cta) ?: 'Learn More', 0, 60),
            'status' => 'pending_approval',
        ]);
        AuditLog::record('studio.brief_created', $c, ['brief_id' => $brief->id]);
        Notification::make()->title(__('Creative brief saved with client approval link'))->success()->send();
    }

    public function addAsset(): void
    {
        $c = $this->currentClient();
        abort_unless($c && $this->canEdit(), 403);
        if (trim($this->assetTitle) === '') {
            return;
        }
        $tags = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,،\s]+/u', $this->assetTags) ?: []))));
        CreativeAsset::create([
            'agency_id' => $c->agency_id,
            'client_id' => $c->id,
            'title' => mb_substr(trim($this->assetTitle), 0, 160),
            'format' => in_array($this->assetFormat, ['image', 'video', 'carousel'], true) ? $this->assetFormat : 'image',
            'aspect_ratio' => in_array($this->assetRatio, ['1:1', '9:16', '16:9', '4:5'], true) ? $this->assetRatio : '1:1',
            'file_url' => mb_substr(trim($this->assetUrl), 0, 500) ?: null,
            'tags' => $tags,
            'networks' => ['meta', 'google_ads', 'tiktok', 'snapchat'],
        ]);
        $this->assetTitle = '';
        $this->assetUrl = '';
        $this->assetTags = '';
        Notification::make()->title(__('Asset added to library'))->success()->send();
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $clients = Client::visibleTo(auth()->user(), $agency)->where('status', 'active')->orderBy('name')->get();
        $client = $this->currentClient();
        $kit = $client ? BrandKit::where('client_id', $client->id)->first() : null;
        $violations = $kit ? $kit->violations($this->hook.' '.$this->primaryText.' '.$this->headline) : [];

        return [
            'clients' => $clients,
            'client' => $client,
            'canEdit' => $this->canEdit(),
            'kit' => $kit,
            'violations' => $violations,
            'dialects' => StudioCopyGenerator::DIALECTS,
            'placements' => StudioCopyGenerator::PLACEMENTS,
            'briefs' => $client ? CreativeBrief::where('client_id', $client->id)->latest('id')->limit(15)->get() : collect(),
            'assets' => $client ? CreativeAsset::where('client_id', $client->id)->latest('id')->limit(15)->get() : collect(),
        ];
    }
}
