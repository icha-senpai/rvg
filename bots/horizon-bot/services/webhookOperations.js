const express = require('express');
const router = express.Router();
const operationService = require('./operationService');

const LOGS_ENABLED = process.env.BOT_LOGS === 'true';
const log = (...args) => {
    if (LOGS_ENABLED) {
        console.log(...args);
    }
};

log("🔥 webhookOperations.js LOADED");

router.post('/op-published', async (req, res) => {
    const received = req.headers['x-bot-secret'];
    const expected = process.env.DISCORD_BOT_SECRET;

    if (received !== expected) {
        log('[OpWebhook] Forbidden', {
            path: req.originalUrl ?? req.url,
            receivedPresent: Boolean(received),
            receivedLength: typeof received === 'string' ? received.length : null,
        });
        return res.status(403).json({ message: 'Forbidden' });
    }

    const op = req.body;

    log('[OpWebhook] Received op-published', {
        path: req.originalUrl ?? req.url,
        bodyType: typeof op,
        bodyKeys: op && typeof op === 'object' ? Object.keys(op) : null,
    });

    if (!op || !op.id || !op.title) {
        return res.status(400).json({ message: 'Invalid operation payload' });
    }

    try {
        const client = req.app.get('client');
        await operationService.announceOperation(client, op);

        return res.json({ message: 'Operation announcement sent.' });
    } catch (error) {
        console.error('[OpWebhook] Error:', error);
        return res.status(500).json({ message: 'Internal bot error.' });
    }
});

router.post('/op-updated', async (req, res) => {
    const received = req.headers['x-bot-secret'];
    const expected = process.env.DISCORD_BOT_SECRET;

    if (received !== expected) {
        log('[OpWebhook] Forbidden', {
            path: req.originalUrl ?? req.url,
            receivedPresent: Boolean(received),
            receivedLength: typeof received === 'string' ? received.length : null,
        });
        return res.status(403).json({ message: 'Forbidden' });
    }

    const op = req.body;

    log('[OpWebhook] Received op-updated', {
        path: req.originalUrl ?? req.url,
        bodyType: typeof op,
        bodyKeys: op && typeof op === 'object' ? Object.keys(op) : null,
    });

    if (!op || !op.id || !op.title) {
        return res.status(400).json({ message: 'Invalid operation payload' });
    }

    try {
        const client = req.app.get('client');
        await operationService.announceOperationUpdated(client, op);

        return res.json({ message: 'Operation update announcement sent.' });
    } catch (error) {
        console.error('[OpWebhook] Error:', error);
        return res.status(500).json({ message: 'Internal bot error.' });
    }
});

module.exports = router;
