<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class UexAdminDataService
{
    public function resourcePayload(string $resource, string $search = '', int $page = 1, int $perPage = 20): array
    {
        $definition = UexResourceRegistry::definition($resource);
        $table = $definition['table'];

        if (! Schema::hasTable($table)) {
            throw new InvalidArgumentException("UEX table [{$table}] is not available yet.");
        }

        $allColumns = Schema::getColumnListing($table);
        $visibleColumns = $this->visibleColumns($allColumns);
        $searchableColumns = $this->searchableColumns($visibleColumns);

        $query = DB::table($table)->select($visibleColumns);

        if ($search !== '' && $searchableColumns !== []) {
            $needle = '%' . mb_strtolower($search) . '%';

            $query->where(function ($nested) use ($searchableColumns, $needle) {
                foreach ($searchableColumns as $column) {
                    $nested->orWhereRaw("LOWER(CAST({$column} AS TEXT)) LIKE ?", [$needle]);
                }
            });
        }

        if (in_array('last_synced_at', $allColumns, true)) {
            $query->orderByDesc('last_synced_at');
        }

        if (in_array('uex_id', $allColumns, true)) {
            $query->orderBy('uex_id');
        } elseif (in_array('id', $allColumns, true)) {
            $query->orderByDesc('id');
        }

        /** @var LengthAwarePaginator $rows */
        $rows = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'resource' => $resource,
            'label' => $definition['label'],
            'group' => $definition['group'],
            'table' => $table,
            'columns' => $visibleColumns,
            'search' => $search,
            'rows' => [
                'data' => collect($rows->items())->map(function ($item) {
                    return collect((array) $item)
                        ->map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : $value)
                        ->all();
                })->all(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
        ];
    }

    protected function visibleColumns(array $allColumns): array
    {
        $priority = [
            'uex_id',
            'metric_key',
            'scope_key',
            'name',
            'full_name',
            'display_name',
            'code',
            'slug',
            'type',
            'kind',
            'vehicle_name',
            'vehicle_full_name',
            'commodity_name',
            'commodity_code',
            'commodity_slug',
            'item_name',
            'item_uuid',
            'terminal_name',
            'terminal_code',
            'terminal_slug',
            'label',
            'value',
            'value_week',
            'value_month',
            'value_previous',
            'change_amount',
            'change_percent',
            'price_buy',
            'price_buy_avg',
            'price_sell',
            'price_sell_avg',
            'price_rent',
            'quantity_buy',
            'quantity_sell',
            'status_buy',
            'status_sell',
            'quality',
            'company_uex_id',
            'vehicle_uex_id',
            'loaner_vehicle_uex_id',
            'commodity_uex_id',
            'item_uex_id',
            'terminal_uex_id',
            'category_uex_id',
            'star_system_uex_id',
            'planet_uex_id',
            'moon_uex_id',
            'city_uex_id',
            'space_station_uex_id',
            'outpost_uex_id',
            'poi_uex_id',
            'source_modified_at',
            'last_synced_at',
        ];

        $selected = [];

        foreach ($priority as $column) {
            if (in_array($column, $allColumns, true)) {
                $selected[] = $column;
            }
        }

        if ($selected === []) {
            return array_values(array_diff($allColumns, ['source_payload', 'created_at', 'updated_at']));
        }

        return $selected;
    }

    protected function searchableColumns(array $visibleColumns): array
    {
        $excluded = [
            'price_buy',
            'price_buy_avg',
            'price_sell',
            'price_sell_avg',
            'price_rent',
            'value',
            'value_week',
            'value_month',
            'value_previous',
            'change_amount',
            'change_percent',
            'quality',
            'status_buy',
            'status_sell',
            'source_modified_at',
            'last_synced_at',
        ];

        return array_values(array_diff($visibleColumns, $excluded));
    }
}
