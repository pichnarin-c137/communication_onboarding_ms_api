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
        Schema::table('media', function (Blueprint $table) {
            $table->string('filename', 255)->nullable()->change();
            $table->string('original_filename', 255)->nullable()->change();
            $table->string('file_path', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('filename', 255)->nullable(false)->change();
            $table->string('original_filename', 255)->nullable(false)->change();
            $table->string('file_path', 500)->nullable(false)->change();
        });
    }
};
