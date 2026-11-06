<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blended_roas_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->date('metric_date');
            $t->decimal('total_spend', 16, 4)->default(0);
            $t->decimal('total_revenue', 16, 4)->default(0);
            $t->decimal('total_conversions', 16, 4)->default(0);
            $t->decimal('blended_roas', 12, 4)->default(0);
            $t->decimal('marketing_efficiency_ratio', 12, 4)->default(0);
            $t->json('channel_breakdown')->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'client_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blended_roas_metrics');
    }
};