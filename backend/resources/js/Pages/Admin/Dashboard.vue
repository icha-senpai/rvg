<template>
  <HorizonContainer class="space-y-10">
    <div class="mx-auto max-w-5xl hz-stack">

    <!-- HEADER -->
    <div class="hz-stack-sm">
      <div class="hz-title-xl">Admin Dashboard</div>
    </div>

    <!-- TAB BAR -->
    <div class="hz-row hz-text-soft" style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);">
      <HorizonButton
        size="sm"
        :variant="activeTab === 'users' ? 'primary' : 'ghost'"
        @click="activeTab = 'users'"
      >
        Users
      </HorizonButton>

      <HorizonButton
        size="sm"
        :variant="activeTab === 'squadrons' ? 'primary' : 'ghost'"
        @click="activeTab = 'squadrons'"
      >
        Squadrons
      </HorizonButton>

      <HorizonButton
        size="sm"
        :variant="activeTab === 'roles' ? 'primary' : 'ghost'"
        @click="activeTab = 'roles'"
      >
        Roles
      </HorizonButton>

      <HorizonButton
        size="sm"
        :variant="activeTab === 'media' ? 'primary' : 'ghost'"
        @click="activeTab = 'media'"
      >
        Media
      </HorizonButton>
    </div>

    <!-- CONTENT PANELS -->
    <div v-if="activeTab === 'users'" class="hz-animate-fade">
      <UsersPanel :users="users" :roles="roles" :filters="filters" />
    </div>

    <div v-if="activeTab === 'squadrons'" class="hz-animate-fade">
      <SquadronsPanel :squadrons="squadrons" :eligible-leaders="eligibleLeaders" />
    </div>

    <div v-if="activeTab === 'roles'" class="hz-animate-fade">
      <RolesPanel :roles="roles" />
    </div>

    <div v-if="activeTab === 'media'" class="hz-animate-fade">
      <MediaPanel />
    </div>

    </div>
  </HorizonContainer>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import UsersPanel from './Partials/UsersPanel.vue';
import SquadronsPanel from './Partials/SquadronsPanel.vue';
import RolesPanel from './Partials/RolesPanel.vue';
import MediaPanel from './Partials/MediaPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';

const props = defineProps({
  users: Object,
  squadrons: Array,
  roles: Array,
  filters: Object,
  eligibleLeaders: Array,
});

const allowedTabs = new Set(['users', 'squadrons', 'roles', 'media']);
const activeTabStorageKey = 'adminDashboard.activeTab';

function getTabFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab');
  return allowedTabs.has(tab) ? tab : null;
}

function setTabInUrl(tab) {
  const url = new URL(window.location.href);

  if (tab && allowedTabs.has(tab)) {
    url.searchParams.set('tab', tab);
  } else {
    url.searchParams.delete('tab');
  }

  window.history.replaceState({}, '', url);
}

function getInitialTab() {
  const urlTab = getTabFromUrl();
  if (urlTab) return urlTab;

  const saved = window.sessionStorage.getItem(activeTabStorageKey);
  if (allowedTabs.has(saved)) return saved;

  return 'users';
}

const activeTab = ref(getInitialTab());

const scrollStorageKeyForTab = (tab) => `adminDashboard.scrollY.${tab}`;
let scrollDebounceId = null;

function persistScrollPosition() {
  if (scrollDebounceId) return;

  scrollDebounceId = window.setTimeout(() => {
    scrollDebounceId = null;
    window.sessionStorage.setItem(scrollStorageKeyForTab(activeTab.value), String(window.scrollY || 0));
  }, 50);
}

function restoreScrollPosition() {
  const raw = window.sessionStorage.getItem(scrollStorageKeyForTab(activeTab.value));
  const y = raw ? Number(raw) : 0;
  if (!Number.isFinite(y) || y <= 0) return;

  nextTick(() => {
    window.scrollTo({ top: y, left: 0, behavior: 'auto' });
  });
}

watch(
  () => activeTab.value,
  (tab, prevTab) => {
    window.sessionStorage.setItem(activeTabStorageKey, tab);
    setTabInUrl(tab);

    if (prevTab) {
      window.sessionStorage.setItem(scrollStorageKeyForTab(prevTab), String(window.scrollY || 0));
    }

    restoreScrollPosition();
  },
  { immediate: true }
);

onMounted(() => {
  restoreScrollPosition();
  window.addEventListener('scroll', persistScrollPosition, { passive: true });
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', persistScrollPosition);
  if (scrollDebounceId) {
    clearTimeout(scrollDebounceId);
    scrollDebounceId = null;
  }
});
</script>
