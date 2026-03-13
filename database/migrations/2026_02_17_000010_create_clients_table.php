<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique()->comment('Auto-generated client code');
            $table->string('company_code', 100)->nullable()->comment("Client's own registration code");
            $table->string('company_name', 255);
            $table->string('phone_number', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('headquarter_address')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('assigned_sale_id');
            $table->uuid('banner_image_id')->nullable();
            $table->uuid('logo_image_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('assigned_sale_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('banner_image_id')
                ->references('id')
                ->on('media')
                ->onDelete('set null');

            $table->foreign('logo_image_id')
                ->references('id')
                ->on('media')
                ->onDelete('set null');

            $table->index('code');
            $table->index('assigned_sale_id');
            $table->index('company_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
