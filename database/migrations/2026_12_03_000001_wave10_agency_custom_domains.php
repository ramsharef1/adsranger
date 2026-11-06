<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            if (!Schema::hasColumn('agencies', 'custom_domain')) {
                $t->string('custom_domain')->nullable()->unique()->after('slug');
            }
            if (!Schema::hasColumn('agencies', 'custom_domain_status')) {
                $t->string('custom_domain_status')->default('pending')->after('custom_domain');
            }
            if (!Schema::hasColumn('agencies', 'custom_domain_verified_at')) {
                $t->timestamp('custom_domain_verified_at')->nullable()->after('custom_domain_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $cols = array_filter(
                ['custom_domain', 'custom_domain_status', 'custom_domain_verified_at'],
                fn ($c) => Schema::hasColumn('agencies', $c)
            );
            if ($cols) {
                $t->dropColumn($cols);
            }
        });
    }
};