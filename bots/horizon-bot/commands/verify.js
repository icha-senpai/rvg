const { SlashCommandBuilder } = require('discord.js');
const { verifyUser } = require('../utils/api');

module.exports = {
    data: new SlashCommandBuilder()
        .setName('verify')
        .setDescription('Verify your RSI account')
        .addStringOption(option =>
            option.setName('rsi_handle')
                .setDescription('Your RSI handle')
                .setRequired(true)
        ),

    async execute(interaction) {
        const rsiHandle = interaction.options.getString('rsi_handle');

        await interaction.deferReply({ ephemeral: true });

        try {
            const res = await verifyUser(interaction.user.id, rsiHandle);

            if (res.success) {
                await interaction.editReply(`✅ Verified as **${rsiHandle}**!`);
            } else {
                await interaction.editReply(`❌ Verification failed: ${res.error || 'Unknown error'}`);
            }
        } catch (err) {
            await interaction.editReply(`❌ API Error: ${err.response?.data?.message || err.message}`);
        }
    }
};
