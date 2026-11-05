<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/** Form fields shared by agency registration and the agency profile. */
class AgencyFields
{
    public static function make(): array
    {
        $currencies = collect(config('adsranger.currencies'))->mapWithKeys(fn ($c) => [$c => $c])->all();
        $zones = collect(config('adsranger.timezones'))->mapWithKeys(fn ($z) => [$z => $z])->all();

        return [
            TextInput::make('name')->label(__('Agency name'))->required()->maxLength(120)
                ->helperText(__('Shown to your team and on client reports. You can change it later.')),
            Select::make('country')->label(__('Country'))->options(config('adsranger.countries'))->searchable()->default('JO'),
            Select::make('timezone')->label(__('Time zone'))->options($zones)->default('Asia/Amman')->required()
                ->helperText(__('Used for daily reports, alerts and "yesterday".')),
            Select::make('currency')->label(__('Reporting currency'))->options($currencies)->default('USD')->required()
                ->helperText(__('Spend from every network is converted to this currency.')),
            Select::make('language')->label(__('Default language'))->options(config('adsranger.languages'))->default('ar')->required(),
        ];
    }
}
