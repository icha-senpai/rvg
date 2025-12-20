const express = require('express');
const nicknameService = require('./nicknameService');


const router = express.Router();

router.post('/sync-nickname', async (req, res) => {
    if (req.headers['x-bot-secret'] !== process.env.DISCORD_BOT_SECRET) {
        return res.status(403).json({ message: 'Forbidden' });
    }

    const { discord_id } = req.body;

    if (!discord_id) {
        return res.status(400).json({ message: 'discord_id is required' });
    }

    try {
        const client = req.app.get('client');
        await nicknameService.enforce(client, discord_id);

        res.json({ message: 'Nickname sync executed' });
    } catch (err) {
        console.error('Webhook error:', err.message);
        res.status(500).json({ message: 'Internal error' });
    }
});

router.post('/verified', async (req, res) => {
    if (req.headers['x-bot-secret'] !== process.env.DISCORD_BOT_SECRET) {
        return res.status(403).json({ message: 'Forbidden' });
    }

    const { discord_id } = req.body;

    if (!discord_id) {
        return res.status(400).json({ message: 'discord_id is required' });
    }

    const memberRoleId = process.env.DISCORD_MEMBER_ROLE_ID;
    if (!memberRoleId) {
        return res.status(500).json({ message: 'DISCORD_MEMBER_ROLE_ID missing in .env' });
    }

    try {
        const client = req.app.get('client');

        await nicknameService.enforce(client, discord_id);

        const guild = await client.guilds.fetch(process.env.DISCORD_GUILD_ID);
        const member = await guild.members.fetch(discord_id);

        if (!member.roles.cache.has(memberRoleId)) {
            await member.roles.add(memberRoleId, 'Website verification complete');
        }

        return res.json({ message: 'Verified actions executed' });
    } catch (err) {
        console.error('Webhook error:', err.message);
        return res.status(500).json({ message: 'Internal error' });
    }
});

module.exports = router;
