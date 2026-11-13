<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI Workspace M1 slice 2: one row per AI a person connected «الربط» (spec v1 §16, Product M1 screens §2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->uuid('oauth_client_id')->index();
            $t->string('client_name')->nullable();       // what the AI called itself when it registered
            $t->string('provider', 20)->nullable();      // chatgpt · claude · gemini · copilot · perplexity (when we can tell)
            $t->string('status', 12)->default('waiting'); // waiting · verified · paused · revoked
            $t->json('allowed_client_ids');
            $t->string('ownership_version', 20);
            $t->timestamp('ownership_at');
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('last_call_at')->nullable();
            $t->date('calls_day')->nullable();
            $t->unsignedInteger('calls_today')->default(0);
            $t->string('paused_reason')->nullable();
            $t->timestamp('paused_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['user_id', 'oauth_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connections');
    }
};
