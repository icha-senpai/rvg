<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

import HorizonContainer from '@/Components/HorizonContainer.vue'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const page = usePage()

const squadrons = computed(() => page.props?.squadrons ?? [])

function leaderNameColor(squadron) {
  const leader = squadron?.leader ?? null
  const slug = getHighestOrgRoleSlug(leader?.roles, leader?.rank)
  return getOrgRoleColor(slug)
}

function formatTitle(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return 'Not set'

  return raw
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(word => (word ? word.charAt(0).toUpperCase() + word.slice(1) : ''))
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

function squadronHref(squadron) {
  if (squadron?.slug) {
    return route('squadrons.show', squadron.slug)
  }

  return `/squadrons/${squadron.id}`
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <!-- Header -->
      <div class="border-b border-white/[0.055] pb-5">
        <div class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-text-secondary)]">
          Horizon Squadron Registry
        </div>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-4xl">
          Squadrons
        </h1>
      </div>

      <!-- Empty state -->
      <section
        v-if="!squadrons.length"
        class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-6 text-text-secondary"
      >
        No squadrons are currently listed.
      </section>

      <!-- Squadron registry grid -->
      <section v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="squadron in squadrons"
          :key="squadron.id"
          :href="squadronHref(squadron)"
          class="hz-surface-welcome group relative flex min-h-full flex-col overflow-hidden rounded-2xl border border-white/[0.055] transition hover:border-white/[0.08]"
        >
          <!-- Emblem area -->
          <div class="relative aspect-square w-full overflow-hidden bg-[color:var(--horizon-void-800)]">

            <img
              v-if="squadron.emblem_url"
              :src="squadron.emblem?.medium_url || squadron.emblem?.url || squadron.emblem_url"
              :alt="squadron.emblem?.alt_text || `${squadron.name} emblem`"
              class="relative h-full w-full object-contain p-5 transition duration-200 group-hover:scale-[1.03]"
              loading="lazy"
            />

            <div
              v-else
              class="relative flex h-full w-full items-center justify-center text-5xl font-black text-[color:var(--horizon-text-primary)]"
            >
              {{ String(squadron.name ?? 'S').slice(0, 1).toUpperCase() }}
            </div>
          </div>

          <!-- Card body -->
          <div class="relative flex flex-1 flex-col gap-4 p-5">
            <div>
              <div class="flex items-start justify-between gap-3">
                <h2 class="min-w-0 text-xl font-black tracking-tight text-horizon-white">
                  {{ squadron.name }}
                </h2>

                <span
                  class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide"
                  :class="squadron.recruiting
                    ? 'border border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                    : 'border-transparent bg-white/[0.042] shadow-none text-text-secondary'"
                >
                  {{ squadron.recruiting ? 'Open' : 'Closed' }}
                </span>
              </div>

              <p class="mt-2 line-clamp-2 text-sm text-text-secondary">
                {{ squadron.motto ?? 'No motto provided.' }}
              </p>
            </div>

            <div class="space-y-2">
              <div class="flex items-center gap-2 text-sm text-text-secondary">
                <span class="text-text-muted">Leader:</span>
                <span
                  class="font-semibold"
                  :style="leaderNameColor(squadron) ? { color: leaderNameColor(squadron) } : undefined"
                >
                  {{ squadron.leader?.rsi_handle ?? squadron.leader?.display_name ?? 'None' }}
                </span>
              </div>

              <div
                v-if="squadron.branch"
                class="flex items-center gap-2 text-sm text-text-secondary"
              >
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
            </div>

            <div class="mt-auto flex items-center justify-between gap-3 border-t border-white/10 pt-4">
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
                View Dossier
              </div>

              <div class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-bold text-[color:var(--horizon-text-primary)] transition group-hover:border-white/[0.055] group-hover:bg-white/[0.042]">
                Open →
              </div>
            </div>
          </div>
        </Link>
      </section>
    </div>
  </HorizonContainer>
</template>






