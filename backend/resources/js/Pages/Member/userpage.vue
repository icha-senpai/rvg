<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import { isAdmiralPlus } from '@/auth'
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
    noShow: u?.operations_no_show_count ?? 0,
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
      <!-- Personnel command header -->
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
              Identity, readiness, preferred roles, operation history, squadron assignment, and member profile data.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ canEditProfile ? 'Editable Profile' : 'Read Only' }}
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

            <template v-else-if="canEditProfile">
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
            </template>
          </div>
        </div>
      </section>

      <div
        v-if="errorMessage"
        class="rounded-[1.5rem] border border-red-300/25 bg-red-300/10 p-4 text-sm font-semibold text-red-100"
      >
        {{ errorMessage }}
      </div>

      <!-- Identity dossier card -->
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] p-6 ">
        <div class="pointer-events-none absolute inset-0 opacity-20">
          <div class="absolute left-10 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center">
          <div class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-center">
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

                <span class="rounded-full border-transparent bg-white/[0.042] shadow-none px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ opCommitmentLabel }}
                </span>
              </div>

              <div class="mt-4 max-w-2xl text-sm text-text-secondary">
                Roles:
                <span class="text-horizon-white">{{ rolesLabel }}</span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Callsign
              </div>

              <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                {{ me?.callsign || 'Not set' }}
              </div>
            </div>

            <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Style
              </div>

              <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                {{ gameplayStyleLabel }}
              </div>
            </div>

            <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Timezone
              </div>

              <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                {{ me?.timezone || 'Not set' }}
              </div>
            </div>

            <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted">
                Availability
              </div>

              <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                {{ me?.availability_status || 'Not set' }}
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="me?.bio"
          class="hz-surface-welcome relative mt-6 rounded-[1.5rem] border border-white/[0.055] p-4"
        >
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Member Bio
          </div>

          <p class="mt-2 whitespace-pre-line text-sm leading-7 text-text-secondary">
            {{ me.bio }}
          </p>
        </div>
      </section>


      <!-- Operation stats + readiness -->
      <section v-if="canViewRestrictedOperationStats" class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Operation Record
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              Mission Activity
            </h2>

            <p class="mt-1 text-sm text-text-secondary">
              Grouped command and member participation stats for senior leadership review.
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

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  Created
                </div>
                <div class="mt-2 text-3xl font-black text-horizon-white">
                  {{ operationsStats.created }}
                </div>
              </div>

              <div class="rounded-2xl border border-red-300/15 bg-red-300/5 p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  Canceled
                </div>
                <div class="mt-2 text-3xl font-black text-horizon-white">
                  {{ operationsStats.canceled }}
                </div>
              </div>

              <div class="rounded-2xl border border-emerald-300/15 bg-emerald-300/5 p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  Success
                </div>
                <div class="mt-2 text-3xl font-black text-horizon-white">
                  {{ operationsStats.success }}
                </div>
              </div>

              <div class="rounded-2xl border border-red-300/15 bg-red-300/5 p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  Failed
                </div>
                <div class="mt-2 text-3xl font-black text-horizon-white">
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

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  Completed
                </div>
                <div class="mt-2 text-3xl font-black text-horizon-white">
                  {{ operationsStats.completed }}
                </div>
              </div>

              <div class="rounded-2xl border border-red-300/15 bg-red-300/5 p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  No Show
                </div>
                <div class="mt-2 text-3xl font-black text-horizon-white">
                  {{ operationsStats.noShow }}
                </div>
              </div>

              <div class="rounded-2xl border border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.03] p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  Joined
                </div>
                <div class="mt-2 text-3xl font-black text-horizon-white">
                  {{ operationsStats.joined }}
                </div>
              </div>

              <div class="rounded-2xl border border-amber-300/15 bg-amber-300/5 p-4">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                  Left Early
                </div>
                <div class="mt-2 text-3xl font-black text-horizon-white">
                  {{ operationsStats.leftEarly }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Readiness profile -->
      <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-5 ">
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
              <div class="mt-2 text-sm font-semibold text-horizon-white">
                {{ primaryRoleLabel }}
              </div>
            </div>

            <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Secondary Role
              </div>
              <div class="mt-2 text-sm font-semibold text-horizon-white">
                {{ secondaryRoleLabel }}
              </div>
            </div>

            <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Gameplay Style
              </div>
              <div class="mt-2 text-sm font-semibold text-horizon-white">
                {{ gameplayStyleLabel }}
              </div>
            </div>

            <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Operation Commitment
              </div>
              <div class="mt-2 text-sm font-semibold text-horizon-white">
                {{ opCommitmentLabel }}
              </div>
            </div>
          </div>

          <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
              v-for="(value, key) in experienceRatings"
              :key="key"
              class="rounded-2xl border border-white/10 bg-black/10 p-4"
            >
              <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-text-muted">
                {{ key.replace(/_/g, ' ') }}
              </div>

              <div class="mt-2 text-2xl font-black text-horizon-white">
                {{ value ?? '—' }}
              </div>
            </div>
          </div>
        </div>

        <aside class="space-y-6">
          <div class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-5">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Availability
            </div>

            <div class="mt-3 text-2xl font-black text-horizon-white">
              {{ me?.availability_status || 'Not set' }}
            </div>

            <div class="mt-2 text-sm text-text-secondary">
              Timezone:
              <span class="font-semibold text-horizon-white">{{ me?.timezone || 'Not set' }}</span>
            </div>

            <div
              v-if="me?.loa_note"
              class="hz-surface-welcome mt-4 rounded-2xl border border-white/[0.055] p-4"
            >
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                LOA Note
              </div>

              <p class="mt-2 whitespace-pre-line text-sm leading-6 text-text-secondary">
                {{ me.loa_note }}
              </p>
            </div>
          </div>

          <div class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-5">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Squadron Assignment
            </div>

            <div class="mt-4 space-y-2">
              <div
                v-for="squadron in inertiaSquadrons"
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
                v-if="!inertiaSquadrons.length"
                class="hz-surface-welcome rounded-2xl border border-dashed border-white/15 p-4 text-sm text-text-secondary"
              >
                No squadron assignment found.
              </div>
            </div>
          </div>
        </aside>
      </section>

      <!-- Equipment preferences -->
      <section class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-5 ">
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
              {{ normalizeProfileArray(isEditing ? form.favorite_ships : me?.favorite_ships).length }} Ships · {{ normalizeProfileArray(isEditing ? form.favorite_guns : me?.favorite_guns).length }} Guns
            </div>
          </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
          <!-- Ships -->
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
                  No ships match your search.
                </div>
              </div>

              <div class="text-sm text-text-muted">
                Tap to select/unselect. Tap a tag above to remove.
              </div>
            </div>
          </div>

          <!-- Guns -->
          <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Favorite Guns
                </div>

                <div class="mt-1 text-sm text-text-secondary">
                  Preferred personal weapons and combat tools.
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
                  v-for="gun in profilePreviewList(me?.favorite_guns).items"
                  :key="gun"
                  class="max-w-full truncate rounded-full border border-white/[0.055] bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary"
                  :title="gun"
                >
                  {{ gun }}
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
                No favorite guns listed.
              </div>
            </template>

            <div v-else class="space-y-4">
              <div class="flex gap-2">
                <input
                  v-model="newFavoriteGun"
                  type="text"
                  class="hz-input"
                  placeholder="Type a gun name and press Enter…"
                  @keydown.enter.prevent="addFavoriteGun"
                />

                <HorizonButton
                  variant="ghost"
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
                  class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-fuchsia-100 transition hover:bg-[color:var(--horizon-sunset-magenta)]/20"
                  :title="'Remove ' + gun"
                  @click="removeFavoriteGun(gun)"
                >
                  {{ gun }} ✕
                </button>
              </div>

              <div class="text-sm text-text-muted">
                Click a tag to remove it.
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Profile edit console -->
      <section
        v-if="isEditing"
        class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-5 "
      >
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Profile Edit Console
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              Update Personnel Record
            </h2>

            <p class="mt-1 text-sm text-text-secondary">
              Edit bio, availability, preferred roles, commitment, and experience ratings.
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

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
          <div class="space-y-5">
            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Bio
              </div>

              <HorizonInput
                v-model="form.bio"
                type="textarea"
                label=""
                placeholder="Tell the org a little about you…"
              />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Timezone
                </div>

                <HorizonInput
                  v-model="form.timezone"
                  label=""
                  placeholder="e.g. CST, UTC-6, America/Chicago"
                />
              </div>

              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Availability
                </div>

                <HorizonInput
                  v-model="form.availability_status"
                  label=""
                  placeholder="e.g. Evenings, Weekends, On-call"
                />
              </div>
            </div>

            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Vacation / LOA Note
              </div>

              <HorizonInput
                v-model="form.loa_note"
                type="textarea"
                label=""
                placeholder="Optional: leave of absence notes…"
              />
            </div>
          </div>

          <aside class="space-y-5">
            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Callsign
              </div>

              <HorizonInput
                v-model="form.callsign"
                label=""
                placeholder="Your callsign"
              />
            </div>

            <div class="grid gap-4">
              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Primary Role
                </div>

                <HorizonInput
                  v-model="form.primary_role"
                  type="select"
                  label=""
                  :options="roleOptions"
                />
              </div>

              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Secondary Role
                </div>

                <HorizonInput
                  v-model="form.secondary_role"
                  type="select"
                  label=""
                  :options="roleOptions"
                />
              </div>

              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Preferred Gameplay Style
                </div>

                <HorizonInput
                  v-model="form.preferred_gameplay_style"
                  type="select"
                  label=""
                  :options="gameplayStyleOptions"
                />
              </div>

              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Typical Op Commitment
                </div>

                <HorizonInput
                  v-model="form.typical_op_commitment"
                  type="select"
                  label=""
                  :options="opCommitmentOptions"
                />
              </div>
            </div>
          </aside>
        </div>

        <div class="hz-surface-welcome mt-6 rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/20 p-4">
          <div class="mb-4 text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
            Experience Ratings
          </div>

          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
              <div class="mb-3 text-sm font-semibold text-horizon-white">
                {{ item.label }}
              </div>

              <div class="flex gap-2">
                <button
                  v-for="n in 5"
                  :key="item.key + '_' + n"
                  type="button"
                  class="h-4 w-4 rounded-full border border-[color:var(--horizon-sunset-blue)]/50 transition"
                  :class="(form.experience_ratings?.[item.key] ?? 0) >= n ? 'bg-[color:var(--horizon-sunset-blue)] ' : 'bg-transparent'"
                  @click="setExperienceRating(item.key, n)"
                />
              </div>
            </div>
          </div>

          <div class="mt-4 text-sm text-text-muted">
            Click dots to set rating from 1–5.
          </div>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>







