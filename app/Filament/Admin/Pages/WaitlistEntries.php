<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\WaitlistEntry;
use App\Support\WorkspaceOnboarding;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use UnitEnum;

/** Super-admin viewer & M1.1/M1.2 onboarding console for public /waitlist signups. */
class WaitlistEntries extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Waitlist';

    protected static ?string $title = 'Agency waitlist';

    protected static ?string $slug = 'waitlist';

    protected static ?int $navigationSort = 93;

    protected string $view = 'filament.admin.pages.waitlist-entries';

    public ?int $activeEntryId = null;

    public string $modalMode = 'invite';

    public string $selectedPlan = 'starter';

    public string $selectedTrial = '14d';

    public ?string $issuedMagicUrl = null;

    public ?string $issuedForEmail = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function openOnboardingModal(int $entryId, string $mode = 'invite'): void
    {
        abort_unless(static::canAccess(), 403);
        $entry = WaitlistEntry::query()->findOrFail($entryId);
        $this->activeEntryId = $entry->id;
        $this->modalMode = $mode === 'provision' ? 'provision' : 'invite';
        $this->selectedPlan = $entry->invited_plan ?: ($entry->audience === 'freelancer' || $entry->audience === 'business' ? 'solo' : 'starter');
        $this->selectedTrial = $entry->invited_trial_length ?: '14d';
    }

    public function closeOnboardingModal(): void
    {
        $this->activeEntryId = null;
    }

    public function submitOnboardingModal(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'selectedPlan' => ['required', Rule::in(array_keys(WorkspaceOnboarding::planOptions()))],
            'selectedTrial' => ['required', Rule::in(array_keys(WorkspaceOnboarding::trialOptions()))],
        ]);

        $entry = WaitlistEntry::query()->findOrFail($this->activeEntryId);
        $actor = auth()->user();

        if ($this->modalMode === 'invite') {
            if (! $entry->mayBeEmailed()) {
                Notification::make()->title(__('Cannot email without recorded consent'))->danger()->send();

                return;
            }
            WorkspaceOnboarding::sendEmailInvite($entry, $this->selectedPlan, $this->selectedTrial, $actor);
            $this->activeEntryId = null;
            Notification::make()->title(__('7-day owner invite sent'))->success()->send();

            return;
        }

        try {
            $res = WorkspaceOnboarding::provisionAndIssueMagicLink($entry, $this->selectedPlan, $this->selectedTrial, $actor);
        } catch (\DomainException) {
            Notification::make()->title(__('This email already has an account. Send a normal invitation instead.'))->danger()->send();

            return;
        }
        $this->issuedMagicUrl = $res['url'];
        $this->issuedForEmail = $entry->email;
        $this->activeEntryId = null;
        Notification::make()->title(__('Workspace provisioned and 7-day link ready'))->success()->send();
    }

    public function regenerateMagicLink(int $entryId): void
    {
        abort_unless(static::canAccess(), 403);
        $entry = WaitlistEntry::query()->findOrFail($entryId);
        try {
            $res = WorkspaceOnboarding::regenerateMagicLink($entry, auth()->user());
        } catch (\DomainException) {
            Notification::make()->title(__('This email already has an account. Send a normal invitation instead.'))->danger()->send();

            return;
        }
        $this->issuedMagicUrl = $res['url'];
        $this->issuedForEmail = $entry->email;
        Notification::make()->title(__('7-day setup link regenerated'))->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'entries' => WaitlistEntry::query()->with('provisionedAgency')->orderByDesc('created_at')->limit(250)->get(),
            'total' => WaitlistEntry::count(),
            'planOptions' => WorkspaceOnboarding::planOptions(),
            'trialOptions' => WorkspaceOnboarding::trialOptions(),
        ];
    }
}
