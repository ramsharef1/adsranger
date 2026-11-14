<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1a (D-308 / DS-21..23): per-ad permit name on client_licences, off-plan launch fields on clients,
 * and weekly delivery-app order counts on delivery_weeks (modelled on cod_weeks; no order IDs or customer PII).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_licences', function (Blueprint $t): void {
            $t->string('ad_name', 160)->nullable();
        });

        Schema::table('clients', function (Blueprint $t): void {
            $t->string('launch_project_name', 160)->nullable();
            $t->date('launch_date')->nullable();
        });

        Schema::create('delivery_weeks', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->date('week_start');
            $t->string('app_name', 80);
            $t->unsignedInteger('orders_count')->default(0);
            $t->decimal('orders_value', 14, 3)->nullable();
            $t->string('source', 8)->default('manual');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['client_id', 'week_start', 'app_name']);
            $t->index(['agency_id', 'client_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_weeks');

        Schema::table('clients', function (Blueprint $t): void {
            $t->dropColumn(['launch_project_name', 'launch_date']);
        });

        Schema::table('client_licences', function (Blueprint $t): void {
            $t->dropColumn('ad_name');
        });
    }
};
