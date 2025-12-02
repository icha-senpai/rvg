<script setup>
import { ref } from 'vue';
import axios from 'axios';

// --- 1) Grab token from URL (after Discord callback) ---
const params = new URLSearchParams(window.location.search);
const tokenFromUrl = params.get('token');

// If we just came back from Discord, save token
if (tokenFromUrl) {
    localStorage.setItem('access_token', tokenFromUrl);
    // Optional: clean the URL so token isn't visible forever
    window.history.replaceState({}, '', '/verify');
}

// Always read the current token from localStorage
const accessToken = localStorage.getItem('access_token') || null;

// Simple state: if we have a token, Discord is "verified"
const discordVerified = ref(!!accessToken);

// RSI state
const rsiHandle = ref('');
const verificationCode = ref('');
const error = ref({
    message: null,
    details: {}
});
const loadingCode = ref(false);
const verifying = ref(false);

// Helper for auth header
const authHeaders = () => ({
    Authorization: `Bearer ${accessToken}`,
});

// --- 2) Generate verification code (uses your existing /generate-code endpoint) ---
const getCode = async () => {
    error.value = null;

    if (!accessToken) {
        error.value = 'Missing access token. Please verify Discord again.';
        return;
    }

    loadingCode.value = true;

    try {
        const res = await axios.post(
            '/api/v1/generate-code',
            {},
            { headers: authHeaders() }
        );

        // Your ApiResponse::success() wraps data under .data
        verificationCode.value = res.data.data.verification_code;
    } catch (e) {
        if (e.response?.data?.message) {
            error.value = {
                message: e.response.data.message,
                details: e.response.data.data || {}
            };
        } else {
            error.value = {
                message: "Couldn't generate verification code. Please try again.",
                details: { error: e.message }
            };
        }
    } finally {
        loadingCode.value = false;
    }
};

// --- 3) Verify RSI (uses your existing /verify-rsi endpoint) ---
const verifyRsi = async () => {
    error.value = null;

    if (!accessToken) {
        error.value = 'Missing access token. Please verify Discord again.';
        return;
    }

    if (!rsiHandle.value) {
        error.value = {
            message: 'Please enter your RSI handle.',
            details: { field: 'rsi_handle' }
        };
        return;
    }

    verifying.value = true;

    try {
        await axios.post(
            '/api/v1/verify-rsi',
            {
                rsi_handle: rsiHandle.value, // 👈 matches RSIVerificationController
            },
            { headers: authHeaders() }
        );

        // On success, send them home
        window.location.href = '/';
    } catch (e) {
        if (e.response?.data) {
            error.value = {
                message: e.response.data.message || 'Verification failed',
                details: e.response.data.data || {}
            };
        } else {
            error.value = {
                message: 'Failed to connect to the verification service. Please check your connection and try again.',
                details: { error: e.message }
            };
        }
    } finally {
        verifying.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-black text-white px-4">
        <div class="w-full max-w-xl p-6 rounded-2xl bg-gray-900 shadow-xl border border-white/10">
            <!-- Header -->
            <h1 class="text-3xl font-bold mb-2">
                Horizon Interstellar Verification
            </h1>
            <p class="text-sm text-gray-400 mb-6">
                Step 1: Link Discord · Step 2: Prove RSI org membership.
            </p>

            <!-- Error box -->
            <div
                v-if="error.message"
                class="mb-4 rounded-lg border border-red-500/60 bg-red-500/10 px-4 py-3 text-sm text-red-200"
            >
                <div class="font-medium">{{ error.message }}</div>
                <!-- Display multi-line error details -->
                <div v-if="typeof error.message === 'string' && error.message.includes('\n')" 
                     class="mt-2 font-mono text-xs whitespace-pre-line">
                    {{ error.message }}
                </div>
                <!-- Display error details if available -->
                <div v-if="Object.keys(error.details).length > 0" class="mt-2 pt-2 border-t border-red-500/20">
                    <div v-if="error.details.help_link" class="mt-1">
                        <a :href="error.details.help_link" target="_blank" class="text-blue-400 hover:underline">
                            {{ error.details.help_link.includes('orgs/') ? 'View Organization' : 'View Profile' }}
                        </a>
                    </div>
                    <div v-if="error.details.error_reference" class="text-xs opacity-75 mt-1">
                        Reference: {{ error.details.error_reference }}
                    </div>
                </div>
            </div>

            <!-- STEP 1: Discord verification -->
            <div v-if="!discordVerified">
                <h2 class="text-xl font-semibold mb-3">Step 1 · Verify with Discord</h2>
                <p class="text-sm text-gray-300 mb-4">
                    Click the button below to log in with Discord. Once approved, you’ll be
                    returned here to finish RSI verification.
                </p>

                <a
                    href="/auth/discord"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 transition text-sm font-medium"
                >
                    Verify with Discord
                </a>
            </div>

            <!-- STEP 2: RSI verification -->
            <div v-else>
                <h2 class="text-xl font-semibold mb-3">Step 2 · RSI Verification</h2>
                <p class="text-sm text-gray-300 mb-4">
                    1) Click "Generate Code" and paste it into your RSI profile bio.<br />
                    2) Enter your RSI handle and click "Verify RSI".
                </p>

                <!-- Generate code button -->
                <button
                    @click="getCode"
                    :disabled="loadingCode"
                    class="mb-3 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                >
                    <span v-if="!loadingCode">Generate Code</span>
                    <span v-else>Generating...</span>
                </button>

                <!-- Show code if generated -->
                <div
                    v-if="verificationCode"
                    class="mb-4 rounded-lg bg-black/40 border border-white/10 px-3 py-2 text-sm"
                >
                    <p class="text-gray-400 mb-1">Paste this EXACTLY into your RSI bio:</p>
                    <code class="font-mono text-lg tracking-widest">
                        {{ verificationCode }}
                    </code>
                </div>

                <!-- RSI handle input -->
                <div class="mb-3" :class="{ 'has-error': error.details?.field === 'rsi_handle' }">
                    <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">
                        RSI Handle
                    </label>
                    <input
                        v-model="rsiHandle"
                        type="text"
                        placeholder="ichaa"
                        :class="{
                            'w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2': true,
                            'bg-gray-800 border border-white/10 focus:ring-indigo-500': error.details?.field !== 'rsi_handle',
                            'bg-red-900/30 border-red-500 focus:ring-red-500': error.details?.field === 'rsi_handle'
                        }"
                    />
                </div>

                <!-- Verify button -->
                <button
                    @click="verifyRsi"
                    :disabled="verifying"
                    class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                >
                    <span v-if="!verifying">Verify RSI</span>
                    <span v-else>Verifying...</span>
                </button>
            </div>
        </div>
    </div>
</template>
