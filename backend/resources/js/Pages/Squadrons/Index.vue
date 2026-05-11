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
      <div class="relative overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/45 bg-[radial-gradient(circle_at_top_left,var(--horizon-glow-blue),transparent_34%),radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_32%),linear-gradient(135deg,var(--horizon-void-600),var(--horizon-void-900))] p-6 shadow-[0_0_48px_rgba(67,56,202,0.18)]">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative">
          <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
            Horizon Squadron Registry
          </div>

          <h1 class="mt-2 text-3xl font-bold tracking-tight text-horizon-white md:text-4xl">
            Squadrons
          </h1>

          <p class="mt-2 max-w-2xl text-sm text-text-secondary">
            Explore combat, logistics, frontier, and support units across Horizon Interstellar.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="squadron in squadrons"
          :key="squadron.id"
          class="group relative mx-auto w-full max-w-64 cursor-pointer overflow-hidden rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/30 bg-[linear-gradient(135deg,rgba(30,64,175,0.18),var(--horizon-void-700)_42%,var(--horizon-void-900))] p-0 shadow-[0_0_32px_rgba(30,64,175,0.10)] transition duration-200 hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-magenta)]/45 hover:shadow-[0_0_42px_rgba(192,38,211,0.16)] sm:max-w-none"
          @click="openSquadron(squadron)"
        >
          <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-200 group-hover:opacity-100">
            <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
            <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
          </div>

          <div
            v-if="squadron.emblem_url"
            class="relative aspect-square w-full bg-[color:var(--horizon-void-800)]"
          >
            <div class="absolute inset-4 rounded-[1.25rem] bg-gradient-to-br from-[color:var(--horizon-sunset-blue)]/10 via-transparent to-[color:var(--horizon-sunset-magenta)]/10 blur-xl"></div>

            <img
              :src="squadron.emblem?.medium_url || squadron.emblem?.url || squadron.emblem_url"
              :alt="squadron.emblem?.alt_text || `${squadron.name} emblem`"
              class="relative h-full w-full object-contain p-4"
              loading="lazy"
            />
          </div>

          <div class="relative hz-stack p-3! sm:p-6!">
            <div class="hz-title-sm sm:hz-title-md">
              {{ squadron.name }}
            </div>

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
                class="h-4 w-4 shrink-0 object-contain"
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