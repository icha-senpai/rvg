<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uex_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->json('requested_resources');
            $table->string('status')->index();
            $table->json('resource_results');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedSmallInteger('successful_resources')->default(0);
            $table->unsignedSmallInteger('failed_resources')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        foreach ([
            'uex_companies',
            'uex_factions',
            'uex_jurisdictions',
            'uex_star_systems',
            'uex_planets',
            'uex_moons',
            'uex_cities',
            'uex_space_stations',
            'uex_outposts',
            'uex_poi',
            'uex_terminals',
            'uex_categories',
            'uex_commodities',
            'uex_items',
            'uex_vehicles',
        ] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $this->addCatalogColumns($table);
            });
        }

        Schema::create('uex_category_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->unique();
            $table->unsignedBigInteger('parent_uex_id')->nullable()->index();
            $table->unsignedBigInteger('category_uex_id')->nullable()->index();
            $table->unsignedBigInteger('item_uex_id')->nullable()->index();
            $table->string('name')->nullable()->index();
            $table->string('code')->nullable()->index();
            $table->string('slug')->nullable()->index();
            $table->string('attribute_key')->nullable()->index();
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->string('unit')->nullable();
            $this->addSourceColumns($table);
        });

        Schema::create('uex_item_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->unique();
            $table->unsignedBigInteger('parent_uex_id')->nullable()->index();
            $table->unsignedBigInteger('category_uex_id')->nullable()->index();
            $table->unsignedBigInteger('item_uex_id')->nullable()->index();
            $table->string('name')->nullable()->index();
            $table->string('code')->nullable()->index();
            $table->string('slug')->nullable()->index();
            $table->string('attribute_key')->nullable()->index();
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->string('unit')->nullable();
            $this->addSourceColumns($table);
        });

        Schema::create('uex_commodity_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->nullable()->index();
            $table->unsignedBigInteger('commodity_uex_id')->index();
            $table->unsignedBigInteger('terminal_uex_id')->index();
            $table->string('commodity_name')->nullable()->index();
            $table->string('commodity_code')->nullable()->index();
            $table->string('commodity_slug')->nullable()->index();
            $table->string('terminal_name')->nullable()->index();
            $table->string('terminal_code')->nullable()->index();
            $table->string('terminal_slug')->nullable()->index();
            $table->decimal('price_buy', 18, 4)->nullable();
            $table->decimal('price_buy_avg', 18, 4)->nullable();
            $table->decimal('price_sell', 18, 4)->nullable();
            $table->decimal('price_sell_avg', 18, 4)->nullable();
            $table->decimal('quantity_buy', 18, 4)->nullable();
            $table->decimal('quantity_buy_avg', 18, 4)->nullable();
            $table->decimal('quantity_sell', 18, 4)->nullable();
            $table->decimal('quantity_sell_avg', 18, 4)->nullable();
            $table->decimal('stock_value', 18, 4)->nullable();
            $table->decimal('stock_value_avg', 18, 4)->nullable();
            $table->integer('status_buy')->nullable();
            $table->integer('status_sell')->nullable();
            $table->string('container_sizes')->nullable();
            $table->integer('quality')->nullable();
            $table->unique(['commodity_uex_id', 'terminal_uex_id'], 'uex_commodity_prices_pair_unique');
            $this->addSourceColumns($table);
        });

        Schema::create('uex_item_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->nullable()->index();
            $table->unsignedBigInteger('item_uex_id')->index();
            $table->unsignedBigInteger('terminal_uex_id')->index();
            $table->unsignedBigInteger('category_uex_id')->nullable()->index();
            $table->string('item_name')->nullable()->index();
            $table->string('item_uuid')->nullable()->index();
            $table->string('terminal_name')->nullable()->index();
            $table->decimal('price_buy', 18, 4)->nullable();
            $table->decimal('price_sell', 18, 4)->nullable();
            $table->unique(['item_uex_id', 'terminal_uex_id'], 'uex_item_prices_pair_unique');
            $this->addSourceColumns($table);
        });

        Schema::create('uex_fuel_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->nullable()->index();
            $table->unsignedBigInteger('commodity_uex_id')->index();
            $table->unsignedBigInteger('terminal_uex_id')->index();
            $table->string('commodity_name')->nullable()->index();
            $table->string('commodity_code')->nullable()->index();
            $table->string('commodity_slug')->nullable()->index();
            $table->string('terminal_name')->nullable()->index();
            $table->string('terminal_code')->nullable()->index();
            $table->string('terminal_slug')->nullable()->index();
            $table->decimal('price_buy', 18, 4)->nullable();
            $table->decimal('price_buy_avg', 18, 4)->nullable();
            $table->unique(['commodity_uex_id', 'terminal_uex_id'], 'uex_fuel_prices_pair_unique');
            $this->addSourceColumns($table);
        });

        Schema::create('uex_vehicle_purchase_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->nullable()->index();
            $table->unsignedBigInteger('vehicle_uex_id')->index();
            $table->unsignedBigInteger('terminal_uex_id')->index();
            $table->string('vehicle_name')->nullable()->index();
            $table->string('terminal_name')->nullable()->index();
            $table->decimal('price_buy', 18, 4)->nullable();
            $table->unique(['vehicle_uex_id', 'terminal_uex_id'], 'uex_vehicle_purchase_pair_unique');
            $this->addSourceColumns($table);
        });

        Schema::create('uex_vehicle_rental_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->nullable()->index();
            $table->unsignedBigInteger('vehicle_uex_id')->index();
            $table->unsignedBigInteger('terminal_uex_id')->index();
            $table->string('vehicle_name')->nullable()->index();
            $table->string('terminal_name')->nullable()->index();
            $table->decimal('price_rent', 18, 4)->nullable();
            $table->unique(['vehicle_uex_id', 'terminal_uex_id'], 'uex_vehicle_rental_pair_unique');
            $this->addSourceColumns($table);
        });

        Schema::create('uex_vehicle_loaners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_uex_id')->index();
            $table->unsignedBigInteger('loaner_vehicle_uex_id')->index();
            $table->unsignedBigInteger('company_uex_id')->nullable()->index();
            $table->unsignedBigInteger('parent_uex_id')->nullable()->index();
            $table->string('vehicle_name')->nullable()->index();
            $table->string('vehicle_full_name')->nullable()->index();
            $table->unique(['vehicle_uex_id', 'loaner_vehicle_uex_id'], 'uex_vehicle_loaners_pair_unique');
            $this->addSourceColumns($table);
        });

        Schema::create('uex_refinery_capacities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->nullable()->index();
            $table->unsignedBigInteger('commodity_uex_id')->index();
            $table->unsignedBigInteger('terminal_uex_id')->index();
            $table->unsignedBigInteger('report_uex_id')->nullable()->index();
            $this->addRefineryLocationColumns($table);
            $table->string('commodity_name')->nullable()->index();
            $table->string('terminal_name')->nullable()->index();
            $table->string('star_system_name')->nullable();
            $table->string('planet_name')->nullable();
            $table->string('orbit_name')->nullable();
            $table->string('moon_name')->nullable();
            $table->string('space_station_name')->nullable();
            $table->string('city_name')->nullable();
            $table->string('outpost_name')->nullable();
            $table->decimal('value', 18, 4)->nullable();
            $table->decimal('value_week', 18, 4)->nullable();
            $table->decimal('value_month', 18, 4)->nullable();
            $table->unique(['commodity_uex_id', 'terminal_uex_id'], 'uex_refinery_capacity_pair_unique');
            $this->addSourceColumns($table);
        });

        Schema::create('uex_refinery_yields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uex_id')->nullable()->index();
            $table->unsignedBigInteger('commodity_uex_id')->index();
            $table->unsignedBigInteger('terminal_uex_id')->index();
            $table->unsignedBigInteger('report_uex_id')->nullable()->index();
            $this->addRefineryLocationColumns($table);
            $table->string('commodity_name')->nullable()->index();
            $table->string('terminal_name')->nullable()->index();
            $table->string('star_system_name')->nullable();
            $table->string('planet_name')->nullable();
            $table->string('orbit_name')->nullable();
            $table->string('moon_name')->nullable();
            $table->string('space_station_name')->nullable();
            $table->string('city_name')->nullable();
            $table->string('outpost_name')->nullable();
            $table->decimal('value', 18, 4)->nullable();
            $table->decimal('value_week', 18, 4)->nullable();
            $table->decimal('value_month', 18, 4)->nullable();
            $table->unique(['commodity_uex_id', 'terminal_uex_id'], 'uex_refinery_yield_pair_unique');
            $this->addSourceColumns($table);
        });

        Schema::create('uex_currencies_index', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key')->unique();
            $table->string('label')->nullable();
            $table->decimal('value', 18, 4)->nullable();
            $table->decimal('value_previous', 18, 4)->nullable();
            $table->decimal('change_amount', 18, 4)->nullable();
            $table->decimal('change_percent', 18, 4)->nullable();
            $this->addSourceColumns($table);
        });

        Schema::create('uex_game_versions', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key')->unique();
            $table->string('live')->nullable();
            $table->string('ptu')->nullable();
            $this->addSourceColumns($table);
        });
    }

    public function down(): void
    {
        foreach ([
            'uex_game_versions',
            'uex_currencies_index',
            'uex_refinery_yields',
            'uex_refinery_capacities',
            'uex_vehicle_loaners',
            'uex_vehicle_rental_prices',
            'uex_vehicle_purchase_prices',
            'uex_fuel_prices',
            'uex_item_prices',
            'uex_commodity_prices',
            'uex_item_attributes',
            'uex_category_attributes',
            'uex_vehicles',
            'uex_items',
            'uex_commodities',
            'uex_categories',
            'uex_terminals',
            'uex_poi',
            'uex_outposts',
            'uex_space_stations',
            'uex_cities',
            'uex_moons',
            'uex_planets',
            'uex_star_systems',
            'uex_jurisdictions',
            'uex_factions',
            'uex_companies',
            'uex_sync_runs',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }

    protected function addCatalogColumns(Blueprint $table): void
    {
        $table->unsignedBigInteger('uex_id')->unique();
        $table->unsignedBigInteger('parent_uex_id')->nullable()->index();
        $table->unsignedBigInteger('category_uex_id')->nullable()->index();
        $table->unsignedBigInteger('item_uex_id')->nullable()->index();
        $table->unsignedBigInteger('company_uex_id')->nullable()->index();
        $table->unsignedBigInteger('faction_uex_id')->nullable()->index();
        $table->unsignedBigInteger('jurisdiction_uex_id')->nullable()->index();
        $table->unsignedBigInteger('star_system_uex_id')->nullable()->index();
        $table->unsignedBigInteger('planet_uex_id')->nullable()->index();
        $table->unsignedBigInteger('orbit_uex_id')->nullable()->index();
        $table->unsignedBigInteger('moon_uex_id')->nullable()->index();
        $table->unsignedBigInteger('space_station_uex_id')->nullable()->index();
        $table->unsignedBigInteger('city_uex_id')->nullable()->index();
        $table->unsignedBigInteger('outpost_uex_id')->nullable()->index();
        $table->unsignedBigInteger('poi_uex_id')->nullable()->index();
        $table->unsignedBigInteger('terminal_uex_id')->nullable()->index();
        $table->unsignedBigInteger('vehicle_uex_id')->nullable()->index();
        $table->unsignedBigInteger('commodity_uex_id')->nullable()->index();
        $table->string('name')->nullable()->index();
        $table->string('full_name')->nullable()->index();
        $table->string('display_name')->nullable()->index();
        $table->string('nickname')->nullable();
        $table->string('code')->nullable()->index();
        $table->string('slug')->nullable()->index();
        $table->string('type')->nullable()->index();
        $table->string('kind')->nullable();
        $table->string('industry')->nullable();
        $table->string('pad_type')->nullable()->index();
        $table->string('uuid')->nullable()->index();
        $table->string('wiki')->nullable();
        $table->string('game_version')->nullable()->index();
        $table->decimal('price_buy', 18, 4)->nullable();
        $table->decimal('price_sell', 18, 4)->nullable();
        $table->decimal('scu', 18, 4)->nullable();
        $table->decimal('mass', 18, 4)->nullable();
        $table->decimal('width', 18, 4)->nullable();
        $table->decimal('height', 18, 4)->nullable();
        $table->decimal('length', 18, 4)->nullable();
        $table->decimal('fuel_quantum', 18, 4)->nullable();
        $table->decimal('fuel_hydrogen', 18, 4)->nullable();
        $table->boolean('is_available')->nullable()->index();
        $table->boolean('is_available_live')->nullable()->index();
        $table->boolean('is_visible')->nullable()->index();
        $table->boolean('is_default')->nullable()->index();
        $table->boolean('is_item_manufacturer')->nullable()->index();
        $table->boolean('is_vehicle_manufacturer')->nullable()->index();
        $table->boolean('is_buyable')->nullable()->index();
        $table->boolean('is_sellable')->nullable()->index();
        $table->boolean('is_illegal')->nullable()->index();
        $table->boolean('is_ground_vehicle')->nullable()->index();
        $table->boolean('is_spaceship')->nullable()->index();
        $table->boolean('is_cargo')->nullable()->index();
        $table->boolean('is_mining')->nullable()->index();
        $table->boolean('is_refinery')->nullable()->index();
        $table->boolean('is_medical')->nullable()->index();
        $this->addSourceColumns($table);
    }

    protected function addRefineryLocationColumns(Blueprint $table): void
    {
        $table->unsignedBigInteger('star_system_uex_id')->nullable()->index();
        $table->unsignedBigInteger('planet_uex_id')->nullable()->index();
        $table->unsignedBigInteger('orbit_uex_id')->nullable()->index();
        $table->unsignedBigInteger('moon_uex_id')->nullable()->index();
        $table->unsignedBigInteger('space_station_uex_id')->nullable()->index();
        $table->unsignedBigInteger('city_uex_id')->nullable()->index();
        $table->unsignedBigInteger('outpost_uex_id')->nullable()->index();
        $table->unsignedBigInteger('poi_uex_id')->nullable()->index();
        $table->unsignedBigInteger('faction_uex_id')->nullable()->index();
    }

    protected function addSourceColumns(Blueprint $table): void
    {
        $table->json('source_payload');
        $table->timestamp('source_modified_at')->nullable()->index();
        $table->timestamp('last_synced_at')->index();
        $table->timestamps();
    }
};
