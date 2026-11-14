<?php

namespace App\Support\AiWorkspace;

use App\Models\AiNightShiftAction;
use App\Models\AuditLog;
use App\Models\AutomationStrategy;
use App\Models\Client;
use App\Models\User;

class NightShiftActionQueue
{
    public const ACTION_TYPES = ['pause_ad', 'adjust_budget_pct', 'add_negative_keyword'];

    /**
     * Proposes a supervised morning action from Night Shift v1.
     * Budget changes are clamped <= 20% and nothing executes automatically without human review.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function propose(Client $client, string $actionType, array $payload, string $rationale): AiNightShiftAction
    {
        $type = in_array($actionType, self::ACTION_TYPES, true) ? $actionType : 'pause_ad';
        if (isset($payload['delta_pct'])) {
            $raw = (float) $payload['delta_pct'];
            $sign = $raw < 0 ? -1.0 : 1.0;
            $payload['delta_pct'] = round($sign * min(AutomationStrategy::MAX_BUDGET_DELTA_PCT, abs($raw)), 2);
        }

        return AiNightShiftAction::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'action_type' => $type,
            'payload' => $payload,
            'rationale' => trim($rationale),
            'status' => 'pending_approval',
        ]);
    }

    public static function decide(AiNightShiftAction $action, User $user, string $decision): AiNightShiftAction
    {
        if ($action->status !== 'pending_approval') {
            return $action;
        }

        $nextStatus = in_array($decision, ['queue', 'confirm'], true) ? 'approved_queued' : 'rejected';

        $action->update([
            'status' => $nextStatus,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        AuditLog::record('ai_workspace.night_shift_action_decided', $action->agency, [
            'action_id' => $action->id,
            'client_id' => $action->client_id,
            'status' => $nextStatus,
        ]);

        return $action->fresh();
    }

    public static function undo(AiNightShiftAction $action, User $user): AiNightShiftAction
    {
        if ($action->status !== 'approved_queued') {
            return $action;
        }

        $action->update([
            'status' => 'undone',
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        AuditLog::record('ai_workspace.night_shift_action_undone', $action->agency, [
            'action_id' => $action->id,
            'client_id' => $action->client_id,
        ]);

        return $action->fresh();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending_approval' => __('Awaiting review'),
            'approved_queued' => __('Confirmed & queued'),
            'rejected' => __('Dismissed'),
            'undone' => __('Undone'),
            default => __('Awaiting review'),
        };
    }
}
