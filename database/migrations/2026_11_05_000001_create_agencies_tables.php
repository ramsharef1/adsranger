<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('country', 8)->nullable();
            $table->string('timezone')->default('Asia/Amman');
            $table->string('currency', 3)->default('USD');
            $table->string('language', 2)->default('ar');
            $table->string('plan')->default('starter');
            $table->string('status')->default('trial');
            $table->timestamps();
        });

        Schema::create('agency_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('manager');
            $table->timestamps();
            $table->unique(['agency_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_user');
        Schema::dropIfExists('agencies');
    }
};
