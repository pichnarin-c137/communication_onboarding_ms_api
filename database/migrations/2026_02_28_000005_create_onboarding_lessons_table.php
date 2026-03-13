<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_id');
            $table->tinyInteger('path'); // 1|2|3
            $table->uuid('lesson_document_id')->nullable();
            $table->string('lesson_video_url', 500)->nullable();
            $table->boolean('is_sent')->default(false);
            $table->dateTime('sent_at')->nullable();
            $table->uuid('sent_by_user_id')->nullable();
            $table->uuid('telegram_message_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('onboarding_id')->references('id')->on('onboarding_requests')->onDelete('cascade');
            $table->foreign('lesson_document_id')->references('id')->on('media')->nullOnDelete();
            $table->foreign('sent_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('telegram_message_id')->references('id')->on('telegram_messages')->nullOnDelete();

            $table->index('onboarding_id');
            $table->index('is_sent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_lessons');
    }
};
