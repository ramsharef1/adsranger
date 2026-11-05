<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wave 4 Studio Slice 1: #134 Brand kits, #135 Asset library, #54 & #131 Creative briefs + client approval links. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_kits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('primary_color', 20)->default('#14B8A6');
            $t->string('secondary_color', 20)->default('#0B1020');
            $t->string('heading_font', 80)->default('IBM Plex Sans Arabic');
            $t->string('tone', 60)->default('professional');
            $t->string('default_dialect', 20)->default('gulf');
            $t->json('banned_words')->nullable();
            $t->string('logo_url', 500)->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });

        Schema::create('creative_assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('title', 160);
            $t->string('format', 24)->default('image'); // image | video | carousel
            $t->string('aspect_ratio', 12)->default('1:1'); // 1:1 | 9:16 | 16:9 | 4:5
            $t->string('file_url', 500)->nullable();
            $t->json('tags')->nullable();
            $t->json('networks')->nullable(); // ['meta','google_ads','tiktok','snapchat']
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });

        Schema::create('creative_briefs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('title', 160);
            $t->string('objective', 40)->default('lead'); // lead | purchase | message | link_click
            $t->string('dialect', 20)->default('gulf'); // gulf | levantine | egyptian | msa | en | fr
            $t->string('placement', 30)->default('feed'); // feed | story | search | native
            $t->string('hook', 250)->nullable();
            $t->text('primary_text');
            $t->string('headline', 160);
            $t->string('cta', 60)->default('Learn More');
            $t->unsignedSmallInteger('version')->default(1);
            $t->string('status', 24)->default('draft'); // draft | pending_approval | approved | changes_requested
            $t->string('share_token', 40)->unique();
            $t->text('client_feedback')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_briefs');
        Schema::dropIfExists('creative_assets');
        Schema::dropIfExists('brand_kits');
    }
};
