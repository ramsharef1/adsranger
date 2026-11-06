<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_creative_radars', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('competitor_name');
            $t->string('network', 32)->default('meta');
            $t->string('dominant_format', 32)->default('reels_video');
            $t->unsignedSmallInteger('active_ads_count')->default(0);
            $t->json('top_hooks');
            $t->json('trend_signals');
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_creative_radars');
    }
};