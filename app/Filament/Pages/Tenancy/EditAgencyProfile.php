<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\AuditLog;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditAgencyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('Agency settings');
    }

    public static function canView(Model $tenant): bool
    {
        return in_array(auth()->user()?->roleIn($tenant), ['owner', 'admin'], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...AgencyFields::make(),
            // #74: members without two-step verification are sent to set it up before they can open this workspace.
            Toggle::make('require_two_factor')->label(__('Require two-step verification for all members'))
                ->helperText(__('Members who haven\'t set it up are asked to add an authenticator app before they can open this workspace. This includes you.')),
        ]);
    }

    protected function afterSave(): void
    {
        if ($this->tenant->wasChanged('require_two_factor')) {
            AuditLog::record('agency.require_2fa_changed', $this->tenant, ['on' => (bool) $this->tenant->require_two_factor]);
        }
    }
}
