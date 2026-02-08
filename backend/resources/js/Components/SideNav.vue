<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const user = computed(() => page.props?.auth?.user ?? null);
const roles = computed(() => user.value?.roles ?? []);
const rankLevel = computed(() => Number(user.value?.rank_level ?? 0));
const rankName = computed(() => {
  const existingRankName = user.value?.rank_name;
  if (existingRankName) {
    return existingRankName;
  }

  return (
    {
      1: 'Member',
      2: 'Lieutenant',
      3: 'Commander',
      4: 'Wing Commander',
      5: 'Admiral',
      6: 'Grand Admiral',
    }[rankLevel.value] ?? 'Unknown'
  );
});

const isDirectorLike = computed(() => {
  return roles.value.some(r => r?.slug === 'director' || r?.slug === 'tech_director');
});

const canSeeOperationsDashboard = computed(() => {
  return isDirectorLike.value || rankLevel.value >= 2;
});

const mySquadron = computed(() => {
  const squadrons = user.value?.squadrons ?? [];

  return (
    squadrons.find(s => s?.pivot?.membership_status === 'active') ??
    squadrons[0] ??
    null
  );
});

const navItems = computed(() => {
  if (!user.value) return [];

  const url = page.url;

  return [
    {
      key: 'home',
      label: 'Welcome',
      routeName: 'home',
      params: undefined,
      isActive: url === '/',
    },
    {
      key: 'archive',
      label: 'Horizon Archive',
      href: 'https://docs.horizoninterstellar.com/collection/horizon-archive-ehz6KYHyv1/overview',
      isActive: false,
    },
    {
      key: 'operations_member',
      label: 'Operations',
      routeName: 'operations.member',
      params: undefined,
      isActive: url === '/operations' || url === '/operations/' || url.startsWith('/operations?'),
    },
    {
      key: 'operations_dashboard',
      label: 'Operations Dashboard',
      routeName: 'operations.index',
      params: undefined,
      isActive: url === '/operations/dashboard' || url === '/operations/dashboard/' || url.startsWith('/operations/dashboard?'),
      show: canSeeOperationsDashboard.value,
    },
    {
      key: 'squadrons_index',
      label: 'Squadrons',
      routeName: 'squadrons.index',
      params: undefined,
      isActive: url === '/squadrons' || url.startsWith('/squadrons?'),
    },
    {
      key: 'my_squadron',
      label: 'Squadron',
      routeName: 'squadrons.show',
      params: mySquadron.value ? mySquadron.value.id : undefined,
      isActive: mySquadron.value ? url === `/squadrons/${mySquadron.value.id}` : false,
      show: !!mySquadron.value,
    },
    {
      key: 'admin_dashboard',
      label: 'Admin Dashboard',
      routeName: 'admin.dashboard',
      params: undefined,
      isActive: url.startsWith('/admin'),
      show: isDirectorLike.value,
    },
  ].filter(item => item.show !== false);
});

const mobileOpen = ref(false);

function openMobileNav() {
  mobileOpen.value = true;
}

function closeMobileNav() {
  mobileOpen.value = false;
}

watch(
  () => page.url,
  () => {
    closeMobileNav();
  }
);

function handleKeydown(event) {
  if (event.key !== 'Escape') return;
  if (!mobileOpen.value) return;

  closeMobileNav();
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <button
    v-if="user && !mobileOpen"
    type="button"
    class="md:hidden fixed top-3 left-3 z-[80] inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-bg-elevated border border-bg-hover text-horizon-white"
    @click="openMobileNav"
  >
    <span class="text-sm font-semibold">Menu</span>
  </button>

  <div v-if="user && mobileOpen" class="md:hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-black/60" @click="closeMobileNav"></div>

    <aside class="absolute inset-y-0 left-0 w-72 max-w-[85vw] bg-bg-elevated border-r border-bg-hover">
      <div class="h-full flex flex-col p-4 gap-4">
        <div class="-mx-4 -mt-4 bg-black overflow-hidden">
          <img
            src="/images/og-card.png"
            alt="Horizon Interstellar"
            class="block w-full h-36 object-contain scale-150"
          />
        </div>

        <div class="flex items-center justify-end px-2">
          <button
            type="button"
            class="px-3 py-2 rounded-xl text-sm font-semibold transition text-text-secondary hover:bg-bg-hover hover:text-horizon-white"
            @click="closeMobileNav"
          >
            Close
          </button>
        </div>

        <div class="px-2 hz-section-label"></div>

        <nav class="flex flex-col gap-1">
          <template v-for="item in navItems" :key="item.key">
            <Link
              v-if="!item.href"
              :href="route(item.routeName, item.params)"
              class="px-3 py-2 rounded-xl text-sm font-semibold transition"
              :class="
                item.isActive
                  ? 'bg-bg-hover text-horizon-white'
                  : 'text-text-secondary hover:bg-bg-hover hover:text-horizon-white'
              "
              @click="closeMobileNav"
            >
              <div class="flex items-center justify-between gap-3">
                <span class="truncate">{{ item.label }}</span>
                <span
                  v-if="item.key === 'my_squadron'"
                  class="text-[10px] leading-none px-2 py-1 rounded-full bg-horizon-blue-dark text-horizon-white"
                >
                  {{ mySquadron?.name ?? 'Active' }}
                </span>
              </div>
            </Link>

            <a
              v-else
              :href="item.href"
              target="_blank"
              rel="noopener noreferrer"
              class="px-3 py-2 rounded-xl text-sm font-semibold transition text-text-secondary hover:bg-bg-hover hover:text-horizon-white"
              @click="closeMobileNav"
            >
              <div class="flex items-center justify-between gap-3">
                <span class="truncate">{{ item.label }}</span>
              </div>
            </a>
          </template>
        </nav>

        <div class="mt-auto px-3 py-3 rounded-2xl bg-horizon-blue-dark border border-bg-hover">
          <div class="text-xs text-text-secondary">User Info</div>
          <div class="text-sm font-semibold truncate text-horizon-white">
            {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
          </div>
          <div class="text-xs text-text-secondary">
            Rank {{ rankName }}
          </div>
        </div>
      </div>
    </aside>
  </div>

  <aside v-if="user" class="hidden md:block h-screen w-64 shrink-0 sticky top-0">
    <div
      class="h-full bg-bg-elevated border-r border-bg-hover"
    >
      <div class="h-full flex flex-col p-4 gap-4">
        <div class="-mx-4 -mt-4 bg-black overflow-hidden">
          <img
            src="/images/og-card.png"
            alt="Horizon Interstellar"
            class="block w-full h-36 object-contain scale-150"
          />
        </div>

        <div class="px-2 hz-section-label"></div>

        <nav class="flex flex-col gap-1">
          <template v-for="item in navItems" :key="item.key">
            <Link
              v-if="!item.href"
              :href="route(item.routeName, item.params)"
              class="px-3 py-2 rounded-xl text-sm font-semibold transition"
              :class="
                item.isActive
                  ? 'bg-bg-hover text-horizon-white'
                  : 'text-text-secondary hover:bg-bg-hover hover:text-horizon-white'
              "
            >
              <div class="flex items-center justify-between gap-3">
                <span class="truncate">{{ item.label }}</span>
                <span
                  v-if="item.key === 'my_squadron'"
                  class="text-[10px] leading-none px-2 py-1 rounded-full bg-horizon-blue-dark text-horizon-white"
                >
                  {{ mySquadron?.name ?? 'Active' }}
                </span>
              </div>
            </Link>

            <a
              v-else
              :href="item.href"
              target="_blank"
              rel="noopener noreferrer"
              class="px-3 py-2 rounded-xl text-sm font-semibold transition text-text-secondary hover:bg-bg-hover hover:text-horizon-white"
            >
              <div class="flex items-center justify-between gap-3">
                <span class="truncate">{{ item.label }}</span>
              </div>
            </a>
          </template>
        </nav>

        <div
          class="mt-auto px-3 py-3 rounded-2xl bg-horizon-blue-dark border border-bg-hover"
        >
          <div class="text-xs text-text-secondary">User Info</div>
          <div class="text-sm font-semibold truncate text-horizon-white">
            {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
          </div>
          <div class="text-xs text-text-secondary">
            Rank {{ rankName }}
          </div>
        </div>
      </div>
    </div>
  </aside>
</template>
