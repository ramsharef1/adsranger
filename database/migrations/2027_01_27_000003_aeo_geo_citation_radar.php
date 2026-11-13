<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-065 AEO / GEO brand citation & AI readiness radar (module seo_audit, off).
 * A check is either 'static_readiness' (our own fetches: robots.txt, /llms.txt, JSON-LD, AR / EN parity; readiness_score set)
 * or 'byo_probe' (answers from the workspace's own AI key; citations set, readiness_score null). No citation rate exists without probes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aeo_brand_monitors', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('brand_name_ar', 120)->nullable();
            $t->string('brand_name_en', 120)->nullable();
            $t->string('primary_domain', 190);
            $t->json('tracked_prompts')->nullable();
            $t->timestamps();

            $t->unique(['agency_id', 'client_id'], 'aeo_brand_monitors_client_unique');
        });

        Schema::create('aeo_citation_checks', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('monitor_id')->constrained('aeo_brand_monitors')->cascadeOnDelete();
            $t->string('source', 24); // static_readiness | byo_probe
            $t->unsignedTinyInteger('readiness_score')->nullable();
            $t->json('signals')->nullable();
            $t->json('citations')->nullable();
            $t->timestamp('checked_at');
            $t->timestamps();

            $t->index(['monitor_id', 'source', 'checked_at'], 'aeo_citation_checks_latest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aeo_citation_checks');
        Schema::dropIfExists('aeo_brand_monitors');
    }
};
