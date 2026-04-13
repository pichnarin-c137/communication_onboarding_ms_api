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
        Schema::table('onboarding_policies', function (Blueprint $table) {
            $table->timestamp('unchecked_at')->nullable()->after('checked_at');
            $table->uuid('unchecked_by_user_id')->nullable();

            $table->foreign('unchecked_by_user_id')->references('id')->on('users')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_policies', function (Blueprint $table) {
            $table->dropColumn('unchecked_at');
            $table->dropColumn('unchecked_by_user_id');
        });
    }
};
