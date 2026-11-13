<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// EG-07 (R1, D-289): last run of every scheduled task / queued job, for the /admin Operations board.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_runs', function (Blueprint $t) {
            $t->id();
            $t->string('job', 120)->unique();
            $t->timestamp('last_started_at')->nullable();
            $t->timestamp('last_finished_at')->nullable();
            $t->decimal('last_seconds', 10, 2)->nullable();
            $t->string('last_status', 12)->nullable();
            $t->string('last_error', 300)->nullable();
            $t->timestamp('last_failed_at')->nullable();
            $t->unsignedInteger('runs')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_runs');
    }
};
