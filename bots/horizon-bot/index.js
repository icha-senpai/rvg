require('dotenv').config();


const express = require('express');
const app = express(); // MUST EXIST BEFORE app.use()
app.use(express.json());
app.use((req, res, next) => {
    console.log("🔥 GLOBAL REQUEST HEADERS:", req.method, req.url, req.headers);
    next();
});

// --------------------
// LOAD WEBHOOK ROUTES
// --------------------
const webhookRoutes = require('./services/webhook');        // nickname sync webhook
const webhookOpRoutes = require('./services/webhookOperations'); // operation published webhook

console.log("Loaded webhookOpRoutes:", webhookOpRoutes);

// Mount all /bot routes AFTER app is created
app.use('/bot', webhookRoutes);
app.use('/bot', webhookOpRoutes);

// --------------------
// DISCORD CLIENT
// --------------------
const { Client, GatewayIntentBits, Collection, REST, Routes, Events } = require('discord.js');
const fs = require('fs');
const path = require('path');

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.GuildMembers,
    ]
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
        console.log('Updating slash commands...');
        await rest.put(
            Routes.applicationGuildCommands(
                process.env.DISCORD_CLIENT_ID,
                process.env.DISCORD_GUILD_ID
            ),
            { body: commands }
        );
        console.log('Slash commands updated.');
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
    console.log(`🚀 Logged in as ${client.user.tag}!`);
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
    console.log(`🌐 Webhook server running at http://localhost:${PORT}`);
});
