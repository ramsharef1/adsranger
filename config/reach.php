<?php

/*
 * FR-REACH (AdsRanger Reach, module `reach`, off). Publisher monetisation: offers, coupons, S2S postbacks, wallets.
 * D-262: no real money moves before the company (LLC) is registered. Every ledger row is an "estimate"; withdrawals
 * and early cashouts are recorded as requests only, and every screen says that billing waits for launch.
 */
return [
    // Share of an offer's payout that goes to the publisher when the import gives no publisher payout.
    'publisher_share' => 0.70,

    // Lifetime referral override: share of a referred publisher's net commission credited to the referrer (from our share).
    'referral_override' => 0.05,

    'wallet' => [
        'currency' => 'USD',
        'min_withdrawal' => 50,
        'clear_after_days' => 30,          // pending → cleared once the network's lock period has passed
        'early_cashout_fee' => 0.06,       // 6% of the advanced amount
        'early_cashout_cap' => 0.85,       // at most 85% of pending can be advanced …
        'early_cashout_reserve' => 0.15,   // … and 15% is held as a reserve that refunds net against first
        'ad_credit_bonus' => 0.15,         // +15% when cleared earnings are turned into AdsRanger ad credit
    ],

    'attribution' => [
        'copy_intent_hours' => 24,          // tier 3: a pool code copied within this window before the sale
        'pool_min' => 10,
        'pool_max' => 20,
    ],

    'networks' => ['arabclicks', 'admitad', 'impact', 'direct_client'],
    'offer_types' => ['cps', 'cpi', 'cpl'],
    'billing_modes' => ['escrow_prepaid', 'agency_guaranteed', 'postpaid_invoice'],

    // CSV importer: accepted headers (English or Arabic) → field.
    'csv_headers' => [
        'title' => ['title', 'offer', 'name', 'العنوان', 'العرض', 'اسم العرض'],
        'network' => ['network', 'source', 'الشبكة', 'المصدر'],
        'external_id' => ['external_id', 'offer_id', 'id', 'رقم العرض', 'المعرف'],
        'payout_amount' => ['payout', 'payout_amount', 'commission', 'العمولة', 'المبلغ'],
        'publisher_payout_amount' => ['publisher_payout', 'publisher_payout_amount', 'عمولة الناشر', 'حصة الناشر'],
        'currency' => ['currency', 'العملة'],
        'offer_type' => ['type', 'offer_type', 'model', 'النوع', 'نوع العرض'],
        'allow_incent' => ['incent', 'allow_incent', 'incentivized', 'incentivised', 'تحفيزي', 'يسمح بالتحفيز'],
        'coupon_code' => ['coupon', 'coupon_code', 'code', 'الكوبون', 'كود الخصم', 'الكود'],
        'landing_url' => ['url', 'landing_url', 'link', 'الرابط'],
        'category' => ['category', 'الفئة', 'التصنيف'],
        'countries' => ['countries', 'geo', 'country', 'الدول', 'الدولة'],
        'keywords' => ['keywords', 'الكلمات', 'الكلمات المفتاحية'],
    ],

    // Categories a family-safe publisher never shows.
    'unsafe_categories' => ['adult', 'dating', 'gambling', 'alcohol', 'tobacco', 'crypto_leverage'],

    'max_blocked_domains' => 20,

    // Query parameter each network reads back as the Sub-ID in its postback ({publisher ref}_{placement}). Set from the
    // network's own docs when an account is opened; "subid" until then.
    'subid_params' => ['arabclicks' => 'subid', 'admitad' => 'subid', 'impact' => 'subid', 'direct_client' => 'subid'],

    // R-2 serve: the cache store for the stateless endpoints (null = the app's default store; set to "redis" where it runs).
    'cache_store' => env('REACH_CACHE_STORE'),
    'serve_cache_seconds' => 60,
    'max_offers_per_view' => 3,

    // AI agents we count as "AI reads" (user-agent substring → label).
    'ai_agents' => [
        'GPTBot' => 'GPTBot', 'OAI-SearchBot' => 'OAI-SearchBot', 'ChatGPT-User' => 'ChatGPT-User',
        'ClaudeBot' => 'ClaudeBot', 'Claude-User' => 'Claude-User', 'anthropic-ai' => 'anthropic-ai',
        'PerplexityBot' => 'PerplexityBot', 'Perplexity-User' => 'Perplexity-User',
        'Google-Extended' => 'Google-Extended', 'CCBot' => 'CCBot', 'Applebot-Extended' => 'Applebot-Extended',
        'meta-externalagent' => 'meta-externalagent', 'Bytespider' => 'Bytespider',
    ],
];
