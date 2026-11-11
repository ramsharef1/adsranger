<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\PartnerApplication;
use App\Support\WorkspaceOnboarding;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use UnitEnum;

/** Super-admin viewer & M1.3 dual actions for /partners design-partner applications (Website P-25, D-219). */
class PartnerApplications extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Partner applications';

    protected static ?string $title = 'Partner applications';

    protected static ?string $slug = 'partner-applications';

    protected static ?int $navigationSort = 94;

    protected string $view = 'filament.admin.pages.partner-applications';

    public ?int $activeEntryId = null;

    public string $modalType = 'onboarding'; // onboarding | directory

    public string $onboardingMode = 'invite'; // invite | provision

    public string $selectedPlan = 'pro';

    public string $selectedTrial = '30d';

    public bool $publishToDirectory = false;

    public string $selectedBadge = 'certified';

    public ?string $issuedMagicUrl = null;

    public ?string $issuedForEmail = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function openOnboardingModal(int $entryId, string $mode = 'invite'): void
    {
        abort_unless(static::canAccess(), 403);
        $entry = PartnerApplication::query()->findOrFail($entryId);
        $this->activeEntryId = $entry->id;
        $this->modalType = 'onboarding';
        $this->onboardingMode = $mode === 'provision' ? 'provision' : 'invite';
        $this->selectedPlan = $entry->invited_plan ?: 'pro';
        $this->selectedTrial = $entry->invited_trial_length ?: '30d';
    }

    public function openDirectoryModal(int $entryId): void
    {
        abort_unless(static::canAccess(), 403);
        $entry = PartnerApplication::query()->findOrFail($entryId);
        $this->activeEntryId = $entry->id;
        $this->modalType = 'directory';
        $this->publishToDirectory = (bool) $entry->is_published;
        $this->selectedBadge = $entry->partner_badge ?: 'certified';
    }

    public function closeModal(): void
    {
        $this->activeEntryId = null;
    }

    public function submitOnboardingModal(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'onboardingMode' => ['required', Rule::in(['invite', 'provision'])],
            'selectedPlan' => ['required', Rule::in(array_keys(WorkspaceOnboarding::planOptions()))],
            'selectedTrial' => ['required', Rule::in(array_keys(WorkspaceOnboarding::trialOptions()))],
        ]);

        $entry = PartnerApplication::query()->findOrFail($this->activeEntryId);
        $actor = auth()->user();

        if ($this->onboardingMode === 'invite') {
            WorkspaceOnboarding::sendEmailInvite($entry, $this->selectedPlan, $this->selectedTrial, $actor);
            $this->activeEntryId = null;
            Notification::make()->title(__('7-day owner invite sent'))->success()->send();

            return;
        }

        $res = WorkspaceOnboarding::provisionAndIssueMagicLink($entry, $this->selectedPlan, $this->selectedTrial, $actor);
        $this->issuedMagicUrl = $res['url'];
        $this->issuedForEmail = $entry->email;
        $this->activeEntryId = null;
        Notification::make()->title(__('Workspace provisioned and 7-day link ready'))->success()->send();
    }

    public function submitDirectoryModal(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'selectedBadge' => ['required', Rule::in(array_keys(PartnerApplication::BADGES))],
        ]);

        $entry = PartnerApplication::query()->findOrFail($this->activeEntryId);
        $entry->forceFill([
            'is_published' => $this->publishToDirectory,
            'partner_badge' => $this->publishToDirectory ? $this->selectedBadge : null,
            'published_at' => $this->publishToDirectory ? ($entry->published_at ?? now()) : null,
        ])->save();

        AuditLog::record('partner.directory_updated', null, [
            'partner_application_id' => $entry->id,
            'agency_name' => $entry->agency_name,
            'is_published' => $this->publishToDirectory,
            'badge' => $entry->partner_badge,
        ]);

        $this->activeEntryId = null;
        Notification::make()->title($this->publishToDirectory ? __('Published to /partners directory') : __('Removed from /partners directory'))->success()->send();
    }

    public function regenerateMagicLink(int $entryId): void
    {
        abort_unless(static::canAccess(), 403);
        $entry = PartnerApplication::query()->findOrFail($entryId);
        $res = WorkspaceOnboarding::regenerateMagicLink($entry, auth()->user());
        $this->issuedMagicUrl = $res['url'];
        $this->issuedForEmail = $entry->email;
        Notification::make()->title(__('7-day setup link regenerated'))->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'entries' => PartnerApplication::query()->with('provisionedAgency')->orderByDesc('created_at')->limit(250)->get(),
            'total' => PartnerApplication::count(),
            'planOptions' => WorkspaceOnboarding::planOptions(),
            'trialOptions' => WorkspaceOnboarding::trialOptions(),
            'badgeOptions' => PartnerApplication::BADGES,
        ];
    }
}
