const { Events } = require('discord.js');
const nicknameService = require('../services/nicknameService');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
  if (LOGS_ENABLED) {
    console.log(...args);
  }
};

module.exports = {
  name: Events.GuildMemberAdd,
  async execute(member) {
    log(`[EVENT] Member joined → ${member.id}`);

    try {
      await nicknameService.enforce(member.client, member.id);
    } catch (err) {
      console.error(`[JOIN] Nickname enforcement failed`, err);
    }
  },
};
