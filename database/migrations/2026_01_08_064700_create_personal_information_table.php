<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_information', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('professtional_photo')->nullable();
            $table->string('nationality_card')->nullable();
            $table->string('family_book')->nullable();
            $table->string('birth_certificate')->nullable();
            $table->string('degreee_certificate')->nullable();
            $table->string('social_media')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('professtional_photo');
            $table->index('nationality_card');
            $table->index('family_book');
            $table->index('birth_certificate');
            $table->index('degreee_certificate');
            $table->index('social_media');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_information');
    }
};
