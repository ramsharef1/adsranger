<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R4 (D-305): XS-24 / NB-36 client licences with expiry, DS-35 two-person approvals for risky actions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_licences', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('kind', 24); // real_estate · health · influencer · advertiser · other
            $t->string('number', 80);
            $t->date('expires_on')->nullable();
            $t->string('note', 200)->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'expires_on']);
        });
        Schema::create('approval_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->string('action', 32); // delete_client · export_workspace
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_name', 200)->nullable();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 12)->default('pending'); // pending · approved · declined · used
            $t->timestamp('expires_at');
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
            $t->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('client_licences');
    }
};
