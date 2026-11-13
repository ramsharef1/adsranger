<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI Workspace M1 slice 4: the agency's own API keys (#159 vault) and night shift v0 «المناوب الليلي». */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 20);                 // openai · anthropic · gemini
            $t->text('api_key');                        // encrypted; shown once, never again
            $t->string('last4', 8);
            $t->string('model', 60);
            $t->decimal('monthly_cap_usd', 10, 2);      // required before the AI box or night shift can run
            $t->string('spent_month', 7)->nullable();   // Y-m
            $t->decimal('spent_usd', 10, 4)->default(0); // estimate from token counts the provider returned
            $t->timestamp('tested_at')->nullable();
            $t->boolean('test_ok')->nullable();
            $t->string('test_message')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['agency_id', 'provider']);
        });
        Schema::create('ai_night_shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->unique()->constrained()->cascadeOnDelete();
            $t->boolean('enabled')->default(false);
            $t->unsignedTinyInteger('run_hour')->default(5);   // workspace time zone
            $t->string('provider', 20)->nullable();
            $t->json('client_ids');
            $t->date('last_run_on')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_night_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 20)->nullable();
            $t->string('status', 12);                   // ok · partial · failed
            $t->unsignedInteger('clients_checked')->default(0);
            $t->unsignedInteger('items_found')->default(0);
            $t->decimal('cost_usd', 10, 4)->default(0);
            $t->string('error')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'started_at']);
        });
        // Night shift items keep their D-88 level and campaign; the brain row carries them.
        Schema::table('ai_brain_items', fn (Blueprint $t) => $t->json('meta')->nullable()->after('cleanup'));
    }

    public function down(): void
    {
        Schema::table('ai_brain_items', fn (Blueprint $t) => $t->dropColumn('meta'));
        Schema::dropIfExists('ai_night_runs');
        Schema::dropIfExists('ai_night_shifts');
        Schema::dropIfExists('ai_keys');
    }
};
