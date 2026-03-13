<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_system_analysis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id')->unique();
            $table->integer('import_employee_count')->default(0);
            $table->integer('connected_app_count')->default(0);
            $table->integer('profile_mobile_count')->default(0);
            $table->timestamps();

            $table->foreign('onboarding_id')->references('id')->on('onboarding_requests')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_system_analysis');
    }
};
