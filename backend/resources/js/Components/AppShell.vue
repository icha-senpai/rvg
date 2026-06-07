<script setup>
import { computed, onBeforeUnmount, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import SideNav from '@/Components/SideNav.vue'
import { normalizeSiteTheme } from '@/siteThemes'

const page = usePage()

const user = computed(() => page.props?.auth?.user ?? null)
const siteTheme = computed(() => {
  return normalizeSiteTheme(page.props?.auth?.user?.site_theme)
})
const isVerifyPage = computed(() => page.component === 'Verify')
const isErrorPage = computed(() => page.component === 'Error')
const shouldRedirectToVerify = computed(() => {
  return !user.value && !isVerifyPage.value && !isErrorPage.value
})

watch(
  siteTheme,
  (theme) => {
    if (typeof document === 'undefined') {
      return
    }

    document.documentElement.setAttribute('data-site-theme', theme)
    document.documentElement.style.colorScheme = 'dark'
  },
  { immediate: true }
)

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

onBeforeUnmount(() => {
  if (typeof document === 'undefined') {
    return
  }

  document.documentElement.setAttribute('data-site-theme', 'horizon')
  document.documentElement.style.colorScheme = 'dark'
})
</script>

<template>
  <div :data-site-theme="siteTheme" class="min-h-screen flex bg-grid-horizon_3 text-text-primary">
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



