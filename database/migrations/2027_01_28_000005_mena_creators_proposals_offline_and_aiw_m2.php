<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_partnerships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('creator_name', 190);
            $table->string('platform', 40)->default('snapchat');
            $table->decimal('flat_fee', 14, 3)->default(0);
            $table->decimal('gifted_cogs', 14, 3)->default(0);
            $table->decimal('boosted_spend', 14, 3)->default(0);
            $table->string('promo_code', 80)->nullable();
            $table->string('utm_campaign', 190)->nullable();
            $table->unsignedInteger('attributed_orders')->default(0);
            $table->decimal('gross_revenue', 14, 3)->default(0);
            $table->decimal('net_poas', 10, 3)->default(0);
            $table->timestamps();

            $table->index(['agency_id', 'client_id'], 'creator_part_client_idx');
        });

        Schema::create('prospect_pitch_decks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('prospect_name', 190);
            $table->string('prospect_url', 500);
            $table->string('currency', 8)->default('SAR');
            $table->string('share_token', 64)->unique();
            $table->json('audit_snapshot');
            $table->json('tier_quotes');
            $table->timestamps();

            $table->index(['agency_id', 'created_at'], 'pitch_decks_agency_created_idx');
        });

        Schema::create('offline_conversion_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('lead_identifier', 190);
            $table->string('deal_stage', 24)->default('qualified');
            $table->decimal('deal_value', 14, 3)->default(0);
            $table->string('currency', 8)->default('SAR');
            $table->string('ctwa_clid', 255)->nullable();
            $table->string('gclid', 255)->nullable();
            $table->string('hashed_phone', 64)->nullable();
            $table->string('hashed_email', 64)->nullable();
            $table->string('target_network', 32)->default('meta_capi');
            $table->string('status', 36)->default('queued_until_credentials');
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'client_id', 'status'], 'offline_conv_client_status_idx');
        });

        Schema::create('ai_night_shift_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 64);
            $table->json('payload');
            $table->text('rationale');
            $table->string('status', 32)->default('pending_approval');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status'], 'ai_ns_actions_agency_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_night_shift_actions');
        Schema::dropIfExists('offline_conversion_dispatches');
        Schema::dropIfExists('prospect_pitch_decks');
        Schema::dropIfExists('creator_partnerships');
    }
};
