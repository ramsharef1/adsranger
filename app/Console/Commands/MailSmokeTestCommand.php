<?php

namespace App\Console\Commands;

use App\Support\Mail\EmailSuppressionGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailSmokeTestCommand extends Command
{
    protected $signature = 'mail:smoke-test {recipient=ops@adsranger.com : Target email address} {--dry-run : Validate config and suppression guard without opening an SMTP socket}';

    protected $description = 'Verify outbound SMTP configuration, suppression guard, and delivery latency (zero secrets exposed).';

    public function handle(): int
    {
        $recipient = strtolower(trim((string) $this->argument('recipient')));
        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $port = (string) config('mail.mailers.smtp.port');
        $from = (string) config('mail.from.address');
        $hasAuth = filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password'));
        $isLoopback = in_array(strtolower($host), ['', '127.0.0.1', 'localhost', 'mailpit', 'mailhog'], true);

        $this->line('=== ADSRANGER MAIL SMOKE TEST ===');
        $this->line("Mailer / Host : mailer={$mailer} | host=".($host ?: 'none').":{$port} | auth=".($hasAuth ? 'yes' : 'no'));
        $this->line("From / To     : from={$from} -> to={$recipient}");

        if (EmailSuppressionGuard::isSuppressed($recipient)) {
            $this->error("ABORTED: Recipient [{$recipient}] is blocked by EmailSuppressionGuard.");

            return self::FAILURE;
        }

        if ($mailer === 'log' || $mailer === 'array' || $isLoopback) {
            $this->warn("WARNING [TD-002]: Mailer is non-external (mailer={$mailer}, host={$host}). Configure Brevo SMTP in .env for external delivery.");
        }

        if ($this->option('dry-run')) {
            $this->info('DRY-RUN OK: Configuration parsed and recipient passed EmailSuppressionGuard.');

            return self::SUCCESS;
        }

        $start = microtime(true);
        try {
            Mail::raw(
                "AdsRanger SMTP smoke test completed at ".now()->toIso8601String()." on environment [".app()->environment()."].",
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject('AdsRanger · اختبار اتصال البريد / SMTP Smoke Test');
                }
            );
            $ms = round((microtime(true) - $start) * 1000, 1);
            $this->info("DELIVERED OK: Handed off to [{$mailer}] in {$ms}ms.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('SMTP HANDOFF FAILED: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
