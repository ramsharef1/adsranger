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

    // #175 phone alerts (Pro+): quiet hours in the workspace time zone (D-141), weekend off (D-122), spend override.
    public string $quietStart = '23:00';

    public string $quietEnd = '07:00';

    public bool $weekendOff = true;

    public bool $overrideSpend = false;

    public function mount(): void
    {
        $u = auth()->user();
        $this->alerts = (bool) $u->email_alerts;
        $this->digest = (bool) $u->email_digest;
        $this->quietStart = (string) ($u->push_quiet_start ?: '23:00');
        $this->quietEnd = (string) ($u->push_quiet_end ?: '07:00');
        $this->weekendOff = (bool) $u->push_weekend_off;
        $this->overrideSpend = (bool) $u->push_override_spend;
    }

    /** @return array<string, string> */
    public function hours(): array
    {
        return collect(range(0, 23))->mapWithKeys(fn ($h) => [sprintf('%02d:00', $h) => sprintf('%02d:00', $h)])->all();
    }

    public function phoneAllowed(): bool
    {
        $a = \Filament\Facades\Filament::getTenant();

        return $a && \App\Support\PlanGate::allows($a, 'phone_push');
    }

    public function devices(): int
    {
        return auth()->user()->pushSubscriptions()->count();
    }

    public function save(): void
    {
        $h = array_keys($this->hours());
        $set = ['email_alerts' => $this->alerts, 'email_digest' => $this->digest];
        if ($this->phoneAllowed()) {
            $set += [
                'push_quiet_start' => in_array($this->quietStart, $h, true) ? $this->quietStart : '23:00',
                'push_quiet_end' => in_array($this->quietEnd, $h, true) ? $this->quietEnd : '07:00',
                'push_weekend_off' => $this->weekendOff,
                'push_override_spend' => $this->overrideSpend,
            ];
        }
        auth()->user()->forceFill($set)->save();
        Notification::make()->title(__('Saved'))->success()->send();
    }
}
