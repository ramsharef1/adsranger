<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #12 Calls split: which Meta call count is the client's result for call campaigns (placed / confirmed / 20s+). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            $t->string('call_result', 12)->default('placed')->after('target_roas');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            $t->dropColumn('call_result');
        });
    }
};
