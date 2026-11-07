<?php

namespace App\Filament\Resources\Alerts\Pages;

use App\Filament\Resources\Alerts\AlertResource;
use App\Models\Agency;
use App\Models\AuditLog;
use Filament\Resources\Pages\ViewRecord;

/**
 * #24 / I-X-4 phone alert page: what, client, since when, the detail (size vs threshold), who has it, and the
 * actions (resolve, snooze, assign) at 44 px. A Critical alert on a phone offers phone alerts once (#175, spec A).
 */
class ViewAlert extends ViewRecord
{
    protected static string $resource = AlertResource::class;

    protected string $view = 'filament.pages.alert-view';

    public function getTitle(): string
    {
        return $this->record->localTitle();
    }

    protected function getHeaderActions(): array
    {
        return AlertResource::alertActions();
    }

    /** @return array<int, array{at: mixed, who: string, what: string}> */
    public function history(): array
    {
        $labels = ['alert.assigned' => __('Assigned'), 'alert.snoozed' => __('Snoozed'), 'alert.resolved' => __('Resolved'), 'alert.reopened' => __('Reopened')];

        return AuditLog::with('user')->where('subject_type', Agency::class)->where('subject_id', $this->record->agency_id)
            ->where('action', 'like', 'alert.%')->orderByDesc('id')->limit(500)->get()
            ->filter(fn (AuditLog $l) => (int) ($l->meta['alert_id'] ?? 0) === (int) $this->record->id)
            ->take(20)
            ->map(fn (AuditLog $l) => ['at' => $l->created_at, 'who' => (string) ($l->user?->name ?? __('AdsRanger')), 'what' => $labels[$l->action] ?? $l->action])
            ->values()->all();
    }
}
