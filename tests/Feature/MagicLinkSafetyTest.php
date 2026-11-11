<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WorkspaceMagicLink;
use App\Support\WorkspaceOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** E1 review of Wave A.1 (D-249): a magic link signs in with no password or two-step code, so it is for new accounts only. */
class MagicLinkSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function entry(string $email): WaitlistEntry
    {
        return WaitlistEntry::create([
            'email' => $email, 'name' => 'Test', 'agency_name' => 'Test Agency', 'country' => 'JO', 'audience' => 'agency', 'lang' => 'en',
            'consent_at' => now(), 'consent_text_version' => WaitlistEntry::CONSENT_TEXT_VERSION,
        ]);
    }

    public function test_an_existing_account_never_gets_a_magic_link(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->expectException(\DomainException::class);
        WorkspaceOnboarding::provisionAndIssueMagicLink($this->entry('taken@example.com'), 'pro', '14d', $admin);
    }

    public function test_super_admins_and_two_step_users_are_refused_and_a_used_link_cannot_be_reissued(): void
    {
        $agency = Agency::create(['name' => 'A', 'slug' => 'a-ml', 'plan' => 'pro', 'status' => 'active']);
        $this->assertFalse(WorkspaceMagicLink::canIssueFor(User::factory()->create(['role' => 'super_admin'])));
        $this->assertFalse(WorkspaceMagicLink::canIssueFor(User::factory()->create(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])));

        $fresh = User::factory()->create();
        [$link] = WorkspaceMagicLink::issue($agency, $fresh);
        $link->forceFill(['used_at' => now()])->save();
        $this->expectException(\DomainException::class);
        WorkspaceMagicLink::issue($agency, $fresh);
    }

    public function test_the_link_needs_a_password_and_verifies_the_email_only_when_used(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $res = WorkspaceOnboarding::provisionAndIssueMagicLink($this->entry('new@example.com'), 'pro', '14d', $admin);
        $user = User::where('email', 'new@example.com')->first();
        $this->assertNull($user->email_verified_at);
        $token = basename($res['url']);

        $this->post('/magic-login/'.$token, ['name' => 'New'])->assertSessionHasErrors('password');
        $this->assertGuest();

        $this->post('/magic-login/'.$token, ['name' => 'New', 'password' => 'a-long-pass-123', 'password_confirmation' => 'a-long-pass-123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
