<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-070 (module seo_audit, off): Rank tracker & snapshots, topical cluster silos,
 * backlink records & Google Disavow exports, and local GBP multi-branch NAP & review drafts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_tracked_keywords', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('keyword', 255);
            $t->string('locale', 12)->default('ar');
            $t->string('location_code', 32)->default('SA');
            $t->string('device', 24)->default('mobile');
            $t->string('target_url', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'is_active'], 'seo_tracked_kw_agency_client_idx');
        });

        Schema::create('seo_rank_snapshots', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('tracked_keyword_id')->nullable()->constrained('seo_tracked_keywords')->nullOnDelete();
            $t->string('keyword', 255);
            $t->string('locale', 12)->default('ar');
            $t->string('location_code', 32)->default('SA');
            $t->string('device', 24)->default('mobile');
            $t->decimal('position', 8, 2)->nullable();
            $t->string('ranking_url', 500)->nullable();
            $t->json('serp_features')->nullable();
            $t->string('source', 24)->default('manual'); // gsc | serp_provider | manual
            $t->timestamp('checked_at');
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'checked_at'], 'seo_rank_snap_agency_client_checked_idx');
            $t->index(['client_id', 'keyword'], 'seo_rank_snap_client_kw_idx');
        });

        Schema::create('seo_topic_clusters', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('pillar_title', 255);
            $t->string('pillar_url', 500);
            $t->json('spokes')->nullable();
            $t->string('hreflang_pair_status', 32)->default('unchecked');
            $t->json('anchor_rules')->nullable();
            $t->text('brief_notes')->nullable();
            $t->timestamps();

            $t->index(['agency_id', 'client_id'], 'seo_topic_clusters_agency_client_idx');
        });

        Schema::create('seo_backlink_records', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('source_domain', 190);
            $t->string('source_url', 500);
            $t->string('target_url', 500);
            $t->string('anchor_text', 300)->nullable();
            $t->string('link_status', 16)->default('active'); // active | lost
            $t->boolean('is_flagged_disavow')->default(false);
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'link_status'], 'seo_backlinks_agency_client_status_idx');
            $t->index(['client_id', 'source_domain'], 'seo_backlinks_client_domain_idx');
        });

        Schema::create('seo_disavow_lists', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('name', 190);
            $t->json('entries');
            $t->longText('exported_txt');
            $t->timestamp('generated_at');
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'generated_at'], 'seo_disavow_agency_client_gen_idx');
        });

        Schema::create('seo_local_locations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('name_ar', 190);
            $t->string('name_en', 190);
            $t->string('phone_e164', 32);
            $t->string('address_ar', 300);
            $t->string('address_en', 300);
            $t->json('hours_json')->nullable();
            $t->string('gbp_location_id', 120)->nullable();
            $t->json('nap_issues')->nullable();
            $t->json('reviews_json')->nullable();
            $t->timestamps();

            $t->index(['agency_id', 'client_id'], 'seo_local_loc_agency_client_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_local_locations');
        Schema::dropIfExists('seo_disavow_lists');
        Schema::dropIfExists('seo_backlink_records');
        Schema::dropIfExists('seo_topic_clusters');
        Schema::dropIfExists('seo_rank_snapshots');
        Schema::dropIfExists('seo_tracked_keywords');
    }
};
