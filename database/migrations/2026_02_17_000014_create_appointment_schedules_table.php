<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->uuid('system_id');
            $table->enum('appointment_type', ['training', 'demo'])->default('training');
            $table->enum('location_type', ['physical', 'online', 'hybrid'])->default('online');
            $table->enum('status', ['pending', 'leave_office', 'in_progress', 'done', 'cancelled', 'rescheduled'])->default('pending');
            $table->uuid('trainer_id')->nullable();
            $table->uuid('client_id');
            $table->uuid('creator_id');
            $table->text('notes')->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->text('physical_location')->nullable();
            $table->date('scheduled_date');
            $table->time('scheduled_start_time');
            $table->time('scheduled_end_time');
            $table->dateTime('actual_start_time')->nullable();
            $table->dateTime('actual_end_time')->nullable();
            $table->uuid('start_proof_media_id')->nullable();
            $table->uuid('end_proof_media_id')->nullable();
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();
            $table->dateTime('leave_office_at')->nullable();
            $table->decimal('leave_office_lat', 10, 7)->nullable();
            $table->decimal('leave_office_lng', 10, 7)->nullable();
            $table->integer('student_count')->default(0);
            $table->text('completion_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('cancelled_by_user_id')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->boolean('is_onboarding_triggered')->default(false);
            $table->boolean('is_continued_session')->default(false);
            $table->uuid('related_onboarding_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('trainer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('system_id')->references('id')->on('systems')->onDelete('restrict');
            $table->foreign('start_proof_media_id')->references('id')->on('media')->nullOnDelete();
            $table->foreign('end_proof_media_id')->references('id')->on('media')->nullOnDelete();
            $table->foreign('cancelled_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('appointment_type');
            $table->index('status');
            $table->index('scheduled_date');
            $table->index('trainer_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
