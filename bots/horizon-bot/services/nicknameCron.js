const nicknameService = require('./nicknameService');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
    if (LOGS_ENABLED) {
        console.log(...args);
    }
};

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

async function runNicknameSync(client) {
    log('[Cron] Running nickname sync (manual or scheduled)…');

    try {
        const guild = await client.guilds.fetch(process.env.DISCORD_GUILD_ID);
        const members = await guild.members.fetch();

        for (const [id] of members) {
            try {
                await nicknameService.enforce(client, id);
            } catch (err) {
                console.error(`[Cron] Nickname failed for ${id}:`, err.message);
            }

            await sleep(250); // throttle
        }

        log('[Cron] Nickname sync complete');
    } catch (err) {
        console.error('[Cron] Sync failed:', err.message);
    }
}

module.exports = {
    start(client) {
        setInterval(() => runNicknameSync(client), 30 * 60 * 1000);
    },
    runNow: runNicknameSync, // manual trigger
};