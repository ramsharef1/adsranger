<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_unit_economics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->decimal('cogs_pct', 6, 2)->default(35.00);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('return_shipping_fee', 12, 2)->default(0);
            $table->decimal('cod_share_pct', 6, 2)->default(0);
            $table->decimal('cod_return_rate_pct', 6, 2)->default(0);
            $table->decimal('bnpl_share_pct', 6, 2)->default(0);
            $table->decimal('bnpl_fee_pct', 6, 2)->default(0);
            $table->decimal('gateway_fee_pct', 6, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('collector_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->unique(['agency_id', 'client_id'], 'client_unit_economics_tenant_unique');
        });

        Schema::create('sku_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('sku_code', 120);
            $table->string('product_title', 255);
            $table->string('stock_status', 32)->default('in_stock');
            $table->integer('stock_qty')->default(0);
            $table->decimal('ad_spend', 14, 2)->default(0);
            $table->decimal('gross_revenue', 14, 2)->default(0);
            $table->decimal('cogs_amount', 14, 2)->default(0);
            $table->decimal('net_profit', 14, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('repeat_ltv_90d', 12, 2)->default(0);
            $table->string('quadrant', 24)->default('sidekick');
            $table->json('co_purchased_skus_json')->nullable();
            $table->json('promo_codes_json')->nullable();
            $table->date('period_date');
            $table->timestamps();

            $table->index(['agency_id', 'client_id', 'period_date'], 'sku_snapshots_tenant_period_idx');
            $table->index(['agency_id', 'client_id', 'sku_code'], 'sku_snapshots_tenant_sku_idx');
        });

        Schema::create('capi_bridge_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('collector_token', 64)->index();
            $table->string('channel', 32)->default('pixel_relay');
            $table->string('event_name', 64);
            $table->string('event_dedup_id', 128);
            $table->string('ctwa_clid', 191)->nullable()->index();
            $table->json('click_ids_json')->nullable();
            $table->timestamp('free_window_expires_at')->nullable();
            $table->string('hashed_contact_sha256', 64)->nullable()->index();
            $table->decimal('value', 14, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('delivery_state', 32)->default('simulated');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['agency_id', 'client_id', 'event_dedup_id'], 'capi_bridge_events_tenant_dedup_unique');
        });

        Schema::create('post_purchase_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('survey_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->json('options_json')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'client_id'], 'post_purchase_surveys_tenant_idx');
        });

        Schema::create('post_purchase_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('post_purchase_survey_id')->constrained('post_purchase_surveys')->cascadeOnDelete();
            $table->string('channel_choice', 80);
            $table->string('order_ref', 120)->nullable();
            $table->decimal('order_value', 14, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->timestamps();

            $table->index(['agency_id', 'client_id', 'channel_choice'], 'pps_responses_tenant_channel_idx');
        });

        Schema::create('client_custom_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('formula_expression', 255);
            $table->string('format', 24)->default('decimal');
            $table->boolean('show_on_client_report')->default(false);
            $table->timestamps();

            $table->index(['agency_id', 'client_id'], 'client_custom_kpis_tenant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_custom_kpis');
        Schema::dropIfExists('post_purchase_survey_responses');
        Schema::dropIfExists('post_purchase_surveys');
        Schema::dropIfExists('capi_bridge_events');
        Schema::dropIfExists('sku_performance_snapshots');
        Schema::dropIfExists('client_unit_economics');
    }
};
