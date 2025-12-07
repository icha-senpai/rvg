console.log("🔥 webhookOperations.js LOADED");

const express = require('express');
const router = express.Router();
const operationService = require('./operationService');

router.post('/op-published', async (req, res) => {
    console.log("🔥 BODY TYPE:", typeof req.body);
    console.log("🔥 RAW BODY VALUE:", req.body);

    console.log("=== WEBHOOK DEBUG ===");
    console.log("📥 Incoming payload:", req.body);


    const received = req.headers['x-bot-secret'];
    const expected = process.env.DISCORD_BOT_SECRET;

    console.log("Headers:", req.headers);
    console.log("Received secret:", received);
    console.log("Expected secret:", expected);

    if (received !== expected) {
        console.log("SECRET MISMATCH");
        return res.status(403).json({ message: 'Forbidden' });
    }

    console.log("SECRET MATCHED ✔");

    const op = req.body;

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

module.exports = router;
