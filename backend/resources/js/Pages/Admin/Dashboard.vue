<template>
  <HorizonContainer class="space-y-10">
    <div class="mx-auto max-w-5xl hz-stack">

    <!-- HEADER -->
    <div class="hz-stack-sm">
      <div class="hz-section-label">Admin Control</div>
      <div class="hz-title-xl">Unified Control Panel</div>
      <div class="hz-text-soft">Manage users, squadrons, and system roles.</div>
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

    </div>
  </HorizonContainer>
</template>

<script setup>
import { ref } from 'vue';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import UsersPanel from './Partials/UsersPanel.vue';
import SquadronsPanel from './Partials/SquadronsPanel.vue';
import RolesPanel from './Partials/RolesPanel.vue';
import HorizonButton from '@/Components/HorizonButton.vue';

const props = defineProps({
  users: Object,
  squadrons: Array,
  roles: Array,
  filters: Object,
  eligibleLeaders: Array,
});

const activeTab = ref('users');
</script>
