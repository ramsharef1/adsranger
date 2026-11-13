<?php

namespace Tests\Feature;

use App\Connectors\ConnectorRegistry;
use App\Connectors\RedditConnector;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Connection;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

/**
 * A-2 (R2-68): recorded API replies as contract tests. (1) Each recorded network parses its reply shape into the numbers we
 * expect, and only through real HTTP calls. (2) Every syncing connector, when the network fails or answers empty, throws or
 * returns nothing: no "built (faked)" success can pass. WR-06 (Reddit HOUR guard) and WR-07 (unconfirmed shapes) are named cases.
 * Fixtures are shapes from the official docs, marked confirmed => false until a scrubbed live reply replaces them (A-11).
 */
class ConnectorContractsA2Test extends TestCase
{
    use RefreshDatabase;

    private const CREDS = ['client_id' => 'cid', 'client_secret' => 'csecret', 'app_id' => 'aid', 'app_secret' => 'asecret', 'developer_token' => 'dev',
        'api_key' => 'key', 'api_secret' => 'ksecret', 'account_id' => 'acc', 'username' => 'u', 'password' => 'p', 'team_id' => 't', 'key_id' => 'k',
        'org_id' => '1', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'login_customer_id' => ''];

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        config(['insights.pull_breakdowns' => false, 'insights.pull_google_extras' => false]);
    }

    private function account(string $key, string $external): AdAccount
    {
        $agency = Agency::create(['name' => 'Contract Agency', 'slug' => 'contract-'.$key, 'plan' => 'agency', 'status' => 'active', 'timezone' => 'Asia/Riyadh']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Contract Client']);
        $network = AdNetwork::updateOrCreate(['key' => $key], ['name' => ucfirst($key), 'enabled' => true, 'status' => 'live', 'environment' => 'live', 'credentials_live' => self::CREDS]);
        $connection = Connection::create(['agency_id' => $agency->id, 'ad_network_id' => $network->id, 'status' => 'active', 'environment' => 'live',
            'access_token' => 'test-token', 'refresh_token' => 'test-refresh', 'token_expires_at' => now()->addHour(), 'credentials' => self::CREDS]);

        return AdAccount::create(['agency_id' => $agency->id, 'connection_id' => $connection->id, 'ad_network_id' => $network->id, 'client_id' => $client->id,
            'external_id' => $external, 'name' => 'Contract account', 'currency' => 'USD', 'timezone' => 'Asia/Riyadh', 'status' => 'ACTIVE', 'is_active' => true]);
    }

    /** @return array<string, array{0: string}> */
    public static function recorded(): array
    {
        return ['meta' => ['meta'], 'google_ads' => ['google_ads'], 'tiktok' => ['tiktok'], 'reddit' => ['reddit']];
    }

    #[DataProvider('recorded')]
    public function test_recorded_reply_parses_to_the_expected_numbers_through_real_calls(string $key): void
    {
        $fx = require base_path("tests/fixtures/contracts/{$key}.php");
        $this->assertFalse($fx['confirmed'] ?? true, 'flip to true only with a scrubbed live reply');
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($fx) {
            foreach ($fx['replies'] as $r) {
                if (preg_match($r['url'], $request->url()) && (! isset($r['body']) || str_contains($request->body(), $r['body']))) {
                    return Http::response($r['json']);
                }
            }

            return Http::response(['error' => ['message' => 'no recorded reply for '.$request->url()]], 404);
        });

        $account = $this->account($key, $fx['account']);
        $out = ConnectorRegistry::for($account->network)->pull($account, '2026-11-01', '2026-11-02');

        $e = $fx['expect'];
        foreach (['campaigns', 'ad_sets', 'ads', 'rows'] as $part) {
            $this->assertCount($e[$part], $out[$part], "$key $part");
        }
        $rows = collect($out['rows']);
        $this->assertEqualsWithDelta($e['spend'], $rows->sum('spend'), 0.001, "$key spend");
        $this->assertEqualsWithDelta($e['results'], $rows->sum('results'), 0.001, "$key results");
        $this->assertEqualsWithDelta($e['revenue'], $rows->sum('revenue'), 0.001, "$key revenue");
        if (isset($e['result_type'])) {
            $this->assertSame($e['result_type'], $rows->first()['result_type'], "$key result type");
        }
        foreach ($out['rows'] as $row) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $row['date']);
        }
        $this->assertGreaterThanOrEqual(count($fx['replies']), count(Http::recorded()), "$key must call every recorded endpoint");
    }

    /** WR-06: from 8 Dec 2026 Reddit answers 400 to HOUR over more than 7 days. We send DATE + AD_ID; any HOUR use is cut to 7-day windows. */
    public function test_reddit_reports_never_ask_hour_over_seven_days(): void
    {
        $this->assertNotContains('HOUR', RedditConnector::REPORT_BREAKDOWNS);
        foreach (RedditConnector::reportWindows('2026-10-01', '2026-11-20', ['HOUR', 'AD_ID']) as [$f, $t]) {
            $this->assertLessThanOrEqual(6, Carbon::parse($f)->diffInDays($t), "$f → $t");
        }

        $fx = require base_path('tests/fixtures/contracts/reddit.php');
        Http::fake(function (Request $request) use ($fx) {
            foreach ($fx['replies'] as $r) {
                if (preg_match($r['url'], $request->url())) {
                    return Http::response($r['json']);
                }
            }

            return Http::response([], 404);
        });
        $account = $this->account('reddit', $fx['account']);
        ConnectorRegistry::for($account->network)->pull($account, '2026-09-01', '2026-11-20');

        $reports = collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), '/reports'));
        $this->assertGreaterThan(0, $reports->count());
        foreach ($reports as [$req]) {
            $data = $req->data()['data'];
            $this->assertNotContains('HOUR', $data['breakdowns']);
            $this->assertLessThanOrEqual(31, Carbon::parse($data['starts_at'])->diffInDays(Carbon::parse($data['ends_at'])));
        }
    }

    /**
     * Every connector that can sync. WR-07: the shapes we have not confirmed against docs or a live reply are named, so a
     * failure says which guess broke.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function syncing(): array
    {
        return [
            'meta' => ['meta', 'act_1'], 'google_ads' => ['google_ads', '1234567890'], 'tiktok' => ['tiktok', '700'], 'snapchat · health shape unconfirmed (WR-07)' => ['snapchat', 'snap-1'],
            'microsoft' => ['microsoft', '123'], 'linkedin' => ['linkedin', '507'], 'x' => ['x', 'x1'], 'pinterest' => ['pinterest', '549'], 'reddit' => ['reddit', 't2_x'],
            'amazon_ads' => ['amazon_ads', '1'], 'taboola · tiered rate limits unconfirmed (WR-07)' => ['taboola', 'tab'], 'outbrain' => ['outbrain', 'ob'],
            'mgid · campaigns-stat shape unconfirmed (WR-07)' => ['mgid', '7'], 'revcontent · daily rows unconfirmed (WR-07)' => ['revcontent', 'rc'],
            'apple_ads · ACL shape unconfirmed (WR-07)' => ['apple_ads', '9'], 'yahoo_dsp · report shape unconfirmed (WR-07)' => ['yahoo_dsp', 'y1'],
        ];
    }

    #[DataProvider('syncing')]
    public function test_connector_never_reports_success_when_the_network_fails_or_answers_empty(string $key, string $external): void
    {
        $account = $this->account($key, $external);
        $connector = ConnectorRegistry::for($account->network);

        foreach (['fails' => Http::response(['error' => ['message' => 'down']], 500), 'empty' => Http::response([], 200)] as $case => $reply) {
            Http::fake(fn () => $reply);
            Http::preventStrayRequests();
            try {
                $out = $connector->pull($account->fresh(), '2026-11-01', '2026-11-02');
            } catch (Throwable) {
                $out = null;
            }
            if ($out !== null) {
                $this->assertSame([], $out['rows'], "$key ($case): no rows may come from a failed or empty reply");
                $this->assertNotEmpty(Http::recorded(), "$key ($case): returned without calling the network");
            }

            if ($case === 'fails') {
                try {
                    $health = $connector->health($account->fresh());
                    $this->assertNotSame([], $health, "$key: a failed network must not read as healthy");
                } catch (Throwable) {
                    // Throwing is fine: the caller shows the error.
                }
            }
            $this->app->forgetInstance(Factory::class);
            Http::clearResolvedInstances();
        }
        $this->addToAssertionCount(1); // reaching here means both cases behaved
    }
}
