<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_respondents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('position', 100)->nullable();
            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')->on('clients')->cascadeOnDelete();

            $table->index('client_id');
        });

        DB::statement('CREATE UNIQUE INDEX feedback_respondents_email_client_unique ON feedback_respondents (email, client_id) WHERE email IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX feedback_respondents_phone_client_unique ON feedback_respondents (phone_number, client_id) WHERE phone_number IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_respondents');
    }
};
