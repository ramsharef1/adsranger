<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): User {
            $agencyId = ! empty($data['agency_id']) ? (int) $data['agency_id'] : null;
            $workspaceRole = in_array($data['workspace_role'] ?? 'admin', ['owner', 'admin', 'manager', 'analyst'], true)
                ? $data['workspace_role']
                : 'admin';
            $platformRole = ($data['role'] ?? 'member') === 'super_admin' ? 'super_admin' : 'member';

            $user = new User;
            $user->forceFill([
                'name' => trim((string) $data['name']),
                'email' => strtolower(trim((string) $data['email'])),
                'password' => (string) $data['password'],
                'role' => $platformRole,
                'email_verified_at' => now(),
            ])->save();

            AuditLog::record('user.created_by_admin', $user, [
                'role' => $platformRole,
                'agency_id' => $agencyId,
                'workspace_role' => $agencyId ? $workspaceRole : null,
            ]);

            if ($agencyId && ($agency = Agency::query()->find($agencyId))) {
                $agency->members()->syncWithoutDetaching([
                    $user->id => ['role' => $workspaceRole],
                ]);
                if ($workspaceRole === 'owner' && ! $agency->owner_id) {
                    $agency->forceFill(['owner_id' => $user->id])->save();
                }
                AuditLog::record('agency.member_attached_by_admin', $agency, [
                    'user_id' => $user->id,
                    'role' => $workspaceRole,
                ]);
            }

            return $user;
        });
    }
}
