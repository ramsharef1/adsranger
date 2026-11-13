<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Support\Devices;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/** A-17 (IDEA-016): where this user is signed in, sign out one or all other devices, recent new-device sign-ins. */
class MyDevices extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Signed-in devices';

    protected static ?string $title = 'Signed-in devices';

    protected static ?string $slug = 'devices';

    protected static ?int $navigationSort = 86;

    protected string $view = 'filament.pages.my-devices';

    /** @return list<array{id: string, label: string, ip: ?string, last_active: Carbon, current: bool}> */
    public function sessions(): array
    {
        return Devices::forUser(auth()->user(), session()->getId());
    }

    /** @return Collection<int, AuditLog> */
    public function newDevices(): Collection
    {
        return AuditLog::where('user_id', auth()->id())->where('action', 'auth.new_device')
            ->where('created_at', '>=', now()->subDays(30))->latest('created_at')->limit(10)->get();
    }

    public function signOut(string $id): void
    {
        $ok = Devices::signOut(auth()->user(), $id, session()->getId());
        Notification::make()->title($ok ? __('That device is signed out.') : __('Nothing to sign out.'))->{$ok ? 'success' : 'warning'}()->send();
    }

    public function signOutOthers(): void
    {
        $n = Devices::signOutOthers(auth()->user(), session()->getId());
        Notification::make()->title(trans_choice('{0} No other device was signed in.|{1} :count other device is signed out.|[2,*] :count other devices are signed out.', $n))->success()->send();
    }
}
