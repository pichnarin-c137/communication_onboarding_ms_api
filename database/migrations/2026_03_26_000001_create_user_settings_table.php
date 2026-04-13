<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Notification preferences
            $table->boolean('in_app_notifications')->default(true);
            $table->boolean('telegram_notifications')->default(true);

            // Language & timezone
            $table->string('language', 5)->default('en');
            $table->string('timezone', 64)->default('Asia/Phnom_Penh');

            // Display preferences
            $table->unsignedSmallInteger('items_per_page')->default(15);
            $table->string('theme', 10)->default('light');

            // Quiet hours
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->string('quiet_hours_start', 5)->default('22:00');
            $table->string('quiet_hours_end', 5)->default('07:00');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
