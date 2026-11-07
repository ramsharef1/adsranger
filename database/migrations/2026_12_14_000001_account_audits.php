<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #173 Day-1 account audit (FR-016): one run per client after the first sync and on demand; findings ranked by money at risk. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('trigger', 20);
            $t->string('currency', 3);
            $t->decimal('money_at_risk', 14, 2)->default(0);
            $t->unsignedSmallInteger('findings_count')->default(0);
            $t->date('window_from');
            $t->date('window_to');
            $t->foreignId('ran_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ran_at');
            $t->timestamps();
            $t->index(['agency_id', 'client_id', 'ran_at']);
        });

        Schema::create('audit_findings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('rank');
            $t->string('check_key', 40);
            $t->string('severity', 10);
            $t->string('fingerprint', 120);
            $t->string('entity_type', 20)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('entity_name')->nullable();
            $t->string('network', 40)->nullable();
            $t->decimal('money_at_risk', 14, 2)->nullable();
            $t->decimal('results_at_risk', 12, 2)->nullable();
            $t->json('params')->nullable();
            $t->string('fix_url', 500)->nullable();
            $t->timestamp('dismissed_at')->nullable();
            $t->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['audit_id', 'rank']);
            $t->index(['client_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audits');
    }
};
