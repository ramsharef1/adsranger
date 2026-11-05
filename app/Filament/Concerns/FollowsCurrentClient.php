<?php

namespace App\Filament\Concerns;

use App\Support\CurrentClient;

/** UX-81: picking a client in a page's own picker also becomes the top bar's current client. */
trait FollowsCurrentClient
{
    public function updatedClientId($value): void
    {
        CurrentClient::remember($value);
    }
}
