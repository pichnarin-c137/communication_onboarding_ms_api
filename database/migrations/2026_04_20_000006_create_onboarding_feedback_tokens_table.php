<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_feedback_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id')->unique();
            $table->string('token', 64)->unique();
            $table->string('client_email', 255);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('onboarding_id')
                ->references('id')->on('onboarding_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_feedback_tokens');
    }
};
