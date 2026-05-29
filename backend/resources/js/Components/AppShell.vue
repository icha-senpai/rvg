<script setup>
import { computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import SideNav from '@/Components/SideNav.vue'

const page = usePage()

const user = computed(() => page.props?.auth?.user ?? null)
const isVerifyPage = computed(() => page.component === 'Verify')
const isErrorPage = computed(() => page.component === 'Error')
const shouldRedirectToVerify = computed(() => {
  return !user.value && !isVerifyPage.value && !isErrorPage.value
})

watch(
  shouldRedirectToVerify,
  (shouldRedirect) => {
    if (!shouldRedirect || typeof window === 'undefined') {
      return
    }

    if (window.location.pathname === '/verify') {
      return
    }

    window.location.assign('/verify')
  },
  { immediate: true }
)
</script>

<template>
  <div class="min-h-screen flex bg-grid-horizon_3 text-text-primary">
    <SideNav v-if="user" />

    <main class="flex-1 min-w-0">
      <section
        v-if="shouldRedirectToVerify"
        class="min-h-screen flex items-center justify-center px-6"
      >
        <p class="text-sm text-text-secondary">
          Redirecting to verification...
        </p>
      </section>

      <slot v-else />
    </main>
  </div>
</template>






