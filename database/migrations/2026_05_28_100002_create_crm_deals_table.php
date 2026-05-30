<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('crm_contact_id');
            $table->string('title', 255);
            $table->string('stage', 30)->default('prospect')
                ->comment('prospect|demo_scheduled|proposal_sent|negotiating|won|lost');
            $table->decimal('value', 12, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->uuid('client_id')->nullable()->comment('Set when the deal is won and synced to clients');
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('crm_contact_id')
                ->references('id')->on('crm_contacts')->cascadeOnDelete();
            $table->foreign('assigned_to')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('client_id')
                ->references('id')->on('clients')->nullOnDelete();
            $table->foreign('created_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->index('crm_contact_id');
            $table->index('stage');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};
