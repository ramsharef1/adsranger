<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reach R-4 (E2-064): per-endpoint override of the network preset (Sub-ID parameter + postback field names). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reach_postback_endpoints', function (Blueprint $t): void {
            $t->string('subid_param', 32)->nullable();
            $t->json('param_map')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reach_postback_endpoints', function (Blueprint $t): void {
            $t->dropColumn(['subid_param', 'param_map']);
        });
    }
};
