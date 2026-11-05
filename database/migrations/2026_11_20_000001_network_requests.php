<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #127 Network catalog: one row per member who asks for a network (catalog key, or a free-text suggestion). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('network_key', 80);
            $t->string('network_name', 120);
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'user_id', 'network_key']);
            $t->index('network_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_requests');
    }
};
