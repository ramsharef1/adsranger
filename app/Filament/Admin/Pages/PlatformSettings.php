<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\ModuleEarlyAccessRequest;
use App\Models\PlanUpgradeRequest;
use App\Models\PlatformSetting;
use App\Support\AiSettings;
use App\Support\Signup;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/** Platform switches for super admins: `signup.open` (#158), trial domain allowlist (D-61), and M4.1 Encrypted AI Settings. */
class PlatformSettings extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Platform settings';

    protected static ?string $title = 'Platform settings';

    protected static ?string $slug = 'platform-settings';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.admin.pages.platform-settings';

    public bool $signupOpen = false;

    public string $allowlist = '';

    public string $aiProvider = 'openai';

    public string $aiModel = 'gpt-4o-mini';

    public string $aiApiKeyInput = '';

    public ?string $aiTestMessage = null;

    public ?bool $aiTestOk = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function mount(): void
    {
        $this->signupOpen = Signup::isOpen();
        $this->allowlist = implode("\n", Signup::allowlist());
        $this->aiProvider = AiSettings::provider();
        $this->aiModel = AiSettings::model($this->aiProvider);
    }

    public function updatedAiProvider(string $value): void
    {
        $providers = AiSettings::providers();
        if (isset($providers[$value])) {
            $this->aiModel = (string) array_key_first($providers[$value]['models']);
        }
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $domains = collect(preg_split('/[\s,]+/', strtolower($this->allowlist)) ?: [])
            ->map(fn ($d) => ltrim(trim($d), '@'))
            ->filter(fn ($d) => preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $d))->unique()->values()->all();

        $before = [
            'signup_open' => Signup::isOpen(),
            'allowlist' => Signup::allowlist(),
            'ai_provider' => AiSettings::provider(),
            'ai_model' => AiSettings::model(),
        ];

        PlatformSetting::put(Signup::FLAG, $this->signupOpen);
        PlatformSetting::put(Signup::ALLOWLIST, $domains);
        AiSettings::saveConfig($this->aiProvider, $this->aiModel, $this->aiApiKeyInput !== '' ? $this->aiApiKeyInput : null);
        $this->aiApiKeyInput = '';

        $after = [
            'signup_open' => $this->signupOpen,
            'allowlist' => $domains,
            'ai_provider' => AiSettings::provider(),
            'ai_model' => AiSettings::model(),
        ];
        $this->allowlist = implode("\n", $domains);

        if ($before !== $after) {
            AuditLog::record('platform.settings_updated', null, ['before' => $before, 'after' => $after]);
        }
        Notification::make()->title(__('Settings saved'))->success()->send();
    }

    public function testAiConnection(): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->aiApiKeyInput !== '') {
            AiSettings::saveConfig($this->aiProvider, $this->aiModel, $this->aiApiKeyInput);
            $this->aiApiKeyInput = '';
        }
        $res = AiSettings::testConnection();
        $this->aiTestOk = $res['ok'];
        $this->aiTestMessage = $res['message'];

        $n = Notification::make()->title($res['message']);
        ($res['ok'] ? $n->success() : $n->warning())->send();
    }

    protected function getViewData(): array
    {
        return [
            'aiProviders' => AiSettings::providers(),
            'maskedAiKey' => AiSettings::maskedKey(),
            'pendingUpgrades' => PlanUpgradeRequest::query()->with('agency')->where('status', 'pending')->latest()->limit(20)->get(),
            'pendingEarlyAccess' => ModuleEarlyAccessRequest::query()->with('agency')->where('status', 'pending')->latest()->limit(20)->get(),
        ];
    }
}
