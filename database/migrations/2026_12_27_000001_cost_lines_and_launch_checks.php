<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A-4 (R2-70) monthly cost lines vs the cap · A-8 (IDEA-049) launch go / no-go list. Platform-level, no agency_id. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_lines', function (Blueprint $table) {
            $table->id();
            $table->date('month');
            $table->string('category', 32);
            $table->decimal('amount_usd', 10, 2);
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('month');
        });

        Schema::create('launch_checks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('owner', 40);
            $table->date('due_on')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('note', 300)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Starting lines: what is known to block launch today (002 / 920 §5). No dates: Rami sets them.
        $now = now();
        $rows = [
            ['Company (LLC) set up', 'Rami'],
            ['Live ad-network APIs verified one by one (A-11 board)', 'E1'],
            ['Email provider picked and connected (D-251)', 'Rami'],
            ['Billing provider live in test, then live', 'E1'],
            ['Off-server encrypted backup (R2) running', 'E1'],
            ['Restore test passed this quarter (A-5)', 'E1'],
            ['Privacy, terms and consent wording checked (Business)', 'Business'],
            ['Error and uptime alerts reach Rami (A-3)', 'E1'],
            ['Preview build per change in use (A-1)', 'E1'],
            ['Product EX check of every module that opens', 'Product'],
        ];
        foreach ($rows as $i => [$title, $owner]) {
            DB::table('launch_checks')->insert(['title' => $title, 'owner' => $owner, 'status' => 'open', 'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('launch_checks');
        Schema::dropIfExists('cost_lines');
    }
};
