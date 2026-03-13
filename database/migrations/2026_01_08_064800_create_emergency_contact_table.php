<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_contact', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('contact_first_name');
            $table->string('contact_last_name');
            $table->string('contact_relationship');
            $table->string('contact_phone_number');
            $table->string('contact_address');
            $table->string('contact_social_media')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index('contact_phone_number');
            $table->index('contact_social_media');
            $table->index('contact_relationship');
            $table->index('contact_address');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contact');
    }
};
