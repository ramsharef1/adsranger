<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #106 scheduled email reports: one schedule per client, a log of every send (one row per recipient and period,
 * which is what makes a send happen once), and per-recipient unsubscribes. New tables only (safe on PostgreSQL 13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('monthly_day')->nullable(); // 1–28; null = no monthly email
            $t->unsignedTinyInteger('weekly_day')->nullable();  // ISO 1 = Monday … 7 = Sunday; null = no weekly email
            $t->string('lang', 2)->default('ar');
            $t->json('recipients');
            $t->boolean('attach_pdf')->default(true);
            $t->boolean('include_link')->default(true);
            $t->foreignId('client_link_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamps();
            $t->index(['active', 'agency_id']);
        });

        Schema::create('report_sends', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('report_schedule_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10);   // monthly | weekly
            $t->string('period', 10); // 2026-10 (month reported) | 2026-W45 (week sent)
            $t->string('email');
            $t->string('status', 12)->default('queued'); // queued | sent | skipped | failed
            $t->string('error', 500)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['report_schedule_id', 'kind', 'period', 'email']);
            $t->index(['client_id', 'created_at']);
        });

        Schema::create('report_unsubscribes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_schedule_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['report_schedule_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_unsubscribes');
        Schema::dropIfExists('report_sends');
        Schema::dropIfExists('report_schedules');
    }
};
