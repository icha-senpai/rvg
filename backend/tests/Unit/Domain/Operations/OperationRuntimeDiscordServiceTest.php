<?php

namespace Tests\Unit\Domain\Operations;

use App\Domain\Operations\OperationRuntimeDiscordService;
use App\Domain\Operations\Enums\OperationStatus;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationRuntimeDiscordServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lobby_channel_ids_are_normalized_and_deduplicated(): void
    {
        config()->set('services.discord.operation_lobby_1_channel_id', ' 1454413000650788949 ');
        config()->set('services.discord.operation_lobby_2_channel_id', '1454413000650788949');

        $service = new OperationRuntimeDiscordService();

        $this->assertSame(['1454413000650788949'], $service->lobbyChannelIds());
    }

    public function test_sync_lobby_returns_configuration_error_when_required_settings_are_missing(): void
    {
        config()->set('services.bot.url', null);
        config()->set('services.bot.secret', null);
        config()->set('services.discord.operation_lobby_1_channel_id', null);
        config()->set('services.discord.operation_lobby_2_channel_id', null);

        $service = new OperationRuntimeDiscordService();
        $result = $service->syncLobby($this->operation());

        $this->assertFalse($result['ok']);
        $this->assertSame('Discord bot webhook URL is not configured yet.', $result['message']);
        $this->assertSame([], $result['data']);
    }

    public function test_sync_lobby_normalizes_successful_bot_payloads(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '111');
        config()->set('services.discord.operation_lobby_2_channel_id', '222');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-lobby' => Http::response([
                'message' => 'Lobby synced.',
                'source_channel_ids' => ['111', '', '222'],
                'present_discord_ids' => ['555', '', '666'],
                'present_by_channel' => ['111' => ['555'], '222' => ['666']],
            ]),
        ]);

        $service = new OperationRuntimeDiscordService();
        $result = $service->syncLobby($this->operation());

        $this->assertTrue($result['ok']);
        $this->assertSame('Lobby synced.', $result['message']);
        $this->assertSame(['111', '222'], $result['data']['source_channel_ids']);
        $this->assertSame(['555', '666'], $result['data']['present_discord_ids']);
        $this->assertSame(['111' => ['555'], '222' => ['666']], $result['data']['present_by_channel']);
    }

    public function test_sync_operation_channels_requires_category_and_role_configuration(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '111');
        config()->set('services.discord.operation_lobby_2_channel_id', '222');
        config()->set('services.discord.operation_category_id', null);
        config()->set('services.discord.operation_member_role_id', null);

        $service = new OperationRuntimeDiscordService();
        $result = $service->syncOperationChannels($this->operation(), [], []);

        $this->assertFalse($result['ok']);
        $this->assertSame('Operation Discord category id is not configured yet.', $result['message']);
    }

    public function test_sync_operation_channels_normalizes_bot_response_counts_and_ids(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '111');
        config()->set('services.discord.operation_lobby_2_channel_id', '222');
        config()->set('services.discord.operation_category_id', '999');
        config()->set('services.discord.operation_member_role_id', '777');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'message' => 'Channels synced.',
                'channels' => [
                    ['operation_channel_id' => 5, 'discord_channel_id' => '12345'],
                ],
                'moved_member_count' => '4',
                'created_channel_count' => '2',
                'renamed_channel_count' => 1,
                'deleted_channel_count' => '3',
                'missing_guild_member_ids' => ['111', '', '222'],
            ]),
        ]);

        $service = new OperationRuntimeDiscordService();
        $result = $service->syncOperationChannels($this->operation(), [['operation_channel_id' => 5]], ['333']);

        $this->assertTrue($result['ok']);
        $this->assertSame('Channels synced.', $result['message']);
        $this->assertSame(4, $result['data']['moved_member_count']);
        $this->assertSame(2, $result['data']['created_channel_count']);
        $this->assertSame(1, $result['data']['renamed_channel_count']);
        $this->assertSame(3, $result['data']['deleted_channel_count']);
        $this->assertSame(['111', '222'], $result['data']['missing_guild_member_ids']);
        $this->assertSame([['operation_channel_id' => 5, 'discord_channel_id' => '12345']], $result['data']['channels']);
    }

    public function test_sync_operation_channels_uses_fallback_error_message_for_failed_bot_responses(): void
    {
        config()->set('services.bot.url', 'http://bot.test/bot');
        config()->set('services.bot.secret', 'secret');
        config()->set('services.discord.operation_lobby_1_channel_id', '111');
        config()->set('services.discord.operation_lobby_2_channel_id', '222');
        config()->set('services.discord.operation_category_id', '999');
        config()->set('services.discord.operation_member_role_id', '777');

        Http::fake([
            'http://bot.test/bot/operations/runtime/sync-channels' => Http::response([
                'unexpected' => 'payload',
            ], 503),
        ]);

        $service = new OperationRuntimeDiscordService();
        $result = $service->syncOperationChannels($this->operation(), [], []);

        $this->assertFalse($result['ok']);
        $this->assertSame('Discord bot rejected the request (503).', $result['message']);
        $this->assertSame(['unexpected' => 'payload'], $result['data']);
    }

    private function operation(array $attributes = []): Operation
    {
        $operation = new Operation();
        $operation->forceFill(array_merge([
            'created_by' => User::factory()->create([
                'global_status' => User::STATUS_ACTIVE,
                'rsi_verified_at' => now(),
            ])->id,
            'title' => 'Discord Runtime Test',
            'description' => 'Operation for Discord runtime bridge tests.',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
            'status' => OperationStatus::Published->value,
        ], $attributes));
        $operation->save();

        return $operation->fresh();
    }
}
