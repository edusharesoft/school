<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PerformanceAudit extends Command
{
    protected $signature = 'performance:audit {--rows=1000 : Only tables with at least this many rows are marked as "large"}';

    protected $description = 'List *_id / status columns that appear in no index (foreign keys are skipped: MySQL indexes them by itself) and the biggest tables';

    public function handle(): int
    {
        $missing = [];
        $sizes = [];
        foreach (\App\Services\Backup\BackupService::tableNames() as $table) {
            if (in_array($table, ['migrations'], true)) {
                continue;
            }
            $indexed = [];
            foreach (Schema::getIndexes($table) as $index) {
                // status is low-cardinality: it only helps inside a composite index; ids are covered when they appear in any index.
                $indexed = array_merge($indexed, $index['columns'] ?? []);
            }
            $fk = collect(Schema::getForeignKeys($table))->pluck('columns')->flatten()->all();
            foreach (Schema::getColumnListing($table) as $col) {
                if ((str_ends_with($col, '_id') || $col === 'status') && ! in_array($col, $indexed, true) && ! in_array($col, $fk, true)) {
                    $missing[] = [$table, $col];
                }
            }
            $sizes[$table] = DB::table($table)->count();
        }
        arsort($sizes);

        $this->info('Columns that are filtered / joined on but appear in no index:');
        $this->table(['Table', 'Column'], $missing ?: [['—', 'none']]);
        $this->info('Largest tables:');
        $this->table(['Table', 'Rows'], collect($sizes)->take(10)->map(fn ($n, $t) => [$t, number_format($n)])->values()->all());
        $large = collect($sizes)->filter(fn ($n) => $n >= (int) $this->option('rows'))->count();
        $this->line("{$large} table(s) have at least ".(int) $this->option('rows').' rows.');

        return self::SUCCESS;
    }
}
