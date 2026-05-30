<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            // A demo for a CRM prospect has no client yet, so client_id may be null.
            $table->uuid('client_id')->nullable()->change();

            // Demo target when the prospect is not yet a client.
            $table->uuid('crm_contact_id')->nullable()->after('client_id');
            // The deal this demo belongs to (drives stage auto-sync).
            $table->uuid('crm_deal_id')->nullable()->after('crm_contact_id');

            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts')->nullOnDelete();
            $table->foreign('crm_deal_id')->references('id')->on('crm_deals')->nullOnDelete();

            $table->index('crm_contact_id');
            $table->index('crm_deal_id');
        });

        // Stamp set when the linked demo appointment completes (deal stays in funnel).
        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->timestamp('demo_completed_at')->nullable()->after('won_at');
        });

        // Exactly-one-target guarantee:
        //   training -> client_id only (no CRM links)
        //   demo     -> exactly one of client_id (existing customer) or crm_contact_id (prospect)
        DB::statement("
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_type_target_chk CHECK (
                (
                    appointment_type = 'training'
                    AND client_id IS NOT NULL
                    AND crm_contact_id IS NULL
                    AND crm_deal_id IS NULL
                )
                OR
                (
                    appointment_type = 'demo'
                    AND ((client_id IS NOT NULL) <> (crm_contact_id IS NOT NULL))
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_type_target_chk');

        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->dropColumn('demo_completed_at');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign(['crm_contact_id']);
            $table->dropForeign(['crm_deal_id']);
            $table->dropIndex(['crm_contact_id']);
            $table->dropIndex(['crm_deal_id']);
            $table->dropColumn(['crm_contact_id', 'crm_deal_id']);
        });

        // Restore NOT NULL only when no demo rows rely on a null client_id.
        if (! DB::table('appointments')->whereNull('client_id')->exists()) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->uuid('client_id')->nullable(false)->change();
            });
        }
    }
};
