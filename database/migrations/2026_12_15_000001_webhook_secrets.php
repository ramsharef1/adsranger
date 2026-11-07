<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * #77 / #115 webhook fixes: the HMAC signing secret is no longer the public feed token. The feed token is stored
 * encrypted with a sha256 lookup hash; every webhook gets its own new signing secret; delivery state is recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_webhooks', function (Blueprint $t) {
            $t->string('feed_token_hash', 64)->nullable()->unique()->after('events');
            $t->text('feed_token')->nullable()->after('feed_token_hash');
            $t->text('signing_secret')->nullable()->after('feed_token');
            $t->unsignedSmallInteger('last_status')->nullable()->after('last_triggered_at');
            $t->string('last_error', 300)->nullable()->after('last_status');
            $t->unsignedInteger('failures')->default(0)->after('last_error');
            $t->string('secret_token', 64)->nullable()->change();
        });

        foreach (DB::table('agency_webhooks')->whereNotNull('secret_token')->get() as $w) {
            DB::table('agency_webhooks')->where('id', $w->id)->update([
                'feed_token_hash' => hash('sha256', $w->secret_token),
                'feed_token' => Crypt::encryptString($w->secret_token),
                'signing_secret' => Crypt::encryptString(Str::random(48)),
                'secret_token' => null,
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('agency_webhooks')->whereNull('secret_token')->whereNotNull('feed_token')->get() as $w) {
            DB::table('agency_webhooks')->where('id', $w->id)->update(['secret_token' => Crypt::decryptString($w->feed_token)]);
        }
        DB::table('agency_webhooks')->whereNull('secret_token')->update(['secret_token' => DB::raw("'x'||id")]);

        Schema::table('agency_webhooks', function (Blueprint $t) {
            $t->dropUnique(['feed_token_hash']);
            $t->dropColumn(['feed_token_hash', 'feed_token', 'signing_secret', 'last_status', 'last_error', 'failures']);
            $t->string('secret_token', 64)->nullable(false)->change();
        });
    }
};
