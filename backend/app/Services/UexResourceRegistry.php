<?php

namespace App\Services;

use InvalidArgumentException;

class UexResourceRegistry
{
    /**
     * Return the ordered UEX resource registry used by sync commands and admin status.
     */
    public static function definitions(): array
    {
        return [
            'factions' => self::catalog('locations', 'Factions', 'uex_factions'),
            'jurisdictions' => self::catalog('locations', 'Jurisdictions', 'uex_jurisdictions'),
            'star_systems' => self::catalog('locations', 'Star Systems', 'uex_star_systems'),
            'planets' => self::catalog('locations', 'Planets', 'uex_planets'),
            'moons' => self::catalog('locations', 'Moons', 'uex_moons'),
            'cities' => self::catalog('locations', 'Cities', 'uex_cities'),
            'space_stations' => self::catalog('locations', 'Space Stations', 'uex_space_stations'),
            'outposts' => self::catalog('locations', 'Outposts', 'uex_outposts'),
            'poi' => self::catalog('locations', 'Points Of Interest', 'uex_poi'),
            'terminals' => self::catalog('locations', 'Terminals', 'uex_terminals'),

            'categories' => self::catalog('trade', 'Categories', 'uex_categories'),
            'categories_attributes' => self::attribute('trade', 'Category Attributes', 'uex_category_attributes', 'category_attribute'),
            'commodities' => self::catalog('trade', 'Commodities', 'uex_commodities'),
            'commodities_prices_all' => self::state('trade', 'Commodity Prices', 'uex_commodity_prices', ['commodity_uex_id', 'terminal_uex_id'], 'commodity_price'),
            'items' => self::catalog('trade', 'Items', 'uex_items'),
            'items_attributes' => self::attribute('trade', 'Item Attributes', 'uex_item_attributes', 'item_attribute'),
            'items_prices_all' => self::state('trade', 'Item Prices', 'uex_item_prices', ['item_uex_id', 'terminal_uex_id'], 'item_price'),
            'fuel_prices_all' => self::state('trade', 'Fuel Prices', 'uex_fuel_prices', ['commodity_uex_id', 'terminal_uex_id'], 'fuel_price'),
            'currencies_index' => self::state('trade', 'Currencies Index', 'uex_currencies_index', ['metric_key'], 'currencies_index'),

            'companies' => self::catalog('vehicles', 'Companies', 'uex_companies'),
            'vehicles' => self::catalog('vehicles', 'Vehicles', 'uex_vehicles'),
            'vehicles_loaners' => self::state('vehicles', 'Vehicle Loaners', 'uex_vehicle_loaners', ['vehicle_uex_id', 'loaner_vehicle_uex_id'], 'vehicle_loaner'),
            'vehicles_purchases_prices_all' => self::state('vehicles', 'Vehicle Purchase Prices', 'uex_vehicle_purchase_prices', ['vehicle_uex_id', 'terminal_uex_id'], 'vehicle_purchase_price'),
            'vehicles_rentals_prices_all' => self::state('vehicles', 'Vehicle Rental Prices', 'uex_vehicle_rental_prices', ['vehicle_uex_id', 'terminal_uex_id'], 'vehicle_rental_price'),

            'refineries_capacities' => self::state('industry', 'Refinery Capacities', 'uex_refinery_capacities', ['commodity_uex_id', 'terminal_uex_id'], 'refinery_capacity'),
            'refineries_yields' => self::state('industry', 'Refinery Yields', 'uex_refinery_yields', ['commodity_uex_id', 'terminal_uex_id'], 'refinery_yield'),
            'game_versions' => self::state('industry', 'Game Versions', 'uex_game_versions', ['scope_key'], 'game_versions'),
        ];
    }

    public static function definition(string $resource): array
    {
        $definition = self::definitions()[$resource] ?? null;

        if (! $definition) {
            throw new InvalidArgumentException("Unknown UEX resource [{$resource}].");
        }

        return $definition + ['resource' => $resource];
    }

    public static function groups(): array
    {
        $groups = [];

        foreach (self::definitions() as $resource => $definition) {
            $groups[$definition['group']][] = $resource;
        }

        return $groups;
    }

    public static function allResourceNames(): array
    {
        return array_keys(self::definitions());
    }

    public static function resolve(string $scope = 'all', array $requestedResources = []): array
    {
        $requestedResources = array_values(array_unique(array_filter($requestedResources)));

        if ($requestedResources !== []) {
            foreach ($requestedResources as $resource) {
                self::definition($resource);
            }

            return $requestedResources;
        }

        if ($scope === 'all') {
            return self::allResourceNames();
        }

        $groups = self::groups();

        if (! array_key_exists($scope, $groups)) {
            throw new InvalidArgumentException("Unknown UEX scope [{$scope}]. Use all, locations, trade, vehicles, industry, or --resource.");
        }

        return $groups[$scope];
    }

    protected static function catalog(string $group, string $label, string $table): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'endpoint' => self::resourceNameFromTable($table),
            'table' => $table,
            'unique_by' => ['uex_id'],
            'transformer' => 'catalog',
        ];
    }

    protected static function attribute(string $group, string $label, string $table, string $transformer): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'endpoint' => $transformer === 'category_attribute' ? 'categories_attributes' : 'items_attributes',
            'table' => $table,
            'unique_by' => ['uex_id'],
            'transformer' => $transformer,
        ];
    }

    protected static function state(string $group, string $label, string $table, array $uniqueBy, string $transformer): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'endpoint' => self::resourceNameFromTable($table),
            'table' => $table,
            'unique_by' => $uniqueBy,
            'transformer' => $transformer,
        ];
    }

    protected static function resourceNameFromTable(string $table): string
    {
        return match ($table) {
            'uex_commodity_prices' => 'commodities_prices_all',
            'uex_item_prices' => 'items_prices_all',
            'uex_fuel_prices' => 'fuel_prices_all',
            'uex_vehicle_purchase_prices' => 'vehicles_purchases_prices_all',
            'uex_vehicle_rental_prices' => 'vehicles_rentals_prices_all',
            'uex_vehicle_loaners' => 'vehicles_loaners',
            'uex_refinery_capacities' => 'refineries_capacities',
            'uex_refinery_yields' => 'refineries_yields',
            'uex_currencies_index' => 'currencies_index',
            'uex_game_versions' => 'game_versions',
            default => str_replace('uex_', '', $table),
        };
    }
}
