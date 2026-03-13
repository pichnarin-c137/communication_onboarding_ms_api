<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds profile_image_id, reset_token, and reset_token_expires_at to the
 * existing credentials table (created after media table is available).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->uuid('profile_image_id')->nullable()->after('password');
            $table->string('reset_token', 255)->nullable()->after('profile_image_id');
            $table->timestamp('reset_token_expires_at')->nullable()->after('reset_token');

            $table->foreign('profile_image_id')
                ->references('id')
                ->on('media')
                ->onDelete('set null');

            $table->index('reset_token');
        });
    }

    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropForeign(['profile_image_id']);
            $table->dropIndex(['reset_token']);
            $table->dropColumn(['profile_image_id', 'reset_token', 'reset_token_expires_at']);
        });
    }
};
