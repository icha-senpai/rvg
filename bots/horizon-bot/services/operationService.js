const { EmbedBuilder } = require('discord.js');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
    if (LOGS_ENABLED) {
        console.log(...args);
    }
};

function normalizeDiscordId(value) {
    const normalized = String(value ?? '').trim();

    return /^\d+$/.test(normalized) ? normalized : null;
}

function normalizeAnnouncementTargets(rawTargets) {
    if (!Array.isArray(rawTargets)) {
        return [];
    }

    return rawTargets
        .map((target) => {
            const channelId = normalizeDiscordId(target?.channel_id);

            if (!channelId) {
                return null;
            }

            return {
                channelId,
                squadronId: target?.squadron_id ?? null,
                squadronName: typeof target?.squadron_name === 'string'
                    ? target.squadron_name.trim()
                    : '',
            };
        })
        .filter(Boolean)
        .filter((target, index, list) => list.findIndex((item) => item.channelId === target.channelId) === index);
}

async function fetchMessageChannel(client, channelId) {
    const channel = await client.channels.fetch(channelId);

    if (!channel || !channel.send || !channel.messages || typeof channel.messages.delete !== 'function') {
        throw new Error(`Channel ${channelId} is not message-capable`);
    }

    return channel;
}

module.exports = {
    async announceOperation(client, op, actionText = 'A new operation has been posted') {
        const channelId = process.env.OP_ANNOUNCE_CHANNEL_ID;
        const roleIdToPing = process.env.OP_ANNOUNCE_ROLE_ID;

        const shouldPing = op?.ping !== false;
        const announceToDefaultChannel = op?.announce_to_default_channel !== false;
        const customTargets = normalizeAnnouncementTargets(op?.discord_targets);

        let operationUrl = null;
        if (process.env.API_BASE_URL && op?.id) {
            try {
                const apiBaseUrl = new URL(process.env.API_BASE_URL);
                const baseUrl = apiBaseUrl.origin;
                operationUrl = `${baseUrl}/operations/${op.id}`;
            } catch (e) {
                operationUrl = null;
            }
        }

        const announcementTargets = [];

        if (customTargets.length > 0) {
            announcementTargets.push(...customTargets.map((target) => ({
                ...target,
                kind: 'squadron',
            })));
        }

        if (announceToDefaultChannel) {
            if (!channelId) {
                console.error('❌ OP_ANNOUNCE_CHANNEL_ID missing in .env!');
                return null;
            }

            announcementTargets.push({
                channelId,
                kind: 'default',
                squadronId: null,
                squadronName: '',
            });
        }

        if (announcementTargets.length === 0) {
            log(`[OpWebhook] No Discord announcement targets resolved for operation ${op?.id}`);
            return null;
        }

        // ------------------------------
        // DESCRIPTION (fallback safe)
        // ------------------------------
        const desc =
            op.description && op.description.trim().length > 0
                ? op.description
                : '*No description provided.*';

        const leaderDiscordId = typeof op?.operation_leader_discord_id === 'string'
            ? op.operation_leader_discord_id
            : null;

        const leaderFallbackName = typeof op?.operation_leader === 'string'
            ? op.operation_leader.trim()
            : '';

        let leaderHeaderName = typeof op?.operation_leader_discord_name === 'string'
            ? op.operation_leader_discord_name.trim()
            : leaderFallbackName;

        let leaderHeaderIconUrl = null;

        if (leaderDiscordId && process.env.DISCORD_GUILD_ID) {
            try {
                const guild = await client.guilds.fetch(process.env.DISCORD_GUILD_ID);
                const member = await guild.members.fetch(leaderDiscordId);

                leaderHeaderName =
                    member?.nickname
                    || member?.displayName
                    || member?.user?.username
                    || leaderHeaderName;

                leaderHeaderIconUrl = member?.displayAvatarURL
                    ? member.displayAvatarURL({ size: 64 })
                    : leaderHeaderIconUrl;
            } catch (e) {
            }
        }

        if (!leaderHeaderIconUrl) {
            const rawAvatar = typeof op?.operation_leader_discord_avatar === 'string'
                ? op.operation_leader_discord_avatar.trim()
                : '';

            if (rawAvatar.startsWith('http://') || rawAvatar.startsWith('https://')) {
                leaderHeaderIconUrl = rawAvatar;
            } else if (rawAvatar && leaderDiscordId) {
                leaderHeaderIconUrl = `https://cdn.discordapp.com/avatars/${leaderDiscordId}/${rawAvatar}.png?size=64`;
            }
        }

        const kind = typeof op?.operation_kind === 'string'
            ? op.operation_kind
            : (typeof op?.operation_type === 'string' ? op.operation_type : '');
        const titlePrefix =
            kind === 'squadron_training'
                ? 'Squadron Training'
                : kind === 'wing_training'
                    ? 'Wing Training'
                    : '';
        const displayTitle = titlePrefix ? `${titlePrefix}: ${op.title}` : op.title;

        // ------------------------------
        // FORMAT START TIME → DISCORD TIMESTAMP
        // ------------------------------
        let tsDisplay = 'N/A';
        if (op.starts_at) {
            const unix = Math.floor(new Date(op.starts_at).getTime() / 1000);
            tsDisplay = `<t:${unix}:f>`;
        }

        // ------------------------------
        // BUILD EMBED
        // ------------------------------
        const startLocationRaw = typeof op?.start_location === 'string' ? op.start_location.trim() : '';
        const startLocation = startLocationRaw.length > 1021
            ? `${startLocationRaw.slice(0, 1021)}...`
            : startLocationRaw;

        const operationLeaderRaw = typeof op?.operation_leader === 'string' ? op.operation_leader.trim() : '';
        const operationLeader = operationLeaderRaw.length > 1021
            ? `${operationLeaderRaw.slice(0, 1021)}...`
            : operationLeaderRaw;

        const fields = [
            {
                name: 'Starts At',
                value: op.starts_at_discord || "N/A",
                inline: false,
            },
            {
                name: 'Start Location',
                value: startLocation || 'N/A',
                inline: false,
            },
            {
                name: 'Operation Leader',
                value: operationLeader || 'N/A',
                inline: false,
            },
            {
                name: 'Comm Strictness',
                value: op.operation_strictness || 'default',
                inline: false,
            },
        ];

        const squadronName = typeof op.squadron_name === 'string' ? op.squadron_name.trim() : '';
        if (squadronName) {
            fields.push({
                name: 'Squadron',
                value: squadronName,
                inline: false,
            });
        }

        const embed = new EmbedBuilder()
            .setColor(0x00a3ff)
            .setTitle(null)
            .setDescription(
                `${operationUrl ? `# [${displayTitle}](${operationUrl})\n` : `# ${displayTitle}\n`}` +
                `\n` +
                `${desc}`
            )
            .addFields(fields)
            .setFooter({ text: `Operation ID: ${op.id}` })
            .setTimestamp();

        if (leaderHeaderName) {
            embed.setAuthor({
                name: leaderHeaderName,
                iconURL: leaderHeaderIconUrl || undefined,
            });
        }

        // ------------------------------
        // SEND TO DISCORD
        // ------------------------------
        try {
            const messageTargets = [];

            for (const target of announcementTargets) {
                const channel = await fetchMessageChannel(client, target.channelId);
                const shouldPingRole = target.kind === 'default' && roleIdToPing && shouldPing;
                const message = await channel.send({
                    content: shouldPingRole
                        ? `${actionText} <@&${roleIdToPing}>`
                        : actionText,
                    embeds: [embed],
                    allowedMentions: shouldPingRole
                        ? { roles: [roleIdToPing] }
                        : { parse: [] },
                });

                if (message?.id) {
                    messageTargets.push({
                        channel_id: target.channelId,
                        message_id: message.id,
                    });
                }
            }

            log(`📢 Operation announced: ${op.title} (#${op.id})`, {
                targetCount: messageTargets.length,
            });

            return {
                messageId: messageTargets[0]?.message_id ?? null,
                messageTargets,
            };
        } catch (err) {
            console.error('❌ Failed to send operation embed:', err);
            return null;
        }
    },

    async announceOperationUpdated(client, op) {
        return this.announceOperation(client, op, 'An operation has been updated');
    },

    async deleteOperationAnnouncement(client, payload) {
        const channelId = process.env.OP_ANNOUNCE_CHANNEL_ID;
        const messageId = typeof payload === 'string' ? payload : payload?.message_id;
        const messageTargets = Array.isArray(payload?.message_targets)
            ? payload.message_targets
            : [];

        const normalizedTargets = messageTargets
            .map((target) => {
                const targetChannelId = normalizeDiscordId(target?.channel_id);
                const targetMessageId = normalizeDiscordId(target?.message_id);

                if (!targetChannelId || !targetMessageId) {
                    return null;
                }

                return {
                    channelId: targetChannelId,
                    messageId: targetMessageId,
                };
            })
            .filter(Boolean);

        if (normalizedTargets.length > 0) {
            let deletedCount = 0;
            let notFoundCount = 0;

            for (const target of normalizedTargets) {
                let channel = null;

                try {
                    channel = await fetchMessageChannel(client, target.channelId);
                    await channel.messages.delete(target.messageId);
                    deletedCount += 1;
                } catch (err) {
                    if (err && typeof err === 'object' && err.code === 10008) {
                        notFoundCount += 1;
                        continue;
                    }

                    console.error('❌ Failed to delete operation announcement:', err);
                    return { status: 'error', reason: 'delete_failed' };
                }
            }

            if (deletedCount === 0 && notFoundCount > 0) {
                return { status: 'not_found' };
            }

            return { status: 'deleted' };
        }

        if (!channelId) {
            console.error('❌ OP_ANNOUNCE_CHANNEL_ID missing in .env!');
            return { status: 'error', reason: 'missing_channel_id' };
        }

        if (!messageId || typeof messageId !== 'string') {
            return { status: 'error', reason: 'missing_message_id' };
        }

        try {
            const channel = await fetchMessageChannel(client, channelId);
            await channel.messages.delete(messageId);
            return { status: 'deleted' };
        } catch (err) {
            if (err && typeof err === 'object' && err.code === 10008) {
                return { status: 'not_found' };
            }

            console.error('❌ Failed to delete operation announcement:', err);
            return { status: 'error', reason: 'delete_failed' };
        }
    },
};
