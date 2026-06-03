const { Events } = require('discord.js');
const promotionService = require('../services/promotionService');

module.exports = {
    name: Events.MessageReactionAdd,
    async execute(reaction, user) {
        try {
            if (reaction.partial) {
                await reaction.fetch();
            }

            if (reaction.message?.partial) {
                await reaction.message.fetch();
            }

            await promotionService.handleAcceptanceReaction(reaction.client, reaction, user);
        } catch (error) {
            console.error('[PromotionReaction] Error handling reaction:', error);

            try {
                if (reaction.message?.channel?.type === 1) {
                    await reaction.message.channel.send('That promotion could not be accepted. It may already be closed or no longer valid.');
                }
            } catch {}
        }
    },
};
