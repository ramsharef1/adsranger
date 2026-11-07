<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-188 required onboarding. Only workspaces that are marked `onboarding_required` are gated: new self-signup and
 * /app/new workspaces, and (here) existing workspaces nobody has joined yet. Everyone already working is not blocked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $t->boolean('onboarding_required')->default(false);
            $t->unsignedTinyInteger('onboarding_step')->default(1);
            $t->timestamp('onboarding_completed_at')->nullable();
            $t->boolean('onboarding_checklist_hidden')->default(false);
            $t->string('audience', 16)->nullable();
        });

        $joined = DB::table('agency_user')->select('agency_id');
        DB::table('agencies')->whereNotIn('id', $joined)->update(['onboarding_required' => true]);
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn(['onboarding_required', 'onboarding_step', 'onboarding_completed_at', 'onboarding_checklist_hidden', 'audience']));
    }
};
