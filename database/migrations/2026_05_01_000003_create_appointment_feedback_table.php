<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('appointment_id');
            $table->uuid('token_id');
            $table->uuid('respondent_id');
            $table->smallInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->foreign('appointment_id')
                ->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('token_id')
                ->references('id')->on('appointment_feedback_tokens')->cascadeOnDelete();
            $table->foreign('respondent_id')
                ->references('id')->on('feedback_respondents')->cascadeOnDelete();

            $table->unique(['respondent_id', 'appointment_id']);
        });

        DB::statement('ALTER TABLE appointment_feedback ADD CONSTRAINT appointment_feedback_rating_check CHECK (rating >= 1 AND rating <= 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_feedback');
    }
};
