<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTime('reschedule_at')->nullable()->after('reschedule_reason');
            $table->date('reschedule_to_date')->nullable()->after('reschedule_at');
            $table->time('reschedule_to_start_time')->nullable()->after('reschedule_to_date');
            $table->time('reschedule_to_end_time')->nullable()->after('reschedule_to_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['reschedule_at', 'reschedule_to_date', 'reschedule_to_start_time', 'reschedule_to_end_time']);
        });
    }
};
