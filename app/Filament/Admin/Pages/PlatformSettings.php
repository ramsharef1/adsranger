<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Support\Signup;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/** Platform switches for super admins: the `signup.open` flag (#158, off by default) and the trial domain allowlist (D-61). */
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

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function mount(): void
    {
        $this->signupOpen = Signup::isOpen();
        $this->allowlist = implode("\n", Signup::allowlist());
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $domains = collect(preg_split('/[\s,]+/', strtolower($this->allowlist)) ?: [])
            ->map(fn ($d) => ltrim(trim($d), '@'))
            ->filter(fn ($d) => preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $d))->unique()->values()->all();

        $before = ['signup_open' => Signup::isOpen(), 'allowlist' => Signup::allowlist()];
        PlatformSetting::put(Signup::FLAG, $this->signupOpen);
        PlatformSetting::put(Signup::ALLOWLIST, $domains);
        $after = ['signup_open' => $this->signupOpen, 'allowlist' => $domains];
        $this->allowlist = implode("\n", $domains);

        if ($before !== $after) {
            AuditLog::record('platform.settings_updated', null, ['before' => $before, 'after' => $after]);
        }
        Notification::make()->title(__('Settings saved'))->success()->send();
    }
}
