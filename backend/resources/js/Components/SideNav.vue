<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const user = computed(() => page.props?.auth?.user ?? null);
const roles = computed(() => user.value?.roles ?? []);
const rankLevel = computed(() => Number(user.value?.rank_level ?? 0));

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
      key: 'operations_member',
      label: 'All Operations',
      routeName: 'operations.member',
      params: undefined,
      isActive: url.startsWith('/operations/member'),
    },
    {
      key: 'operations_dashboard',
      label: 'Operation Dashboard',
      routeName: 'operations.index',
      params: undefined,
      isActive: url === '/operations' || url.startsWith('/operations?'),
      show: canSeeOperationsDashboard.value,
    },
    {
      key: 'squadrons_index',
      label: 'All Squadrons',
      routeName: 'squadrons.index',
      params: undefined,
      isActive: url === '/squadrons' || url.startsWith('/squadrons?'),
    },
    {
      key: 'my_squadron',
      label: 'My Squadron',
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
</script>

<template>
  <aside v-if="user" class="hidden md:block h-screen w-64 shrink-0 sticky top-0">
    <div
      class="h-full bg-bg-elevated border-r border-bg-hover"
    >
      <div class="h-full flex flex-col p-4 gap-4">
        <div class="flex items-center gap-3 px-2 py-2">
          <div
            class="w-2.5 h-2.5 rounded-full"
            style="background: var(--color-horizon-blue-light);"
          ></div>
          <div class="hz-title-md text-horizon-white">
            Horizon
          </div>
        </div>

        <div class="px-2 hz-section-label">Navigation</div>

        <nav class="flex flex-col gap-1">
          <Link
            v-for="item in navItems"
            :key="item.key"
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
                class="text-[10px] leading-none px-2 py-1 rounded-full bg-(--color-horizon-blue-10) text-text-secondary"
              >
                {{ mySquadron?.name ?? 'Active' }}
              </span>
            </div>
          </Link>
        </nav>

        <div
          class="mt-auto px-3 py-3 rounded-2xl bg-bg-surface border border-bg-hover"
        >
          <div class="text-xs text-text-secondary">Signed in as</div>
          <div class="text-sm font-semibold truncate text-horizon-white">
            {{ user.rsi_handle ?? user.discord_name ?? 'Member' }}
          </div>
          <div class="text-xs text-(--color-text-muted)">
            Rank {{ rankLevel }}
          </div>
        </div>
      </div>
    </div>
  </aside>
</template>
