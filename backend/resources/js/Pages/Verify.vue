<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import { extractFirstErrorMessage, notifyError } from '@/errors'

// --- URL params from Discord OAuth callback ---
const page = usePage()
const params = new URLSearchParams(window.location.search)
const errorFromUrl = params.get('error')

const verification = computed(() => page.props?.verification ?? {})
const pageErrors = computed(() => page.props?.errors ?? {})
const flashSuccess = computed(() => page.props?.flash?.success ?? null)

let initialErrorMessage = null

if (errorFromUrl === 'not_in_guild') {
    initialErrorMessage = 'Access denied. You must be in the org Discord before you can continue.'
} else if (errorFromUrl === 'discord_check_unavailable') {
    initialErrorMessage = 'Discord verification is temporarily unavailable (guild check failed). Please try again in a few minutes.'
} else if (errorFromUrl === 'oauth') {
    initialErrorMessage = 'Discord login failed. Please try again.'
} else if (errorFromUrl) {
    initialErrorMessage = 'Discord verification failed. Please try again.'
}

if (errorFromUrl) {
    window.history.replaceState({}, '', '/verify')
}

const discordVerified = computed(() => Boolean(verification.value?.discordVerified))
const rsiVerified = computed(() => Boolean(verification.value?.rsiVerified))
const verificationCode = computed(() => verification.value?.code ?? '')
const verificationCodeExpiresAt = computed(() => verification.value?.expiresAt ?? null)

const rsiForm = useForm({
    rsi_handle: verification.value?.rsiHandle ?? '',
})

watch(
    () => verification.value?.rsiHandle,
    (nextValue) => {
        rsiForm.rsi_handle = nextValue ?? ''
    },
    { immediate: true }
)

const loadingCode = ref(false)
const copiedCode = ref(false)

const inlineErrorMessage = computed(() => {
    const verificationError = pageErrors.value?.verification
    const handleError = pageErrors.value?.rsi_handle

    const combinedErrors = {
        ...(verificationError ? { verification: verificationError } : {}),
        ...(handleError ? { rsi_handle: handleError } : {}),
    }

    if (Object.keys(combinedErrors).length === 0) {
        return initialErrorMessage
    }

    return extractFirstErrorMessage(combinedErrors, initialErrorMessage)
})

const rsiHandleFieldError = computed(() => {
    const handleError = pageErrors.value?.rsi_handle

    if (Array.isArray(handleError) && handleError[0]) return handleError[0]
    if (typeof handleError === 'string' && handleError.trim()) return handleError

    return null
})

// --- Generate RSI verification code ---
const getCode = () => {
    loadingCode.value = true

    router.post('/verify/code', {}, {
        preserveScroll: true,
        onFinish: () => {
            loadingCode.value = false
            copiedCode.value = false
        },
    })
}

// --- Submit RSI verification ---
const verifyRsi = () => {
    rsiForm.post('/verify/rsi', {
        preserveScroll: true,
    })
}

const copyVerificationCode = async () => {
    const text = verificationCode.value
    if (!text) return

    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(text)
        } else {
            const textarea = document.createElement('textarea')
            textarea.value = text
            textarea.setAttribute('readonly', '')
            textarea.style.position = 'fixed'
            textarea.style.top = '0'
            textarea.style.left = '0'
            textarea.style.opacity = '0'
            document.body.appendChild(textarea)
            textarea.focus()
            textarea.select()
            textarea.setSelectionRange(0, textarea.value.length)
            const ok = document.execCommand('copy')
            textarea.remove()

            if (!ok) {
                throw new Error('Copy failed')
            }
        }

        copiedCode.value = true
        window.setTimeout(() => {
            copiedCode.value = false
        }, 1400)
    } catch (e) {
        notifyError({ message: 'Failed to copy code. Please copy it manually.' })
    }
}
</script>

<template>
    <HorizonContainer class="bg-grid-horizon_3 flex items-center justify-center">
        <div class="hz-popover-surface w-full max-w-xl rounded-2xl p-6 text-text-primary">

            <h1 class="mb-2 text-3xl font-bold text-horizon-white">Horizon Interstellar Verification</h1>
            <p class="mb-6 text-sm text-text-secondary">
                Step 1: Link Discord · Step 2: Prove RSI org membership.
            </p>

            <!-- Error -->
            <div
                v-if="inlineErrorMessage"
                class="mb-4 rounded-lg border border-red-500/60 bg-red-500/10 px-4 py-3 text-sm text-red-200"
            >
                <div class="font-medium">{{ inlineErrorMessage }}</div>
            </div>

            <div
                v-if="flashSuccess"
                class="mb-4 rounded-lg border border-emerald-500/50 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100"
            >
                <div class="font-medium">{{ flashSuccess }}</div>
            </div>

            <!-- STEP 1: Discord -->
            <div v-if="!discordVerified">
                <h2 class="text-xl font-semibold mb-3">Step 1 · Verify with Discord</h2>
                <p class="mb-4 text-sm text-text-secondary">
                    Click below to log in with Discord. You'll return here afterward.
                </p>

                <a
                    href="/auth/discord"
                    class="hz-btn hz-btn-primary hz-btn-sm"
                >
                    Verify with Discord
                </a>
            </div>

            <!-- STEP 2: RSI — only if not yet verified -->
            <div v-else-if="!rsiVerified">
                <h2 class="text-xl font-semibold mb-3">Step 2 · RSI Verification</h2>
                <p class="mb-4 text-sm text-text-secondary">
                    1) Make sure Horizon is set as your main org!!<br>
                    2) Generate your code and paste it into your RSI bio.<br>
                    3) Enter your RSI handle and click "Verify RSI".
                </p>

                <!-- Generate code -->
                <HorizonButton
                    class="mb-3"
                    variant="primary"
                    size="sm"
                    @click="getCode"
                    :disabled="loadingCode"
                >
                    <span v-if="!loadingCode">Generate Code</span>
                    <span v-else>Generating...</span>
                </HorizonButton>

                <!-- Show code -->
                <div v-if="verificationCode" class="hz-surface-deep mb-4 rounded-lg px-3 py-2 text-sm">
                    <p class="mb-1 text-text-secondary">Paste this EXACTLY in your RSI bio:</p>
                    <p v-if="verificationCodeExpiresAt" class="mb-2 text-xs text-text-muted">Expires at {{ new Date(verificationCodeExpiresAt).toLocaleString() }}</p>
                    <div class="flex items-center justify-between gap-3">
                        <code class="font-mono text-lg tracking-widest text-horizon-white">{{ verificationCode }}</code>
                        <HorizonButton
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="!verificationCode || copiedCode"
                            @click="copyVerificationCode"
                        >
                            <span v-if="copiedCode">Copied</span>
                            <span v-else>Copy</span>
                        </HorizonButton>
                    </div>
                </div>

                <!-- RSI handle input -->
                <div class="mb-3">
                    <label class="mb-1 block text-xs uppercase tracking-wide text-text-muted">
                        RSI Handle
                    </label>
                    <HorizonInput
                        v-model="rsiForm.rsi_handle"
                        type="text"
                        placeholder=""
                        :class="[
                            'w-full text-sm focus:outline-none focus:ring-2',
                            rsiHandleFieldError
                                ? 'border border-red-500 bg-red-500/10 text-red-100 focus:ring-red-500'
                                : 'hz-input focus:ring-indigo-500'
                        ]"
                    />
                </div>

                <!-- Verify -->
                <HorizonButton
                    class="w-full"
                    variant="primary"
                    size="sm"
                    @click="verifyRsi"
                    :disabled="rsiForm.processing"
                >
                    <span v-if="!rsiForm.processing">Verify RSI</span>
                    <span v-else>Verifying...</span>
                </HorizonButton>
            </div>

            <!-- (Optional) If rsiVerified AND discordVerified → instant redirect happens above -->
        </div>
    </HorizonContainer>
</template>





