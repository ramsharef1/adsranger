<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R5 side rows: WK-12 prayer-aware quiet switch (null = on), DS-25 holiday status (expected / confirmed). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t): void {
            $t->boolean('prayer_quiet')->nullable();
        });
        Schema::create('holiday_statuses', function (Blueprint $t): void {
            $t->id();
            $t->string('event_key', 40);
            $t->unsignedSmallInteger('year');
            $t->string('status', 12)->default('expected'); // expected · confirmed
            $t->date('start_date')->nullable(); // the announced first day, when it differs from the calculation
            $t->string('source', 255)->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['event_key', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_statuses');
        Schema::table('agencies', function (Blueprint $t): void {
            $t->dropColumn('prayer_quiet');
        });
    }
};
