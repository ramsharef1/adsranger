<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-071 (module intelligence_hub, off): Search term rows for 1/2/3-gram wasted-spend stem mining,
 * and tokenized shopping product feeds enriched with SKU profit quadrants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_search_term_rows', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('network', 32)->default('google_ads');
            $t->string('campaign_name', 255)->default('');
            $t->string('search_term', 300);
            $t->unsignedInteger('impressions')->default(0);
            $t->unsignedInteger('clicks')->default(0);
            $t->decimal('spend', 14, 3)->default(0);
            $t->decimal('conversions', 12, 2)->default(0);
            $t->decimal('revenue', 14, 3)->default(0);
            $t->date('stat_date');
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'stat_date'], 'sem_terms_agency_client_date_idx');
            $t->index(['client_id', 'network'], 'sem_terms_client_network_idx');
        });

        Schema::create('client_product_feeds', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('feed_token', 64)->unique();
            $t->string('channel', 32)->default('google_merchant'); // google_merchant | meta_catalog | tiktok_catalog | snap_catalog
            $t->boolean('include_out_of_stock')->default(false);
            $t->json('rules')->nullable();
            $t->timestamp('last_generated_at')->nullable();
            $t->timestamps();

            $t->index(['agency_id', 'client_id'], 'product_feeds_agency_client_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_product_feeds');
        Schema::dropIfExists('client_search_term_rows');
    }
};
