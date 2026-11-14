<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-069 (module seo_audit, off): Search Console query/page telemetry, GA4 funnel rows for 3-way reconciliation,
 * and streamed server access log analyses for AI/search crawler budgets and orphan discovery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_search_console_rows', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('site_url', 255);
            $t->string('query', 300);
            $t->string('page_url', 500);
            $t->string('country', 12)->default('');
            $t->string('device', 24)->default('ALL');
            $t->unsignedInteger('clicks')->default(0);
            $t->unsignedInteger('impressions')->default(0);
            $t->decimal('ctr', 8, 4)->default(0);
            $t->decimal('position', 8, 2)->default(0);
            $t->date('stat_date');
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'stat_date'], 'gsc_rows_agency_client_date_idx');
            $t->index(['client_id', 'query'], 'gsc_rows_client_query_idx');
            $t->index(['client_id', 'page_url'], 'gsc_rows_client_page_idx');
        });

        Schema::create('client_ga4_funnel_rows', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('property_id', 64);
            $t->string('channel_group', 120);
            $t->string('landing_page', 500)->default('/');
            $t->unsignedInteger('sessions')->default(0);
            $t->unsignedInteger('engaged_sessions')->default(0);
            $t->unsignedInteger('add_to_carts')->default(0);
            $t->decimal('key_events', 12, 2)->default(0);
            $t->decimal('revenue', 14, 3)->default(0);
            $t->date('stat_date');
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'stat_date'], 'ga4_rows_agency_client_date_idx');
            $t->index(['client_id', 'property_id'], 'ga4_rows_client_prop_idx');
        });

        Schema::create('seo_log_analyses', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('filename', 255);
            $t->unsignedInteger('total_lines')->default(0);
            $t->json('bot_hits');
            $t->json('status_breakdown');
            $t->json('wasted_budget_paths');
            $t->json('orphan_candidates');
            $t->timestamp('analyzed_at')->nullable();
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'analyzed_at'], 'seo_logs_agency_client_analyzed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_log_analyses');
        Schema::dropIfExists('client_ga4_funnel_rows');
        Schema::dropIfExists('client_search_console_rows');
    }
};
