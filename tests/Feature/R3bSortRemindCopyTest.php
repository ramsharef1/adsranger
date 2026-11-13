<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Filament\Pages\ImportReviewPage;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Support\ClientSetupCopy;
use App\Support\Import\CampaignMatcher;
use App\Support\Import\GoalGuess;
use App\Support\Import\ImportReview;
use App\Support\Import\ImportSheet;
use App\Support\MetricsImporter;
use App\Support\UploadReminders;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R3 part 2 (D-290, D-296): PS-02 client suggestions, PS-03 goal guesses, PS-08 upload reminder, PS-13 copy client setup. */
class R3bSortRemindCopyTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create(['name' => 'Souq Ads', 'slug' => 'souq-'.uniqid(), 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'currency' => 'SAR']);
        $this->owner = User::factory()->create();
        $this->agency->members()->attach($this->owner->id, ['role' => 'owner']);
    }

    private function client(string $name): Client
    {
        return Client::create(['agency_id' => $this->agency->id, 'name' => $name, 'status' => 'active']);
    }

    public function test_campaign_names_suggest_a_client_across_scripts_and_only_when_clear(): void
    {
        $noor = $this->client('عيادة النور');
        $rayan = $this->client('Al Rayan Furniture');
        $clients = [$noor->id => $noor->name, $rayan->id => $rayan->name];

        $this->assertSame($noor->id, CampaignMatcher::suggest('عيادة النور - رمضان', $clients));
        $this->assertSame($noor->id, CampaignMatcher::suggest('NOOR_LEADS_KSA', $clients));
        $this->assertSame($rayan->id, CampaignMatcher::suggest('RAYAN | Sales | UAE', $clients));
        $this->assertNull(CampaignMatcher::suggest('Ramadan leads KSA', $clients), 'no client words, no guess');
    }

    public function test_goal_guesses_from_indicator_and_name(): void
    {
        $this->assertSame('lead', GoalGuess::fromIndicator('actions:lead'));
        $this->assertSame('purchase', GoalGuess::fromIndicator('actions:offsite_conversion.fb_pixel_purchase'));
        $this->assertSame('message', GoalGuess::fromIndicator('actions:onsite_conversion.messaging_conversation_started_7d'));
        $this->assertSame('message', GoalGuess::fromName('عيادة النور - رسائل واتساب'));
        $this->assertSame('lead', GoalGuess::fromName('NOOR_LEADS_KSA'));
        $this->assertSame('purchase', GoalGuess::fromName('متجر الريان - طلبات'));
        $this->assertNull(GoalGuess::fromName('Spring 2026'));
    }

    public function test_review_page_prefills_suggestions_and_saves_only_on_confirm(): void
    {
        $noor = $this->client('عيادة النور');
        $csv = "Day,Campaign,Cost,Conversions\n2026-11-01,NOOR_LEADS_KSA,100,4\n2026-11-02,NOOR_LEADS_KSA,50,2\n2026-11-01,Unknown brand push,30,1\n";
        MetricsImporter::importRows($this->agency, $this->owner, 'google_ads', ImportSheet::fromCsv($csv), 'g.csv');

        $pending = collect(ImportReview::pending($this->agency))->keyBy('campaign');
        $this->assertSame($noor->id, $pending['NOOR_LEADS_KSA']['suggest_client']);
        $this->assertSame('lead', $pending['NOOR_LEADS_KSA']['suggest_goal']);
        $this->assertNull($pending['Unknown brand push']['suggest_client']);
        $this->assertSame(0, DailyMetric::whereNotNull('client_id')->count(), 'nothing assigned before Save');

        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);
        Livewire::test(ImportReviewPage::class)->assertSee('NOOR_LEADS_KSA')->call('save');

        $rows = DailyMetric::where('campaign_name', 'NOOR_LEADS_KSA')->get();
        $this->assertTrue($rows->every(fn ($m) => $m->client_id === $noor->id && $m->result_type === 'lead'));
        $this->assertEqualsWithDelta(6.0, (float) $rows->sum('leads'), 0.001, 'results count as leads once the goal is lead');
        $this->assertNull(DailyMetric::where('campaign_name', 'Unknown brand push')->value('client_id'));
    }

    public function test_upload_stale_alert_after_7_days_and_one_push_per_7_day_step(): void
    {
        $c = $this->client('عيادة النور');
        MetricsImporter::importRows($this->agency, $this->owner, 'meta', ImportSheet::fromCsv("Day,Campaign,Spend\n2026-11-01,Noor,10\n"), 'm.csv', 'file', $c->id);

        $this->travel(6)->days();
        AlertEngine::run($this->agency, now()->toDateString());
        $this->assertSame(0, Alert::where('rule_key', 'upload_stale')->count());

        $this->travel(2)->days();
        AlertEngine::run($this->agency, now()->toDateString());
        $al = Alert::where('rule_key', 'upload_stale')->firstOrFail();
        $this->assertSame(8, $al->payload['days']);
        $this->assertStringContainsString('Last upload 8 days ago', $al->detail);

        config(['push.enabled' => true]);
        $this->assertSame(0, UploadReminders::run(), 'no member has a phone subscription, so nothing is sent');

        // A fresh upload resolves it.
        MetricsImporter::importRows($this->agency, $this->owner, 'meta', ImportSheet::fromCsv("Day,Campaign,Spend\n2026-11-09,Noor,12\n"), 'm2.csv', 'file', $c->id);
        AlertEngine::run($this->agency, now()->toDateString());
        $this->assertSame('resolved', $al->fresh()->status);
    }

    public function test_copy_setup_copies_targets_and_schedule_but_not_recipients(): void
    {
        $from = $this->client('عيادة النور');
        $from->forceFill(['target_cpr' => 12.5, 'target_roas' => 3, 'vertical' => 'clinic', 'market' => 'SA'])->save();
        ReportSchedule::create(['agency_id' => $this->agency->id, 'client_id' => $from->id, 'monthly_day' => 3, 'weekly_day' => null, 'lang' => 'ar',
            'recipients' => ['owner@noor.test'], 'active' => true]);
        $to = $this->client('عيادة الشفاء');
        $other = Agency::create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'currency' => 'SAR']);
        $foreign = Client::create(['agency_id' => $other->id, 'name' => 'X', 'status' => 'active']);

        $this->actingAs($this->owner);
        $n = ClientSetupCopy::copy($from, [$to->id, $foreign->id], ['targets', 'schedule']);

        $this->assertSame(1, $n, 'another workspace\'s client is never touched');
        $this->assertEqualsWithDelta(12.5, $to->fresh()->target_cpr, 0.001);
        $this->assertSame('clinic', $to->fresh()->vertical);
        $s = ReportSchedule::where('client_id', $to->id)->firstOrFail();
        $this->assertSame([3, [], false], [(int) $s->monthly_day, $s->recipients, (bool) $s->active]);
        $this->assertNull($foreign->fresh()->target_cpr);
    }
}
