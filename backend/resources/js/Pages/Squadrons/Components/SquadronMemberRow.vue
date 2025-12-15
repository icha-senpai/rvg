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
        <button
          class="hz-btn hz-btn-primary hz-btn-xs"
          @click="$emit('accept', member)"
        >
          Accept
        </button>
        <button
          class="hz-btn hz-btn-secondary hz-btn-xs"
          @click="$emit('reject', member)"
        >
          Reject
        </button>
      </template>

      <!-- Remove button (for active members or for admins) -->
      <button
        v-if="permissions?.can_manage_members && member.membership_status !== 'pending'"
        class="hz-btn hz-btn-ghost hz-btn-xs"
        @click="$emit('remove', member)"
      >
        Remove
      </button>
      <button
        v-if="permissions.can_promote_lieutenant && member.role === 'member'"
        class="hz-btn hz-btn-ghost hz-btn-xs"
        @click="$emit('promote-lt', member.user)"
      >
        Promote to Lieutenant
      </button>
      <button
        v-if="permissions.can_promote_lieutenant && member.is_lieutenant"
        class="hz-btn hz-btn-ghost hz-btn-xs"
        @click="$emit('demote-lt', member)"
      >
        Demote
      </button>


    </div>
  </div>
</template>

<script setup>
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
