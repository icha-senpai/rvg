<script setup>
import SquadronMemberRow from './SquadronMemberRow.vue'

const props = defineProps({
  members: Array,
  permissions: Object,
  activeAction: String,
})

const emit = defineEmits([
  'accept-member',
  'reject-member',
  'promote-lt',
  'demote-lt',
  'remove-member',
])
</script>

<template>
  <div class="hz-stack">
    <div class="hz-section-label">Roster</div>

    <div v-if="!members || members.length === 0" class="hz-soft">
      No members found.
    </div>

    <SquadronMemberRow
      v-for="member in members"
      :key="member.id"
      :member="member"
      :permissions="permissions"
      @accept="emit('accept-member', member)"
      @reject="emit('reject-member', member)"
      @promote-lt="emit('promote-lt', member)"
      @demote-lt="emit('demote-lt', member)"
      @remove="emit('remove-member', member)"
    />
  </div>
</template>


