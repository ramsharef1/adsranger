<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wave 3a: annual budget plan (#45), daily health score history (#35). */
return new class extends Migration
{
    public function up(): void
    {
        // One row per client per month. Empty month = falls back to the client's monthly budget / targets.
        Schema::create('budget_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->date('month'); // first day of the month
            $t->decimal('budget', 14, 2)->nullable();
            $t->unsignedInteger('target_results')->nullable();
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->unique(['client_id', 'month']);
            $t->index(['agency_id', 'month']);
        });

        Schema::create('client_health', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->unsignedTinyInteger('score')->nullable();
            $t->json('parts')->nullable();
            $t->timestamps();
            $t->unique(['client_id', 'date']);
            $t->index(['agency_id', 'date']);
        });

        Schema::table('clients', function (Blueprint $t) {
            $t->unsignedTinyInteger('health_score')->nullable();
            $t->json('health_parts')->nullable();
            $t->timestamp('health_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn(['health_score', 'health_parts', 'health_at']));
        Schema::dropIfExists('client_health');
        Schema::dropIfExists('budget_plans');
    }
};
