<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('vertical')->nullable();
            $t->string('market', 8)->nullable();
            $t->string('currency', 8)->default('USD');
            $t->decimal('monthly_budget', 14, 2)->nullable();
            $t->decimal('target_cpr', 14, 4)->nullable();
            $t->unsignedInteger('target_results')->nullable();
            $t->decimal('target_roas', 8, 2)->nullable();
            $t->boolean('hide_spend')->default(false);
            $t->string('status')->default('active');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'name']);
        });

        // Empty for a client = everyone in the workspace can see it. Owners and admins always can.
        Schema::create('client_user', function (Blueprint $t) {
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['client_id', 'user_id']);
        });

        Schema::table('ad_accounts', function (Blueprint $t) {
            $t->foreignId('client_id')->nullable()->after('ad_network_id')->constrained()->nullOnDelete();
            $t->timestamp('last_synced_at')->nullable();
            $t->string('sync_status')->nullable(); // ok | error | running
            $t->text('sync_error')->nullable();
            $t->unsignedSmallInteger('sync_failures')->default(0);
            $t->timestamp('backfilled_at')->nullable();
        });

        Schema::table('data_imports', function (Blueprint $t) {
            $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
        });

        foreach (['campaigns', 'ad_sets', 'ads'] as $tbl) {
            Schema::create($tbl, function (Blueprint $t) use ($tbl) {
                $t->id();
                $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
                $t->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
                if ($tbl !== 'campaigns') {
                    $t->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
                }
                if ($tbl === 'ads') {
                    $t->foreignId('ad_set_id')->nullable()->constrained()->cascadeOnDelete();
                }
                $t->string('external_id');
                $t->string('name')->nullable();
                $t->string('status')->nullable();
                if ($tbl === 'campaigns') {
                    $t->string('objective')->nullable();
                    $t->string('channel')->nullable();
                }
                if ($tbl === 'ad_sets') {
                    $t->string('optimization_goal')->nullable();
                    $t->string('custom_event')->nullable();
                }
                if ($tbl !== 'ads') {
                    $t->decimal('daily_budget', 14, 2)->nullable();
                    $t->decimal('lifetime_budget', 14, 2)->nullable();
                    $t->timestamp('start_time')->nullable();
                    $t->timestamp('end_time')->nullable();
                } else {
                    $t->text('thumbnail_url')->nullable();
                    $t->text('review_feedback')->nullable();
                }
                $t->timestamps();
                $t->unique(['ad_account_id', 'external_id']);
            });
        }

        Schema::table('daily_metrics', function (Blueprint $t) {
            $t->foreignId('client_id')->nullable()->after('agency_id')->constrained()->nullOnDelete();
            $t->string('level', 16)->default('campaign')->after('network'); // account | campaign | ad_set | ad
            $t->unsignedBigInteger('campaign_ref')->nullable();
            $t->unsignedBigInteger('ad_set_ref')->nullable();
            $t->unsignedBigInteger('ad_ref')->nullable();
            $t->unsignedBigInteger('reach')->default(0);
            $t->unsignedBigInteger('link_clicks')->default(0);
            $t->decimal('results', 16, 4)->default(0);
            $t->string('result_type', 48)->nullable();
            $t->decimal('calls', 14, 2)->default(0);
            $t->decimal('calls_confirmed', 14, 2)->default(0);
            $t->decimal('calls_20s', 14, 2)->default(0);
            $t->decimal('messages', 14, 2)->default(0);
            $t->decimal('leads', 14, 2)->default(0);
            $t->decimal('purchases', 14, 2)->default(0);
            $t->decimal('thruplays', 14, 2)->default(0);
            $t->decimal('landing_views', 14, 2)->default(0);
            $t->decimal('add_to_cart', 14, 2)->default(0);
            $t->decimal('checkouts', 14, 2)->default(0);
            $t->boolean('roas_eligible')->default(false);
            $t->json('breakdown')->nullable();
            $t->index(['ad_account_id', 'date']);
            $t->index(['client_id', 'date']);
            $t->index(['campaign_ref', 'date']);
        });

        Schema::create('sync_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $t->string('kind')->default('daily'); // daily | backfill | today | manual
            $t->date('from');
            $t->date('to');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unsignedInteger('rows')->default(0);
            $t->string('status')->default('queued'); // queued | running | ok | error
            $t->text('error')->nullable();
            $t->timestamps();
            $t->index(['ad_account_id', 'created_at']);
        });

        Schema::create('fx_rates', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->string('base', 8);
            $t->string('quote', 8);
            $t->decimal('rate', 20, 10);
            $t->unique(['date', 'base', 'quote']);
        });

        Schema::create('alert_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('rule_key');
            $t->boolean('enabled')->default(true);
            $t->json('thresholds')->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'rule_key']);
        });

        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('ad_account_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('rule_key');
            $t->string('severity', 8); // high | medium | low
            $t->string('entity_type', 16)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('entity_name')->nullable();
            $t->string('title');
            $t->text('detail')->nullable();
            $t->json('payload')->nullable();
            $t->string('fingerprint');
            $t->string('status', 12)->default('open'); // open | snoozed | resolved
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('snoozed_until')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'status']);
            $t->index(['agency_id', 'fingerprint']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->boolean('email_alerts')->default(false);
            $t->boolean('email_digest')->default(false);
        });

        Schema::table('agencies', function (Blueprint $t) {
            $t->date('digest_sent_on')->nullable();
            $t->date('alerts_run_on')->nullable();
        });

        // Pegged Gulf currencies (and JOD) so conversions work before the first FX pull.
        $pegs = ['USD' => 1, 'JOD' => 0.709, 'SAR' => 3.75, 'AED' => 3.6725, 'QAR' => 3.64, 'BHD' => 0.376, 'OMR' => 0.3845];
        foreach ($pegs as $q => $r) {
            DB::table('fx_rates')->insert(['date' => '2000-01-01', 'base' => 'USD', 'quote' => $q, 'rate' => $r]);
        }
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn(['digest_sent_on', 'alerts_run_on']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['email_alerts', 'email_digest']));
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('fx_rates');
        Schema::dropIfExists('sync_runs');
        Schema::table('daily_metrics', function (Blueprint $t) {
            $t->dropConstrainedForeignId('client_id');
            $t->dropIndex(['ad_account_id', 'date']);
            $t->dropIndex(['campaign_ref', 'date']);
            $t->dropColumn(['level', 'campaign_ref', 'ad_set_ref', 'ad_ref', 'reach', 'link_clicks', 'results', 'result_type', 'calls', 'calls_confirmed', 'calls_20s', 'messages', 'leads', 'purchases', 'thruplays', 'landing_views', 'add_to_cart', 'checkouts', 'roas_eligible', 'breakdown']);
        });
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_sets');
        Schema::dropIfExists('campaigns');
        Schema::table('data_imports', fn (Blueprint $t) => $t->dropConstrainedForeignId('client_id'));
        Schema::table('ad_accounts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('client_id');
            $t->dropColumn(['last_synced_at', 'sync_status', 'sync_error', 'sync_failures', 'backfilled_at']);
        });
        Schema::dropIfExists('client_user');
        Schema::dropIfExists('clients');
    }
};
