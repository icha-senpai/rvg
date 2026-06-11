<?php

namespace Tests\Feature;

use App\Domain\Operations\Events\OperationPublished;
use App\Domain\Operations\Events\OperationUpdated;
use App\Domain\Operations\Listeners\SendOperationPublishedToDiscord;
use App\Domain\Operations\Listeners\SendOperationUpdatedToDiscord;
use App\Models\Operation;
use App\Models\Squadron;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationDiscordAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_squadron_operation_publish_posts_to_all_selected_squadron_channels(): void
    {
        config([
            'services.bot.url' => 'http://bot.test',
            'services.bot.secret' => 'test-secret',
        ]);

        $creator = User::factory()->create([
            'discord_id' => '555666777888999000',
            'discord_name' => 'Sammi',
            'discord_avatar' => 'avatar-hash',
            'rsi_handle' => 'Sammi',
        ]);

        $ownerSquadron = Squadron::create([
            'name' => 'Ghost Squadron',
            'slug' => 'ghost-squadron',
            'status' => 'active',
            'discord_channel_id' => '111111111111111111',
        ]);

        $supportSquadron = Squadron::create([
            'name' => 'Nova Wing',
            'slug' => 'nova-wing',
            'status' => 'active',
            'discord_channel_id' => '222222222222222222',
        ]);

        $operation = $this->makeOperation($creator, [
            'squadron_id' => $ownerSquadron->id,
            'squadron_name' => 'Ghost Squadron, Nova Wing',
            'status' => 'published',
        ]);

        Http::fake([
            'http://bot.test/op-published' => Http::response([
                'message_id' => '900000000000000001',
                'message_targets' => [
                    [
                        'channel_id' => '111111111111111111',
                        'message_id' => '900000000000000001',
                    ],
                    [
                        'channel_id' => '222222222222222222',
                        'message_id' => '900000000000000002',
                    ],
                ],
            ], 200),
        ]);

        app(SendOperationPublishedToDiscord::class)->handle(new OperationPublished($operation));

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://bot.test/op-published'
                && $request->hasHeader('X-Bot-Secret', 'test-secret')
                && $data['announce_to_default_channel'] === false
                && collect($data['discord_targets'] ?? [])->pluck('channel_id')->values()->all() === [
                    '111111111111111111',
                    '222222222222222222',
                ];
        });

        $operation->refresh();

        $this->assertSame('900000000000000001', $operation->discord_message_id);
        $this->assertSame([
            [
                'channel_id' => '111111111111111111',
                'message_id' => '900000000000000001',
            ],
            [
                'channel_id' => '222222222222222222',
                'message_id' => '900000000000000002',
            ],
        ], $operation->discord_message_targets);
    }

    public function test_squadron_operation_update_deletes_old_targets_and_reposts_to_new_selected_channels(): void
    {
        config([
            'services.bot.url' => 'http://bot.test',
            'services.bot.secret' => 'test-secret',
        ]);

        $creator = User::factory()->create([
            'discord_id' => '555666777888999000',
            'discord_name' => 'Sammi',
            'discord_avatar' => 'avatar-hash',
            'rsi_handle' => 'Sammi',
        ]);

        $ownerSquadron = Squadron::create([
            'name' => 'Ghost Squadron',
            'slug' => 'ghost-squadron',
            'status' => 'active',
            'discord_channel_id' => '111111111111111111',
        ]);

        Squadron::create([
            'name' => 'Nova Wing',
            'slug' => 'nova-wing',
            'status' => 'active',
            'discord_channel_id' => '222222222222222222',
        ]);

        Squadron::create([
            'name' => 'Atlas Wing',
            'slug' => 'atlas-wing',
            'status' => 'active',
            'discord_channel_id' => '333333333333333333',
        ]);

        $operation = $this->makeOperation($creator, [
            'squadron_id' => $ownerSquadron->id,
            'squadron_name' => 'Ghost Squadron, Nova Wing',
            'status' => 'published',
            'discord_message_id' => '900000000000000001',
            'discord_message_targets' => [
                [
                    'channel_id' => '111111111111111111',
                    'message_id' => '900000000000000001',
                ],
                [
                    'channel_id' => '222222222222222222',
                    'message_id' => '900000000000000002',
                ],
            ],
        ]);

        $operation->update([
            'squadron_name' => 'Ghost Squadron, Atlas Wing',
        ]);

        Http::fake([
            'http://bot.test/op-delete' => Http::response([
                'message' => 'Deleted.',
            ], 200),
            'http://bot.test/op-updated' => Http::response([
                'message_id' => '900000000000000010',
                'message_targets' => [
                    [
                        'channel_id' => '111111111111111111',
                        'message_id' => '900000000000000010',
                    ],
                    [
                        'channel_id' => '333333333333333333',
                        'message_id' => '900000000000000011',
                    ],
                ],
            ], 200),
        ]);

        app(SendOperationUpdatedToDiscord::class)->handle(new OperationUpdated($operation->fresh()));

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://bot.test/op-delete'
                && ($data['message_targets'] ?? []) === [
                    [
                        'channel_id' => '111111111111111111',
                        'message_id' => '900000000000000001',
                    ],
                    [
                        'channel_id' => '222222222222222222',
                        'message_id' => '900000000000000002',
                    ],
                ];
        });

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://bot.test/op-updated'
                && $data['announce_to_default_channel'] === false
                && collect($data['discord_targets'] ?? [])->pluck('channel_id')->values()->all() === [
                    '111111111111111111',
                    '333333333333333333',
                ];
        });

        $operation->refresh();

        $this->assertSame('900000000000000010', $operation->discord_message_id);
        $this->assertSame([
            [
                'channel_id' => '111111111111111111',
                'message_id' => '900000000000000010',
            ],
            [
                'channel_id' => '333333333333333333',
                'message_id' => '900000000000000011',
            ],
        ], $operation->discord_message_targets);
    }

    protected function makeOperation(User $creator, array $overrides = []): Operation
    {
        $payload = array_merge([
            'title' => 'Med Runner Patrol',
            'description' => 'Keep the route clear.',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'visibility' => 'open',
            'difficulty' => 'medium',
            'operation_strictness' => 'normal',
            'status' => 'draft',
            'created_by' => $creator->id,
            'slots' => [],
        ], $overrides);

        $operationTypeColumn = Schema::hasColumn('operations', 'operation_type')
            ? 'operation_type'
            : 'operation_kind';

        if (! array_key_exists('operation_type', $payload) && ! array_key_exists('operation_kind', $payload)) {
            $payload[$operationTypeColumn] = 'combat';
        }

        if ($operationTypeColumn === 'operation_kind' && array_key_exists('operation_type', $payload)) {
            $payload['operation_kind'] = $payload['operation_type'];
            unset($payload['operation_type']);
        }

        if ($operationTypeColumn === 'operation_type' && array_key_exists('operation_kind', $payload)) {
            $payload['operation_type'] = $payload['operation_kind'];
            unset($payload['operation_kind']);
        }

        if (! Schema::hasColumn('operations', 'start_location')) {
            unset($payload['start_location']);
        } elseif (! array_key_exists('start_location', $payload)) {
            $payload['start_location'] = 'Port Tressler';
        }

        if (! Schema::hasColumn('operations', 'operation_location')) {
            unset($payload['operation_location']);
        } elseif (! array_key_exists('operation_location', $payload)) {
            $payload['operation_location'] = 'MicroTech Orbit';
        }

        if (! Schema::hasColumn('operations', 'branch')) {
            unset($payload['branch']);
        } elseif (! array_key_exists('branch', $payload)) {
            $payload['branch'] = 'Navy';
        }

        if (! Schema::hasColumn('operations', 'gameplay_type')) {
            unset($payload['gameplay_type']);
        } elseif (! array_key_exists('gameplay_type', $payload)) {
            $payload['gameplay_type'] = 'Patrol';
        }

        $operation = new Operation();
        $operation->forceFill($payload);
        $operation->save();

        return $operation->fresh();
    }
}
