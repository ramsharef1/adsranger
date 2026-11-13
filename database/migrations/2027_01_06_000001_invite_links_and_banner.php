<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R1 adds (D-291 / D-293): EH-11 invite by link (no email until someone joins) · EH-17 /admin announcement banner · CS-20 re-index gate · DB-01 landing page down since. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_invitations', function (Blueprint $t) {
            $t->string('email')->nullable()->change();
            $t->boolean('via_link')->default(false);
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('body_ar', 240);
            $t->string('body_en', 240);
            $t->string('tone', 8)->default('info'); // info · warning
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('landing_pages', function (Blueprint $t) { // DB-01: when the page first failed, for the alert
            $t->timestamp('down_since')->nullable();
        });
        Schema::create('page_gate', function (Blueprint $t) { // CS-20: one row per held guide / post / glossary term
            $t->id();
            $t->string('path', 160)->unique();
            $t->string('kind', 8); // guide · post · term
            $t->unsignedInteger('words')->default(0);
            $t->boolean('band_ok')->default(false);
            $t->boolean('schema_ok')->default(false);
            $t->unsignedSmallInteger('out_links')->default(0);
            $t->unsignedSmallInteger('in_links')->default(0);
            $t->unsignedSmallInteger('lint_errors')->default(0);
            $t->string('lint_first', 200)->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->foreignId('ready_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('released_at')->nullable();
            $t->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_gate');
        Schema::table('landing_pages', function (Blueprint $t) {
            $t->dropColumn('down_since');
        });
        Schema::dropIfExists('announcements');
        Schema::table('agency_invitations', function (Blueprint $t) {
            $t->dropColumn('via_link');
        });
        // email stays nullable on the way down: link invites may hold no email.
    }
};
