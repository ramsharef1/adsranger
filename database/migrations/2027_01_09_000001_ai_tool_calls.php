<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R7 part 2 (WS-10 / FS-10): the connector's tool catalogue — per-workspace tool switches and a log of tool calls. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $t->json('ai_tools_off')->nullable();
        });
        Schema::create('ai_tool_calls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ai_connection_id')->nullable()->constrained()->nullOnDelete();
            $t->string('tool', 60);
            $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedSmallInteger('status');
            $t->timestamp('created_at')->nullable();
            $t->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_calls');
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('ai_tools_off'));
    }
};
