const axios = require('axios');
const https = require('https');

const axiosInstance = axios.create({
    httpsAgent: new https.Agent({
        rejectUnauthorized: false,
    }),
    validateStatus: status => status >= 200 && status < 500
});

module.exports = {
    async verifyUser(discordId, rsiHandle) {
        const url = `${process.env.API_BASE_URL}/bot/verify`;

        const response = await axiosInstance.post(
            url,
            { discord_id: discordId, rsi_handle: rsiHandle },
            {
                headers: {
                    'X-Bot-Secret': process.env.API_SECRET,
                    'Accept': 'application/json',
                },
            }
        );

        if (response.status !== 200) {
            console.error('API verify error:', response.data);
            throw new Error(`Verify failed (${response.status})`);
        }

        return response.data;
    },

    async checkVerificationStatus(discordId) {
        const url = `${process.env.API_BASE_URL}/bot/users/${discordId}`;

        const response = await axiosInstance.get(url, {
            headers: {
                'X-Bot-Secret': process.env.API_SECRET,
                'Accept': 'application/json',
            },
        });

        // 👇 NORMAL CASE: user not in DB
        if (response.status === 204) {
            return null;
        }

        // 👇 REAL ERROR
        if (response.status !== 200) {
            console.error('API status error:', response.data);
            throw new Error(`Status check failed (${response.status})`);
        }

        return response.data;
    }
};
