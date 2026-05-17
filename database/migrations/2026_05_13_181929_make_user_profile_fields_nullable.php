<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->change();
            $table->string('last_name', 100)->nullable()->change();
            $table->date('dob')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('nationality', 100)->nullable()->change();
        });

        // PostgreSQL does not support ALTER COLUMN on enum via change() — use raw SQL
        DB::statement('ALTER TABLE users ALTER COLUMN gender DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable(false)->change();
            $table->string('last_name', 100)->nullable(false)->change();
            $table->date('dob')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('nationality', 100)->nullable(false)->change();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN gender SET NOT NULL');
    }
};
