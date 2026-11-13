<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** XS-03 / FS-20 / R2-56 (R4): "what we did" and "next month" notes per client and month, for the monthly report. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_month_notes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('month', 7); // YYYY-MM
            $t->text('done')->nullable();
            $t->text('next')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['client_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_month_notes');
    }
};
