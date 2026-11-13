<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** XS-16 / FS-04 / WS-31 (R5): one dialect per client; null means "from the client's market". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $t): void {
            $t->string('dialect', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $t): void {
            $t->dropColumn('dialect');
        });
    }
};
