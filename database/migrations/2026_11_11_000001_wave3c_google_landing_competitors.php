<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wave 3c: search terms + negatives (#46), impression share + Quality Score (#47), PMax asset groups (#48), landing pages (#49), competitors (#50). */
return new class extends Migration
{
    public function up(): void
    {
        $base = function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
        };

        Schema::create('google_campaign_daily', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('campaign_external', 40);
            $t->date('date');
            foreach (['search_is', 'lost_rank', 'lost_budget', 'top_is', 'abs_top_is'] as $c) {
                $t->decimal($c, 6, 4)->nullable();
            }
            $t->unique(['ad_account_id', 'campaign_external', 'date']);
            $t->index(['client_id', 'date']);
        });

        Schema::create('search_terms', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('campaign_external', 40);
            $t->string('ad_group_external', 40)->nullable();
            $t->string('term', 300);
            $t->string('status', 20)->nullable(); // ADDED | EXCLUDED | ADDED_EXCLUDED | NONE
            $t->date('date');
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->decimal('cost', 14, 4)->default(0);
            $t->decimal('conversions', 14, 2)->default(0);
            $t->decimal('conv_value', 14, 2)->default(0);
            $t->string('currency', 8)->nullable();
            $t->index(['client_id', 'date']);
            $t->index(['ad_account_id', 'date']);
        });

        Schema::create('google_keywords', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('campaign_external', 40);
            $t->string('ad_group_external', 40);
            $t->string('criterion_id', 40);
            $t->string('text', 300);
            $t->string('match_type', 20)->nullable();
            $t->string('status', 20)->nullable();
            $t->unsignedTinyInteger('qs')->nullable();
            $t->string('creative_qs', 20)->nullable();
            $t->string('landing_qs', 20)->nullable();
            $t->string('ctr_qs', 20)->nullable();
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->decimal('cost', 14, 4)->default(0);
            $t->decimal('conversions', 14, 2)->default(0);
            $t->timestamp('snapshot_at')->nullable();
            $t->unique(['ad_account_id', 'ad_group_external', 'criterion_id']);
            $t->index(['client_id']);
        });

        Schema::create('google_asset_groups', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('campaign_external', 40);
            $t->string('campaign_name')->nullable();
            $t->string('asset_group_id', 40);
            $t->string('name')->nullable();
            $t->string('status', 20)->nullable();
            $t->string('ad_strength', 20)->nullable();
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->decimal('cost', 14, 4)->default(0);
            $t->decimal('conversions', 14, 2)->default(0);
            $t->decimal('conv_value', 14, 2)->default(0);
            $t->json('assets')->nullable();
            $t->timestamp('snapshot_at')->nullable();
            $t->unique(['ad_account_id', 'asset_group_id']);
        });

        Schema::create('negative_keyword_actions', function (Blueprint $t) use ($base) {
            $base($t);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('campaign_external', 40);
            $t->string('text', 300);
            $t->string('match_type', 10);
            $t->string('status', 10); // ok | error | demo
            $t->text('error')->nullable();
            $t->timestamps();
        });

        Schema::create('landing_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $t->string('url', 1000);
            $t->char('url_hash', 40);
            $t->string('source', 10)->default('manual'); // google | meta | manual
            $t->string('state', 12)->default('unchecked'); // ok | slow | redirects | insecure | broken | blocked | unchecked
            $t->unsignedSmallInteger('status_code')->nullable();
            $t->unsignedInteger('ms')->nullable();
            $t->unsignedInteger('bytes')->nullable();
            $t->unsignedTinyInteger('redirects')->nullable();
            $t->string('final_url', 1000)->nullable();
            $t->string('error', 300)->nullable();
            $t->unsignedTinyInteger('psi_score')->nullable();
            $t->unsignedInteger('clicks_30d')->default(0);
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'url_hash']);
            $t->index(['agency_id', 'state']);
        });

        Schema::create('competitors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('facebook_page_id', 40)->nullable();
            $t->string('website')->nullable();
            $t->json('countries')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });
    }

    public function down(): void
    {
        foreach (['competitors', 'landing_pages', 'negative_keyword_actions', 'google_asset_groups', 'google_keywords', 'search_terms', 'google_campaign_daily'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
