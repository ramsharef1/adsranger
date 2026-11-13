<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** FS-16 / XS-14 / WK-01 (R4, D-304): COD outcomes as counts per client and week (optionally per campaign); no order IDs, no customer data. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_weeks', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->date('week_start');
            $t->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $t->unsignedInteger('delivered')->default(0);
            $t->unsignedInteger('returned')->default(0);
            $t->unsignedInteger('cancelled')->default(0);
            $t->string('source', 8)->default('manual'); // manual · sheet
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['client_id', 'week_start', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_weeks');
    }
};
