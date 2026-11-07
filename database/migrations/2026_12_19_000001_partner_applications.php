<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Website P-25 /partners (D-219): design-partner applications, separate from the waitlist (consent partners-v1). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_applications', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190);
            $t->string('agency_name', 140);
            $t->string('country', 2);
            $t->string('clients_range', 10);
            $t->string('name', 120)->nullable();
            $t->string('networks', 120)->nullable();
            $t->string('monthly_spend', 20)->nullable();
            $t->string('lang', 2)->default('ar');
            $t->timestamp('consent_at');
            $t->string('consent_text_version', 20);
            $t->timestamps();
            $t->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_applications');
    }
};
