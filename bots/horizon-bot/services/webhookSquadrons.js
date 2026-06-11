const express = require('express');
const router = express.Router();
const squadronDiscordService = require('./squadronDiscordService');

function isAuthorized(req) {
  return req.headers['x-bot-secret'] === process.env.DISCORD_BOT_SECRET;
}

router.post('/squadrons/channel/create', async (req, res) => {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.squadron_id || !payload.squadron_name) {
    return res.status(400).json({ message: 'squadron_id and squadron_name are required' });
  }

  try {
    const client = req.app.get('client');
    const result = await squadronDiscordService.createSquadronChannel(client, payload);

    return res.json({
      message: 'Squadron channel created.',
      channel_id: result.channelId,
      channel_name: result.channelName,
    });
  } catch (error) {
    console.error('[SquadronWebhook] create-channel failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while creating squadron channel.',
    });
  }
});

router.post('/squadrons/channel/delete', async (req, res) => {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.squadron_id || !payload.discord_channel_id) {
    return res.status(400).json({ message: 'squadron_id and discord_channel_id are required' });
  }

  try {
    const client = req.app.get('client');
    const result = await squadronDiscordService.deleteSquadronChannel(client, payload);

    return res.json({
      message: 'Squadron channel deleted.',
      channel_id: result.channelId,
    });
  } catch (error) {
    console.error('[SquadronWebhook] delete-channel failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while deleting squadron channel.',
    });
  }
});

router.post('/squadrons/lieutenant/promote', async (req, res) => {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.member_discord_id || !payload.lieutenant_role_id) {
    return res.status(400).json({ message: 'member_discord_id and lieutenant_role_id are required' });
  }

  try {
    const client = req.app.get('client');
    const result = await squadronDiscordService.promoteSquadronLieutenant(client, payload);

    return res.json({
      message: 'Squadron lieutenant promotion synced.',
      member_discord_id: result.memberDiscordId,
      announcement_message_id: result.announcementMessageId,
    });
  } catch (error) {
    console.error('[SquadronWebhook] lieutenant promote failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while syncing lieutenant promotion.',
    });
  }
});

router.post('/squadrons/lieutenant/demote', async (req, res) => {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.member_discord_id || !payload.lieutenant_role_id) {
    return res.status(400).json({ message: 'member_discord_id and lieutenant_role_id are required' });
  }

  try {
    const client = req.app.get('client');
    const result = await squadronDiscordService.demoteSquadronLieutenant(client, payload);

    return res.json({
      message: 'Squadron lieutenant demotion synced.',
      member_discord_id: result.memberDiscordId,
    });
  } catch (error) {
    console.error('[SquadronWebhook] lieutenant demote failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while syncing lieutenant demotion.',
    });
  }
});

async function handleSquadronChannelSync(req, res) {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.squadron_id || !payload.discord_channel_id) {
    return res.status(400).json({ message: 'squadron_id and discord_channel_id are required' });
  }

  try {
    const client = req.app.get('client');
    const result = await squadronDiscordService.syncSquadronChannelAccess(client, payload);

    return res.json({
      message: 'Squadron channel synced.',
      synced_member_count: result.syncedMemberCount,
      removed_overwrite_count: result.removedOverwriteCount,
    });
  } catch (error) {
    console.error('[SquadronWebhook] sync failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while syncing squadron access.',
    });
  }
}

router.post('/squadrons/sync', handleSquadronChannelSync);
router.post('/squadrons/channel/sync', handleSquadronChannelSync);

router.post('/squadrons/shared-role/sync-members', async (req, res) => {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.shared_role_id) {
    return res.status(400).json({ message: 'shared_role_id is required' });
  }

  try {
    const client = req.app.get('client');
    const result = await squadronDiscordService.syncSharedSquadronRoleMembers(client, payload);

    return res.json({
      message: 'Shared Squadron role sync completed.',
      added_shared_role_count: result.addedSharedRoleCount,
      removed_shared_role_count: result.removedSharedRoleCount,
      missing_guild_member_ids: result.missingGuildMemberIds,
      shared_role_add_failed_ids: result.sharedRoleAddFailedIds,
      shared_role_remove_failed_ids: result.sharedRoleRemoveFailedIds,
    });
  } catch (error) {
    console.error('[SquadronWebhook] shared-role sync-members failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while syncing the shared Squadron role.',
    });
  }
});

router.post('/squadrons/shared-role/repair', async (req, res) => {
  if (!isAuthorized(req)) {
    return res.status(403).json({ message: 'Forbidden' });
  }

  const payload = req.body || {};

  if (!payload.shared_role_id) {
    return res.status(400).json({ message: 'shared_role_id is required' });
  }

  try {
    const client = req.app.get('client');
    const result = await squadronDiscordService.repairSharedSquadronRole(client, payload);

    return res.json({
      message: 'Shared Squadron role repair completed.',
      added_shared_role_count: result.addedSharedRoleCount,
      removed_shared_role_count: result.removedSharedRoleCount,
      missing_guild_member_ids: result.missingGuildMemberIds,
      shared_role_add_failed_ids: result.sharedRoleAddFailedIds,
      shared_role_remove_failed_ids: result.sharedRoleRemoveFailedIds,
    });
  } catch (error) {
    console.error('[SquadronWebhook] shared-role repair failed:', error);

    return res.status(500).json({
      message: error?.message || 'Internal bot error while repairing the shared Squadron role.',
    });
  }
});

module.exports = router;
