<?php

use App\Providers\ActivityServiceProvider;
use App\Providers\AiWorkspaceServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;

return [
    AppServiceProvider::class,
    ActivityServiceProvider::class,
    AiWorkspaceServiceProvider::class,
    AppPanelProvider::class,
    AdminPanelProvider::class,
];
