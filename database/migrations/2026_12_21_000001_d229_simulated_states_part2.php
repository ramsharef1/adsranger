<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-229 part 2 (Features' live check 12:45): Studio launches, organic posts, inbox replies and budget shifts that
 * never reached a network are relabelled `simulated`. Their modules stay off until rebuilt. Rows are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('network_launches')->where('status', 'published')->update(['status' => 'simulated', 'published_at' => null]);
        DB::table('social_posts')->where('status', 'published')->update(['status' => 'simulated', 'published_at' => null]);
        DB::table('social_inbox_messages')->where('status', 'replied')->update(['status' => 'simulated', 'replied_at' => null]);
        DB::table('budget_reallocation_logs')->where('status', 'applied')->update(['status' => 'simulated']);
    }

    public function down(): void
    {
        // One-way relabel: the original fake states are not restored.
    }
};
