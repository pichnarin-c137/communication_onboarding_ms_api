<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convert all existing timestamps from Asia/Phnom_Penh (+07:00) to UTC.
 *
 * Background: APP_TIMEZONE was changed from Asia/Phnom_Penh to UTC so the DB
 * stores universal timestamps. All previously stored values were written in
 * local Phnom Penh time (UTC+7), so we subtract 7 hours to correct them.
 *
 * Excluded: Laravel internal tables (migrations, jobs, telescope_*).
 */
return new class extends Migration
{
    private const EXCLUDED_TABLES = [
        'migrations',
        'jobs',
        'failed_jobs',
        'job_batches',
        'telescope_entries',
        'telescope_monitoring',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach ($this->timestampColumnsByTable() as $table => $columns) {
                $this->shiftTable($table, $columns, '-');
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach ($this->timestampColumnsByTable() as $table => $columns) {
                $this->shiftTable($table, $columns, '+');
            }
        });
    }

    private function shiftTable(string $table, array $columns, string $direction): void
    {
        $sets = implode(', ', array_map(
            fn ($col) => "\"$col\" = CASE WHEN \"$col\" IS NOT NULL THEN \"$col\" {$direction} INTERVAL '7 hours' ELSE NULL END",
            $columns
        ));

        DB::statement("UPDATE \"$table\" SET $sets");
    }

    /** @return array<string, string[]> */
    private function timestampColumnsByTable(): array
    {
        $excluded = implode(
            ', ',
            array_map(fn ($t) => "'$t'", self::EXCLUDED_TABLES)
        );

        $rows = DB::select("
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND data_type IN ('timestamp without time zone', 'timestamp with time zone')
              AND table_name NOT IN ({$excluded})
            ORDER BY table_name, column_name
        ");

        $byTable = [];
        foreach ($rows as $row) {
            $byTable[$row->table_name][] = $row->column_name;
        }

        return $byTable;
    }
};
