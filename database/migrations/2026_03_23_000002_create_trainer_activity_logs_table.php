<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trainer_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('appointment_id')->nullable();
            $table->string('status', 20);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->string('detection_method', 20)->default('manual');
            $table->timestamps();

            $table->foreign('trainer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            $table->index(['trainer_id', 'created_at']);
        });

        DB::statement("SELECT AddGeometryColumn('public', 'trainer_activity_logs', 'location', 4326, 'POINT', 2)");
        DB::statement('CREATE INDEX trainer_activity_logs_location_gist ON trainer_activity_logs USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_activity_logs');
    }
};
