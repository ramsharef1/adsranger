<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_operational_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('audit_type', 48);
            $t->string('status', 24)->default('healthy');
            $t->decimal('primary_metric', 16, 4)->default(0);
            $t->json('details');
            $t->timestamps();
            $t->index(['agency_id', 'audit_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_operational_audits');
    }
};