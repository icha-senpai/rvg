const { Events } = require('discord.js');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
    if (LOGS_ENABLED) {
        console.log(...args);
    }
};

module.exports = {
    name: Events.ClientReady,
    once: true,
    execute(client) {
        log(`🚀 Logged in as ${client.user.tag}!`);
    },
};
