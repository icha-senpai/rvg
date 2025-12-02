require('dotenv').config();
const { Client, GatewayIntentBits, REST, Routes, Events } = require('discord.js');
const axios = require('axios');
const fs = require('fs');
const path = require('path');

// Initialize Discord client with required intents
const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.GuildMembers,
    ]
});

// Slash Commands
const commands = [
    {
        name: 'verify',
        description: 'Verify your RSI account',
        options: [
            {
                name: 'rsi_handle',
                description: 'Your RSI handle',
                type: 3, // STRING
                required: true
            }
        ]
    },
    {
        name: 'status',
        description: 'Check your verification status'
    }
];

// Register slash commands
const rest = new REST({ version: '10' }).setToken(process.env.DISCORD_TOKEN);

async function registerCommands() {
    try {
        console.log('Started refreshing application (/) commands.');

        await rest.put(
            Routes.applicationGuildCommands(process.env.DISCORD_CLIENT_ID, process.env.DISCORD_GUILD_ID),
            { body: commands }
        );

        console.log('Successfully reloaded application (/) commands.');
    } catch (error) {
        console.error('Error refreshing commands:', error);
    }
}

// Handle slash commands
client.on(Events.InteractionCreate, async interaction => {
    if (!interaction.isChatInputCommand()) return;

    if (interaction.commandName === 'verify') {
        await handleVerifyCommand(interaction);
    } else if (interaction.commandName === 'status') {
        await handleStatusCommand(interaction);
    }
});

async function handleVerifyCommand(interaction) {
    const rsiHandle = interaction.options.getString('rsi_handle');
    
    try {
        await interaction.deferReply({ ephemeral: true });

        // Call your Laravel API to verify the user
        const response = await verifyUser(interaction.user.id, rsiHandle);
        
        if (response.success) {
            await interaction.editReply({
                content: `✅ Successfully verified as ${rsiHandle}!`,
                ephemeral: true
            });
        } else {
            await interaction.editReply({
                content: '❌ Verification failed. Please try again later.',
                ephemeral: true
            });
        }
    } catch (error) {
        console.error('Verification error:', error);
        const errorMessage = error.response?.data?.error || 'An error occurred during verification.';
        
        await interaction.editReply({
            content: `❌ Error: ${errorMessage}`,
            ephemeral: true
        });
    }
}

async function handleStatusCommand(interaction) {
    try {
        await interaction.deferReply({ ephemeral: true });
        
        const response = await checkVerificationStatus(interaction.user.id);
        
        if (response.user?.is_verified) {
            await interaction.editReply({
                content: `✅ You are verified as ${response.user.rsi_handle || 'an RSI user'}.`,
                ephemeral: true
            });
        } else {
            await interaction.editReply({
                content: '❌ You are not verified. Use `/verify` to get started.',
                ephemeral: true
            });
        }
    } catch (error) {
        console.error('Status check error:', error);
        await interaction.editReply({
            content: '❌ Failed to check verification status.',
            ephemeral: true
        });
    }
}

// API Helper Functions
async function verifyUser(discordId, rsiHandle) {
    try {
        const response = await axios.post(
            `${process.env.API_BASE_URL}/api/bot/verify`,
            { 
                discord_id: discordId,
                rsi_handle: rsiHandle
            },
            { 
                headers: { 
                    'X-Bot-Secret': process.env.API_SECRET,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                } 
            }
        );
        return response.data;
    } catch (error) {
        console.error('API Error:', error.response?.data || error.message);
        throw error;
    }
}

async function checkVerificationStatus(discordId) {
    try {
        const response = await axios.get(
            `${process.env.API_BASE_URL}/api/bot/users/${discordId}`,
            { 
                headers: { 
                    'X-Bot-Secret': process.env.API_SECRET,
                    'Accept': 'application/json'
                } 
            }
        );
        return response.data;
    } catch (error) {
        console.error('Status check API error:', error.response?.data || error.message);
        throw error;
    }
}

// Bot startup
client.once('ready', () => {
    console.log(`Logged in as ${client.user.tag}!`);
    registerCommands().catch(console.error);
});

// Error handling
client.on('error', error => {
    console.error('Discord client error:', error);
});

process.on('unhandledRejection', error => {
    console.error('Unhandled promise rejection:', error);
});

// Login to Discord
client.login(process.env.DISCORD_TOKEN).catch(error => {
    console.error('Failed to log in to Discord:', error);
    process.exit(1);
});