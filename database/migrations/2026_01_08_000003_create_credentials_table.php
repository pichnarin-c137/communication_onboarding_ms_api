<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('phone_number', 20)->unique();
            $table->string('password'); // bcrypt hashed
            $table->string('otp', 4)->nullable();
            $table->timestamp('otp_expiry')->nullable();
            $table->timestamps();

            // Foreign key with cascade delete
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Indexes for login lookups
            $table->index('email');
            $table->index('username');
            $table->index('phone_number');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
