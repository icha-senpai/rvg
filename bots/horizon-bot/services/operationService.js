const { EmbedBuilder } = require('discord.js');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
    if (LOGS_ENABLED) {
        console.log(...args);
    }
};

module.exports = {
    async announceOperation(client, op, actionText = 'A new operation has been posted') {
        const channelId = process.env.OP_ANNOUNCE_CHANNEL_ID;
        const roleIdToPing = process.env.OP_ANNOUNCE_ROLE_ID;

        const shouldPing = op?.ping !== false;

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

        // ------------------------------
        // VALIDATE CHANNEL
        // ------------------------------
        if (!channelId) {
            console.error('❌ OP_ANNOUNCE_CHANNEL_ID missing in .env!');
            return;
        }

        const channel = client.channels.cache.get(channelId);
        if (!channel) {
            console.error(`❌ Announcement channel (${channelId}) not found!`);
            return;
        }

        // ------------------------------
        // DESCRIPTION (fallback safe)
        // ------------------------------
        const desc =
            op.description && op.description.trim().length > 0
                ? op.description
                : '*No description provided.*';

        const kind = typeof op?.operation_kind === 'string' ? op.operation_kind : '';
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
        const fields = [
            {
                name: 'Starts At',
                value: op.starts_at_discord || "N/A",
                inline: false,
            },
            {
                name: 'Strictness',
                value: op.operation_strictness || 'default',
                inline: false,
            },
            {
                name: 'Visibility',
                value: op.visibility || 'open',
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
                `━━━━━━━━━━━━━━━━━━\n\n` +
                `${desc}`
            )
            .addFields(fields)
            .setFooter({ text: `Operation ID: ${op.id}` })
            .setTimestamp();

        // ------------------------------
        // SEND TO DISCORD
        // ------------------------------
        try {
            const content = roleIdToPing && shouldPing
                ? `${actionText} <@&${roleIdToPing}>`
                : actionText;

            const message = await channel.send({
                content,
                embeds: [embed],
                allowedMentions: roleIdToPing && shouldPing
                    ? { roles: [roleIdToPing] }
                    : { parse: [] },
            });
            log(`📢 Operation announced: ${op.title} (#${op.id})`);

            // Return message id so the website can persist it and delete + repost on update.
            return message?.id || null;
        } catch (err) {
            console.error('❌ Failed to send operation embed:', err);
            return null;
        }
    },

    async announceOperationUpdated(client, op) {
        return this.announceOperation(client, op, 'An operation has been updated');
    },

    async deleteOperationAnnouncement(client, messageId) {
        const channelId = process.env.OP_ANNOUNCE_CHANNEL_ID;

        if (!channelId) {
            console.error('❌ OP_ANNOUNCE_CHANNEL_ID missing in .env!');
            return { status: 'error', reason: 'missing_channel_id' };
        }

        if (!messageId || typeof messageId !== 'string') {
            return { status: 'error', reason: 'missing_message_id' };
        }

        let channel = null;
        try {
            channel = await client.channels.fetch(channelId);
        } catch (err) {
            console.error('❌ Failed to fetch announcement channel:', err);
            return { status: 'error', reason: 'channel_fetch_failed' };
        }

        if (!channel || !channel.messages || typeof channel.messages.delete !== 'function') {
            console.error('❌ Announcement channel is not a message-capable channel');
            return { status: 'error', reason: 'channel_not_message_capable' };
        }

        try {
            await channel.messages.delete(messageId);
            return { status: 'deleted' };
        } catch (err) {
            // Discord "Unknown Message" error code
            if (err && typeof err === 'object' && err.code === 10008) {
                return { status: 'not_found' };
            }

            console.error('❌ Failed to delete operation announcement:', err);
            return { status: 'error', reason: 'delete_failed' };
        }
    },
};
