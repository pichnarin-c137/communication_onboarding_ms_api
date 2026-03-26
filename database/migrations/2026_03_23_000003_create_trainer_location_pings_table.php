<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_location_pings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trainer_id');
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->timestamp('pinged_at');
            $table->timestamps();

            $table->foreign('trainer_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['trainer_id', 'pinged_at']);
        });

        DB::statement("SELECT AddGeometryColumn('public', 'trainer_location_pings', 'location', 4326, 'POINT', 2)");
        DB::statement('CREATE INDEX trainer_location_pings_location_gist ON trainer_location_pings USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_location_pings');
    }
};
