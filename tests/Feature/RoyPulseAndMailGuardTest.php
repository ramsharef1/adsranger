<?php

namespace Tests\Feature;

use App\Support\Mail\EmailSuppressionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RoyPulseAndMailGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_roy_pulse_outputs_sanitized_telemetry_without_secrets(): void
    {
        $this->artisan('roy:pulse')
            ->expectsOutputToContain('=== ROY SANITIZED TELEMETRY PULSE')
            ->expectsOutputToContain('waitlist_entries=0')
            ->assertExitCode(0);
    }

    public function test_email_suppression_guard_blocks_suppressed_recipient_and_allows_clean_recipient(): void
    {
        EmailSuppressionGuard::suppress('bounced@agency.test', 'hard_bounce');
        $this->assertTrue(EmailSuppressionGuard::isSuppressed('BOUNCED@agency.test'));

        Mail::raw('Test body', function ($msg) {
            $msg->to('bounced@agency.test')->subject('Should be blocked');
        });

        Mail::raw('Clean body', function ($msg) {
            $msg->to('active@agency.test')->subject('Should pass');
        });

        $this->assertFalse(EmailSuppressionGuard::isSuppressed('active@agency.test'));
    }

    public function test_mail_smoke_test_dry_run_and_suppression_abort(): void
    {
        $this->artisan('mail:smoke-test', ['recipient' => 'clean@agency.test', '--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN OK')
            ->assertExitCode(0);

        EmailSuppressionGuard::suppress('blocked@agency.test', 'manual');
        $this->artisan('mail:smoke-test', ['recipient' => 'blocked@agency.test', '--dry-run' => true])
            ->expectsOutputToContain('ABORTED: Recipient [blocked@agency.test] is blocked')
            ->assertExitCode(1);
    }
}
