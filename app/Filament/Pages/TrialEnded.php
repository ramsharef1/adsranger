<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Http\Controllers\ExportController;
use App\Support\Business\TrialRequests;
use App\Support\PlanGate;
use App\Support\Trial;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * D-61: the page a closed (trial ended, status `expired`) self-signup workspace opens on after sign-in.
 * Says when the data is deleted, gives the owner the workspace ZIP, and owner/admin "See plans"; other roles are
 * told to ask the owner (EX-37). Not in the navigation.
 */
class TrialEnded extends Page
{
    use TranslatesPageLabels;

    protected static ?string $title = 'Your trial has ended';

    protected static ?string $slug = 'trial-ended';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.trial-ended';

    public string $reason = '';

    /** PS-32: owner / admin asks for more trial time; Rami answers in /admin › Customers. */
    public function requestMoreTime(): void
    {
        $a = Filament::getTenant();
        if (! PlanGate::canUpgrade(auth()->user(), $a)) {
            return;
        }
        $this->validate(['reason' => ['required', 'string', 'max:300']], [], ['reason' => __('reason')]);
        TrialRequests::ask($a, auth()->user(), $this->reason);
        $this->reason = '';
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $user = auth()->user();

        return [
            'agency' => $a,
            'closed' => Trial::isClosed($a),
            'readOnlyUntil' => Trial::readOnlyUntil($a),
            'deleteOn' => Trial::deleteOn($a),
            'canUpgrade' => PlanGate::canUpgrade($user, $a),
            'canZip' => ExportController::canDownloadWorkspace($user, $a),
            'extension' => TrialRequests::latest($a),
            'zipUrl' => route('filament.app.export.workspace', ['tenant' => $a->slug]),
        ];
    }
}
