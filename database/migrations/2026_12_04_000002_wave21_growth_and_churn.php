<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_churn_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedTinyInteger('risk_score')->default(15);
            $table->string('risk_level', 32)->default('low');
            $table->json('risk_factors')->nullable();
            $table->text('suggested_action')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'risk_level']);
        });

        Schema::create('agency_partner_listings', function (Blueprint $table): void {
            $table->id();
            $table->string('agency_name', 160);
            $table->string('city', 80);
            $table->string('country', 80);
            $table->string('website', 255);
            $table->string('tier_certified', 40)->default('Pro Partner');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_partner_listings');
        Schema::dropIfExists('client_churn_scores');
    }
};
