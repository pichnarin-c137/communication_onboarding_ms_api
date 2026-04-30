<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reminder_24h_sent_at')->nullable()->after('is_continued_session');
            $table->timestamp('reminder_1h_sent_at')->nullable()->after('reminder_24h_sent_at');
            $table->timestamp('no_show_notified_at')->nullable()->after('reminder_1h_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['reminder_24h_sent_at', 'reminder_1h_sent_at', 'no_show_notified_at']);
        });
    }
};
