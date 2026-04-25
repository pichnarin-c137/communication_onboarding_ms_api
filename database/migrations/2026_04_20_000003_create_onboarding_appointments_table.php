<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id');
            $table->uuid('appointment_id');
            $table->string('session_type', 20);
            $table->uuid('linked_by_user_id')->nullable();
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamps();

            $table->foreign('onboarding_id')
                ->references('id')->on('onboarding_requests')->cascadeOnDelete();

            $table->foreign('appointment_id')
                ->references('id')->on('appointments')->cascadeOnDelete();

            $table->foreign('linked_by_user_id')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(['onboarding_id', 'appointment_id']);
            $table->index('onboarding_id');
            $table->index('appointment_id');
        });

        DB::statement("ALTER TABLE onboarding_appointments ADD CONSTRAINT onboarding_appointments_session_type_check CHECK (session_type IN ('primary', 'supplemental', 'retraining'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_appointments');
    }
};
