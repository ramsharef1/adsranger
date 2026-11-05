<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #24 finish + #175 Critical push (D-104, D-141): phone subscriptions, per-user quiet hours, held pushes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('endpoint');
            $t->string('endpoint_hash', 64)->unique();
            $t->string('p256dh', 120);
            $t->string('auth', 40);
            $t->string('user_agent', 190)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('push_held', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'alert_id']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('push_quiet_start', 5)->default('23:00');
            $t->string('push_quiet_end', 5)->default('07:00');
            $t->boolean('push_weekend_off')->default(true);
            $t->boolean('push_override_spend')->default(false);
        });
        Schema::table('alerts', function (Blueprint $t) {
            $t->timestamp('pushed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', fn (Blueprint $t) => $t->dropColumn('pushed_at'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['push_quiet_start', 'push_quiet_end', 'push_weekend_off', 'push_override_spend']));
        Schema::dropIfExists('push_held');
        Schema::dropIfExists('push_subscriptions');
    }
};
