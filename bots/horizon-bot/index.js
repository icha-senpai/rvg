require('dotenv').config();


const express = require('express');
const app = express(); // MUST EXIST BEFORE app.use()

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
    if (LOGS_ENABLED) {
        console.log(...args);
    }
};

app.use(express.json());

if (LOGS_ENABLED) {
    app.use((req, res, next) => {
        const ip =
            req.headers['cf-connecting-ip'] ||
            req.headers['x-forwarded-for'] ||
            req.ip;

        log('[Webhook]', {
            method: req.method,
            url: req.originalUrl ?? req.url,
            ip,
            userAgent: req.headers['user-agent'],
            contentType: req.headers['content-type'],
            contentLength: req.headers['content-length'],
            hasBotSecret: Boolean(req.headers['x-bot-secret']),
        });
        next();
    });
}

// --------------------
// LOAD WEBHOOK ROUTES
// --------------------
const webhookRoutes = require('./services/webhook');        // nickname sync webhook
const webhookOpRoutes = require('./services/webhookOperations'); // operation published webhook
const webhookPromotionRoutes = require('./services/webhookPromotions');
const webhookSquadronRoutes = require('./services/webhookSquadrons');
const webhookOperationRuntimeRoutes = require('./services/webhookOperationRuntime');

log("Loaded webhookOpRoutes:", webhookOpRoutes);

// Mount all /bot routes AFTER app is created
app.use('/bot', webhookRoutes);
app.use('/bot', webhookOpRoutes);
app.use('/bot', webhookPromotionRoutes);
app.use('/bot', webhookSquadronRoutes);
app.use('/bot', webhookOperationRuntimeRoutes);

// --------------------
// DISCORD CLIENT
// --------------------
const { Client, GatewayIntentBits, Partials, Collection, REST, Routes, Events } = require('discord.js');
const fs = require('fs');
const path = require('path');

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.GuildMessageReactions,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.GuildMembers,
        GatewayIntentBits.GuildVoiceStates,
        GatewayIntentBits.DirectMessages,
        GatewayIntentBits.DirectMessageReactions,
    ],
    partials: [
        Partials.Channel,
        Partials.Message,
        Partials.Reaction,
    ],
});

// Make client accessible inside webhook handlers
app.set('client', client);

// --------------------
// LOAD COMMANDS
// --------------------
client.commands = new Collection();
const commands = [];

const commandsPath = path.join(__dirname, 'commands');
const commandFiles = fs.readdirSync(commandsPath);

for (const file of commandFiles) {
    const command = require(`./commands/${file}`);
    client.commands.set(command.data.name, command);
    commands.push(command.data.toJSON());
}

// Register slash commands
const rest = new REST({ version: '10' }).setToken(process.env.DISCORD_TOKEN);

(async () => {
    try {
        log('Updating slash commands...');
        await rest.put(
            Routes.applicationGuildCommands(
                process.env.DISCORD_CLIENT_ID,
                process.env.DISCORD_GUILD_ID
            ),
            { body: commands }
        );
        log('Slash commands updated.');
    } catch (error) {
        console.error(error);
    }
})();

// --------------------
// LOAD EVENTS
// --------------------
const eventsPath = path.join(__dirname, 'events');
const eventFiles = fs.readdirSync(eventsPath);

for (const file of eventFiles) {
    const event = require(`./events/${file}`);
    if (event.once) {
        client.once(event.name, (...args) => event.execute(...args));
    } else {
        client.on(event.name, (...args) => event.execute(...args));
    }
}

// --------------------
// LOG WATCHER
// --------------------
const logWatcher = require('./services/logWatcher');

// --------------------
// CRON SERVICE
// --------------------
const nicknameCron = require('./services/nicknameCron');

client.once(Events.ClientReady, async () => {
    await nicknameCron.runNow(client);
});
// --------------------
// BOT READY
// --------------------
client.once(Events.ClientReady, () => {
    log(`🚀 Logged in as ${client.user.tag}!`);
    logWatcher.start(client);
    nicknameCron.start(client);
});

// --------------------
// START DISCORD BOT
// --------------------
client.login(process.env.DISCORD_TOKEN);

// --------------------
// START EXPRESS SERVER
// --------------------
const PORT = process.env.PORT || 3001;
app.listen(PORT, () => {
    log(`🌐 Webhook server running at http://localhost:${PORT}`);
});
