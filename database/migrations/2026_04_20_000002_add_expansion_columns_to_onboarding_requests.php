<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_requests', function (Blueprint $table) {
            $table->string('hold_reason', 500)->nullable()->after('status');
            $table->timestamp('hold_started_at')->nullable()->after('hold_reason');
            $table->smallInteger('hold_count')->default(0)->after('hold_started_at');
            $table->string('revision_note', 1000)->nullable()->after('hold_count');
            $table->timestamp('revision_requested_at')->nullable()->after('revision_note');
            $table->uuid('revision_requested_by_user_id')->nullable()->after('revision_requested_at');
            $table->date('due_date')->nullable()->after('revision_requested_by_user_id');
            $table->timestamp('sla_breached_at')->nullable()->after('due_date');
            $table->uuid('parent_onboarding_id')->nullable()->after('sla_breached_at');
            $table->smallInteger('cycle_number')->default(1)->after('parent_onboarding_id');
            $table->timestamp('reopened_at')->nullable()->after('cycle_number');
            $table->uuid('reopened_by_user_id')->nullable()->after('reopened_at');

            $table->foreign('revision_requested_by_user_id')
                ->references('id')->on('users')->nullOnDelete();

            $table->foreign('parent_onboarding_id')
                ->references('id')->on('onboarding_requests')->nullOnDelete();

            $table->foreign('reopened_by_user_id')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_requests', function (Blueprint $table) {
            $table->dropForeign(['revision_requested_by_user_id']);
            $table->dropForeign(['parent_onboarding_id']);
            $table->dropForeign(['reopened_by_user_id']);

            $table->dropColumn([
                'hold_reason',
                'hold_started_at',
                'hold_count',
                'revision_note',
                'revision_requested_at',
                'revision_requested_by_user_id',
                'due_date',
                'sla_breached_at',
                'parent_onboarding_id',
                'cycle_number',
                'reopened_at',
                'reopened_by_user_id',
            ]);
        });
    }
};
