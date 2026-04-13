<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_lessons', function (Blueprint $table) {
            $table->unsignedInteger('slot_index')->nullable()->after('path');
            $table->index(['onboarding_id', 'path', 'slot_index'], 'onboarding_lessons_onboarding_path_slot_idx');
        });

        $rows = DB::table('onboarding_lessons')
            ->select(['id', 'onboarding_id', 'path'])
            ->orderBy('onboarding_id')
            ->orderBy('path')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $slotCounters = [];

        foreach ($rows as $row) {
            $key = $row->onboarding_id . '|' . $row->path;
            $slotCounters[$key] = ($slotCounters[$key] ?? 0) + 1;

            DB::table('onboarding_lessons')
                ->where('id', $row->id)
                ->update(['slot_index' => $slotCounters[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('onboarding_lessons', function (Blueprint $table) {
            $table->dropIndex('onboarding_lessons_onboarding_path_slot_idx');
            $table->dropColumn('slot_index');
        });
    }
};
