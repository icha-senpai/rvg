const {
  ChannelType,
  OverwriteType,
  PermissionFlagsBits,
} = require('discord.js');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
  if (LOGS_ENABLED) {
    console.log(...args);
  }
};

function normalizeDiscordIds(values) {
  if (!Array.isArray(values)) {
    return [];
  }

  return [...new Set(
    values
      .map(value => String(value ?? '').trim())
      .filter(value => /^\d+$/.test(value))
  )];
}

function buildChannelName(rawName) {
  return String(rawName ?? '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-');
}

async function fetchGuild(client) {
  if (!process.env.DISCORD_GUILD_ID) {
    throw new Error('DISCORD_GUILD_ID missing in bot environment');
  }

  return client.guilds.fetch(process.env.DISCORD_GUILD_ID);
}

async function fetchTextChannel(client, channelId) {
  const channel = await client.channels.fetch(channelId);

  if (!channel || channel.type !== ChannelType.GuildText) {
    throw new Error('Configured squadron channel is not a guild text channel');
  }

  return channel;
}

async function fetchGuildMember(guild, discordId) {
  const cached = guild.members.cache.get(discordId);
  if (cached) {
    return cached;
  }

  try {
    return await guild.members.fetch(discordId);
  } catch (error) {
    if (error?.code === 10007) {
      return null;
    }

    throw error;
  }
}

async function fetchAllGuildMembers(guild) {
  return guild.members.fetch();
}

async function createSquadronChannel(client, payload) {
  const guild = await fetchGuild(client);
  const categoryId = String(payload?.category_id ?? '').trim();
  const sharedRoleId = String(payload?.shared_role_id ?? '').trim();
  const channelName = buildChannelName(payload?.channel_name || payload?.squadron_name);

  if (!categoryId) {
    throw new Error('Discord squadron category id missing from payload');
  }

  if (!channelName) {
    throw new Error('Discord squadron channel name could not be derived');
  }

  const channel = await guild.channels.create({
    name: channelName,
    type: ChannelType.GuildText,
    parent: categoryId,
    reason: `Created for squadron ${payload?.squadron_name || payload?.squadron_id || 'unknown'}`,
    permissionOverwrites: [
      {
        id: guild.roles.everyone.id,
        deny: [PermissionFlagsBits.ViewChannel],
      },
      ...(sharedRoleId
        ? [{
            id: sharedRoleId,
            deny: [PermissionFlagsBits.ViewChannel],
          }]
        : []),
    ],
  });

  return {
    channelId: channel.id,
    channelName: channel.name,
  };
}

async function deleteSquadronChannel(client, payload) {
  const channelId = String(payload?.discord_channel_id ?? '').trim();

  if (!channelId) {
    throw new Error('discord_channel_id is required');
  }

  const channel = await fetchTextChannel(client, channelId);

  await channel.delete(`Deleted for squadron ${payload?.squadron_name || payload?.squadron_id || channelId}`);

  return {
    channelId,
  };
}

async function promoteSquadronLieutenant(client, payload) {
  const guild = await fetchGuild(client);
  const memberDiscordId = String(payload?.member_discord_id ?? '').trim();
  const lieutenantRoleId = String(payload?.lieutenant_role_id ?? '').trim();
  const channelId = String(payload?.discord_channel_id ?? '').trim();
  const announcementMessage = String(payload?.announcement_message ?? '').trim();

  if (!memberDiscordId) {
    throw new Error('member_discord_id is required');
  }

  if (!lieutenantRoleId) {
    throw new Error('lieutenant_role_id is required');
  }

  const member = await fetchGuildMember(guild, memberDiscordId);
  if (!member) {
    throw new Error('Discord guild member for lieutenant promotion could not be found');
  }

  if (!member.roles.cache.has(lieutenantRoleId)) {
    await member.roles.add(lieutenantRoleId, 'Promoted to Lieutenant in Horizon');
  }

  let announcementMessageId = null;

  if (channelId && announcementMessage) {
    const channel = await fetchTextChannel(client, channelId);
    const message = await channel.send({ content: announcementMessage });
    announcementMessageId = message.id;
  }

  return {
    memberDiscordId,
    announcementMessageId,
  };
}

async function demoteSquadronLieutenant(client, payload) {
  const guild = await fetchGuild(client);
  const memberDiscordId = String(payload?.member_discord_id ?? '').trim();
  const lieutenantRoleId = String(payload?.lieutenant_role_id ?? '').trim();

  if (!memberDiscordId) {
    throw new Error('member_discord_id is required');
  }

  if (!lieutenantRoleId) {
    throw new Error('lieutenant_role_id is required');
  }

  const member = await fetchGuildMember(guild, memberDiscordId);
  if (!member) {
    throw new Error('Discord guild member for lieutenant demotion could not be found');
  }

  if (member.roles.cache.has(lieutenantRoleId)) {
    await member.roles.remove(lieutenantRoleId, 'Demoted from Lieutenant in Horizon');
  }

  return {
    memberDiscordId,
  };
}

async function syncSquadronChannelAccess(client, payload) {
  const guild = await fetchGuild(client);
  const channelId = String(payload?.discord_channel_id ?? '').trim();
  const sharedRoleId = String(payload?.shared_role_id ?? '').trim();
  const activeMemberIds = new Set(normalizeDiscordIds(payload?.active_member_discord_ids));

  if (!channelId) {
    throw new Error('discord_channel_id is required');
  }

  if (!sharedRoleId) {
    throw new Error('shared_role_id is required');
  }

  const channel = await fetchTextChannel(client, channelId);

  log('[SquadronWebhook] sync start', {
    squadronId: payload?.squadron_id,
    squadronName: payload?.squadron_name,
    channelId,
    sharedRoleId,
    activeMemberIds: [...activeMemberIds],
  });

  await channel.permissionOverwrites.edit(
    guild.roles.everyone.id,
    { ViewChannel: false },
    { reason: `Lock squadron channel ${payload?.squadron_name || payload?.squadron_id || channelId}` }
  );

  await channel.permissionOverwrites.edit(
    sharedRoleId,
    { ViewChannel: false },
    { reason: `Shared squadron role should not grant channel access for ${payload?.squadron_name || payload?.squadron_id || channelId}` }
  );

  const missingMemberIds = [];

  for (const discordId of activeMemberIds) {
    const member = await fetchGuildMember(guild, discordId);

    if (!member) {
      missingMemberIds.push(discordId);
      log('[SquadronWebhook] skipping missing guild member during channel sync', {
        squadronId: payload?.squadron_id,
        squadronName: payload?.squadron_name,
        channelId,
        discordId,
      });
      continue;
    }

    await channel.permissionOverwrites.edit(
      member,
      {
        ViewChannel: true,
        SendMessages: true,
        SendMessagesInThreads: true,
        ReadMessageHistory: true,
        CreatePublicThreads: true,
        CreatePrivateThreads: true,
        EmbedLinks: true,
        AttachFiles: true,
        AddReactions: true,
        UseExternalEmojis: true,
        UseExternalStickers: true,
      },
      {
        type: OverwriteType.Member,
        reason: `Sync squadron access for ${payload?.squadron_name || payload?.squadron_id || channelId}`,
      }
    );
  }

  const staleMemberOverwriteIds = channel.permissionOverwrites.cache
    .filter(overwrite => overwrite.type === OverwriteType.Member)
    .filter(overwrite => !activeMemberIds.has(overwrite.id))
    .map(overwrite => overwrite.id);

  for (const overwriteId of staleMemberOverwriteIds) {
    await channel.permissionOverwrites.delete(
      overwriteId,
      `Removed stale squadron access for ${payload?.squadron_name || payload?.squadron_id || channelId}`
    );
  }

  return {
    syncedMemberCount: activeMemberIds.size - missingMemberIds.length,
    missingMemberIds,
    removedOverwriteCount: staleMemberOverwriteIds.length,
  };
}

async function syncSharedSquadronRoleMembers(client, payload) {
  const guild = await fetchGuild(client);
  const sharedRoleId = String(payload?.shared_role_id ?? '').trim();
  const activeSquadronMemberIds = new Set(normalizeDiscordIds(payload?.active_squadron_member_discord_ids));
  const touchedMemberIds = normalizeDiscordIds(payload?.member_discord_ids);

  if (!sharedRoleId) {
    throw new Error('shared_role_id is required');
  }

  return reconcileSharedSquadronRole(guild, sharedRoleId, activeSquadronMemberIds, touchedMemberIds, {
    reasonPrefix: 'Targeted shared squadron role sync',
    logContext: { mode: 'targeted' },
  });
}

async function repairSharedSquadronRole(client, payload) {
  const guild = await fetchGuild(client);
  const sharedRoleId = String(payload?.shared_role_id ?? '').trim();
  const activeSquadronMemberIds = new Set(normalizeDiscordIds(payload?.active_squadron_member_discord_ids));

  if (!sharedRoleId) {
    throw new Error('shared_role_id is required');
  }

  const members = await fetchAllGuildMembers(guild);
  const currentHolderIds = members
    .filter(member => member.roles.cache.has(sharedRoleId))
    .map(member => member.id);

  return reconcileSharedSquadronRole(guild, sharedRoleId, activeSquadronMemberIds, [
    ...activeSquadronMemberIds,
    ...currentHolderIds,
  ], {
    reasonPrefix: 'Full shared squadron role repair',
    logContext: { mode: 'full-repair' },
    prefetchedMembers: members,
  });
}

async function reconcileSharedSquadronRole(guild, sharedRoleId, activeSquadronMemberIds, touchedMemberIds, options = {}) {
  let addedSharedRoleCount = 0;
  let removedSharedRoleCount = 0;
  const missingGuildMemberIds = [];
  const sharedRoleAddFailedIds = [];
  const sharedRoleRemoveFailedIds = [];
  const uniqueTouchedIds = [...new Set(normalizeDiscordIds(touchedMemberIds))];
  const prefetchedMembers = options.prefetchedMembers ?? null;

  log('[SquadronWebhook] shared role sync start', {
    sharedRoleId,
    activeSquadronMemberIds: [...activeSquadronMemberIds],
    touchedMemberIds: uniqueTouchedIds,
    ...options.logContext,
  });

  for (const discordId of uniqueTouchedIds) {
    const member = prefetchedMembers?.get(discordId) ?? await fetchGuildMember(guild, discordId);

    if (!member) {
      log('[SquadronWebhook] guild member missing during shared role sync', {
        discordId,
        sharedRoleId,
        ...options.logContext,
      });
      missingGuildMemberIds.push(discordId);
      continue;
    }

    const shouldHaveSharedRole = activeSquadronMemberIds.has(member.id);
    const hasSharedRole = member.roles.cache.has(sharedRoleId);

    log('[SquadronWebhook] shared role check', {
      discordId: member.id,
      shouldHaveSharedRole,
      hasSharedRole,
      sharedRoleId,
      ...options.logContext,
    });

    if (shouldHaveSharedRole && !hasSharedRole) {
      try {
        await member.roles.add(sharedRoleId, 'User has at least one active Horizon squadron membership');
        addedSharedRoleCount += 1;
        log('[SquadronWebhook] shared role added', {
          discordId: member.id,
          sharedRoleId,
          ...options.logContext,
        });
      } catch (error) {
        console.error('[SquadronWebhook] shared role add failed', {
          discordId,
          sharedRoleId,
          error: error?.message || error,
          ...options.logContext,
        });
        sharedRoleAddFailedIds.push(discordId);
      }
      continue;
    }

    if (!shouldHaveSharedRole && hasSharedRole) {
      try {
        await member.roles.remove(sharedRoleId, 'User no longer has any active Horizon squadron memberships');
        removedSharedRoleCount += 1;
        log('[SquadronWebhook] shared role removed', {
          discordId: member.id,
          sharedRoleId,
          ...options.logContext,
        });
      } catch (error) {
        console.error('[SquadronWebhook] shared role remove failed', {
          discordId,
          sharedRoleId,
          error: error?.message || error,
          ...options.logContext,
        });
        sharedRoleRemoveFailedIds.push(discordId);
      }
    }
  }

  return {
    addedSharedRoleCount,
    removedSharedRoleCount,
    missingGuildMemberIds,
    sharedRoleAddFailedIds,
    sharedRoleRemoveFailedIds,
  };
}

module.exports = {
  createSquadronChannel,
  deleteSquadronChannel,
  promoteSquadronLieutenant,
  demoteSquadronLieutenant,
  syncSquadronChannelAccess,
  syncSharedSquadronRoleMembers,
  repairSharedSquadronRole,
};
