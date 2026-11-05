<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #76 What's new page: platform release notes shown in /app/{agency}/whats-new and managed in /admin/releases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $t) {
            $t->id();
            $t->string('version', 40);
            $t->date('date')->index();
            $t->string('title_en', 200);
            $t->string('title_ar', 200);
            $t->json('items')->nullable();
            $t->boolean('is_published')->default(true)->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
