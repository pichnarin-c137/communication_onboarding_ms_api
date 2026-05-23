<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_trainer_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sale_user_id');
            $table->uuid('trainer_user_id');
            $table->uuid('assigned_by_id')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sale_user_id')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->foreign('trainer_user_id')
                ->references('id')->on('users')->restrictOnDelete();

            $table->foreign('assigned_by_id')
                ->references('id')->on('users')->nullOnDelete();

            $table->index('sale_user_id');
            $table->index('trainer_user_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX sale_trainer_assignments_sale_trainer_active_unique '
            .'ON sale_trainer_assignments (sale_user_id, trainer_user_id) '
            .'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sale_trainer_assignments_sale_trainer_active_unique');
        Schema::dropIfExists('sale_trainer_assignments');
    }
};
