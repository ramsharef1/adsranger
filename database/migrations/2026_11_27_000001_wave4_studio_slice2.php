<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_launches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('creative_brief_id')->nullable()->constrained()->nullOnDelete();
            $t->string('campaign_name', 160);
            $t->json('networks'); // ['meta', 'google_ads', 'tiktok', 'snapchat']
            $t->string('budget_type', 20)->default('daily'); // daily | lifetime
            $t->decimal('budget_amount', 12, 2)->default(50.00);
            $t->string('status', 24)->default('draft'); // draft | scheduled | published | failed
            $t->text('policy_notes')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_launches');
    }
};
