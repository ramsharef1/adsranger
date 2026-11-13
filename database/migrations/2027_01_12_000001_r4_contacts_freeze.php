<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R4 part 1 (D-303): XS-10 client freeze (reason + resume date) and XS-02 client contact log. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $t): void {
            $t->string('paused_reason', 20)->nullable();
            $t->date('paused_until')->nullable();
            $t->timestamp('paused_at')->nullable();
        });
        Schema::create('client_contacts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('kind', 12); // call · whatsapp · meeting · other
            $t->date('happened_on');
            $t->text('note')->nullable();
            $t->date('next_on')->nullable();
            $t->timestamp('next_done_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'next_on']);
            $t->index(['client_id', 'happened_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn(['paused_reason', 'paused_until', 'paused_at']));
    }
};
