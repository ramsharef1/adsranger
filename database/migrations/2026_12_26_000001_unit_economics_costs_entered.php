<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UX-255: the hub shows saved costs only once the agency has entered them itself (no invented defaults). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_unit_economics', function (Blueprint $table) {
            $table->timestamp('costs_entered_at')->nullable()->after('gateway_fee_pct');
        });
    }

    public function down(): void
    {
        Schema::table('client_unit_economics', function (Blueprint $table) {
            $table->dropColumn('costs_entered_at');
        });
    }
};
