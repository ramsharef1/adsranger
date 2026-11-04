<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wave 3b: per-day breakdowns (hour, placement, device, region / city, age × gender, 30-day reach) + benchmarks opt-in (#42). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_breakdowns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $t->string('network', 40);
            $t->string('dimension', 20); // hour | placement | device | region | age_gender | reach30
            $t->date('date');
            $t->string('key', 120);
            $t->string('label', 190)->nullable();
            $t->decimal('spend', 14, 4)->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('reach')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->unsignedBigInteger('link_clicks')->default(0);
            $t->decimal('results', 14, 2)->default(0);
            $t->string('result_type', 30)->nullable();
            $t->string('currency', 8)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['client_id', 'dimension', 'date']);
            $t->index(['ad_account_id', 'dimension', 'date']);
        });

        Schema::table('agencies', function (Blueprint $t) {
            $t->boolean('benchmarks_opt_in')->default(false);
            $t->timestamp('benchmarks_opt_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn(['benchmarks_opt_in', 'benchmarks_opt_in_at']));
        Schema::dropIfExists('metric_breakdowns');
    }
};
