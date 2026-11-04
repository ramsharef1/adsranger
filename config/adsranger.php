<?php

// Shared AdsRanger option lists (countries, currencies, languages, plans, member roles).

return [
    'countries' => [
        'JO' => 'Jordan', 'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'KW' => 'Kuwait',
        'QA' => 'Qatar', 'BH' => 'Bahrain', 'OM' => 'Oman', 'EG' => 'Egypt', 'IQ' => 'Iraq',
        'LB' => 'Lebanon', 'PS' => 'Palestine', 'MA' => 'Morocco', 'TN' => 'Tunisia', 'DZ' => 'Algeria',
        'LY' => 'Libya', 'SY' => 'Syria', 'YE' => 'Yemen', 'SD' => 'Sudan', 'TR' => 'Turkey',
        'GB' => 'United Kingdom', 'US' => 'United States', 'CA' => 'Canada', 'OTHER' => 'Other',
    ],
    'currencies' => ['USD', 'JOD', 'SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'EGP', 'IQD', 'EUR', 'GBP', 'TRY'],
    'timezones' => [
        'Asia/Amman', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Bahrain',
        'Asia/Muscat', 'Africa/Cairo', 'Asia/Baghdad', 'Asia/Beirut', 'Asia/Gaza', 'Africa/Casablanca',
        'Europe/Istanbul', 'Europe/London', 'UTC',
    ],
    'languages' => ['ar' => 'العربية', 'en' => 'English'],
    'plans' => ['starter' => 'Starter', 'pro' => 'Pro', 'agency' => 'Agency'],
    'agency_statuses' => ['active' => 'Active', 'trial' => 'Trial', 'suspended' => 'Suspended'],
    // Slugs an agency may not use (they clash with /app routes).
    // Agency slugs that would collide with panel-level /app routes (Filament: login, logout, new, profile,
    // password-reset, email-verification, register, multi-factor-authentication; ours: connect). Tenant-route check 2026-11-05.
    'reserved_slugs' => ['new', 'login', 'logout', 'register', 'profile', 'connect', 'password-reset', 'email-verification',
        'multi-factor-authentication', 'two-factor-authentication', 'admin', 'api', 'app', 'auth', 'oauth', 'webhooks', 'livewire', 'filament'],
    'trial_days' => 14,
    // Networks that start with report import only (no public API yet).
    'import_networks' => [
        'noon' => 'Noon Ads', 'talabat' => 'Talabat Ads', 'careem' => 'Careem Ads', 'arabyads' => 'ArabyAds',
        'anghami' => 'Anghami Ads', 'shahid' => 'Shahid (MBC)', 'osn' => 'OSN+', 'telegram' => 'Telegram Ads',
        'arabclicks' => 'ArabClicks', 'publisher' => 'Arabic publisher (other)', 'other' => 'Other network',
    ],
    'verticals' => [
        'clinic' => 'Clinic / medical', 'charity' => 'Charity / NGO', 'ecommerce' => 'E-commerce', 'real_estate' => 'Real estate',
        'education' => 'Education', 'restaurant' => 'Restaurant / food', 'beauty' => 'Beauty / salon', 'automotive' => 'Automotive',
        'travel' => 'Travel / tourism', 'services' => 'Services', 'b2b' => 'B2B', 'app' => 'App', 'media' => 'Media', 'other' => 'Other',
    ],
    'member_roles' => [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'manager' => 'Account manager',
        'analyst' => 'Analyst (read only)',
    ],
];
