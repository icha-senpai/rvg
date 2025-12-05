<template>
  <div class="hz-container hz-stack">

    <!-- HEADER -->
    <div class="hz-stack-sm">
      <div class="hz-section-label">Admin Control</div>
      <div class="hz-title-xl">Unified Control Panel</div>
      <div class="hz-text-soft">Manage users, squadrons, and system roles.</div>
    </div>

    <!-- TAB BAR -->
    <div class="hz-row hz-text-soft" style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);">
      <button
        class="hz-btn hz-btn-ghost"
        :class="{ 'hz-btn-primary': activeTab === 'users' }"
        @click="activeTab = 'users'"
      >
        Users
      </button>

      <button
        class="hz-btn hz-btn-ghost"
        :class="{ 'hz-btn-primary': activeTab === 'squadrons' }"
        @click="activeTab = 'squadrons'"
      >
        Squadrons
      </button>

      <button
        class="hz-btn hz-btn-ghost"
        :class="{ 'hz-btn-primary': activeTab === 'roles' }"
        @click="activeTab = 'roles'"
      >
        Roles
      </button>
    </div>

    <!-- CONTENT PANELS -->
    <div v-if="activeTab === 'users'" class="hz-animate-fade">
      <UsersPanel :users="users" :roles="roles" :filters="filters" />
    </div>

    <div v-if="activeTab === 'squadrons'" class="hz-animate-fade">
      <SquadronsPanel :squadrons="squadrons" />
    </div>

    <div v-if="activeTab === 'roles'" class="hz-animate-fade">
      <RolesPanel :roles="roles" />
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';

import UsersPanel from './Partials/UsersPanel.vue';
import SquadronsPanel from './Partials/SquadronsPanel.vue';
import RolesPanel from './Partials/RolesPanel.vue';

const props = defineProps({
  users: Object,
  squadrons: Array,
  roles: Array,
  filters: Object,
  eligibleLeaders: Array,
});

const activeTab = ref('users');
</script>
