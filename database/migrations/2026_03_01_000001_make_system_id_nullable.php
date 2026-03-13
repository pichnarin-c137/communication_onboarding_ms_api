<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('system_id');
        });

        Schema::table('onboarding_requests', function (Blueprint $table) {
            $table->dropColumn('system_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->uuid('system_id')->nullable();
        });

        Schema::table('onboarding_requests', function (Blueprint $table) {
            $table->uuid('system_id')->nullable();
        });
    }
};
