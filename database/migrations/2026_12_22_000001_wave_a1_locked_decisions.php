<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            if (! Schema::hasColumn('agencies', 'module_overrides')) {
                $table->json('module_overrides')->nullable()->after('require_two_factor');
            }
        });

        Schema::table('waitlist_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('waitlist_entries', 'invited_at')) {
                $table->timestamp('invited_at')->nullable();
                $table->string('invited_plan', 32)->nullable();
                $table->string('invited_trial_length', 32)->nullable();
                $table->foreignId('provisioned_agency_id')->nullable()->constrained('agencies')->nullOnDelete();
                $table->timestamp('magic_link_expires_at')->nullable();
            }
        });

        Schema::table('partner_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_applications', 'is_published')) {
                $table->boolean('is_published')->default(false);
                $table->string('partner_badge', 48)->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('invited_at')->nullable();
                $table->string('invited_plan', 32)->nullable();
                $table->string('invited_trial_length', 32)->nullable();
                $table->foreignId('provisioned_agency_id')->nullable()->constrained('agencies')->nullOnDelete();
                $table->timestamp('magic_link_expires_at')->nullable();
            }
        });

        if (! Schema::hasTable('workspace_magic_links')) {
            Schema::create('workspace_magic_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plan_upgrade_requests')) {
            Schema::create('plan_upgrade_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('current_plan', 32);
                $table->string('target_plan', 32);
                $table->text('note')->nullable();
                $table->string('status', 24)->default('pending');
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('module_early_access_requests')) {
            Schema::create('module_early_access_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('module_key', 64);
                $table->text('note')->nullable();
                $table->string('status', 24)->default('pending');
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_early_access_requests');
        Schema::dropIfExists('plan_upgrade_requests');
        Schema::dropIfExists('workspace_magic_links');

        Schema::table('partner_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provisioned_agency_id');
            $table->dropColumn([
                'is_published', 'partner_badge', 'published_at',
                'invited_at', 'invited_plan', 'invited_trial_length', 'magic_link_expires_at',
            ]);
        });

        Schema::table('waitlist_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provisioned_agency_id');
            $table->dropColumn(['invited_at', 'invited_plan', 'invited_trial_length', 'magic_link_expires_at']);
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn('module_overrides');
        });
    }
};
