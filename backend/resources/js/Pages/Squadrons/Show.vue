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
        class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-6 text-text-secondary"
      >
        Squadron data could not be loaded.
      </div>

      <template v-else>
        <!-- Main content card -->
        <div class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-6 md:p-8">
          <!-- Header -->
          <div class="border-b border-white/[0.055] pb-6">
            <div class="flex items-center gap-4">
              <img
                v-if="squadron.emblem_url"
                :src="squadron.emblem?.medium_url || squadron.emblem?.url || squadron.emblem_url"
                :alt="squadron.emblem?.alt_text || `${squadron.name} emblem`"
                class="h-20 w-20 rounded-2xl object-contain md:h-24 md:w-24"
                loading="lazy"
              />
              <div v-else class="flex h-20 w-20 items-center justify-center rounded-2xl bg-white/[0.05] text-2xl font-bold text-white md:h-24 md:w-24">
                {{ squadron.name?.[0] || '?' }}
              </div>
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-text-secondary)]">
                  {{ formatTitle(squadron.branch) }}
                </div>
                <h1 class="text-3xl font-black tracking-tight text-white md:text-5xl">
                  {{ squadron.name }}
                </h1>
              </div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-xs">
              <span
                class="rounded-full px-2.5 py-0.5 font-semibold"
                :class="squadron.recruiting
                  ? 'border border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                  : 'bg-white/[0.042] text-text-secondary'"
              >
                {{ recruitmentStateLabel }}
              </span>
              <span v-if="squadron.division" class="text-text-muted">{{ squadron.division }}</span>
            </div>
          </div>

        <div v-if="errorMessage" class="hz-alert hz-alert-danger">
          {{ errorMessage }}
        </div>

        <!-- Main dossier layout -->
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <main class="space-y-6">
            <!-- Recruitment -->
            <div v-if="squadron.recruitment_propaganda" class="space-y-3">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Recruitment</div>
              <div
                class="hz-rte-content"
                v-html="squadron.recruitment_propaganda"
              />
            </div>

            <!-- Overview -->
            <div class="space-y-3">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">About</div>
              <div
                v-if="squadron.description"
                class="hz-rte-content"
                v-html="squadron.description"
              />
              <p v-else class="text-sm text-text-secondary">No description provided.</p>
            </div>

            <!-- Roster -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Roster</div>
                <div class="text-xs text-text-secondary">{{ activeMemberCount }} / {{ maxRosterSize }}</div>
              </div>

              <div class="space-y-6">
                <!-- Pending applications -->
                <div v-if="canManageMembers && pendingMembers.length" class="space-y-3">
                  <div class="flex items-center justify-between">
                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-amber-100/80">Pending</div>
                    <div class="text-xs font-bold text-amber-100">{{ pendingMembers.length }}</div>
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
                </div>

                <!-- Commanding officer -->
                <div v-if="leaderMember || leader" class="space-y-2">
                  <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Commanding Officer</div>
                  <div class="flex items-center gap-2">
                    <img
                      v-if="leaderMember && memberAvatar(leaderMember)"
                      :src="memberAvatar(leaderMember)"
                      :alt="`${memberName(leaderMember)} Discord avatar`"
                      class="h-8 w-8 shrink-0 rounded-lg object-cover"
                      loading="lazy"
                    />
                    <div
                      v-else
                      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.05] text-xs font-bold text-horizon-white"
                    >
                      {{ leaderMember ? memberInitial(leaderMember) : String(leader?.rsi_handle ?? leader?.display_name ?? leader?.name ?? 'L').slice(0, 1).toUpperCase() }}
                    </div>
                    <Link
                      v-if="leaderMember && memberProfileHref(leaderMember)"
                      :href="memberProfileHref(leaderMember)"
                      class="text-sm font-semibold text-horizon-white hover:underline"
                      :style="memberNameColor(leaderMember) ? { color: memberNameColor(leaderMember) } : undefined"
                    >
                      {{ memberName(leaderMember) }}
                    </Link>

                        <span
                          v-else
                          class="text-sm font-semibold text-horizon-white"
                          :style="leaderColor ? { color: leaderColor } : undefined"
                        >
                          {{ leader?.rsi_handle ?? leader?.display_name ?? leader?.name ?? 'None Assigned' }}
                        </span>
                  </div>
                </div>

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
                      class="hz-surface-welcome rounded-2xl border border-[color:var(--squadron-accent-a)]/15 p-3"
                    >
                      <div class="flex items-start gap-3">
                        <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-xl border border-[color:var(--squadron-accent-a)]/25 bg-white/[0.042]">
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

                  <p v-else class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-3 text-sm text-text-secondary">
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
                      class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-3"
                    >
                      <div class="flex items-start gap-3">
                        <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-xl border border-white/[0.055] bg-white/[0.042]">
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

                  <p v-else class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-3 text-sm text-text-secondary">
                    No regular members are listed yet.
                  </p>
                </section>
              </div>
            </div>
          </main>

          <aside class="space-y-6">
            <!-- Your Status -->
            <div class="space-y-2">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Your Status</div>
              <div class="text-lg font-semibold text-horizon-white">{{ viewerStatusLabel }}</div>
              <div class="text-sm text-text-secondary">
                {{ viewerMembership ? 'You have an existing relationship with this squadron.' : 'You are not currently assigned to this squadron.' }}
              </div>
              <div class="flex flex-wrap gap-2 pt-2">
                <HorizonButton
                  v-if="permissions?.can_apply"
                  variant="primary"
                  size="sm"
                  :disabled="!!activeAction"
                  @click="applyToSquadron"
                >
                  {{ activeAction === 'apply' ? 'Applying…' : 'Apply' }}
                </HorizonButton>

                <HorizonButton
                  v-if="permissions?.can_leave"
                  variant="secondary"
                  size="sm"
                  :disabled="!!activeAction"
                  @click="askLeaveSquadron"
                >
                  {{ activeAction === 'leave' ? 'Leaving…' : 'Leave Squadron' }}
                </HorizonButton>
              </div>
            </div>

            <!-- Officer Tools -->
            <div v-if="canEditSquadron || canManageMembers" class="space-y-2">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Officer Tools</div>
              <div class="flex flex-col gap-2">
                <HorizonButton v-if="canEditSquadron" variant="primary" size="sm" class="w-full" @click="openEditModal">Edit Squadron</HorizonButton>
                <div v-if="canManageMembers" class="text-sm text-text-secondary">{{ pendingMembers.length }} pending</div>
              </div>
            </div>

            <!-- Quick Facts -->
            <div class="space-y-2 text-sm">
              <div class="flex justify-between gap-2">
                <span class="text-text-muted">Branch</span>
                <span class="flex items-center gap-2 font-semibold text-horizon-white">
                  <img v-if="branchLogoSrc(squadron.branch)" :src="branchLogoSrc(squadron.branch)" class="h-4 w-4 object-contain" loading="lazy" />
                  {{ formatTitle(squadron.branch) }}
                </span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-text-muted">Division</span>
                <span class="font-semibold text-horizon-white">{{ formatTitle(squadron.division) }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-text-muted">Status</span>
                <span class="font-semibold text-horizon-white">{{ formatTitle(squadron.status) }}</span>
              </div>
              <div class="flex justify-between gap-2">
                <span class="text-text-muted">Recruiting</span>
                <span class="font-semibold text-horizon-white">{{ squadron.recruiting ? 'Open' : 'Closed' }}</span>
              </div>
            </div>

            <!-- Capacity -->
            <div class="space-y-2">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Capacity</div>
              <div class="text-2xl font-semibold text-horizon-white">{{ activeMemberCount }} / {{ maxRosterSize }}</div>
              <div class="h-1.5 overflow-hidden rounded-full bg-white/[0.06]">
                <div
                  class="h-full rounded-full bg-white/[0.3]"
                  :style="{ width: `${rosterPercent}%` }"
                ></div>
              </div>
            </div>

            <!-- Leadership -->
            <div class="space-y-3">
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Leadership</div>
              <div class="space-y-1">
                <div class="text-sm font-semibold text-horizon-white">{{ leader?.rsi_handle ?? leader?.display_name ?? leader?.name ?? 'None Assigned' }}</div>
                <div class="text-xs text-text-muted">Commander</div>
              </div>
              <div class="space-y-1">
                <div class="text-sm font-semibold text-horizon-white">{{ lieutenantMembers.length }} / 2</div>
                <div class="text-xs text-text-muted">Lieutenants</div>
              </div>
            </div>
          </aside>
        </div>
        </div>

        <!-- Edit modal -->
        <div
          v-if="isEditModalOpen"
          class="fixed inset-0 z-40 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
          @click.self="closeEditModal"
        >
          <section class="hz-surface-welcome max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/[0.055]">
            <header class="flex items-start justify-between gap-4 border-b border-white/[0.055] p-5">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-text-secondary)]">
                  Edit Squadron
                </div>
                <h2 class="mt-1 text-xl font-black text-horizon-white">
                  {{ squadron.name }}
                </h2>
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
                  <div class="space-y-3">
                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Emblem</div>

                    <div class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-2xl border border-white/[0.055] bg-[color:var(--horizon-void-800)]">
                      <img
                        v-if="squadron.emblem_url"
                        :src="squadron.emblem?.medium_url || squadron.emblem?.url || squadron.emblem_url"
                        :alt="squadron.emblem?.alt_text || `${squadron.name} emblem`"
                        class="h-full w-full object-contain p-3"
                        loading="lazy"
                      />
                      <div v-else class="text-3xl font-black text-horizon-white">
                        {{ String(squadron.name ?? 'S').slice(0, 1).toUpperCase() }}
                      </div>
                    </div>

                    <div class="space-y-2">
                      <HorizonButton variant="primary" size="sm" class="w-full" :disabled="isSavingEmblem" @click="openEmblemPicker">
                        Choose Emblem
                      </HorizonButton>
                      <HorizonButton variant="ghost" size="sm" class="w-full" :disabled="isSavingEmblem || !squadron.emblem_url" @click="clearEmblem">
                        {{ isSavingEmblem ? 'Updating…' : 'Clear' }}
                      </HorizonButton>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Save Rules</div>
                    <p class="text-sm text-text-secondary">Text fields save together. Emblem changes save immediately after selection.</p>
                  </div>
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

            <footer class="flex items-center justify-end gap-2 border-t border-white/[0.055] p-5">
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







