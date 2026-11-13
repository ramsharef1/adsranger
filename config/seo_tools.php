<?php

/*
 * Free SEO tools (SEO slice 0 step 0.4, PR-015 Part 2). While `public` is false the tool pages answer at their URLs
 * for checks (Product EX check, Growth live check) but send noindex, stay out of the sitemap and the menu, and no
 * page links to them. Rami approves each publish (SEO_Slice0_Spec 0.4 gate) before this is switched on.
 */
return [
    'public' => (bool) env('SEO_TOOLS_PUBLIC', false),

    // GB-01 (Rami, 2026-11-13 03:1x: "List them as they are"): the /guides and /blog articles and the glossary terms are in
    // the sitemap while Growth rewrites them (GB-10, guides first). Setting SITE_ARTICLES_PUBLIC=false holds the guides
    // and blog instead: noindex, out of the sitemap, /llms.txt and the hubs' cards, and no page links to them.
    'articles_public' => (bool) env('SITE_ARTICLES_PUBLIC', true),

    // D-279 (Rami: "Noindex until rewritten"): with SITE_ARTICLES_PUBLIC=false the glossary terms are held too (noindex,
    // out of the sitemap; the /glossary index stays). A page Growth has rewritten to its band goes back in by listing its
    // path here (comma-separated, e.g. "/guides/ad-budget-pacing,/glossary/roas"); both languages follow.
    'articles_released' => array_values(array_filter(array_map('trim', explode(',', (string) env('SITE_ARTICLES_RELEASED', ''))))),

    // Each tool's view is resources/views/public/tools/<slug>.blade.php. URLs per Content's map (ADSR-042 §13) where set;
    // the three ad tools (D-90) wait for Content's OK on their slugs.
    'tools' => [
        'serp-preview', 'meta-length-checker', 'arabic-keyword-generator', 'arabizi-converter',
        'roas-calculator', 'budget-pacing-calculator', 'utm-builder', 'hreflang-checker', 'page-audit',
        'ad-pixel-checker', 'cod-poas-calculator', 'whatsapp-link-generator', 'ad-mockup-preview',
        'llms-txt-generator', 'schema-generator', 'ab-significance-calculator', 'safe-zone-checker',
        'gulf-ad-disclosure-checklist', 'cpm-cpc-cpa-calculator', 'agency-fee-vat-calculator', 'robots-sitemap-checker',
    ],

    // The few tools shown in the header menu (the rest are on /tools). Order = menu order.
    'menu' => ['page-audit', 'arabic-keyword-generator', 'roas-calculator', 'cod-poas-calculator'],

    // Built but held back (D-249): they answer at their URLs with noindex and stay out of the menu, hub and sitemap even
    // while `public` is on, until Rami publishes each one (remove it from this list).
    'held' => [
        // all 12 Wave A.2 tools published by Rami, 2026-11-12 (D-250)
    ],

    // Tools that belong to a held-back module (FR-REACH R-3): slug => module. They answer only while that module is on
    // (404 otherwise), always send noindex, and are never in `tools`, so never in the sitemap, the menus, the /tools hub,
    // the share cards or /llms.txt. Their routes live with the module (routes/web.php, ReachEnabled group).
    'module_gated' => [
        'ai-crawler-revenue-checker' => 'reach',
    ],

    // Our own hosts, pinned to the server's public IP for SafeFetcher: the VPS resolves adsranger.com to ::1 in its hosts
    // file, which the SSRF guard (rightly) refuses. Only these exact hosts are mapped; every other host goes through DNS.
    'own_hosts' => [
        'adsranger.com' => env('SEO_OWN_HOST_IP', '72.62.132.138'),
        'www.adsranger.com' => env('SEO_OWN_HOST_IP', '72.62.132.138'),
    ],
];
