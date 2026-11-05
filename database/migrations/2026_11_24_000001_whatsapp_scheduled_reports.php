<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #106 finish: WhatsApp delivery channel alongside email for scheduled reports. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_schedules', function (Blueprint $t) {
            $t->json('whatsapp_recipients')->nullable()->after('recipients');
        });

        Schema::table('report_sends', function (Blueprint $t) {
            $t->string('channel', 12)->default('email')->after('period');
        });
    }

    public function down(): void
    {
        Schema::table('report_sends', function (Blueprint $t) {
            $t->dropColumn('channel');
        });

        Schema::table('report_schedules', function (Blueprint $t) {
            $t->dropColumn('whatsapp_recipients');
        });
    }
};
