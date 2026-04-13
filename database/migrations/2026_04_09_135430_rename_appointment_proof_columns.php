<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->renameColumn('start_proof_media_id', 'start_proof_media');
            $table->renameColumn('end_proof_media_id', 'end_proof_media');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->renameColumn('start_proof_media', 'start_proof_media_id');
            $table->renameColumn('end_proof_media', 'end_proof_media_id');
        });
    }
};
