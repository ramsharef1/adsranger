<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-067 retainer vs ad-spend split (module agency_ops_hub, off). The agency's own billing identity and each client's fee
 * terms. Exports are draft structures for the agency's accounting software; nothing here files or validates an invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_billing_profiles', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->unique()->constrained('agencies')->cascadeOnDelete();
            $t->string('legal_name', 190);
            $t->string('tax_identifier', 40)->nullable();
            $t->string('default_currency', 3);
            $t->string('country_code', 2)->nullable();
            $t->timestamps();
        });

        Schema::create('client_retainer_terms', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('fee_model', 16); // fixed | percent_spend | hybrid
            $t->decimal('fixed_amount', 14, 3)->default(0);
            $t->decimal('spend_percent', 6, 2)->default(0);
            $t->decimal('min_fee', 14, 3)->default(0);
            $t->string('currency', 3);
            $t->decimal('tax_rate_percent', 5, 2)->nullable();
            $t->timestamps();

            $t->unique(['agency_id', 'client_id'], 'client_retainer_terms_client_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_retainer_terms');
        Schema::dropIfExists('agency_billing_profiles');
    }
};
