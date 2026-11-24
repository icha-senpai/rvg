const publicSiteUrl = 'https://horizoninterstellar.com';

module.exports = {
    verificationUrl: `${publicSiteUrl}/verify`,
    operationUrl: (id) => `${publicSiteUrl}/operations/${encodeURIComponent(id)}`,
};
