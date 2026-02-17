<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonPanel from '@/Components/HorizonPanel.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonStat from '@/Components/HorizonStat.vue'

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
const isLoading = ref(false)
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
      'Blade', 
      'Glaive', 
      'Talon', 
      'Talon Shrike', 
      'Stinger'
    ],
  },
  {
    label: "Grey's Market (GLSN / Grey's Market)",
    options: [
      'Shiv'
    ],
  },
  {
    label: 'Greycat Industrial',
    options: [
      'ROC', 
      'STV'
    ],
  },
  {
    label: 'Kruger Intergalactic (KRIG)',
    options: [
      'P-52 Merlin', 
      'P-72 Archimedes', 
      'L-21 Wolf', 
      'L-22 Alpha Wolf'
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
      'Guardian MX'
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
      'Galaxy',
      'Perseus',
      'Polaris',
      'Bengal',
      'Apollo',
      'Apollo Triage',
      'Apollo Medivac',
      'Hermes',
      'Salvation',
      'Scorpius',
      'Scorpius Antares',
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
      'Khartu-al',
      "San'tok.yāi",
      'Railen',
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
  const viewer = inertiaUser.value ?? {}
  const roles = viewer?.roles ?? []
  const roleSlugs = roles.map(r => r?.slug).filter(Boolean)

  if (roleSlugs.includes('director') || roleSlugs.includes('tech_director')) return true

  const commanderPlusRoleSlugs = ['commander', 'wing_commander', 'admiral', 'grand_admiral']
  return roleSlugs.some(s => commanderPlusRoleSlugs.includes(s))
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

function extractApiErrorMessage(error, fallback) {
  const status = error?.response?.status ?? null

  if (status === 401 || status === 419) {
    return 'Your session is missing/expired. Please verify again.'
  }

  const errors = error?.response?.data?.errors

  if (errors && typeof errors === 'object') {
    const firstKey = Object.keys(errors)[0]
    const firstValue = firstKey ? errors[firstKey] : null
    const firstMessage = Array.isArray(firstValue) ? firstValue[0] : firstValue

    if (firstMessage) return String(firstMessage)
  }

  return error?.response?.data?.message ?? fallback
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

function extractMeFromApiPayload(payload) {
  const maybe = payload?.data
  if (!maybe) return null
  if (maybe?.data && typeof maybe.data === 'object') return maybe.data
  return maybe
}

async function fetchMe() {
  isLoading.value = true
  errorMessage.value = null

  try {
    const res = await axios.get('/api/v1/me')
    me.value = extractMeFromApiPayload(res.data) ?? me.value
    seedFormFromUser(me.value)
  } catch (e) {
    errorMessage.value = extractApiErrorMessage(e, 'Failed to load your profile.')
  } finally {
    isLoading.value = false
  }
}

async function saveProfile() {
  if (!canEditProfile.value) return
  isSaving.value = true
  errorMessage.value = null

  try {
    const res = await axios.put('/api/v1/me', {
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
    })

    me.value = extractMeFromApiPayload(res.data) ?? me.value
    seedFormFromUser(me.value)
    isEditing.value = false
  } catch (e) {
    errorMessage.value = extractApiErrorMessage(e, 'Failed to save profile.')
  } finally {
    isSaving.value = false
  }
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

  if (isViewingOwnProfile.value) {
    fetchMe()
  }
})

watch(
  () => profileUser.value,
  (next) => {
    me.value = next ?? inertiaUser.value
    isEditing.value = false
    errorMessage.value = null
    seedFormFromUser(me.value)

    if (isViewingOwnProfile.value) {
      fetchMe()
    }
  }
)
</script>

<template>
  <HorizonContainer class="space-y-10">
    <div class="mx-auto max-w-5xl hz-stack">
      <div class="hz-title-lg">{{ canEditProfile ? 'My Profile' : 'User Profile' }}</div>

      <div v-if="errorMessage" class="hz-alert hz-alert-danger">
        {{ errorMessage }}
      </div>

      <HorizonPanel class="!border !border-[color:var(--horizon-sunset-blue)]">
        <div class="hz-row-between gap-4">
          <div class="hz-row gap-4 min-w-0">
            <div class="shrink-0">
              <img
                v-if="me?.discord_avatar"
                :src="me.discord_avatar"
                alt=""
                class="h-28 w-28 rounded-2xl object-cover border border-bg-hover"
              />
              <div
                v-else :style="displayNameColor ? { color: displayNameColor } : undefined"
                class="h-28 w-28 rounded-2xl bg-bg-hover border border-bg-hover flex items-center justify-center text-lg font-semibold"
              >
                {{ String(displayName).slice(0, 1).toUpperCase() }}
              </div>
            </div>

            <div class="min-w-0 hz-stack-sm">
              <div class="hz-title-md truncate">
                {{ displayName }}
              </div>

              <div class="hz-text-muted">
                Rank {{ rankName }}
              </div>

              <div class="hz-text-muted">
                Roles: <span class="hz-text-soft">{{ rolesLabel }}</span>
              </div>
            </div>
          </div>

          <div class="hz-row gap-2 shrink-0">
            <HorizonButton
              v-if="canEditProfile && !isEditing"
              variant="primary"
              size="sm"
              @click="startEdit"
              :disabled="isLoading"
            >
              Edit
            </HorizonButton>

            <template v-else-if="canEditProfile">
              <HorizonButton variant="secondary" size="sm" @click="cancelEdit" :disabled="isSaving">
                Cancel
              </HorizonButton>

              <HorizonButton variant="primary" size="sm" @click="saveProfile" :disabled="isSaving">
                Save
              </HorizonButton>
            </template>
          </div>
        </div>
      </HorizonPanel>

      <HorizonPanel class="!border !border-[color:var(--horizon-sunset-blue)]">
        <div class="hz-stack-sm">
          <div class="hz-section-label">Operation Stats</div>

          <div
            v-if="canViewRestrictedOperationStats"
            class="grid grid-cols-1 gap-4 md:grid-cols-3"
          >
            <HorizonStat
              label="Operations Created"
              :value="operationsStats.created"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Canceled"
              :value="operationsStats.canceled"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Success"
              :value="operationsStats.success"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Failed"
              :value="operationsStats.failed"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Completed"
              :value="operationsStats.completed"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Joined"
              :value="operationsStats.joined"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Left Early"
              :value="operationsStats.leftEarly"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
          </div>

          <div v-else class="grid grid-cols-1 gap-4">
            <HorizonStat
              label="Operations Created"
              :value="operationsStats.created"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Canceled"
              :value="operationsStats.canceled"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Success"
              :value="operationsStats.success"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Failed"
              :value="operationsStats.failed"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
            <HorizonStat
              label="Operations Completed"
              :value="operationsStats.completed"
              class="!border !border-[color:var(--horizon-sunset-blue)]"
            />
          </div>
        </div>
      </HorizonPanel>

      <HorizonPanel class="!border !border-[color:var(--horizon-sunset-blue)]">
        <div class="hz-stack-sm">
          <div class="hz-section-label">About</div>

          <div v-if="!isEditing" class="hz-text-soft whitespace-pre-wrap">
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

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-4">
          <div class="hz-stack-sm">
            <div class="hz-section-label">Timezone</div>

            <div v-if="!isEditing" class="hz-text-soft">
              {{ me?.timezone ?? 'Not set' }}
            </div>

            <HorizonInput v-else v-model="form.timezone" label="" placeholder="e.g. CST, UTC-6, America/Chicago" />
          </div>

          <div class="hz-stack-sm">
            <div class="hz-section-label">Availability</div>

            <div v-if="!isEditing" class="hz-text-soft">
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

        <div class="hz-stack-sm mt-4">
          <div class="hz-section-label">Vacation Note</div>

          <div v-if="!isEditing" class="hz-text-soft whitespace-pre-wrap">
            {{ me?.loa_note ?? 'None' }}
          </div>

          <HorizonInput v-else v-model="form.loa_note" type="textarea" label="" placeholder="Optional: leave of absence notes…" />
        </div>

        <div class="hz-stack-sm mt-4">
          <div class="hz-section-label">Favorite Ships</div>

          <div v-if="!isEditing" class="hz-text-soft whitespace-pre-wrap">
            {{ favoriteShipsLabel }}
          </div>

          <div v-else class="hz-stack-sm">
            <input
              v-model="favoriteShipSearch"
              type="text"
              class="hz-input"
              placeholder="Search ships…"
            />

            <div v-if="form.favorite_ships?.length" class="hz-row gap-2 flex-wrap">
              <button
                v-for="ship in form.favorite_ships"
                :key="ship"
                type="button"
                class="hz-badge hz-badge-warn"
                @click="removeFavoriteShip(ship)"
                :title="'Remove ' + ship"
              >
                {{ ship }}
              </button>
            </div>

            <div class="max-h-72 overflow-auto rounded-xl border border-bg-hover p-3 hz-stack-sm">
              <div
                v-for="group in filteredFavoriteShipOptions"
                :key="group.label"
                class="hz-stack-sm"
              >
                <div class="hz-text-muted text-xs uppercase tracking-wide">
                  {{ group.label }}
                </div>

                <div class="grid grid-cols-1 gap-2">
                  <button
                    v-for="ship in group.options"
                    :key="group.label + '::' + ship"
                    type="button"
                    class="hz-row-between rounded-lg border border-bg-hover px-3 py-2 text-left"
                    :class="form.favorite_ships?.includes(ship) ? 'bg-bg-hover' : ''"
                    @click="toggleFavoriteShip(ship)"
                  >
                    <span class="truncate">{{ ship }}</span>
                    <span v-if="form.favorite_ships?.includes(ship)" class="hz-text-muted text-xs">Selected</span>
                  </button>
                </div>
              </div>

              <div v-if="!filteredFavoriteShipOptions.length" class="hz-text-muted text-sm">
                No ships match your search.
              </div>
            </div>

            <div class="hz-text-muted text-sm">
              Tap to select/unselect. Tap a tag above to remove.
            </div>
          </div>
        </div>

        <div class="hz-stack-sm mt-4">
          <div class="hz-section-label">Favorite Guns</div>

          <div v-if="!isEditing" class="hz-text-soft whitespace-pre-wrap">
            {{ favoriteGunsLabel }}
          </div>

          <div v-else class="hz-stack-sm">
            <div class="hz-row gap-2">
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

            <div v-if="form.favorite_guns?.length" class="hz-row gap-2 flex-wrap">
              <button
                v-for="gun in form.favorite_guns"
                :key="gun"
                type="button"
                class="hz-badge hz-badge-warn"
                @click="removeFavoriteGun(gun)"
                :title="'Remove ' + gun"
              >
                {{ gun }}
              </button>
            </div>

            <div class="hz-text-muted text-sm">
              Click a tag to remove it.
            </div>
          </div>
        </div>
      </HorizonPanel>

      <HorizonPanel class="!border !border-[color:var(--horizon-sunset-blue)]">
        <div class="hz-section-label">Gameplay Profile</div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-4">
          <div class="hz-stack-sm">
            <div class="hz-section-label">Callsign</div>

            <div v-if="!isEditing" class="hz-text-soft">
              {{ me?.callsign ?? 'Not set' }}
            </div>

            <HorizonInput v-else v-model="form.callsign" label="" placeholder="Your callsign" />
          </div>

          <div class="hz-stack-sm">
            <div class="hz-section-label">Typical Op Commitment</div>

            <div v-if="!isEditing" class="hz-text-soft">
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
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-4">
          <div class="hz-stack-sm">
            <div class="hz-section-label">Primary Role</div>

            <div v-if="!isEditing" class="hz-text-soft">
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

          <div class="hz-stack-sm">
            <div class="hz-section-label">Secondary Role</div>

            <div v-if="!isEditing" class="hz-text-soft">
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

        <div class="hz-stack-sm mt-4">
          <div class="hz-section-label">Preferred Gameplay Style</div>

          <div v-if="!isEditing" class="hz-text-soft">
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

        <div class="hz-stack-sm mt-6">
          <div class="hz-section-label">Experience Ratings</div>

          <div class="hz-stack-sm">
            <div class="hz-row-between">
              <div class="hz-text-muted">Space Combat</div>
              <div class="hz-row gap-2">
                <button
                  v-for="n in 5"
                  :key="'space_combat_' + n"
                  type="button"
                  class="h-5 w-5 rounded-full border border-[color:var(--horizon-sunset-blue)]"
                  :class="(isEditing ? form.experience_ratings?.space_combat : experienceRatings.space_combat) >= n ? 'bg-[color:var(--horizon-sunset-blue)]' : 'bg-transparent'"
                  @click="isEditing && setExperienceRating('space_combat', n)"
                />
              </div>
            </div>

            <div class="hz-row-between">
              <div class="hz-text-muted">Ground Combat</div>
              <div class="hz-row gap-2">
                <button
                  v-for="n in 5"
                  :key="'ground_combat_' + n"
                  type="button"
                  class="h-5 w-5 rounded-full border border-[color:var(--horizon-sunset-blue)]"
                  :class="(isEditing ? form.experience_ratings?.ground_combat : experienceRatings.ground_combat) >= n ? 'bg-[color:var(--horizon-sunset-blue)]' : 'bg-transparent'"
                  @click="isEditing && setExperienceRating('ground_combat', n)"
                />
              </div>
            </div>

            <div class="hz-row-between">
              <div class="hz-text-muted">Logistics / Support</div>
              <div class="hz-row gap-2">
                <button
                  v-for="n in 5"
                  :key="'logistics_support_' + n"
                  type="button"
                  class="h-5 w-5 rounded-full border border-[color:var(--horizon-sunset-blue)]"
                  :class="(isEditing ? form.experience_ratings?.logistics_support : experienceRatings.logistics_support) >= n ? 'bg-[color:var(--horizon-sunset-blue)]' : 'bg-transparent'"
                  @click="isEditing && setExperienceRating('logistics_support', n)"
                />
              </div>
            </div>

            <div class="hz-row-between">
              <div class="hz-text-muted">Medical</div>
              <div class="hz-row gap-2">
                <button
                  v-for="n in 5"
                  :key="'medical_' + n"
                  type="button"
                  class="h-5 w-5 rounded-full border border-[color:var(--horizon-sunset-blue)]"
                  :class="(isEditing ? form.experience_ratings?.medical : experienceRatings.medical) >= n ? 'bg-[color:var(--horizon-sunset-blue)]' : 'bg-transparent'"
                  @click="isEditing && setExperienceRating('medical', n)"
                />
              </div>
            </div>
          </div>

          <div v-if="isEditing" class="hz-text-muted text-sm">
            Click dots to set rating (1–5).
          </div>
        </div>
      </HorizonPanel>

      <HorizonPanel
        v-if="canEditProfile && inertiaSquadrons && inertiaSquadrons.length"
        class="!border !border-[color:var(--horizon-sunset-blue)]"
      >
        <div class="hz-section-label">Squadrons</div>

        <div class="hz-stack-sm">
          <div
            v-for="s in inertiaSquadrons"
            :key="s.id"
            class="hz-card-soft hz-row-between !border !border-[color:var(--horizon-sunset-blue)]"
          >
            <div class="hz-row gap-3 items-center">
              <div
                v-if="s?.emblem_url"
                class="w-24 h-24 rounded-lg overflow-hidden border border-[color:var(--horizon-sunset-blue)] bg-bg-surface shrink-0"
              >
                <img
                  :src="s?.emblem?.thumbnail_url || s?.emblem?.medium_url || s?.emblem?.url || s?.emblem_url"
                  :alt="s?.emblem?.alt_text || `${s?.name} emblem`"
                  class="w-full h-full object-contain"
                  loading="lazy"
                />
              </div>

              <div class="hz-stack-sm">
                <div class="hz-title-md">{{ s.name }}</div>
                <div class="hz-text-muted">
                  {{ s?.pivot?.membership_status ?? 'unknown' }} · {{ s?.pivot?.role ?? 'member' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </HorizonPanel>

      <div v-if="isLoading" class="hz-soft">Loading profile…</div>
    </div>
  </HorizonContainer>
</template>
