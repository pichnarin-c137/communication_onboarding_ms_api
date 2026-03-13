<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('filename', 255);
            $table->string('original_filename', 255);
            $table->string('file_path', 500);
            $table->string('file_url', 500);
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->enum('media_category', ['profile', 'logo', 'banner', 'document', 'other'])->default('other');
            $table->uuid('uploaded_by_user_id')->nullable();
            $table->string('cloudinary_public_id', 255)->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('media_category');
            $table->index('uploaded_by_user_id');
            $table->index('cloudinary_public_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
