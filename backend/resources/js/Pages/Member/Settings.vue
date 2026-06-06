<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import { normalizeSiteTheme, siteThemeGroups } from '@/siteThemes'

const page = usePage()
const pendingTheme = ref(null)

const user = computed(() => page.props?.auth?.user ?? null)
const currentTheme = computed(() => normalizeSiteTheme(user.value?.site_theme))

const profileHref = computed(() => {
  if (!user.value) return null
  if (user.value?.rsi_handle) return route('member.profile', user.value.rsi_handle)
  if (user.value?.id) return `/user/${user.value.id}`
  return null
})

const profileEditHref = computed(() => {
  if (!profileHref.value) return null

  try {
    const resolved = new URL(profileHref.value, window.location.origin)
    resolved.searchParams.set('edit', '1')
    return `${resolved.pathname}${resolved.search}`
  } catch {
    const separator = profileHref.value.includes('?') ? '&' : '?'
    return `${profileHref.value}${separator}edit=1`
  }
})

function visit(href) {
  if (!href) return

  router.visit(href, {
    preserveScroll: true,
    preserveState: true,
  })
}

function applyTheme(theme) {
  const normalizedTheme = normalizeSiteTheme(theme)

  if (pendingTheme.value || normalizedTheme === currentTheme.value) {
    return
  }

  pendingTheme.value = normalizedTheme

  router.put(route('me.update'), {
    site_theme: normalizedTheme,
  }, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      pendingTheme.value = null
    },
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-5xl">
      <section class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-text-muted">
              User Settings
            </div>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
              Account Preferences
            </h1>
            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              Pick the site theme you want to live in without changing it for anyone else.
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <HorizonButton
              v-if="profileHref"
              type="button"
              variant="ghost"
              size="sm"
              @click="visit(profileHref)"
            >
              View Profile
            </HorizonButton>
            <HorizonButton
              v-if="profileEditHref"
              type="button"
              variant="ghost"
              size="sm"
              @click="visit(profileEditHref)"
            >
              Edit Profile
            </HorizonButton>
          </div>
        </div>
      </section>

      <section class="mt-6 hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-6">
        <div class="flex flex-col gap-2">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
            Personal Theme
          </div>
          <h2 class="text-2xl font-black tracking-tight text-horizon-white md:text-3xl">
            Choose your site look
          </h2>
        </div>

        <div class="mt-8 space-y-8">
          <section
            v-for="group in siteThemeGroups"
            :key="group.key"
            class="space-y-4"
          >
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.22em] text-text-muted">
                {{ group.label }}
              </div>
              <p class="mt-2 max-w-3xl text-sm text-text-secondary">
                {{ group.description }}
              </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <article
                v-for="option in group.options"
                :key="option.value"
                class="rounded-[1.6rem] border p-5"
                :class="currentTheme === option.value
                  ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.05]'
                  : 'border-white/[0.055] bg-white/[0.024]'"
              >
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h3 class="text-lg font-black text-horizon-white">
                      {{ option.label }}
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-text-secondary">
                      {{ option.description }}
                    </p>
                  </div>

                  <span
                    class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]"
                    :class="currentTheme === option.value
                      ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200'
                      : 'border-white/[0.08] bg-white/[0.04] text-text-secondary'"
                  >
                    {{ currentTheme === option.value ? 'Active' : 'Theme' }}
                  </span>
                </div>

                <div
                  class="mt-5 rounded-[1.25rem] border p-4"
                  :style="{
                    borderColor: `${option.primary}33`,
                    background: `linear-gradient(180deg, ${option.dark}F2, ${option.dark}FA)`,
                  }"
                >
                  <div class="flex gap-2">
                    <span
                      v-for="color in [option.dark, option.primary, option.secondary]"
                      :key="`${option.value}-${color}`"
                      class="h-10 flex-1 rounded-full border border-black/5"
                      :style="{ backgroundColor: color }"
                    />
                  </div>
                  <div class="mt-4 grid gap-2">
                    <div
                      class="h-3 rounded-full"
                      :style="{ backgroundColor: `${option.text}33` }"
                    />
                    <div
                      class="h-3 w-4/5 rounded-full"
                      :style="{ backgroundColor: `${option.text}1F` }"
                    />
                    <div
                      class="h-9 w-32 rounded-2xl"
                      :style="{ background: `linear-gradient(135deg, ${option.primary}, ${option.secondary})` }"
                    />
                  </div>
                </div>

                <div class="mt-5">
                  <HorizonButton
                    type="button"
                    :variant="currentTheme === option.value ? 'success' : 'ghost'"
                    size="sm"
                    :disabled="Boolean(pendingTheme)"
                    @click="applyTheme(option.value)"
                  >
                    <template v-if="pendingTheme === option.value">
                      Applying...
                    </template>
                    <template v-else-if="currentTheme === option.value">
                      Current Theme
                    </template>
                    <template v-else>
                      Use {{ option.label }}
                    </template>
                  </HorizonButton>
                </div>
              </article>
            </div>
          </section>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
