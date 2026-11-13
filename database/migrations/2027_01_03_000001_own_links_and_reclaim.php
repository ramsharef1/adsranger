<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BX-07 link monitor (own links) + BX-09 link reclamation (404 log, GSC "top linked pages", 301s added from /admin).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_links', function (Blueprint $t) {
            $t->id();
            $t->string('source_url', 500)->unique();
            $t->string('name', 160)->nullable();
            $t->string('note', 300)->nullable();
            $t->string('status', 16)->default('unchecked'); // unchecked · live · lost · archived
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('target_url', 500)->nullable();
            $t->string('rel', 80)->nullable();
            $t->string('anchor', 200)->nullable();
            $t->string('anchor_kind', 16)->nullable(); // brand · exact · topic · image
            $t->unsignedSmallInteger('links_found')->default(0);
            $t->timestamp('checked_at')->nullable();
            $t->timestamp('lost_at')->nullable();
            $t->timestamps();
        });
        Schema::create('missing_pages', function (Blueprint $t) {
            $t->id();
            $t->string('path', 300)->unique();
            $t->unsignedInteger('hits')->default(0);
            $t->string('last_referrer_host', 190)->nullable();
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->boolean('ignored')->default(false);
        });
        Schema::create('gsc_linked_pages', function (Blueprint $t) {
            $t->id();
            $t->string('path', 300)->unique();
            $t->unsignedInteger('links')->default(0);
            $t->unsignedInteger('sites')->default(0);
            $t->timestamp('imported_at')->nullable();
        });
        Schema::create('link_redirects', function (Blueprint $t) {
            $t->id();
            $t->string('from_path', 300)->unique();
            $t->string('to_path', 300);
            $t->boolean('active')->default(true);
            $t->unsignedInteger('hits')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_redirects');
        Schema::dropIfExists('gsc_linked_pages');
        Schema::dropIfExists('missing_pages');
        Schema::dropIfExists('own_links');
    }
};
