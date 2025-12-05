<template>
  <HorizonContainer class="space-y-12">

    <HorizonSectionHeader
      :label="squadron.name"
      title="Squadron Management"
    />

    <HorizonPanel class="p-6 space-y-4">

      <div class="hz-title-lg">Members</div>

      <div
        v-for="m in members"
        :key="m.id"
        class="p-4 hz-overlay-dark rounded-lg flex justify-between"
      >
        <div>
          <div class="hz-title">{{ m.user.discord_name }}</div>
          <div class="hz-caption text-horizon-muted">
            Status: {{ m.membership_status }} |
            Role: {{ m.role || 'member' }}
          </div>
        </div>

        <div class="flex gap-2" v-if="isLeader">
          
          <HorizonButton
            size="sm"
            variant="outline"
            @click="updateMember(m, 'leader')"
          >
            Leader
          </HorizonButton>

          <HorizonButton
            size="sm"
            variant="outline"
            @click="updateMember(m, 'lieutenant')"
          >
            LT
          </HorizonButton>

          <HorizonButton
            size="sm"
            variant="ghost"
            @click="updateMember(m, 'null')"
          >
            Member
          </HorizonButton>

          <HorizonButton
            size="sm"
            variant="danger"
            @click="removeMember(m)"
          >
            Remove
          </HorizonButton>

        </div>

        <div v-else-if="isLieutenant" class="flex gap-2">

          <HorizonButton
            size="sm"
            variant="outline"
            v-if="m.membership_status === 'pending'"
            @click="approve(m)"
          >
            Approve
          </HorizonButton>

          <HorizonButton
            size="sm"
            variant="danger"
            @click="removeMember(m)"
          >
            Remove
          </HorizonButton>

        </div>

      </div>

    </HorizonPanel>
<HorizonPanel class="p-6 space-y-6" v-if="isLeader">
  <div class="hz-title-lg">Squadron Settings</div>

  <div class="grid grid-cols-1 gap-4">

    <div>
      <label class="hz-caption block mb-1">Motto</label>
      <input v-model="settings.motto" class="hz-input w-full" />
    </div>

    <div>
      <label class="hz-caption block mb-1">Description</label>
      <textarea v-model="settings.description" class="hz-input w-full min-h-[80px]" />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="hz-caption block mb-1">Primary Color</label>
        <input v-model="settings.primary_color" type="color" class="hz-input w-full" />
      </div>

      <div>
        <label class="hz-caption block mb-1">Secondary Color</label>
        <input v-model="settings.secondary_color" type="color" class="hz-input w-full" />
      </div>
    </div>

    <div>
      <label class="hz-caption block mb-1">Recruiting</label>
      <select v-model="settings.recruiting" class="hz-input w-full">
        <option :value="true">Open</option>
        <option :value="false">Closed</option>
      </select>
    </div>

  </div>

  <HorizonButton variant="primary" size="md" class="mt-4"
    @click="saveSettings">
    Save Squadron Settings
  </HorizonButton>

</HorizonPanel>

  </HorizonContainer>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';

const props = defineProps({
  squadron: Object,
  members: Array,
  isLeader: Boolean,
  isLieutenant: Boolean
});

function updateMember(member, role) {
  router.post(route('squadrons.members.update', props.squadron.id), {
    id: member.id,
    role: role,
    membership_status: member.membership_status,
  });
}

function approve(member) {
  router.post(route('squadrons.members.update', props.squadron.id), {
    id: member.id,
    role: member.role,
    membership_status: 'active',
  });
}

function removeMember(member) {
  router.post(route('squadrons.members.remove', props.squadron.id), {
    id: member.id,
  });
}
const settings = ref({
  motto: props.squadron.motto || '',
  description: props.squadron.description || '',
  primary_color: props.squadron.primary_color || '#ffffff',
  secondary_color: props.squadron.secondary_color || '#000000',
  recruiting: props.squadron.recruiting ?? true,
});

function saveSettings() {
  router.post(route('squadrons.settings.update', props.squadron.id), settings.value);
}

</script>
