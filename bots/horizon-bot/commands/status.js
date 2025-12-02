const { SlashCommandBuilder } = require('discord.js');
const { checkVerificationStatus } = require('../utils/api');

module.exports = {
    data: new SlashCommandBuilder()
        .setName('status')
        .setDescription('Check your verification status'),

    async execute(interaction) {
        await interaction.deferReply({ ephemeral: true });

        try {
            const res = await checkVerificationStatus(interaction.user.id);

            if (res.user?.is_verified) {
                await interaction.editReply(
                    `✅ Verified as **${res.user.rsi_handle || 'Unknown'}**`
                );
            } else {
                await interaction.editReply(`❌ You are not verified.`);
            }
        } catch (err) {
            await interaction.editReply(`❌ Could not check status.`);
        }
    }
};
