<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_system_access', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('system_id');
            $table->timestamp('granted_at')->useCurrent();
            $table->uuid('granted_by')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('system_id')
                ->references('id')
                ->on('systems')
                ->onDelete('cascade');

            $table->foreign('granted_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique(['user_id', 'system_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_system_access');
    }
};
