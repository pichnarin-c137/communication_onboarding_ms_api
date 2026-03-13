<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('client_contact_id')->nullable();
            $table->string('type', 50)->comment('e.g. assignment_created, session_scheduled');
            $table->string('title', 255);
            $table->text('message');
            $table->string('related_entity_type', 50)->nullable();
            $table->uuid('related_entity_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('client_contact_id')
                ->references('id')
                ->on('client_contacts')
                ->onDelete('cascade');

            $table->index('user_id');
            $table->index('client_contact_id');
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
