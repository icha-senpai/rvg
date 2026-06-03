const express = require('express');
const promotionService = require('./promotionService');

const router = express.Router();

function isAuthorized(req) {
    return req.headers['x-bot-secret'] === process.env.DISCORD_BOT_SECRET;
}

router.post('/promotion-offers/create', async (req, res) => {
    if (!isAuthorized(req)) {
        return res.status(403).json({ message: 'Forbidden' });
    }

    const payload = req.body || {};

    if (!payload.offer_id || !payload.member_discord_id || !payload.quarter_channel_id) {
        return res.status(400).json({ message: 'Invalid promotion offer payload' });
    }

    try {
        const client = req.app.get('client');
        const result = await promotionService.sendPromotionOffer(client, payload);

        return res.json({
            message: 'Promotion offer messages sent.',
            dm_message_id: result.dmMessageId,
            quarter_message_id: result.quarterMessageId,
        });
    } catch (error) {
        console.error('[PromotionWebhook] Create error:', error);
        return res.status(500).json({ message: 'Internal bot error.' });
    }
});

router.post('/promotion-offers/cancel', async (req, res) => {
    if (!isAuthorized(req)) {
        return res.status(403).json({ message: 'Forbidden' });
    }

    try {
        const client = req.app.get('client');
        await promotionService.updatePromotionOfferStatus(client, req.body || {}, 'cancelled');

        return res.json({ message: 'Promotion offer cancellation updates sent.' });
    } catch (error) {
        console.error('[PromotionWebhook] Cancel error:', error);
        return res.status(500).json({ message: 'Internal bot error.' });
    }
});

router.post('/promotion-offers/expire', async (req, res) => {
    if (!isAuthorized(req)) {
        return res.status(403).json({ message: 'Forbidden' });
    }

    try {
        const client = req.app.get('client');
        await promotionService.updatePromotionOfferStatus(client, req.body || {}, 'expired');

        return res.json({ message: 'Promotion offer expiry updates sent.' });
    } catch (error) {
        console.error('[PromotionWebhook] Expire error:', error);
        return res.status(500).json({ message: 'Internal bot error.' });
    }
});

module.exports = router;
