<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditAgencyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Agency settings';
    }

    public static function canView(Model $tenant): bool
    {
        return in_array(auth()->user()?->roleIn($tenant), ['owner', 'admin'], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(AgencyFields::make());
    }
}
