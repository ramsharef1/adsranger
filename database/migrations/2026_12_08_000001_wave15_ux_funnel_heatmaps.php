<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ux_funnel_heatmaps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('landing_page_url');
            $t->unsignedInteger('sessions_analyzed')->default(0);
            $t->decimal('avg_scroll_depth', 5, 2)->default(0);
            $t->decimal('drop_off_rate', 5, 2)->default(0);
            $t->string('primary_leak_step', 64)->nullable();
            $t->json('friction_Zones');
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ux_funnel_heatmaps');
    }
};