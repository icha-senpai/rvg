const {
  ChannelType,
  OverwriteType,
  PermissionFlagsBits,
} = require('discord.js');

function normalizeDiscordIds(values) {
  if (!Array.isArray(values)) {
    return [];
  }

  return [...new Set(
    values
      .map((value) => String(value ?? '').trim())
      .filter((value) => /^\d+$/.test(value))
  )];
}

function normalizeChannelName(value) {
  return String(value ?? '')
    .trim()
    .replace(/\s+/g, ' ')
    .slice(0, 100);
}

async function fetchGuild(client) {
  if (!process.env.DISCORD_GUILD_ID) {
    throw new Error('DISCORD_GUILD_ID missing in bot environment');
  }

  return client.guilds.fetch(process.env.DISCORD_GUILD_ID);
}

async function fetchChannel(client, channelId) {
  const channel = await client.channels.fetch(channelId);

  if (!channel) {
    throw new Error(`Discord channel ${channelId} could not be found`);
  }

  if (!channel.isVoiceBased?.() && channel.type !== ChannelType.GuildVoice && channel.type !== ChannelType.GuildStageVoice) {
    throw new Error(`Discord channel ${channelId} is not a voice channel`);
  }

  return channel;
}

async function syncLobbyPresence(client, payload) {
  const lobbyChannelIds = normalizeDiscordIds(payload?.lobby_channel_ids);

  if (lobbyChannelIds.length === 0) {
    throw new Error('lobby_channel_ids is required');
  }

  const presentDiscordIds = new Set();
  const presentByChannel = {};

  for (const channelId of lobbyChannelIds) {
    const channel = await fetchChannel(client, channelId);
    const memberIds = [...channel.members.keys()];

    presentByChannel[channelId] = memberIds;

    for (const memberId of memberIds) {
      presentDiscordIds.add(memberId);
    }
  }

  return {
    sourceChannelIds: lobbyChannelIds,
    presentDiscordIds: [...presentDiscordIds],
    presentByChannel,
  };
}

async function syncOperationChannels(client, payload) {
  const guild = await fetchGuild(client);
  const categoryId = String(payload?.category_id ?? '').trim();
  const memberRoleId = String(payload?.member_role_id ?? '').trim();
  const channels = Array.isArray(payload?.channels) ? payload.channels : [];
  const deleteChannelIds = normalizeDiscordIds(payload?.delete_channel_ids);

  if (!categoryId) {
    throw new Error('category_id is required');
  }

  if (!memberRoleId) {
    throw new Error('member_role_id is required');
  }

  if (channels.length === 0 && deleteChannelIds.length === 0) {
    throw new Error('channels is required');
  }

  let createdChannelCount = 0;
  let renamedChannelCount = 0;
  let deletedChannelCount = 0;
  let movedMemberCount = 0;
  const missingGuildMemberIds = [];
  const channelResults = [];

  for (const channelId of deleteChannelIds) {
    try {
      const channel = await fetchChannel(client, channelId);
      await channel.delete(`Deleted for operation ${payload?.operation_title || payload?.operation_id || channelId}`);
      deletedChannelCount += 1;
    } catch (error) {
      if (error?.code === 10003) {
        continue;
      }

      throw error;
    }
  }

  for (const channelConfig of channels) {
    const operationChannelId = Number(channelConfig?.operation_channel_id ?? 0);
    const requestedName = normalizeChannelName(channelConfig?.name);
    const existingDiscordChannelId = String(channelConfig?.discord_channel_id ?? '').trim();
    const memberDiscordIds = normalizeDiscordIds(channelConfig?.member_discord_ids);

    if (!operationChannelId || !requestedName) {
      continue;
    }

    let channel = null;

    if (existingDiscordChannelId) {
      try {
        channel = await fetchChannel(client, existingDiscordChannelId);
      } catch (error) {
        channel = null;
      }
    }

    if (!channel) {
      channel = await guild.channels.create({
        name: requestedName,
        type: ChannelType.GuildVoice,
        parent: categoryId,
        reason: `Created for operation ${payload?.operation_title || payload?.operation_id || operationChannelId}`,
      });
      createdChannelCount += 1;
    } else {
      const updates = {};

      if (channel.name !== requestedName) {
        updates.name = requestedName;
      }

      if (channel.parentId !== categoryId) {
        updates.parent = categoryId;
      }

      if (Object.keys(updates).length > 0) {
        await channel.edit(updates, `Synced for operation ${payload?.operation_title || payload?.operation_id || operationChannelId}`);
        renamedChannelCount += updates.name ? 1 : 0;
      }
    }

    await channel.permissionOverwrites.edit(
      guild.roles.everyone.id,
      {
        ViewChannel: false,
        Connect: false,
      },
      { reason: `Lock operation voice channel ${requestedName}` }
    );

    await channel.permissionOverwrites.edit(
      memberRoleId,
      {
        ViewChannel: true,
        Connect: false,
      },
      { reason: `Members can view but not join ${requestedName} without operation assignment` }
    );

    for (const discordId of memberDiscordIds) {
      await channel.permissionOverwrites.edit(
        discordId,
        {
          ViewChannel: true,
          Connect: true,
          Speak: true,
          Stream: true,
          UseVAD: true,
        },
        { reason: `Assigned to operation channel ${requestedName}` }
      );

      let guildMember = null;

      try {
        guildMember = await guild.members.fetch(discordId);
      } catch (error) {
        if (error?.code === 10007) {
          missingGuildMemberIds.push(discordId);
          continue;
        }

        throw error;
      }

      if (guildMember?.voice?.channelId && guildMember.voice.channelId !== channel.id) {
        await guildMember.voice.setChannel(channel, `Moved into assigned operation channel ${requestedName}`);
        movedMemberCount += 1;
      }
    }

    const staleMemberOverwriteIds = channel.permissionOverwrites.cache
      .filter((overwrite) => overwrite.type === OverwriteType.Member)
      .filter((overwrite) => !memberDiscordIds.includes(overwrite.id))
      .map((overwrite) => overwrite.id);

    for (const overwriteId of staleMemberOverwriteIds) {
      await channel.permissionOverwrites.delete(
        overwriteId,
        `Removed stale operation access for ${requestedName}`
      );
    }

    channelResults.push({
      operation_channel_id: operationChannelId,
      discord_channel_id: channel.id,
      name: channel.name,
    });
  }

  return {
    channels: channelResults,
    createdChannelCount,
    renamedChannelCount,
    deletedChannelCount,
    movedMemberCount,
    missingGuildMemberIds,
  };
}

module.exports = {
  syncLobbyPresence,
  syncOperationChannels,
};
