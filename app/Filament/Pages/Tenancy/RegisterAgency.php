<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Agency;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RegisterAgency extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('Create your agency workspace');
    }

    /**
     * D-61 one trial per workspace: someone who started a self-signup trial can't open more trial
     * workspaces from /app/new (support or an admin can). Nothing changes for invited users.
     */
    public static function canView(): bool
    {
        $user = auth()->user();
        if (! parent::canView() || ! $user || $user->isSuperAdmin()) {
            return parent::canView();
        }

        return ! $user->agencies()->wherePivot('role', 'owner')->where('signup_source', 'self_signup')->exists();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(AgencyFields::make());
    }

    protected function handleRegistration(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = auth()->user();
            $agency = Agency::create($data + [
                'slug' => Agency::uniqueSlug($data['name']),
                'owner_id' => $user->id,
                'plan' => 'starter',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays((int) config('adsranger.trial_days', 14)),
                'signup_source' => $user->isSuperAdmin() ? 'admin' : 'invite',
            ]);
            $agency->members()->attach($user->id, ['role' => 'owner']);

            return $agency;
        });
    }
}
