<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** XS-06 (R4): the agency asks its client to approve a plan, budget or creative on the report link; the client answers there. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_approvals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('kind', 12); // plan · budget · creative · other
            $t->string('title', 160);
            $t->text('details')->nullable();
            $t->string('status', 16)->default('pending'); // pending · approved · changes · withdrawn
            $t->string('answered_by', 80)->nullable(); // the name the client typed
            $t->text('client_note')->nullable();
            $t->timestamp('answered_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_approvals');
    }
};
