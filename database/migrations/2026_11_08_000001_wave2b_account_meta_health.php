<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_accounts', function (Blueprint $t) {
            $t->json('meta')->nullable();
            $t->json('health')->nullable();
            $t->timestamp('health_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ad_accounts', fn (Blueprint $t) => $t->dropColumn(['meta', 'health', 'health_checked_at']));
    }
};
