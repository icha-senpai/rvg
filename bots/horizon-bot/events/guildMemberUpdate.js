const { Events } = require('discord.js');
const nicknameService = require('../services/nicknameService');

module.exports = {
    name: Events.GuildMemberUpdate,
    async execute(oldMember, newMember) {
        if (oldMember.nickname !== newMember.nickname) {
            console.log(`[EVENT] Nickname changed → enforcing for ${newMember.id}`);
            await nicknameService.enforce(newMember.client, newMember.id);
        }
    }
};
