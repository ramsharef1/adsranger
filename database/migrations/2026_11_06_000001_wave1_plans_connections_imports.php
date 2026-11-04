<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->decimal('price_usd_month', 8, 2)->nullable();
            $t->unsignedInteger('seats')->default(3);
            $t->unsignedInteger('ad_accounts')->default(10);
            $t->unsignedInteger('clients')->default(10);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('ad_network_plan', function (Blueprint $t) {
            $t->foreignId('ad_network_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->primary(['ad_network_id', 'plan_id']);
        });

        Schema::create('agency_network_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ad_network_id')->constrained()->cascadeOnDelete();
            $t->string('mode'); // allow | deny
            $t->timestamps();
            $t->unique(['agency_id', 'ad_network_id', 'mode']);
        });

        Schema::create('agency_network_users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ad_network_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unique(['agency_id', 'ad_network_id', 'user_id']);
        });

        Schema::table('ad_networks', function (Blueprint $t) {
            $t->timestamp('last_tested_at')->nullable();
            $t->text('last_test_result')->nullable();
        });
        Schema::table('agencies', fn (Blueprint $t) => $t->timestamp('trial_ends_at')->nullable());
        Schema::table('users', fn (Blueprint $t) => $t->timestamp('last_login_at')->nullable());

        Schema::create('connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ad_network_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('active'); // active | error | disconnected
            $t->string('environment')->default('live');
            $t->string('external_user_id')->nullable();
            $t->string('external_user_name')->nullable();
            $t->text('access_token')->nullable();
            $t->text('refresh_token')->nullable();
            $t->timestamp('token_expires_at')->nullable();
            $t->text('scopes')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
        });

        Schema::create('ad_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ad_network_id')->constrained()->cascadeOnDelete();
            $t->string('external_id');
            $t->string('name')->nullable();
            $t->string('currency', 8)->nullable();
            $t->string('timezone')->nullable();
            $t->string('status')->nullable();
            $t->boolean('is_active')->default(false);
            $t->timestamps();
            $t->unique(['connection_id', 'external_id']);
        });

        Schema::create('data_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('network');
            $t->string('source')->default('file'); // file | sheet
            $t->string('filename')->nullable();
            $t->unsignedInteger('rows')->default(0);
            $t->string('status')->default('done'); // done | failed
            $t->text('error')->nullable();
            $t->json('mapping')->nullable();
            $t->timestamps();
        });

        Schema::create('daily_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ad_account_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('data_import_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('network');
            $t->date('date');
            $t->string('campaign_id')->nullable();
            $t->string('campaign_name')->nullable();
            $t->decimal('spend', 16, 4)->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->decimal('conversions', 16, 4)->default(0);
            $t->decimal('revenue', 16, 4)->default(0);
            $t->string('currency', 8)->nullable();
            $t->string('source')->default('import');
            $t->timestamps();
            $t->index(['agency_id', 'date']);
        });

        // Default plans (editable in /admin → Plans).
        $plans = [
            ['key' => 'starter', 'name' => 'Starter', 'seats' => 3, 'ad_accounts' => 10, 'clients' => 10, 'sort' => 1],
            ['key' => 'pro', 'name' => 'Pro', 'seats' => 10, 'ad_accounts' => 50, 'clients' => 50, 'sort' => 2],
            ['key' => 'agency', 'name' => 'Agency', 'seats' => 50, 'ad_accounts' => 300, 'clients' => 300, 'sort' => 3],
        ];
        foreach ($plans as $p) {
            DB::table('plans')->insert($p + ['created_at' => now(), 'updated_at' => now()]);
        }

        // Make sure every network in the catalogue has a row (adds GA4).
        foreach (config('ad_networks', []) as $key => $n) {
            if (! DB::table('ad_networks')->where('key', $key)->exists()) {
                DB::table('ad_networks')->insert(['key' => $key, 'name' => $n['name'], 'category' => $n['category'], 'stage' => $n['stage'], 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // Networks per plan, following the spec's tiers.
        $tiers = [
            'meta' => ['starter', 'pro', 'agency'], 'google_ads' => ['starter', 'pro', 'agency'],
            'ga4' => ['pro', 'agency'], 'x' => ['pro', 'agency'],
            'tiktok' => ['agency'], 'snapchat' => ['agency'], 'microsoft' => ['agency'],
            'linkedin' => ['agency'], 'google_merchant' => ['agency'],
        ];
        foreach ($tiers as $net => $keys) {
            $nid = DB::table('ad_networks')->where('key', $net)->value('id');
            foreach ($keys as $k) {
                $pid = DB::table('plans')->where('key', $k)->value('id');
                if ($nid && $pid) {
                    DB::table('ad_network_plan')->insert(['ad_network_id' => $nid, 'plan_id' => $pid]);
                }
            }
        }

        DB::table('agencies')->whereNull('trial_ends_at')->update(['trial_ends_at' => now()->addDays(14)]);
    }

    public function down(): void
    {
        foreach (['daily_metrics', 'data_imports', 'ad_accounts', 'connections', 'agency_network_users', 'agency_network_overrides', 'ad_network_plan', 'plans'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('ad_networks', fn (Blueprint $t) => $t->dropColumn(['last_tested_at', 'last_test_result']));
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('trial_ends_at'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_login_at'));
    }
};
