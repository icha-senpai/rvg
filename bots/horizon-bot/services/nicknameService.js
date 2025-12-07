const { checkVerificationStatus } = require('../utils/api');
const { REST, Routes } = require('discord.js');

module.exports = {
    async enforce(client, discordId) {
        try {
            const status = await checkVerificationStatus(discordId);

            if (!status?.user?.is_verified || !status.user?.rsi_handle) {
                console.log(`[Nickname] ${discordId}: not verified, skipping`);
                return;
            }

            const rsi = status.user.rsi_handle;
            const guild = await client.guilds.fetch(process.env.DISCORD_GUILD_ID);
            const member = await guild.members.fetch(discordId);

            const current = member.nickname || member.user.username;

            if (current === rsi) {
                console.log(`[Nickname] ${discordId} already correct (${rsi})`);
                return;
            }

            await member.setNickname(rsi, 'RSI nickname sync');
            console.log(`[Nickname] Updated ${discordId} → ${rsi}`);

        } catch (err) {
            console.error(`[Nickname] Failed for ${discordId}:`, err.message);
        }
    }
};
