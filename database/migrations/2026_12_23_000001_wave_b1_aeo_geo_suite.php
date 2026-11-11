<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aeo_tracked_prompts')) {
            Schema::create('aeo_tracked_prompts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->string('prompt_text', 500);
                $table->string('locale', 8)->default('ar');
                $table->string('funnel_stage', 32)->default('consideration');
                $table->json('competitors_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['agency_id', 'client_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('aeo_citation_runs')) {
            Schema::create('aeo_citation_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('aeo_tracked_prompt_id')->constrained('aeo_tracked_prompts')->cascadeOnDelete();
                $table->string('engine', 32);
                $table->string('mode', 24)->default('deterministic');
                $table->boolean('client_mentioned')->default(false);
                $table->unsignedSmallInteger('client_position')->nullable();
                $table->string('sentiment', 24)->default('neutral');
                $table->json('competitor_mentions_json')->nullable();
                $table->json('cited_urls_json')->nullable();
                $table->text('response_excerpt')->nullable();
                $table->timestamp('checked_at');
                $table->timestamps();

                $table->index(['agency_id', 'client_id', 'engine']);
            });
        }

        if (! Schema::hasTable('aeo_domain_audits')) {
            Schema::create('aeo_domain_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->string('domain_url', 500);
                $table->json('robots_bot_matrix_json')->nullable();
                $table->longText('llms_txt_markdown')->nullable();
                $table->longText('schema_json_ld')->nullable();
                $table->json('internal_link_orphans_json')->nullable();
                $table->json('snippet_rewrites_json')->nullable();
                $table->timestamp('audited_at');
                $table->timestamps();

                $table->index(['agency_id', 'client_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aeo_domain_audits');
        Schema::dropIfExists('aeo_citation_runs');
        Schema::dropIfExists('aeo_tracked_prompts');
    }
};
