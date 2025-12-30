const { Events } = require('discord.js');
const nicknameService = require('../services/nicknameService');
const welcomeService = require('../services/welcomeService');

const GUEST_ROLE_ID = '1454412918828306483';

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
  if (LOGS_ENABLED) {
    console.log(...args);
  }
};

module.exports = {
  name: Events.GuildMemberUpdate,
  async execute(oldMember, newMember) {
    // ----------------------------
    // Nickname enforcement
    // ----------------------------
    if (oldMember.nickname !== newMember.nickname) {
      log(`[EVENT] Nickname changed → enforcing for ${newMember.id}`);

      try {
        await nicknameService.enforce(newMember.client, newMember.id);
      } catch (err) {
        console.error(
          `[UPDATE] Nickname enforcement failed for ${newMember.id}`,
          err
        );
      }
    }

    // ----------------------------
    // Guest role → Arrival Platform
    // ----------------------------
    const hadGuest = oldMember.roles.cache.has(GUEST_ROLE_ID);
    const hasGuest = newMember.roles.cache.has(GUEST_ROLE_ID);

    if (!hadGuest && hasGuest) {
      log(`[ARRIVAL] Guest role assigned → ${newMember.id}`);

      try {
        await welcomeService.sendWelcome(newMember);
      } catch (err) {
        console.error(
          `[ARRIVAL] Welcome message failed for ${newMember.id}`,
          err
        );
      }
    }
  },
};
