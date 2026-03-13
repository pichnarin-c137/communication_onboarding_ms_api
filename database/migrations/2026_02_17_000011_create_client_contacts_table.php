<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('telegram_username', 100)->nullable();
            $table->string('telegram_chat_id', 255)->nullable();
            $table->string('position', 100)->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->onDelete('cascade');

            $table->index('client_id');
            $table->index('telegram_chat_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
