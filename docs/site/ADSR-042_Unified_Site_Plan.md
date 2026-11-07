# ADSR-042 Unified site + content plan (adsranger.com)

Owner: **Content chat** (topical map, briefs, link plan, live audits) · Created 2026-11-07 13:45 · Updated 2026-11-07 23:15 (open cases closed: /for-clients indexed; earlier: audit of Website P-11…P-25: B-12; URLs /solutions/* + /integrations/{platform} (Rami 23:08); GA4 folded per D-204; earlier: Rami's 4 answers: demo-report indexed, platform guides first, GA4 page, SME hub URL; earlier: B-09 glossary, B-10 pillars P2–P6, B-11 network pages; earlier sync: D-188…D-190; Backlinks asset note; earlier: O-229 SME cluster P7 + B-08; D-176 / D-181 logged; earlier: R-20 + blog fallback, R-19, B-06, B-07) · Status: **ACTIVE under ADSR-110** (D-154).
Purpose: the public-site and content plan. **ADSR-110 is the one project plan** (engines, rules, order of work); this doc follows its rules (110 §3 rule 11) and holds the site-level detail. **E1** = Claude master engine. **E2** = the second AI engine (PRs only, D-149). Where this doc and ADSR-040 / 041 disagree, ADSR-040 (decisions) and ADSR-041 (approved copy) win until the master merges the change.

---

## 1. Who does what on the public site

| Role | Owns | Never does |
|---|---|---|
| **Content** (this chat) | This doc: topical map, query → page register (no two pages on one query), page briefs (B-xx), internal-link plan, live-site audits after every deploy | Writes final copy, touches code |
| **Website** chat | Page packages from each brief (AR + EN copy, title, meta, schema, OG); technical SEO spec (ADSR-040) | Publishes |
| **experience** chat | EX check + Arabic glossary check of every package and every live page | Writes packages |
| **Backlinks** chat | Off-page work; links only to pages this map marks **LIVE + indexable** | Pitches pages that are not live |
| **E1** (master engine) | Approves, logs (005), files docs, mirrors this plan + packages into the repo, reviews E2's pull requests, migrates, deploys, server | Builds public pages that E2 has claimed |
| **E2** (second engine) | Builds public-site **code** from filed packages: Blade views, routes, sitemap, robots, schema, meta, the Posts resource (D-10), free tools; fixes R-01…R-16 below | Writes copy that isn't in a package · deploys · changes server, DNS or mail · edits E1.md |

**Now (D-169, 18:20):** work runs in the master only. **E1 builds** what the table gives E2 (site fixes P-0…P-10 included); E2 gets no tasks until Rami reopens it. The flow below stays, with E1 in E2's place. `claude/site/E2_site_fixes_prompt.md` is kept for that day.

**One flow for every public page change:**
Content brief (042) → Website package (041) → experience EX check + claims checklist (040) → E1 files the package in the repo (`docs/site/packages/<slug>.md`) → E2 builds it on a branch `e2/site-<slug>` and opens a pull request → E1 reviews, deploys and logs it (005) → Content audits the live page within the same day → the map status changes to LIVE.
Small copy fixes follow the same path; nothing goes to the live site straight from a chat or from E2.

**Sync between the engines:** E2 cannot read this Claude Project, so E1 keeps a **repo mirror** in `docs/site/` (this plan + ADSR-040 rules + filed packages) and updates it with every change. E2 reports in `docs/worklog/E2.md` (branch `e2/log`); E1 copies it to `claude/E2_Reports/E2_report_latest.md` on every `collect`, so every chat sees what E2 did.

---

## 2. Live site vs plan (audit 2026-11-07 13:30, live curl)

**Status 18:56:** **D-169** — work runs in the master only; **E1 builds the site fixes**, E2 gets no tasks (§1's E2 rows wait until Rami reopens it). Live in `8d9a4f5` (E1 18:40): R-03 / R-04 waitlist (D-159 fields, consent waitlist-v1, USD), R-17 "AdsRanger" one word, R-18 Ramadan dates, footer «قيد التطوير · الإطلاق في 2027» (D-170). Still open: R-02 (pillar body), R-06…R-14. Since 13:30 five more public pages went live (all noindex, in the sitemap): `/pricing`, `/integrations`, `/demo-report`, `/mena-calendar`, `/security` (+ `/en/`); fixes for them are Website P-5…P-10 + experience UX-115…141.

Live today: 12 URLs (home, waitlist, Ramadan 2027, privacy, terms, data deletion × AR/EN), all `X-Robots-Tag: noindex, nofollow`. The waitlist and Ramadan pages, and the home copy, don't match the approved packages in ADSR-041 and aren't in the 005 log (last entry 2026-11-05 13:37). They read as E2's edits. Fix list (owner **E2** unless noted; E1 reviews and deploys):

| # | Page | Found live | Plan / rule | Fix | Priority |
|---|---|---|---|---|---|
| R-01 | Ramadan | URL `/ramadan-2027` | ADSR-040/041 said `/calendar/ramadan-2027` | **Q-1 decided: keep `/ramadan-2027`.** Seasonal pillars live at the root (`/{event}-{year}`); `/mena-calendar` is the hub that links to them (Rami 18:57). E1 updates 040 / 041 / blog calendar / Backlinks (916) to the new slug | Docs only |
| R-02 | Ramadan | Calculator page with its own copy: prep "18 Jan", phase 3 "28 Feb – 11 Mar", "CPRs typically rise 10%–25%" (no source), "+20% / +10% seasonality defaults" | Approved, sourced package O-31 rev 2 (prep from 11 Jan, sources section, FAQ, byline) | Pillar body = the 041 package. Calculator moves to **`/tools/ramadan-budget-calculator`** (free tool), assumptions labelled "example assumptions, change them", no unsourced figures | MUST by 15 Dec |
| R-03 | Waitlist | Fields: name, agency, email, monthly spend, networks/notes. No country, no consent box | **D-159:** email, agency, **country**, **consent** (unticked), honeypot required; name, spend range, networks **optional**; double opt-in by Brevo | Add country + consent + honeypot, mark name / spend / networks optional, drop notes | MUST |
| R-04 | Waitlist | "Early agency access · Public launch **Sep 2027**", "cohorts", "hands-on support", "14+ networks", "Reserve your spot" | Claims checklist: "2027" only, no network counts, no early-access promise (D-07, D-41) | Use the 041 waitlist copy (D-51 brand edits) | MUST |
| R-05 | Waitlist | Live and linked from home + Ramadan | Waitlist went live before Brevo (D-99 / D-128) | **Q-2 decided: keep it live, fix in place.** R-03 + R-04 go first in E2's next PR. Rows already collected without consent are not emailed until each person confirms by double opt-in | MUST, first |
| R-06 | Home | H1 "Every campaign judged by the result…", "Private beta", "Open the platform", "Connect Meta + Google… data refreshes every morning", footer "Staging environment" | Home rev 3 package (041) + claims: no "connect" / "available" before a network is Verified; status words "in development" | Build home rev 3 (lead line D-139 stays). Remove "Open the platform" as a primary CTA (secondary Sign in only, D-52) | MUST before Phase 1 |
| R-07 | Home | AR cards «ربط ميتا وجوجل» | D-51 / EX-61: Latin brand names in `<bdi dir="ltr">` | Fix with R-06 | MUST |
| R-08 | All | No `<meta name="description">` | Unique title ≤ 60 + meta ≤ 160 per language (040) | Add from packages | MUST |
| R-09 | All | No canonical | Self-referencing canonical per language | Add | MUST |
| R-10 | All | hreflang ar + en only | + `x-default` → Arabic URL (D-06), in `<head>` and sitemap | Add | MUST |
| R-11 | All | No OG tags, no schema | OG per page (sizes in 041); `Organization` + `WebSite` (home), `Article` + `FAQPage` + `BreadcrumbList` (pillar), `WebPage` (legal/waitlist) | Add | MUST |
| R-12 | robots.txt | `Disallow:` (allow all), no Sitemap line | Phase 1 spec §2 list + `Sitemap:` line | Replace with the spec file | MUST before Phase 1 |
| R-13 | Sitemap | One flat `urlset`, `/en` without slash, no `x-default`, no `lastmod` | Sitemap index → `/sitemaps/pages.xml`; `x-default`; only 200 + canonical + indexable URLs; `lastmod` | Rebuild | MUST before Phase 1 |
| R-14 | English home | `/en` (and `/en/` also answers 200: duplicate) | Spec: `/en/` (only trailing-slash URL), 301 the other | **Q-3 decided: `/en/`**; 301 `/en` → `/en/`; hreflang, switcher, sitemap and internal links use `/en/` | MUST before Phase 1 |
| R-15 | Cookie line | "essential cookies only" banner, single "Got it" | Fine until GA4. With GA4: Accept / Reject banner + Consent Mode v2 (spec §5) | Keep until GA4 goes in | Later |
| R-17 | All (header) | Wordmark text "Ads Ranger" (with a space) | D-51: "AdsRanger", one word, every language | Fix in the shared header | MUST |
| R-18 | Ramadan | ISO dates in copy ("2027-02-08", "2027-03-09") | UX-24: "8 February 2027" / «8 فبراير 2027», Western digits | Fixed by the pillar body (B-02) | MUST |
| R-19 | Sitemap | Website P-0's sitemap set lists 14 URLs and leaves out the 5 pages live since 7 Nov | Every live, canonical page that the map keeps indexable | Add `/pricing`, `/integrations`, `/demo-report`, `/mena-calendar`, `/security` (+ `/en/`) to `/sitemaps/pages.xml`; all five stay indexable (B-05) | MUST before Phase 1 |
| R-20 (= D-181) | Waitlist, calculator | P-1 / P-4 write "USD 10,000", "USD 3,600" (code first); D-173's example is «3,600 USD» (code after) | **Rami (Content chat, 19:59): "3,600 USD (Recommended)"** — number first, code after, site-wide | Edit P-1 spend options («أقل من 10,000 USD» / "Under 10,000 USD", …) and P-4 outputs ("3,600 USD", «<bdi dir=\"ltr\">3,600 USD</bdi>»); same rule in-app | MUST before build |
| R-16 | Legal | `/data-deletion` in the sitemap | Phase 1 set has privacy + terms; data-deletion "joins it" | Keep, matches the legal template package | OK |

---

## 3. Topical map v1

**Central entity:** AdsRanger, an Arabic-first ad workspace for MENA agencies (in development, launching 2027).
**Source context:** an agency team that runs many clients' paid media across several networks in Jordan and the GCC (region-wide SEO, D-85), Arabic first.
**Rule:** one query cluster → one page. A new page needs a free cluster in the register below. Search volumes stay **estimates** until the Keyword Planner check on the AdsRanger Google Ads account (D-129; I-W-9).

### 3.1 Pillars (core) and their spokes

**P1 · Ramadan and MENA seasons**: hub **`/mena-calendar`** (Rami 2026-11-07 18:57, replaces `/calendar` + `/tools/mena-ad-calendar`); pillar `/ramadan-2027` (D-150)
| Page | AR query (seed) | EN query (seed) | Intent | Publish | Status |
|---|---|---|---|---|---|
| `/ramadan-2027` (pillar) | إعلانات رمضان 2027 · حملات رمضان الإعلانية | ramadan 2027 advertising | info → commercial | 15 Dec 2026 | live (noindex) with E2's calculator copy; body to be replaced by the 041 package (R-02) |
| `/tools/ramadan-budget-calculator` | حاسبة ميزانية إعلانات رمضان | ramadan ad budget calculator | tool | 15 Dec 2026 (with the pillar) | built by E2, needs a package + claims check |
| `/blog/eid-al-fitr-2027-campaign-checklist` | حملات عيد الفطر 2027 | eid al fitr 2027 campaign | info | 22 Dec | calendar (040) |
| `/blog/ramadan-budget-by-phase` | ميزانية إعلانات رمضان | ramadan ad budget | info | 5 Jan | calendar |
| `/blog/ramadan-ad-scheduling` | توقيت إعلانات رمضان | ramadan ad schedule | info | 12 Jan | calendar |
| `/blog/ramadan-creative-checklist` | تصاميم إعلانات رمضان | ramadan ad creative | info | 19 Jan | calendar |
| `/blog/ramadan-client-report` | تقرير حملات رمضان | ramadan campaign report | info | 26 Jan | calendar |
| `/blog/ramadan-2027-start-by-country` | متى رمضان 2027 | ramadan 2027 start date | info (news) | ~1–7 Feb | calendar |
| `/blog/ramadan-2027-wrap-up` | مراجعة نتائج رمضان | ramadan results review | info | 16 Mar | calendar |
| `/mena-calendar` (hub + Tool 1 in one page, D-95 data) | تقويم المواسم التسويقية 2027 | MENA marketing calendar 2027 | info + tool | live (noindex); fix per B-04 + P-9 | live |
| `/eid-al-adha-2027` | إعلانات عيد الأضحى | eid al adha advertising | info | Apr 2027 | idea |
| `/white-friday-2027` | عروض الجمعة البيضاء للمعلنين | white friday ads mena | info | Sep 2027 | idea |
| `/saudi-national-day-2027` | حملات اليوم الوطني السعودي | saudi national day campaign | info | Aug 2027 | idea |

**P2 · Client reporting for agencies**: pillar `/guides/client-ad-reporting` → product `/reports`
| Page | AR seed | EN seed | Intent | Publish |
|---|---|---|---|---|
| `/guides/client-ad-reporting` (pillar) | تقارير الحملات الإعلانية للعملاء | client ad reporting | info → commercial | Feb 2027 |
| `/blog/agency-monthly-report-what-to-include` | ماذا يتضمن تقرير الحملات الشهري | monthly ad report for clients | info | Feb |
| `/blog/results-by-campaign-objective` | قياس نتائج الحملة حسب الهدف | measure ad results by objective | info | Feb |
| `/blog/counting-calls-and-leads` | احتساب المكالمات والعملاء المحتملين من الإعلانات | count calls from ads | info | Mar |
| `/blog/arabic-client-reports` | تقارير إعلانية بالعربية للعملاء | arabic marketing reports | info | Mar |
| `/templates/monthly-client-report` | قالب تقرير إعلانات شهري | monthly ad report template | tool | Apr |
| `/reports` (product) | تقارير إعلانات موحدة | cross-network ad reporting | commercial | Dec 2026 (package filed) |

**P3 · Budget planning and pacing**: pillar `/guides/ad-budget-pacing` → product `/reports#planning`
| Page | AR seed | EN seed | Intent | Publish |
|---|---|---|---|---|
| `/guides/ad-budget-pacing` (pillar) | متابعة ميزانية الإعلانات · وتيرة الإنفاق | ad budget pacing | info → commercial | Mar 2027 |
| `/blog/split-budget-across-networks` | توزيع ميزانية الإعلانات على المنصات | split ad budget across platforms | info | Mar |
| `/blog/month-end-spend-forecast` | توقع الإنفاق الإعلاني آخر الشهر | ad spend forecast | info | Mar |
| `/tools/break-even-roas` (P-19, replaces the ad cost calculator) | حاسبة العائد على الإنفاق الإعلاني · حاسبة ROAS | break even roas calculator | tool | Jan–Mar (proposed, D-204) |

**P4 · Meta ads in MENA**: pillar `/guides/meta-ads-mena` → `/integrations/meta`
Spokes (Dec–Jan guide-first, D-111 c): Meta ads in Saudi · Arabic ad copy for Meta · click-to-WhatsApp campaigns · Meta lead forms for MENA · Meta ad review in Arabic markets.

**P5 · Google Ads for agencies**: pillar `/guides/google-ads-for-agencies` → `/search`, `/integrations/google-ads`
Spokes: Arabic keywords + match types · negative keywords for Arabic searches · search terms report · Performance Max for MENA · Quality Score.

**P6 · Snapchat + TikTok in the Gulf**: pillar `/guides/snapchat-tiktok-ads-gulf` → `/integrations/snapchat`, `/integrations/tiktok`
Spokes: Snapchat ads in Saudi · TikTok ads in Saudi (how-to) · Snapchat vs TikTok for Gulf brands · vertical video specs (links `/tools/ad-specs`).

**P7 · Ads for business owners (SME, in-house; D-183…D-185, O-229)**: hub `/solutions/business-owners` (Rami 22:17: URL confirmed); the Ramadan calculator is the first SME tool
- **Rules:** agency guard (D-183): home, `/features` and the headline stay agency-first; SME pages sit under `/solutions/` and `/blog/`, never on agency client links, PDFs or report emails; no SME prices before launch (D-07); every page ends at the waitlist (type choice "business", O-230). Audience = owners who run their own ads (Jordan + GCC, D-85 region-wide SEO), plain MSA, no agency jargon.
| Page | AR query (seed, estimate) | EN query (seed) | Intent | Publish | Links |
|---|---|---|---|---|---|
| `/solutions/business-owners` (hub) | إدارة إعلانات مشروعك بنفسك | run your own ads small business | commercial (soft) | Jan 2027 | every P7 guide · `/tools/ramadan-budget-calculator` · waitlist |
| `/blog/meta-ads-budget-jordan` | كم ميزانية إعلانات فيسبوك وانستغرام في الأردن | how much to spend on facebook ads jordan | info | 2 Feb | hub · calculator · `/glossary` (CPM, CPR) |
| `/blog/ramadan-ads-small-shop` | إعلانات رمضان للمحلات الصغيرة | ramadan ads for small business | info (seasonal) | 26 Jan | ↑ `/ramadan-2027` · calculator · hub |
| `/blog/read-your-ad-results` | كيف تقرأ نتائج إعلاناتك بنفسك | how to read facebook ads results | info | 9 Feb | hub · `/demo-report` · `/reports` |
| `/blog/boost-post-vs-ads-manager` | الفرق بين تمويل المنشور ومدير الإعلانات | boost post vs ads manager | info | 16 Feb | hub · P4 Meta pillar (when live) |
| `/blog/click-to-whatsapp-ads` | إعلانات واتساب للمتاجر | click to whatsapp ads | info | 23 Feb | hub · P4 Meta pillar |
| `/blog/snapchat-ads-small-business-saudi` | إعلانات سناب شات للمشاريع الصغيرة في السعودية | snapchat ads small business saudi | info | 2 Mar | hub · P6 pillar (when live) |
| free ad-account check (later, #173b flow) | فحص حساب الإعلانات مجانًا | free ad account audit | tool | after Stage 1 apps are approved | hub · waitlist |

### 3.2 Outer section (supports the core)
| Section | URLs | When | Notes |
|---|---|---|---|
| Glossary | `/glossary`, `/glossary/{term}` | 40 terms Jan 2027 (D-111 a) | ADSR-030 glossary is the term source; every first mention in an article links here |
| Tools hub | `/tools` + 4 tools (D-90 order) incl. UTM builder (Tool 4, copy in 041) | Jan–Mar 2027 | Each tool links to its in-app twin as "in development" |
| Product | `/`, `/features`, `/reports`, `/studio`, `/search`, `/ai` | Dec 2026 (packages filed) | Claims checklist on every line |
| Platform pages | `/integrations/{platform}`, Stage 1 (7; B-11) | Dec 2026 → | Page goes live only when its connector is in the build; label "planned integration" |
| Solutions + markets | `/solutions/agencies`, `/solutions/freelancers`, `/solutions/business-owners` (B-12), `/markets/{sa, ae, eg, jo, kw, qa}`, one generic vertical (D-116, Website picks from demand) | Jan–Mar 2027 | No mention of Rami's past clients or verticals |
| Company | `/about`, `/contact` (+ `/en/`) in the Phase 1 set (D-120) | Phase 1 | Author page dropped until launch (D-55) |
| Compare | `/compare/{tool}` | from Apr 2027 (D-08) | Factual tables only |
| Live since 7 Nov (§7 B-05) | `/pricing`, `/integrations`, `/demo-report`, `/mena-calendar`, `/security` (+ `/en/`) | live, noindex | Fixed by Website P-5…P-10 + E1 |

### 3.3 Query → page register (no cannibalisation)
| Cluster | Owner page | Not on |
|---|---|---|
| Ramadan advertising (general) | `/ramadan-2027` | blog posts, home |
| MENA / seasons calendar | `/mena-calendar` | pillar keeps a short dates table and links here |
| Ramadan ads for small shops (owner angle) | `/blog/ramadan-ads-small-shop` | `/ramadan-2027` keeps the agency angle; each links to the other once |
| Ad budget for owners | `/blog/meta-ads-budget-jordan` | the calculator owns "calculator" queries; B-07b owns the agency phase split |
| Ramadan budget numbers | `/tools/ramadan-budget-calculator` + `/blog/ramadan-budget-by-phase` (how-to) | pillar (links out) |
| Ramadan dates | `/blog/ramadan-2027-start-by-country` | pillar keeps one dates table, links out |
| Client reporting (tool) | `/reports` | `/guides/client-ad-reporting` targets the how-to query |
| Pacing (tool) vs pacing (how-to) | `/reports#planning` vs `/guides/ad-budget-pacing` | — |
| Ad management platform | `/` | `/features` targets "agency ad management platform features" |
| Agency audience | `/solutions/agencies` (P-12) | `/` keeps the platform query; `/for-teams` is a role page, no query |
| Freelancer audience | `/solutions/freelancers` (P-13) | — |
| Business owners / in-house | `/solutions/business-owners` (P-13 in-house copy = this hub, D-202) | no `/in-house` URL |
| Client ad report (tool / how-to / viewer) | `/reports` (tool) · `/guides/client-ad-reporting` (how-to) | `/for-clients` (P-14) targets no query: it's the page agencies send to clients |
| ROAS calculator | `/tools/break-even-roas` (P-19) | glossary "what is ROAS" keeps the definition; `/tools/ad-cost-calculator` dropped (P-19 replaces it) |
| Ad text length / Arabic ad copy check | `/tools/arabic-ad-text-checker` (P-17) | P4 spoke "Arabic copy for Meta" keeps the how-to |
| Hijri ↔ Gregorian for campaigns | `/tools/hijri-dates` (P-18) | `/mena-calendar` keeps the season dates |
| Ramadan campaign checklist | `/tools/ramadan-readiness` (P-20) | pillar keeps its checklist section, links to the quiz |

---

## 4. Internal-link plan (adds to ADSR-040 rules 1–7)
1. Pillar → every spoke (in-body, once). Spoke → its pillar (first 150 words) + one product page + one tool or network page.
2. Home → each live pillar (one text link each) + `/features`.
3. Tools → their pillar and the in-app feature ("in development").
4. Anchors: page topic, varied; brand anchor **AdsRanger** in every language (D-51); never "click here"; no exact-match commercial anchors repeated across pages.
5. No link to a page that isn't LIVE + indexable. Exception: the waitlist CTA (Q-2), once R-03 + R-04 are deployed.
6. Content audits links after every deploy: orphans, missing reverse links, duplicate anchors, AR ↔ EN pairs.

---

## 5. Next 6 weeks (dates from the clock; Phase 1 17–30 Nov, ADSR-040)
| Week | Content | Website | E1 builds (D-169; was E2) | E1 ops |
|---|---|---|---|---|
| 9–15 Nov | Briefs B-01 home, B-02 Ramadan pillar + calculator tool | Waitlist rev (R-03/04), calculator tool package | R-01…R-14 on one branch `e2/site-fixes` → PR | Mirror plan + packages to `docs/site/`; review + deploy the PR |
| 16–30 Nov (Phase 1) | Audit Phase 1 live; register v2 after Keyword Planner | about / contact packages | Phase 1 build (robots, sitemaps, consent, GA4 tag after Rami's property) | Lift noindex, submit sitemap, Bing import |
| 1–15 Dec | Briefs for Dec product pages + 6 Ramadan spokes | Packages, 10 days before each date | `/mena-calendar` fixes, pillar body from 041 at `/ramadan-2027`, calculator → `/tools/ramadan-budget-calculator`, Posts resource (D-10, before 22 Dec) | Deploy; Backlinks starts outreach 15 Dec |

---

## 6. Decisions (Rami, Content chat, 2026-11-07 ~13:50; logged by the master)
- **Closing rule (Rami, Content chat, 23:15):** items that only wait for the master to log Rami's answers are **closed in Content** ("Close here, master logs later (Recommended)"); items handed to another chat are **closed as handed off** ("Close as handed off (Recommended)"), and Content checks them when they reach the live site.
- **`/for-clients` (Rami 23:15):** "Index it, no target query (Recommended)".
- **D-150** (Q-1) Ramadan pillar URL → **`/ramadan-2027`**. Seasonal pillars at the root. Replaces D-148 / B-7; no 301.
- **D-181** USD order → amount first, ISO code after («10,000 USD»), everywhere (Rami, master 19:10; Content's 19:59 answer is the same).
- **Blog fallback (Rami, Content chat, 2026-11-07 19:59, "Publish as a fixed page (Recommended)")** → if the Posts resource (D-10) isn't live by 22 Dec, E1 builds each due spoke as a static page at its `/blog/<slug>` URL; it moves into Posts later with the same URL (no redirect). Needs a D-number (E1).
- **D-176** Calendar → one page `/mena-calendar` (+ `/en/mena-calendar`) = seasons hub + calendar tool; `/calendar` and `/tools/mena-ad-calendar` dropped.
- **D-183…D-185** SMEs are a second market now (in-house mode, agency guard, public headline stays agency-first); Solo 19 USD for them too; first channel = free Arabic tools + content → cluster **P7** below (O-229).
- **Rami, Content chat, 2026-11-07 22:17 (options; need D-numbers from E1):** `/demo-report` **indexed** · **platform guides first** (P4 5 Jan, P5 12 Jan, P6 19 Jan, then P2, P3) · ~~GA4 page~~ → **superseded by D-204** (GA4 folded into the guides; 7 platform pages) · SME hub stays **`/solutions/business-owners`**.
- **D-152** (Q-2…Q-4) Waitlist → **live, fixed in place** (R-03 + R-04 first) · English home → **`/en/`**, 301 from `/en` · E2 builds public-site code **only from filed packages, by PR**; E1 reviews, deploys, logs (D-149).
- **D-154** Content chat owns this doc (outbox ADSR-917_Content_Outbox).
- **Open (Content → master, 917 · 14:15):** ADSR-110 §1 writes the English Ramadan URL as `/en/ramadan-2027/`; the URL rule (040, §2 R-14) gives a trailing slash to the English **home only** → use `/en/ramadan-2027`.

---

## 7. Briefs (Content → Website; Website turns each into a package in 041)

### B-01 · Home (`/`, `/en/`) · package due 14 Nov · live with Phase 1 (17–30 Nov)
- **Cluster:** platform (`/` in §3.3). AR «منصة إدارة الإعلانات للوكالات» · EN "ad management platform for agencies". Intent: commercial, brand-forming. Volumes = estimates (D-129 check pending).
- **Base copy:** home rev 3 in 041 + the D-139 lead line (live `3163198`, keep word for word). Title ≤ 60 leads with the Arabic keyword in AR; meta ≤ 160 (none live today, R-08).
- **Remove (R-06 / R-07):** "Private beta" · "Open the platform" as a primary button (keep secondary «تسجيل الدخول» / "Sign in" → `/app/login`, D-52) · "Connect Meta + Google… data refreshes every morning" (present-tense claim; no network is Verified) · footer "Staging environment" · «ميتا وجوجل» → `<bdi dir="ltr">Meta</bdi>، <bdi dir="ltr">Google</bdi>`.
- **Sections:** H1 + lead (D-139) → 3 pillars (rev 3, one "in development" label on the section, UX-15) → one text link to the Ramadan pillar → waitlist CTA (D-152, once R-03 / R-04 are live) → footer (legal, cookie line).
- **Links out (§4 rule 2):** `/ramadan-2027` · `/en/ramadan-2027` (language-matched) · `/waitlist` · legal. Add `/features` when the Dec product pages ship.
- **Schema / meta:** `Organization` + `WebSite`; self canonical; hreflang ar / en / x-default → `/`; OG 1200×630 wordmark (041 sizes).
- **Not on the page:** prices, network counts, "available / connect", launch month (claims checklist, 040).

### B-02 · Ramadan pillar (`/ramadan-2027`) + calculator tool (`/tools/ramadan-budget-calculator`) · packages due 25 Nov · live 15 Dec
**Pillar**
- **Cluster:** Ramadan advertising (general). AR «إعلانات رمضان 2027» · «حملات رمضان الإعلانية» · EN "ramadan 2027 advertising" · "ramadan marketing campaign". Intent: info → commercial. Owns the query; spokes link up to it (§3.1).
- **Body:** the filed package O-31 **rev 2** (041) with the D-51 brand edits and UX-31 line: key-dates table, 5 phases (preparation from **11 Jan**), agency checklist, "How AdsRanger fits", 4 FAQs, sources section, byline "Rami Alsharef · dates expected, last checked …". Replaces E2's calculator copy (R-02). Dates follow the Tool 1 table (D-95): Ramadan 8 Feb, Eid 9 Mar (or 10 Mar), all "expected".
- **Drop from the live page:** "auction CPRs typically rise 10%–25%" (no source) · "AdsRanger seasonality defaults" · phase dates 18 Jan / 28 Feb – 11 Mar (don't match the sourced table) · "Open MENA Calendar in App" button (in-app, not public).
- **Add:** one block "Plan your budget" → link to `/tools/ramadan-budget-calculator`; link to `/mena-calendar`; spokes from the 040 blog calendar as they publish (22 Dec →).
- **Schema / meta:** `Article` (author `Person` Rami Alsharef + LinkedIn `sameAs`, datePublished, dateModified) + `FAQPage` (the 4 FAQs) + `BreadcrumbList` (Home › Ramadan 2027); title / meta from 041 rev 2 (D-51 form).
- **Update rule:** re-check dates after the official announcements (~1–7 Feb) → `dateModified`; then `/blog/ramadan-2027-start-by-country`.

**Calculator tool**
- **Cluster:** Ramadan budget numbers. AR «حاسبة ميزانية إعلانات رمضان» · EN "ramadan ad budget calculator". Intent: tool. Owns the query (§3.3); the pillar links to it.
- **Function (reuse E2's calculator code):** inputs = normal daily spend, normal cost per result, currency (USD default; JOD, SAR, AED, KWD, QAR, EGP); two editable assumptions, **spend change %** and **cost-per-result change %**, pre-filled and labelled «افتراضات تجريبية، عدّلها» / "Example assumptions, edit them"; outputs = Ramadan budget for 29 or 30 days (toggle), expected cost per result, expected results. Nothing stored, no sign-in.
- **Copy rules:** no claim that the defaults are AdsRanger data or market averages; no unsourced market figures; a one-line note "Estimates only, based on the numbers you enter." Western digits, `<bdi>` around currency codes in Arabic.
- **Links:** back to `/ramadan-2027` (first paragraph), in-app twin as "in development" (Ramadan scenario planner #96, text only, no `/app` link), `/waitlist`.
- **Schema / meta:** `WebPage` + `BreadcrumbList` (Home › Tools › Ramadan budget calculator); its own title / meta in both languages.
- **E2 build note:** move, don't duplicate: the calculator leaves `/ramadan-2027` in the same PR that ships the pillar body.

### B-03 · Waitlist (`/waitlist`, `/en/waitlist`) · **live in `8d9a4f5` (E1 18:40)** · rev for D-172 / D-173 + thanks state
- **Cluster:** none (conversion page, not a ranking target). Indexable with Phase 1 (spec §3).
- **Built (E1 18:40):** email, agency, country, unticked consent (stored as waitlist-v1) required; name, spend (USD), platforms optional; honeypot; 5 per IP per hour; old rows never emailed (D-152). Confirmation shows on the same page (no `/waitlist/thanks` URL) — fine for SEO: nothing extra to index or disallow.
- **Copy rules (still binding):** consent text = **D-172** (Website P-1: «أوافق على تلقي رسائل بريد إلكتروني حول إطلاق AdsRanger» + EN twin), no "early access", "cohorts", "hands-on support", network counts or launch month; stage line = **D-170** «قيد التطوير · الإطلاق في 2027»; spend ranges in ISO code «10,000 USD» (**D-173**), never "$" / «دولار»; button «انضم إلى قائمة الانتظار» (UX-32); never reveal whether an email is already listed (UX-33).
- **Same-page confirmation text:** as filed in Website **P-1** (Rami-approved): H2 EN "Thanks, you're on the list" · AR «شكرًا، سجّلنا بياناتك»; body says the address is confirmed by one email before any news. Content's earlier draft line is withdrawn.
- **Links:** home, `/privacy`. Home + Ramadan pillar link here (§4 rule 5 exception).
- **Schema / meta:** `WebPage`; self canonical; hreflang ar / en / x-default → `/waitlist`.

### B-04 · Seasons calendar hub (`/mena-calendar`, `/en/mena-calendar`) · live (noindex), fix with P-9 · final by 15 Dec (with the pillar)
- **URL:** **`/mena-calendar`** (Rami 18:57, "Keep /mena-calendar (Recommended)"). One page is both the hub and the calendar tool; `/calendar` and `/tools/mena-ad-calendar` are not built. If either was linked anywhere, no redirect is needed (both 404, never live).
- **Cluster:** MENA calendar. AR «تقويم المواسم التسويقية 2027» · «مواسم التسويق في الخليج» · EN "MENA marketing calendar 2027". Intent: info + tool. Owns the query alone.
- **Name (D-190):** «تقويم المواسم» / "Seasons calendar" in nav, links and labels. Content's rule for the `<title>` and H1: keep the query words «تقويم» + «2027» / "calendar" + "2027" (e.g. «تقويم المواسم الإعلانية 2027»).
- **Job:** hub for every seasonal pillar (D-150: pillars at the root) + the full 2027 table from the D-95 data (041), each row "expected" or "fixed" with its source; rows with a live pillar link to it (today `/ramadan-2027`).
- **Fix from the live page:** title says "2026–2027" → 2027 only; past rows (e.g. 23 Sep 2026) out (UX-136); one Eid al-Fitr end date (UX-136); no retailer logos or partnership wording; dates in words (UX-24).
- **Sections:** H1 + one-line lead → "Season guides" (cards for live pillars only) → "2027 key dates" (360 px: one card per row) → waitlist CTA. Later (Tool 1, D-90): country filter + `.ics` feed on this same page (I-W-7), no new URL.
- **Links:** every live seasonal pillar; `/ramadan-2027` links back here; home links here once two seasonal pages are live.
- **Backlinks assets (status):** `/ramadan-2027`, `/mena-calendar` and (after P-4 ships) `/tools/ramadan-budget-calculator` become pitchable when Phase 1 lifts noindex; Content marks them LIVE + indexable here on that day. The calculator **moves** to its own URL (B-02, P-4): today's 404 means "not built yet", not "dropped".
- **Schema / meta:** `CollectionPage` + `BreadcrumbList` (Home › Calendar); `ItemList` of the season guides; own title / meta per language.

### B-05 · Five new pages in the map (live since 7 Nov, all noindex, in the sitemap)
| Page | Role in the map | Cluster it may own | Notes for Website / experience |
|---|---|---|---|
| `/pricing` | Product (convert) | "AdsRanger plans" (brand) only | Title "Plans" (P-6); no prices before launch (D-07); Solo card question Q1 is with Rami (e1-collect 18:43) |
| `/integrations` | Product → Networks hub | "ad platforms AdsRanger connects to" | Parent of `/integrations/{platform}` (B-11); every network "planned integration" (claims); remove the 6 networks with no connector (UX-131) |
| `/demo-report` | Product → Reporting (P2) · **indexed** (Rami 22:17: "Index it (Recommended)") | none (sample, not a ranking target) | Links to `/reports` when live; P2 pillar links to it as "sample client report"; neutral retailer client (UX-133) |
| `/mena-calendar` | P1 hub | MENA calendar | B-04 above |
| `/security` | Company / trust | "AdsRanger security" (brand) | Only claims E1 confirms in writing (040 list 2; UX-139…141) |

### B-06 · December product pages (`/features`, `/reports`, `/studio`, `/search`, `/ai` + `/en/`) · packages filed in 041 (912 · 02:13) → refresh due 1 Dec · live Dec step, after Phase 1
| Page | Owns cluster (§3.3) | Must link to | Must not |
|---|---|---|---|
| `/features` (hub) | "agency ad management platform features" · «منصة إدارة إعلانات للوكالات» (features angle; `/` keeps the platform query) | all 4 product pages, `/integrations`, `/ramadan-2027` | repeat the home H1 or title |
| `/reports` | cross-network ad reporting · «تقارير إعلانات موحدة» | `/demo-report` ("See a sample report"), `/features`, `/integrations`; later `/guides/client-ad-reporting` | claim live connects (050 labels) |
| `/studio` | create ads for several platforms · «إنشاء إعلانات لعدة منصات» | `/features`, `/ai`, `/integrations` | "every network" (UX-37) |
| `/search` | Google Ads tools for agencies · «أدوات إعلانات البحث للوكالات» | `/features`, `/studio`, `/reports`; later the P5 pillar | — |
| `/ai` | AI for ad agencies in Arabic · «الذكاء الاصطناعي لوكالات الإعلان» | `/features`, `/studio` | accuracy claims, model names, "trained on everyone's data" (O-183) |
- **Refresh rules for Website:** P-5 shared layout + P-0 head; D-170 stage line; status badges «قيد التطوير» / «مخطط له» per 060 today (statuses moved since 5 Nov: #12, #51, #56, #57, #100, #176 built → "In development"); network names and order exactly as P-7 `/integrations`; "AdsRanger" one word; no "$".
- **Navigation:** when these ship, add "Features" / «الميزات» to the P-5 header (between Home and Ramadan 2027) and footer, so every product page is 2 clicks from home.
- **Sitemap:** add the 10 URLs to `/sitemaps/pages.xml` (a `features` child is optional until there are more than ~50 URLs).

### B-07 · Ramadan spokes (blog, pillar P1) · Posts resource (D-10) before 22 Dec, else static pages at the same URLs (Rami 19:59) · each package due 10 days before publish
| # | Slug (AR + EN same) | Publish (week of) | Package due | Query (AR · EN, estimates) | Angle (one job, no overlap) | Links |
|---|---|---|---|---|---|---|
| B-07a | `/blog/eid-al-fitr-2027-campaign-checklist` | 22 Dec | 12 Dec | حملات عيد الفطر 2027 · eid al fitr 2027 campaign | Eid-only checklist: dates per country (D-95 A4), offer timing, holiday cover, post-Eid budget | ↑ `/ramadan-2027` · `/mena-calendar` · waitlist |
| B-07b | `/blog/ramadan-budget-by-phase` | 5 Jan | 26 Dec | ميزانية إعلانات رمضان · ramadan ad budget | How to split one client budget across the 5 phases; worked example in USD («3,600 USD» order, R-20) | ↑ pillar · `/tools/ramadan-budget-calculator` (owns the numbers query) |
| B-07c | `/blog/ramadan-ad-scheduling` | 12 Jan | 2 Jan | توقيت إعلانات رمضان · ramadan ad schedule | Iftar / suhoor hours by market, time zones per account | ↑ pillar · `/reports` (best hours, "in development") |
| B-07d | `/blog/ramadan-creative-checklist` | 19 Jan | 9 Jan | تصاميم إعلانات رمضان · ramadan ad creative | AR + EN creative review, ad-review lead times per network | ↑ pillar · `/studio` ("planned") |
| B-07e | `/blog/ramadan-client-report` | 26 Jan | 16 Jan | تقرير حملات رمضان · ramadan campaign report | What goes in the client's Ramadan report, by phase | ↑ pillar · `/demo-report` · `/reports` |
| B-07f | `/blog/ramadan-2027-start-by-country` | after the official announcements (~1–7 Feb) | draft by 25 Jan, dates filled on the day | متى رمضان 2027 · ramadan 2027 start date | Confirmed start + Eid per country, sourced to each official announcement; owns the "dates" query (§3.3) | ↑ pillar · `/mena-calendar`; pillar `dateModified` the same day |
| B-07g | `/blog/ramadan-2027-wrap-up` | 16 Mar | 6 Mar | مراجعة نتائج رمضان · ramadan results review | Reviewing results with clients after Eid | ↑ pillar · `/reports` |
- **Every spoke:** full AR (MSA) + EN; title ≤ 60, meta ≤ 160; `Article` (author Rami Alsharef, LinkedIn `sameAs`) + `BreadcrumbList` (Home › Ramadan 2027 › post); a source for every figure; claims checklist + EX check before `send`; first mention of a glossary term linked once the glossary exists; the pillar adds a link to each spoke on its publish day.
- **Backlinks:** each spoke is an outreach asset only once live and indexable (Phase 1).

### Keyword register v2 (scheduled, not a gap in this doc)
- Needs the AdsRanger Google Ads account (D-129) → **Sun 8 Nov session** (D-155). Content then re-ranks §3 with real AR + EN volumes for SA, AE, EG, JO, KW, QA and updates §3.3; until then every volume here is an estimate.

### B-08 · SME cluster P7 (O-229, D-183…D-185) · packages: hub by 15 Jan, guides 10 days before each publish (first: `/blog/ramadan-ads-small-shop`, due 16 Jan)
- **Pages, queries, dates, links:** §3.1 P7 table. One query per page (§3.3 rows added for the two overlaps with P1).
- **Voice:** an owner who runs their own ads, not an agency; MSA, short sentences, worked examples in «… USD» and one local currency (JOD / SAR, D-181 order); no "agency" word in SME titles.
- **Every guide:** Arabic first, EN twin; title ≤ 60, meta ≤ 160; `Article` (author Rami Alsharef) + `BreadcrumbList` (Home › For business owners › post); a source for every figure (no invented benchmarks; platform help pages first); ends with the waitlist CTA, type «شركة تدير إعلاناتها بنفسها» / "Business that runs its own ads" pre-selected (D-189).
- **Hub `/solutions/business-owners`:** H1 «أدِر إعلانات مشروعك بنفسك» / "Run your own business's ads" (Website finalises); lists the guides + the calculator; Solo appears only as "Planned" with no price (D-07, D-180); no comparison with agencies, no "replace your agency" angle (agency guard).
- **Not allowed:** SME offers or links on agency-facing surfaces; contacting or targeting any agency's clients; claims about live connects (050 labels).
- **Sitemap / nav:** add each page on publish; footer link "For business owners" / «لأصحاب المشاريع» once the hub is live; header unchanged (agency-first).
- **Keyword check:** these seeds join register v2 (Sun 8 Nov session, D-155); titles may change after real volumes.

### B-09 · Glossary hub (`/glossary`, `/glossary/{term}` + `/en/`) · 40 terms by end Jan 2027 (D-111 a) · packages in 4 batches of 10, from 4 Jan
- **Source of terms:** the ADSR-030 EX-61 glossary (the app's own Arabic terms), so site and app say the same thing.
- **Batch order (by search pull + use in our pages):** 1) CPM, CPC, CTR, CPR, ROAS, conversion, frequency, reach, impression share, Quality Score · 2) pacing «وتيرة الإنفاق», budget, bid strategy, lookalike, retargeting, lead form, pixel, conversions API, attribution, UTM · 3) Performance Max, search terms, negative keywords, match types, ad set, campaign objective, creative «التصاميم الإعلانية», ThruPlay, placement, A/B test · 4) iftar / suhoor scheduling, Hijri date, health score «مؤشر الصحة», targets «المستهدفات», blended ROAS / MER, CAC, LTV, MENA, ad network «المنصة الإعلانية», benchmarks «المقارنات المرجعية».
- **Each term page:** AR title «ما هو {term}؟» / EN "What is {term}?"; 80–200 words: definition, formula if any, one MENA example in «… USD» (D-181), "where you see it in AdsRanger" only as "in development" (no live claims); `DefinedTerm` + `BreadcrumbList`; links up to `/glossary` and out to the one pillar that uses it most.
- **Hub:** A–Z (Latin) + «أ–ي» lists, search box optional; `DefinedTermSet`. Every article links a term's first mention here (§4 rule 5 once live).
- **Owns:** "what is {term}" queries only; pillars keep the how-to.

### B-10 · Remaining pillars P2–P6 (6 pillars by Mar 2027, living plan) · packages 10 days before publish
| Pillar | URL | Publish (week of) | Package due | Owns (AR · EN, estimates) | Spokes (first 3) | Product link |
|---|---|---|---|---|---|---|
| P4 Meta in MENA | `/guides/meta-ads-mena` | 5 Jan 2027 | 26 Dec | إعلانات فيسبوك وانستغرام في الخليج · meta ads mena | Arabic copy for Meta · click-to-WhatsApp (shared with P7 owner angle) · lead forms | `/integrations` (Meta, planned) |
| P5 Google Ads for agencies | `/guides/google-ads-for-agencies` | 12 Jan | 2 Jan | إدارة إعلانات جوجل للوكالات · google ads for agencies | Arabic keywords + match types · negative keywords · Performance Max in MENA | `/search` |
| P6 Snapchat + TikTok in the Gulf | `/guides/snapchat-tiktok-ads-gulf` | 19 Jan | 9 Jan | إعلانات سناب شات وتيك توك في السعودية · snapchat tiktok ads saudi | Snapchat ads in Saudi · TikTok ads in Saudi · vertical video specs | `/integrations` |
| P2 Client reporting | `/guides/client-ad-reporting` | 2 Feb | 23 Jan | تقارير الحملات الإعلانية للعملاء · client ad reporting | monthly report contents · results by objective · calls + leads | `/reports`, `/demo-report` |
| P3 Budget pacing | `/guides/ad-budget-pacing` | 2 Mar | 20 Feb | وتيرة إنفاق الإعلانات · ad budget pacing | split across networks · month-end forecast · goal calculator | `/reports#planning` |
- **Order logic (Rami 22:17: "Platform guides first (Recommended)"):** network guides first (D-111 c), then the agency-ops pillars once the product pages exist. Pillars use the same `/guides/` folder (living plan); no dates in slugs (evergreen, refreshed yearly).
- **Every pillar:** 1,500–2,500 words AR + EN; a sourced figure or none; `Article` + `BreadcrumbList` (+ `FAQPage` only with a real FAQ); links to all its live spokes, one product page, the glossary terms it uses, and the waitlist; spokes publish after their pillar.
- **Overlap guard:** P4 click-to-WhatsApp is the agency angle; P7 `/blog/click-to-whatsapp-ads` is the owner angle; each links to the other once (§3.3).

### B-11 · Platform pages (`/integrations/{platform}`, Rami 23:08: "/integrations/{platform} (Recommended)") · Stage 1 (7) · built from Website P-21 · proposed until M-3 (D-204)
- **Pages (7):** `/integrations/meta`, `/integrations/google-ads`, `/integrations/tiktok`, `/integrations/snapchat`, `/integrations/microsoft-advertising`, `/integrations/linkedin`, `/integrations/x` (+ `/en/`). Parent `/integrations`. The old `/networks/` section (040, living plan) is dropped.
- **GA4:** **no page** (D-204, Rami master 22:22 "Fold into the tools/guides", replaces his Content-chat answer at 22:17 / D-202). GA4 reporting is covered in P2 `/guides/client-ad-reporting`, the glossary and the `/integrations` hub line.
- **Owns (P-21 title pattern):** «تقارير إعلانات {platform} للوكالات» / "{platform} reporting for agencies" (commercial). The P4–P6 pillars own the how-to; each platform page links to its pillar and back. This is the cluster P-21 asks for before indexing.
- **Content rules:** "Planned integration" / «تكامل مخطط له» (050 labels; no "connect" until Verified); "What AdsRanger will report"; "Notes for MENA" only with a cited source; plan names only, no prices (D-07); `WebPage` + `BreadcrumbList` (Home › Ad platforms › {platform}).
- **Gate:** a platform page goes live only when its connector is in the build; Stage 2 stays a line on `/integrations`. "Suggest a platform" form (P-21) sits on `/integrations` only.

### B-12 · Audit of Website P-11…P-25 (`claude/site/P-11_P-24_packages.md`) · all proposed until M-3 except what Phase 1 needs (D-204)
| Package | Map verdict | Change before build |
|---|---|---|
| P-11 "I am…" chooser | OK (no URL) | "Read more" links → `/solutions/agencies`, `/solutions/freelancers`, `/solutions/business-owners` |
| P-12 /agencies | OK as hub | URL **`/solutions/agencies`** (Rami 23:08) |
| P-13 /freelancers + /in-house | OK | URLs **`/solutions/freelancers`** and **`/solutions/business-owners`** (in-house copy becomes the P7 hub, B-08 rules apply: agency guard, owner voice) |
| P-14 /for-clients | OK, no ranking target | **Indexed, no target query** (Rami 23:15: "Index it, no target query (Recommended)"); title must not chase "client ad report" (owned by `/reports` + P2); linked from `/reports` and client links' footer |
| P-15 /for-teams | OK | Role names per D-203 / D-205 («مدير علاقات العملاء») |
| P-16 /tools hub | OK (map planned it) | Card list = map: calculator, quiz, text checker, Hijri dates, break-even ROAS, UTM builder, `/mena-calendar`; `/tools/ad-cost-calculator` + `/tools/goal-calculator` dropped from the map |
| P-17…P-20 tools | OK, clusters in §3.3 | Each links to its pillar: P-17 → P4, P-18 → `/mena-calendar`, P-19 → P3, P-20 → `/ramadan-2027` |
| P-21 platform pages | OK | URLs `/integrations/{platform}` (Rami 23:08); cluster in B-11; no GA4 page (D-204) |
| P-22 /roadmap | OK, brand only | Only 060 rows marked public; no dates beyond "2027" |
| P-23 /about · P-24 /contact · P-25 /partners | OK | Proposed per D-204; sitemap only when built |
- **Sitemap / nav:** add each page when it ships; header gets "Free tools" with P-16 (P-5 menu); footer gets About, Contact, Partners when live.

