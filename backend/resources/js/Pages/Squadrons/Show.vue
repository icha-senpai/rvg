<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonRichTextEditor from '@/Components/HorizonRichTextEditor.vue'
import MediaPickerModal from '@/Components/MediaPickerModal.vue'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

/* -------------------------------------------------
   Props from Inertia
------------------------------------------------- */
const props = defineProps({
  squadronId: {
    type: Number,
    required: true,
  },
})

const page = usePage()

/* -------------------------------------------------
   Page state
------------------------------------------------- */
const activeAction = ref(null)
const errorMessage = ref(null)

const isEditModalOpen = ref(false)
const isEmblemPickerOpen = ref(false)
const isSavingSettings = ref(false)
const isSavingEmblem = ref(false)

// Confirm dialog refs
const leaveConfirmDialog = ref(null)
const clearEmblemConfirmDialog = ref(null)
const acceptConfirmDialog = ref(null)
const rejectConfirmDialog = ref(null)
const removeConfirmDialog = ref(null)
const promoteConfirmDialog = ref(null)
const demoteConfirmDialog = ref(null)

// Pending member targets for dialogs
const pendingAcceptMember = ref(null)
const pendingRejectMember = ref(null)
const pendingRemoveMember = ref(null)
const pendingPromoteMember = ref(null)
const pendingDemoteMember = ref(null)

const editForm = ref({
  motto: '',
  description: '',
  recruitment_propaganda: '',
})

/* -------------------------------------------------
   Payload
------------------------------------------------- */
const payload = computed(() => page.props?.activeSquadron ?? null)
const squadron = computed(() => payload.value?.squadron ?? null)
const members = computed(() => Array.isArray(payload.value?.members) ? payload.value.members : [])
const viewerMembership = computed(() => payload.value?.viewer_membership ?? null)
const permissions = computed(() => payload.value?.permissions ?? {})

const canEditSquadron = computed(() => permissions.value?.can_update_squadron === true)
const canManageMembers = computed(() => permissions.value?.can_manage_members === true)
const canPromoteMembers = computed(() => permissions.value?.can_promote_lieutenant === true)
const canDemoteMembers = computed(() => permissions.value?.can_demote_lieutenant === true)

/* -------------------------------------------------
   Squadron palette extraction
------------------------------------------------- */
const squadronAccentA = ref('#1E40AF')
const squadronAccentB = ref('#C026D3')

const squadronAccentStyle = computed(() => ({
  '--squadron-accent-a': squadronAccentA.value,
  '--squadron-accent-b': squadronAccentB.value,
  '--squadron-glow-a': hexToRgba(squadronAccentA.value, 0.28),
  '--squadron-glow-b': hexToRgba(squadronAccentB.value, 0.28),
}))

const squadronImageForPalette = computed(() => {
  return squadron.value?.emblem?.medium_url
    ?? squadron.value?.emblem?.url
    ?? squadron.value?.emblem_url
    ?? null
})

watch(
  () => squadronImageForPalette.value,
  async (url) => {
    if (!url) {
      squadronAccentA.value = '#1E40AF'
      squadronAccentB.value = '#C026D3'
      return
    }

    try {
      const colors = await extractTwoImageColors(url)
      squadronAccentA.value = colors[0] ?? '#1E40AF'
      squadronAccentB.value = colors[1] ?? '#C026D3'
    } catch {
      squadronAccentA.value = '#1E40AF'
      squadronAccentB.value = '#C026D3'
    }
  },
  { immediate: true }
)

function hexToRgba(hex, alpha = 1) {
  if (!hex || !String(hex).startsWith('#')) {
    return `rgba(30,64,175,${alpha})`
  }

  const clean = String(hex).replace('#', '')
  const value = clean.length === 3
    ? clean.split('').map(char => char + char).join('')
    : clean

  const number = Number.parseInt(value, 16)
  const r = (number >> 16) & 255
  const g = (number >> 8) & 255
  const b = number & 255

  return `rgba(${r},${g},${b},${alpha})`
}

function rgbToHex(r, g, b) {
  return `#${[r, g, b]
    .map(value => Math.max(0, Math.min(255, value)).toString(16).padStart(2, '0'))
    .join('')}`
}

function colorDistance(a, b) {
  return Math.sqrt(
    Math.pow(a.r - b.r, 2)
    + Math.pow(a.g - b.g, 2)
    + Math.pow(a.b - b.b, 2)
  )
}

function isUsefulColor(r, g, b, a) {
  if (a < 180) return false

  const brightness = (r + g + b) / 3
  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  const saturation = max - min

  if (brightness < 35) return false
  if (brightness > 235) return false
  if (saturation < 24) return false

  return true
}

function quantize(value, step = 24) {
  return Math.round(value / step) * step
}

function extractTwoImageColors(url) {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.crossOrigin = 'anonymous'

    image.onload = () => {
      try {
        const canvas = document.createElement('canvas')
        const size = 80

        canvas.width = size
        canvas.height = size

        const context = canvas.getContext('2d', { willReadFrequently: true })
        if (!context) {
          reject(new Error('Could not create canvas context.'))
          return
        }

        context.drawImage(image, 0, 0, size, size)

        const data = context.getImageData(0, 0, size, size).data
        const buckets = new Map()

        for (let i = 0; i < data.length; i += 4) {
          const r = data[i]
          const g = data[i + 1]
          const b = data[i + 2]
          const a = data[i + 3]

          if (!isUsefulColor(r, g, b, a)) continue

          const qr = quantize(r)
          const qg = quantize(g)
          const qb = quantize(b)
          const key = `${qr},${qg},${qb}`

          const existing = buckets.get(key) ?? {
            r: qr,
            g: qg,
            b: qb,
            count: 0,
          }

          existing.count += 1
          buckets.set(key, existing)
        }

        const ranked = [...buckets.values()]
          .sort((a, b) => b.count - a.count)

        if (!ranked.length) {
          resolve(['#1E40AF', '#C026D3'])
          return
        }

        const first = ranked[0]

        const second = ranked.find(color => colorDistance(first, color) > 80)
          ?? ranked[1]
          ?? { r: 192, g: 38, b: 211 }

        resolve([
          rgbToHex(first.r, first.g, first.b),
          rgbToHex(second.r, second.g, second.b),
        ])
      } catch (error) {
        reject(error)
      }
    }

    image.onerror = () => reject(new Error('Could not load squadron image.'))
    image.src = url
  })
}

/* -------------------------------------------------
   Roster shaping
------------------------------------------------- */
const activeMembers = computed(() =>
  members.value.filter(member => member?.membership_status === 'active')
)

const pendingMembers = computed(() =>
  members.value.filter(member => member?.membership_status === 'pending')
)

const leaderMember = computed(() =>
  members.value.find(member => member?.is_leader || String(member?.role ?? '').toLowerCase() === 'leader') ?? null
)

const lieutenantMembers = computed(() =>
  activeMembers.value.filter(member => member?.is_lieutenant && !member?.is_leader)
)

const regularMembers = computed(() =>
  activeMembers.value.filter(member => !member?.is_leader && !member?.is_lieutenant)
)

const sortedRegularMembers = computed(() => {
  return [...regularMembers.value].sort((a, b) => {
    const aName = memberName(a).toLowerCase()
    const bName = memberName(b).toLowerCase()

    if (aName < bName) return -1
    if (aName > bName) return 1

    return 0
  })
})

const activeMemberCount = computed(() => activeMembers.value.length)
const maxRosterSize = 21

const rosterPercent = computed(() => {
  if (!maxRosterSize) return 0
  return Math.min(100, Math.round((activeMemberCount.value / maxRosterSize) * 100))
})

const canPromoteLieutenant = computed(() => lieutenantMembers.value.length < 2)

const recruitmentStateLabel = computed(() => {
  if (!squadron.value) return 'Unknown'
  return squadron.value?.recruiting ? 'Recruiting Open' : 'Recruiting Closed'
})

const viewerStatusLabel = computed(() => {
  const status = viewerMembership.value?.membership_status
  if (!status) return 'Not a member'
  return formatTitle(status)
})

const leader = computed(() => squadron.value?.leader ?? leaderMember.value?.user ?? null)

const leaderColor = computed(() => {
  const u = leader.value
  const slug = getHighestOrgRoleSlug(u?.roles, u?.rank)
  return getOrgRoleColor(slug)
})

/* -------------------------------------------------
   Display helpers
------------------------------------------------- */
function formatTitle(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return 'Not set'

  return raw
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(word => word ? word.charAt(0).toUpperCase() + word.slice(1) : '')
    .join(' ')
}

function branchLogoSrc(branch) {
  const key = String(branch ?? '').trim().toLowerCase()
  const map = {
    defence: '/images/Horizon_Defence_Logo.png',
    frontiers: '/images/Horizon_Frontiers_Logo.png',
    industries: '/images/Horizon_Industries_logo.png',
    lifelines: '/images/Horizon_Lifeline_logo.png',
  }

  return map[key] ?? null
}

function memberName(member) {
  return member?.user?.rsi_handle ?? member?.user?.display_name ?? member?.user?.name ?? 'Unknown Member'
}

function memberRank(member) {
  return member?.user?.rank ?? 'Rank unknown'
}

function memberRoleLabel(member) {
  if (member?.is_leader || String(member?.role ?? '').toLowerCase() === 'leader') return 'Leader'
  if (member?.is_lieutenant || String(member?.role ?? '').toLowerCase() === 'lieutenant') return 'Lieutenant'
  return formatTitle(member?.role ?? 'member')
}

function memberInitial(member) {
  return String(memberName(member) ?? 'M').slice(0, 1).toUpperCase()
}

function memberAvatar(member) {
  return member?.user?.avatar ?? null
}

function memberNameColor(member) {
  const u = member?.user ?? null
  const slug = getHighestOrgRoleSlug(u?.roles, u?.rank)
  return getOrgRoleColor(slug)
}

function memberProfileHref(member) {
  const user = member?.user
  if (!user?.id) return null
  return user?.rsi_handle ? route('member.profile', user.rsi_handle) : `/user/${user.id}`
}

function memberUserId(member) {
  return member?.user_id ?? member?.user?.id ?? member?.id ?? null
}

function isMemberBusy(member, action) {
  return activeAction.value === `${action}:${member?.id}`
}

/* -------------------------------------------------
   Squadron edit
------------------------------------------------- */
function seedEditForm() {
  editForm.value = {
    motto: squadron.value?.motto ?? '',
    description: squadron.value?.description ?? '',
    recruitment_propaganda: squadron.value?.recruitment_propaganda ?? '',
  }
}

function openEditModal() {
  if (!canEditSquadron.value) return
  seedEditForm()
  errorMessage.value = null
  isEditModalOpen.value = true
}

function closeEditModal() {
  if (isSavingSettings.value || isSavingEmblem.value) return
  isEditModalOpen.value = false
  isEmblemPickerOpen.value = false
  seedEditForm()
}

function saveSquadronSettings() {
  if (!squadron.value?.id || !canEditSquadron.value) return

  isSavingSettings.value = true
  errorMessage.value = null

  router.post(
    route('squadrons.settings.update', { squadron: squadron.value.id }),
    {
      motto: editForm.value.motto,
      description: editForm.value.description,
      recruitment_propaganda: editForm.value.recruitment_propaganda,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isEditModalOpen.value = false
      },
      onError: () => {
        errorMessage.value = 'Failed to save squadron settings.'
      },
      onFinish: () => {
        isSavingSettings.value = false
      },
    }
  )
}

function openEmblemPicker() {
  if (!squadron.value?.id || !canEditSquadron.value) return
  isEmblemPickerOpen.value = true
}

function closeEmblemPicker() {
  isEmblemPickerOpen.value = false
}

function setEmblem(media) {
  if (!squadron.value?.id || !canEditSquadron.value) return

  isSavingEmblem.value = true
  errorMessage.value = null

  router.put(
    route('squadrons.emblem.select', { squadron: squadron.value.id }),
    {
      emblem_media_id: media?.id ?? null,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isEmblemPickerOpen.value = false
      },
      onError: () => {
        errorMessage.value = 'Failed to update squadron emblem.'
      },
      onFinish: () => {
        isSavingEmblem.value = false
      },
    }
  )
}

function clearEmblem() {
  clearEmblemConfirmDialog.value?.show()
}

/* -------------------------------------------------
   Member actions
------------------------------------------------- */
function applyToSquadron() {
  if (!squadron.value?.id) return

  activeAction.value = 'apply'
  errorMessage.value = null

  router.post(route('squadrons.join', { squadron: squadron.value.id }), {}, {
    preserveScroll: true,
    onError: () => {
      errorMessage.value = 'Failed to apply to squadron.'
    },
    onFinish: () => {
      activeAction.value = null
    },
  })
}

function askLeaveSquadron() {
  leaveConfirmDialog.value?.show()
}

function leaveSquadron() {
  if (!squadron.value?.id) return

  activeAction.value = 'leave'
  errorMessage.value = null

  router.post(route('squadrons.leave', { squadron: squadron.value.id }), {}, {
    preserveScroll: true,
    onError: () => {
      errorMessage.value = 'Failed to leave squadron.'
    },
    onFinish: () => {
      activeAction.value = null
    },
  })
}

function askAcceptMember(member) {
  if (!squadron.value?.id || !member?.id || !canManageMembers.value) return
  pendingAcceptMember.value = member
  acceptConfirmDialog.value?.show()
}

function acceptMember(member) {
  if (!squadron.value?.id || !member?.id || !canManageMembers.value) return

  activeAction.value = `accept:${member.id}`
  errorMessage.value = null

  router.post(
    route('squadrons.members.update', { squadron: squadron.value.id }),
    {
      id: member.id,
      membership_status: 'active',
      role: 'member',
    },
    {
      preserveScroll: true,
      onError: () => {
        errorMessage.value = 'Failed to accept applicant.'
      },
      onFinish: () => {
        activeAction.value = null
      },
    }
  )
}

function askRejectMember(member) {
  if (!squadron.value?.id || !member?.id || !canManageMembers.value) return
  pendingRejectMember.value = member
  rejectConfirmDialog.value?.show()
}

function rejectMember(member) {
  if (!squadron.value?.id || !member?.id || !canManageMembers.value) return

  activeAction.value = `reject:${member.id}`
  errorMessage.value = null

  router.post(
    route('squadrons.members.remove', { squadron: squadron.value.id }),
    {
      id: member.id,
    },
    {
      preserveScroll: true,
      onError: () => {
        errorMessage.value = 'Failed to reject applicant.'
      },
      onFinish: () => {
        activeAction.value = null
      },
    }
  )
}

function askRemoveMember(member) {
  if (!squadron.value?.id || !member?.id || !canManageMembers.value) return
  if (member?.is_leader) return
  pendingRemoveMember.value = member
  removeConfirmDialog.value?.show()
}

function removeMember(member) {
  if (!squadron.value?.id || !member?.id || !canManageMembers.value) return
  if (member?.is_leader) return

  activeAction.value = `remove:${member.id}`
  errorMessage.value = null

  router.post(
    route('squadrons.members.remove', { squadron: squadron.value.id }),
    {
      id: member.id,
    },
    {
      preserveScroll: true,
      onError: () => {
        errorMessage.value = 'Failed to remove member.'
      },
      onFinish: () => {
        activeAction.value = null
      },
    }
  )
}

function askPromoteLieutenant(member) {
  if (!squadron.value?.id || !canPromoteMembers.value || !canPromoteLieutenant.value) return
  if (!memberUserId(member)) return
  pendingPromoteMember.value = member
  promoteConfirmDialog.value?.show()
}

function promoteLieutenant(member) {
  if (!squadron.value?.id || !canPromoteMembers.value || !canPromoteLieutenant.value) return

  const userId = memberUserId(member)
  if (!userId) return

  activeAction.value = `promote:${member.id}`
  errorMessage.value = null

  router.post(
    route('squadrons.promoteLieutenant', { squadron: squadron.value.id }),
    {
      user_id: userId,
    },
    {
      preserveScroll: true,
      onError: () => {
        errorMessage.value = 'Failed to promote member.'
      },
      onFinish: () => {
        activeAction.value = null
      },
    }
  )
}

function askDemoteLieutenant(member) {
  if (!squadron.value?.id || !canDemoteMembers.value || !member?.is_lieutenant) return
  if (!memberUserId(member)) return
  pendingDemoteMember.value = member
  demoteConfirmDialog.value?.show()
}

function demoteLieutenant(member) {
  if (!squadron.value?.id || !canDemoteMembers.value || !member?.is_lieutenant) return

  const userId = memberUserId(member)
  if (!userId) return

  activeAction.value = `demote:${member.id}`
  errorMessage.value = null

  router.post(
    route('squadrons.demoteLieutenant', { squadron: squadron.value.id }),
    {
      user_id: userId,
    },
    {
      preserveScroll: true,
      onError: () => {
        errorMessage.value = 'Failed to demote member.'
      },
      onFinish: () => {
        activeAction.value = null
      },
    }
  )
}

watch(
  () => squadron.value?.id,
  () => {
    seedEditForm()
  },
  { immediate: true }
)
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-8" :style="squadronAccentStyle">
      <div
        v-if="!squadron"
        class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/30 bg-[color:var(--horizon-void-700)]/80 p-6 text-text-secondary"
      >
        Squadron data could not be loaded.
      </div>

      <template v-else>
        <!-- Squadron hero -->
        <section
          class="relative overflow-hidden rounded-[2rem] border border-[color:var(--squadron-accent-a)]/45 bg-[radial-gradient(circle_at_top_left,var(--squadron-glow-a),transparent_34%),radial-gradient(circle_at_top_right,var(--squadron-glow-b),transparent_32%),linear-gradient(135deg,var(--horizon-void-600),var(--horizon-void-900))] p-6"
          :style="{ boxShadow: '0 0 48px var(--squadron-glow-a)' }"
        >
          <div class="pointer-events-none absolute inset-0 opacity-40">
            <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--squadron-accent-a)] to-transparent"></div>
            <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--squadron-accent-b)] to-transparent"></div>
          </div>

          <div class="relative grid gap-6 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center">
            <div class="relative mx-auto h-36 w-36 shrink-0 lg:mx-0">
              <div class="absolute -inset-3 rounded-[2rem] bg-gradient-to-br from-[color:var(--squadron-accent-a)]/35 via-transparent to-[color:var(--squadron-accent-b)]/30 blur-xl"></div>

              <div class="relative flex h-36 w-36 items-center justify-center overflow-hidden rounded-[1.75rem] border border-[color:var(--squadron-accent-a)]/35 bg-[color:var(--horizon-void-800)] shadow-[0_0_28px_var(--squadron-glow-a)]">
                <img
                  v-if="squadron.emblem_url"
                  :src="squadron.emblem?.medium_url || squadron.emblem?.url || squadron.emblem_url"
                  :alt="squadron.emblem?.alt_text || `${squadron.name} emblem`"
                  class="h-full w-full object-contain p-3"
                  loading="lazy"
                />
                <div v-else class="text-4xl font-black text-[color:var(--horizon-text-primary)]">
                  {{ String(squadron.name ?? 'S').slice(0, 1).toUpperCase() }}
                </div>
              </div>
            </div>

            <div class="min-w-0 text-center lg:text-left">
              <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
                Horizon Squadron File
              </div>

              <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
                {{ squadron.name }}
              </h1>

              <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
                {{ squadron.motto ?? 'No motto provided.' }}
              </p>

              <div class="mt-4 flex flex-wrap justify-center gap-2 lg:justify-start">
                <span class="rounded-full border border-[color:var(--squadron-accent-a)]/30 bg-[color:var(--squadron-accent-a)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                  {{ formatTitle(squadron.branch) }}<span v-if="squadron.division"> · {{ formatTitle(squadron.division) }}</span>
                </span>

                <span
                  class="rounded-full px-3 py-1 text-xs font-semibold"
                  :class="squadron.recruiting
                    ? 'border border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                    : 'border border-white/10 bg-white/[0.04] text-text-secondary'"
                >
                  {{ recruitmentStateLabel }}
                </span>

                <span class="rounded-full border border-[color:var(--squadron-accent-b)]/30 bg-[color:var(--squadron-accent-b)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                  {{ activeMemberCount }} / {{ maxRosterSize }} Active
                </span>
              </div>
            </div>

            <div class="flex flex-col gap-2 lg:min-w-44">
              <HorizonButton
                v-if="canEditSquadron"
                variant="primary"
                size="sm"
                @click="openEditModal"
              >
                Edit Squadron
              </HorizonButton>

              <HorizonButton
                v-if="permissions?.can_apply"
                variant="primary"
                size="sm"
                :disabled="activeAction === 'apply'"
                @click="applyToSquadron"
              >
                {{ activeAction === 'apply' ? 'Applying…' : 'Apply' }}
              </HorizonButton>

              <HorizonButton
                v-if="permissions?.can_leave"
                variant="secondary"
                size="sm"
                :disabled="activeAction === 'leave'"
                @click="askLeaveSquadron"
              >
                {{ activeAction === 'leave' ? 'Leaving…' : 'Leave Squadron' }}
              </HorizonButton>

              <Link
                :href="route('squadrons.index')"
                class="inline-flex items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] px-3 py-2 text-sm font-semibold text-text-secondary transition hover:bg-white/[0.06] hover:text-horizon-white"
              >
                Back to Registry
              </Link>
            </div>
          </div>
        </section>

        <div v-if="errorMessage" class="hz-alert hz-alert-danger">
          {{ errorMessage }}
        </div>

        <!-- Main dossier layout -->
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <main class="space-y-6">
            <!-- Recruitment -->
            <section class="rounded-[2rem] border border-[color:var(--squadron-accent-a)]/25 bg-[linear-gradient(135deg,var(--squadron-glow-a),var(--horizon-void-700)_42%,var(--horizon-void-900))] p-6 shadow-[0_0_32px_var(--squadron-glow-a)]">
              <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                  <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                    Recruitment Broadcast
                  </div>
                  <h2 class="mt-1 text-xl font-bold text-horizon-white">
                    Join Directive
                  </h2>
                </div>

                <span
                  class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                  :class="squadron.recruiting
                    ? 'border border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                    : 'border border-white/10 bg-white/[0.04] text-text-secondary'"
                >
                  {{ recruitmentStateLabel }}
                </span>
              </div>

              <div
                v-if="squadron.recruitment_propaganda"
                class="hz-soft space-y-3 [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-[color:var(--squadron-accent-a)]/35 [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_code]:rounded [&_code]:bg-bg-elevated [&_code]:px-1 [&_code]:py-0.5 [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_h3]:text-lg [&_h3]:font-semibold [&_hr]:my-4 [&_hr]:border-[color:var(--color-bg-hover)] [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-lg [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:leading-7 [&_pre]:rounded [&_pre]:bg-bg-elevated [&_pre]:p-3 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-[color:var(--color-bg-hover)] [&_td]:bg-bg-elevated [&_td]:p-2 [&_th]:border [&_th]:border-[color:var(--color-bg-hover)] [&_th]:bg-bg-hover [&_th]:p-2 [&_ul]:list-disc [&_ul]:pl-5"
                v-html="squadron.recruitment_propaganda"
              />

              <p v-else class="text-sm text-text-secondary">
                No recruitment message has been posted for this squadron yet.
              </p>
            </section>

            <!-- Overview -->
            <section class="rounded-[2rem] border border-white/10 bg-white/[0.035] p-6">
              <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Squadron Overview
                </div>
                <h2 class="mt-1 text-xl font-bold text-horizon-white">
                  Mission Profile
                </h2>
              </div>

              <div
                v-if="squadron.description"
                class="hz-soft space-y-3 [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-[color:var(--squadron-accent-a)]/35 [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_code]:rounded [&_code]:bg-bg-elevated [&_code]:px-1 [&_code]:py-0.5 [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_h3]:text-lg [&_h3]:font-semibold [&_hr]:my-4 [&_hr]:border-[color:var(--color-bg-hover)] [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-lg [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:leading-7 [&_pre]:rounded [&_pre]:bg-bg-elevated [&_pre]:p-3 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-[color:var(--color-bg-hover)] [&_td]:bg-bg-elevated [&_td]:p-2 [&_th]:border [&_th]:border-[color:var(--color-bg-hover)] [&_th]:bg-bg-hover [&_th]:p-2 [&_ul]:list-disc [&_ul]:pl-5"
                v-html="squadron.description"
              />

              <p v-else class="text-sm text-text-secondary">
                No squadron description has been provided yet.
              </p>
            </section>

            <!-- Roster Command Console -->
            <section class="rounded-[2rem] border border-[color:var(--squadron-accent-a)]/30 bg-[color:var(--horizon-void-700)]/80 p-6 shadow-[0_0_32px_var(--squadron-glow-a)]">
              <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                  <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                    Roster Command Console
                  </div>
                  <h2 class="mt-1 text-xl font-bold text-horizon-white">
                    Active Personnel
                  </h2>
                  <p class="mt-1 text-sm text-text-secondary">
                    Command staff, lieutenants, squadron members, and pending applications.
                  </p>
                </div>

                <div class="rounded-2xl border border-[color:var(--squadron-accent-b)]/25 bg-[color:var(--squadron-accent-b)]/10 px-4 py-3 text-right">
                  <div class="text-2xl font-black text-horizon-white">
                    {{ activeMemberCount }} / {{ maxRosterSize }}
                  </div>
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Active Slots
                  </div>
                </div>
              </div>

              <div class="space-y-6">
                <!-- Pending applications -->
                <section
                  v-if="canManageMembers && pendingMembers.length"
                  class="rounded-[1.5rem] border border-amber-300/25 bg-amber-300/5 p-4"
                >
                  <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                      <div class="text-xs font-bold uppercase tracking-[0.18em] text-amber-100/80">
                        Pending Applications
                      </div>
                      <div class="text-sm text-text-secondary">
                        Applicants awaiting officer review.
                      </div>
                    </div>

                    <div class="rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-xs font-bold text-amber-100">
                      {{ pendingMembers.length }}
                    </div>
                  </div>

                  <div class="grid gap-3 md:grid-cols-2">
                    <article
                      v-for="member in pendingMembers"
                      :key="member.id"
                      class="rounded-2xl border border-amber-300/20 bg-black/10 p-3"
                    >
                      <div class="flex items-start gap-3">
                        <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-xl border border-amber-300/20 bg-amber-300/10">
                          <img
                            v-if="memberAvatar(member)"
                            :src="memberAvatar(member)"
                            :alt="`${memberName(member)} Discord avatar`"
                            class="h-full w-full object-cover"
                            loading="lazy"
                          />

                          <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-sm font-black text-amber-100"
                          >
                            {{ memberInitial(member) }}
                          </div>
                        </div>

                        <div class="min-w-0 flex-1">
                          <div class="truncate font-semibold text-horizon-white">
                            {{ memberName(member) }}
                          </div>
                          <div class="text-xs uppercase tracking-wide text-amber-100/70">
                            Pending Review
                          </div>
                        </div>
                      </div>

                      <div class="mt-3 flex flex-wrap gap-2">
                        <HorizonButton
                          variant="primary"
                          size="xs"
                          :disabled="!!activeAction"
                          @click="askAcceptMember(member)"
                        >
                          {{ isMemberBusy(member, 'accept') ? 'Accepting…' : 'Accept' }}
                        </HorizonButton>

                        <HorizonButton
                          variant="secondary"
                          size="xs"
                          :disabled="!!activeAction"
                          @click="askRejectMember(member)"
                        >
                          {{ isMemberBusy(member, 'reject') ? 'Rejecting…' : 'Reject' }}
                        </HorizonButton>
                      </div>
                    </article>
                  </div>
                </section>

                <!-- Commanding officer -->
                <section v-if="leaderMember || leader" class="space-y-2">
                  <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                    Commanding Officer
                  </div>

                  <article class="rounded-2xl border border-[color:var(--squadron-accent-a)]/25 bg-white/[0.035] p-4">
                    <div class="flex items-center gap-3">
                      <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-[color:var(--squadron-accent-a)]/30 bg-[color:var(--squadron-accent-a)]/10 shadow-[0_0_18px_var(--squadron-glow-a)]">
                        <img
                          v-if="leaderMember && memberAvatar(leaderMember)"
                          :src="memberAvatar(leaderMember)"
                          :alt="`${memberName(leaderMember)} Discord avatar`"
                          class="h-full w-full object-cover"
                          loading="lazy"
                        />

                        <div
                          v-else
                          class="flex h-full w-full items-center justify-center text-lg font-black text-horizon-white"
                        >
                          {{ leaderMember ? memberInitial(leaderMember) : String(leader?.rsi_handle ?? leader?.display_name ?? leader?.name ?? 'L').slice(0, 1).toUpperCase() }}
                        </div>
                      </div>

                      <div class="min-w-0">
                        <Link
                          v-if="leaderMember && memberProfileHref(leaderMember)"
                          :href="memberProfileHref(leaderMember)"
                          class="font-semibold hover:underline"
                          :style="memberNameColor(leaderMember) ? { color: memberNameColor(leaderMember) } : undefined"
                        >
                          {{ memberName(leaderMember) }}
                        </Link>

                        <span
                          v-else
                          class="font-semibold"
                          :style="leaderColor ? { color: leaderColor } : undefined"
                        >
                          {{ leader?.rsi_handle ?? leader?.display_name ?? leader?.name ?? 'None Assigned' }}
                        </span>

                        <div class="mt-1 text-xs uppercase tracking-wide text-text-muted">
                          Squadron Leader
                        </div>
                      </div>
                    </div>
                  </article>
                </section>

                <!-- Lieutenants -->
                <section class="space-y-2">
                  <div class="flex items-center justify-between gap-3">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                      Lieutenants
                    </div>

                    <div class="text-xs font-semibold text-text-muted">
                      {{ lieutenantMembers.length }} / 2
                    </div>
                  </div>

                  <div v-if="lieutenantMembers.length" class="grid gap-3 md:grid-cols-2">
                    <article
                      v-for="member in lieutenantMembers"
                      :key="member.id"
                      class="rounded-2xl border border-[color:var(--squadron-accent-a)]/15 bg-white/[0.025] p-3"
                    >
                      <div class="flex items-start gap-3">
                        <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-xl border border-[color:var(--squadron-accent-a)]/25 bg-[color:var(--squadron-accent-a)]/10 shadow-[0_0_14px_var(--squadron-glow-a)]">
                          <img
                            v-if="memberAvatar(member)"
                            :src="memberAvatar(member)"
                            :alt="`${memberName(member)} Discord avatar`"
                            class="h-full w-full object-cover"
                            loading="lazy"
                          />

                          <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-sm font-black text-horizon-white"
                          >
                            {{ memberInitial(member) }}
                          </div>
                        </div>

                        <div class="min-w-0 flex-1">
                          <Link
                            v-if="memberProfileHref(member)"
                            :href="memberProfileHref(member)"
                            class="font-semibold hover:underline"
                            :style="memberNameColor(member) ? { color: memberNameColor(member) } : undefined"
                          >
                            {{ memberName(member) }}
                          </Link>

                          <span v-else class="font-semibold text-horizon-white">
                            {{ memberName(member) }}
                          </span>

                          <div class="mt-1 text-xs uppercase tracking-wide text-text-muted">
                            {{ memberRoleLabel(member) }} · {{ memberRank(member) }}
                          </div>
                        </div>
                      </div>

                      <div v-if="canDemoteMembers || canManageMembers" class="mt-3 flex flex-wrap gap-2">
                        <HorizonButton
                          v-if="canDemoteMembers"
                          variant="ghost"
                          size="xs"
                          :disabled="!!activeAction"
                          @click="askDemoteLieutenant(member)"
                        >
                          {{ isMemberBusy(member, 'demote') ? 'Demoting…' : 'Demote' }}
                        </HorizonButton>

                        <HorizonButton
                          v-if="canManageMembers"
                          variant="ghost"
                          size="xs"
                          :disabled="!!activeAction"
                          @click="askRemoveMember(member)"
                        >
                          {{ isMemberBusy(member, 'remove') ? 'Removing…' : 'Remove' }}
                        </HorizonButton>
                      </div>
                    </article>
                  </div>

                  <p v-else class="rounded-2xl border border-white/10 bg-white/[0.025] p-3 text-sm text-text-secondary">
                    No lieutenants are assigned yet.
                  </p>
                </section>

                <!-- Members -->
                <section class="space-y-2">
                  <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                    Members
                  </div>

                  <div v-if="sortedRegularMembers.length" class="grid gap-3 md:grid-cols-2">
                    <article
                      v-for="member in sortedRegularMembers"
                      :key="member.id"
                      class="rounded-2xl border border-white/10 bg-white/[0.025] p-3"
                    >
                      <div class="flex items-start gap-3">
                        <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-white/[0.04]">
                          <img
                            v-if="memberAvatar(member)"
                            :src="memberAvatar(member)"
                            :alt="`${memberName(member)} Discord avatar`"
                            class="h-full w-full object-cover"
                            loading="lazy"
                          />

                          <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-sm font-black text-horizon-white"
                          >
                            {{ memberInitial(member) }}
                          </div>
                        </div>

                        <div class="min-w-0 flex-1">
                          <Link
                            v-if="memberProfileHref(member)"
                            :href="memberProfileHref(member)"
                            class="font-semibold hover:underline"
                            :style="memberNameColor(member) ? { color: memberNameColor(member) } : undefined"
                          >
                            {{ memberName(member) }}
                          </Link>

                          <span v-else class="font-semibold text-horizon-white">
                            {{ memberName(member) }}
                          </span>

                          <div class="mt-1 text-xs uppercase tracking-wide text-text-muted">
                            {{ memberRoleLabel(member) }} · {{ memberRank(member) }}
                          </div>
                        </div>
                      </div>

                      <div v-if="canPromoteMembers || canManageMembers" class="mt-3 flex flex-wrap gap-2">
                        <HorizonButton
                          v-if="canPromoteMembers && canPromoteLieutenant"
                          variant="ghost"
                          size="xs"
                          :disabled="!!activeAction"
                          @click="askPromoteLieutenant(member)"
                        >
                          {{ isMemberBusy(member, 'promote') ? 'Promoting…' : 'Promote LT' }}
                        </HorizonButton>

                        <HorizonButton
                          v-if="canManageMembers"
                          variant="ghost"
                          size="xs"
                          :disabled="!!activeAction"
                          @click="askRemoveMember(member)"
                        >
                          {{ isMemberBusy(member, 'remove') ? 'Removing…' : 'Remove' }}
                        </HorizonButton>
                      </div>
                    </article>
                  </div>

                  <p v-else class="rounded-2xl border border-white/10 bg-white/[0.025] p-3 text-sm text-text-secondary">
                    No regular members are listed yet.
                  </p>
                </section>
              </div>
            </section>
          </main>

          <aside class="space-y-6">
            <!-- Viewer status -->
            <section class="rounded-[2rem] border border-[color:var(--squadron-accent-a)]/25 bg-[linear-gradient(135deg,var(--squadron-glow-a),rgba(255,255,255,0.025))] p-5 shadow-[0_0_24px_var(--squadron-glow-a)]">
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Your Status
              </div>

              <div class="mt-3 text-2xl font-black text-horizon-white">
                {{ viewerStatusLabel }}
              </div>

              <p class="mt-2 text-sm text-text-secondary">
                {{ viewerMembership ? 'You have an existing relationship with this squadron.' : 'You are not currently assigned to this squadron.' }}
              </p>
            </section>

            <!-- Officer tools -->
            <section
              v-if="canEditSquadron || canManageMembers"
              class="rounded-[2rem] border border-[color:var(--squadron-accent-b)]/25 bg-[radial-gradient(circle_at_top_right,var(--squadron-glow-b),transparent_46%),rgba(255,255,255,0.035)] p-5"
            >
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Officer Tools
              </div>

              <div class="mt-4 space-y-2">
                <HorizonButton
                  v-if="canEditSquadron"
                  variant="primary"
                  size="sm"
                  class="w-full"
                  @click="openEditModal"
                >
                  Edit Dossier
                </HorizonButton>

                <div
                  v-if="canManageMembers"
                  class="rounded-2xl border border-white/10 bg-white/[0.025] p-3"
                >
                  <div class="text-sm font-semibold text-horizon-white">
                    {{ pendingMembers.length }} Pending
                  </div>
                  <div class="mt-1 text-xs text-text-muted">
                    Applications awaiting review.
                  </div>
                </div>
              </div>
            </section>

            <!-- Quick facts -->
            <section class="rounded-[2rem] border border-white/10 bg-white/[0.035] p-5">
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Quick Facts
              </div>

              <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
                  <span class="text-sm text-text-muted">Branch</span>
                  <span class="flex items-center gap-2 text-sm font-semibold text-horizon-white">
                    <img
                      v-if="branchLogoSrc(squadron.branch)"
                      :src="branchLogoSrc(squadron.branch)"
                      :alt="`${formatTitle(squadron.branch)} logo`"
                      class="h-4 w-4 shrink-0 object-contain"
                      loading="lazy"
                    />
                    {{ formatTitle(squadron.branch) }}
                  </span>
                </div>

                <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
                  <span class="text-sm text-text-muted">Division</span>
                  <span class="text-sm font-semibold text-horizon-white">
                    {{ formatTitle(squadron.division) }}
                  </span>
                </div>

                <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3">
                  <span class="text-sm text-text-muted">Status</span>
                  <span class="text-sm font-semibold text-horizon-white">
                    {{ formatTitle(squadron.status) }}
                  </span>
                </div>

                <div class="flex items-center justify-between gap-3">
                  <span class="text-sm text-text-muted">Recruiting</span>
                  <span class="text-sm font-semibold text-horizon-white">
                    {{ squadron.recruiting ? 'Open' : 'Closed' }}
                  </span>
                </div>
              </div>
            </section>

            <!-- Capacity -->
            <section class="rounded-[2rem] border border-[color:var(--squadron-accent-b)]/25 bg-[radial-gradient(circle_at_top_right,var(--squadron-glow-b),transparent_46%),rgba(255,255,255,0.035)] p-5">
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Roster Capacity
              </div>

              <div class="mt-3 flex items-end justify-between gap-3">
                <div class="text-3xl font-black text-horizon-white">
                  {{ activeMemberCount }}
                </div>
                <div class="pb-1 text-sm text-text-secondary">
                  of {{ maxRosterSize }} active slots
                </div>
              </div>

              <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/[0.06]">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-[color:var(--squadron-accent-a)] to-[color:var(--squadron-accent-b)]"
                  :style="{ width: `${rosterPercent}%` }"
                ></div>
              </div>
            </section>

            <!-- Leadership -->
            <section class="rounded-[2rem] border border-white/10 bg-white/[0.035] p-5">
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Leadership
              </div>

              <div class="mt-4 space-y-3">
                <div class="rounded-2xl border border-[color:var(--squadron-accent-a)]/20 bg-white/[0.025] p-3">
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Commander
                  </div>
                  <div
                    class="mt-1 font-semibold text-horizon-white"
                    :style="leaderColor ? { color: leaderColor } : undefined"
                  >
                    {{ leader?.rsi_handle ?? leader?.display_name ?? leader?.name ?? 'None Assigned' }}
                  </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/[0.025] p-3">
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Lieutenants
                  </div>
                  <div class="mt-1 font-semibold text-horizon-white">
                    {{ lieutenantMembers.length }} / 2
                  </div>
                </div>
              </div>
            </section>
          </aside>
        </div>

        <!-- Edit modal -->
        <div
          v-if="isEditModalOpen"
          class="fixed inset-0 z-40 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
          @click.self="closeEditModal"
        >
          <section class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border border-[color:var(--squadron-accent-a)]/45 bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))] shadow-[0_0_64px_var(--squadron-glow-a)]">
            <header class="flex items-start justify-between gap-4 border-b border-[color:var(--squadron-accent-a)]/20 p-5">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Squadron Edit Console
                </div>
                <h2 class="mt-1 text-2xl font-black text-horizon-white">
                  Edit {{ squadron.name }}
                </h2>
                <p class="mt-1 text-sm text-text-secondary">
                  Update the public dossier without disturbing the command layout.
                </p>
              </div>

              <HorizonButton
                variant="ghost"
                size="sm"
                :disabled="isSavingSettings || isSavingEmblem"
                @click="closeEditModal"
              >
                Close
              </HorizonButton>
            </header>

            <div class="max-h-[calc(90vh-9rem)] overflow-y-auto p-5">
              <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <!-- Emblem controls -->
                <aside class="space-y-4">
                  <section class="rounded-2xl border border-[color:var(--squadron-accent-a)]/25 bg-white/[0.035] p-4">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Emblem
                    </div>

                    <div class="relative mx-auto mt-4 h-40 w-40">
                      <div class="absolute -inset-3 rounded-[2rem] bg-gradient-to-br from-[color:var(--squadron-accent-a)]/25 via-transparent to-[color:var(--squadron-accent-b)]/25 blur-xl"></div>

                      <div class="relative flex h-40 w-40 items-center justify-center overflow-hidden rounded-[1.5rem] border border-[color:var(--squadron-accent-a)]/35 bg-[color:var(--horizon-void-800)]">
                        <img
                          v-if="squadron.emblem_url"
                          :src="squadron.emblem?.medium_url || squadron.emblem?.url || squadron.emblem_url"
                          :alt="squadron.emblem?.alt_text || `${squadron.name} emblem`"
                          class="h-full w-full object-contain p-3"
                          loading="lazy"
                        />
                        <div v-else class="text-4xl font-black text-[color:var(--horizon-text-primary)]">
                          {{ String(squadron.name ?? 'S').slice(0, 1).toUpperCase() }}
                        </div>
                      </div>
                    </div>

                    <div class="mt-4 space-y-2">
                      <HorizonButton
                        variant="primary"
                        size="sm"
                        class="w-full"
                        :disabled="isSavingEmblem"
                        @click="openEmblemPicker"
                      >
                        Choose Emblem
                      </HorizonButton>

                      <HorizonButton
                        variant="ghost"
                        size="sm"
                        class="w-full"
                        :disabled="isSavingEmblem || !squadron.emblem_url"
                        @click="clearEmblem"
                      >
                        {{ isSavingEmblem ? 'Updating…' : 'Clear Emblem' }}
                      </HorizonButton>
                    </div>
                  </section>

                  <section class="rounded-2xl border border-white/10 bg-white/[0.025] p-4">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Save Rules
                    </div>
                    <p class="mt-2 text-sm text-text-secondary">
                      Text fields save together. Emblem changes save immediately after selection.
                    </p>
                  </section>
                </aside>

                <!-- Text controls -->
                <main class="space-y-5">
                  <div>
                    <label class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Motto
                    </label>
                    <input
                      v-model="editForm.motto"
                      class="hz-input mt-2"
                      placeholder="Optional squadron motto"
                    />
                  </div>

                  <div>
                    <label class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Recruitment Broadcast
                    </label>
                    <div class="mt-2">
                      <HorizonRichTextEditor
                        v-model="editForm.recruitment_propaganda"
                        :rows="10"
                        placeholder="Write the squadron recruitment pitch..."
                      />
                    </div>
                  </div>

                  <div>
                    <label class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                      Mission Profile / Description
                    </label>
                    <div class="mt-2">
                      <HorizonRichTextEditor
                        v-model="editForm.description"
                        :rows="10"
                        placeholder="Write the squadron description..."
                      />
                    </div>
                  </div>
                </main>
              </div>
            </div>

            <footer class="flex items-center justify-end gap-2 border-t border-[color:var(--squadron-accent-a)]/20 p-5">
              <HorizonButton
                variant="ghost"
                size="sm"
                :disabled="isSavingSettings || isSavingEmblem"
                @click="closeEditModal"
              >
                Cancel
              </HorizonButton>

              <HorizonButton
                variant="primary"
                size="sm"
                :disabled="isSavingSettings || isSavingEmblem"
                @click="saveSquadronSettings"
              >
                {{ isSavingSettings ? 'Saving…' : 'Save Changes' }}
              </HorizonButton>
            </footer>
          </section>
        </div>

        <MediaPickerModal
          :open="isEmblemPickerOpen"
          collection="squadron_emblem"
          :squadron-id="squadron.id"
          title="Choose Squadron Emblem"
          :allow-upload="true"
          @close="closeEmblemPicker"
          @selected="setEmblem"
        />
      </template>
    </div>
  </HorizonContainer>

  <HorizonConfirmDialog
    ref="leaveConfirmDialog"
    title="Leave Squadron"
    confirm-label="Leave"
    cancel-label="Cancel"
    variant="warning"
    message="Leave this squadron? You will need to re-apply to rejoin."
    @confirm="leaveSquadron"
  />

  <HorizonConfirmDialog
    ref="clearEmblemConfirmDialog"
    title="Clear Emblem"
    confirm-label="Clear"
    cancel-label="Cancel"
    variant="warning"
    message="Remove the squadron emblem?"
    @confirm="() => setEmblem(null)"
  />

  <HorizonConfirmDialog
    ref="acceptConfirmDialog"
    title="Accept Application"
    confirm-label="Accept"
    cancel-label="Cancel"
    variant="default"
    message="Accept this member into the squadron?"
    @confirm="() => { if (pendingAcceptMember) acceptMember(pendingAcceptMember) }"
  />

  <HorizonConfirmDialog
    ref="rejectConfirmDialog"
    title="Reject Application"
    confirm-label="Reject"
    cancel-label="Cancel"
    variant="danger"
    message="Reject this member's application?"
    @confirm="() => { if (pendingRejectMember) rejectMember(pendingRejectMember) }"
  />

  <HorizonConfirmDialog
    ref="removeConfirmDialog"
    title="Remove Member"
    confirm-label="Remove"
    cancel-label="Cancel"
    variant="danger"
    message="Remove this member from the squadron?"
    @confirm="() => { if (pendingRemoveMember) removeMember(pendingRemoveMember) }"
  />

  <HorizonConfirmDialog
    ref="promoteConfirmDialog"
    title="Promote to Lieutenant"
    confirm-label="Promote"
    cancel-label="Cancel"
    variant="default"
    message="Promote this member to Lieutenant?"
    @confirm="() => { if (pendingPromoteMember) promoteLieutenant(pendingPromoteMember) }"
  />

  <HorizonConfirmDialog
    ref="demoteConfirmDialog"
    title="Demote Lieutenant"
    confirm-label="Demote"
    cancel-label="Cancel"
    variant="warning"
    message="Remove this member's Lieutenant rank?"
    @confirm="() => { if (pendingDemoteMember) demoteLieutenant(pendingDemoteMember) }"
  />
</template>