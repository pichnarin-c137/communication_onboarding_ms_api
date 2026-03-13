<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('dob');
            $table->text('address');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('nationality', 100);
            $table->boolean('is_suspended')->default(false);
            $table->timestamps();
            $table->softDeletes(); // For soft deletion

            // Foreign key
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('restrict');

            // Indexes
            $table->index('role_id');
            $table->index('is_suspended');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
