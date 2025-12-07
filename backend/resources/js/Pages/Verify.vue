<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

// --- URL token from Discord OAuth callback ---
const params = new URLSearchParams(window.location.search);
const tokenFromUrl = params.get('token');

if (tokenFromUrl) {
    localStorage.setItem('access_token', tokenFromUrl);
    window.history.replaceState({}, '', '/verify');
}

let accessToken = localStorage.getItem('access_token') || null;

// Verification state
const discordVerified = ref(false);
const rsiVerified = ref(false);

// RSI fields
const rsiHandle = ref('');
const verificationCode = ref('');
const error = ref({ message: null, details: {} });
const loadingCode = ref(false);
const verifying = ref(false);

// Auth header helper
const authHeaders = () => ({
    Authorization: `Bearer ${accessToken}`,
});

// --- On mount: validate Discord + determine RSI verification state ---
onMounted(async () => {
    if (!accessToken) {
        discordVerified.value = false;
        return;
    }

    try {
        const res = await axios.get('/api/v1/me', { headers: authHeaders() });

        discordVerified.value = true;
        rsiVerified.value = !!res.data.data.rsi_verified;

        // If RSI is already verified, skip step completely
        if (rsiVerified.value) {
            window.location.href = '/';
            return;
        }

    } catch (e) {
        localStorage.removeItem('access_token');
        accessToken = null;
        discordVerified.value = false;

        error.value = {
            message: 'Your Discord session expired or became invalid. Please verify Discord again.',
            details: {}
        };
    }
});

// --- Utility to clear errors ---
const clearError = () => {
    error.value = { message: null, details: {} };
};

// --- Generate RSI verification code ---
const getCode = async () => {
    clearError();
    accessToken = localStorage.getItem('access_token') || null;

    if (!accessToken) {
        discordVerified.value = false;
        error.value = { message: 'Missing access token. Please verify Discord again.', details: {} };
        return;
    }

    loadingCode.value = true;

    try {
        const res = await axios.post('/api/v1/generate-code', {}, { headers: authHeaders() });
        verificationCode.value = res.data.data.verification_code;
    } catch (e) {
        error.value = {
            message: e.response?.data?.message || "Couldn't generate verification code.",
            details: e.response?.data?.data || {}
        };
    } finally {
        loadingCode.value = false;
    }
};

// --- Submit RSI verification ---
const verifyRsi = async () => {
    clearError();
    accessToken = localStorage.getItem('access_token') || null;

    if (!accessToken) {
        discordVerified.value = false;
        error.value = { message: 'Missing access token. Please verify Discord again.', details: {} };
        return;
    }

    if (!rsiHandle.value) {
        error.value = { message: 'Please enter your RSI handle.', details: { field: 'rsi_handle' } };
        return;
    }

    verifying.value = true;

    try {
        await axios.post('/api/v1/verify-rsi', { rsi_handle: rsiHandle.value }, { headers: authHeaders() });

        // Success = send them home
        window.location.href = '/';
    } catch (e) {
        error.value = {
            message: e.response?.data?.message || 'Verification failed',
            details: e.response?.data?.data || {}
        };
    } finally {
        verifying.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-black text-white px-4">
        <div class="w-full max-w-xl p-6 rounded-2xl bg-gray-900 shadow-xl border border-white/10">

            <h1 class="text-3xl font-bold mb-2">Horizon Interstellar Verification</h1>
            <p class="text-sm text-gray-400 mb-6">
                Step 1: Link Discord · Step 2: Prove RSI org membership.
            </p>

            <!-- Error -->
            <div
                v-if="error.message"
                class="mb-4 rounded-lg border border-red-500/60 bg-red-500/10 px-4 py-3 text-sm text-red-200"
            >
                <div class="font-medium">{{ error.message }}</div>

                <div
                    v-if="Object.keys(error.details).length"
                    class="mt-2 pt-2 border-t border-red-500/20 text-xs opacity-80"
                >
                    <div v-for="(val, key) in error.details" :key="key">
                        {{ key }}: {{ val }}
                    </div>
                </div>
            </div>

            <!-- STEP 1: Discord -->
            <div v-if="!discordVerified">
                <h2 class="text-xl font-semibold mb-3">Step 1 · Verify with Discord</h2>
                <p class="text-sm text-gray-300 mb-4">
                    Click below to log in with Discord. You’ll return here afterward.
                </p>

                <a
                    href="/auth/discord"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 transition text-sm font-medium"
                >
                    Verify with Discord
                </a>
            </div>

            <!-- STEP 2: RSI — only if not yet verified -->
            <div v-else-if="!rsiVerified">
                <h2 class="text-xl font-semibold mb-3">Step 2 · RSI Verification</h2>
                <p class="text-sm text-gray-300 mb-4">
                    1) Generate your code and paste it into your RSI bio.<br>
                    2) Enter your RSI handle and click "Verify RSI".
                </p>

                <!-- Generate code -->
                <button
                    @click="getCode"
                    :disabled="loadingCode"
                    class="mb-3 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                >
                    <span v-if="!loadingCode">Generate Code</span>
                    <span v-else>Generating...</span>
                </button>

                <!-- Show code -->
                <div v-if="verificationCode" class="mb-4 rounded-lg bg-black/40 border border-white/10 px-3 py-2 text-sm">
                    <p class="text-gray-400 mb-1">Paste this EXACTLY in your RSI bio:</p>
                    <code class="font-mono text-lg tracking-widest">{{ verificationCode }}</code>
                </div>

                <!-- RSI handle input -->
                <div class="mb-3">
                    <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">
                        RSI Handle
                    </label>
                    <input
                        v-model="rsiHandle"
                        type="text"
                        placeholder="ichaa"
                        :class="[
                            'w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2',
                            error.details?.field === 'rsi_handle'
                                ? 'bg-red-900/30 border-red-500 focus:ring-red-500'
                                : 'bg-gray-800 border border-white/10 focus:ring-indigo-500'
                        ]"
                    />
                </div>

                <!-- Verify -->
                <button
                    @click="verifyRsi"
                    :disabled="verifying"
                    class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 disabled:opacity-50 text-sm font-medium"
                >
                    <span v-if="!verifying">Verify RSI</span>
                    <span v-else>Verifying...</span>
                </button>
            </div>

            <!-- (Optional) If rsiVerified AND discordVerified → instant redirect happens above -->
        </div>
    </div>
</template>
