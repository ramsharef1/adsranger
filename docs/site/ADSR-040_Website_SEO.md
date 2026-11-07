# ADSR-040 Website & SEO (public adsranger.com)

Owner: master (engine) · Updated 2026-11-07 23:22 (site sections + URL decisions D-150, D-176, D-192, D-202, D-204, D-209…D-212) · Renumbered from ADSR-030_Website_SEO (old copy in `claude/archive/`).
Full living plan: https://claude.ai/artifact/GxpwwLCxgK7WKquupRzbfB (goals, URL rules, site tree, 14 keyword clusters, content hub, linking, technical SEO, build order).

## Decided (Rami, 2026-11-04 — see ADSR-002)
- Index the marketing site early (from Nov 2026). /app, /admin, /invite, /login, /register, /password, /connect stay noindex + disallowed.
- Deliverable: sitemap + SEO doc. ADSR-030 Experience Rules apply to the public site (except [panels] rules).
- D-05 Latin slugs in both languages; Arabic at root, English at /en/; hreflang ar/en; self canonicals.
- D-06 x-default → Arabic root `/`. (The living plan said /en/; this decision overrides it.)
- D-07 Waitlist as the pre-launch CTA (email + agency name + country), live after Brevo (D-22). No prices, ratings or testimonials before launch.
- D-08 Compare pages naming competitors, factual tables only, from Apr 2027.
- D-09 Author page /author/rami-alsharef linking LinkedIn.
- D-10 Blog as a Filament "Posts" resource in /admin.
- D-11 GA4 now + Search Console domain property (Namecheap DNS TXT); revisit Plausible before launch.
- D-12 Phase 1 approved.

## Site sections (8)
Product (/features, /studio, /reports, /organic, /search, /ai, /pricing, /demo-report) · **Platforms (/integrations hub + /integrations/{platform}, D-210; the old /networks/ path is dropped)** · **Solutions (/solutions/agencies, /solutions/freelancers, /solutions/business-owners, D-209; /markets/{country})** · Resources (/guides, /blog, /mena-calendar, /tools, /glossary, /templates) · Seasonal pillars at the root (/ramadan-2027, D-150; hub /mena-calendar, D-176) · Compare · Pricing + waitlist · Company (/about, /contact, /security, /for-clients, /for-teams) · Legal.

### URL decisions (2026-11-07, see ADSR-002)
- **D-150** Ramadan pillar at **/ramadan-2027** (seasonal pillars at the root, `/{event}-{year}`); the blog calendar row below that says `/calendar/ramadan-2027` is superseded.
- **D-176** Seasons hub + calendar tool = one page **/mena-calendar**; `/calendar` and `/tools/mena-ad-calendar` are not built. Label «تقويم المواسم» / "Seasons calendar" (D-190).
- **D-192** Blog fallback: if Posts (D-10) isn't live by **22 Dec**, each due Ramadan spoke ships as a static page at its `/blog/<slug>` URL and moves into Posts later on the same URL (no redirect).
- **D-209** Audience pages: **/solutions/agencies**, **/solutions/freelancers**, **/solutions/business-owners** (the in-house copy is the business-owners hub; no `/in-house` URL).
- **D-210** Platform pages at **/integrations/{platform}** (Stage 1: meta, google-ads, tiktok, snapchat, microsoft-advertising, linkedin, x); **/networks/ dropped**.
- **D-204** No GA4 page; GA4 is covered in the guides and the /integrations hub line.
- **D-202** /demo-report is indexed. **D-211** /for-clients is indexed with **no target query** (its title must not chase "client ad report").
- **D-212** Phase 1 site build = P-5…P-10 + A-1…A-7; P-11…P-25 stay proposed until M-3.
- English URLs: `/en/` for the English home only (301 from `/en`, R-14); every other English URL has no trailing slash (e.g. `/en/ramadan-2027`).

## Build order
Nov 2026 Phase 1 (indexing switch, robots.txt, sitemaps with hreflang, GSC, GA4, waitlist page) → Dec product-line pages + Stage 1 platform pages (/integrations/{platform}, D-210) + **Ramadan 2027 pillar (live by mid-Dec; Ramadan starts early Feb 2027)** → Jan–Mar audiences + free tools → Apr–Jun coverage + compare → Jul–Aug pricing + help center → Sep 2027 launch.

## Before Phase 1 ships
- Confirm app routes are tenant-prefixed (/app/{slug}/calendar, /benchmarks).
- Brevo set up (D-22) so waitlist confirmations don't land in spam.
- Keyword seeds still need volume checks (Keyword Planner / GSC).
- **Search Console: verified 2026-11-05 02:12** (D-68) as URL-prefix property `https://adsranger.com/` under Rami's personal Google account (D-67), HTML file `public/google65865a41419dcc23.html` (commit `bbc107d`; never remove it). A Domain property was started first and left unverified (no TXT added). Sitemap submission waits for Phase 1 (the site is still noindex).
- Legal pages /privacy, /terms, /data-deletion (+ /en/) already exist (ADSR-003): Phase 1 re-templates them. **Flow (D-54):** Business writes the content changes (AR + EN; entity line after the LLC) → Website sends the re-template packages → engine builds.
- **If Phase 1 slips past 30 Nov (D-56):** publish the Ramadan page by 15 Dec with the minimum pieces (robots.txt, sitemap, hreflang + canonicals, GSC verified + sitemap submitted); finish the rest of Phase 1 after.
- **Keyword data (D-57):** Keyword Planner under Rami's **personal** Google account, never the Telescope work account; Rami signs in, Website runs read-only checks (SA, AE, EG, JO, KW, QA; AR + EN).
- **Author page: dropped until launch (D-55).**
- **Phase 1 adds** a `/login` → `/app/login` 301 (UX-30) and a `features` sitemap child when the Dec product pages go live (ADSR-041).

---

## Phase 1 spec (O-30) — ACCEPTED
**Amended 2026-11-05 01:28 (Rami, D-41):** Phase 1 URL set is **6 URLs** (`/`, `/en/`, `/privacy`, `/en/privacy`, `/terms`, `/en/terms`); the author page is **held** until Rami sends bio details and a photo; Bing Webmaster Tools import from GSC is **in scope**; acceptance check 9.2 covers 6 URLs; no CTA button on any page until /waitlist is live; launch wording "2027", no month. **D-36:** light theme by default with a dark toggle (respects `prefers-color-scheme`), both passing EX-54. Page packages: ADSR-041.
Approved by Rami: "approve all", 2026-11-05 01:13 (D-35). Written by the Website chat (ADSR-912, 01:12), filed here unchanged. The engine builds it 17–30 Nov, together with the public-page fixes UX-1, UX-2, UX-8, UX-10, UX-11 (ADSR-911 01:17).

### Phase 1 — adsranger.com public site
**1. URLs + languages**
- Arabic at root `/`, English at `/en/`; same Latin slug in both (`/privacy` ↔ `/en/privacy`) (D-05).
- One trailing-slash rule: `/en/` for the English home only; every other URL without a trailing slash; 301 the other variant.
- `<html lang="ar" dir="rtl">` on Arabic pages, `<html lang="en" dir="ltr">` on English pages.
- Every public page: self-referencing canonical; `hreflang` `ar` → Arabic URL, `en` → English URL, `x-default` → Arabic URL (D-06). Tags in `<head>` and the same set in the sitemap.
- Language switcher links to the exact pair page. **No automatic redirect** by IP or Accept-Language (crawlers must reach both versions).
- `og:locale` `ar_AR` / `en_US`, `og:locale:alternate` for the pair; `og:title`, `og:description`, `og:image` per page (sizes come with each page package).

**2. Indexing switch, robots.txt, noindex (feeds the tenant-route check)**
- Public routes indexable from Phase 1 go-live; everything else stays out.
- `robots.txt`:
```
User-agent: *
Disallow: /app/
Disallow: /admin/
Disallow: /invite/
Disallow: /login
Disallow: /register
Disallow: /password/
Disallow: /connect/
Disallow: /en/login
Disallow: /en/register
Disallow: /en/password/
Disallow: /waitlist/thanks
Disallow: /en/waitlist/thanks
Sitemap: https://adsranger.com/sitemap.xml
```
- Also send the `X-Robots-Tag: noindex, nofollow` header from middleware on every route group above, plus all 401/403/404/500 responses and any preview or draft URL (`?preview=`).
- Do **not** block CSS, JS, images, fonts or `/livewire/livewire.js`. Public pages need them to render for Google.
- **Engine check (queue item 4):** confirm every app route sits under `/app/{slug}/…` (incl. `/app/{slug}/calendar`, `/app/{slug}/benchmarks`) and that no app, auth or Filament route lives at a public-looking path (`/calendar`, `/benchmarks`, `/reports`, `/features`, …). Those public names are reserved for marketing pages (ADSR-040 site sections). Report any route outside the list above.

**3. Sitemaps**
- `/sitemap.xml` = sitemap index. Phase 1 child: `/sitemaps/pages.xml`. Later children are added as sections ship: features, networks, guides, blog, glossary, tools.
- Every Arabic and every English URL is its own `<url>`, each with `xhtml:link` alternates `ar`, `en`, `x-default` (→ Arabic).
- `lastmod` = the page's real last content change. No `priority` or `changefreq`.
- Only 200, canonical, indexable URLs. No redirects, noindex pages or the thanks page.
- Phase 1 URL set: `/` · `/en/` · `/privacy` · `/en/privacy` · `/terms` · `/en/terms` (D-41; the author page is held). `/waitlist` + `/en/waitlist` join when Brevo is live (D-07, D-22). **R-19 (ADSR-042):** the pages live since 7 Nov also go in `/sitemaps/pages.xml`: `/pricing`, `/integrations`, `/demo-report`, `/mena-calendar`, `/security` (+ `/en/`).
- Regenerate on publish; ping is not needed (GSC reads the index).

**4. GA4 (D-11)**
- One GA4 property "AdsRanger", one web stream for adsranger.com, on **public pages only**. No tag inside `/app` or `/admin` in Phase 1.
- Consent Mode v2: `analytics_storage` and `ad_storage` default **denied** until the visitor accepts the banner (§5); no GA cookies before consent.
- Events: `page_view` (auto) · `language_switch` (from, to) · `waitlist_view` · `generate_lead` on waitlist submit (country, language; never email or agency name) · `click_outbound` (LinkedIn on the author page).
- Settings: data retention 14 months · internal-traffic filter for Rami's IPs · Google signals off · link GA4 to GSC.
- Mark `generate_lead` as a key event.

**5. Consent banner (owner: experience UX, engine build)**
- Shown on first visit, AR/EN per page language; buttons "Accept" / "Reject" given equal weight; link to `/privacy`; choice stored 6 months; a "Cookie settings" link in the footer reopens it.
- Copy comes in the /privacy page package; experience checks it against ADSR-030.

**6. Waitlist (D-07), live only after Brevo (D-22)**
| Field | Type | Rule |
|---|---|---|
| email | email | required, validated, lowercased, unique (re-submit = friendly "already on the list") |
| agency_name | text | required, 2–120 chars |
| country | select | required; Jordan, Saudi Arabia, UAE, Kuwait, Qatar, Bahrain, Oman, Egypt, Iraq, Lebanon, Palestine, Morocco, Algeria, Tunisia, Libya, Syria, Yemen, Sudan, Other; labels in page language |
| consent | checkbox | required, unticked by default; "I agree to receive emails about AdsRanger's launch" + link to /privacy |
| language | hidden | `ar` / `en` from the page |
| source | hidden | utm_source / utm_medium / utm_campaign + referrer, if present |
| website | honeypot | hidden; any value → silently drop |
- Rate limit 5 submits per IP per hour; server-side validation; no phone number, no other fields.
- Store in a `waitlist_signups` table (fields above + created_at, confirmed_at) and sync to one Brevo list; double opt-in email from Brevo in the page language.
- After submit → `/waitlist/thanks` (noindex, disallowed). Copy for the page, form, errors and emails comes in the waitlist page package.
- Business gets read access to the list; no export outside Brevo without Rami's yes.

**7. Search Console (D-68, O-92)**
- Search Console: URL-prefix property https://adsranger.com/ under Rami's personal Google account, verified by HTML file in public/ (`bbc107d`, D-68). Keep the file in every deploy.
- When Phase 1 lifts noindex: submit /sitemap.xml, request indexing for / and /en/, then import the property into Bing Webmaster Tools (D-41). No DNS TXT.
- `/login` stays in robots.txt Disallow (it only redirects to `/app/login`, UX-30).
- Acceptance check 9.4 unchanged.

**8. Structured data**
- Home: `Organization` (name AdsRanger / أدز رينجر, url, logo; `sameAs` added once Business reserves the @adsranger handles) + `WebSite`.
- Author page: held (D-41). The Ramadan page's `Article.author` = `Person` "Rami Alsharef" with `sameAs` LinkedIn, no `url`.
- No `Product`, `Offer`, `Review` or `AggregateRating` before launch (D-07).

**9. Acceptance checks (engine runs before calling Phase 1 LIVE)**
1. `curl -I` on every Phase 1 URL → 200; on /app, /admin, /login → `X-Robots-Tag: noindex`.
2. View source: canonical + 3 hreflang tags correct on all 6 URLs; `lang`/`dir` correct.
3. `/robots.txt` and `/sitemap.xml` load; sitemap validates; contains exactly the Phase 1 set.
4. GSC URL inspection: `/` and `/en/` "URL is available to Google".
5. GA4 DebugView: no hits before consent; `page_view` after Accept; `language_switch` fires.
6. Switcher on every page goes to its exact pair.
7. Lighthouse SEO ≥ 95 on `/` and `/en/` (mobile).

## Public-claims checklist (every page package passes it)
Written by the Website chat (ADSR-912, 01:13), filed here unchanged. The experience public-page EX checklist (ADSR-911 01:17) runs beside it.

### Before every `send`
**1. Status → what a page may say**
| Source status | Public wording (EN) | Public wording (AR) | CTA allowed |
|---|---|---|---|
| 060 Done **and** every network it needs is 050 Verified (live) | "Available" | متاح | Waitlist / sign-up |
| 060 Done, networks Built (faked) — **all of W1–W3d today** | "In development, coming at launch" | قيد التطوير — متاح عند الإطلاق | Waitlist only |
| 060 Scheduled (W4–W7) | "Planned" | مخطط له | Waitlist only |
| 060 Proposed / Compared / Parked, or not in 060 | not mentioned | — | — |
| 050 Planned / Import-first networks | "Planned integration" / "Import support planned" | تكامل مخطط له / الاستيراد مخطط له | none |
| 050 Built (faked) | "Planned integration" (Business's public label per network) | Business's label | none, no "connect" |
| 050 Verified (live) + Business marks the app approved | "Connect <network>" | اربط <network> | Connect |
- Today no network is Verified, so no page may say "available", "connect" or "works with" for any network, and no feature is "available".

**2. Never on a public page before launch**
- Prices, discounts, "free" plans, trial lengths (D-07, D-19)
- Ratings, reviews, testimonials, customer or agency counts, logos of agencies or clients (D-07)
- Partner badges or wording ("Meta Business Partner", "Google Partner", "official integration") unless the network has granted it in writing, and Business confirms
- Network logos, unless Business has checked that network's brand guidelines; use plain names in text until then
- Performance numbers ("save 10 hours a week", "+30% ROAS"), AI accuracy claims, uptime/SLA, security certifications (SOC 2, ISO), data-residency promises, unless the engine confirms them in writing
- An exact launch date: "2027" only, no month (Rami, 2026-11-05, D-41)
- Exact network counts ("55 networks") as present fact; only "planned coverage across search, social, native, retail and more", with the list taken from 050
- Competitor names before the Apr 2027 compare pages (D-08)

**3. Per-page check (the author ticks each line in the package)**
- [ ] Every feature mentioned maps to a 060 row, and its wording matches table 1
- [ ] Every network mentioned maps to a 050 row and uses Business's public label
- [ ] Nothing from list 2
- [ ] AR and EN say the same thing; neither promises more than the other
- [ ] Brand copy minimal until the 15 Nov trademark check (O-60)
- [ ] Sent to experience for the EX check (O-60)
- [ ] Rechecked against 060 / 050 on the day the engine publishes (statuses move)

## Blog calendar (Website, 912 · 01:45; Posts resource D-10 must exist before 22 Dec)
 (Dec 2026 – Mar 2027; Posts resource D-10; author byline plain "Rami Alsharef" per D-41)
| Publish (week of) | Slug (AR + EN same) | EN title (working) | Target queries (estimates) | Links to |
|---|---|---|---|---|
| 15 Dec 2026 | `/ramadan-2027` (D-150) | Ramadan 2027: the advertising plan for agencies (pillar) | ramadan 2027 · إعلانات رمضان | — |
| 22 Dec 2026 | `/blog/eid-al-fitr-2027-campaign-checklist` | Eid al-Fitr 2027 campaign checklist for agencies | eid al fitr 2027 · حملات عيد الفطر | pillar |
| 5 Jan 2027 | `/blog/ramadan-budget-by-phase` | How to split a Ramadan ad budget by phase | ramadan ad budget · ميزانية إعلانات رمضان | pillar |
| 12 Jan 2027 | `/blog/ramadan-ad-scheduling` | Scheduling ads around iftar and suhoor | ramadan ad schedule · توقيت إعلانات رمضان | pillar |
| 19 Jan 2027 | `/blog/ramadan-creative-checklist` | Arabic and English Ramadan creative: a review checklist | ramadan ad creative · تصاميم إعلانات رمضان | pillar |
| 26 Jan 2027 | `/blog/ramadan-client-report` | What to put in a client's Ramadan report | ramadan campaign report · تقرير حملات رمضان | pillar |
| after the official announcements (~1–7 Feb 2027) | update the pillar + `/blog/ramadan-2027-start-by-country` | Ramadan 2027 start date by country | متى رمضان 2027 · ramadan 2027 start date | pillar |
| 16 Mar 2027 | `/blog/ramadan-2027-wrap-up` | After Eid: reviewing Ramadan 2027 results with clients | ramadan results review · مراجعة نتائج رمضان | pillar |
- Every post: full AR (MSA) + EN, title ≤ 60, meta ≤ 160, `Article` schema, the D-43 glossary, sources for every figure, claims checklist + EX check before `send`. Packages are sent 10 days before each publish date.
- Volumes are estimates until the Keyword Planner check (D-28); the list may change after that.
- Needs: Posts resource (D-10) built before 22 Dec, else the D-192 static-page fallback at the same `/blog/<slug>` URLs; blog URLs added to the sitemap as a `blog` child (spec §3).
