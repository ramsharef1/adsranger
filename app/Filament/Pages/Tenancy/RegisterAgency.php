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
            ]);
            $agency->members()->attach($user->id, ['role' => 'owner']);

            return $agency;
        });
    }
}
