<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('appointment_id');
            $table->uuid('media_id');
            $table->enum('material_type', ['lesson_video', 'lesson_document', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->softDeletes();

            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade');

            $table->index('appointment_id');
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_materials');
    }
};
