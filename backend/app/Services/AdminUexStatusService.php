<?php

namespace App\Services;

use App\Models\UexSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminUexStatusService
{
    public function build(): array
    {
        $definitions = UexResourceRegistry::definitions();
        $countsByTable = [];

        foreach ($definitions as $definition) {
            $table = $definition['table'];
            $countsByTable[$table] = Schema::hasTable($table)
                ? DB::table($table)->count()
                : 0;
        }

        $groups = collect(UexResourceRegistry::groups())
            ->map(function (array $resourceNames, string $group) use ($definitions, $countsByTable) {
                $resources = collect($resourceNames)
                    ->map(function (string $resourceName) use ($definitions, $countsByTable) {
                        $definition = $definitions[$resourceName];
                        $table = $definition['table'];

                        return [
                            'resource' => $resourceName,
                            'label' => $definition['label'],
                            'table' => $table,
                            'rows' => $countsByTable[$table] ?? 0,
                        ];
                    })
                    ->values();

                return [
                    'key' => $group,
                    'label' => str($group)->replace('_', ' ')->title()->toString(),
                    'resources' => $resources->all(),
                    'resource_count' => $resources->count(),
                    'row_count' => $resources->sum('rows'),
                ];
            })
            ->values();

        $lastRun = Schema::hasTable('uex_sync_runs')
            ? UexSyncRun::query()->orderByDesc('started_at')->orderByDesc('id')->first()
            : null;

        return [
            'commands' => [
                'all' => 'php artisan uex:sync all',
                'locations' => 'php artisan uex:sync locations',
                'trade' => 'php artisan uex:sync trade',
                'vehicles' => 'php artisan uex:sync vehicles',
                'industry' => 'php artisan uex:sync industry',
                'commodity_prices' => 'php artisan uex:sync --resource=commodities_prices_all',
            ],
            'sync_actions' => [
                'all' => ['scope' => 'all', 'resources' => []],
                'locations' => ['scope' => 'locations', 'resources' => []],
                'trade' => ['scope' => 'trade', 'resources' => []],
                'vehicles' => ['scope' => 'vehicles', 'resources' => []],
                'industry' => ['scope' => 'industry', 'resources' => []],
                'commodity_prices' => ['scope' => 'all', 'resources' => ['commodities_prices_all']],
            ],
            'groups' => $groups->all(),
            'total_rows' => $groups->sum('row_count'),
            'last_run' => $lastRun ? [
                'id' => $lastRun->id,
                'scope' => $lastRun->scope,
                'status' => $lastRun->status,
                'requested_resources' => $lastRun->requested_resources ?? [],
                'resource_results' => $lastRun->resource_results ?? [],
                'total_records' => $lastRun->total_records,
                'successful_resources' => $lastRun->successful_resources,
                'failed_resources' => $lastRun->failed_resources,
                'error_message' => $lastRun->error_message,
                'started_at' => $lastRun->started_at?->toIso8601String(),
                'finished_at' => $lastRun->finished_at?->toIso8601String(),
            ] : null,
        ];
    }
}
