<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_company_info', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id')->unique();
            $table->text('content')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->uuid('completed_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('onboarding_id')->references('id')->on('onboarding_requests')->onDelete('cascade');
            $table->foreign('completed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_company_info');
    }
};
