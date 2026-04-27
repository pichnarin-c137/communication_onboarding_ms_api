<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_revision_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id');
            $table->string('note', 1000);
            $table->uuid('requested_by_user_id')->nullable();
            $table->timestamp('requested_at');
            $table->uuid('acknowledged_by_user_id')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->foreign('onboarding_id')
                ->references('id')->on('onboarding_requests')->cascadeOnDelete();

            $table->foreign('requested_by_user_id')
                ->references('id')->on('users')->nullOnDelete();

            $table->foreign('acknowledged_by_user_id')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_revision_history');
    }
};
