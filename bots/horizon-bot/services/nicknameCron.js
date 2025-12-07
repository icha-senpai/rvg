const nicknameService = require('./nicknameService');

module.exports = {
    async start(client) {
        setInterval(async () => {
            console.log('[Cron] Running periodic nickname sync...');
            try {
                const guild = await client.guilds.fetch(process.env.DISCORD_GUILD_ID);
                const members = await guild.members.fetch();

                for (const [id] of members) {
                    await nicknameService.enforce(client, id);
                }
            } catch (err) {
                console.error('[Cron] Sync failed:', err.message);
            }
        }, 30 * 60 * 1000); // 30 minutes
    }
};
