const fs = require('fs');
const path = require('path');

const LOG_FILE = process.env.LARAVEL_LOG_PATH;
const REDACTED_VALUE = '[REDACTED]';
const OMITTED_VALUE = '[OMITTED]';
const SENSITIVE_KEYS = new Set([
    'token',
    'access_token',
    'refresh_token',
    'verification_code',
    'password',
    'secret',
    'authorization',
    'cookie',
    'set_cookie',
]);

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
    if (LOGS_ENABLED) {
        console.log(...args);
    }
};

module.exports = {
    start(client) {
        log('🛰️ Log watcher initializing...');

        if (!LOG_FILE) {
            console.error('❌ LARAVEL_LOG_PATH is not configured.');
            return;
        }

        // If the file doesn't exist yet — create it so fs.watch won't explode
        ensureLogFileExists(LOG_FILE);

        watchLogFile(client);
    }
};

// Ensures the log file exists, or creates an empty file
function ensureLogFileExists(filepath) {
    const dir = path.dirname(filepath);

    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    if (!fs.existsSync(filepath)) {
        fs.writeFileSync(filepath, ""); // create empty file
        log("📄 Created missing laravel.log");
    }
}

function watchLogFile(client) {
    let fileSize = fs.statSync(LOG_FILE).size;

    log(`📡 Watching log file: ${LOG_FILE}`);

    // Try-catch so Windows doesn't hard-crash
    try {
        fs.watch(LOG_FILE, (eventType) => {
            if (eventType === 'change') {
                readNewLogs(client, fileSize);
                fileSize = fs.statSync(LOG_FILE).size;
            }
        });
    } catch (e) {
        console.error("❌ Failed to watch file, retrying in 2s:", e.message);

        setTimeout(() => watchLogFile(client), 2000);
    }

    // Initial read
    readNewLogs(client, fileSize);
}

function readNewLogs(client, prevSize) {
    const stream = fs.createReadStream(LOG_FILE, {
        start: prevSize,
        encoding: 'utf8'
    });

    stream.on('data', (data) => {
        data.split('\n').filter(Boolean).forEach(line => {
            const parsed = parseLogLine(line);

            if (parsed && (parsed.type === 'auth' || parsed.type === 'rsi_verification')) {
                void sendToDiscord(client, parsed);
            }
        });
    });
}

function parseLogLine(line) {
    const trimmed = String(line || '').trim();

    if (!trimmed) {
        return null;
    }

    const jsonStart = trimmed.indexOf('{');
    const jsonEnd = trimmed.lastIndexOf('}');
    const candidate = jsonStart !== -1 && jsonEnd > jsonStart
        ? trimmed.slice(jsonStart, jsonEnd + 1)
        : trimmed;

    try {
        return sanitizeLogEntry(JSON.parse(candidate));
    } catch {
        return null;
    }
}

async function sendToDiscord(client, logEntry) {
    const channelId = process.env.DISCORD_LOGS_CHANNEL_ID;

    if (!channelId) {
        console.error('❌ DISCORD_LOGS_CHANNEL_ID is not configured.');
        return;
    }

    const cachedChannel = client.channels.cache.get(channelId);
    const channel = cachedChannel || await client.channels.fetch(channelId).catch(() => null);

    if (!channel || typeof channel.send !== 'function') {
        console.error('❌ Log channel not found or is not message-capable.');
        return;
    }

    const embed = {
        color: getColor(logEntry),
        title: getTitle(logEntry),
        fields: getFields(logEntry),
        timestamp: new Date(logEntry.timestamp || Date.now())
    };

    channel.send({ embeds: [embed] }).catch(console.error);
}

function sanitizeLogEntry(entry) {
    if (!entry || typeof entry !== 'object' || Array.isArray(entry)) {
        return null;
    }

    const sanitized = {};

    for (const [key, value] of Object.entries(entry)) {
        sanitized[key] = sanitizeValue(key, value);
    }

    return sanitized;
}

function sanitizeValue(key, value) {
    const normalizedKey = String(key || '').toLowerCase();

    if (SENSITIVE_KEYS.has(normalizedKey)) {
        return REDACTED_VALUE;
    }

    if (Array.isArray(value)) {
        return value.slice(0, 10).map((item) => sanitizeValue(key, item));
    }

    if (value && typeof value === 'object') {
        const nested = {};

        for (const [nestedKey, nestedValue] of Object.entries(value)) {
            nested[nestedKey] = sanitizeValue(nestedKey, nestedValue);
        }

        return nested;
    }

    if (typeof value !== 'string') {
        return value;
    }

    let sanitized = value.replace(/Bearer\s+[A-Za-z0-9\-._|]+/gi, 'Bearer [REDACTED]');
    sanitized = sanitized.replace(/(access_token|refresh_token|verification_code|password|secret|authorization|cookie)=([^&\s]+)/gi, '$1=[REDACTED]');

    return sanitized.length > 500 ? `${sanitized.slice(0, 497)}...` : sanitized;
}

function getColor(log) {
    const action = String(log.action || '');

    if (action.includes('success')) return 0x44ff44;
    if (action.includes('failed') || action.includes('error')) return 0xff4444;
    return 0x3498db;
}

function getTitle(log) {
    const types = {
        'auth': '🔐 Auth',
        'rsi_verification': '✅ RSI Verification'
    };

    return `${types[log.type] || '📝 Log'} - ${
        log.action.split('_').map(s => s[0].toUpperCase() + s.slice(1)).join(' ')
    }`;
}

function getFields(log) {
    const fields = [];
    const { type, action, timestamp, ...rest } = log;

    if (rest.discord_id) {
        fields.push({
            name: 'User',
            value: `<@${rest.discord_id}>`,
            inline: true
        });
        delete rest.discord_id;
    }

    for (const [key, value] of Object.entries(rest)) {
        if (value && typeof value === 'object') {
            const compact = JSON.stringify(value);

            if (!compact) {
                continue;
            }

            fields.push({
                name: key.split('_')
                    .map(s => s[0].toUpperCase() + s.slice(1))
                    .join(' '),
                value: compact.substring(0, 1024),
                inline: false
            });
            continue;
        }

        if (value === undefined || value === null || value === '') {
            continue;
        }

        fields.push({
            name: key.split('_')
                .map(s => s[0].toUpperCase() + s.slice(1))
                .join(' '),
            value: String(value || OMITTED_VALUE).substring(0, 1024),
            inline: true
        });
    }

    return fields.length ? fields.slice(0, 25) : [{
        name: 'Details',
        value: OMITTED_VALUE,
        inline: false
    }];
}
