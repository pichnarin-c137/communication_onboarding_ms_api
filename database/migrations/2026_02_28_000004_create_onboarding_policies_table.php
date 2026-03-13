<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id');
            $table->string('policy_name', 255);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_checked')->default(false);
            $table->dateTime('checked_at')->nullable();
            $table->uuid('checked_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('onboarding_id')->references('id')->on('onboarding_requests')->onDelete('cascade');
            $table->foreign('checked_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('onboarding_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_policies');
    }
};
