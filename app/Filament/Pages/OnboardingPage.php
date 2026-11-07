<?php

namespace App\Filament\Pages;

use App\Models\AgencyInvitation;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\Onboarding;
use App\Support\PlanLimits;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** D-188 required onboarding: one step per screen, saved after each step; steps 1–3 required, 4–7 skippable. */
class OnboardingPage extends Page
{
    protected static ?string $slug = 'onboarding';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.onboarding';

    public int $step = 1;

    public ?string $audience = null;

    public ?string $name = null;

    public ?string $country = null;

    public ?string $currency = null;

    public ?string $language = null;

    public ?string $timezone = null;

    public ?string $clientName = null;

    public ?string $vertical = null;

    public ?string $monthlyBudget = null;

    public ?string $targetCpr = null;

    public ?string $inviteEmail = null;

    public string $inviteRole = 'manager';

    public bool $emailDigest = true;

    public bool $emailAlerts = true;

    public static function getNavigationLabel(): string
    {
        return __('onboarding.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('onboarding.title');
    }

    public static function canAccess(): bool
    {
        $agency = Filament::getTenant();
        $user = auth()->user();

        return $agency && $user && ($user->isSuperAdmin() || $agency->members()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin'])->exists());
    }

    public function mount(): void
    {
        $a = Filament::getTenant();
        $user = auth()->user();
        $requested = (int) request()->query('step', 0);
        $this->step = max(1, min(Onboarding::LAST_STEP, (int) $a->onboarding_step));
        // Back from the Desk checklist: open a skippable step directly, once the required ones are done.
        if ($a->onboarding_completed_at && $requested >= Onboarding::REQUIRED_STEPS + 1 && $requested <= Onboarding::LAST_STEP) {
            $this->step = $requested;
        }
        $client = $a->clients()->orderBy('id')->first();
        $this->audience = $a->audience;
        $this->name = $a->name;
        $this->country = $a->country ?: 'JO';
        $this->currency = $a->currency ?: 'USD';
        $this->language = $a->language ?: 'ar';
        $this->timezone = $a->timezone ?: 'Asia/Amman';
        $this->clientName = $client?->name;
        $this->vertical = $client?->vertical;
        $this->monthlyBudget = $client?->monthly_budget !== null ? (string) $client->monthly_budget : null;
        $this->targetCpr = $client?->target_cpr !== null ? (string) $client->target_cpr : null;
        $this->emailDigest = (bool) ($user->email_digest || ! $a->onboarding_completed_at);
        $this->emailAlerts = (bool) ($user->email_alerts || ! $a->onboarding_completed_at);
        if ($this->step === 6 && ! Onboarding::hasTeamStep($a)) {
            $this->step = 7;
        }
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
        if ($this->step === 6 && ! Onboarding::hasTeamStep(Filament::getTenant())) {
            $this->step = 5;
        }
    }

    public function skip(): void
    {
        abort_if($this->step <= Onboarding::REQUIRED_STEPS, 403);
        Onboarding::track(Filament::getTenant(), $this->step, 'skipped');
        $this->advance();
    }

    public function save(): mixed
    {
        $a = Filament::getTenant();

        match ($this->step) {
            1 => $this->saveAudience(),
            2 => $this->saveWorkspace(),
            3 => $this->saveFirstClient(),
            4 => $this->saveTarget(),
            5 => null,
            6 => $this->sendInvite(),
            7 => $this->saveSummary(),
        };
        Onboarding::track($a, $this->step, 'done');

        return $this->advance();
    }

    private function advance(): mixed
    {
        $a = Filament::getTenant();
        $next = $this->step + 1;
        if ($next === 6 && ! Onboarding::hasTeamStep($a)) {
            $next = 7;
        }
        if ($next > Onboarding::LAST_STEP) {
            $a->forceFill(['onboarding_step' => Onboarding::LAST_STEP])->save();

            return $this->redirect(DailyDesk::getUrl(tenant: $a));
        }
        if ($next > $a->onboarding_step) {
            $a->forceFill(['onboarding_step' => $next])->save();
        }
        $this->step = $next;

        return null;
    }

    private function saveAudience(): void
    {
        $this->validate(['audience' => ['required', Rule::in(Onboarding::AUDIENCES)]]);
        Filament::getTenant()->forceFill(['audience' => $this->audience])->save();
    }

    private function saveWorkspace(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:140'],
            'country' => ['required', Rule::in(array_keys(config('adsranger.countries')))],
            'currency' => ['required', Rule::in(config('adsranger.currencies'))],
            'language' => ['required', Rule::in(array_keys(config('adsranger.languages')))],
            'timezone' => ['required', Rule::in(config('adsranger.timezones'))],
        ]);
        Filament::getTenant()->forceFill([
            'name' => $this->name, 'country' => $this->country, 'currency' => $this->currency,
            'language' => $this->language, 'timezone' => $this->timezone,
        ])->save();
        if (Filament::getTenant()->audience === 'business' && ! $this->clientName) {
            $this->clientName = $this->name;
        }
    }

    private function saveFirstClient(): void
    {
        $this->validate([
            'clientName' => ['required', 'string', 'min:2', 'max:140'],
            'vertical' => ['nullable', Rule::in(array_keys(config('adsranger.verticals')))],
        ]);
        $a = Filament::getTenant();
        $client = $a->clients()->orderBy('id')->first();
        if ($client) {
            $client->update(['name' => $this->clientName, 'vertical' => $this->vertical]);
        } else {
            Client::create(['agency_id' => $a->id, 'name' => $this->clientName, 'vertical' => $this->vertical,
                'currency' => $a->currency ?: 'USD', 'market' => $a->country]);
        }
        if (! $a->onboarding_completed_at) {
            $a->forceFill(['onboarding_completed_at' => now()])->save();
            AuditLog::record('onboarding.completed', $a, ['audience' => $a->audience]);
        }
    }

    private function saveTarget(): void
    {
        $this->validate([
            'monthlyBudget' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'targetCpr' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);
        Filament::getTenant()->clients()->orderBy('id')->first()?->update([
            'monthly_budget' => $this->monthlyBudget !== null && $this->monthlyBudget !== '' ? (float) $this->monthlyBudget : null,
            'target_cpr' => $this->targetCpr !== null && $this->targetCpr !== '' ? (float) $this->targetCpr : null,
        ]);
    }

    private function sendInvite(): void
    {
        $a = Filament::getTenant();
        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:190'],
            'inviteRole' => ['required', Rule::in(Onboarding::INVITE_ROLES)],
        ]);
        if (! PlanLimits::canAddSeat($a)) {
            throw ValidationException::withMessages(['inviteEmail' => __('onboarding.step6.no_seat')]);
        }
        AgencyInvitation::send($a, $this->inviteEmail, $this->inviteRole, auth()->user());
        Notification::make()->title(__('onboarding.step6.sent'))->success()->send();
        $this->inviteEmail = null;
    }

    private function saveSummary(): void
    {
        auth()->user()->forceFill(['email_digest' => $this->emailDigest, 'email_alerts' => $this->emailAlerts])->save();
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();

        return [
            'agency' => $a,
            'inHouse' => Onboarding::isInHouse($a) || $this->audience === 'business',
            'hasTeamStep' => Onboarding::hasTeamStep($a),
            'shownSteps' => Onboarding::hasTeamStep($a) ? Onboarding::LAST_STEP : Onboarding::LAST_STEP - 1,
            'shownIndex' => (! Onboarding::hasTeamStep($a) && $this->step === 7) ? 6 : $this->step,
            'networksUrl' => Networks::getUrl(tenant: $a),
            'importsUrl' => '/app/'.$a->slug.'/imports',
            'countries' => config('adsranger.countries'),
            'currencies' => config('adsranger.currencies'),
            'timezones' => config('adsranger.timezones'),
            'verticals' => config('adsranger.verticals'),
            'done' => (bool) $a->onboarding_completed_at,
        ];
    }
}
