<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2-063: signed store webhooks from Salla, Zid and Shopify into the profit hub (module intelligence_hub, off).
 * A connection starts as webhook_ready and turns active only after the first event whose signature verified.
 * client_unit_economics gains observed store counters next to (never over) the rates the agency typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_store_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('platform', 16); // salla | zid | shopify
            $table->text('webhook_secret'); // encrypted cast
            $table->string('webhook_token', 64)->unique();
            $table->string('store_identifier', 191);
            $table->string('status', 16)->default('webhook_ready'); // webhook_ready | active | paused
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'client_id'], 'store_connections_tenant_idx');
        });

        Schema::create('client_store_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('client_store_connections')->cascadeOnDelete();
            $table->string('event_uid', 191);
            $table->string('event_type', 32); // order_completed | cod_returned | order_cancelled | stock_update | ignored
            $table->string('order_ref', 64)->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'event_uid'], 'store_events_uid_unique');
            $table->index(['connection_id', 'order_ref'], 'store_events_order_idx');
        });

        Schema::table('client_unit_economics', function (Blueprint $table): void {
            $table->unsignedInteger('store_orders_observed')->default(0);
            $table->unsignedInteger('store_cod_orders')->default(0);
            $table->unsignedInteger('store_cod_returned')->default(0);
            $table->timestamp('store_rates_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_unit_economics', function (Blueprint $table): void {
            $table->dropColumn(['store_orders_observed', 'store_cod_orders', 'store_cod_returned', 'store_rates_at']);
        });
        Schema::dropIfExists('client_store_webhook_events');
        Schema::dropIfExists('client_store_connections');
    }
};
