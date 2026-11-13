<?php

/*
 * AI Workspace (spec v2, D-270). M1 slice 2: the AdsRanger connector at mcp.adsranger.com (D-283), OAuth 2.1 via Passport
 * (D-284). Limits are defaults until the plan records (slice 3, Business's plan table).
 */
return [
    'mcp_host' => env('AI_MCP_HOST', 'mcp.adsranger.com'),
    'ownership_version' => 'ai-own-v1',
    'token_minutes' => 60,          // short-lived access tokens (spec v1 §16.4 #3)
    'refresh_days' => 30,
    'calls_per_minute' => 30,
    'calls_per_day' => 200,         // fallback when a plan has no row below
    // D-286: tool calls per AI connection per day, by plan (Agency: 10,000, fair use).
    'calls_per_day_by_tier' => ['free' => 200, 'solo' => 1000, 'starter' => 2000, 'pro' => 5000, 'agency' => 10000],
    // D-286 / spec v2 §14.9 #1: Free covers one ad account spending up to this a month (USD); over it 2 months running →
    // upgrade notice, AI tools go view-only 14 days later. Data is never deleted.
    'free_spend_cap_usd' => 1000,
    'free_notice_days' => 14,
    'free_inactive_days' => 30,
    'max_payload_kb' => 64,
    // Abnormal activity → pause that AI connection + alert the owner (v1 §16.4 #6). Counts within the window.
    'anomaly' => ['window_minutes' => 10, 'scope_probes' => 3, 'schema_failures' => 5, 'injections' => 2, 'bursts' => 5],

    // M1 slice 4: the in-app AI box + night shift run on the agency's own API key (#159). Prices are per 1M tokens in USD,
    // used only for the labelled estimate and the monthly cap; the provider's bill is the real figure. Checked 2026-11.
    'own_key_models' => [
        'openai' => ['gpt-4.1-mini' => ['GPT-4.1 mini', 0.40, 1.60], 'gpt-4o-mini' => ['GPT-4o mini', 0.15, 0.60], 'gpt-4.1' => ['GPT-4.1', 2.00, 8.00]],
        'anthropic' => ['claude-haiku-4-5' => ['Claude Haiku 4.5', 1.00, 5.00], 'claude-sonnet-4-5' => ['Claude Sonnet 4.5', 3.00, 15.00]],
        'gemini' => ['gemini-2.5-flash' => ['Gemini 2.5 Flash', 0.30, 2.50], 'gemini-2.5-pro' => ['Gemini 2.5 Pro', 1.25, 10.00]],
    ],
    'own_key_billing' => [
        'openai' => 'https://platform.openai.com/settings/organization/billing/overview',
        'anthropic' => 'https://console.anthropic.com/settings/billing',
        'gemini' => 'https://aistudio.google.com/apikey',
    ],
    'max_output_tokens' => 1500,
    'night_shift' => ['max_clients' => 25, 'max_items_per_client' => 3, 'retry_hours' => 6], // FS-09: own-key outage queue
];
