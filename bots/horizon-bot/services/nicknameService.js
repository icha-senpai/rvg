const { checkVerificationStatus } = require('../utils/api');

module.exports = {
    async enforce(client, discordId) {
        try {
            const status = await checkVerificationStatus(discordId);

            // 👇 204 or unknown user → clean no-op
            if (!status) {
                return;
            }

            const isVerified = !!(status.user?.is_verified || status.user?.rsi_verified_at);

            if (!isVerified || !status.user?.rsi_handle) {
                return;
            }

            const rsi = status.user.rsi_handle;

            const guild = await client.guilds.fetch(process.env.DISCORD_GUILD_ID);
            const member = await guild.members.fetch(discordId);

            const current = member.nickname || member.user.username;

            if (current === rsi) {
                return;
            }

            await member.setNickname(rsi, 'RSI nickname sync');
            console.log(`[Nickname] Updated ${discordId} → ${rsi}`);

        } catch (err) {
            // Only log REAL failures
            console.error(
                `[Nickname] Failed for ${discordId}:`,
                err.response?.status || err.message
            );
        }
    }
};
