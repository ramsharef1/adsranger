<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_accelerator_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('network', 32);
            $t->string('feature_key', 48);
            $t->string('external_ref')->nullable();
            $t->string('status', 24)->default('processed');
            $t->decimal('attributed_value', 16, 4)->default(0);
            $t->unsignedSmallInteger('score')->default(0);
            $t->json('payload');
            $t->timestamps();
            $t->index(['agency_id', 'client_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_accelerator_events');
    }
};