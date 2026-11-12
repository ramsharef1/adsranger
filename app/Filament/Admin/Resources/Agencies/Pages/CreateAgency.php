<?php

namespace App\Filament\Admin\Resources\Agencies\Pages;

use App\Filament\Admin\Resources\Agencies\AgencyResource;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Agency {
            $ownerEmail = strtolower(trim((string) ($data['owner_email'] ?? '')));
            $ownerName = trim((string) ($data['owner_name'] ?? ''));
            $ownerPassword = (string) ($data['owner_password'] ?? '');
            $ownerWorkspaceRole = in_array($data['owner_workspace_role'] ?? 'owner', ['owner', 'admin'], true)
                ? $data['owner_workspace_role']
                : 'owner';

            unset($data['owner_email'], $data['owner_name'], $data['owner_password'], $data['owner_workspace_role']);

            $rawSlug = trim((string) ($data['slug'] ?? ''));
            $slug = Agency::uniqueSlug($rawSlug !== '' ? $rawSlug : (string) $data['name']);

            $agency = new Agency;
            $agency->forceFill([
                'name' => trim((string) $data['name']),
                'slug' => $slug,
                'country' => $data['country'] ?? 'JO',
                'currency' => $data['currency'] ?? 'USD',
                'language' => in_array($data['language'] ?? 'ar', ['ar', 'en'], true) ? $data['language'] : 'ar',
                'plan' => $data['plan'] ?? 'pro',
                'status' => $data['status'] ?? 'active',
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                'signup_source' => 'admin',
                'module_overrides' => $data['module_overrides'] ?? null,
            ])->save();

            if ($ownerEmail !== '') {
                $user = User::query()->whereRaw('LOWER(email) = ?', [$ownerEmail])->first();
                if (! $user) {
                    $user = new User;
                    $user->forceFill([
                        'name' => $ownerName !== '' ? $ownerName : explode('@', $ownerEmail)[0],
                        'email' => $ownerEmail,
                        'password' => $ownerPassword,
                        'role' => 'member',
                        'email_verified_at' => now(),
                    ])->save();
                    AuditLog::record('user.created_by_admin', $user, ['role' => 'member', 'agency_id' => $agency->id]);
                }

                if ($ownerWorkspaceRole === 'owner' || ! $agency->owner_id) {
                    $agency->forceFill(['owner_id' => $user->id])->save();
                }

                $agency->members()->syncWithoutDetaching([
                    $user->id => ['role' => $ownerWorkspaceRole],
                ]);
            }

            AuditLog::record('agency.created_by_admin', $agency, [
                'plan' => $agency->plan,
                'status' => $agency->status,
                'owner_email' => $ownerEmail ?: null,
            ]);

            return $agency;
        });
    }
}
