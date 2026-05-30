<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_name', 255);
            $table->string('company_name_kh', 255)->nullable();
            $table->string('contact_name', 255);
            $table->string('phone', 30);
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->uuid('business_type_id')->nullable();
            $table->string('source', 50)->nullable()->comment('referral|cold_call|website|event|other');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('prospect')->comment('prospect|deal_active|won|lost');
            $table->uuid('synced_client_id')->nullable()->comment('Client created when a deal was won (dedupe link)');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_type_id')
                ->references('id')->on('business_types')->nullOnDelete();
            $table->foreign('synced_client_id')
                ->references('id')->on('clients')->nullOnDelete();
            $table->foreign('created_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->index('company_name');
            $table->index('status');
            $table->index('business_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
