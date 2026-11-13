<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\Seo\ToolGuard;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/** BZ-10: free-tool limit hits, unreadable hosts and blocks (auto 24 h or by hand), with lift / block controls. */
class ToolGuardBoard extends Page
{
    use TranslatesPageLabels;

    public const TOOLS = ['page-audit', 'hreflang-checker', 'ad-pixel-checker', 'llms-txt-generator', 'robots-sitemap-checker'];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Tool guard';

    protected static ?string $title = 'Free tools guard';

    protected static ?string $slug = 'tool-guard';

    protected static ?int $navigationSort = 93;

    protected string $view = 'filament.admin.pages.tool-guard';

    public string $host = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function blockHost(): void
    {
        $ok = ToolGuard::blockHost($this->host);
        Notification::make()->title($ok ? __('Host blocked for the free tools.') : __('Enter a host like example.com.'))->{$ok ? 'success' : 'danger'}()->send();
        if ($ok) {
            $this->host = '';
        }
    }

    public function lift(string $key): void
    {
        ToolGuard::lift($key);
        Notification::make()->title(__('Block lifted.'))->success()->send();
    }

    protected function getViewData(): array
    {
        $days = collect(range(0, 6))->map(fn ($i) => now('Asia/Amman')->subDays($i)->format('Y-m-d'))->all();
        $rows = [];
        foreach (self::TOOLS as $t) {
            $rows[$t] = ['today' => ToolGuard::counts($t), 'week' => collect($days)->map(fn ($d) => ToolGuard::counts($t, $d))
                ->reduce(fn ($c, $x) => ['limit' => $c['limit'] + $x['limit'], 'fail' => $c['fail'] + $x['fail'], 'refused' => $c['refused'] + $x['refused']], ['limit' => 0, 'fail' => 0, 'refused' => 0])];
        }

        return ['rows' => $rows, 'auto' => ToolGuard::autoBlocks(), 'manual' => ToolGuard::manualHosts()];
    }
}
