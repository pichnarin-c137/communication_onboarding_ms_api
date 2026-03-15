<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('chat_id');
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index('chat_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_events');
    }
};
