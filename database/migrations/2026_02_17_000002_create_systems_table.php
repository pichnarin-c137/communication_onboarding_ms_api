<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('systems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('systems')->insert([
            ['id' => 'aaaaaaaa-0000-0000-0000-000000000001', 'name' => 'coms', 'description' => 'Customer Onboarding Management System', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'aaaaaaaa-0000-0000-0000-000000000002', 'name' => 'crm', 'description' => 'Customer Relationship Management', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'aaaaaaaa-0000-0000-0000-000000000003', 'name' => 'hrms', 'description' => 'Human Resource Management System', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Add customer role if not already seeded
        if (! DB::table('roles')->where('role', 'customer')->exists()) {
            DB::table('roles')->insert([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'role' => 'customer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('role', 'customer')->delete();
        Schema::dropIfExists('systems');
    }
};
