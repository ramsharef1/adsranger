<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Public Website Phase 1: agency waitlist signups for beta / launch access. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('agency_name', 140)->nullable();
            $t->string('email', 190)->unique();
            $t->string('monthly_spend', 60)->nullable();
            $t->string('lang', 2)->default('ar');
            $t->string('notes', 500)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
