<template>
  <div class="hz-row-between">
    <div class="hz-row gap-2">
      <span class="hz-text-primary font-medium">
        {{ member.user?.rsi_handle ?? member.user?.display_name }}
      </span>

      <span 
        v-if="member.membership_status === 'pending'" 
        class="hz-badge hz-badge-warn"
      >
        Pending
      </span>
      <span v-else class="hz-text-muted text-xs uppercase tracking-wide">
        {{ member.role }}
      </span>
    </div>

    <div class="hz-row gap-2">
      <!-- Accept/Reject buttons for pending members (only show if user has permission) -->
      <template v-if="member.membership_status === 'pending' && permissions?.can_manage_members">
        <HorizonButton
          variant="primary"
          size="xs"
          @click="$emit('accept', member)"
        >
          Accept
        </HorizonButton>
        <HorizonButton
          variant="secondary"
          size="xs"
          @click="$emit('reject', member)"
        >
          Reject
        </HorizonButton>
      </template>

      <!-- Remove button (for active members or for admins) -->
      <HorizonButton
        v-if="permissions?.can_manage_members && member.membership_status !== 'pending'"
        variant="ghost"
        size="xs"
        @click="$emit('remove', member)"
      >
        Remove
      </HorizonButton>
      <HorizonButton
        v-if="permissions.can_promote_lieutenant && member.role === 'member'"
        variant="ghost"
        size="xs"
        @click="$emit('promote-lt', member.user)"
      >
        Promote to Lieutenant
      </HorizonButton>
      <HorizonButton
        v-if="permissions.can_promote_lieutenant && member.is_lieutenant"
        variant="ghost"
        size="xs"
        @click="$emit('demote-lt', member)"
      >
        Demote
      </HorizonButton>


    </div>
  </div>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue';

const props = defineProps({
  member: Object,
  permissions: Object,
  isBusy: Boolean,
})

const emit = defineEmits([
  'accept',
  'reject',
  'promote-lt',
  'demote-lt',
  'remove',
])
</script>
