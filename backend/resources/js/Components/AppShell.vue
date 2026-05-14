<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import SideNav from '@/Components/SideNav.vue'

const page = usePage()

const user = computed(() => page.props?.auth?.user ?? null)
const isVerifyPage = computed(() => page.component === 'Verify')
const isErrorPage = computed(() => page.component === 'Error')
const shouldShowAuthFallback = computed(() => {
  return !user.value && !isVerifyPage.value && !isErrorPage.value
})
</script>

<template>
  <div class="min-h-screen flex bg-grid-horizon_3 text-text-primary">
    <SideNav v-if="user" />

    <main class="flex-1 min-w-0">
      <section
        v-if="shouldShowAuthFallback"
        class="min-h-screen flex items-center justify-center px-6"
      >
        <div class="max-w-xl rounded-2xl border border-white/10 bg-white/[0.035] p-8 text-center shadow-[0_0_40px_rgba(56,189,248,0.12)]">
          <h1 class="text-2xl font-bold text-horizon-white">
            Authentication Required
          </h1>

          <p class="mt-3 text-text-secondary">
            Your session is not active. Please continue through Horizon verification.
          </p>

          <Link
            href="/verify"
            class="mt-6 inline-flex rounded-xl border border-[color:var(--horizon-sunset-blue)]/40 bg-[color:var(--horizon-sunset-blue)]/15 px-5 py-3 font-semibold text-horizon-white transition hover:bg-[color:var(--horizon-sunset-blue)]/25"
          >
            Go to Verification
          </Link>
        </div>
      </section>

      <slot v-else />
    </main>
  </div>
</template>