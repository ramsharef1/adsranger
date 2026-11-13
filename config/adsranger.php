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
    'plans' => ['solo' => 'Solo', 'starter' => 'Starter', 'pro' => 'Pro', 'agency' => 'Agency'],
    // `read_only` and `expired` are set only by the D-61 trial lifecycle (self-signup agencies; config/trial.php).
    'agency_statuses' => ['active' => 'Active', 'trial' => 'Trial', 'read_only' => 'Read-only (trial ended)', 'expired' => 'Closed (trial ended)', 'suspended' => 'Suspended'],
    // Slugs an agency may not use (they clash with /app routes).
    // Agency slugs that would collide with panel-level /app routes (Filament: login, logout, new, profile,
    // password-reset, email-verification, register, multi-factor-authentication; ours: connect). Tenant-route check 2026-11-05.
    'reserved_slugs' => ['new', 'login', 'logout', 'register', 'profile', 'connect', 'password-reset', 'email-verification',
        'multi-factor-authentication', 'two-factor-authentication', 'admin', 'api', 'app', 'auth', 'oauth', 'webhooks', 'livewire', 'filament'],
    'trial_days' => 14,
    // D-53: the public waitlist page. Null until /waitlist is live (it waits for Brevo, D-22): the invite-only page
    // and the trial notices then show the support@ mailto link instead.
    'waitlist_url' => env('WAITLIST_URL'),
    // #158 self-signup. The flag stays OFF until Rami opens it (D-40/D-41/D-42). A super admin's switch in
    // /admin › Platform settings wins over this env default once it has been saved.
    'signup' => [
        'open' => (bool) env('SIGNUP_OPEN', false),
        'attempts_per_hour' => 5, // per IP
        'support_email' => 'support@adsranger.com',
        // Temporary-inbox services refused at signup (small built-in list; extend here).
        'disposable_domains' => [
            'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', 'grr.la', '10minutemail.com',
            '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmail.net', 'throwawaymail.com',
            'yopmail.com', 'yopmail.net', 'getnada.com', 'nada.email', 'trashmail.com', 'trashmail.de', 'dispostable.com',
            'maildrop.cc', 'mailnesia.com', 'mintemail.com', 'mohmal.com', 'fakeinbox.com', 'emailondeck.com',
            'tempinbox.com', 'spamgourmet.com', 'mailcatch.com', 'moakt.com', 'tmpmail.org', 'burnermail.io', 'mail.tm',
            'discard.email', 'inboxkitten.com', 'mytemp.email', 'tempr.email', 'emailfake.com', 'spam4.me',
        ],
        // D-61: one trial per email domain applies to business domains only; these shared providers are exempt.
        'free_mail_domains' => [
            'gmail.com', 'googlemail.com', 'hotmail.com', 'outlook.com', 'live.com', 'msn.com', 'yahoo.com', 'yahoo.co.uk',
            'ymail.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'proton.me', 'protonmail.com', 'gmx.com', 'gmx.de',
            'mail.com', 'zoho.com', 'yandex.com', 'yandex.ru', 'hotmail.fr', 'outlook.sa', 'live.co.uk',
        ],
    ],
    // D-61: while on trial (Pro tier) an agency is capped at these limits; the plan's own limit applies if lower.
    'trial_caps' => ['ad_accounts' => 5, 'seats' => 3],
    // Panel language when nothing else decides it (UX-3: Arabic first).
    'panel_locale' => 'ar',
    // Networks that start with report import only (no public API yet).
    'import_networks' => [
        'noon' => 'Noon Ads', 'talabat' => 'Talabat Ads', 'careem' => 'Careem Ads', 'arabyads' => 'ArabyAds',
        'anghami' => 'Anghami Ads', 'shahid' => 'Shahid (MBC)', 'osn' => 'OSN+', 'telegram' => 'Telegram Ads',
        'arabclicks' => 'ArabClicks', 'publisher' => 'Arabic publisher (other)', 'other' => 'Other network',
    ],
    // DS-24 (R5): currencies with 3 minor digits; shown and exported with 3 decimals everywhere.
    'three_decimal_currencies' => ['KWD', 'BHD', 'OMR', 'JOD', 'TND', 'LYD'],
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
