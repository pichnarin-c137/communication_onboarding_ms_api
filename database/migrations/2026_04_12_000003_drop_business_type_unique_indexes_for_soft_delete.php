<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table): void {
            $table->dropUnique('business_types_name_en_unique');
            $table->dropUnique('business_types_name_km_unique');
        });
    }

    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table): void {
            $table->unique('name_en');
            $table->unique('name_km');
        });
    }
};
