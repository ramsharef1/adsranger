# ROY_CONTEXT — AdsRanger

## 1. Latest Sanitized Pulse
- **Timestamp**: 2026-11-09T14:52:15+00:00 (`8665779`)
- **Mail / Flags**: `mailer=log`, `SEO_TOOLS_PUBLIC=false`, `MODULE_VIEW_AS=false`, `SELF_SIGNUP=false`
- **Counts**: `agencies=0`, `users=0`, `clients=0`, `ad_accounts=0`, `alerts=0`, `waitlist_entries=0`, `jobs=0`, `failed_jobs=0`
- **Tech-Debt Triggers**: [TD-002 ACTIVE] MAIL_MAILER=log (outbound mails are not using live SMTP yet); [TD-001 OK] waitlist_entries=0/250 threshold

## 2. Active Tech-Debt Triggers
- **[TD-001]**: Migrate `EmailSuppressionGuard` cache keys to a dedicated `email_suppressions` table + Brevo bounce webhook when `waitlist_entries >= 250` or outbound volume exceeds `100/day`.
- **[TD-002]**: Cut over production `.env` `MAIL_MAILER` from `log` to `smtp` (Brevo) once SPF/DKIM/DMARC records are verified on `adsranger.com`.

## 3. Core Architecture & Guardrails
- **Stack**: Laravel 13, Filament, PostgreSQL/MySQL, Redis, Nginx (`72.62.132.138`).
- **Public SEO & Tools**: 10 free tools (`SEO_TOOLS_PUBLIC=true`), full `ADSR-042` (`B-01`–`B-12`) bilingual hubs, 40-term glossary, 328+ PHPUnit tests.

## 4. Recent Sprint Changes
- `2026-11-09` | `app/Console/Commands/RoyPulseCommand.php`, `app/Support/Mail/EmailSuppressionGuard.php`, `app/Providers/AppServiceProvider.php` | Added `roy:pulse` sanitized telemetry command and `MessageSending` suppression guard (`E2-046`).
