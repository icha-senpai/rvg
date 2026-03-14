<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import SquadronExpandedPanel from './Components/SquadronExpandedPanel.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage()

const squadrons = computed(() => page.props?.squadrons ?? [])
const activeSquadron = computed(() => page.props?.activeSquadron ?? null)
const isExpanded = computed(() => !!activeSquadron.value)
const activeSquadronId = computed(() => activeSquadron.value?.squadron?.id ?? null)

/* -------------------------------------------------
   Panel controls
------------------------------------------------- */
function openSquadron(squadron) {
  router.get(
    route('squadrons.index', { squadron: squadron?.slug ?? squadron?.id }),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      only: ['activeSquadron'],
    }
  )
}

function closePanel() {
  router.get(route('squadrons.index'), {}, {
    preserveScroll: true,
    preserveState: true,
    only: ['activeSquadron'],
  })
}

function leaderNameColor(squadron) {
  const leader = squadron?.leader ?? null
  const slug = getHighestOrgRoleSlug(leader?.roles, leader?.rank)
  return getOrgRoleColor(slug)
}

function formatTitle(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return ''

  return raw
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(w => (w ? w.charAt(0).toUpperCase() + w.slice(1) : ''))
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
</script>

<template>
  <HorizonContainer class="space-y-10">
    <div class="mx-auto max-w-5xl hz-stack">

      <div class="hz-title-lg">Squadrons</div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="squadron in squadrons"
          :key="squadron.id"
          class="hz-card-soft hz-stack cursor-pointer overflow-hidden border! border-(--horizon-sunset-blue)! p-0! w-full max-w-64 sm:max-w-none mx-auto"
          @click="openSquadron(squadron)"
        >
          <div
            v-if="squadron.emblem_url"
            class="w-full aspect-square bg-bg-surface"
          >
            <img
              :src="squadron.emblem?.medium_url || squadron.emblem?.url || squadron.emblem_url"
              :alt="squadron.emblem?.alt_text || `${squadron.name} emblem`"
              class="w-full h-full object-contain"
              loading="lazy"
            />
          </div>

          <div class="hz-stack p-3! sm:p-6!">
            <div class="hz-title-sm sm:hz-title-md">{{ squadron.name }}</div>

            <div class="hz-caption text-horizon-muted">
              Leader:
              <span :style="leaderNameColor(squadron) ? { color: leaderNameColor(squadron) } : undefined">
                {{ squadron.leader?.rsi_handle ?? squadron.leader?.display_name ?? 'None' }}
              </span>
            </div>

            <div v-if="squadron.branch" class="hz-caption text-horizon-muted flex items-center gap-2">
              <img
                v-if="branchLogoSrc(squadron.branch)"
                :src="branchLogoSrc(squadron.branch)"
                :alt="`${formatTitle(squadron.branch)} logo`"
                class="w-4 h-4 object-contain shrink-0"
                loading="lazy"
              />
              <span>
                {{ formatTitle(squadron.branch) }}<span v-if="squadron.division"> · {{ formatTitle(squadron.division) }}</span>
              </span>
            </div>

            <div class="hz-text-soft">
              {{ squadron.motto ?? 'No motto provided.' }}
            </div>
          </div>
        </div>
      </div>

      <SquadronExpandedPanel
        v-if="isExpanded && activeSquadronId"
        variant="modal"
        :squadronId="activeSquadronId"
        :show-close-button="true"
        @close="closePanel"
      />
    </div>
  </HorizonContainer>
</template>
