<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->timestamp('changed_at')->useCurrent();
            $table->uuid('changed_by_user_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('onboarding_id')
                ->references('id')->on('onboarding_requests')->cascadeOnDelete();

            $table->foreign('changed_by_user_id')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['onboarding_id', 'changed_at']);
            $table->index(['to_status', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_status_history');
    }
};
