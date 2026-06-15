const express = require('express');
const router = express.Router();
const operationRuntimeService = require('./operationRuntimeService');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
  if (LOGS_ENABLED) {
    console.log(...args);
  }
};

function isAuthorized(req) {
  return req.headers['x-bot-secret'] === process.env.DISCORD_BOT_SECRET;
}

router.post('/operations/runtime/sync-lobby', async (req, res) => {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.operation_id) {
    return res.status(400).json({ message: 'operation_id is required' });
  }

  try {
    const client = req.app.get('client');
    const result = await operationRuntimeService.syncLobbyPresence(client, payload);

    log('[OperationRuntimeWebhook] sync-lobby', {
      operationId: payload.operation_id,
      lobbyChannelIds: result.sourceChannelIds,
      presentCount: result.presentDiscordIds.length,
    });

    return res.json({
      message: 'Operation lobby sync completed.',
      source_channel_ids: result.sourceChannelIds,
      present_discord_ids: result.presentDiscordIds,
      present_by_channel: result.presentByChannel,
    });
  } catch (error) {
    console.error('[OperationRuntimeWebhook] sync-lobby failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while syncing operation lobbies.',
    });
  }
});

router.post('/operations/runtime/sync-channels', async (req, res) => {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.operation_id) {
    return res.status(400).json({ message: 'operation_id is required' });
  }

  try {
    const client = req.app.get('client');
    const result = await operationRuntimeService.syncOperationChannels(client, payload);

    log('[OperationRuntimeWebhook] sync-channels', {
      operationId: payload.operation_id,
      channelCount: result.channels.length,
      createdChannelCount: result.createdChannelCount,
      deletedChannelCount: result.deletedChannelCount,
      movedMemberCount: result.movedMemberCount,
    });

    return res.json({
      message: 'Operation Discord channels synced.',
      channels: result.channels,
      created_channel_count: result.createdChannelCount,
      renamed_channel_count: result.renamedChannelCount,
      deleted_channel_count: result.deletedChannelCount,
      moved_member_count: result.movedMemberCount,
      missing_guild_member_ids: result.missingGuildMemberIds,
    });
  } catch (error) {
    console.error('[OperationRuntimeWebhook] sync-channels failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while syncing operation channels.',
    });
  }
});

module.exports = router;
