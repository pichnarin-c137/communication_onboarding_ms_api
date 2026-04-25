<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_trainer_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id');
            $table->uuid('trainer_id');
            $table->uuid('assigned_by_id')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->boolean('is_current')->default(true);
            $table->timestamp('replaced_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('onboarding_id')
                ->references('id')->on('onboarding_requests')->cascadeOnDelete();

            $table->foreign('trainer_id')
                ->references('id')->on('users')->restrictOnDelete();

            $table->foreign('assigned_by_id')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_trainer_assignments');
    }
};
