<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AeoBrandMonitor;
use App\Models\AiKey;
use App\Models\Client;
use App\Support\CurrentClient;
use App\Support\Seo\AeoCitationRadar;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * E2-065 AEO / GEO brand citation & AI readiness radar (module seo_audit, off). Owners, admins and managers set the
 * brand and run checks; analysts view. Probes use only the workspace's own AI key and its monthly cap (#159).
 */
class AeoCitationRadarPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'seo_audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'AI citation radar';

    protected static ?string $title = 'AI citation radar';

    protected static ?string $slug = 'aeo-radar';

    protected static ?int $navigationSort = 31;

    protected string $view = 'filament.pages.aeo-citation-radar';

    public string $brandAr = '';

    public string $brandEn = '';

    public string $domain = '';

    public string $promptsText = '';

    public string $keyProvider = '';

    public function mount(): void
    {
        $m = $this->monitor();
        if ($m) {
            $this->brandAr = (string) $m->brand_name_ar;
            $this->brandEn = (string) $m->brand_name_en;
            $this->domain = $m->primary_domain;
            $this->promptsText = implode("\n", (array) $m->tracked_prompts);
        }
        $this->keyProvider = (string) ($this->keys()->first()?->provider ?? '');
    }

    protected function client(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user) {
            return null;
        }
        $current = CurrentClient::resolve();
        if ($current && Client::visibleTo($user, $agency)->whereKey($current->id)->exists()) {
            return $current;
        }

        return Client::visibleTo($user, $agency)->orderBy('name')->first();
    }

    protected function monitor(): ?AeoBrandMonitor
    {
        $client = $this->client();

        return $client ? AeoBrandMonitor::where('agency_id', $client->agency_id)->where('client_id', $client->id)->first() : null;
    }

    protected function canManage(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    protected function keys()
    {
        $agency = Filament::getTenant();

        return $agency ? AiKey::where('agency_id', $agency->getKey())->where('monthly_cap_usd', '>', 0)->orderBy('provider')->get() : collect();
    }

    public function saveMonitor(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            return;
        }
        $this->validate([
            'brandAr' => ['nullable', 'string', 'max:120', 'required_without:brandEn'],
            'brandEn' => ['nullable', 'string', 'max:120'],
            'domain' => ['required', 'string', 'max:190', function (string $attr, mixed $value, \Closure $fail): void {
                if (AeoCitationRadar::origin((string) $value) === null) {
                    $fail(__('Enter a domain such as example.com.'));
                }
            }],
            'promptsText' => ['nullable', 'string', 'max:3000'],
        ]);
        $prompts = array_slice(array_values(array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 300), preg_split('/\R/u', $this->promptsText) ?: []))), 0, (int) config('seo_audit.aeo.max_prompts', 10));
        AeoBrandMonitor::updateOrCreate(
            ['agency_id' => $client->agency_id, 'client_id' => $client->id],
            ['brand_name_ar' => trim($this->brandAr) ?: null, 'brand_name_en' => trim($this->brandEn) ?: null, 'primary_domain' => (string) parse_url((string) AeoCitationRadar::origin($this->domain), PHP_URL_HOST), 'tracked_prompts' => $prompts]
        );
        Notification::make()->title(__('Saved'))->success()->send();
    }

    public function runReadiness(): void
    {
        $m = $this->monitor();
        if (! $m || ! $this->canManage()) {
            return;
        }
        AeoCitationRadar::readiness($m);
    }

    public function runProbe(): void
    {
        $m = $this->monitor();
        if (! $m || ! $this->canManage()) {
            return;
        }
        $key = $this->keys()->firstWhere('provider', $this->keyProvider);
        if (! $key) {
            Notification::make()->title(__('Add your own AI key with a monthly cap first (AI keys page).'))->warning()->send();

            return;
        }
        if ((array) $m->tracked_prompts === []) {
            Notification::make()->title(__('Add at least one question to ask.'))->warning()->send();

            return;
        }
        $check = AeoCitationRadar::probe($m, $key);
        if (! empty($check->signals['error'])) {
            Notification::make()->title(__('The AI answers stopped early: :reason', ['reason' => $check->signals['error']]))->warning()->send();
        }
    }

    protected function getViewData(): array
    {
        $m = $this->monitor();
        $probe = $m?->latest('byo_probe');

        return [
            'ar' => app()->getLocale() === 'ar',
            'client' => $this->client(),
            'monitor' => $m,
            'readiness' => $m?->latest('static_readiness'),
            'probe' => $probe,
            'rates' => AeoCitationRadar::rates($probe),
            'keys' => $this->keys(),
            'canManage' => $this->canManage(),
            'weights' => AeoCitationRadar::WEIGHTS,
        ];
    }

    /** @return array<string, string> */
    public static function signalLabels(): array
    {
        return [
            'robots' => __('AI crawlers allowed in robots.txt'),
            'llms_txt' => __('An /llms.txt file'),
            'organization' => __('Organization markup (JSON-LD)'),
            'faq' => __('FAQ markup (FAQPage)'),
            'offering' => __('Product or service markup'),
            'bilingual' => __('Arabic and English versions linked (hreflang)'),
            'brand_ar' => __('Arabic brand name on the Arabic page'),
            'brand_en' => __('English brand name on the English page'),
            'org_names' => __('Both brand names in the Organization markup'),
        ];
    }
}
