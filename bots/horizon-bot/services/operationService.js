const { EmbedBuilder } = require('discord.js');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
    if (LOGS_ENABLED) {
        console.log(...args);
    }
};

module.exports = {
    async announceOperation(client, op) {
        const channelId = process.env.OP_ANNOUNCE_CHANNEL_ID;
        const roleIdToPing = process.env.OP_ANNOUNCE_ROLE_ID;

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
        const embed = new EmbedBuilder()
            .setColor(0x00a3ff)
            .setTitle(null)
            .setDescription(
                `${operationUrl ? `# [${op.title}](${operationUrl})\n` : `# ${op.title}\n`}` +
                `━━━━━━━━━━━━━━━━━━\n\n` +
                `${desc}`
            )
            .addFields(
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
                {
                    name: 'Squadron',
                    value: op.squadron_name || 'N/A',
                    inline: false,
                }
            )
            .setFooter({ text: `Operation ID: ${op.id}` })
            .setTimestamp();

        // ------------------------------
        // SEND TO DISCORD
        // ------------------------------
        try {
            const content = roleIdToPing
                ? `A new operation has been posted <@&${roleIdToPing}>`
                : 'A new operation has been posted';

            await channel.send({
                content,
                embeds: [embed],
                allowedMentions: roleIdToPing ? { roles: [roleIdToPing] } : undefined,
            });
            log(`📢 Operation announced: ${op.title} (#${op.id})`);
        } catch (err) {
            console.error('❌ Failed to send operation embed:', err);
        }
    },
};
