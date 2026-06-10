<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import { normalizeSiteTheme, siteThemeGroups } from '@/siteThemes'

const page = usePage()
const pendingTheme = ref(null)
const activeTab = ref('themes')
const savingUserSettings = ref(false)
const syncingDiscordRoles = ref(false)
const savingDiscordRoles = ref(false)
const selectedRegion = ref('')
const selectedTimezone = ref('')
const timezoneSearch = ref('')
const selectedBranchRoleIds = ref([])
const selectedPlayerRoleIds = ref([])

const user = computed(() => page.props?.auth?.user ?? null)
const currentTheme = computed(() => normalizeSiteTheme(user.value?.site_theme))
const discordRoleSettings = computed(() => page.props?.discordRoleSettings ?? {
  status: 'not_configured',
  status_message: 'Discord role sync is not configured yet.',
  enabled: false,
  discord_linked: false,
  discord_name: null,
  synced_at: null,
  health_checks: [],
  branch_roles: [],
  player_roles: [],
  selected_branch_role_ids: [],
  selected_player_role_ids: [],
})
const discordRoleError = computed(() => page.props?.errors?.discord_roles
  ?? page.props?.errors?.branch_role_ids
  ?? page.props?.errors?.['branch_role_ids.0']
  ?? page.props?.errors?.player_role_ids
  ?? page.props?.errors?.['player_role_ids.0']
  ?? null)
const selectedBranchRoleCount = computed(() => selectedBranchRoleIds.value.length)
const selectedPlayerRoleCount = computed(() => selectedPlayerRoleIds.value.length)
const userSettingsError = computed(() => page.props?.errors?.timezone
  ?? page.props?.errors?.region
  ?? null)
const formattedDiscordSyncedAt = computed(() => {
  const value = discordRoleSettings.value?.synced_at

  if (!value) return null

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return null

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date)
})

const regionOptions = [
  { label: 'EU', value: 'EU' },
  { label: 'US', value: 'US' },
  { label: 'APAC', value: 'APAC' },
]

const fallbackTimezoneValues = [
  'UTC',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/New_York',
  'America/Phoenix',
  'America/Toronto',
  'Asia/Dubai',
  'Asia/Hong_Kong',
  'Asia/Kolkata',
  'Asia/Singapore',
  'Asia/Tokyo',
  'Australia/Adelaide',
  'Australia/Brisbane',
  'Australia/Melbourne',
  'Australia/Perth',
  'Australia/Sydney',
  'Europe/Amsterdam',
  'Europe/Berlin',
  'Europe/London',
  'Europe/Madrid',
  'Europe/Paris',
]

function timezoneOffsetLabel(value) {
  try {
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: value,
      timeZoneName: 'shortOffset',
    })

    const offsetPart = formatter.formatToParts(new Date()).find(part => part.type === 'timeZoneName')?.value

    if (!offsetPart) {
      return null
    }

    if (offsetPart === 'GMT' || offsetPart === 'UTC') {
      return 'UTC+0'
    }

    return offsetPart.replace(/^GMT/, 'UTC')
  } catch {
    return null
  }
}

function formatTimezoneOptionLabel(value) {
  const offset = timezoneOffsetLabel(value)

  return offset ? `${value} (${offset})` : value
}

const timezoneOptions = computed(() => {
  const values = typeof Intl !== 'undefined' && typeof Intl.supportedValuesOf === 'function'
    ? Intl.supportedValuesOf('timeZone')
    : fallbackTimezoneValues

  const uniqueValues = Array.from(new Set([
    ...values,
    selectedTimezone.value,
    user.value?.timezone,
  ].filter(Boolean)))

  return uniqueValues.map(value => ({
    label: formatTimezoneOptionLabel(value),
    value,
  }))
})

const visibleTimezoneOptions = computed(() => {
  const query = String(timezoneSearch.value ?? '').trim().toLowerCase()

  if (query) {
    return timezoneOptions.value
      .filter(option =>
        option.value.toLowerCase().includes(query)
        || option.label.toLowerCase().includes(query)
      )
  }

  return timezoneOptions.value
})

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

watch(
  user,
  (nextUser) => {
    selectedRegion.value = nextUser?.region ?? ''
    selectedTimezone.value = nextUser?.timezone ?? ''
  },
  { immediate: true }
)

watch(
  discordRoleSettings,
  (settings) => {
    selectedBranchRoleIds.value = Array.isArray(settings?.selected_branch_role_ids)
      ? [...settings.selected_branch_role_ids]
      : []
    selectedPlayerRoleIds.value = Array.isArray(settings?.selected_player_role_ids)
      ? [...settings.selected_player_role_ids]
      : []
  },
  { immediate: true }
)

function toggleBranchRole(roleId) {
  if (selectedBranchRoleIds.value.includes(roleId)) {
    selectedBranchRoleIds.value = selectedBranchRoleIds.value.filter(value => value !== roleId)
    return
  }

  selectedBranchRoleIds.value = [...selectedBranchRoleIds.value, roleId]
}

function togglePlayerRole(roleId) {
  if (selectedPlayerRoleIds.value.includes(roleId)) {
    selectedPlayerRoleIds.value = selectedPlayerRoleIds.value.filter(value => value !== roleId)
    return
  }

  selectedPlayerRoleIds.value = [...selectedPlayerRoleIds.value, roleId]
}

function syncDiscordRoles() {
  if (syncingDiscordRoles.value) return

  syncingDiscordRoles.value = true

  router.post(route('settings.discord-roles.sync'), {}, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      syncingDiscordRoles.value = false
    },
  })
}

function saveUserSettings() {
  if (savingUserSettings.value) return

  savingUserSettings.value = true

  router.put(route('me.update'), {
    region: selectedRegion.value || null,
    timezone: selectedTimezone.value || null,
  }, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      savingUserSettings.value = false
    },
  })
}

function selectTimezone(value) {
  selectedTimezone.value = value
}

function saveDiscordRoles() {
  if (savingDiscordRoles.value) return

  savingDiscordRoles.value = true

  router.put(route('settings.discord-roles.update'), {
    branch_role_ids: selectedBranchRoleIds.value,
    player_role_ids: selectedPlayerRoleIds.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      savingDiscordRoles.value = false
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
        <div class="flex flex-col gap-4">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
            Settings Areas
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-full border px-4 py-2 text-sm font-semibold transition"
              :class="activeTab === 'user'
                ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.06] text-horizon-white'
                : 'border-white/[0.08] bg-white/[0.025] text-text-secondary hover:border-white/15 hover:text-horizon-white'"
              @click="activeTab = 'user'"
            >
              User Settings
            </button>
            <button
              type="button"
              class="rounded-full border px-4 py-2 text-sm font-semibold transition"
              :class="activeTab === 'themes'
                ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.06] text-horizon-white'
                : 'border-white/[0.08] bg-white/[0.025] text-text-secondary hover:border-white/15 hover:text-horizon-white'"
              @click="activeTab = 'themes'"
            >
              Themes
            </button>
            <button
              type="button"
              class="rounded-full border px-4 py-2 text-sm font-semibold transition"
              :class="activeTab === 'discord'
                ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.06] text-horizon-white'
                : 'border-white/[0.08] bg-white/[0.025] text-text-secondary hover:border-white/15 hover:text-horizon-white'"
              @click="activeTab = 'discord'"
            >
              Discord Roles
            </button>
          </div>
        </div>

        <div
          v-if="activeTab === 'user'"
          class="mt-8 space-y-6"
        >
          <div class="flex flex-col gap-2">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
              User Settings
            </div>
            <h2 class="text-2xl font-black tracking-tight text-horizon-white md:text-3xl">
              Time and region
            </h2>
            <p class="max-w-3xl text-sm text-text-secondary">
              Set the region and timezone shown across your Horizon profile.
            </p>
          </div>

          <section class="hz-surface-deep rounded-[1.5rem] p-5">
            <div class="grid gap-5 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
              <div>
                <div class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  Region
                </div>

                <div class="space-y-3">
                  <button
                    type="button"
                    class="w-full rounded-[1.25rem] border px-4 py-4 text-left transition"
                    :class="selectedRegion === ''
                      ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.05]'
                      : 'border-white/[0.08] bg-white/[0.025] hover:border-white/15'"
                    @click="selectedRegion = ''"
                  >
                    <div class="flex items-start justify-between gap-4">
                      <div>
                        <div class="text-sm font-black text-horizon-white">
                          No region
                        </div>
                        <div class="mt-1 text-sm text-text-secondary">
                          Leave your region unset on the profile.
                        </div>
                      </div>
                      <span class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]"
                        :class="selectedRegion === ''
                          ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200'
                          : 'border-white/[0.08] bg-white/[0.04] text-text-secondary'"
                      >
                        {{ selectedRegion === '' ? 'Selected' : 'Clear' }}
                      </span>
                    </div>
                  </button>

                  <button
                    v-for="option in regionOptions"
                    :key="option.value"
                    type="button"
                    class="w-full rounded-[1.25rem] border px-4 py-4 text-left transition"
                    :class="selectedRegion === option.value
                      ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.05]'
                      : 'border-white/[0.08] bg-white/[0.025] hover:border-white/15'"
                    @click="selectedRegion = option.value"
                  >
                    <div class="flex items-start justify-between gap-4">
                      <div>
                        <div class="text-sm font-black text-horizon-white">
                          {{ option.label }}
                        </div>
                        <div class="mt-1 text-sm text-text-secondary">
                          Show {{ option.label }} as your primary region.
                        </div>
                      </div>
                      <span class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]"
                        :class="selectedRegion === option.value
                          ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200'
                          : 'border-white/[0.08] bg-white/[0.04] text-text-secondary'"
                      >
                        {{ selectedRegion === option.value ? 'Selected' : 'Pick' }}
                      </span>
                    </div>
                  </button>
                </div>
              </div>

              <div>
                <div class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  Timezone
                </div>

                <div class="rounded-[1.25rem] border border-white/[0.08] bg-white/[0.025] p-4">
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <div class="text-sm font-black text-horizon-white">
                        {{ selectedTimezone || 'No timezone selected' }}
                      </div>
                      <div class="mt-1 text-sm text-text-secondary">
                        {{ selectedTimezone ? 'This timezone will show on your Horizon profile.' : 'Pick a timezone from the list below.' }}
                      </div>
                    </div>

                    <HorizonButton
                      type="button"
                      variant="ghost"
                      size="sm"
                      :disabled="selectedTimezone === ''"
                      @click="selectTimezone('')"
                    >
                      Clear
                    </HorizonButton>
                  </div>

                  <div class="mt-4">
                    <input
                      v-model="timezoneSearch"
                      type="text"
                      class="hz-input"
                      placeholder="Search timezone..."
                    />
                  </div>

                  <div class="mt-4 max-h-80 overflow-auto rounded-[1rem] border border-white/[0.08] bg-black/15 p-2">
                    <div class="grid gap-2">
                      <button
                        v-for="option in visibleTimezoneOptions"
                        :key="option.value"
                        type="button"
                        class="rounded-[1rem] border px-3 py-3 text-left transition"
                        :class="selectedTimezone === option.value
                          ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.05]'
                          : 'border-white/[0.08] bg-white/[0.025] hover:border-white/15'"
                        @click="selectTimezone(option.value)"
                      >
                        <div class="flex items-start justify-between gap-3">
                          <div>
                            <div class="text-sm font-black text-horizon-white">
                              {{ option.value }}
                            </div>
                            <div class="mt-1 text-xs text-text-secondary">
                              {{ option.label }}
                            </div>
                          </div>
                          <span
                            class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]"
                            :class="selectedTimezone === option.value
                              ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200'
                              : 'border-white/[0.08] bg-white/[0.04] text-text-secondary'"
                          >
                            {{ selectedTimezone === option.value ? 'Selected' : 'Pick' }}
                          </span>
                        </div>
                      </button>

                      <div
                        v-if="!visibleTimezoneOptions.length"
                        class="rounded-[1rem] border border-dashed border-white/[0.08] px-4 py-6 text-sm text-text-secondary"
                      >
                        No timezones match that search.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <p v-if="userSettingsError" class="mt-4 text-sm font-semibold text-red-300">
              {{ userSettingsError }}
            </p>

            <div class="mt-5 flex flex-wrap justify-end gap-2">
              <HorizonButton
                type="button"
                variant="primary"
                size="sm"
                :disabled="savingUserSettings"
                @click="saveUserSettings"
              >
                {{ savingUserSettings ? 'Saving…' : 'Save User Settings' }}
              </HorizonButton>
            </div>
          </section>
        </div>

        <div v-else-if="activeTab === 'themes'" class="mt-8 space-y-8">
          <div class="flex flex-col gap-2">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
              Personal Theme
            </div>
            <h2 class="text-2xl font-black tracking-tight text-horizon-white md:text-3xl">
              Choose your site look
            </h2>
          </div>

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

        <div
          v-else
          class="mt-8 space-y-6"
        >
          <div class="flex flex-col gap-2">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">
              Discord Roles
            </div>
            <h2 class="text-2xl font-black tracking-tight text-horizon-white md:text-3xl">
              Sync and manage your guild roles
            </h2>
            <p class="max-w-3xl text-sm text-text-secondary">
              Pick any branch and player roles you want active on your Discord profile.
            </p>
          </div>

          <div class="flex flex-wrap items-center justify-end gap-2">
            <p v-if="formattedDiscordSyncedAt" class="mr-auto text-xs text-text-muted">
              Last synced {{ formattedDiscordSyncedAt }}
            </p>
            <p v-if="discordRoleError" class="mr-auto text-sm font-semibold text-red-300">
              {{ discordRoleError }}
            </p>
            <HorizonButton
              type="button"
              variant="ghost"
              size="sm"
              :disabled="syncingDiscordRoles || !discordRoleSettings.discord_linked || !discordRoleSettings.enabled"
              @click="syncDiscordRoles"
            >
              {{ syncingDiscordRoles ? 'Syncing…' : 'Sync Discord Roles' }}
            </HorizonButton>
            <HorizonButton
              type="button"
              variant="primary"
              size="sm"
              :disabled="savingDiscordRoles || !discordRoleSettings.discord_linked || !discordRoleSettings.enabled"
              @click="saveDiscordRoles"
            >
              {{ savingDiscordRoles ? 'Saving…' : 'Save Discord Roles' }}
            </HorizonButton>
          </div>

          <section class="grid gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <div class="hz-surface-deep rounded-[1.5rem] p-5">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="text-xs font-bold uppercase tracking-[0.22em] text-text-muted">
                    Branch Roles
                  </div>
                  <h3 class="mt-2 text-xl font-black text-horizon-white">
                    Toggle your branches
                  </h3>
                </div>

                <div class="rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ selectedBranchRoleCount ? `${selectedBranchRoleCount} selected` : 'Optional' }}
                </div>
              </div>

              <div class="mt-5 space-y-3">
                <button
                  v-for="role in discordRoleSettings.branch_roles"
                  :key="role.id"
                  type="button"
                  class="w-full rounded-[1.25rem] border px-4 py-4 text-left transition"
                  :class="selectedBranchRoleIds.includes(role.id)
                    ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.05]'
                    : 'border-white/[0.08] bg-white/[0.025] hover:border-white/15'"
                  @click="toggleBranchRole(role.id)"
                >
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <div class="text-sm font-black text-horizon-white">
                        {{ role.label }}
                      </div>
                      <div class="mt-1 text-sm text-text-secondary">
                        {{ selectedBranchRoleIds.includes(role.id) ? 'This branch role will stay on your Discord profile.' : 'Click to add this branch role.' }}
                      </div>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]"
                      :class="selectedBranchRoleIds.includes(role.id)
                        ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200'
                        : 'border-white/[0.08] bg-white/[0.04] text-text-secondary'"
                    >
                      {{ selectedBranchRoleIds.includes(role.id) ? 'On' : 'Off' }}
                    </span>
                  </div>
                </button>
              </div>
            </div>

            <div class="hz-surface-deep rounded-[1.5rem] p-5">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="text-xs font-bold uppercase tracking-[0.22em] text-text-muted">
                    Player Roles
                  </div>
                  <h3 class="mt-2 text-xl font-black text-horizon-white">
                    Toggle your activity roles
                  </h3>
                </div>

                <div class="rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ selectedPlayerRoleCount }} selected
                </div>
              </div>

              <div class="mt-5 grid gap-3 md:grid-cols-2">
                <button
                  v-for="role in discordRoleSettings.player_roles"
                  :key="role.id"
                  type="button"
                  class="rounded-[1.25rem] border px-4 py-4 text-left transition"
                  :class="selectedPlayerRoleIds.includes(role.id)
                    ? 'border-[color:var(--horizon-sunset-blue)]/45 bg-white/[0.05]'
                    : 'border-white/[0.08] bg-white/[0.025] hover:border-white/15'"
                  @click="togglePlayerRole(role.id)"
                >
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <div class="text-sm font-black text-horizon-white">
                        {{ role.label }}
                      </div>
                      <div class="mt-1 text-sm text-text-secondary">
                        {{ selectedPlayerRoleIds.includes(role.id) ? 'This role will stay on your Discord profile.' : 'Click to add this Discord role.' }}
                      </div>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]"
                      :class="selectedPlayerRoleIds.includes(role.id)
                        ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200'
                        : 'border-white/[0.08] bg-white/[0.04] text-text-secondary'"
                    >
                      {{ selectedPlayerRoleIds.includes(role.id) ? 'On' : 'Off' }}
                    </span>
                  </div>
                </button>
              </div>
            </div>
          </section>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>
