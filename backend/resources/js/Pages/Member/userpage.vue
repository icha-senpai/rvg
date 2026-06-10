<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonDrawer from '@/Components/HorizonDrawer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import { isAdmiralPlus } from '@/auth'
import { extractFirstErrorMessage as extractSharedErrorMessage } from '@/errors'

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage()

const inertiaUser = computed(() => page.props?.auth?.user ?? null)
const profileUser = computed(() => page.props?.profileUser ?? null)
const favoriteShipReferences = computed(() => Array.isArray(page.props?.favoriteShipOptions) ? page.props.favoriteShipOptions : [])
const favoriteItemReferences = computed(() => Array.isArray(page.props?.favoriteItemOptions) ? page.props.favoriteItemOptions : [])
const isViewingOwnProfile = computed(() => {
  const authedId = inertiaUser.value?.id
  const targetId = (profileUser.value ?? inertiaUser.value)?.id
  if (!authedId || !targetId) return true
  return Number(authedId) === Number(targetId)
})
const canEditProfile = computed(() => isViewingOwnProfile.value)

const me = ref(profileUser.value ?? inertiaUser.value)
const errorMessage = ref(null)
const promotionErrorMessage = ref(null)

const isEditing = ref(false)
const isSaving = ref(false)
const isSubmittingPromotion = ref(false)
const isCancellingPromotion = ref(false)
const isDemotingMember = ref(false)
const isPromotionWorkflowOpen = ref(false)
const isPromotionConfirmOpen = ref(false)
const selectedPromotionBranchRoleId = ref('')

const form = ref({
  bio: '',
  timezone: '',
  region: '',
  availability_status: '',
  loa_note: '',
  favorite_ships: [],
  favorite_guns: [],
  callsign: '',
  primary_role: '',
  secondary_role: '',
  preferred_gameplay_style: '',
  typical_op_commitment: '',
  experience_ratings: {
    space_combat: null,
    ground_combat: null,
    logistics_support: null,
    medical: null,
  },
})

const roleOptions = [
  { label: 'Not set', value: '' },
  { label: 'Space Combat', value: 'space_combat' },
  { label: 'Ground Combat', value: 'ground_combat' },
  { label: 'Logistics / Support', value: 'logistics_support' },
  { label: 'Medical', value: 'medical' },
]

const gameplayStyleOptions = [
  { label: 'Not set', value: '' },
  { label: 'Tactical / Milsim', value: 'tactical/milsim' },
  { label: 'Casual / Chill', value: 'casual/chill' },
  { label: 'RP / RP-leaning', value: 'rp/rp-leaning' },
  { label: 'Training / Mentor', value: 'training/mentor' },
  { label: "Point me at them and I'll fire", value: "point me at them and i'll fire" },
]

const opCommitmentOptions = [
  { label: 'Not set', value: '' },
  { label: 'Full', value: 'full' },
  { label: 'Partial', value: 'partial' },
  { label: 'Flexible', value: 'flexible' },
]

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
    form.value.timezone,
    me.value?.timezone,
  ].filter(Boolean)))

  return uniqueValues.map(value => ({
    label: formatTimezoneOptionLabel(value),
    value,
  }))
})

const favoriteShipSearch = ref('')
const favoriteItemSearch = ref('')

const favoriteShipOptions = computed(() => {
  const groupedOptions = new Map()

  favoriteShipReferences.value.forEach(ship => {
    const name = String(ship?.name ?? '').trim()
    if (!name) return

    const label = String(ship?.type ?? '').trim() || 'Other'
    const current = groupedOptions.get(label) ?? []

    if (!current.includes(name)) {
      current.push(name)
    }

    groupedOptions.set(label, current)
  })

  return Array.from(groupedOptions.entries())
    .map(([label, options]) => ({
      label,
      options: options.sort((left, right) => left.localeCompare(right)),
    }))
    .sort((left, right) => left.label.localeCompare(right.label))
})

const favoriteItemOptions = computed(() => {
  const groupedOptions = new Map()

  favoriteItemReferences.value.forEach(item => {
    const name = String(item?.name ?? '').trim()
    if (!name) return

    const label = String(item?.type ?? '').trim() || 'Other'
    const current = groupedOptions.get(label) ?? []

    if (!current.includes(name)) {
      current.push(name)
    }

    groupedOptions.set(label, current)
  })

  return Array.from(groupedOptions.entries())
    .map(([label, options]) => ({
      label,
      options: options.sort((left, right) => left.localeCompare(right)),
    }))
    .sort((left, right) => left.label.localeCompare(right.label))
})

const displayName = computed(() => {
  const u = me.value
  return u?.rsi_handle ?? u?.discord_name ?? u?.name ?? 'Member'
})

const displayedSquadrons = computed(() => {
  return Array.isArray(me.value?.squadrons) ? me.value.squadrons : []
})

const rankName = computed(() => {
  const u = me.value
  return u?.rank_name ?? u?.rank ?? 'Unknown'
})

const rolesLabel = computed(() => {
  const roles = me.value?.roles ?? inertiaUser.value?.roles ?? []
  if (!roles.length) return 'None'
  return roles.map(r => r?.name ?? r?.slug).filter(Boolean).join(', ')
})

const displayNameColor = computed(() => {
  const u = me.value ?? {}
  const slug = getHighestOrgRoleSlug(u?.roles, u?.rank)
  return getOrgRoleColor(slug)
})

const canViewRestrictedOperationStats = computed(() => {
  return isAdmiralPlus(inertiaUser.value)
})

const favoriteShipsLabel = computed(() => {
  const ships = me.value?.favorite_ships
  if (!Array.isArray(ships) || !ships.length) return 'None'
  return ships.join(', ')
})

const favoriteGunsLabel = computed(() => {
  const guns = me.value?.favorite_guns
  if (!Array.isArray(guns) || !guns.length) return 'None'
  return guns.join(', ')
})

function normalizeProfileArray(value) {
  if (!Array.isArray(value)) return []

  return value
    .map(item => String(item ?? '').trim())
    .filter(Boolean)
}

function profilePreviewList(value, max = 8) {
  const list = normalizeProfileArray(value)

  return {
    items: list.slice(0, max),
    moreCount: Math.max(0, list.length - max),
    hasItems: list.length > 0,
  }
}

const primaryRoleLabel = computed(() => {
  const value = me.value?.primary_role
  const found = roleOptions.find(o => o.value === value)
  return found?.label ?? (value || 'Not set')
})

const secondaryRoleLabel = computed(() => {
  const value = me.value?.secondary_role
  const found = roleOptions.find(o => o.value === value)
  return found?.label ?? (value || 'Not set')
})

const gameplayStyleLabel = computed(() => {
  const value = me.value?.preferred_gameplay_style
  const found = gameplayStyleOptions.find(o => o.value === value)
  return found?.label ?? (value || 'Not set')
})

const opCommitmentLabel = computed(() => {
  const value = me.value?.typical_op_commitment
  const found = opCommitmentOptions.find(o => o.value === value)
  return found?.label ?? (value || 'Not set')
})

const experienceRatings = computed(() => {
  const incoming = me.value?.experience_ratings
  return normalizeExperienceRatings(incoming)
})

function toggleFavoriteShip(ship) {
  const current = Array.isArray(form.value.favorite_ships) ? form.value.favorite_ships : []
  if (current.includes(ship)) {
    form.value.favorite_ships = current.filter(s => s !== ship)
    return
  }

  form.value.favorite_ships = [...current, ship]
}

function removeFavoriteShip(ship) {
  const current = Array.isArray(form.value.favorite_ships) ? form.value.favorite_ships : []
  form.value.favorite_ships = current.filter(s => s !== ship)
}

function toggleFavoriteItem(item) {
  const current = Array.isArray(form.value.favorite_guns) ? form.value.favorite_guns : []
  if (current.includes(item)) {
    form.value.favorite_guns = current.filter(entry => entry !== item)
    return
  }

  form.value.favorite_guns = [...current, item]
}

function removeFavoriteItem(item) {
  const current = Array.isArray(form.value.favorite_guns) ? form.value.favorite_guns : []
  form.value.favorite_guns = current.filter(entry => entry !== item)
}

const filteredFavoriteShipOptions = computed(() => {
  const query = String(favoriteShipSearch.value ?? '').trim().toLowerCase()
  if (!query) return favoriteShipOptions.value

  return favoriteShipOptions.value
    .map(group => {
      const options = (group.options ?? []).filter(ship => String(ship).toLowerCase().includes(query))
      return { ...group, options }
    })
    .filter(group => group.options.length)
})

const filteredFavoriteItemOptions = computed(() => {
  const query = String(favoriteItemSearch.value ?? '').trim().toLowerCase()
  if (!query) return favoriteItemOptions.value

  return favoriteItemOptions.value
    .map(group => {
      const options = (group.options ?? []).filter(item => String(item).toLowerCase().includes(query))
      return { ...group, options }
    })
    .filter(group => group.options.length)
})

function setExperienceRating(key, value) {
  form.value.experience_ratings = {
    ...(form.value.experience_ratings ?? {}),
    [key]: value,
  }
}

function normalizeExperienceRatings(source) {
  const incoming = source && typeof source === 'object' ? source : {}

  return {
    space_combat: incoming.space_combat ?? null,
    ground_combat: incoming.ground_combat ?? null,
    logistics_support: incoming.logistics_support ?? null,
    medical: incoming.medical ?? null,
  }
}

const operationsStats = computed(() => {
  const u = me.value ?? {}
  return {
    created: u?.operations_created_count ?? 0,
    canceled: u?.operations_canceled_count ?? 0,
    success: u?.operations_success_count ?? 0,
    failed: u?.operations_failed_count ?? 0,
    joined: u?.operations_joined_count ?? 0,
    completed: u?.operations_completed_count ?? 0,
    noShow: u?.operations_no_show_count ?? 0,
    leftEarly: u?.operations_left_early_count ?? 0,
  }
})

const promotionPanel = computed(() => me.value?.promotion ?? null)
const activePromotionOffer = computed(() => promotionPanel.value?.active_offer ?? null)
const selectedPromotionBranch = computed(() => {
  const options = promotionPanel.value?.allowed_branch_roles ?? []
  return options.find(option => option.id === selectedPromotionBranchRoleId.value) ?? null
})
const canOpenPromotionConfirm = computed(() => {
  return Boolean(
    promotionPanel.value?.can_create
    && selectedPromotionBranch.value
    && promotionBasePath.value
  )
})
const promotionBasePath = computed(() => {
  const handle = me.value?.rsi_handle
  if (!handle) return null
  return `/user/${encodeURIComponent(handle)}`
})

function extractFirstErrorMessage(errors, fallback) {
  return extractSharedErrorMessage(errors, fallback)
}

function seedFormFromUser(user) {
  form.value = {
    bio: user?.bio ?? '',
    timezone: user?.timezone ?? '',
    region: user?.region ?? '',
    availability_status: user?.availability_status ?? '',
    loa_note: user?.loa_note ?? '',
    favorite_ships: Array.isArray(user?.favorite_ships) ? user.favorite_ships : [],
    favorite_guns: Array.isArray(user?.favorite_guns) ? user.favorite_guns : [],
    callsign: user?.callsign ?? '',
    primary_role: user?.primary_role ?? '',
    secondary_role: user?.secondary_role ?? '',
    preferred_gameplay_style: user?.preferred_gameplay_style ?? '',
    typical_op_commitment: user?.typical_op_commitment ?? '',
    experience_ratings: normalizeExperienceRatings(user?.experience_ratings),
  }
}

async function saveProfile() {
  if (!canEditProfile.value) return
  isSaving.value = true
  errorMessage.value = null

  router.put(route('me.update'), {
      bio: form.value.bio,
      timezone: form.value.timezone,
      region: form.value.region,
      availability_status: form.value.availability_status,
      loa_note: form.value.loa_note,
      favorite_ships: form.value.favorite_ships,
      favorite_guns: form.value.favorite_guns,
      callsign: form.value.callsign,
      primary_role: form.value.primary_role,
      secondary_role: form.value.secondary_role,
      preferred_gameplay_style: form.value.preferred_gameplay_style,
      typical_op_commitment: form.value.typical_op_commitment,
      experience_ratings: form.value.experience_ratings,
    }, {
      preserveScroll: true,
      onSuccess: (page) => {
        me.value = page?.props?.profileUser ?? page?.props?.auth?.user ?? me.value
        seedFormFromUser(me.value)
        isEditing.value = false
      },
      onError: (errors) => {
        errorMessage.value = extractFirstErrorMessage(errors, 'Failed to save profile.')
      },
      onFinish: () => {
        isSaving.value = false
      },
    })
}

function startEdit() {
  if (!canEditProfile.value) return
  seedFormFromUser(me.value ?? inertiaUser.value)
  favoriteShipSearch.value = ''
  favoriteItemSearch.value = ''
  isEditing.value = true
}

function cancelEdit() {
  seedFormFromUser(me.value ?? inertiaUser.value)
  favoriteShipSearch.value = ''
  favoriteItemSearch.value = ''
  isEditing.value = false
}

function pageHasEditQuery() {
  const rawUrl = String(page.url ?? '')

  try {
    const resolved = new URL(rawUrl, window.location.origin)
    return resolved.searchParams.get('edit') === '1'
  } catch {
    return false
  }
}

function consumeEditQuery() {
  try {
    const resolved = new URL(window.location.href)

    if (!resolved.searchParams.has('edit')) {
      return
    }

    resolved.searchParams.delete('edit')
    const nextUrl = `${resolved.pathname}${resolved.search}${resolved.hash}`
    window.history.replaceState(window.history.state, '', nextUrl)
  } catch {
    // Best effort only.
  }
}

function maybeOpenEditorFromQuery() {
  if (!canEditProfile.value || isEditing.value || !pageHasEditQuery()) {
    return
  }

  startEdit()
  consumeEditQuery()
}

function applyUpdatedProfile(pagePayload) {
  me.value = pagePayload?.props?.profileUser ?? pagePayload?.props?.auth?.user ?? me.value
  selectedPromotionBranchRoleId.value = ''
}

function togglePromotionWorkflow() {
  isPromotionWorkflowOpen.value = !isPromotionWorkflowOpen.value
}

function openPromotionConfirm() {
  if (!canOpenPromotionConfirm.value) return
  promotionErrorMessage.value = null
  isPromotionConfirmOpen.value = true
}

function closePromotionConfirm() {
  if (isSubmittingPromotion.value) return
  isPromotionConfirmOpen.value = false
}

function submitPromotionOffer() {
  if (!canOpenPromotionConfirm.value || !promotionBasePath.value) return

  isSubmittingPromotion.value = true
  promotionErrorMessage.value = null

  router.post(`${promotionBasePath.value}/promotion-offers`, {
    branch_role_id: selectedPromotionBranchRoleId.value,
  }, {
    preserveScroll: true,
    onSuccess: (pagePayload) => {
      applyUpdatedProfile(pagePayload)
      isPromotionConfirmOpen.value = false
    },
    onError: (errors) => {
      promotionErrorMessage.value = extractFirstErrorMessage(errors, 'Failed to send promotion offer.')
    },
    onFinish: () => {
      isSubmittingPromotion.value = false
    },
  })
}

function cancelPromotionOffer() {
  if (!activePromotionOffer.value?.id || !promotionBasePath.value || isCancellingPromotion.value) return
  if (!window.confirm('Cancel this pending promotion offer?')) return

  isCancellingPromotion.value = true
  promotionErrorMessage.value = null

  router.post(`${promotionBasePath.value}/promotion-offers/${activePromotionOffer.value.id}/cancel`, {}, {
    preserveScroll: true,
    onSuccess: (pagePayload) => {
      applyUpdatedProfile(pagePayload)
    },
    onError: (errors) => {
      promotionErrorMessage.value = extractFirstErrorMessage(errors, 'Failed to cancel promotion offer.')
    },
    onFinish: () => {
      isCancellingPromotion.value = false
    },
  })
}

function demoteProfileMember() {
  if (!promotionPanel.value?.can_demote || !promotionBasePath.value || isDemotingMember.value) return
  if (!window.confirm('Demote this member back to Member?')) return

  isDemotingMember.value = true
  promotionErrorMessage.value = null

  router.post(`${promotionBasePath.value}/demote`, {}, {
    preserveScroll: true,
    onSuccess: (pagePayload) => {
      applyUpdatedProfile(pagePayload)
    },
    onError: (errors) => {
      promotionErrorMessage.value = extractFirstErrorMessage(errors, 'Failed to demote this member.')
    },
    onFinish: () => {
      isDemotingMember.value = false
    },
  })
}

onMounted(() => {
  seedFormFromUser(me.value)
  maybeOpenEditorFromQuery()
})

watch(
  () => profileUser.value,
  (next) => {
    me.value = next ?? inertiaUser.value
    isEditing.value = false
    errorMessage.value = null
    promotionErrorMessage.value = null
    isPromotionWorkflowOpen.value = false
    isPromotionConfirmOpen.value = false
    selectedPromotionBranchRoleId.value = ''
    seedFormFromUser(me.value)
  }
)

watch(
  () => page.url,
  () => {
    maybeOpenEditorFromQuery()
  }
)
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-8">
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] p-6 ">
        <div class="pointer-events-none absolute inset-0 opacity-20">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
              Horizon Personnel Dossier
            </div>

            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
              {{ canEditProfile ? 'My Profile' : 'Personnel File' }}
            </h1>

            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              Identity, readiness, availability, squadron assignment, equipment preferences, and member details in one continuous flow.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ isEditing ? 'Editing In Place' : canEditProfile ? 'Editable Profile' : 'Read Only' }}
              </span>

              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ rankName }}
              </span>

              <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
                {{ rolesLabel }}
              </span>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            <HorizonButton
              v-if="canEditProfile && !isEditing"
              variant="primary"
              size="sm"
              @click="startEdit"
            >
              Edit Profile
            </HorizonButton>

            <div
              v-else-if="canEditProfile"
              class="hz-surface-welcome rounded-2xl border border-white/[0.055] px-4 py-3 text-left"
            >
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Draft Session
              </div>

              <div class="mt-1 text-sm font-semibold text-horizon-white">
                Editing across profile sections
              </div>
            </div>
          </div>
        </div>
      </section>

      <section
        v-if="isEditing"
        class="static z-20 hz-surface-welcome rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/25 p-4 shadow-[0_24px_80px_rgba(0,0,0,0.35)] backdrop-blur md:sticky md:top-4"
      >
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Profile Draft
            </div>

            <p class="mt-1 text-sm text-text-secondary">
              Make changes anywhere on the page, then save once when everything looks right.
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <HorizonButton
              variant="ghost"
              size="sm"
              :disabled="isSaving"
              @click="cancelEdit"
            >
              Cancel
            </HorizonButton>

            <HorizonButton
              variant="primary"
              size="sm"
              :disabled="isSaving"
              @click="saveProfile"
            >
              {{ isSaving ? 'Saving…' : 'Save Changes' }}
            </HorizonButton>
          </div>
        </div>
      </section>

      <div
        v-if="errorMessage"
        class="rounded-[1.5rem] border border-red-300/25 bg-red-300/10 p-4 text-sm font-semibold text-red-100"
      >
        {{ errorMessage }}
      </div>

      <div
        v-if="promotionErrorMessage"
        class="rounded-[1.5rem] border border-amber-300/25 bg-amber-300/10 p-4 text-sm font-semibold text-amber-100"
      >
        {{ promotionErrorMessage }}
      </div>

      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055]">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px opacity-30">
          <div class="absolute left-10 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        </div>

        <div class="relative divide-y divide-white/[0.055]">
          <section class="p-6">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
              <div class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-start">
                <div class="relative shrink-0">
                  <img
                    v-if="me?.discord_avatar"
                    :src="me.discord_avatar"
                    alt=""
                    class="relative h-32 w-32 rounded-[1.7rem] border border-white/[0.055] object-cover"
                  />

                  <div
                    v-else
                    :style="displayNameColor ? { color: displayNameColor } : undefined"
                    class="relative flex h-32 w-32 items-center justify-center rounded-[1.7rem] border border-white/[0.055] bg-white/[0.042] text-4xl font-black"
                  >
                    {{ String(displayName).slice(0, 1).toUpperCase() }}
                  </div>
                </div>

                <div class="min-w-0">
                  <div class="text-xs font-bold uppercase tracking-[0.22em] text-text-muted">
                    Registered Member
                  </div>

                  <div
                    class="mt-2 truncate text-3xl font-black tracking-tight text-horizon-white md:text-5xl"
                    :style="displayNameColor ? { color: displayNameColor } : undefined"
                  >
                    {{ displayName }}
                  </div>

                  <div class="mt-3 flex flex-wrap gap-2">
                    <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                      Rank {{ rankName }}
                    </span>

                    <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                      {{ primaryRoleLabel }}
                    </span>

                    <span class="rounded-full border-transparent bg-white/[0.042] px-3 py-1 text-xs font-semibold text-text-secondary">
                      {{ opCommitmentLabel }}
                    </span>
                  </div>

                  <div class="mt-4 max-w-2xl text-sm text-text-secondary">
                    Roles:
                    <span class="text-horizon-white">{{ rolesLabel }}</span>
                  </div>

                  <div v-if="promotionPanel?.show_panel" class="mt-5 max-w-2xl">
                    <div class="flex flex-wrap items-center gap-3">
                      <HorizonButton
                        variant="primary"
                        size="sm"
                        @click="togglePromotionWorkflow"
                      >
                        Promote
                      </HorizonButton>

                      <span
                        v-if="activePromotionOffer"
                        class="rounded-full border border-[color:var(--horizon-sunset-blue)]/20 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-horizon-white"
                      >
                        Offer Active
                      </span>
                    </div>

                    <div
                      v-if="isPromotionWorkflowOpen"
                      class="mt-4 rounded-[1.5rem] border border-white/[0.055] bg-white/[0.03] p-4"
                    >
                      <div class="flex flex-wrap items-start justify-end gap-3">
                        <HorizonButton
                          variant="ghost"
                          size="sm"
                          @click="togglePromotionWorkflow"
                        >
                          Close
                        </HorizonButton>
                      </div>

                      <div
                        v-if="activePromotionOffer"
                        class="mt-4 rounded-[1.25rem] border border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.03] p-4"
                      >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                              Pending Offer
                            </div>

                            <div class="mt-1 text-sm font-semibold text-horizon-white">
                              {{ activePromotionOffer.to_rank.label }} · {{ activePromotionOffer.branch_role_label || 'Branch pending' }}
                            </div>
                          </div>

                          <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                            {{ activePromotionOffer.state }}
                          </span>
                        </div>

                        <div class="mt-4 space-y-2 text-sm text-text-secondary">
                          <div>
                            Promoter:
                            <span class="text-horizon-white">{{ activePromotionOffer.promoter.name }}</span>
                          </div>

                          <div>
                            Expires:
                            <span class="text-horizon-white">{{ activePromotionOffer.expires_at_label }}</span>
                          </div>
                        </div>

                        <div
                          v-if="promotionPanel?.can_cancel"
                          class="mt-4"
                        >
                          <HorizonButton
                            variant="ghost"
                            size="sm"
                            :disabled="isCancellingPromotion"
                            @click="cancelPromotionOffer"
                          >
                            {{ isCancellingPromotion ? 'Cancelling…' : 'Cancel Offer' }}
                          </HorizonButton>
                        </div>
                      </div>

                      <div
                        v-else
                        class="mt-4 space-y-4"
                      >
                        <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.03] p-4">
                          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                            Next Eligible Rank
                          </div>

                          <div class="mt-1 text-sm font-semibold text-horizon-white">
                            {{ promotionPanel?.next_rank?.label || 'No controlled next rank' }}
                          </div>

                          <div
                            v-if="promotionPanel?.gating_reason"
                            class="mt-3 rounded-2xl border border-amber-300/20 bg-amber-300/5 p-3 text-sm text-amber-100"
                          >
                            {{ promotionPanel.gating_reason }}
                          </div>
                        </div>

                        <div
                          v-if="promotionPanel?.can_create"
                          class="space-y-3 rounded-[1.25rem] border border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.03] p-4"
                        >
                          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                            Branch Selection
                          </div>

                          <HorizonInput
                            v-model="selectedPromotionBranchRoleId"
                            type="select"
                            label=""
                            :options="[
                              { label: 'Select branch role', value: '' },
                              ...(promotionPanel?.allowed_branch_roles ?? []).map(option => ({
                                label: option.label,
                                value: option.id,
                              })),
                            ]"
                          />

                          <HorizonButton
                            variant="primary"
                            size="sm"
                            :disabled="!canOpenPromotionConfirm || isSubmittingPromotion"
                            @click="openPromotionConfirm"
                          >
                            Review Offer
                          </HorizonButton>
                        </div>

                        <div
                          v-if="promotionPanel?.can_demote"
                          class="rounded-[1.25rem] border border-red-300/20 bg-red-300/5 p-4"
                        >
                          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                            Demotion
                          </div>

                          <p class="mt-2 text-sm text-text-secondary">
                            Demotion is a direct action and always returns the member to Member.
                          </p>

                          <div class="mt-4">
                            <HorizonButton
                              variant="ghost"
                              size="sm"
                              :disabled="isDemotingMember"
                              @click="demoteProfileMember"
                            >
                              {{ isDemotingMember ? 'Demoting…' : 'Demote to Member' }}
                            </HorizonButton>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="space-y-3">
                <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                  <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                    Callsign
                  </div>

                  <div v-if="isEditing && canEditProfile" class="mt-3">
                    <HorizonInput
                      v-model="form.callsign"
                      label=""
                      placeholder="Your callsign"
                    />
                  </div>

                  <div v-else class="mt-2 text-sm font-semibold text-horizon-white">
                    {{ me?.callsign || 'Not set' }}
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                      Readiness
                    </div>

                    <div class="mt-1 text-sm font-semibold text-horizon-white">
                      {{ primaryRoleLabel }}
                    </div>
                  </div>

                  <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                      Commitment
                    </div>

                    <div class="mt-1 text-sm font-semibold text-horizon-white">
                      {{ opCommitmentLabel }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="hz-surface-welcome relative mt-6 rounded-[1.5rem] border border-white/[0.055] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Member Bio
              </div>

              <div v-if="isEditing && canEditProfile" class="mt-3">
                <HorizonInput
                  v-model="form.bio"
                  type="textarea"
                  label=""
                  placeholder="Tell the org a little about you…"
                />
              </div>

              <p
                v-else-if="me?.bio"
                class="mt-2 whitespace-pre-line text-sm leading-7 text-text-secondary"
              >
                {{ me.bio }}
              </p>

              <div
                v-else
                class="mt-3 rounded-2xl border border-dashed border-white/15 p-4 text-sm text-text-secondary"
              >
                No bio added yet.
              </div>
            </div>
          </section>

          <section class="px-6 py-6">
            <div class="mb-5">
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Readiness Profile
              </div>

              <h2 class="mt-1 text-xl font-black text-horizon-white">
                Role + Experience
              </h2>

              <p class="mt-1 text-sm text-text-secondary">
                Preferred roles, gameplay style, commitment level, and self-rated experience.
              </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Primary Role
                </div>

                <div v-if="isEditing && canEditProfile" class="mt-3">
                  <HorizonInput
                    v-model="form.primary_role"
                    type="select"
                    label=""
                    :options="roleOptions"
                  />
                </div>

                <div v-else class="mt-2 text-sm font-semibold text-horizon-white">
                  {{ primaryRoleLabel }}
                </div>
              </div>

              <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Secondary Role
                </div>

                <div v-if="isEditing && canEditProfile" class="mt-3">
                  <HorizonInput
                    v-model="form.secondary_role"
                    type="select"
                    label=""
                    :options="roleOptions"
                  />
                </div>

                <div v-else class="mt-2 text-sm font-semibold text-horizon-white">
                  {{ secondaryRoleLabel }}
                </div>
              </div>

              <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Gameplay Style
                </div>

                <div v-if="isEditing && canEditProfile" class="mt-3">
                  <HorizonInput
                    v-model="form.preferred_gameplay_style"
                    type="select"
                    label=""
                    :options="gameplayStyleOptions"
                  />
                </div>

                <div v-else class="mt-2 text-sm font-semibold text-horizon-white">
                  {{ gameplayStyleLabel }}
                </div>
              </div>

              <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Operation Commitment
                </div>

                <div v-if="isEditing && canEditProfile" class="mt-3">
                  <HorizonInput
                    v-model="form.typical_op_commitment"
                    type="select"
                    label=""
                    :options="opCommitmentOptions"
                  />
                </div>

                <div v-else class="mt-2 text-sm font-semibold text-horizon-white">
                  {{ opCommitmentLabel }}
                </div>
              </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <div
                v-for="item in [
                  { key: 'space_combat', label: 'Space Combat' },
                  { key: 'ground_combat', label: 'Ground Combat' },
                  { key: 'logistics_support', label: 'Logistics / Support' },
                  { key: 'medical', label: 'Medical' },
                ]"
                :key="item.key"
                class="rounded-2xl border border-white/10 bg-black/10 p-4"
              >
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  {{ item.label }}
                </div>

                <div v-if="isEditing && canEditProfile" class="mt-3 flex gap-2">
                  <button
                    v-for="n in 5"
                    :key="item.key + '_' + n"
                    type="button"
                    class="h-4 w-4 rounded-full border border-[color:var(--horizon-sunset-blue)]/50 transition"
                    :class="(form.experience_ratings?.[item.key] ?? 0) >= n ? 'bg-[color:var(--horizon-sunset-blue)]' : 'bg-transparent'"
                    @click="setExperienceRating(item.key, n)"
                  />
                </div>

                <div v-else class="mt-2 text-2xl font-black text-horizon-white">
                  {{ experienceRatings[item.key] ?? '—' }}
                </div>
              </div>
            </div>

            <div
              v-if="isEditing && canEditProfile"
              class="mt-4 text-sm text-text-muted"
            >
              Click dots to set rating from 1–5.
            </div>
          </section>

          <section class="px-6 py-6">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
              <div>
                <div class="mb-5">
                  <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                    Availability
                  </div>

                  <h2 class="mt-1 text-xl font-black text-horizon-white">
                    Time + Availability
                  </h2>

                  <p class="mt-1 text-sm text-text-secondary">
                    Region, current timezone, general availability, and any active leave notes.
                  </p>
                </div>

                <div class="grid gap-4 md:grid-cols-[12rem_minmax(0,1.5fr)_minmax(0,1fr)]">
                  <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Region
                    </div>

                    <div v-if="isEditing && canEditProfile" class="mt-3">
                      <HorizonInput
                        v-model="form.region"
                        type="select"
                        label=""
                        :options="[
                          { label: 'Select region', value: '' },
                          ...regionOptions,
                        ]"
                      />
                    </div>

                    <div v-else class="mt-2 text-sm font-semibold text-horizon-white">
                      {{ me?.region || 'Not set' }}
                    </div>
                  </div>

                  <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Timezone
                    </div>

                    <div v-if="isEditing && canEditProfile" class="mt-3">
                      <HorizonInput
                        v-model="form.timezone"
                        type="select"
                        label=""
                        :options="[
                          { label: 'Select timezone', value: '' },
                          ...timezoneOptions,
                        ]"
                      />
                    </div>

                    <div v-else class="mt-2 text-sm font-semibold text-horizon-white">
                      {{ me?.timezone || 'Not set' }}
                    </div>
                  </div>

                  <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Availability
                    </div>

                    <div v-if="isEditing && canEditProfile" class="mt-3">
                      <HorizonInput
                        v-model="form.availability_status"
                        label=""
                        placeholder="e.g. Evenings, Weekends, On-call"
                      />
                    </div>

                    <div v-else class="mt-2 text-sm font-semibold text-horizon-white">
                      {{ me?.availability_status || 'Not set' }}
                    </div>
                  </div>
                </div>

                <div class="hz-surface-welcome mt-4 rounded-[1.5rem] border border-white/[0.055] p-4">
                  <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    LOA Note
                  </div>

                  <div v-if="isEditing && canEditProfile" class="mt-3">
                    <HorizonInput
                      v-model="form.loa_note"
                      type="textarea"
                      label=""
                      placeholder="Optional: leave of absence notes…"
                    />
                  </div>

                  <p
                    v-else-if="me?.loa_note"
                    class="mt-2 whitespace-pre-line text-sm leading-6 text-text-secondary"
                  >
                    {{ me.loa_note }}
                  </p>

                  <div
                    v-else
                    class="mt-3 rounded-2xl border border-dashed border-white/15 p-4 text-sm text-text-secondary"
                  >
                    No leave note on file.
                  </div>
                </div>
              </div>

              <aside class="space-y-6">
                <div>
                  <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                    Squadron Assignment
                  </div>

                  <div class="mt-4 space-y-2">
                    <div
                      v-for="squadron in displayedSquadrons"
                      :key="squadron.id"
                      class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4"
                    >
                      <div class="text-sm font-bold text-horizon-white">
                        {{ squadron.name }}
                      </div>

                      <div class="mt-1 text-xs text-text-secondary">
                        {{ squadron.pivot?.role ?? 'member' }}
                        <span v-if="squadron.pivot?.membership_status">
                          · {{ squadron.pivot.membership_status }}
                        </span>
                      </div>
                    </div>

                    <div
                      v-if="!displayedSquadrons.length"
                      class="hz-surface-welcome rounded-2xl border border-dashed border-white/15 p-4 text-sm text-text-secondary"
                    >
                      No squadron assignment found.
                    </div>
                  </div>
                </div>
              </aside>
            </div>
          </section>

          <section class="px-6 py-6">
            <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Equipment Profile
                </div>

                <h2 class="mt-1 text-xl font-black text-horizon-white">
                  Preferred Ships + Weapons
                </h2>

                <p class="mt-1 text-sm text-text-secondary">
                  Personal loadout preferences and favorite equipment for mission planning.
                </p>
              </div>

              <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] px-4 py-3">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Loadout
                </div>

                <div class="mt-1 text-sm font-semibold text-horizon-white">
                  {{ normalizeProfileArray(isEditing ? form.favorite_ships : me?.favorite_ships).length }} Ships · {{ normalizeProfileArray(isEditing ? form.favorite_guns : me?.favorite_guns).length }} Items
                </div>
              </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
              <div class="hz-surface-welcome rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/20 p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                  <div>
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                      Favorite Ships
                    </div>

                    <div class="mt-1 text-sm text-text-secondary">
                      Preferred hulls and vehicle choices.
                    </div>
                  </div>

                  <div class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-bold text-horizon-white">
                    {{ normalizeProfileArray(isEditing ? form.favorite_ships : me?.favorite_ships).length }}
                  </div>
                </div>

                <template v-if="!isEditing">
                  <div
                    v-if="profilePreviewList(me?.favorite_ships).hasItems"
                    class="flex flex-wrap gap-2"
                  >
                    <span
                      v-for="ship in profilePreviewList(me?.favorite_ships).items"
                      :key="ship"
                      class="max-w-full truncate rounded-full border border-white/[0.055] bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary"
                      :title="ship"
                    >
                      {{ ship }}
                    </span>

                    <span
                      v-if="profilePreviewList(me?.favorite_ships).moreCount > 0"
                      class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-semibold text-horizon-white"
                    >
                      +{{ profilePreviewList(me?.favorite_ships).moreCount }} more
                    </span>
                  </div>

                  <div
                    v-else
                    class="hz-surface-welcome rounded-2xl border border-dashed border-white/15 p-4 text-sm text-text-secondary"
                  >
                    No favorite ships listed.
                  </div>
                </template>

                <div v-else class="space-y-4">
                  <input
                    v-model="favoriteShipSearch"
                    type="text"
                    class="hz-input"
                    placeholder="Search ships…"
                  />

                  <div v-if="form.favorite_ships?.length" class="flex flex-wrap gap-2">
                    <button
                      v-for="ship in form.favorite_ships"
                      :key="ship"
                      type="button"
                      class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-cyan-100 transition hover:bg-[color:var(--horizon-sunset-blue)]/20"
                      :title="'Remove ' + ship"
                      @click="removeFavoriteShip(ship)"
                    >
                      {{ ship }} ✕
                    </button>
                  </div>

                  <div class="max-h-80 overflow-auto rounded-2xl border border-white/10 bg-black/20 p-3">
                    <div
                      v-for="group in filteredFavoriteShipOptions"
                      :key="group.label"
                      class="mb-4 last:mb-0"
                    >
                      <div class="mb-2 text-xs font-bold uppercase tracking-wide text-text-muted">
                        {{ group.label }}
                      </div>

                      <div class="grid grid-cols-1 gap-2">
                        <button
                          v-for="ship in group.options"
                          :key="group.label + '::' + ship"
                          type="button"
                          class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-sm transition"
                          :class="
                            form.favorite_ships?.includes(ship)
                              ? 'border-[color:var(--horizon-sunset-blue)]/35 bg-white/[0.042] text-horizon-white'
                              : 'border-white/[0.055] bg-white/[0.024] text-text-secondary hover:border-white/[0.055] hover:bg-white/[0.05] hover:text-horizon-white'
                          "
                          @click="toggleFavoriteShip(ship)"
                        >
                          <span class="truncate">{{ ship }}</span>
                          <span v-if="form.favorite_ships?.includes(ship)" class="shrink-0 text-xs text-cyan-200">Selected</span>
                        </button>
                      </div>
                    </div>

                    <div v-if="!filteredFavoriteShipOptions.length" class="text-sm text-text-muted">
                      {{ favoriteShipOptions.length ? 'No ships match your search.' : 'No synced UEX ships are available yet.' }}
                    </div>
                  </div>

                  <div class="text-sm text-text-muted">
                    Tap to select or unselect. Tap a tag above to remove.
                  </div>
                </div>
              </div>

              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                  <div>
                    <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                      Favorite Items
                    </div>

                    <div class="mt-1 text-sm text-text-secondary">
                      Preferred armors, weapons, tools, and items.
                    </div>
                  </div>

                  <div class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-bold text-horizon-white">
                    {{ normalizeProfileArray(isEditing ? form.favorite_guns : me?.favorite_guns).length }}
                  </div>
                </div>

                <template v-if="!isEditing">
                  <div
                    v-if="profilePreviewList(me?.favorite_guns).hasItems"
                    class="flex flex-wrap gap-2"
                  >
                    <span
                      v-for="item in profilePreviewList(me?.favorite_guns).items"
                      :key="item"
                      class="max-w-full truncate rounded-full border border-white/[0.055] bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary"
                      :title="item"
                    >
                      {{ item }}
                    </span>

                    <span
                      v-if="profilePreviewList(me?.favorite_guns).moreCount > 0"
                      class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-semibold text-horizon-white"
                    >
                      +{{ profilePreviewList(me?.favorite_guns).moreCount }} more
                    </span>
                  </div>

                  <div
                    v-else
                    class="hz-surface-welcome rounded-2xl border border-dashed border-white/15 p-4 text-sm text-text-secondary"
                  >
                    No favorite items listed.
                  </div>
                </template>

                <div v-else class="space-y-4">
                  <input
                    v-model="favoriteItemSearch"
                    type="text"
                    class="hz-input"
                    placeholder="Search items…"
                  />

                  <div v-if="form.favorite_guns?.length" class="flex flex-wrap gap-2">
                    <button
                      v-for="item in form.favorite_guns"
                      :key="item"
                      type="button"
                      class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-fuchsia-100 transition hover:bg-[color:var(--horizon-sunset-magenta)]/20"
                      :title="'Remove ' + item"
                      @click="removeFavoriteItem(item)"
                    >
                      {{ item }} ✕
                    </button>
                  </div>

                  <div class="max-h-80 overflow-auto rounded-2xl border border-white/10 bg-black/20 p-3">
                    <div
                      v-for="group in filteredFavoriteItemOptions"
                      :key="group.label"
                      class="mb-4 last:mb-0"
                    >
                      <div class="mb-2 text-xs font-bold uppercase tracking-wide text-text-muted">
                        {{ group.label }}
                      </div>

                      <div class="grid grid-cols-1 gap-2">
                        <button
                          v-for="item in group.options"
                          :key="group.label + '::' + item"
                          type="button"
                          class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-sm transition"
                          :class="
                            form.favorite_guns?.includes(item)
                              ? 'border-[color:var(--horizon-sunset-magenta)]/35 bg-white/[0.042] text-horizon-white'
                              : 'border-white/[0.055] bg-white/[0.024] text-text-secondary hover:border-white/[0.055] hover:bg-white/[0.05] hover:text-horizon-white'
                          "
                          @click="toggleFavoriteItem(item)"
                        >
                          <span class="truncate">{{ item }}</span>
                          <span v-if="form.favorite_guns?.includes(item)" class="shrink-0 text-xs text-fuchsia-200">Selected</span>
                        </button>
                      </div>
                    </div>

                    <div v-if="!filteredFavoriteItemOptions.length" class="text-sm text-text-muted">
                      {{ favoriteItemOptions.length ? 'No items match your search.' : 'No synced UEX items are available yet.' }}
                    </div>
                  </div>

                  <div class="text-sm text-text-muted">
                    Tap to select or unselect. Tap a tag above to remove.
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section v-if="canViewRestrictedOperationStats" class="px-6 py-6">
            <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Operation Record
                </div>

                <h2 class="mt-1 text-xl font-black text-horizon-white">
                  Mission Activity
                </h2>

                <p class="mt-1 text-sm text-text-secondary">
                  Leadership and participation stats stay ranked and role gated for the same authorized viewers as before.
                </p>
              </div>

              <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] px-4 py-3">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Visibility
                </div>

                <div class="mt-1 text-sm font-semibold text-horizon-white">
                  Admiral View
                </div>
              </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Command Record
                    </div>

                    <div class="mt-1 text-sm text-text-secondary">
                      Leadership outcomes and operation ownership.
                    </div>
                  </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                      Created
                    </div>

                    <div class="mt-2 text-2xl font-black text-horizon-white">
                      {{ operationsStats.created }}
                    </div>
                  </div>

                  <div class="rounded-2xl border border-red-300/15 bg-red-300/5 p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                      Canceled
                    </div>

                    <div class="mt-2 text-2xl font-black text-horizon-white">
                      {{ operationsStats.canceled }}
                    </div>
                  </div>

                  <div class="rounded-2xl border border-emerald-300/15 bg-emerald-300/5 p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                      Success
                    </div>

                    <div class="mt-2 text-2xl font-black text-horizon-white">
                      {{ operationsStats.success }}
                    </div>
                  </div>

                  <div class="rounded-2xl border border-red-300/15 bg-red-300/5 p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                      Failed
                    </div>

                    <div class="mt-2 text-2xl font-black text-horizon-white">
                      {{ operationsStats.failed }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Member Activity
                    </div>

                    <div class="mt-1 text-sm text-text-secondary">
                      Attendance, participation, and reliability signals.
                    </div>
                  </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                      Completed
                    </div>

                    <div class="mt-2 text-2xl font-black text-horizon-white">
                      {{ operationsStats.completed }}
                    </div>
                  </div>

                  <div class="rounded-2xl border border-red-300/15 bg-red-300/5 p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                      No Show
                    </div>

                    <div class="mt-2 text-2xl font-black text-horizon-white">
                      {{ operationsStats.noShow }}
                    </div>
                  </div>

                  <div class="rounded-2xl border border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.03] p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                      Joined
                    </div>

                    <div class="mt-2 text-2xl font-black text-horizon-white">
                      {{ operationsStats.joined }}
                    </div>
                  </div>

                  <div class="rounded-2xl border border-amber-300/15 bg-amber-300/5 p-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                      Left Early
                    </div>

                    <div class="mt-2 text-2xl font-black text-horizon-white">
                      {{ operationsStats.leftEarly }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </section>
    </div>
  </HorizonContainer>

  <HorizonDrawer v-if="isEditing && canEditProfile" close-label="Close profile editor drawer" @close="cancelEdit">
    <template #header>
      <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
        Profile Editor
      </div>

      <div class="mt-1 text-2xl font-black text-horizon-white md:text-3xl">
        {{ displayName }}
      </div>

      <p class="mt-1 text-sm text-text-secondary">
        Update your Horizon profile, readiness details, availability, and equipment preferences in one place.
      </p>
    </template>

    <div class="space-y-6">
      <div
        v-if="errorMessage"
        class="rounded-[1.5rem] border border-red-300/25 bg-red-300/10 p-4 text-sm font-semibold text-red-100"
      >
        {{ errorMessage }}
      </div>

      <section class="hz-surface-welcome rounded-[1.75rem] border border-[color:var(--horizon-sunset-blue)]/20 p-5">
        <div class="mb-5">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Identity
          </div>

          <h3 class="mt-1 text-xl font-black text-horizon-white">
            Callsign + Bio
          </h3>

          <p class="mt-1 text-sm text-text-secondary">
            Set how you show up across the org and give people a quick read on who you are.
          </p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Callsign
            </label>
            <HorizonInput
              v-model="form.callsign"
              label=""
              placeholder="Your callsign"
            />
          </div>

          <div>
            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Member Bio
            </label>
            <HorizonInput
              v-model="form.bio"
              type="textarea"
              label=""
              placeholder="Tell the org a little about you…"
            />
          </div>
        </div>
      </section>

      <section class="hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-5">
        <div class="mb-5">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Readiness
          </div>

          <h3 class="mt-1 text-xl font-black text-horizon-white">
            Roles + Experience
          </h3>

          <p class="mt-1 text-sm text-text-secondary">
            Shape the role mix and experience profile other leaders see.
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Primary Role
            </label>
            <HorizonInput
              v-model="form.primary_role"
              type="select"
              label=""
              :options="roleOptions"
            />
          </div>

          <div>
            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Secondary Role
            </label>
            <HorizonInput
              v-model="form.secondary_role"
              type="select"
              label=""
              :options="roleOptions"
            />
          </div>

          <div>
            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Gameplay Style
            </label>
            <HorizonInput
              v-model="form.preferred_gameplay_style"
              type="select"
              label=""
              :options="gameplayStyleOptions"
            />
          </div>

          <div>
            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Operation Commitment
            </label>
            <HorizonInput
              v-model="form.typical_op_commitment"
              type="select"
              label=""
              :options="opCommitmentOptions"
            />
          </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div
            v-for="item in [
              { key: 'space_combat', label: 'Space Combat' },
              { key: 'ground_combat', label: 'Ground Combat' },
              { key: 'logistics_support', label: 'Logistics / Support' },
              { key: 'medical', label: 'Medical' },
            ]"
            :key="`profile-drawer-rating-${item.key}`"
            class="rounded-2xl border border-white/10 bg-black/10 p-4"
          >
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
              {{ item.label }}
            </div>

            <div class="mt-3 flex gap-2">
              <button
                v-for="n in 5"
                :key="`${item.key}_${n}`"
                type="button"
                class="h-4 w-4 rounded-full border border-[color:var(--horizon-sunset-blue)]/50 transition"
                :class="(form.experience_ratings?.[item.key] ?? 0) >= n ? 'bg-[color:var(--horizon-sunset-blue)]' : 'bg-transparent'"
                @click="setExperienceRating(item.key, n)"
              />
            </div>
          </div>
        </div>

        <div class="mt-4 text-sm text-text-muted">
          Click dots to set rating from 1–5.
        </div>
      </section>

      <section class="hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-5">
        <div class="mb-5">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Availability
          </div>

          <h3 class="mt-1 text-xl font-black text-horizon-white">
            Time + Status
          </h3>

          <p class="mt-1 text-sm text-text-secondary">
            Keep your availability and leave notes current.
          </p>
        </div>

        <div class="grid gap-4">
          <div class="md:col-span-3">
            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              Availability
            </label>
            <HorizonInput
              v-model="form.availability_status"
              label=""
              placeholder="e.g. Evenings, Weekends, On-call"
            />
          </div>

          <div class="md:col-span-3">
            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
              LOA Note
            </label>
            <HorizonInput
              v-model="form.loa_note"
              type="textarea"
              label=""
              placeholder="Optional: leave of absence notes…"
            />
          </div>
        </div>
      </section>

      <section class="hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Favorite Ships
            </div>

            <p class="mt-1 text-sm text-text-secondary">
              Pick the hulls you want leaders to know you prefer flying.
            </p>
          </div>

          <div class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-bold text-horizon-white">
            {{ normalizeProfileArray(form.favorite_ships).length }}
          </div>
        </div>

        <div class="space-y-4">
          <input
            v-model="favoriteShipSearch"
            type="text"
            class="hz-input"
            placeholder="Search ships…"
          />

          <div v-if="form.favorite_ships?.length" class="flex flex-wrap gap-2">
            <button
              v-for="ship in form.favorite_ships"
              :key="`profile-drawer-ship-${ship}`"
              type="button"
              class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-cyan-100 transition hover:bg-[color:var(--horizon-sunset-blue)]/20"
              :title="'Remove ' + ship"
              @click="removeFavoriteShip(ship)"
            >
              {{ ship }} ✕
            </button>
          </div>

          <div class="max-h-80 overflow-auto rounded-2xl border border-white/10 bg-black/20 p-3">
            <div
              v-for="group in filteredFavoriteShipOptions"
              :key="`profile-drawer-group-${group.label}`"
              class="mb-4 last:mb-0"
            >
              <div class="mb-2 text-xs font-bold uppercase tracking-wide text-text-muted">
                {{ group.label }}
              </div>

              <div class="grid grid-cols-1 gap-2">
                <button
                  v-for="ship in group.options"
                  :key="`${group.label}::${ship}`"
                  type="button"
                  class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-sm transition"
                  :class="
                    form.favorite_ships?.includes(ship)
                      ? 'border-[color:var(--horizon-sunset-blue)]/35 bg-white/[0.042] text-horizon-white'
                      : 'border-white/[0.055] bg-white/[0.024] text-text-secondary hover:border-white/[0.055] hover:bg-white/[0.05] hover:text-horizon-white'
                  "
                  @click="toggleFavoriteShip(ship)"
                >
                  <span class="truncate">{{ ship }}</span>
                  <span v-if="form.favorite_ships?.includes(ship)" class="shrink-0 text-xs text-cyan-200">Selected</span>
                </button>
              </div>
            </div>

            <div v-if="!filteredFavoriteShipOptions.length" class="text-sm text-text-muted">
              {{ favoriteShipOptions.length ? 'No ships match your search.' : 'No synced UEX ships are available yet.' }}
            </div>
          </div>
        </div>
      </section>

      <section class="hz-surface-welcome rounded-[1.75rem] border border-white/[0.055] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Favorite Items
            </div>

            <p class="mt-1 text-sm text-text-secondary">
              Preferred armors, weapons, tools, and items.
            </p>
          </div>

          <div class="hz-surface-welcome rounded-full border border-white/[0.055] px-3 py-1 text-xs font-bold text-horizon-white">
            {{ normalizeProfileArray(form.favorite_guns).length }}
          </div>
        </div>

        <div class="space-y-4">
          <input
            v-model="favoriteItemSearch"
            type="text"
            class="hz-input"
            placeholder="Search items…"
          />

          <div v-if="form.favorite_guns?.length" class="flex flex-wrap gap-2">
            <button
              v-for="item in form.favorite_guns"
              :key="`profile-drawer-item-${item}`"
              type="button"
              class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-fuchsia-100 transition hover:bg-[color:var(--horizon-sunset-magenta)]/20"
              :title="'Remove ' + item"
              @click="removeFavoriteItem(item)"
            >
              {{ item }} ✕
            </button>
          </div>

          <div class="max-h-80 overflow-auto rounded-2xl border border-white/10 bg-black/20 p-3">
            <div
              v-for="group in filteredFavoriteItemOptions"
              :key="`profile-drawer-item-group-${group.label}`"
              class="mb-4 last:mb-0"
            >
              <div class="mb-2 text-xs font-bold uppercase tracking-wide text-text-muted">
                {{ group.label }}
              </div>

              <div class="grid grid-cols-1 gap-2">
                <button
                  v-for="item in group.options"
                  :key="`${group.label}::${item}`"
                  type="button"
                  class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2 text-left text-sm transition"
                  :class="
                    form.favorite_guns?.includes(item)
                      ? 'border-[color:var(--horizon-sunset-magenta)]/35 bg-white/[0.042] text-horizon-white'
                      : 'border-white/[0.055] bg-white/[0.024] text-text-secondary hover:border-white/[0.055] hover:bg-white/[0.05] hover:text-horizon-white'
                  "
                  @click="toggleFavoriteItem(item)"
                >
                  <span class="truncate">{{ item }}</span>
                  <span v-if="form.favorite_guns?.includes(item)" class="shrink-0 text-xs text-fuchsia-200">Selected</span>
                </button>
              </div>
            </div>

            <div v-if="!filteredFavoriteItemOptions.length" class="text-sm text-text-muted">
              {{ favoriteItemOptions.length ? 'No items match your search.' : 'No synced UEX items are available yet.' }}
            </div>
          </div>

          <div class="text-sm text-text-muted">
            Tap to select or unselect. Tap a tag above to remove.
          </div>
        </div>
      </section>
    </div>

    <template #footer>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-text-secondary">
          Save once to apply all profile changes together.
        </div>

        <div class="flex flex-wrap gap-2 sm:justify-end">
          <HorizonButton
            variant="ghost"
            size="sm"
            :disabled="isSaving"
            @click="cancelEdit"
          >
            Cancel
          </HorizonButton>

          <HorizonButton
            variant="primary"
            size="sm"
            :disabled="isSaving"
            @click="saveProfile"
          >
            {{ isSaving ? 'Saving…' : 'Save Changes' }}
          </HorizonButton>
        </div>
      </div>
    </template>
  </HorizonDrawer>

  <div
    v-if="isPromotionConfirmOpen && promotionPanel?.next_rank"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/70 px-4 backdrop-blur-sm"
    @click.self="closePromotionConfirm"
  >
    <div class="hz-surface-welcome w-full max-w-xl rounded-[2rem] border border-white/[0.055] p-6 shadow-[0_24px_80px_rgba(0,0,0,0.45)]">
      <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
        Review Promotion Offer
      </div>

      <h2 class="mt-2 text-2xl font-black text-horizon-white">
        {{ displayName }}
      </h2>

      <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-white/[0.055] bg-white/[0.03] p-4">
          <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
            Current Rank
          </div>
          <div class="mt-2 text-sm font-semibold text-horizon-white">
            {{ promotionPanel?.current_rank?.label || rankName }}
          </div>
        </div>

        <div class="rounded-2xl border border-white/[0.055] bg-white/[0.03] p-4">
          <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
            New Rank
          </div>
          <div class="mt-2 text-sm font-semibold text-horizon-white">
            {{ promotionPanel.next_rank.label }}
          </div>
        </div>

        <div class="rounded-2xl border border-white/[0.055] bg-white/[0.03] p-4">
          <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
            Branch
          </div>
          <div class="mt-2 text-sm font-semibold text-horizon-white">
            {{ selectedPromotionBranch?.label || 'Not selected' }}
          </div>
        </div>

        <div class="rounded-2xl border border-white/[0.055] bg-white/[0.03] p-4">
          <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
            Promoter
          </div>
          <div class="mt-2 text-sm font-semibold text-horizon-white">
            {{ inertiaUser?.rsi_handle ?? inertiaUser?.discord_name ?? inertiaUser?.name ?? 'Unknown' }}
          </div>
        </div>
      </div>

      <div class="mt-4 rounded-2xl border border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.03] p-4 text-sm text-text-secondary">
        This offer will remain active for {{ promotionPanel?.expiry_minutes || 240 }} minutes once sent.
      </div>

      <div class="mt-6 flex flex-wrap justify-end gap-2">
        <HorizonButton
          variant="ghost"
          size="sm"
          :disabled="isSubmittingPromotion"
          @click="closePromotionConfirm"
        >
          Back
        </HorizonButton>

        <HorizonButton
          variant="primary"
          size="sm"
          :disabled="!canOpenPromotionConfirm || isSubmittingPromotion"
          @click="submitPromotionOffer"
        >
          {{ isSubmittingPromotion ? 'Sending…' : 'Send Offer' }}
        </HorizonButton>
      </div>
    </div>
  </div>
</template>
