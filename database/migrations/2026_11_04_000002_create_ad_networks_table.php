<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_networks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category');
            $table->unsignedSmallInteger('stage')->default(1);
            $table->string('status')->default('not_set');
            $table->boolean('enabled')->default(false);
            $table->string('environment')->default('sandbox');
            $table->text('credentials_live')->nullable();
            $table->text('credentials_sandbox')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        foreach (config('ad_networks', []) as $key => $network) {
            DB::table('ad_networks')->insert([
                'key' => $key,
                'name' => $network['name'],
                'category' => $network['category'],
                'stage' => $network['stage'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ad_networks');
    }
};
