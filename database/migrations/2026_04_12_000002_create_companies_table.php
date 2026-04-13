<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name_en', 255);
            $table->string('name_km', 255);
            $table->uuid('business_type_id');
            $table->string('owner_name_en', 255);
            $table->string('owner_name_km', 255);
            $table->string('phone', 20);
            $table->text('address_km');
            $table->uuid('logo_media_id')->nullable();
            $table->uuid('patent_document_media_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_type_id')
                ->references('id')->on('business_types')
                ->restrictOnDelete();

            $table->foreign('logo_media_id')
                ->references('id')->on('media')
                ->nullOnDelete();

            $table->foreign('patent_document_media_id')
                ->references('id')->on('media')
                ->nullOnDelete();

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('business_type_id');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
