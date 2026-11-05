<?php

namespace App\Filament\Pages\Auth;

use Illuminate\Contracts\Support\Htmlable;

/** System admin sign-in (UX-48): the heading and browser title name the admin panel in the page language. */
class AdminLogin extends Login
{
    /** The browser title is the panel brand name alone («AdsRanger · مدير النظام»), so the page adds nothing before it. */
    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getHeading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return __('AdsRanger · System admin');
    }

    /** #74: super admins sign in with password + two-step verification only. */
    protected function showsGoogle(): bool
    {
        return false;
    }
}
