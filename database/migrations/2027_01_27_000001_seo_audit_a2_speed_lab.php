<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site audit A2 (E2-062): speed lab columns. Every column is nullable: a null means "not measured", never zero.
 * speed_source = 'static' (our own HTML asset checks) or 'psi' (Google PageSpeed Insights v5, only when a key is set).
 */
return new class extends Migration
{
    private const TABLES = ['seo_audits', 'seo_audit_pages'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->unsignedTinyInteger('psi_mobile_score')->nullable();
                $t->unsignedInteger('lcp_ms')->nullable();
                $t->unsignedInteger('inp_ms')->nullable();
                $t->decimal('cls_score', 6, 3)->nullable();
                $t->unsignedInteger('ttfb_ms')->nullable();
                $t->unsignedSmallInteger('render_blocking_count')->nullable();
                $t->string('speed_source', 16)->nullable();
                $t->timestamp('speed_checked_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->dropColumn(['psi_mobile_score', 'lcp_ms', 'inp_ms', 'cls_score', 'ttfb_ms', 'render_blocking_count', 'speed_source', 'speed_checked_at']);
            });
        }
    }
};
