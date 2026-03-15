<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('chat_id')->unique();
            $table->string('group_name');
            $table->string('bot_status')->default('connected');
            $table->string('language', 5)->default('en');
            $table->uuid('connected_by')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnDelete();

            $table->foreign('connected_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('client_id');
            $table->index('bot_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_groups');
    }
};
