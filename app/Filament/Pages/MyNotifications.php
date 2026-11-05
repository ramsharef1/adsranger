<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/** Each user's own email choices (both off by default). */
class MyNotifications extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'My notifications';

    protected static ?string $title = 'My notifications';

    protected static ?string $slug = 'notifications';

    protected static ?int $navigationSort = 85;

    protected string $view = 'filament.pages.my-notifications';

    public bool $alerts = false;

    public bool $digest = false;

    public function mount(): void
    {
        $this->alerts = (bool) auth()->user()->email_alerts;
        $this->digest = (bool) auth()->user()->email_digest;
    }

    public function save(): void
    {
        auth()->user()->forceFill(['email_alerts' => $this->alerts, 'email_digest' => $this->digest])->save();
        Notification::make()->title('Saved')->success()->send();
    }
}
