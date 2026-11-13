<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BX-05 (D-278 / D-280): agencies can switch off the "Powered by AdsRanger" line on client reports (null = on). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->boolean('report_powered_by')->nullable());
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('report_powered_by'));
    }
};
