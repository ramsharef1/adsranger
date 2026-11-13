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
    'calls_per_day' => 200,         // ~200 tool calls / day (Free, v2 §3); paid limits come with the plan records
    'max_payload_kb' => 64,
    // Abnormal activity → pause that AI connection + alert the owner (v1 §16.4 #6). Counts within the window.
    'anomaly' => ['window_minutes' => 10, 'scope_probes' => 3, 'schema_failures' => 5, 'injections' => 2, 'bursts' => 5],
];
