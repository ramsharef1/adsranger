<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('influencer_name');
            $t->string('platform', 32)->default('instagram');
            $t->string('promo_code', 48);
            $t->string('utm_campaign', 96)->nullable();
            $t->decimal('fixed_fee', 16, 4)->default(0);
            $t->decimal('commission_percent', 6, 2)->default(0);
            $t->unsignedInteger('orders_count')->default(0);
            $t->decimal('attributed_revenue', 16, 4)->default(0);
            $t->decimal('total_payout', 16, 4)->default(0);
            $t->decimal('effective_roas', 12, 4)->default(0);
            $t->decimal('cac', 16, 4)->default(0);
            $t->string('status', 24)->default('active');
            $t->timestamps();
            $t->index(['agency_id', 'client_id', 'promo_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_campaigns');
    }
};