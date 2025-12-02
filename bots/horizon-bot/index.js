require('dotenv').config();
const { Client, GatewayIntentBits, REST, Routes, EmbedBuilder } = require('discord.js');
const axios = require('axios');
const fs = require('fs');
const path = require('path');

// Initialize Discord client with required intents
const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMembers,
        GatewayIntentBits.MessageContent
    ]
});

// Log file setup
const LOG_FILE = path.join(process.cwd(), 'storage/logs/laravel.log');
let fileSize = fs.existsSync(LOG_FILE) ? fs.statSync(LOG_FILE).size : 0;

// Slash command setup
const commands = [
    {
        name: 'verify',
        description: 'Manually verify yourself with Horizon Interstellar.'
    }
];

// Deploy slash commands
const rest = new REST({ version: '10' }).setToken(process.env.DISCORD_TOKEN);

async function registerCommands() {
    try {
        await rest.put(
            Routes.applicationGuildCommands(process.env.DISCORD_CLIENT_ID, process.env.DISCORD_GUILD_ID),
            { body: commands }
        );
        console.log('✅ Slash commands deployed');
    } catch (error) {
        console.error('❌ Failed to deploy commands:', error);
    }
}

// Log monitoring functions
function watchLogs() {
    if (!fs.existsSync(LOG_FILE)) {
        console.error(`Log file not found at: ${LOG_FILE}`);
        return;
    }
    
    // Initial read
    readNewLogs();
    
    // Watch for changes
    fs.watch(LOG_FILE, (eventType) => {
        if (eventType === 'change') {
            readNewLogs();
        }
    });
}

function readNewLogs() {
    const stream = fs.createReadStream(LOG_FILE, { start: fileSize, encoding: 'utf8' });
    
    stream.on('data', (data) => {
        fileSize += Buffer.byteLength(data, 'utf8');
        
        data.split('\n').filter(Boolean).forEach(line => {
            try {
                const log = JSON.parse(line);
                if (log.type === 'auth' || log.type === 'rsi_verification') {
                    sendToDiscord(log);
                }
            } catch (e) {
                // Ignore non-JSON lines or parse errors
            }
        });
    });
}

function sendToDiscord(log) {
    const channel = client.channels.cache.get(process.env.DISCORD_LOGS_CHANNEL_ID);
    if (!channel) {
        console.error('Log channel not found!');
        return;
    }

    const embed = new EmbedBuilder()
        .setColor(getColor(log))
        .setTitle(getTitle(log))
        .setTimestamp()
        .addFields(getFields(log));

    channel.send({ embeds: [embed] }).catch(console.error);
}

function getColor(log) {
    const colors = {
        auth: '#3498db',
        rsi_verification: '#2ecc71'
    };
    return colors[log.type] || '#9b59b6';
}

function getTitle(log) {
    const titles = {
        auth: {
            login_success: '✅ Login Successful',
            login_failed: '❌ Login Failed'
        },
        rsi_verification: {
            verification_success: '✅ RSI Verification Success',
            verification_failed: '❌ RSI Verification Failed'
        }
    };
    return titles[log.type]?.[log.action] || log.action || 'New Log Entry';
}

function getFields(log) {
    const fields = [];
    
    // Common fields
    if (log.user_id) fields.push({ name: 'User ID', value: log.user_id.toString(), inline: true });
    if (log.discord_id) fields.push({ name: 'Discord ID', value: log.discord_id, inline: true });
    if (log.rsi_handle) fields.push({ name: 'RSI Handle', value: log.rsi_handle, inline: true });
    
    // Error handling
    if (log.error) {
        fields.push({
            name: 'Error',
            value: `\`\`\`${log.error}\`\`\``
        });
    }
    
    // IP and User Agent
    if (log.ip) fields.push({ name: 'IP', value: log.ip, inline: true });
    if (log.user_agent) {
        fields.push({
            name: 'User Agent',
            value: `\`${log.user_agent.substring(0, 100)}${log.user_agent.length > 100 ? '...' : ''}\``,
            inline: false
        });
    }
    
    // Timestamp
    fields.push({
        name: 'Timestamp',
        value: new Date(log.timestamp || Date.now()).toISOString(),
        inline: false
    });
    
    return fields;
}

// Bot events
client.once('ready', () => {
    console.log(`✅ Logged in as ${client.user.tag}`);
    console.log(`👀 Watching log file: ${LOG_FILE}`);
    watchLogs();
    registerCommands();
});

// Slash command handler
client.on('interactionCreate', async (interaction) => {
    if (!interaction.isChatInputCommand()) return;

    if (interaction.commandName === 'verify') {
        await interaction.deferReply({ ephemeral: true });

        try {
            const res = await axios.post(
                `${process.env.API_BASE_URL}/api/v1/discord/verify`,
                { discord_id: interaction.user.id },
                { headers: { 'X-Bot-Secret': process.env.API_SECRET } }
            );

            const roleId = res.data.role_id;
            if (roleId) {
                const member = await interaction.guild.members.fetch(interaction.user.id);
                await member.roles.add(roleId);
                await interaction.editReply({ content: '✅ You have been verified and your roles have been updated!' });
            } else {
                await interaction.editReply({ content: '✅ Verification complete!' });
            }
        } catch (error) {
            console.error('Verification error:', error);
            const errorMessage = error.response?.data?.message || 'Failed to verify. Please try again later.';
            await interaction.editReply({ content: `❌ ${errorMessage}` });
        }
    }
});

// Error handling
process.on('unhandledRejection', error => {
    console.error('Unhandled promise rejection:', error);
    const logChannel = client.channels.cache.get(process.env.DISCORD_LOGS_CHANNEL_ID);
    if (logChannel) {
        logChannel.send({
            embeds: [new EmbedBuilder()
                .setColor('#e74c3c')
                .setTitle('❌ Bot Error')
                .setDescription('```' + error.stack.substring(0, 1800) + '```')
                .setTimestamp()
            ]
        }).catch(console.error);
    }
});

// Start the bot
client.login(process.env.DISCORD_TOKEN)
    .catch(error => {
        console.error('Failed to log in:', error);
        process.exit(1);
    });