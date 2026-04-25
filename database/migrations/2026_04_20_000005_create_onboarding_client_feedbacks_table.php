<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_client_feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id')->unique();
            $table->smallInteger('rating');
            $table->text('comment')->nullable();
            $table->string('submitted_via', 20);
            $table->uuid('submitted_by_user_id')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->foreign('onboarding_id')
                ->references('id')->on('onboarding_requests')->cascadeOnDelete();

            $table->foreign('submitted_by_user_id')
                ->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE onboarding_client_feedbacks ADD CONSTRAINT onboarding_client_feedbacks_rating_check CHECK (rating >= 1 AND rating <= 5)');
        DB::statement("ALTER TABLE onboarding_client_feedbacks ADD CONSTRAINT onboarding_client_feedbacks_submitted_via_check CHECK (submitted_via IN ('email', 'manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_client_feedbacks');
    }
};
