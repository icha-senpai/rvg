require('dotenv').config();
const { Client, GatewayIntentBits, REST, Routes } = require('discord.js');
const axios = require('axios');

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMembers
    ]
});

// Slash command deploy
const commands = [
    {
        name: 'verify',
        description: 'Manually verify yourself with Horizon Interstellar.'
    }
];

const rest = new REST({ version: '10' }).setToken(process.env.DISCORD_TOKEN);

async function registerCommands() {
    await rest.put(
        Routes.applicationGuildCommands(process.env.DISCORD_CLIENT_ID, process.env.DISCORD_GUILD_ID),
        { body: commands }
    );
    console.log('Slash commands deployed.');
}

// Handle slash commands
client.on('interactionCreate', async (interaction) => {
    if (!interaction.isChatInputCommand()) return;

    if (interaction.commandName === 'verify') {
        await interaction.reply({ content: 'Verifying you…', ephemeral: true });

        // Send request to Laravel
        try {
            const res = await axios.post(
                `${process.env.API_BASE_URL}/api/v1/discord/verify`,
                {
                    discord_id: interaction.user.id
                },
                {
                    headers: { 'X-Bot-Secret': process.env.API_SECRET }
                }
            );

            const roleId = res.data.role_id;

            // Assign discord role
            const member = await interaction.guild.members.fetch(interaction.user.id);
            await member.roles.add(roleId);

            await interaction.editReply('Verification complete. Welcome to Horizon Interstellar.');
        } catch (err) {
            console.error(err);
            await interaction.editReply('Verification failed. Ping an officer.');
        }
    }
});

// Bot startup
client.once('ready', () => {
    console.log(`Bot online as ${client.user.tag}`);
});

registerCommands();
client.login(process.env.DISCORD_TOKEN);
