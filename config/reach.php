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

    'networks' => ['arabclicks', 'linkaraby', 'admitad', 'impact', 'direct_client'],
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

    // R-4 network presets (E2-064). `subid` = the click-link parameter the network echoes back in its postback
    // ({publisher ref}_{placement}); `fields` = which postback parameter carries each value we read; `macros` = the
    // placeholder each network fills in, used only to print a ready postback URL on /admin › Reach; `trust` = the
    // recommended check (networks that can't sign a request use the IP allow-list). An endpoint can override
    // `subid` and any field on /admin › Reach. Confirm the macros against the network account when it is opened.
    'network_presets' => [
        'arabclicks' => ['subid' => 'aff_sub', 'trust' => 'ip_allowlist',
            'fields' => ['conversion_id' => 'transaction_id', 'payout' => 'payout', 'status' => 'status', 'offer_id' => 'offer_id'],
            'macros' => ['transaction_id' => '{transaction_id}', 'aff_sub' => '{aff_sub}', 'payout' => '{payout}', 'status' => '{status}', 'offer_id' => '{offer_id}']],
        'linkaraby' => ['subid' => 'aff_sub', 'trust' => 'ip_allowlist',
            'fields' => ['conversion_id' => 'transaction_id', 'payout' => 'payout', 'status' => 'status', 'offer_id' => 'offer_id'],
            'macros' => ['transaction_id' => '{transaction_id}', 'aff_sub' => '{aff_sub}', 'payout' => '{payout}', 'status' => '{status}', 'offer_id' => '{offer_id}']],
        'admitad' => ['subid' => 'subid', 'trust' => 'ip_allowlist',
            'fields' => ['conversion_id' => 'order_id', 'payout' => 'payment', 'status' => 'status', 'offer_id' => 'offer_id'],
            'macros' => ['order_id' => '[[[order_id]]]', 'subid' => '[[[subid]]]', 'payment' => '[[[payment_sum]]]', 'status' => '[[[status]]]', 'offer_id' => '[[[offer_id]]]']],
        'impact' => ['subid' => 'subId1', 'trust' => 'ip_allowlist',
            'fields' => ['conversion_id' => 'action_id', 'payout' => 'payout', 'status' => 'status', 'offer_id' => 'campaign_id'],
            'macros' => ['action_id' => '{ActionId}', 'subId1' => '{SubId1}', 'payout' => '{Payout}', 'status' => '{ActionStatus}', 'campaign_id' => '{CampaignId}']],
        'direct_client' => ['subid' => 'subid', 'trust' => 'hmac',
            'fields' => ['conversion_id' => 'conversion_id', 'payout' => 'payout', 'status' => 'status', 'offer_id' => 'offer_id'],
            'macros' => ['conversion_id' => '{conversion_id}', 'subid' => '{subid}', 'payout' => '{payout}', 'status' => '{status}', 'offer_id' => '{offer_id}']],
    ],

    // Click-link Sub-ID parameter per network (read by Serve when it builds the outbound link); mirrors network_presets.
    'subid_params' => ['arabclicks' => 'aff_sub', 'linkaraby' => 'aff_sub', 'admitad' => 'subid', 'impact' => 'subId1', 'direct_client' => 'subid'],

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
