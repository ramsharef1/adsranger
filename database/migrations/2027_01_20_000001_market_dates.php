<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PS-21 / XS-19 / XS-20 (R5, D-307): payday days and school / exam periods per country, entered or confirmed by the
 * agency (always shown as "usually"), until Growth's checked list fills them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_dates', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->string('country', 2);
            $t->string('kind', 16); // payday · school_holiday · exams
            $t->string('label', 120)->nullable();
            $t->unsignedTinyInteger('day_of_month')->nullable(); // payday: 1–31; 0 = last day of the month
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('source', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['agency_id', 'country', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_dates');
    }
};
