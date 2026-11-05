<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('title', 160)->nullable();
            $t->text('content');
            $t->json('platforms'); // ['meta', 'tiktok', 'x', 'linkedin', 'snapchat']
            $t->string('post_type', 32)->default('post'); // post | reel | carousel | story
            $t->json('media_urls')->nullable();
            $t->string('status', 24)->default('draft'); // draft | scheduled | published | failed
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id', 'status']);
        });

        Schema::create('social_inbox_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 32); // meta | tiktok | x | linkedin | snapchat
            $t->string('message_type', 24)->default('comment'); // comment | dm | mention
            $t->string('sender_name', 120);
            $t->string('sender_handle', 120)->nullable();
            $t->text('body');
            $t->string('sentiment', 24)->default('neutral'); // positive | neutral | negative
            $t->string('status', 24)->default('open'); // open | replied | archived
            $t->text('reply_body')->nullable();
            $t->timestamp('replied_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'client_id', 'status']);
        });

        Schema::create('social_metrics_daily', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 32);
            $t->date('date');
            $t->unsignedBigInteger('followers_count')->default(0);
            $t->integer('followers_gained')->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('reach')->default(0);
            $t->unsignedBigInteger('engagements')->default(0);
            $t->unsignedBigInteger('profile_views')->default(0);
            $t->timestamps();
            $t->unique(['client_id', 'platform', 'date']);
            $t->index(['agency_id', 'client_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_metrics_daily');
        Schema::dropIfExists('social_inbox_messages');
        Schema::dropIfExists('social_posts');
    }
};
