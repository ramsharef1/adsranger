<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Site audit A1 (FR-089, #183): sites per client with consent, audits, crawled pages, issues. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('url', 300);
            $table->string('host', 255);
            $table->foreignId('consent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'client_id', 'host']);
        });

        Schema::create('seo_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('seo_site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('queued'); // queued · running · done · failed · blocked · site_down
            $table->unsignedInteger('pages_planned')->default(0);
            $table->unsignedInteger('pages_done')->default(0);
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('info_count')->default(0);
            $table->string('error', 120)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['seo_site_id', 'created_at']);
        });

        Schema::create('seo_audit_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_audit_id')->constrained('seo_audits')->cascadeOnDelete();
            $table->string('url', 1000);
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('lang', 16)->nullable();
            $table->string('dir', 8)->nullable();
            $table->text('title')->nullable();
            $table->unsignedSmallInteger('title_px')->nullable();
            $table->text('h1')->nullable();
            $table->string('canonical', 1000)->nullable();
            $table->json('hreflang')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->boolean('noindex')->default(false);
            $table->unsignedInteger('response_ms')->nullable();
            $table->timestamps();

            $table->index('seo_audit_id');
        });

        Schema::create('seo_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_audit_id')->constrained('seo_audits')->cascadeOnDelete();
            $table->foreignId('seo_audit_page_id')->nullable()->constrained('seo_audit_pages')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('level', 12); // critical · warning · info (D-88)
            $table->string('detail', 500)->nullable();
            $table->timestamps();

            $table->index(['seo_audit_id', 'level', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_issues');
        Schema::dropIfExists('seo_audit_pages');
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('seo_sites');
    }
};
