<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_code')->unique();
            $table->uuid('appointment_id');
            $table->uuid('client_id');
            $table->uuid('system_id');
            $table->uuid('trainer_id');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('restrict');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('system_id')->references('id')->on('systems')->onDelete('restrict');
            $table->foreign('trainer_id')->references('id')->on('users')->onDelete('restrict');

            $table->index('status');
            $table->index('client_id');
            $table->index('trainer_id');
        });

        // Add FK from appointments back to onboarding_requests
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('related_onboarding_id')->references('id')->on('onboarding_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['related_onboarding_id']);
        });
        Schema::dropIfExists('onboarding_requests');
    }
};
