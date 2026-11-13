# Arabic SEO test set (FR-095, SEO slice 0)

Spec: `claude/product/SEO_Slice0_Spec.md` (Product, 2026-11-08) · rules: `resources/arabic/normalise.json` (only copy).

| File | Author | Pass bar | State |
|---|---|---|---|
| `must_match_spelling.csv` | Content (106 pairs) + E1 (hamza seats, digits, tashkeel, tatweel, case) | 100 % | step 0.1 |
| `must_not_match.csv` | Content 13:48 cases (Amman / Oman, related words); Product + Content grow it to ≥ 50 | 100 % (0 false merges) | step 0.1, to grow in 0.2 |
| `parity_extra.txt` | E1 | PHP = JS for every function | step 0.1 |
| `arabizi.csv`, `dialect_tags.csv` | Content | ≥ 0.90 / ≥ 0.85 | step 0.2 |
| `pages/*.html`, `pages/expected.json` | Product (1–20) · E1 (21–28: fonts, encoded URLs, schema dates, bad hreflang codes, missing return link, canonical to the other language, broken schema, noindex) | PageAudit output per fixture | step 0.2 |
| `pages/expected_audit.json` | E1 (A1 rules; E2-062 A2 speed fixtures 34–37, `php artisan seo:audit-fixtures`; `--write` regenerates after a rule change, then Product reviews the diff) | **100 % Critical found, 0 false Critical** | step 0.3 |

Run: `php artisan test --group arabic-seo` (the parity test calls `node tests/arabic/parity.mjs`).
