<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI Workspace M1 item 3: the client brain «ذاكرة العميل». Only what users save is stored (spec v1 §0 answer 4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_brain_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20);              // rule · decision · brief · learning · output · chat_link
            $t->string('title');
            $t->longText('original');            // exactly what arrived; never changed ("show original")
            $t->longText('clean')->nullable();   // after the rule pass + safe fixes
            $t->json('output')->nullable();      // structured output (output contract types)
            $t->string('output_type', 30)->nullable();
            $t->string('url', 500)->nullable();  // chat links: title + link only
            $t->string('ai_provider', 20)->nullable();
            $t->string('playbook', 40)->nullable();
            $t->string('playbook_version', 10)->nullable();
            $t->string('state', 20)->default('ok'); // ok · needs_formatting (internal only)
            $t->unsignedTinyInteger('score')->nullable();
            $t->string('band', 10)->nullable();  // ready · review · blocked
            $t->boolean('blocking')->default(false);
            $t->json('checks')->nullable();
            $t->json('cleanup')->nullable();     // hidden, generic, actions, no_fix
            $t->timestamps();
            $t->softDeletes();
            $t->index(['client_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_brain_items');
    }
};
