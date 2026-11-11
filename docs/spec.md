<!-- Read-only mirror of the AdsRanger build spec (D-23). Source of truth: https://claude.ai/artifact/Wp4VRMVMzdif9WERPAgYc8
     Exported 2026-11-05 (doc rev 54). Do not edit here; refresh from the source when the spec changes.
     Per-feature tier + status: ADSR-060 (overrides this copy for #4, #5, #83, #84 per D-32). -->

# AdsRanger: Build Spec

Nov 3, 2026 · @Rami

## Overview

A multi-tenant platform to create, manage and report ads on every network, plus organic tools, for small-to-mid MENA agencies, owned and built by Rami. All 157 planned features are built before one public launch, on Laravel. Build starts now.

- **Who it's for:** agencies of 5–30 people running Meta and Google Ads for clinics, charities, e-commerce and services in Jordan, KSA, UAE and the Gulf.
- **The problem:** generic dashboards count the wrong result, report only in English, and catch wasted spend days late.
- **What it does:** shows each campaign's result by its real goal (messages, calls, leads, donations, sales), sends daily alerts on broken tracking and waste, scores client health against targets, and gives clients Arabic/English reports through signed links.
- **Launch goal:** one public launch with every feature complete and tested; one build, tested as a whole before release.

## Name and legal entity

Name decided: AdsRanger (Arabic: أدز رينجر), on adsranger.com (registered 3 Nov 2026 at Namecheap; staging runs there). AdsFalcon was dropped because AdFalcon, an ArabyAds network, operates in the same market; adsfalcon.com now redirects to adsranger.com. The legal entity is still open. Both block the Meta and Google API applications, which need a live domain, a privacy policy and a verified business.

**Name shortlist** (bilingual; domains not yet checked):

| Name | Arabic | Meaning | Angle |
| --- | --- | --- | --- |
| Natija | نتيجة | result | The core idea: every campaign judged by its real result |
| Nabd | نبض | pulse | Daily desk and alerts, the account's heartbeat |
| Basira | بصيرة | insight, foresight | Health score, forecast, insights tiers |
| Raseed | رصيد | balance, record | Spend, pacing and reconciliation |
| Daleel | دليل | guide, evidence | Reports that prove results to clients |

**Legal entity options:**

| Option | Speed | Cost | Meta verification | Card billing (Stripe/Paddle) |
| --- | --- | --- | --- | --- |
| Jordan company (LLC) | Slow | Medium | Strong | Paddle works; Stripe not available in Jordan |
| Jordan registered freelancer / sole trader | Fast | Low | Possible; needs official registration documents | Paddle works |
| UAE free-zone company | Medium | Higher | Strong | Stripe available |

Start with Paddle as merchant of record, which avoids needing Stripe at launch. Confirm the Stripe and Paddle country rules before choosing; they change.

- [ ] Pick a name and check .com / .io / .app availability
- [ ] Choose the entity and start registration
- [ ] Put up a one-page site with a privacy policy and terms on the domain

## Stack and infrastructure

The product is a Laravel modular monolith on PostgreSQL and Redis, hosted on Rami's VPS behind Cloudflare.

| Layer | Choice | Notes |
| --- | --- | --- |
| Framework | Laravel 13 (PHP 8.4) | Scheduler, queues, signed URLs, policies built in |
| Modules | nwidart/laravel-modules | One folder per module; see Module system |
| Database | PostgreSQL 13 | Single database, `tenant_id` on every tenant row |
| Queue + cache | Database queue + cache (no Redis on the shared VPS) | One job per ad account per pull; no time caps |
| Admin screens | Filament 5 (/admin) | Users, clients, settings, audit, billing |
| App screens | Filament 5 panel /app/{agency} (Livewire) | RTL + LTR layouts |
| Charts | Chart.js (EX-44) | Light and dark themes, Arabic numerals optional |
| Billing | Paddle (Laravel Cashier Paddle) | Merchant of record; Stripe later if the entity allows |
| Email | Server mail now; Brevo for transactional mail (D-22) | Digests, alerts, invites |
| PDF | Browsershot (headless Chrome) | Monthly report, EN/AR |
| Hosting | VPS + Cloudflare, Laravel Forge or Ploi | Managed Postgres once paying customers arrive |
| Monitoring | Sentry + Laravel Pulse | Errors, slow jobs, queue health |
| Secrets | Laravel encrypted casts | Per-tenant OAuth tokens never stored in plain text |

Open: confirm the VPS has headroom next to the existing projects on it, or use a separate small server for the SaaS.

## Tenancy and data model

One database is shared by all agencies. Every tenant table carries `tenant_id`, enforced by a global scope plus a test per model. The hierarchy is Agency → Users → Clients → Ad accounts → Campaigns → Ad sets → Ads.

| Table | Key columns | Purpose |
| --- | --- | --- |
| `tenants` | name, slug, plan, currency, locale, timezone | One row per agency |
| `users` | tenant\_id, email, role, locale, email\_prefs | Admin, Manager, Analyst, Client viewer |
| `client_user` | user\_id, client\_id | Which clients a user can see |
| `clients` | tenant\_id, name, vertical, market, currency, monthly\_budget, target\_cpr, target\_results, target\_roas, hide\_spend | Targets live here |
| `connections` | tenant\_id, provider, external\_business\_id, token (encrypted), expires\_at, status | One OAuth link per provider per agency |
| `ad_accounts` | tenant\_id, client\_id, connection\_id, provider, external\_id, currency, status | Meta and Google accounts mapped to clients |
| `campaigns` / `ad_sets` / `ads` | tenant\_id, ad\_account\_id, external\_id, name, objective, optimization\_goal, status, daily\_budget, end\_time | Structure, refreshed daily |
| `daily_metrics` | tenant\_id, level, entity\_id, date, spend, impressions, reach, clicks, results, result\_type, calls, messages, leads, purchases, value | One row per entity per day; partitioned by month |
| `fx_rates` | date, base, quote, rate | Spend shown in client currency |
| `alerts` | tenant\_id, client\_id, rule\_key, severity, entity, payload, status, resolved\_at | Alert inbox |
| `alert_rules` | tenant\_id, rule\_key, enabled, thresholds | Per-agency overrides of rule defaults |
| `health_scores` | tenant\_id, client\_id, date, score, components | Daily score history |
| `client_links` | tenant\_id, client\_id, token\_hash, expires\_at, revoked\_at | Signed read-only links |
| `reports` | tenant\_id, client\_id, period, locale, pdf\_path, sent\_at | Monthly PDFs |
| `sync_runs` | tenant\_id, ad\_account\_id, started\_at, finished\_at, rows, error | Pull log and data-freshness badge |
| `audit_log` | tenant\_id, user\_id, action, subject, before, after | Every change |
| `modules` / `plan_modules` / `tenant_modules` | module\_key, plan, enabled | Module gating (see Module system) |
| `releases` | version, date, title\_en, title\_ar, items | In-app What's new |

Retention: daily metrics kept 25 months (13 months shown plus a year-over-year comparison). Raw API payloads are kept 30 days for debugging.

## Connectors and API approvals

Each agency connects its own accounts through OAuth; the SaaS never holds a shared token. Three approvals gate a public launch, and each can take weeks, so they are submitted first.

| Provider | API | Scopes / access | Approval needed | Pull cadence |
| --- | --- | --- | --- | --- |
| Meta | Marketing API (Graph) | `ads_read`, `business_management`; `ads_management` only if pausing is offered | Business Verification + App Review for Advanced Access | Daily 05:00 tenant time + today on demand (10-min cache) |
| Google Ads | Google Ads API | `adwords` OAuth scope + developer token | Developer token Basic Access + Google OAuth app verification | Daily 05:00 |
| GA4 (v1.1) | Analytics Data API | `analytics.readonly` | Same Google OAuth app verification | Daily |
| TikTok / Snap (later) | Marketing APIs | Read-only reporting | Partner app approval | Daily |

**Pull design:** the Scheduler queues one job per ad account. Each job pulls yesterday plus a 3-day look-back (late conversions), upserts `daily_metrics`, logs a `sync_runs` row, and refreshes the token when it is near expiry. An expired or revoked connection raises an alert to the agency Admin.

**Approval checklist:**

- [ ] Meta developer app created under the SaaS business
- [ ] Meta Business Verification submitted (needs the legal entity documents)
- [ ] Meta App Review: screencast of the connect flow and reports, privacy policy URL, data deletion URL
- [ ] Google Cloud project + OAuth consent screen on the SaaS domain
- [ ] Google Ads developer token applied (test access first, then Basic Access)
- [ ] Google OAuth verification for the `adwords` and `analytics.readonly` scopes

Until approval, development runs on Rami's own ad accounts in Meta development mode and with a Google Ads test-access token.

### API applications to submit now (Stage 1)

The slow approvals are Meta App Review and LinkedIn Standard tier. Both need a working app and a demo, so they go in as soon as Wave 1 has a login and a connect screen. Checked Nov 4, 2026.

**Shared prerequisites (do these first):**

- [x] Privacy policy and terms pages live on adsranger.com
- [ ] Business email on adsranger.com (e.g. dev@ / support@)
- [ ] Legal business documents for verification (Meta Business Verification, Google brand verification). Open: which entity they will be in
- [x] OAuth callback URLs on https://adsranger.com/app/connect/\*
- [ ] A demo ad account on each network for review videos

| # | Network | Where to apply | What it needs | Review time |
| --- | --- | --- | --- | --- |
| 1 | Meta | App Dashboard → App Review | Business app + Business Verification; Advanced access for ads\_read and ads\_management (needed to manage other people's accounts); full Marketing API tier needs 500+ calls in 15 days at under 15% errors | Days to weeks |
| 2 | Google Ads | Google Cloud Console → Google Ads API overview | Developer tokens were retired on 10 Sep 2026; access now sits on a dedicated Cloud project. Brand verification, then Basic access (automated, minutes); Standard access for higher limits | Minutes (Basic) |
| 3 | TikTok | TikTok API for Business → Become a Developer | Developer account, company name and website, app name, description, redirect URL | 2–3 business days |
| 4 | Snapchat | Business Manager → Business Details → OAuth Apps | Organization Admin role, app name, redirect URI; no review gate reported | Instant |
| 5 | Google Merchant Center | Same Cloud project as Google Ads | Merchant API enabled on the project | Instant |
| 6 | Microsoft Ads | developers.ads.microsoft.com + Microsoft Entra app | Developer token (third-party tools can wait up to 5 business days), Entra app registration; build on REST, SOAP v13 ends 31 Jan 2027 | Up to 5 days |
| 7 | LinkedIn | LinkedIn Developer Portal, then a support ticket for Standard tier | Starts on Development tier (5 ad accounts); Standard tier is at LinkedIn's discretion; meet the integration requirements first | Weeks |
| 8 | X | console.x.com → Ads API access form | Developer app, then the form choosing Standard Access (analytics, campaigns, creatives, audiences) | Not published |

Sources: [Google change](https://ppc.land/google-drops-developer-tokens-from-ads-api-access-decisions/) · [Meta access](https://developers.secure.facebook.com/docs/marketing-api/access) · [TikTok guide](https://docs.treasure.ai/int/guides-for-creating-tiktok-marketing-api-developer-account-and-application) · [Snapchat guide](https://unified.to/blog/how_to_get_your_snapchat_ads_api_key) · [Microsoft guide](https://unified.to/blog/how_to_get_your_microsoft_advertising_api_key) · [LinkedIn tiers](https://learn.microsoft.com/linkedin/marketing/increasing-access) · [X Ads API](https://docs.x.com/x-ads-api/getting-started/step-by-step-guide)

## MVP modules

These 5 core modules are built first, because every other feature depends on them. Goal-based results is the core differentiator.

### 1. Connectors

- OAuth connect for Meta Business and Google Ads (MCC or single account).
- Account picker: map each ad account to a client, set its currency, pause sync.
- Connection health: last sync, rows pulled, token expiry, errors.
- Backfill 13 months on first connect, as a queued job with progress shown.

### 2. Goal-based results

1. A campaign's result follows its ad set optimisation goal; if none, the campaign objective.
2. Video goals count ThruPlays.
3. A conversions goal with 0 results but purchases > 0 counts purchases (donations, sales).
4. Calls are split into placed, confirmed and 20 s+ where the platform reports them.
5. Messages, leads, calls, purchases and value are stored separately, so a client can mix goals.
6. ROAS counts only spend on purchase or conversion goals.
7. Google: results = primary conversion actions, with per-action breakdown; count-only actions carry no value.
8. Costs under $1 show 3 decimals.

### 3. Daily desk + alerts

- **Daily desk:** yesterday, today (live, cached 10 min) or any date, per client in its own currency, with every goal type. Compare two clients over 7/14/30/90 days.
- **Alert rules** (defaults; each agency can change thresholds or switch a rule off):

| Rule | Fires when | Severity |
| --- | --- | --- |
| Tracking break | 0 results yesterday after a normal 7 days | High |
| Spend, no results | Spend ≥ 60 (client currency) over 3 days with 0 results | High |
| Tracking issue | Conversion goal, ≥ 50 spend in 7 days, 0 results | High |
| Under-delivery | Spend < 30% of daily budget | Medium |
| CPR rising | Cost per result up ≥ X% vs the prior 7 days (X set per agency) | Medium |
| Ending soon | Ad set ends within 3 days | Low |
| Connection problem | Token expired, revoked, or sync failed twice | High |

- Delivery: in-app inbox for everyone, email opt-in per user (default off), morning digest at 08:00 tenant time.

### 4. Health score + targets

- Targets per client: monthly budget, target CPR, target results, target ROAS, market, vertical.
- Health score 0–100, daily, stored in `health_scores`. Proposed components (weights to confirm):
  - result volume vs target (30)
  - CPR vs target (30)
  - budget pacing (20)
  - open high alerts (20)
- Without targets, the score falls back to the client's own 30-day baseline and shows a "Set targets" prompt.

### 5. Client links + monthly report

- Signed read-only links per client, 1–90 days, revocable, logged when opened.
- The client page shows results by goal, spend (unless hidden), a 6-month trend and the top ads with thumbnails.
- Monthly PDF in Arabic or English with a plain-language summary, sent on the 1st to opted-in recipients.
- Agency logo and brand colour on the link and the PDF.

## Module system and plan tiers

Each module is a self-contained folder under `Modules/` that registers itself with the core. Plans switch modules on or off without code changes.

**What a module contains:** `module.json` (key, EN/AR name, tier, dependencies), a service provider, migrations with `tenant_id`, jobs, event listeners, screens, Filament settings, EN/AR strings, permissions and tests (including tenant isolation).

**How it plugs in:** the core exposes registries for navigation, dashboard widgets, alert rules and report sections, and fires events (`MetricsPulled`, `ClientCreated`, `ConnectionExpired`). Modules listen; the core never calls a module directly.

**Gating:** `modules` lists what exists, `plan_modules` maps tiers to modules, `tenant_modules` holds per-agency trials and add-ons. Middleware blocks routes of disabled modules, and locked modules show an upgrade card.

**Adding a module:** `module:make` → fill `module.json` → migrations → register in the provider → permissions → EN/AR strings → tests → release entry → add to a plan → deploy.

| Tier | Ad accounts | Modules | Price (USD/month) |
| --- | --- | --- | --- |
| Starter | up to 10 | The 5 MVP modules | To decide |
| Pro | up to 30 | + Insights (forecast, budget planner, benchmarks, seasonality), client portal, GA4, Google Chat/Slack alerts | To decide |
| Agency | Unlimited | + Creative Studio, Finance, Influencers, AI drafts, TikTok/Snap, white-label | To decide |

Later add-on: the real-outcomes loop (booked, showed up, paid) with Conversions API offline events.

## Full feature list

The product plan holds 157 features, all built before launch: 44 in Starter, 78 more in Pro and 35 more in Agency. The 5 core modules, built first, are marked ★. Set Priority in the last column, and change a Tier to move a feature between plans.

### Connect & data (10)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 1 | Meta connector ★ | Starter |  |
| 2 | Google Ads connector ★ | Starter |  |
| 3 | GA4 | Pro |  |
| 4 | TikTok Ads | Agency |  |
| 5 | Snapchat Ads | Agency |  |
| 6 | Google Search Console | Pro |  |
| 7 | 13-month backfill on connect | Starter |  |
| 8 | Multi-currency + FX history | Starter |  |
| 9 | Spend reconciliation vs platform | Pro |  |
| 10 | Sync health + data-updated badge | Starter |  |

### Results & outcomes (10)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 11 | Goal-based results ★ | Starter |  |
| 12 | Calls split: placed / confirmed / 20s+ | Starter |  |
| 13 | Google conversions by action | Starter |  |
| 14 | Attribution windows (1d / 7d / 28d / view) | Pro |  |
| 15 | Over-claim ratio, platform vs GA4 | Pro |  |
| 16 | Full funnel, impressions to paid | Pro |  |
| 17 | Lead outcomes form: cost per booked / paid, real ROAS | Pro |  |
| 18 | Offline outcomes to Conversions API + CRM (HubSpot, Zoho, Odoo, Sheets) | Agency |  |
| 19 | WhatsApp outcomes tagging | Agency |  |
| 20 | E-commerce revenue: Shopify, WooCommerce, Salla, Zid | Agency |  |

### Daily operations (14)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 21 | Daily desk ★ | Starter |  |
| 22 | Compare two clients | Starter |  |
| 23 | Alerts engine ★ (7 rules in Starter, about 20 in Pro) | Starter |  |
| 24 | Alerts inbox: assign, snooze, resolve | Starter |  |
| 25 | Morning digest | Starter |  |
| 26 | Alerts on WhatsApp / Telegram / Google Chat / Slack | Pro |  |
| 27 | Tasks + action tracker | Pro |  |
| 28 | Change log + before/after impact | Pro |  |
| 29 | "Why did it change?" explainer | Pro |  |
| 30 | Change approvals: propose, approve, apply in Meta | Pro |  |
| 31 | Pause list + tired ads to refresh | Pro |  |
| 32 | Learning-phase risk | Pro |  |
| 33 | Naming + setup checks | Pro |  |
| 34 | Rejected ads + spend-cap alerts | Pro |  |

### Planning & insights (16)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 35 | Health score + targets ★ | Starter |  |
| 36 | Budget pacing | Starter |  |
| 37 | Month-end forecast | Pro |  |
| 38 | Budget planner (spend-response curve) | Pro |  |
| 39 | Budget reallocation suggestions | Pro |  |
| 40 | Hijri / MENA calendar + seasonality | Starter |  |
| 41 | Internal benchmarks | Pro |  |
| 42 | MENA benchmarks network (across agencies) | Pro |  |
| 43 | Best and weakest hours / weekdays heatmap | Pro |  |
| 44 | Audience + placement breakdown | Pro |  |
| 45 | Annual budget plan vs actual | Agency |  |
| 46 | Google search terms + negative keywords | Pro |  |
| 47 | Impression share + Quality Score | Pro |  |
| 48 | Performance Max asset groups | Pro |  |
| 49 | Landing page health (speed, broken links) | Pro |  |
| 50 | Competitor watch (Meta Ad Library) | Pro |  |

### Creative (5)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 51 | Top ads with thumbnails | Starter |  |
| 52 | Creative fatigue detection | Pro |  |
| 53 | Creative intelligence (AI tags: hook, format, offer) | Pro |  |
| 54 | Creative Studio: briefs, versions, client approval links | Agency |  |
| 55 | Test tracker (Meta experiments) | Agency |  |

### Clients (8)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 56 | Signed client links ★ | Starter |  |
| 57 | Monthly PDF report in EN/AR ★ | Starter |  |
| 58 | Plain-language AI summary | Pro |  |
| 59 | Client portal with goals + milestones | Pro |  |
| 60 | White-label + custom domain | Agency |  |
| 61 | Client satisfaction pulse | Pro |  |
| 62 | Prospect audit link | Pro |  |
| 63 | Pitch forecast builder | Pro |  |

### Agency management (5)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 64 | Portfolio view + risk board | Pro |  |
| 65 | Manager scorecard + workload | Agency |  |
| 66 | Agency economics: fees, margin, VAT invoices | Agency |  |
| 67 | Influencers module | Agency |  |
| 68 | Checklists: launch, monthly review | Pro |  |

### AI (2)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 69 | Ask the platform (Arabic / English) | Agency |  |
| 70 | AI drafts with human approval | Agency |  |

### Platform & admin (9)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 71 | Agencies, roles, client access, View as | Starter |  |
| 72 | Onboarding wizard | Starter |  |
| 73 | Audit log | Starter |  |
| 74 | Google SSO + 2FA | Starter |  |
| 75 | Plans + billing | Starter |  |
| 76 | What's new page | Starter |  |
| 77 | Public API + Looker Studio / Sheets connector | Agency |  |
| 78 | Phone app (PWA) + push | Pro |  |
| 79 | Data export + deletion (needed for Meta App Review) | Starter |  |

### Added features (80–115)

The 36 features below were added on 3 Nov 2026. All are new ideas, not built anywhere yet.

#### Connect & data (7)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 80 | Meta lead forms inbox: live leads forwarded to the client by WhatsApp/email | Pro |  |
| 81 | Google Business Profile insights (calls, directions, reviews) | Pro |  |
| 82 | Facebook / Instagram organic page insights | Pro |  |
| 83 | Microsoft (Bing) Ads | Agency |  |
| 84 | LinkedIn Ads | Agency |  |
| 85 | Call tracking numbers (via a call provider) | Agency |  |
| 86 | UTM builder + link checker | Starter |  |

#### Results & outcomes (4)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 87 | Lead quality score (fields, duplicates, fake numbers) | Pro |  |
| 88 | Reverse-funnel goal calculator | Starter |  |
| 89 | Customer LTV + cohorts (e-commerce) | Agency |  |
| 90 | Lift / holdout test helper | Agency |  |

#### Daily operations (5)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 91 | Account health monitor: disabled accounts, failed payments, restrictions | Starter |  |
| 92 | Ad rejection explainer + fix suggestions | Pro |  |
| 93 | Custom alert-rule builder | Pro |  |
| 94 | Bulk edits across accounts (with approvals) | Agency |  |
| 95 | Business Manager access audit | Pro |  |

#### Planning & insights (4)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 96 | Ramadan / Eid scenario planner | Pro |  |
| 97 | City-level geo heatmap | Pro |  |
| 98 | Audience overlap + saturation | Pro |  |
| 99 | Weekly AI account review (top 3 actions per client) | Pro |  |

#### Creative (5)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 100 | Shareable ad preview gallery for clients | Starter |  |
| 101 | Arabic copy checker (dialect fit, RTL breaks, mixed numerals) | Pro |  |
| 102 | Video retention curves | Pro |  |
| 103 | Competitor swipe file | Pro |  |
| 104 | Creative brief generator from winning ads | Pro |  |

#### Clients (5)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 105 | Client onboarding link: requests Meta / Google / GA4 access in one flow | Pro |  |
| 106 | Scheduled reports by email and WhatsApp | Starter |  |
| 107 | Shared notes visible to the client | Pro |  |
| 108 | Client approvals for budget increases | Agency |  |
| 109 | Reports in French + other languages | Pro |  |

#### Agency management (4)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 110 | Churn risk score per client | Pro |  |
| 111 | Upsell finder | Pro |  |
| 112 | Time tracking + capacity per team member | Agency |  |
| 113 | Contract + renewal tracker | Agency |  |

#### Platform & admin (2)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 114 | Demo agency with sample data | Starter |  |
| 115 | Webhooks + Zapier / Make / n8n | Pro |  |

### Scope expansion: every network, ads creation, organic (116–157)

Added 2026-11-04: 42 features that make AdsRanger a platform to create, manage and report ads on every network, plus organic tools. Networks without a public API connect through report import (CSV, Sheets, email reports) until an API is approved.

#### Every ad network (13)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 116 | Integration tiers: full API, report import, manual + universal CSV/Sheets importer | Starter |  |
| 117 | Search: Apple Search Ads, Yandex Direct, Naver, Baidu (Microsoft Ads is #83) | Agency |  |
| 118 | Social: X Ads, Pinterest Ads, Reddit Ads, Telegram Ads, YouTube reported on its own (LinkedIn is #84) | Pro |  |
| 119 | Native (Western): Taboola, Outbrain, MGID, Revcontent, Yahoo Native | Pro |  |
| 120 | Native + publishers (Arabic/MENA): ArabyAds, Anghami Ads, Arabic publisher networks via import | Agency |  |
| 121 | Retail media: Amazon Ads, Noon Ads, Talabat Ads, Careem Ads, Google Merchant Center / Shopping | Agency |  |
| 122 | DSPs / programmatic: DV360, The Trade Desk, Amazon DSP, StackAdapt, Yahoo DSP | Agency |  |
| 123 | PPV / pop / push: PropellerAds, Adsterra, RichAds, Zeropark, PopAds, Clickadu, Evadav, HilltopAds | Pro |  |
| 124 | Mobile app networks: AppLovin, Unity Ads, ironSource, Mintegral, Moloco + AppsFlyer / Adjust attribution | Agency |  |
| 125 | Video / CTV / audio: Spotify Ads, Anghami audio, Shahid (MBC), OSN+, CTV through DSPs (streaming via report import) | Agency |  |
| 126 | Affiliate networks: Admitad, ArabClicks, Impact, CJ | Agency |  |
| 127 | Network catalog + request-a-network votes | Starter |  |
| 128 | Cross-network normalised metrics: one schema, one currency, one result definition | Starter |  |

#### Network build order

Connectors ship in four stages, ranked by how much MENA agency spend runs through each network and how easy its API is to get. Import-first networks work from day one through CSV, Sheets or emailed reports. API access types are from general knowledge and must be confirmed when we apply.

**Stage 1: core (Wave 1–2)**

| Rank | Network | Category | API access |
| --- | --- | --- | --- |
| 1 | Meta (Facebook, Instagram) | Social | Open, needs App Review |
| 2 | Google Ads (Search, PMax, YouTube) | Search / video | Open, needs developer token |
| 3 | TikTok Ads | Social | Open, needs app approval |
| 4 | Snapchat Ads | Social | Open, needs app approval |
| 5 | Google Merchant Center / Shopping | Retail | Open |
| 6 | Microsoft (Bing) Ads | Search | Open, needs developer token |
| 7 | LinkedIn Ads | Social | Partner application |
| 8 | X Ads | Social | Application required |

**Stage 2: native + retail + Apple (Wave 3)**

| Rank | Network | Category | API access |
| --- | --- | --- | --- |
| 9 | Taboola | Native | Open to advertisers |
| 10 | Outbrain | Native | On request |
| 11 | Amazon Ads | Retail | Registration approval |
| 12 | Apple Search Ads | Search | Open with account |
| 13 | Pinterest Ads | Social | Open, needs app approval |
| 14 | Reddit Ads | Social | Limited access |
| 15 | MGID | Native | Open |
| 16 | Revcontent | Native | Open |
| 17 | Yahoo Native + Yahoo DSP | Native / DSP | Partner |

**Stage 3: PPV / pop / push + search extras (Wave 4)**

| Rank | Network | Category | API access |
| --- | --- | --- | --- |
| 18 | PropellerAds | Push / pop | Open |
| 19 | Adsterra | Push / pop | Open |
| 20 | Zeropark | Pop / redirect | Open |
| 21 | RichAds | Push / pop | Open |
| 22 | PopAds | Pop | Open |
| 23 | Clickadu | Pop / push | Open |
| 24 | Evadav | Push | To confirm |
| 25 | HilltopAds | Pop / push | Open |
| 26 | Yandex Direct | Search | Application required |
| 27 | Naver Search Ads | Search | Open |
| 28 | Baidu | Search | Partner, needs local entity |

**Stage 4: DSPs, mobile, audio, affiliate (Wave 6)**

| Rank | Network | Category | API access |
| --- | --- | --- | --- |
| 29 | Google DV360 | DSP | Access request |
| 30 | The Trade Desk | DSP | Partner |
| 31 | Amazon DSP | DSP | Through Amazon Ads API |
| 32 | StackAdapt | DSP | Open |
| 33 | AppsFlyer | Attribution | Open (paid plan) |
| 34 | Adjust | Attribution | Open |
| 35 | AppLovin | Mobile | Open reporting |
| 36 | Unity Ads + ironSource | Mobile | Open reporting |
| 37 | Mintegral | Mobile | Open reporting |
| 38 | Moloco | Mobile | Partner |
| 39 | Spotify Ads | Audio | Open |
| 40 | Admitad | Affiliate | Open |
| 41 | Impact | Affiliate | Open |
| 42 | CJ | Affiliate | Open |

**Import first (from day one, API later if offered):** Noon Ads, Talabat Ads, Careem Ads, ArabyAds, Anghami Ads, Shahid (MBC), OSN+, Telegram Ads, ArabClicks and Arabic news publishers. Each gets a saved import template that maps the report onto AdsRanger's common metrics.

#### Network access rules (decided 2026-11-04)

The super admin controls each network on four layers in /admin. An agency user sees a network only when every layer allows it; an agency override can also switch a network on for a beta agency while it is off globally.

| Layer | Control | Example |
| --- | --- | --- |
| 1. Global | On/off for the whole platform (built) | Network off: hidden for everyone |
| 2. Plan | Which plans include the network | TikTok only on Pro and Agency |
| 3. Agency | Per-agency on/off override | LinkedIn on for 2 beta agencies |
| 4. User | Limit a network to chosen users in an agency | Only the media buyer can connect X |

Layers 2–4 are built in Wave 1 with plans, agency workspaces and roles.

#### Ads creation: AdsRanger Studio (16)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 129 | Campaign builder: create campaigns, ad sets and ads on Meta, Google, TikTok and Snap | Pro |  |
| 130 | Cross-network launch: one brief becomes network-ready variants | Agency |  |
| 131 | AI ad copy in Arabic dialects (Gulf, Levantine, Egyptian) + English / French | Starter |  |
| 132 | AI image generation + auto-resize to every placement | Pro |  |
| 133 | Video ad maker: templates, Arabic captions, auto-cut to 9:16 / 1:1 / 16:9 | Pro |  |
| 134 | Brand kit: logos, colours, fonts, tone, banned words | Starter |  |
| 135 | Asset library with tags + where each asset runs | Starter |  |
| 136 | Placement previews: feed, stories, reels, search, native, push | Starter |  |
| 137 | Search ad builder: keywords, RSA headlines, assets | Pro |  |
| 138 | Audience builder + sync across networks (custom, lookalike, retargeting) | Pro |  |
| 139 | Variant generator + A/B test setup | Pro |  |
| 140 | Landing page builder (AR/EN, RTL, forms into the leads inbox) | Pro |  |
| 141 | Product feed manager for Meta, Google, TikTok and Snap catalogs | Agency |  |
| 142 | Draft, approve, schedule and publish flow | Pro |  |
| 143 | Policy pre-check before publish (Meta / Google rules, health and finance claims) | Pro |  |
| 144 | Native, push and pop creative formats (titles, icons, thumbnails) | Pro |  |

#### Organic tools (13)

| # | Feature | Tier | Priority |
| --- | --- | --- | --- |
| 145 | Post scheduler: Facebook, Instagram, TikTok, X, LinkedIn, Snapchat, YouTube, Google Business, Telegram | Starter |  |
| 146 | Content calendar with Hijri dates + client approvals | Starter |  |
| 147 | Unified inbox: comments, DMs, WhatsApp, reviews | Pro |  |
| 148 | Organic analytics across every social profile | Starter |  |
| 149 | AI caption + hashtag writer (AR/EN) | Starter |  |
| 150 | Best time to post, per profile | Pro |  |
| 151 | Trend + hashtag finder by country | Pro |  |
| 152 | Competitor organic tracking | Pro |  |
| 153 | Boost a post: turn a top organic post into an ad | Pro |  |
| 154 | Review management: Google + Facebook reviews, AI reply drafts | Pro |  |
| 155 | SEO tools: rank tracking, site audit, keyword research | Agency |  |
| 156 | Link-in-bio page with UTM tracking | Starter |  |
| 157 | Social listening: brand mentions in Arabic and English | Agency |  |

New totals: 157 features, 44 in Starter, 78 in Pro, 35 in Agency.

## Screens

V1 has 9 screens for agency users and 1 for clients, all in Arabic (RTL) and English, usable on a phone.

| Screen | Who | Shows |
| --- | --- | --- |
| Onboarding wizard | Agency Admin | Create agency → connect Meta/Google → map accounts to clients → set targets → invite team |
| Overview | All agency users | Every client: health score, spend vs budget, results vs target, open alerts, data-updated badge |
| Client page | Assigned users | Results by goal, trend, campaigns → ad sets → ads drill-down, top ads with thumbnails |
| Daily desk | All agency users | Yesterday / today / any date per client; Compare two clients |
| Alerts inbox | All agency users | Open alerts by severity, assign, snooze, resolve |
| Clients & targets | Admin, Manager | Client list, targets, currency, hide spend, assigned users |
| Connections | Admin | Connected businesses, accounts, sync status, reconnect |
| Team | Admin | Users, roles, client access, invites |
| Settings & billing | Admin | Agency profile, logo, alert thresholds, plan, invoices |
| Client link view | Client (no login) | Read-only client page + monthly report download |

Shared: a What's new panel in the header, fed by the `releases` table.

## Milestones and open decisions

Everything is built before one public launch, now estimated for September 2027 (re-planned on 4 Nov 2026 from June 2027, after the scope grew from 115 to 157 features). The extra three months cover Studio (2 months), Organic and the Stage 2–4 networks. It is one build: the stages below are only the order of work, because later features depend on earlier ones. Nothing is released before launch, and the whole build is tested together in the final stage.

&#91;embedded content: Build plan · 8 waves, one launch in Sep 2027 (estimate)\]

Estimates assume Rami building solo with Claude Code, full time after 12 Nov. Plan switches exist only to gate features by tier. All API applications go in during Wave 0 with the full permission list, because approvals are the slowest part.

**Open decisions:**

- [x] Product name and domain
- [ ] Legal entity: decided 2026-11-04, a Jordan company (LLC). Register it; it is needed for Meta Business Verification and Google brand verification
- [ ] Pricing per tier (USD/month)
- [x] Health score weights (35% Pacing, 35% Active Alerts & Rejected Ads, 30% Target CPR/ROAS; 50/50 re-weight when no target is set)
- [x] Default CPR-rising threshold (>20% Warning, >40% Critical over 7 days, gated by >= 50 USD-eq or >= 3x Target CPR spend floor)
- [x] Livewire or Inertia + Vue for app screens (Filament 5 + Livewire)
- [ ] Same VPS or a separate server (decide later; build first)
- [ ] Beta agencies: who to invite
