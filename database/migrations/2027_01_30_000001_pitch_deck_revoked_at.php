<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** cl149: a shared pitch-deck link can be switched off; the deck stays in the list as revoked. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_pitch_decks', fn (Blueprint $t) => $t->timestamp('revoked_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('prospect_pitch_decks', fn (Blueprint $t) => $t->dropColumn('revoked_at'));
    }
};
