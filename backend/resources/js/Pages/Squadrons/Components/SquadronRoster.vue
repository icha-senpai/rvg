<script setup>
import { computed } from 'vue'
import SquadronMemberRow from './SquadronMemberRow.vue'

const props = defineProps({
  members: Array,
  permissions: Object,
  canPromoteLieutenant: Boolean,
  activeAction: String,
})

const sortedMembers = computed(() => {
  const list = [...(props.members ?? [])]

  const getRoleRank = (member) => {
    const role = String(member?.role ?? '').toLowerCase()
    if (role === 'leader') return 0

    if (member?.is_lieutenant === true || role === 'lieutenant') return 1

    return 2
  }

  const getNameKey = (member) => {
    return String(member?.user?.rsi_handle ?? member?.user?.display_name ?? '').toLowerCase()
  }

  return list.sort((a, b) => {
    const aPending = a?.membership_status === 'pending'
    const bPending = b?.membership_status === 'pending'

    if (aPending && !bPending) return 1
    if (!aPending && bPending) return -1

    const roleDiff = getRoleRank(a) - getRoleRank(b)
    if (roleDiff !== 0) return roleDiff

    const aName = getNameKey(a)
    const bName = getNameKey(b)
    if (aName < bName) return -1
    if (aName > bName) return 1
    return 0
  })
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
      v-for="member in sortedMembers"
      :key="member.id"
      :member="member"
      :permissions="permissions"
      :canPromoteLieutenant="canPromoteLieutenant"
      @accept="emit('accept-member', member)"
      @reject="emit('reject-member', member)"
      @promote-lt="emit('promote-lt', $event)"
      @demote-lt="emit('demote-lt', $event)"
      @remove="emit('remove-member', member)"
    />
  </div>
</template>


