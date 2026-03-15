<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the old telegram_messages table (client-contact based)
     * and create the new one (telegram-group based).
     *
     * The old migration (2026_02_17_000019) created the original table.
     * The onboarding_lessons table has a FK on telegram_message_id → telegram_messages.
     * We must drop that FK first, drop and recreate the table, then restore the FK.
     */
    public function up(): void
    {
        // Drop the FK from onboarding_lessons that references the old telegram_messages table
        Schema::table('onboarding_lessons', function (Blueprint $table) {
            $table->dropForeign(['telegram_message_id']);
        });

        // Drop the old table and create the new one
        Schema::dropIfExists('telegram_messages');

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('telegram_group_id');
            $table->string('message_type');
            $table->text('message_body');
            $table->string('language', 5);
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('telegram_group_id')
                ->references('id')
                ->on('telegram_groups')
                ->cascadeOnDelete();

            $table->index('telegram_group_id');
            $table->index('status');
            $table->index('message_type');
            $table->index('sent_at');
        });

        // Restore the FK from onboarding_lessons to the new telegram_messages table
        Schema::table('onboarding_lessons', function (Blueprint $table) {
            $table->foreign('telegram_message_id')
                ->references('id')
                ->on('telegram_messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop FK from onboarding_lessons to new telegram_messages
        Schema::table('onboarding_lessons', function (Blueprint $table) {
            $table->dropForeign(['telegram_message_id']);
        });

        Schema::dropIfExists('telegram_messages');

        // Restore the original telegram_messages structure
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
        });

        // Restore FK from onboarding_lessons to original telegram_messages
        Schema::table('onboarding_lessons', function (Blueprint $table) {
            $table->foreign('telegram_message_id')
                ->references('id')
                ->on('telegram_messages')
                ->nullOnDelete();
        });
    }
};
