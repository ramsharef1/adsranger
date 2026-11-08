<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Agency;
use App\Models\NetworkRequest;
use App\Models\PartnerApplication;
use App\Models\WaitlistEntry;
use App\Support\Signup;
use Filament\Widgets\Widget;

class SystemOverviewWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.system-overview-widget';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    protected function getViewData(): array
    {
        $enabledModules = (array) config('modules.enabled', []);
        $offModulesCount = collect($enabledModules)->filter(fn ($v) => ! $v)->count();

        return [
            'agenciesTotal' => Agency::count(),
            'agenciesActive' => Agency::where('status', 'active')->count(),
            'waitlistTotal' => WaitlistEntry::count(),
            'waitlistConsented' => WaitlistEntry::whereNotNull('consent_at')->count(),
            'partnersTotal' => PartnerApplication::count(),
            'networkRequestsTotal' => NetworkRequest::count(),
            'signupOpen' => Signup::isOpen(),
            'offModulesCount' => $offModulesCount,
            'totalModulesCount' => count($enabledModules),
        ];
    }
}
