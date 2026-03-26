<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('from_label', 255);
            $table->string('to_label', 255);
            $table->json('waypoints');
            $table->integer('duration_seconds')->nullable();
            $table->integer('distance_meters')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_routes');
    }
};
