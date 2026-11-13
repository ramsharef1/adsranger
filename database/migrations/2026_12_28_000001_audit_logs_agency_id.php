<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A-18 (IDEA-015): audit rows carry the workspace they happened in, so owners can read their own workspace's log. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable()->after('user_id');
            $table->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'created_at']);
            $table->dropColumn('agency_id');
        });
    }
};
