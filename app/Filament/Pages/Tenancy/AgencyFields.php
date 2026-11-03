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
            TextInput::make('name')->label('Agency name')->required()->maxLength(120)
                ->helperText('Shown to your team and on client reports. You can change it later.'),
            Select::make('country')->label('Country')->options(config('adsranger.countries'))->searchable()->default('JO'),
            Select::make('timezone')->label('Time zone')->options($zones)->default('Asia/Amman')->required()
                ->helperText('Used for daily reports, alerts and "yesterday".'),
            Select::make('currency')->label('Reporting currency')->options($currencies)->default('USD')->required()
                ->helperText('Spend from every network is converted to this currency.'),
            Select::make('language')->label('Default language')->options(config('adsranger.languages'))->default('ar')->required(),
        ];
    }
}
