<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-REACH R-1 (module `reach`, off): publishers, widgets, offers + coupon codes, postback endpoints, wallets, ledger,
 * advertiser billing. Every amount is an estimate until launch (D-262): nothing here moves money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reach_publishers', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('name', 120);
            $t->string('domain_or_app', 190);
            $t->string('status', 24)->default('pending_verification'); // pending_verification · verified · rejected · paused
            $t->boolean('family_safe')->default(true);
            $t->json('category_filters')->nullable();
            $t->json('blocked_domains')->nullable(); // up to 20 advertiser domains this publisher never shows
            $t->string('ref', 16)->unique(); // public id used in Sub-IDs: ?subid={ref}_{placement}
            $t->string('referral_code', 16)->unique();
            $t->foreignId('referred_by_id')->nullable()->constrained('reach_publishers')->nullOnDelete();
            $t->timestamps();
            $t->index(['user_id', 'status']);
        });

        Schema::create('reach_widgets', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('publisher_id')->constrained('reach_publishers')->cascadeOnDelete();
            $t->string('type', 12); // web_js · app_sdk
            $t->string('public_key', 24)->unique();
            $t->string('placement', 32)->default('main');
            $t->json('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('reach_advertiser_billing', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('advertiser_id')->unique()->constrained('clients')->cascadeOnDelete();
            $t->string('billing_mode', 24)->default('escrow_prepaid'); // escrow_prepaid · agency_guaranteed · postpaid_invoice
            $t->decimal('escrow_balance', 14, 3)->default(0);
            $t->decimal('credit_cap', 14, 3)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('reach_offers', function (Blueprint $t): void {
            $t->id();
            $t->string('title', 190);
            $t->string('network', 16); // arabclicks · admitad · impact · direct_client
            $t->string('external_id', 64)->nullable();
            $t->foreignId('advertiser_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->decimal('payout_amount', 12, 3);
            $t->decimal('publisher_payout_amount', 12, 3);
            $t->string('currency', 3)->default('USD');
            $t->string('offer_type', 3); // cps · cpi · cpl
            $t->boolean('allow_incent')->default(false);
            $t->string('coupon_code', 40)->nullable();
            $t->string('landing_url', 500)->nullable();
            $t->string('category', 32)->nullable();
            $t->json('countries')->nullable(); // ISO-2 list; null = all
            $t->json('keywords')->nullable(); // normalised keys for the URL keyword match
            $t->string('status', 12)->default('active'); // active · paused
            $t->timestamps();
            $t->unique(['network', 'external_id']);
            $t->index(['status', 'allow_incent']);
        });

        Schema::create('reach_coupon_codes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('offer_id')->constrained('reach_offers')->cascadeOnDelete();
            $t->string('code', 40);
            $t->string('tier', 8); // vip · ai_bot · pool
            $t->foreignId('publisher_id')->nullable()->constrained('reach_publishers')->nullOnDelete(); // vip / ai_bot owner
            $t->timestamps();
            $t->unique(['offer_id', 'code']);
        });

        Schema::create('reach_copy_intents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('coupon_code_id')->constrained('reach_coupon_codes')->cascadeOnDelete();
            $t->foreignId('publisher_id')->constrained('reach_publishers')->cascadeOnDelete();
            $t->string('placement', 32)->default('main');
            $t->timestamp('copied_at');
            $t->index(['coupon_code_id', 'copied_at']);
        });

        Schema::create('reach_postback_endpoints', function (Blueprint $t): void {
            $t->id();
            $t->string('network', 16);
            $t->string('label', 120);
            $t->string('token_hash', 64)->unique();
            $t->text('secret'); // encrypted cast; HMAC-SHA256 key
            $t->json('allowed_ips')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamp('last_received_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('reach_wallets', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('publisher_id')->unique()->constrained('reach_publishers')->cascadeOnDelete();
            $t->decimal('pending_balance', 14, 3)->default(0);
            $t->decimal('cleared_balance', 14, 3)->default(0);
            $t->decimal('reserve_balance', 14, 3)->default(0); // 15% hold after an early cashout
            $t->decimal('advanced_balance', 14, 3)->default(0); // pending already advanced by an early cashout
            $t->decimal('ad_credit_balance', 14, 3)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->timestamps();
        });

        Schema::create('reach_ledger', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('publisher_id')->constrained('reach_publishers')->cascadeOnDelete();
            $t->foreignId('offer_id')->nullable()->constrained('reach_offers')->nullOnDelete();
            $t->foreignId('postback_endpoint_id')->nullable()->constrained('reach_postback_endpoints')->nullOnDelete();
            $t->string('conversion_type', 24); // conversion · refund · early_cashout · withdrawal_request · ad_credit · referral_override · clear
            $t->decimal('gross_amount', 14, 3)->default(0);
            $t->decimal('net_commission', 14, 3)->default(0);
            $t->string('status', 12)->default('estimate'); // always estimate before launch (D-262)
            $t->string('wallet_state', 12)->nullable(); // pending · cleared · reserve · ad_credit
            $t->string('attribution_tier', 8)->nullable(); // subid · vip · ai_bot · pool
            $t->string('placement', 32)->nullable();
            $t->string('coupon_code', 40)->nullable();
            $t->string('external_conversion_id', 96)->nullable();
            $t->timestamp('converted_at')->nullable();
            $t->timestamp('cleared_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->unique(['postback_endpoint_id', 'external_conversion_id', 'conversion_type'], 'reach_ledger_postback_unique');
            $t->index(['publisher_id', 'conversion_type', 'wallet_state']);
        });
    }

    public function down(): void
    {
        foreach (['reach_ledger', 'reach_wallets', 'reach_postback_endpoints', 'reach_copy_intents', 'reach_coupon_codes', 'reach_offers', 'reach_advertiser_billing', 'reach_widgets', 'reach_publishers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
