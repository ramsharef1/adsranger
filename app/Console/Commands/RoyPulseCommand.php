<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoyPulseCommand extends Command
{
    protected $signature = 'roy:pulse {--write : Update ROY_CONTEXT.md snapshot}';

    protected $description = 'Output sanitized architecture, queue, and tech-debt telemetry pulse (zero secrets/PII).';

    public function handle(): int
    {
        $tables = ['agencies', 'users', 'clients', 'ad_accounts', 'alerts', 'waitlist_entries', 'jobs', 'failed_jobs'];
        $counts = [];
        foreach ($tables as $t) {
            $counts[$t] = Schema::hasTable($t) ? DB::table($t)->count() : 0;
        }

        $mailer = (string) config('mail.default');
        $mailHost = (string) config('mail.mailers.smtp.host');
        $seoPublic = config('seo.tools_public', env('SEO_TOOLS_PUBLIC', false)) ? 'true' : 'false';
        $viewAs = config('modules.view_as', env('MODULE_VIEW_AS', false)) ? 'true' : 'false';
        $selfSignup = config('modules.self_signup', env('SELF_SIGNUP_ENABLED', false)) ? 'true' : 'false';
        $head = trim((string) shell_exec('git rev-parse --short HEAD 2>/dev/null')) ?: 'unknown';

        $triggers = [];
        if ($mailer === 'log' || $mailer === 'array') {
            $triggers[] = "[TD-002 ACTIVE] MAIL_MAILER={$mailer} (outbound mails are not using live SMTP yet)";
        }
        if ($counts['failed_jobs'] > 0) {
            $triggers[] = "[ALERT] failed_jobs={$counts['failed_jobs']} (inspect queue failures)";
        }
        if ($counts['waitlist_entries'] >= 250) {
            $triggers[] = "[TD-001 CROSSED] waitlist_entries={$counts['waitlist_entries']} >= 250 (migrate suppression cache to DB + Brevo webhook)";
        } else {
            $triggers[] = "[TD-001 OK] waitlist_entries={$counts['waitlist_entries']}/250 threshold";
        }

        $this->line("=== ROY SANITIZED TELEMETRY PULSE (@ {$head}) ===");
        $this->line('Environment : '.app()->environment().' | PHP '.PHP_VERSION.' | DB '.config('database.default'));
        $this->line("Mail Config : mailer={$mailer} | smtp_host=".($mailHost ?: 'none'));
        $this->line("Flags       : SEO_TOOLS_PUBLIC={$seoPublic} | MODULE_VIEW_AS={$viewAs} | SELF_SIGNUP={$selfSignup}");
        $this->line('Row Counts  : '.collect($counts)->map(fn ($v, $k) => "{$k}={$v}")->implode(' · '));
        $this->line('Triggers    : '.implode(' | ', $triggers));

        if ($this->option('write')) {
            $ctxFile = base_path('ROY_CONTEXT.md');
            $existing = is_file($ctxFile) ? file_get_contents($ctxFile) : "# ROY_CONTEXT — AdsRanger\n\n## 4. Recent Sprint Changes\n";
            $pulseBlock = "## 1. Latest Sanitized Pulse\n"
                ."- **Timestamp**: ".now()->toIso8601String()." (`{$head}`)\n"
                ."- **Mail / Flags**: `mailer={$mailer}`, `SEO_TOOLS_PUBLIC={$seoPublic}`, `MODULE_VIEW_AS={$viewAs}`, `SELF_SIGNUP={$selfSignup}`\n"
                ."- **Counts**: ".collect($counts)->map(fn ($v, $k) => "`{$k}={$v}`")->implode(', ')."\n"
                ."- **Tech-Debt Triggers**: ".implode('; ', $triggers)."\n\n";
            if (str_contains($existing, '## 1. Latest Sanitized Pulse')) {
                $existing = preg_replace('/## 1\. Latest Sanitized Pulse.*?(?=## \d|$)/s', $pulseBlock, $existing, 1);
            } else {
                $existing = "# ROY_CONTEXT — AdsRanger\n\n".$pulseBlock.preg_replace('/^# ROY_CONTEXT[^\n]*\n+/m', '', $existing);
            }
            file_put_contents($ctxFile, $existing);
            $this->info('Updated ROY_CONTEXT.md');
        }

        return self::SUCCESS;
    }
}
