<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_contact_id')->nullable();
            $table->string('message_type', 50);
            $table->text('message_content');
            $table->string('telegram_message_id', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_contact_id')
                ->references('id')
                ->on('client_contacts')
                ->nullOnDelete();

            $table->index('client_contact_id');
            $table->index('sent_at');
            $table->index('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
