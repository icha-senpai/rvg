const { Client, Intents } = require('discord.js');
const fs = require('fs');
const path = require('path');
require('dotenv').config();

const client = new Client({ intents: [Intents.FLAGS.GUILDS] });
const LOG_FILE = path.join(process.cwd(), 'storage/logs/laravel.log');
let fileSize = fs.existsSync(LOG_FILE) ? fs.statSync(LOG_FILE).size : 0;

client.once('ready', () => {
    console.log('✅ Log monitor ready');
    watchLogs();
});

function watchLogs() {
    // Initial read in case there are existing logs
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

    const embed = {
        color: getColor(log),
        title: getTitle(log),
        fields: getFields(log),
        timestamp: new Date()
    };

    channel.send({ embeds: [embed] }).catch(console.error);}

function getColor(log) {
    if (log.action.includes('success')) return 0x44ff44;
    if (log.action.includes('failed') || log.action.includes('error')) return 0xff4444;
    return 0x3498db;
}

function getTitle(log) {
    const types = {
        'auth': '🔐 Auth',
        'rsi_verification': '✅ RSI Verification'
    };
    return `${types[log.type] || '📝 Log'} - ${log.action.split('_').map(s => s[0].toUpperCase() + s.slice(1)).join(' ')}`;
}

function getFields(log) {
    const fields = [];
    const { type, action, timestamp, ...rest } = log;
    
    // Add user mention if available
    if (rest.discord_id) {
        fields.push({
            name: 'User',
            value: `<@${rest.discord_id}>`,
            inline: true
        });
        delete rest.discord_id;
    }
    
    // Add other fields
    for (const [key, value] of Object.entries(rest)) {
        if (value && typeof value === 'object') continue;
        
        fields.push({
            name: key.split('_')
                    .map(s => s[0].toUpperCase() + s.slice(1))
                    .join(' '),
            value: String(value || 'N/A').substring(0, 1024),
            inline: true
        });
    }
    
    return fields;
}

// Handle errors
process.on('unhandledRejection', error => {
    console.error('Unhandled promise rejection:', error);
    const logChannel = client.channels.cache.get(process.env.DISCORD_LOGS_CHANNEL_ID);
    if (logChannel) {
        logChannel.send(`❌ **Bot Error**: \`\`\`${error.message}\`\`\``);
    }
});

client.login(process.env.DISCORD_BOT_TOKEN);
