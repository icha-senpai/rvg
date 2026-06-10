<?php

namespace App\Services;

use App\Models\UexSyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UexSyncService
{
    protected ?array $itemCategoryIds = null;

    public function __construct(
        protected UexClient $client,
    ) {}

    public function sync(string $scope = 'all', array $requestedResources = []): UexSyncRun
    {
        $this->configureRuntime();

        $resourceNames = UexResourceRegistry::resolve($scope, $requestedResources);
        $runScope = $requestedResources !== [] ? 'custom' : $scope;
        $startedAt = CarbonImmutable::now();

        $run = UexSyncRun::create([
            'scope' => $runScope,
            'requested_resources' => $resourceNames,
            'status' => 'running',
            'resource_results' => [],
            'total_records' => 0,
            'successful_resources' => 0,
            'failed_resources' => 0,
            'started_at' => $startedAt,
        ]);

        $this->registerFatalSyncGuard($run->id, $runScope);

        $resourceResults = [];
        $totalRecords = 0;
        $successfulResources = 0;
        $failedResources = 0;
        $firstError = null;

        foreach ($resourceNames as $resourceName) {
            $definition = UexResourceRegistry::definition($resourceName);
            $resourceStartedAt = CarbonImmutable::now();

            try {
                $payload = $this->fetchPayload($resourceName, $definition);
                $rows = $this->transformRows($definition['transformer'], $payload, $resourceStartedAt);

                DB::transaction(function () use ($definition, $rows): void {
                    $this->upsertRows(
                        $definition['table'],
                        $rows,
                        $definition['unique_by'],
                    );
                });

                $resourceResults[] = [
                    'resource' => $resourceName,
                    'label' => $definition['label'],
                    'group' => $definition['group'],
                    'table' => $definition['table'],
                    'status' => 'success',
                    'records' => count($rows),
                    'error' => null,
                    'started_at' => $resourceStartedAt->toIso8601String(),
                    'finished_at' => CarbonImmutable::now()->toIso8601String(),
                ];

                $totalRecords += count($rows);
                $successfulResources++;
            } catch (Throwable $e) {
                $failedResources++;
                $firstError ??= $e->getMessage();

                Log::warning('UEX resource sync failed', [
                    'resource' => $resourceName,
                    'scope' => $runScope,
                    'error' => $e->getMessage(),
                ]);

                $resourceResults[] = [
                    'resource' => $resourceName,
                    'label' => $definition['label'],
                    'group' => $definition['group'],
                    'table' => $definition['table'],
                    'status' => 'failed',
                    'records' => 0,
                    'error' => Str::limit($e->getMessage(), 400),
                    'started_at' => $resourceStartedAt->toIso8601String(),
                    'finished_at' => CarbonImmutable::now()->toIso8601String(),
                ];
            }
        }

        $status = match (true) {
            $failedResources === 0 => 'success',
            $successfulResources === 0 => 'failed',
            default => 'partial_failure',
        };

        $run->fill([
            'status' => $status,
            'resource_results' => $resourceResults,
            'total_records' => $totalRecords,
            'successful_resources' => $successfulResources,
            'failed_resources' => $failedResources,
            'error_message' => $firstError,
            'finished_at' => CarbonImmutable::now(),
        ])->save();

        return $run->fresh();
    }

    protected function configureRuntime(): void
    {
        @set_time_limit(0);

        $memoryLimit = trim((string) config('services.uex.sync_memory_limit', '512M'));

        if ($memoryLimit !== '') {
            @ini_set('memory_limit', $memoryLimit);
        }
    }

    protected function registerFatalSyncGuard(int $runId, string $scope): void
    {
        register_shutdown_function(function () use ($runId, $scope): void {
            $error = error_get_last();

            if (! $error || ! in_array($error['type'], [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
                E_USER_ERROR,
            ], true)) {
                return;
            }

            try {
                $updated = UexSyncRun::query()
                    ->whereKey($runId)
                    ->where('status', 'running')
                    ->update([
                        'status' => 'failed',
                        'error_message' => Str::limit('Sync crashed before completion: ' . ($error['message'] ?? 'Unknown fatal error.'), 400),
                        'finished_at' => CarbonImmutable::now(),
                    ]);

                if ($updated > 0) {
                    Log::error('UEX sync crashed fatally', [
                        'run_id' => $runId,
                        'scope' => $scope,
                        'type' => $error['type'] ?? null,
                        'message' => $error['message'] ?? null,
                        'file' => $error['file'] ?? null,
                        'line' => $error['line'] ?? null,
                    ]);
                }
            } catch (Throwable $shutdownException) {
                Log::error('UEX sync fatal guard failed', [
                    'run_id' => $runId,
                    'scope' => $scope,
                    'error' => $shutdownException->getMessage(),
                ]);
            }
        });
    }

    protected function fetchPayload(string $resourceName, array $definition): mixed
    {
        return match ($resourceName) {
            'items', 'items_attributes' => $this->fetchItemScopedPayload($definition['endpoint']),
            default => $this->client->fetch($definition['endpoint']),
        };
    }

    protected function fetchItemScopedPayload(string $endpoint): array
    {
        $payload = [];

        foreach ($this->itemCategoryIds() as $categoryId) {
            $records = $this->client->fetch($endpoint, [
                'id_category' => $categoryId,
            ]);

            foreach ($this->listify($records) as $record) {
                $payload[] = $record;
            }
        }

        return $payload;
    }

    protected function itemCategoryIds(): array
    {
        if ($this->itemCategoryIds !== null) {
            return $this->itemCategoryIds;
        }

        $categories = $this->client->fetch('categories', [
            'type' => 'item',
        ]);

        $this->itemCategoryIds = collect($this->listify($categories))
            ->map(fn ($record) => $this->toInt(((array) $record)['id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->itemCategoryIds;
    }

    protected function upsertRows(string $table, array $rows, array $uniqueBy): void
    {
        if ($rows === []) {
            return;
        }

        $rows = array_values($this->deduplicateRows($rows, $uniqueBy));
        $firstRow = $rows[0];
        $updateColumns = array_values(array_filter(
            array_keys($firstRow),
            fn (string $column) => ! in_array($column, array_merge($uniqueBy, ['created_at']), true)
        ));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $updateColumns);
        }
    }

    protected function deduplicateRows(array $rows, array $uniqueBy): array
    {
        $deduplicated = [];

        foreach ($rows as $row) {
            $parts = [];

            foreach ($uniqueBy as $column) {
                $parts[] = is_scalar($row[$column] ?? null) ? (string) $row[$column] : json_encode($row[$column]);
            }

            $deduplicated[implode('|', $parts)] = $row;
        }

        return $deduplicated;
    }

    protected function transformRows(string $transformer, mixed $payload, CarbonImmutable $syncedAt): array
    {
        return match ($transformer) {
            'catalog' => $this->transformCatalogRows($payload, $syncedAt),
            'category_attribute', 'item_attribute' => $this->transformAttributeRows($payload, $syncedAt),
            'commodity_price' => $this->transformCommodityPriceRows($payload, $syncedAt),
            'item_price' => $this->transformItemPriceRows($payload, $syncedAt),
            'fuel_price' => $this->transformFuelPriceRows($payload, $syncedAt),
            'vehicle_purchase_price' => $this->transformVehiclePurchasePriceRows($payload, $syncedAt),
            'vehicle_rental_price' => $this->transformVehicleRentalPriceRows($payload, $syncedAt),
            'vehicle_loaner' => $this->transformVehicleLoanerRows($payload, $syncedAt),
            'refinery_capacity' => $this->transformRefineryCapacityRows($payload, $syncedAt),
            'refinery_yield' => $this->transformRefineryYieldRows($payload, $syncedAt),
            'currencies_index' => $this->transformCurrenciesIndexRows($payload, $syncedAt),
            'game_versions' => $this->transformGameVersionsRows($payload, $syncedAt),
            default => throw new RuntimeException("Unknown UEX transformer [{$transformer}]."),
        };
    }

    protected function transformCatalogRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(fn ($record) => $this->catalogRow((array) $record, $syncedAt))
            ->filter(fn ($row) => $row['uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformAttributeRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(function ($record) use ($syncedAt) {
                $record = (array) $record;

                return array_merge($this->baseRow($record, $syncedAt), [
                    'uex_id' => $this->toInt($record['id'] ?? null),
                    'parent_uex_id' => $this->toInt($record['id_parent'] ?? null),
                    'category_uex_id' => $this->toInt($record['id_category'] ?? null),
                    'item_uex_id' => $this->toInt($record['id_item'] ?? null),
                    'name' => $this->toString($record['name'] ?? $record['label'] ?? null),
                    'code' => $this->toString($record['code'] ?? null),
                    'slug' => $this->toString($record['slug'] ?? null),
                    'attribute_key' => $this->toString($record['key'] ?? $record['attribute'] ?? null),
                    'value_text' => $this->toString($record['value'] ?? $record['value_text'] ?? null),
                    'value_number' => $this->toFloat($record['value_number'] ?? $record['value_float'] ?? $record['sort_order'] ?? null),
                    'unit' => $this->toString($record['unit'] ?? null),
                ]);
            })
            ->filter(fn ($row) => $row['uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformCommodityPriceRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(fn ($record) => $this->priceRow((array) $record, $syncedAt, [
                'uex_id' => $this->toInt($record['id'] ?? null),
                'commodity_uex_id' => $this->toInt($record['id_commodity'] ?? null),
                'terminal_uex_id' => $this->toInt($record['id_terminal'] ?? null),
                'commodity_name' => $this->toString($record['commodity_name'] ?? null),
                'commodity_code' => $this->toString($record['commodity_code'] ?? null),
                'commodity_slug' => $this->toString($record['commodity_slug'] ?? null),
                'terminal_name' => $this->toString($record['terminal_name'] ?? null),
                'terminal_code' => $this->toString($record['terminal_code'] ?? null),
                'terminal_slug' => $this->toString($record['terminal_slug'] ?? null),
                'price_buy' => $this->toFloat($record['price_buy'] ?? null),
                'price_buy_avg' => $this->toFloat($record['price_buy_avg'] ?? null),
                'price_sell' => $this->toFloat($record['price_sell'] ?? null),
                'price_sell_avg' => $this->toFloat($record['price_sell_avg'] ?? null),
                'quantity_buy' => $this->toFloat($record['scu_buy'] ?? null),
                'quantity_buy_avg' => $this->toFloat($record['scu_buy_avg'] ?? null),
                'quantity_sell' => $this->toFloat($record['scu_sell'] ?? null),
                'quantity_sell_avg' => $this->toFloat($record['scu_sell_avg'] ?? null),
                'stock_value' => $this->toFloat($record['scu_sell_stock'] ?? null),
                'stock_value_avg' => $this->toFloat($record['scu_sell_stock_avg'] ?? null),
                'status_buy' => $this->toInt($record['status_buy'] ?? null),
                'status_sell' => $this->toInt($record['status_sell'] ?? null),
                'container_sizes' => $this->toString($record['container_sizes'] ?? null),
                'quality' => $this->toInt($record['quality'] ?? null),
            ]))
            ->filter(fn ($row) => $row['commodity_uex_id'] !== null && $row['terminal_uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformItemPriceRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(fn ($record) => $this->priceRow((array) $record, $syncedAt, [
                'uex_id' => $this->toInt($record['id'] ?? null),
                'item_uex_id' => $this->toInt($record['id_item'] ?? null),
                'terminal_uex_id' => $this->toInt($record['id_terminal'] ?? null),
                'category_uex_id' => $this->toInt($record['id_category'] ?? null),
                'item_name' => $this->toString($record['item_name'] ?? null),
                'item_uuid' => $this->toString($record['item_uuid'] ?? null),
                'terminal_name' => $this->toString($record['terminal_name'] ?? null),
                'price_buy' => $this->toFloat($record['price_buy'] ?? null),
                'price_sell' => $this->toFloat($record['price_sell'] ?? null),
            ]))
            ->filter(fn ($row) => $row['item_uex_id'] !== null && $row['terminal_uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformFuelPriceRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(fn ($record) => $this->priceRow((array) $record, $syncedAt, [
                'uex_id' => $this->toInt($record['id'] ?? null),
                'commodity_uex_id' => $this->toInt($record['id_commodity'] ?? null),
                'terminal_uex_id' => $this->toInt($record['id_terminal'] ?? null),
                'commodity_name' => $this->toString($record['commodity_name'] ?? null),
                'commodity_code' => $this->toString($record['commodity_code'] ?? null),
                'commodity_slug' => $this->toString($record['commodity_slug'] ?? null),
                'terminal_name' => $this->toString($record['terminal_name'] ?? null),
                'terminal_code' => $this->toString($record['terminal_code'] ?? null),
                'terminal_slug' => $this->toString($record['terminal_slug'] ?? null),
                'price_buy' => $this->toFloat($record['price_buy'] ?? null),
                'price_buy_avg' => $this->toFloat($record['price_buy_avg'] ?? null),
            ]))
            ->filter(fn ($row) => $row['commodity_uex_id'] !== null && $row['terminal_uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformVehiclePurchasePriceRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(fn ($record) => $this->priceRow((array) $record, $syncedAt, [
                'uex_id' => $this->toInt($record['id'] ?? null),
                'vehicle_uex_id' => $this->toInt($record['id_vehicle'] ?? null),
                'terminal_uex_id' => $this->toInt($record['id_terminal'] ?? null),
                'vehicle_name' => $this->toString($record['vehicle_name'] ?? null),
                'terminal_name' => $this->toString($record['terminal_name'] ?? null),
                'price_buy' => $this->toFloat($record['price_buy'] ?? null),
            ]))
            ->filter(fn ($row) => $row['vehicle_uex_id'] !== null && $row['terminal_uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformVehicleRentalPriceRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(fn ($record) => $this->priceRow((array) $record, $syncedAt, [
                'uex_id' => $this->toInt($record['id'] ?? null),
                'vehicle_uex_id' => $this->toInt($record['id_vehicle'] ?? null),
                'terminal_uex_id' => $this->toInt($record['id_terminal'] ?? null),
                'vehicle_name' => $this->toString($record['vehicle_name'] ?? null),
                'terminal_name' => $this->toString($record['terminal_name'] ?? null),
                'price_rent' => $this->toFloat($record['price_rent'] ?? null),
            ]))
            ->filter(fn ($row) => $row['vehicle_uex_id'] !== null && $row['terminal_uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformVehicleLoanerRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        $rows = [];

        foreach ($this->listify($payload) as $record) {
            $record = (array) $record;
            $vehicleId = $this->toInt($record['id'] ?? null);
            $loanerIds = $this->csvIntegers($record['ids_vehicles_loaners'] ?? null);

            if ($vehicleId === null || $loanerIds === []) {
                continue;
            }

            foreach ($loanerIds as $loanerVehicleId) {
                $rows[] = array_merge($this->baseRow($record, $syncedAt), [
                    'vehicle_uex_id' => $vehicleId,
                    'loaner_vehicle_uex_id' => $loanerVehicleId,
                    'vehicle_name' => $this->toString($record['name'] ?? null),
                    'vehicle_full_name' => $this->toString($record['name_full'] ?? null),
                    'company_uex_id' => $this->toInt($record['id_company'] ?? null),
                    'parent_uex_id' => $this->toInt($record['id_parent'] ?? null),
                ]);
            }
        }

        return $rows;
    }

    protected function transformRefineryCapacityRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(fn ($record) => $this->refineryRow((array) $record, $syncedAt, [
                'value' => $this->toFloat($record['value'] ?? null),
                'value_week' => $this->toFloat($record['value_week'] ?? null),
                'value_month' => $this->toFloat($record['value_month'] ?? null),
            ]))
            ->filter(fn ($row) => $row['commodity_uex_id'] !== null && $row['terminal_uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformRefineryYieldRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        return collect($this->listify($payload))
            ->map(fn ($record) => $this->refineryRow((array) $record, $syncedAt, [
                'value' => $this->toFloat($record['value'] ?? null),
                'value_week' => $this->toFloat($record['value_week'] ?? null),
                'value_month' => $this->toFloat($record['value_month'] ?? null),
            ]))
            ->filter(fn ($row) => $row['commodity_uex_id'] !== null && $row['terminal_uex_id'] !== null)
            ->values()
            ->all();
    }

    protected function transformCurrenciesIndexRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        if (is_array($payload) && array_is_list($payload)) {
            return collect($payload)
                ->map(function ($record, int $index) use ($syncedAt) {
                    $record = (array) $record;

                    return array_merge($this->baseRow($record, $syncedAt), [
                        'metric_key' => $this->toString($record['code'] ?? $record['name'] ?? "metric-{$index}"),
                        'label' => $this->toString($record['name'] ?? $record['label'] ?? null),
                        'value' => $this->toFloat($record['value'] ?? $record['index'] ?? $record['uec_idx'] ?? null),
                        'value_previous' => $this->toFloat($record['value_previous'] ?? $record['previous'] ?? null),
                        'change_amount' => $this->toFloat($record['change_amount'] ?? $record['variation'] ?? null),
                        'change_percent' => $this->toFloat($record['change_percent'] ?? null),
                    ]);
                })
                ->values()
                ->all();
        }

        $record = is_array($payload) ? $payload : [];

        return [[
            ...$this->baseRow($record, $syncedAt),
            'metric_key' => 'current',
            'label' => $this->toString($record['name'] ?? 'Current Index'),
            'value' => $this->toFloat($record['value'] ?? $record['index'] ?? $record['uec_idx'] ?? null),
            'value_previous' => $this->toFloat($record['value_previous'] ?? $record['previous'] ?? null),
            'change_amount' => $this->toFloat($record['change_amount'] ?? $record['variation'] ?? null),
            'change_percent' => $this->toFloat($record['change_percent'] ?? null),
        ]];
    }

    protected function transformGameVersionsRows(mixed $payload, CarbonImmutable $syncedAt): array
    {
        $record = is_array($payload) ? $payload : [];

        return [[
            ...$this->baseRow($record, $syncedAt),
            'scope_key' => 'current',
            'live' => $this->toString($record['live'] ?? null),
            'ptu' => $this->toString($record['ptu'] ?? null),
        ]];
    }

    protected function catalogRow(array $record, CarbonImmutable $syncedAt): array
    {
        return array_merge($this->baseRow($record, $syncedAt), [
            'uex_id' => $this->toInt($record['id'] ?? null),
            'parent_uex_id' => $this->toInt($record['id_parent'] ?? null),
            'category_uex_id' => $this->toInt($record['id_category'] ?? null),
            'item_uex_id' => $this->toInt($record['id_item'] ?? null),
            'company_uex_id' => $this->toInt($record['id_company'] ?? null),
            'faction_uex_id' => $this->toInt($record['id_faction'] ?? null),
            'jurisdiction_uex_id' => $this->toInt($record['id_jurisdiction'] ?? null),
            'star_system_uex_id' => $this->toInt($record['id_star_system'] ?? null),
            'planet_uex_id' => $this->toInt($record['id_planet'] ?? null),
            'orbit_uex_id' => $this->toInt($record['id_orbit'] ?? null),
            'moon_uex_id' => $this->toInt($record['id_moon'] ?? null),
            'space_station_uex_id' => $this->toInt($record['id_space_station'] ?? null),
            'city_uex_id' => $this->toInt($record['id_city'] ?? null),
            'outpost_uex_id' => $this->toInt($record['id_outpost'] ?? null),
            'poi_uex_id' => $this->toInt($record['id_poi'] ?? null),
            'terminal_uex_id' => $this->toInt($record['id_terminal'] ?? null),
            'vehicle_uex_id' => $this->toInt($record['id_vehicle'] ?? null),
            'commodity_uex_id' => $this->toInt($record['id_commodity'] ?? null),
            'name' => $this->toString($record['name'] ?? null),
            'full_name' => $this->toString($record['fullname'] ?? $record['full_name'] ?? $record['name_full'] ?? null),
            'display_name' => $this->toString($record['displayname'] ?? $record['display_name'] ?? null),
            'nickname' => $this->toString($record['nickname'] ?? null),
            'code' => $this->toString($record['code'] ?? null),
            'slug' => $this->toString($record['slug'] ?? null),
            'type' => $this->toString($record['type'] ?? null),
            'kind' => $this->toString($record['kind'] ?? null),
            'industry' => $this->toString($record['industry'] ?? null),
            'pad_type' => $this->toString($record['pad_type'] ?? null),
            'uuid' => $this->toString($record['uuid'] ?? null),
            'wiki' => $this->toString($record['wiki'] ?? null),
            'game_version' => $this->toString($record['game_version'] ?? null),
            'price_buy' => $this->toFloat($record['price_buy'] ?? null),
            'price_sell' => $this->toFloat($record['price_sell'] ?? null),
            'scu' => $this->toFloat($record['scu'] ?? $record['weight_scu'] ?? null),
            'mass' => $this->toFloat($record['mass'] ?? null),
            'width' => $this->toFloat($record['width'] ?? null),
            'height' => $this->toFloat($record['height'] ?? null),
            'length' => $this->toFloat($record['length'] ?? null),
            'fuel_quantum' => $this->toFloat($record['fuel_quantum'] ?? null),
            'fuel_hydrogen' => $this->toFloat($record['fuel_hydrogen'] ?? null),
            'is_available' => $this->toBool($record['is_available'] ?? null),
            'is_available_live' => $this->toBool($record['is_available_live'] ?? null),
            'is_visible' => $this->toBool($record['is_visible'] ?? null),
            'is_default' => $this->toBool($record['is_default'] ?? $record['is_default_system'] ?? null),
            'is_item_manufacturer' => $this->toBool($record['is_item_manufacturer'] ?? null),
            'is_vehicle_manufacturer' => $this->toBool($record['is_vehicle_manufacturer'] ?? null),
            'is_buyable' => $this->toBool($record['is_buyable'] ?? null),
            'is_sellable' => $this->toBool($record['is_sellable'] ?? null),
            'is_illegal' => $this->toBool($record['is_illegal'] ?? null),
            'is_ground_vehicle' => $this->toBool($record['is_ground_vehicle'] ?? null),
            'is_spaceship' => $this->toBool($record['is_spaceship'] ?? null),
            'is_cargo' => $this->toBool($record['is_cargo'] ?? null),
            'is_mining' => $this->toBool($record['is_mining'] ?? null),
            'is_refinery' => $this->toBool($record['is_refinery'] ?? null),
            'is_medical' => $this->toBool($record['is_medical'] ?? null),
        ]);
    }

    protected function priceRow(array $record, CarbonImmutable $syncedAt, array $fields): array
    {
        return array_merge($this->baseRow($record, $syncedAt), $fields);
    }

    protected function refineryRow(array $record, CarbonImmutable $syncedAt, array $fields): array
    {
        return array_merge($this->baseRow($record, $syncedAt), [
            'uex_id' => $this->toInt($record['id'] ?? null),
            'commodity_uex_id' => $this->toInt($record['id_commodity'] ?? null),
            'terminal_uex_id' => $this->toInt($record['id_terminal'] ?? null),
            'report_uex_id' => $this->toInt($record['id_report'] ?? null),
            'star_system_uex_id' => $this->toInt($record['id_star_system'] ?? null),
            'planet_uex_id' => $this->toInt($record['id_planet'] ?? null),
            'orbit_uex_id' => $this->toInt($record['id_orbit'] ?? null),
            'moon_uex_id' => $this->toInt($record['id_moon'] ?? null),
            'space_station_uex_id' => $this->toInt($record['id_space_station'] ?? null),
            'city_uex_id' => $this->toInt($record['id_city'] ?? null),
            'outpost_uex_id' => $this->toInt($record['id_outpost'] ?? null),
            'poi_uex_id' => $this->toInt($record['id_poi'] ?? null),
            'faction_uex_id' => $this->toInt($record['id_faction'] ?? null),
            'commodity_name' => $this->toString($record['commodity_name'] ?? null),
            'terminal_name' => $this->toString($record['terminal_name'] ?? null),
            'star_system_name' => $this->toString($record['star_system_name'] ?? null),
            'planet_name' => $this->toString($record['planet_name'] ?? null),
            'orbit_name' => $this->toString($record['orbit_name'] ?? null),
            'moon_name' => $this->toString($record['moon_name'] ?? null),
            'space_station_name' => $this->toString($record['space_station_name'] ?? null),
            'city_name' => $this->toString($record['city_name'] ?? null),
            'outpost_name' => $this->toString($record['outpost_name'] ?? null),
        ], $fields);
    }

    protected function baseRow(array $record, CarbonImmutable $syncedAt): array
    {
        return [
            'source_payload' => $this->encodeJson($record),
            'source_modified_at' => $this->timestampFrom(
                $record['date_modified'] ?? $record['date_added'] ?? null
            ),
            'last_synced_at' => $syncedAt,
            'created_at' => $syncedAt,
            'updated_at' => $syncedAt,
        ];
    }

    protected function listify(mixed $payload): array
    {
        if ($payload === null) {
            return [];
        }

        if (is_array($payload) && array_is_list($payload)) {
            return $payload;
        }

        if (is_array($payload)) {
            return [$payload];
        }

        throw new RuntimeException('UEX payload was not an array.');
    }

    protected function timestampFrom(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestampUTC((int) $value)->toDateTimeString();
        }

        return (string) $value;
    }

    protected function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    protected function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function toString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $this->encodeJson($value);
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }

    protected function toBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    protected function csvIntegers(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $part) => trim($part))
            ->filter(fn (string $part) => $part !== '' && is_numeric($part))
            ->map(fn (string $part) => (int) $part)
            ->unique()
            ->values()
            ->all();
    }
}
