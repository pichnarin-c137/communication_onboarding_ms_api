<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('sale_order_code')->unique();
            $table->uuid('client_id');
            $table->uuid('created_by');
            $table->json('content')->nullable();
            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->restrictOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->index('client_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
