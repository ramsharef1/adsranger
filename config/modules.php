<?php

/*
 * ADSR-110 step 0.2 (D-149): module switches for work that is built but not yet ready for agencies.
 * A module that is off is hidden from agencies (no nav item, 403 on its page, its actions and routes do nothing).
 * Super admins, and the workspaces listed in MODULES_PREVIEW_AGENCIES (comma-separated slugs, test workspaces only),
 * keep a preview of every module so the review can run on the real code.
 * A module is switched on only after it passes the gate (review, plan gating, EX check), deployed by E1.
 */
return [
    'enabled' => [
        // E2 scaffolds (ADSR-060 "Scaffold"): no network calls yet, fake success states.
        'studio' => (bool) env('MODULE_STUDIO', false),                       // #129–#131, #134, #135, #143, #54 brief links, #93, #94
        'organic' => (bool) env('MODULE_ORGANIC', false),                     // #145–#148, #150
        'intelligence_hub' => (bool) env('MODULE_INTELLIGENCE_HUB', false),   // off-plan FR-023…FR-025, FR-027, FR-028; #50 / #103 radar
        'agency_ops_hub' => (bool) env('MODULE_AGENCY_OPS_HUB', false),       // #64–#67, #112, #113; budget auto-apply (FR-026)
        'webhooks' => (bool) env('MODULE_WEBHOOKS', false),                   // #77 / #115 webhooks + Looker / Sheets feed, own page (D-229)
        'client_onboarding' => (bool) env('MODULE_CLIENT_ONBOARDING', false), // #105 client onboarding link, own page (D-229)
        // Real but held until Rami's review / consent policy.
        'self_serve_deletion' => (bool) env('MODULE_SELF_SERVE_DELETION', false), // #79 delete actions: Rami sees them first (901 queue 14)
        'whatsapp_reports' => (bool) env('MODULE_WHATSAPP_REPORTS', false),       // #106 WhatsApp half belongs to #162 (D-94), needs #171 consent
        'custom_domains' => (bool) env('MODULE_CUSTOM_DOMAINS', false),           // #60 custom report domain: no SSL / routing on the server yet (D-249)
        'view_as' => (bool) env('MODULE_VIEW_AS', false),
        // SEO suite (D-239 slice 0.3): site audit A1 (FR-089, #183) + the E2 Arabic SEO workspace scaffold. Off until Product's EX check + Rami's go.
        'seo_audit' => (bool) env('MODULE_SEO_AUDIT', false),
        // AI Workspace M1 (D-270, spec v2 §6): off until Product's EX check (M1 screens §12) + Rami's go.
        'ai_workspace' => (bool) env('MODULE_AI_WORKSPACE', false),
        // PX-01 / PX-02 new panel shell (D-302): areas in the header, area sidebar, season ribbon, phone bottom bar.
        'panel_shell' => (bool) env('MODULE_PANEL_SHELL', false),
    ],

    'preview_agencies' => array_values(array_filter(array_map('trim', explode(',', (string) env('MODULES_PREVIEW_AGENCIES', ''))))),
];
