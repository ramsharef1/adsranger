<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_audience_syncs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('name', 160);
            $t->string('source_type', 40)->default('crm_leads'); // crm_leads | purchasers | website_visitors | high_ltv
            $t->json('target_networks'); // ['meta', 'google', 'tiktok', 'snapchat']
            $t->unsignedInteger('records_count')->default(0);
            $t->decimal('match_rate', 5, 2)->default(0.00);
            $t->string('status', 24)->default('synced'); // pending | syncing | synced | failed
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id', 'status']);
        });

        Schema::create('conversion_health_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('network', 32); // meta | google | tiktok | snapchat
            $t->string('event_name', 64)->default('Purchase'); // Purchase | Lead | CompleteRegistration
            $t->boolean('pixel_active')->default(true);
            $t->boolean('capi_active')->default(false);
            $t->decimal('event_match_quality', 4, 1)->default(6.0); // 0.0 to 10.0
            $t->decimal('deduplication_rate', 5, 2)->default(95.00);
            $t->string('status', 24)->default('healthy'); // healthy | warning | critical
            $t->timestamp('checked_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_health_checks');
        Schema::dropIfExists('custom_audience_syncs');
    }
};
