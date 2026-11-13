<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// R2 (D-289): our own business view in /admin. Counts and our own notes only; no client data.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_ledger', function (Blueprint $t) { // EG-33 / NB-13
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('month', 7);
            $t->unsignedInteger('seats')->default(0);
            $t->unsignedInteger('clients')->default(0);
            $t->unsignedInteger('ad_accounts')->default(0);
            $t->unsignedInteger('reports_sent')->default(0);
            $t->unsignedInteger('link_views')->default(0);
            $t->unsignedInteger('ai_saves')->default(0);
            $t->unsignedInteger('night_runs')->default(0);
            $t->timestamps();
            $t->unique(['agency_id', 'month']);
        });
        Schema::create('upgrade_moments', function (Blueprint $t) { // PS-27 / NB-03 (log part)
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('feature', 60);
            $t->string('kind', 12); // limit · locked
            $t->unsignedInteger('hits')->default(0);
            $t->timestamp('last_at')->nullable();
            $t->unique(['agency_id', 'feature', 'kind']);
        });
        Schema::create('agency_notes', function (Blueprint $t) { // WS-16
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->date('next_check_on')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('trial_extension_requests', function (Blueprint $t) { // PS-32
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason', 300);
            $t->string('status', 10)->default('open'); // open · granted · declined
            $t->unsignedTinyInteger('days')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('ai_answer_checks', function (Blueprint $t) { // GR-28
            $t->id();
            $t->date('checked_on');
            $t->string('question', 300);
            $t->string('assistant', 40);
            $t->string('cited', 8); // yes · no · tool · page
            $t->string('note', 300)->nullable();
            $t->timestamps();
        });
        Schema::create('prospect_log', function (Blueprint $t) { // NB-48
            $t->id();
            $t->string('name', 160);
            $t->string('country', 2)->nullable();
            $t->string('outcome', 8)->default('open'); // open · won · lost
            $t->string('reason', 300)->nullable();
            $t->date('logged_on');
            $t->timestamps();
        });
        Schema::table('waitlist_entries', function (Blueprint $t) { // GR-01 / GR-07
            $t->string('origin_landing', 300)->nullable();
            $t->string('origin_tool', 80)->nullable();
            $t->string('utm_source', 80)->nullable();
            $t->string('utm_medium', 80)->nullable();
            $t->string('utm_campaign', 80)->nullable();
            $t->string('ref_code', 12)->nullable()->unique();
            $t->string('referred_by', 12)->nullable();
        });
        Schema::table('client_onboarding_links', function (Blueprint $t) { // DB-05 client intake (shown once the legal line is set)
            $t->json('intake')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_onboarding_links', function (Blueprint $t) {
            $t->dropColumn('intake');
        });
        Schema::table('waitlist_entries', function (Blueprint $t) {
            $t->dropUnique(['ref_code']);
            $t->dropColumn(['origin_landing', 'origin_tool', 'utm_source', 'utm_medium', 'utm_campaign', 'ref_code', 'referred_by']);
        });
        foreach (['prospect_log', 'ai_answer_checks', 'trial_extension_requests', 'agency_notes', 'upgrade_moments', 'usage_ledger'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
