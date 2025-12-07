const { Events } = require('discord.js');
const nicknameService = require('../services/nicknameService');

module.exports = {
    name: Events.GuildMemberAdd,
    async execute(member) {
        console.log(`[EVENT] Member joined → ${member.id}`);
        await nicknameService.enforce(member.client, member.id);
    }
};
