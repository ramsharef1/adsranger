<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_reallocation_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('source_campaign');
            $t->string('target_campaign');
            $t->decimal('shifted_amount', 16, 4)->default(0);
            $t->decimal('monthly_cap', 16, 4)->default(0);
            $t->decimal('projected_spend', 16, 4)->default(0);
            $t->string('status', 24)->default('applied');
            $t->json('decision_meta')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_reallocation_logs');
    }
};