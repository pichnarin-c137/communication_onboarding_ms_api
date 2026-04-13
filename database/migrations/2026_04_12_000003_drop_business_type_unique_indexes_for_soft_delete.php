<?php

return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void
    {
        \Illuminate\Support\Facades\Schema::table('business_types', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->dropUnique('business_types_name_en_unique');
            $table->dropUnique('business_types_name_km_unique');
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\Schema::table('business_types', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->unique('name_en');
            $table->unique('name_km');
        });
    }
};
