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
    'member_roles' => [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'manager' => 'Account manager',
        'analyst' => 'Analyst (read only)',
    ],
];
