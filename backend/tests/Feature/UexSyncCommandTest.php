<?php

namespace Tests\Feature;

use App\Models\UexSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UexSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.uex.base_url', 'https://api.uexcorp.uk/2.0');
        config()->set('services.uex.token', 'test-token');
        config()->set('services.uex.timeout', 5);
    }

    public function test_it_syncs_a_requested_resource_and_tracks_the_run(): void
    {
        Http::fake([
            'https://api.uexcorp.uk/2.0/commodities' => Http::response([
                'status' => 'ok',
                'data' => [[
                    'id' => 101,
                    'name' => 'Laranite',
                    'code' => 'LAR',
                    'slug' => 'laranite',
                    'price_buy' => 27.5,
                    'price_sell' => 31.75,
                    'is_buyable' => 1,
                    'is_sellable' => 1,
                    'date_modified' => 1717420800,
                ]],
            ], 200),
        ]);

        $this->artisan('uex:sync', [
            'scope' => 'all',
            '--resource' => ['commodities'],
        ])->assertExitCode(0);

        $this->assertDatabaseHas('uex_commodities', [
            'uex_id' => 101,
            'name' => 'Laranite',
            'code' => 'LAR',
        ]);

        $this->assertDatabaseHas('uex_sync_runs', [
            'scope' => 'custom',
            'status' => 'success',
            'successful_resources' => 1,
            'failed_resources' => 0,
        ]);
    }

    public function test_it_upserts_existing_rows_when_the_same_resource_is_synced_again(): void
    {
        Http::fake([
            'https://api.uexcorp.uk/2.0/commodities' => Http::sequence()
                ->push([
                    'status' => 'ok',
                    'data' => [[
                        'id' => 101,
                        'name' => 'Laranite',
                        'code' => 'LAR',
                        'slug' => 'laranite',
                        'price_buy' => 27.5,
                        'date_modified' => 1717420800,
                    ]],
                ], 200)
                ->push([
                    'status' => 'ok',
                    'data' => [[
                        'id' => 101,
                        'name' => 'Refined Laranite',
                        'code' => 'LAR',
                        'slug' => 'laranite',
                        'price_buy' => 42.25,
                        'date_modified' => 1717420900,
                    ]],
                ], 200),
        ]);

        $this->artisan('uex:sync', [
            'scope' => 'all',
            '--resource' => ['commodities'],
        ])->assertExitCode(0);

        $this->artisan('uex:sync', [
            'scope' => 'all',
            '--resource' => ['commodities'],
        ])->assertExitCode(0);

        $this->assertDatabaseCount('uex_commodities', 1);
        $this->assertDatabaseHas('uex_commodities', [
            'uex_id' => 101,
            'name' => 'Refined Laranite',
        ]);
    }

    public function test_it_marks_a_run_as_partial_failure_without_rolling_back_successful_resources(): void
    {
        Http::fake([
            'https://api.uexcorp.uk/2.0/commodities' => Http::response([
                'status' => 'ok',
                'data' => [[
                    'id' => 101,
                    'name' => 'Laranite',
                    'date_modified' => 1717420800,
                ]],
            ], 200),
            'https://api.uexcorp.uk/2.0/categories*' => Http::response([
                'status' => 'ok',
                'data' => [[
                    'id' => 22,
                    'name' => 'Armor',
                ]],
            ], 200),
            'https://api.uexcorp.uk/2.0/items*' => Http::response([
                'status' => 'error',
                'message' => 'Rate limited',
            ], 500),
        ]);

        $this->artisan('uex:sync', [
            'scope' => 'all',
            '--resource' => ['commodities', 'items'],
        ])->assertExitCode(1);

        $run = UexSyncRun::query()->latest('id')->firstOrFail();

        $this->assertSame('partial_failure', $run->status);
        $this->assertSame(1, $run->successful_resources);
        $this->assertSame(1, $run->failed_resources);
        $this->assertDatabaseHas('uex_commodities', [
            'uex_id' => 101,
            'name' => 'Laranite',
        ]);
        $this->assertDatabaseCount('uex_items', 0);
    }

    public function test_it_syncs_items_and_item_attributes_by_fanning_out_over_item_categories(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $endpoint = trim(str_replace('/2.0/', '', $path), '/');
            $categoryId = (int) ($query['id_category'] ?? 0);

            return match ($endpoint) {
                'categories' => Http::response([
                    'status' => 'ok',
                    'data' => [
                        ['id' => 10, 'name' => 'Weapons'],
                        ['id' => 11, 'name' => 'Armor'],
                    ],
                ], 200),
                'items' => Http::response([
                    'status' => 'ok',
                    'data' => [[
                        'id' => $categoryId === 10 ? 501 : 502,
                        'id_category' => $categoryId,
                        'name' => $categoryId === 10 ? 'Laser Rifle' : 'Heavy Armor',
                        'slug' => $categoryId === 10 ? 'laser-rifle' : 'heavy-armor',
                        'type' => $categoryId === 10 ? 'weapon' : 'armor',
                    ]],
                ], 200),
                'items_attributes' => Http::response([
                    'status' => 'ok',
                    'data' => [[
                        'id' => $categoryId === 10 ? 801 : 802,
                        'id_category' => $categoryId,
                        'id_item' => $categoryId === 10 ? 501 : 502,
                        'attribute' => $categoryId === 10 ? 'damage' : 'armor_rating',
                        'value' => $categoryId === 10 ? '42' : '88',
                        'name' => $categoryId === 10 ? 'Damage' : 'Armor Rating',
                    ]],
                ], 200),
                default => Http::response([
                    'status' => 'error',
                    'message' => "Unexpected endpoint [{$endpoint}]",
                ], 500),
            };
        });

        $this->artisan('uex:sync', [
            'scope' => 'all',
            '--resource' => ['items', 'items_attributes'],
        ])->assertExitCode(0);

        $this->assertDatabaseHas('uex_items', [
            'uex_id' => 501,
            'category_uex_id' => 10,
            'name' => 'Laser Rifle',
        ]);

        $this->assertDatabaseHas('uex_items', [
            'uex_id' => 502,
            'category_uex_id' => 11,
            'name' => 'Heavy Armor',
        ]);

        $this->assertDatabaseHas('uex_item_attributes', [
            'uex_id' => 801,
            'item_uex_id' => 501,
            'attribute_key' => 'damage',
        ]);

        $this->assertDatabaseHas('uex_item_attributes', [
            'uex_id' => 802,
            'item_uex_id' => 502,
            'attribute_key' => 'armor_rating',
        ]);
    }

    public function test_item_sync_ignores_category_slices_that_return_null_data(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $endpoint = trim(str_replace('/2.0/', '', $path), '/');
            $categoryId = (int) ($query['id_category'] ?? 0);

            return match ($endpoint) {
                'categories' => Http::response([
                    'status' => 'ok',
                    'data' => [
                        ['id' => 10, 'name' => 'Weapons'],
                        ['id' => 11, 'name' => 'Armor'],
                    ],
                ], 200),
                'items' => Http::response([
                    'status' => 'ok',
                    'data' => $categoryId === 10 ? [[
                        'id' => 501,
                        'id_category' => 10,
                        'name' => 'Laser Rifle',
                        'slug' => 'laser-rifle',
                        'type' => 'weapon',
                    ]] : null,
                ], 200),
                default => Http::response([
                    'status' => 'error',
                    'message' => "Unexpected endpoint [{$endpoint}]",
                ], 500),
            };
        });

        $this->artisan('uex:sync', [
            'scope' => 'all',
            '--resource' => ['items'],
        ])->assertExitCode(0);

        $this->assertDatabaseHas('uex_items', [
            'uex_id' => 501,
            'category_uex_id' => 10,
        ]);

        $this->assertDatabaseCount('uex_items', 1);
    }

    public function test_locations_scope_only_populates_location_resources(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            $endpoint = trim(str_replace('/2.0/', '', $path), '/');

            return Http::response([
                'status' => 'ok',
                'data' => [[
                    'id' => abs(crc32($endpoint)),
                    'name' => ucfirst(str_replace('_', ' ', $endpoint)),
                    'date_modified' => 1717420800,
                ]],
            ], 200);
        });

        $this->artisan('uex:sync', [
            'scope' => 'locations',
        ])->assertExitCode(0);

        $this->assertDatabaseCount('uex_star_systems', 1);
        $this->assertDatabaseCount('uex_terminals', 1);
        $this->assertDatabaseCount('uex_vehicles', 0);
        $this->assertDatabaseCount('uex_vehicle_purchase_prices', 0);
    }
}
