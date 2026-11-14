<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_strategies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->json('conditions');
            $table->json('actions');
            $table->decimal('max_budget_delta_pct', 5, 2)->default(10.00);
            $table->string('execution_mode', 24)->default('dry_run');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_template')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'client_id', 'is_active'], 'auto_strat_client_active_idx');
        });

        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->constrained('automation_strategies')->cascadeOnDelete();
            $table->string('execution_mode', 24)->default('dry_run');
            $table->boolean('matched')->default(false);
            $table->json('evaluated_metrics');
            $table->json('proposed_actions');
            $table->string('status', 32)->default('no_match');
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['agency_id', 'client_id', 'ran_at'], 'auto_runs_client_ran_idx');
        });

        Schema::create('client_cohort_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('cohort_month', 7);
            $table->string('acquisition_key', 190);
            $table->unsignedInteger('customers_count')->default(0);
            $table->decimal('cac_amount', 14, 3)->default(0);
            $table->decimal('d30_ltv', 14, 3)->default(0);
            $table->decimal('d60_ltv', 14, 3)->default(0);
            $table->decimal('d90_ltv', 14, 3)->default(0);
            $table->decimal('d180_ltv', 14, 3)->default(0);
            $table->unsignedSmallInteger('payback_days')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'cohort_month', 'acquisition_key'], 'cohort_client_month_key_uniq');
            $table->index(['agency_id', 'client_id', 'cohort_month'], 'cohort_client_month_idx');
        });

        Schema::create('watched_landing_urls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->json('expected_pixels');
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->unsignedTinyInteger('redirect_hops')->default(0);
            $table->boolean('has_noindex')->default(false);
            $table->json('missing_pixels')->nullable();
            $table->boolean('broken_whatsapp_link')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'client_id'], 'watched_urls_client_idx');
        });

        Schema::create('watched_url_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('watched_url_id')->constrained('watched_landing_urls')->cascadeOnDelete();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('redirect_hops')->default(0);
            $table->boolean('has_noindex')->default(false);
            $table->json('missing_pixels')->nullable();
            $table->boolean('broken_whatsapp_link')->default(false);
            $table->string('error_code', 32)->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['watched_url_id', 'checked_at'], 'watched_checks_url_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watched_url_checks');
        Schema::dropIfExists('watched_landing_urls');
        Schema::dropIfExists('client_cohort_snapshots');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_strategies');
    }
};
