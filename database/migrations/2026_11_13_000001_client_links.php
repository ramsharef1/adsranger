<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #56 signed client links: a client opens its read-only report without an account. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('label')->nullable();
            $t->string('token_hash', 64)->unique();
            $t->text('token'); // encrypted, so the agency can copy the link again
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_viewed_at')->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['client_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_links');
    }
};
