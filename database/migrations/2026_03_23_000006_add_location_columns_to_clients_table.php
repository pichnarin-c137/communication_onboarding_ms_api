<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'headquarter_latitude')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('headquarter_latitude', 50)->nullable()->after('headquarter_address');
                $table->string('headquarter_longitude', 50)->nullable()->after('headquarter_latitude');
            });
        }

        if (! Schema::hasColumn('clients', 'geofence_radius')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->integer('geofence_radius')->default(200)->after('headquarter_longitude');
            });
        }

        DB::statement("SELECT AddGeometryColumn('public', 'clients', 'location', 4326, 'POINT', 2)");
        DB::statement('CREATE INDEX clients_location_gist ON clients USING GIST (location)');

        // Populate location from existing lat/lng columns
        DB::statement("
            UPDATE clients
            SET location = ST_SetSRID(ST_MakePoint(
                CAST(headquarter_longitude AS double precision),
                CAST(headquarter_latitude AS double precision)
            ), 4326)
            WHERE headquarter_latitude IS NOT NULL
              AND headquarter_longitude IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE clients DROP COLUMN IF EXISTS location");

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['geofence_radius', 'headquarter_latitude', 'headquarter_longitude']);
        });
    }
};
