<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R7 end (D-301): DS-30 model change check on own keys, WS-34 brain review date, WS-32 agency prompt library. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_keys', function (Blueprint $t): void {
            $t->string('previous_model', 80)->nullable()->after('model');
            $t->json('model_check')->nullable()->after('previous_model');
        });
        Schema::table('clients', function (Blueprint $t): void {
            $t->timestamp('brain_checked_at')->nullable();
            $t->foreignId('brain_checked_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('ai_prompts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->string('title', 120);
            $t->text('body');
            $t->string('playbook', 40)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('agency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
        Schema::table('clients', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('brain_checked_by');
            $t->dropColumn('brain_checked_at');
        });
        Schema::table('ai_keys', function (Blueprint $t): void {
            $t->dropColumn(['previous_model', 'model_check']);
        });
    }
};
