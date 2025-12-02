const axios = require('axios');
const https = require('https');

const axiosInstance = axios.create({
    httpsAgent: new https.Agent({
        rejectUnauthorized: false
    })
});

module.exports = {
    async verifyUser(discordId, rsiHandle) {
        const url = `${process.env.API_BASE_URL}/bot/verify`;

        try {
            const response = await axiosInstance.post(url, {
                discord_id: discordId,
                rsi_handle: rsiHandle
            }, {
                headers: {
                    'X-Bot-Secret': process.env.API_SECRET,
                    'Accept': 'application/json',
                }
            });

            return response.data;
        } catch (error) {
            console.error('API verify error:', error.response?.data || error.message);
            throw error;
        }
    },

    async checkVerificationStatus(discordId) {
        const url = `${process.env.API_BASE_URL}/bot/users/${discordId}`;

        try {
            const response = await axiosInstance.get(url, {
                headers: {
                    'X-Bot-Secret': process.env.API_SECRET,
                    'Accept': 'application/json',
                }
            });

            return response.data;
        } catch (error) {
            console.error('API status error:', error.response?.data || error.message);
            throw error;
        }
    }
};
