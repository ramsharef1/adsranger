<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-066 creative fatigue & peak-season decay guard (module studio, off). One open row per (client, ad) while the ad
 * stays fatigued or on watch; resolved_at is set when it recovers. Numbers are copied from daily_metrics (level = ad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_fatigue_alerts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('network', 24)->nullable();
            $t->string('ad_ref', 120);
            $t->string('ad_name', 255)->nullable();
            $t->string('status', 16); // watch | fatigued
            $t->decimal('frequency', 8, 2)->nullable();
            $t->decimal('ctr_recent', 8, 4)->nullable();
            $t->decimal('ctr_baseline', 8, 4)->nullable();
            $t->decimal('ctr_drop_pct', 6, 1)->nullable();
            $t->decimal('thruplay_drop_pct', 6, 1)->nullable();
            $t->decimal('spend_recent', 14, 3)->nullable();
            $t->string('peak_key', 32)->nullable();
            $t->json('thresholds')->nullable();
            $t->date('window_end');
            $t->timestamp('detected_at');
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();

            $t->index(['agency_id', 'client_id', 'resolved_at'], 'creative_fatigue_open_idx');
            $t->index(['client_id', 'ad_ref'], 'creative_fatigue_ad_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_fatigue_alerts');
    }
};
