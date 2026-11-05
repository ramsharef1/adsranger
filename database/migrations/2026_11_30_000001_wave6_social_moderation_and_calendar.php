<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_moderation_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('name', 140);
            $t->string('platform', 32)->default('all'); // all | meta | tiktok | x | linkedin | snapchat
            $t->json('keywords'); // matched words or phrases
            $t->string('match_type', 24)->default('contains'); // contains | exact | sentiment_negative
            $t->string('action', 24)->default('archive'); // archive | auto_reply | flag
            $t->text('auto_reply_template')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('triggered_count')->default(0);
            $t->timestamps();
            $t->index(['agency_id', 'client_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_moderation_rules');
    }
};
