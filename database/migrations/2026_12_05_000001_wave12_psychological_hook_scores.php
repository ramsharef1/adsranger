<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychological_hook_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('dialect', 24)->default('gulf');
            $t->text('original_copy');
            $t->unsignedSmallInteger('overall_score')->default(0);
            $t->json('trigger_scores');
            $t->json('dialect_variants');
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psychological_hook_scores');
    }
};