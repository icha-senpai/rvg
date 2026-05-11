<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonPanel from '@/Components/HorizonPanel.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonStat from '@/Components/HorizonStat.vue'
import { isCommanderPlus } from '@/auth'
import { extractFirstErrorMessage as extractSharedErrorMessage } from '@/errors'

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage()

const inertiaUser = computed(() => page.props?.auth?.user ?? null)
const inertiaSquadrons = computed(() => inertiaUser.value?.squadrons ?? [])

const profileUser = computed(() => page.props?.profileUser ?? null)
const isViewingOwnProfile = computed(() => {
  const authedId = inertiaUser.value?.id
  const targetId = (profileUser.value ?? inertiaUser.value)?.id
  if (!authedId || !targetId) return true
  return Number(authedId) === Number(targetId)
})
const canEditProfile = computed(() => isViewingOwnProfile.value)

const me = ref(profileUser.value ?? inertiaUser.value)
const errorMessage = ref(null)

const isEditing = ref(false)
const isSaving = ref(false)

const form = ref({
  bio: '',
  timezone: '',
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

const newFavoriteGun = ref('')

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

const favoriteShipSearch = ref('')

const favoriteShipOptions = [
  {
    label: 'Aegis Dynamics (AEGS)',
    options: [
      'Avenger Titan',
      'Avenger Titan Renegade',
      'Avenger Stalker',
      'Avenger Warlock',
      'Gladius',
      'Gladius Valiant',
      'Gladius Pirate Edition',
      'Sabre',
      'Sabre Comet',
      'Sabre Raven',
      'Sabre Firebird',
      'Sabre Peregrine',
      'Vanguard Warden',
      'Vanguard Harbinger',
      'Vanguard Sentinel',
      'Vanguard Hoplite',
      'Retaliator Bomber',
      'Retaliator Living quarters / other modules',
      'Eclipse',
      'Eclipse Best in Show Edition',
      'Hammerhead',
      'Hammerhead Best In Show Edition',
      'Reclaimer',
      'Reclaimer Best In Show Edition',
      'Idris-P',
      'Idris-M',
      'Idris-K',
      'Redeemer',
      'Javelin',
      'Nautilus',
      'Nautilus Solstice Edition',
      'Vulcan',
    ],
  },
  {
    label: 'Anvil Aerospace (ANVL)',
    options: [
      'F7C Hornet',
      'F7C Hornet Wildfire',
      'F7A Hornet',
      'F7C-S Hornet Ghost',
      'F7C-R Hornet Tracker',
      'F7C-M Super Hornet',
      'F7C-M Super Hornet Heartseeker',
      'F7C Hornet Mk II',
      'F7C-R Hornet Tracker Mk II',
      'F7C-S Hornet Ghost Mk II',
      'F7C-M Super Hornet Mk II',
      'F7A Hornet Mk II',
      'Arrow',
      'Gladiator',
      'Hawk',
      'Hurricane',
      'Crucible',
      'Terrapin',
      'Terrapin Medic',
      'Valkyrie',
      'Valkyrie Liberator Edition',
      'Carrack',
      'Carrack w/C8X Pisces',
      'Carrack Expedition',
      'Carrack Expedition w/C8X',
      'Liberator',
      'Spartan',
      'Centurion',
      'Legionnaire',
      'Ballista',
      'Ballista Snowblind',
      'Ballista Dunestalker',
      'C8 Pisces',
      'C8X Pisces Expedition',
      'C8R Pisces Rescue',
      'F8C Lightning',
      'F8C Lightning Executive-Edition',
      'Paladin',
      'Asgard',
    ],
  },
  {
    label: 'ARGO Astronautics (ARGO)',
    options: [
      'MPUV Personnel',
      'MPUV Cargo',
      'MPUV Tractor',
      'Moth',
      'MOLE',
      'MOLE Carbon',
      'MOLE Talus',
      'RAFT',
      'SRV',
      'ATLS',
      'ATLS GEO',
      'CSV-SM',
    ],
  },
  {
    label: 'Banu Souli (BANU)',
    options: [
      'Defender', 
      'Merchantman'
    ],
  },
  {
    label: 'Consolidated Outland (CNOU)',
    options: [
      'Mustang Alpha', 
      'Mustang Beta', 
      'Mustang Delta', 
      'Mustang Gamma', 
      'Mustang Omega', 
      'Nomad', 
      'Pioneer'
    ],
  },
  {
    label: 'Crusader Industries (CRUS)',
    options: [
      'Mercury Star Runner',
      'C2 Hercules',
      'M2 Hercules',
      'A2 Hercules',
      'Ares Inferno',
      'Ares Ion',
      'C1 Spirit',
      'E1 Spirit',
      'A1 Spirit',
      'Genesis Starliner',
      'Intrepid',
    ],
  },
  {
    label: 'Drake Interplanetary (DRAK)',
    options: [
      'Cutlass Black',
      'Cutlass Blue',
      'Cutlass Red',
      'Cutlass Steel',
      'Corsair',
      'Caterpillar',
      'Caterpillar Best in Show Edition',
      'Dragonfly',
      'Dragonfly Yellowjacket',
      'Dragonfly Black',
      'Buccaneer',
      'Herald',
      'Kraken',
      'Kraken Privateer',
      'Ironclad',
      'Ironclad Assault',
    ],
  },
  {
    label: 'Esperia (ESPR)',
    options: [
      'Prowler',
      'Prowler Utility', 
      'Blade', 
      'Glaive', 
      'Talon', 
      'Talon Shrike', 
      'Stinger',
    ],
  },
  {
    label: "Grey's Market (GLSN / Grey's Market)",
    options: [
      'Shiv',
    ],
  },
  {
    label: 'Greycat Industrial',
    options: [
      'ROC', 
      'STV',
      'Cydnus',
    ],
  },
  {
    label: 'Kruger Intergalactic (KRIG)',
    options: [
      'P-52 Merlin', 
      'P-72 Archimedes', 
      'L-21 Wolf', 
      'L-22 Alpha Wolf',
    ],
  },
  {
    label: 'MISC (MISC)',
    options: [
      'Freelancer',
      'Freelancer MAX',
      'Freelancer DUR',
      'Freelancer MIS',
      'Starfarer',
      'Starfarer Gemini',
      'Prospector',
      'Hull A',
      'Hull B',
      'Hull C',
      'Hull D',
      'Hull E',
      'Endeavor',
      'Expanse',
      'Razor',
      'Razor EX',
      'Razor LX',
      'Reliant Kore',
      'Reliant Mako',
      'Reliant Sen',
      'Reliant Tana',
    ],
  },
  {
    label: 'Mirai (MRAI)',
    options: [
      'Fury',
      'Fury LX', 
      'Fury MX', 
      'Guardian', 
      'Guardian QI', 
      'Guardian MX',
    ],
  },
  {
    label: 'Origin Jumpworks (ORIG)',
    options: [
      '100i',
      '125a',
      '135c',
      '300i',
      '315p',
      '325a',
      '350r',
      '400i',
      '600i Touring',
      '600i Explorer',
      '890 Jump',
      'X1',
      '85x',
      'M50',
    ],
  },
  {
    label: 'Roberts Space Industries (RSI)',
    options: [
      'Aurora ES',
      'Aurora MR',
      'Aurora LN',
      'Aurora LX',
      'Aurora CL',
      'Constellation Andromeda',
      'Constellation Taurus',
      'Constellation Aquila',
      'Constellation Phoenix',
      'Constellation Phoenix Emerald',
      'Galaxy',
      'Perseus',
      'Pegasus',
      'Polaris',
      'Bengal',
      'Apollo',
      'Apollo Triage',
      'Apollo Medivac',
      'Hermes',
      'Salvation',
      'Scorpius',
      'Scorpius Antares',
      'Zeus',
      'Zeus mkII',
      'Zeus mkII CL',
      'Zeus mkII ES',
      'Zeus mkII MR',
      'Zeus mkII ST',
    ],
  },
  {
    label: 'Tumbril',
    options: [
      'Cyclone',
      'Cyclone-RC',
      'Cyclone-RN',
      'Cyclone-RR',
      'Cyclone-TR',
      'Nova',
    ],
  },
  {
    label: 'Other / Alien / Rare',
    options: [
      'Retribution',
      'Khartu-al',
      "San'tok.yāi",
      'Railen',
      'Syulen',
      'Cleaver',
      'Driller',
      'Harvester',
      'Hunter',
      'Kingship',
      'Mualer',
      'Scythe',
      'Stinger',
      'Void',
    ],
  },
]

const displayName = computed(() => {
  const u = me.value
  return u?.rsi_handle ?? u?.discord_name ?? u?.name ?? 'Member'
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
  return isCommanderPlus(inertiaUser.value)
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

function addFavoriteGun() {
  const value = String(newFavoriteGun.value ?? '').trim()
  if (!value) return

  const current = Array.isArray(form.value.favorite_guns) ? form.value.favorite_guns : []
  if (!current.includes(value)) {
    form.value.favorite_guns = [...current, value]
  }

  newFavoriteGun.value = ''
}

function removeFavoriteGun(gun) {
  const current = Array.isArray(form.value.favorite_guns) ? form.value.favorite_guns : []
  form.value.favorite_guns = current.filter(g => g !== gun)
}

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

const filteredFavoriteShipOptions = computed(() => {
  const query = String(favoriteShipSearch.value ?? '').trim().toLowerCase()
  if (!query) return favoriteShipOptions

  return favoriteShipOptions
    .map(group => {
      const options = (group.options ?? []).filter(ship => String(ship).toLowerCase().includes(query))
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
    leftEarly: u?.operations_left_early_count ?? 0,
  }
})

function extractFirstErrorMessage(errors, fallback) {
  return extractSharedErrorMessage(errors, fallback)
}

function seedFormFromUser(user) {
  form.value = {
    bio: user?.bio ?? '',
    timezone: user?.timezone ?? '',
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
  isEditing.value = true
}

function cancelEdit() {
  seedFormFromUser(me.value ?? inertiaUser.value)
  favoriteShipSearch.value = ''
  isEditing.value = false
}

onMounted(() => {
  seedFormFromUser(me.value)
})

watch(
  () => profileUser.value,
  (next) => {
    me.value = next ?? inertiaUser.value
    isEditing.value = false
    errorMessage.value = null
    seedFormFromUser(me.value)
  }
)
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-6xl space-y-8">
      <!-- Page header -->
      <div class="relative overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/40 bg-[radial-gradient(circle_at_top_left,rgba(56,189,248,0.18),transparent_34%),radial-gradient(circle_at_top_right,rgba(192,38,211,0.16),transparent_32%),linear-gradient(135deg,rgba(27,32,53,0.92),rgba(11,13,20,0.96))] p-6 shadow-[0_0_48px_rgba(56,189,248,0.14)]">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-cyan-300 to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-fuchsia-400 to-transparent"></div>
        </div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-cyan-200/70">
              Horizon Personnel File
            </div>

            <h1 class="mt-2 text-3xl font-bold tracking-tight text-horizon-white md:text-4xl">
              {{ canEditProfile ? 'My Profile' : 'User Profile' }}
            </h1>

            <p class="mt-2 max-w-2xl text-sm text-text-secondary">
              Identity, readiness, preferred roles, operation history, and squadron assignment.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <HorizonButton
              v-if="canEditProfile && !isEditing"
              variant="primary"
              size="sm"
              @click="startEdit"
            >
              Edit Profile
            </HorizonButton>

            <template v-else-if="canEditProfile">
              <HorizonButton variant="secondary" size="sm" @click="cancelEdit" :disabled="isSaving">
                Cancel
              </HorizonButton>

              <HorizonButton variant="primary" size="sm" @click="saveProfile" :disabled="isSaving">
                {{ isSaving ? 'Saving…' : 'Save Changes' }}
              </HorizonButton>
            </template>
          </div>
        </div>
      </div>

      <div v-if="errorMessage" class="hz-alert hz-alert-danger">
        {{ errorMessage }}
      </div>

      <!-- Hero identity card -->
      <section class="relative overflow-hidden rounded-[2rem] border border-cyan-300/20 bg-[linear-gradient(135deg,rgba(28,58,94,0.55),rgba(21,25,42,0.92)_42%,rgba(11,13,20,0.96))] p-6 shadow-[0_0_40px_rgba(56,189,248,0.10)]">
        <div class="absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_center,rgba(192,38,211,0.13),transparent_55%)]"></div>

        <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
          <div class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-center">
            <div class="relative shrink-0">
              <div class="absolute -inset-2 rounded-[2rem] bg-gradient-to-br from-cyan-300/30 via-transparent to-fuchsia-500/25 blur-xl"></div>

              <img
                v-if="me?.discord_avatar"
                :src="me.discord_avatar"
                alt=""
                class="relative h-32 w-32 rounded-[1.7rem] border border-cyan-300/30 object-cover shadow-[0_0_28px_rgba(56,189,248,0.22)]"
              />

              <div
                v-else
                :style="displayNameColor ? { color: displayNameColor } : undefined"
                class="relative flex h-32 w-32 items-center justify-center rounded-[1.7rem] border border-cyan-300/30 bg-bg-hover text-4xl font-black shadow-[0_0_28px_rgba(56,189,248,0.22)]"
              >
                {{ String(displayName).slice(0, 1).toUpperCase() }}
              </div>
            </div>

            <div class="min-w-0">
              <div class="text-xs font-bold uppercase tracking-[0.22em] text-text-muted">
                Registered Member
              </div>

              <div
                class="mt-2 truncate text-3xl font-black tracking-tight text-horizon-white md:text-4xl"
                :style="displayNameColor ? { color: displayNameColor } : undefined"
              >
                {{ displayName }}
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <span class="rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-semibold text-cyan-100">
                  Rank {{ rankName }}
                </span>

                <span class="rounded-full border border-fuchsia-300/20 bg-fuchsia-300/10 px-3 py-1 text-xs font-semibold text-fuchsia-100">
                  {{ primaryRoleLabel }}
                </span>

                <span class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ opCommitmentLabel }}
                </span>
              </div>

              <div class="mt-4 max-w-2xl text-sm text-text-secondary">
                Roles:
                <span class="text-horizon-white">{{ rolesLabel }}</span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 md:w-80">
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Callsign
              </div>
              <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                {{ me?.callsign ?? 'Not set' }}
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Style
              </div>
              <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                {{ gameplayStyleLabel }}
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Operation stats -->
      <section class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/35 bg-bg-elevated/80 p-5 shadow-[0_0_32px_rgba(192,38,211,0.08)]">
        <div class="mb-5 flex items-center justify-between gap-4">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-cyan-200/70">
              Operation Stats
            </div>
            <div class="mt-1 text-sm text-text-muted">
              Mission activity and command contribution.
            </div>
          </div>
        </div>

        <div
          v-if="canViewRestrictedOperationStats"
          class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-7"
        >
          <div class="rounded-2xl border border-cyan-300/15 bg-[linear-gradient(135deg,rgba(56,189,248,0.12),rgba(255,255,255,0.025))] p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Created</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.created }}</div>
          </div>

          <div class="rounded-2xl border border-cyan-300/15 bg-white/[0.03] p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Canceled</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.canceled }}</div>
          </div>

          <div class="rounded-2xl border border-emerald-300/15 bg-emerald-300/5 p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Success</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.success }}</div>
          </div>

          <div class="rounded-2xl border border-red-300/15 bg-red-300/5 p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Failed</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.failed }}</div>
          </div>

          <div class="rounded-2xl border border-cyan-300/15 bg-white/[0.03] p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Completed</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.completed }}</div>
          </div>

          <div class="rounded-2xl border border-cyan-300/15 bg-white/[0.03] p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Joined</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.joined }}</div>
          </div>

          <div class="rounded-2xl border border-amber-300/15 bg-amber-300/5 p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Left Early</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.leftEarly }}</div>
          </div>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <div class="rounded-2xl border border-cyan-300/15 bg-[linear-gradient(135deg,rgba(56,189,248,0.12),rgba(255,255,255,0.025))] p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Created</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.created }}</div>
          </div>

          <div class="rounded-2xl border border-cyan-300/15 bg-white/[0.03] p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Canceled</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.canceled }}</div>
          </div>

          <div class="rounded-2xl border border-emerald-300/15 bg-emerald-300/5 p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Success</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.success }}</div>
          </div>

          <div class="rounded-2xl border border-red-300/15 bg-red-300/5 p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Failed</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.failed }}</div>
          </div>

          <div class="rounded-2xl border border-cyan-300/15 bg-white/[0.03] p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">Completed</div>
            <div class="mt-2 text-3xl font-black text-horizon-white">{{ operationsStats.completed }}</div>
          </div>
        </div>
      </section>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <!-- About / readiness -->
        <section class="rounded-[2rem] border border-cyan-300/20 bg-bg-elevated/80 p-6 shadow-[0_0_32px_rgba(56,189,248,0.08)]">
          <div class="mb-5">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-cyan-200/70">
              About
            </div>
            <div class="mt-1 text-sm text-text-muted">
              Personal notes, availability, and preferred equipment.
            </div>
          </div>

          <div class="space-y-5">
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div class="mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Bio
              </div>

              <div v-if="!isEditing" class="whitespace-pre-wrap text-sm leading-7 text-text-secondary">
                {{ me?.bio ?? 'No bio set.' }}
              </div>

              <HorizonInput
                v-else
                v-model="form.bio"
                type="textarea"
                label=""
                placeholder="Tell the org a little about you…"
              />
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div class="mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                  Timezone
                </div>

                <div v-if="!isEditing" class="text-sm font-semibold text-horizon-white">
                  {{ me?.timezone ?? 'Not set' }}
                </div>

                <HorizonInput v-else v-model="form.timezone" label="" placeholder="e.g. CST, UTC-6, America/Chicago" />
              </div>

              <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div class="mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                  Availability
                </div>

                <div v-if="!isEditing" class="text-sm font-semibold text-horizon-white">
                  {{ me?.availability_status ?? 'Not set' }}
                </div>

                <HorizonInput
                  v-else
                  v-model="form.availability_status"
                  label=""
                  placeholder="e.g. Evenings, Weekends, On-call"
                />
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div class="mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Vacation Note
              </div>

              <div v-if="!isEditing" class="whitespace-pre-wrap text-sm leading-7 text-text-secondary">
                {{ me?.loa_note ?? 'None' }}
              </div>

              <HorizonInput v-else v-model="form.loa_note" type="textarea" label="" placeholder="Optional: leave of absence notes…" />
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div class="mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Favorite Ships
              </div>

              <div v-if="!isEditing" class="text-sm leading-7 text-text-secondary">
                {{ favoriteShipsLabel }}
              </div>

              <div v-else class="space-y-3">
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
                    class="rounded-full border border-cyan-300/25 bg-cyan-300/10 px-3 py-1 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-300/20"
                    @click="removeFavoriteShip(ship)"
                    :title="'Remove ' + ship"
                  >
                    {{ ship }}
                  </button>
                </div>

                <div class="max-h-72 overflow-auto rounded-2xl border border-white/10 bg-bg-base/50 p-3">
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
                            ? 'border-cyan-300/35 bg-cyan-300/10 text-horizon-white'
                            : 'border-white/10 bg-white/[0.025] text-text-secondary hover:border-cyan-300/25 hover:bg-white/[0.05] hover:text-horizon-white'
                        "
                        @click="toggleFavoriteShip(ship)"
                      >
                        <span class="truncate">{{ ship }}</span>
                        <span v-if="form.favorite_ships?.includes(ship)" class="shrink-0 text-xs text-cyan-200">Selected</span>
                      </button>
                    </div>
                  </div>

                  <div v-if="!filteredFavoriteShipOptions.length" class="text-sm text-text-muted">
                    No ships match your search.
                  </div>
                </div>

                <div class="text-sm text-text-muted">
                  Tap to select/unselect. Tap a tag above to remove.
                </div>
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div class="mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Favorite Guns
              </div>

              <div v-if="!isEditing" class="text-sm leading-7 text-text-secondary">
                {{ favoriteGunsLabel }}
              </div>

              <div v-else class="space-y-3">
                <div class="flex gap-2">
                  <input
                    v-model="newFavoriteGun"
                    type="text"
                    class="hz-input"
                    placeholder="Type a gun name and press Enter…"
                    @keydown.enter.prevent="addFavoriteGun"
                  />

                  <HorizonButton
                    variant="secondary"
                    size="sm"
                    type="button"
                    @click="addFavoriteGun"
                  >
                    Add
                  </HorizonButton>
                </div>

                <div v-if="form.favorite_guns?.length" class="flex flex-wrap gap-2">
                  <button
                    v-for="gun in form.favorite_guns"
                    :key="gun"
                    type="button"
                    class="rounded-full border border-fuchsia-300/25 bg-fuchsia-300/10 px-3 py-1 text-xs font-semibold text-fuchsia-100 transition hover:bg-fuchsia-300/20"
                    @click="removeFavoriteGun(gun)"
                    :title="'Remove ' + gun"
                  >
                    {{ gun }}
                  </button>
                </div>

                <div class="text-sm text-text-muted">
                  Click a tag to remove it.
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Gameplay profile -->
        <section class="rounded-[2rem] border border-fuchsia-300/20 bg-[linear-gradient(160deg,rgba(21,25,42,0.92),rgba(11,13,20,0.96))] p-6 shadow-[0_0_32px_rgba(192,38,211,0.08)]">
          <div class="mb-5">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-fuchsia-200/70">
              Gameplay Profile
            </div>
            <div class="mt-1 text-sm text-text-muted">
              Role preference, playstyle, and skill confidence.
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4">
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Callsign</div>

              <div v-if="!isEditing" class="mt-2 text-lg font-bold text-horizon-white">
                {{ me?.callsign ?? 'Not set' }}
              </div>

              <HorizonInput v-else v-model="form.callsign" label="" placeholder="Your callsign" />
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-1">
              <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Primary Role</div>

                <div v-if="!isEditing" class="mt-2 text-sm font-semibold text-horizon-white">
                  {{ primaryRoleLabel }}
                </div>

                <HorizonInput
                  v-else
                  v-model="form.primary_role"
                  type="select"
                  label=""
                  :options="roleOptions"
                />
              </div>

              <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Secondary Role</div>

                <div v-if="!isEditing" class="mt-2 text-sm font-semibold text-horizon-white">
                  {{ secondaryRoleLabel }}
                </div>

                <HorizonInput
                  v-else
                  v-model="form.secondary_role"
                  type="select"
                  label=""
                  :options="roleOptions"
                />
              </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Preferred Gameplay Style</div>

              <div v-if="!isEditing" class="mt-2 text-sm font-semibold text-horizon-white">
                {{ gameplayStyleLabel }}
              </div>

              <HorizonInput
                v-else
                v-model="form.preferred_gameplay_style"
                type="select"
                label=""
                :options="gameplayStyleOptions"
              />
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">Typical Op Commitment</div>

              <div v-if="!isEditing" class="mt-2 text-sm font-semibold text-horizon-white">
                {{ opCommitmentLabel }}
              </div>

              <HorizonInput
                v-else
                v-model="form.typical_op_commitment"
                type="select"
                label=""
                :options="opCommitmentOptions"
              />
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div class="mb-4 text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Experience Ratings
              </div>

              <div class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                  <div class="text-sm text-text-secondary">Space Combat</div>
                  <div class="flex gap-2">
                    <button
                      v-for="n in 5"
                      :key="'space_combat_' + n"
                      type="button"
                      class="h-4 w-4 rounded-full border border-cyan-300/50 transition"
                      :class="(isEditing ? form.experience_ratings?.space_combat : experienceRatings.space_combat) >= n ? 'bg-cyan-300 shadow-[0_0_12px_rgba(56,189,248,0.75)]' : 'bg-transparent'"
                      @click="isEditing && setExperienceRating('space_combat', n)"
                    />
                  </div>
                </div>

                <div class="flex items-center justify-between gap-4">
                  <div class="text-sm text-text-secondary">Ground Combat</div>
                  <div class="flex gap-2">
                    <button
                      v-for="n in 5"
                      :key="'ground_combat_' + n"
                      type="button"
                      class="h-4 w-4 rounded-full border border-cyan-300/50 transition"
                      :class="(isEditing ? form.experience_ratings?.ground_combat : experienceRatings.ground_combat) >= n ? 'bg-cyan-300 shadow-[0_0_12px_rgba(56,189,248,0.75)]' : 'bg-transparent'"
                      @click="isEditing && setExperienceRating('ground_combat', n)"
                    />
                  </div>
                </div>

                <div class="flex items-center justify-between gap-4">
                  <div class="text-sm text-text-secondary">Logistics / Support</div>
                  <div class="flex gap-2">
                    <button
                      v-for="n in 5"
                      :key="'logistics_support_' + n"
                      type="button"
                      class="h-4 w-4 rounded-full border border-cyan-300/50 transition"
                      :class="(isEditing ? form.experience_ratings?.logistics_support : experienceRatings.logistics_support) >= n ? 'bg-cyan-300 shadow-[0_0_12px_rgba(56,189,248,0.75)]' : 'bg-transparent'"
                      @click="isEditing && setExperienceRating('logistics_support', n)"
                    />
                  </div>
                </div>

                <div class="flex items-center justify-between gap-4">
                  <div class="text-sm text-text-secondary">Medical</div>
                  <div class="flex gap-2">
                    <button
                      v-for="n in 5"
                      :key="'medical_' + n"
                      type="button"
                      class="h-4 w-4 rounded-full border border-cyan-300/50 transition"
                      :class="(isEditing ? form.experience_ratings?.medical : experienceRatings.medical) >= n ? 'bg-cyan-300 shadow-[0_0_12px_rgba(56,189,248,0.75)]' : 'bg-transparent'"
                      @click="isEditing && setExperienceRating('medical', n)"
                    />
                  </div>
                </div>
              </div>

              <div v-if="isEditing" class="mt-4 text-sm text-text-muted">
                Click dots to set rating from 1–5.
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Squadrons -->
      <section
        v-if="canEditProfile && inertiaSquadrons && inertiaSquadrons.length"
        class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/35 bg-bg-elevated/80 p-6 shadow-[0_0_32px_rgba(56,189,248,0.08)]"
      >
        <div class="mb-5">
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-cyan-200/70">
            Squadrons
          </div>
          <div class="mt-1 text-sm text-text-muted">
            Active squadron membership and internal assignment.
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div
            v-for="s in inertiaSquadrons"
            :key="s.id"
            class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:border-cyan-300/25 hover:bg-white/[0.05]"
          >
            <div class="flex min-w-0 items-center gap-4">
              <div
                v-if="s?.emblem_url"
                class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-cyan-300/25 bg-bg-surface"
              >
                <img
                  :src="s?.emblem?.thumbnail_url || s?.emblem?.medium_url || s?.emblem?.url || s?.emblem_url"
                  :alt="s?.emblem?.alt_text || `${s?.name} emblem`"
                  class="h-full w-full object-contain"
                  loading="lazy"
                />
              </div>

              <div class="min-w-0">
                <div class="truncate text-lg font-bold text-horizon-white">
                  {{ s.name }}
                </div>

                <div class="mt-1 text-sm text-text-muted">
                  {{ s?.pivot?.membership_status ?? 'unknown' }} · {{ s?.pivot?.role ?? 'member' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>