<?php

/*
 * E2-063 store webhooks (Salla «سلة», Zid «زد», Shopify) into the profit hub, module intelligence_hub (off).
 * Signatures: HMAC-SHA256 over the raw body with the secret the agency pasted from the store's app settings.
 */
return [
    'platforms' => [
        'salla' => ['header' => 'X-Salla-Signature', 'encoding' => 'hex', 'label' => 'Salla'],
        'zid' => ['header' => 'X-Zid-Webhook-Signature', 'encoding' => 'hex', 'label' => 'Zid'],
        'shopify' => ['header' => 'X-Shopify-Hmac-Sha256', 'encoding' => 'base64', 'label' => 'Shopify'],
    ],
    // Events older than this (when the payload carries a time) are refused as replays. Shopify retries for 48 hours.
    'replay_window_hours' => 72,
    'max_body_kb' => 512,
    // "Use store rates" appears once this many finished orders were observed.
    'min_orders_for_rates' => 20,
];
