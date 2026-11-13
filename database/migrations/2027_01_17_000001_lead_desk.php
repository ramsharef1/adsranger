<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** XS-13 / XS-23 (R4, D-306): a no-login lead page per client; leads logged as counts with times and outcome, no phone numbers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_desk_links', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->text('token');
            $t->string('token_hash', 64)->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('leads', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('channel', 12); // whatsapp · call · form · walk_in · other
            $t->string('label', 60)->nullable(); // first name or a short note; numbers are removed
            $t->timestamp('received_at');
            $t->timestamp('contacted_at')->nullable();
            $t->string('outcome', 14)->nullable(); // qualified · sale · not_relevant
            $t->decimal('value', 14, 2)->nullable();
            $t->string('logged_via', 6)->default('desk'); // desk · app
            $t->timestamps();
            $t->index(['client_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_desk_links');
    }
};
