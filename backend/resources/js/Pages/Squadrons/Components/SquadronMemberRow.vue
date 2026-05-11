<template>
  <div class="hz-row-between rounded-xl border border-[color:var(--horizon-sunset-blue)]/15 bg-white/[0.025] px-3 py-2">
    <div class="hz-row gap-2">
      <Link
        v-if="member.user?.id"
        :href="member.user?.rsi_handle ? route('member.profile', member.user.rsi_handle) : `/user/${member.user.id}`"
        class="hz-text-primary font-medium hover:underline"
        :style="nameColor ? { color: nameColor } : undefined"
      >
        {{ member.user?.rsi_handle ?? member.user?.display_name }}
      </Link>
      <span
        v-else
        class="hz-text-primary font-medium"
        :style="nameColor ? { color: nameColor } : undefined"
      >
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
        v-if="permissions.can_promote_lieutenant && canPromoteLieutenant && member.role === 'member'"
        variant="ghost"
        size="xs"
        @click="$emit('promote-lt', member.user)"
      >
        Promote to Lieutenant
      </HorizonButton>
      <HorizonButton
        v-if="permissions.can_demote_lieutenant && member.is_lieutenant"
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
import { Link } from '@inertiajs/vue3'
import HorizonButton from '@/Components/HorizonButton.vue';

import { computed } from 'vue'
import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const props = defineProps({
  member: Object,
  permissions: Object,
  canPromoteLieutenant: Boolean,
  isBusy: Boolean,
})

const emit = defineEmits([
  'accept',
  'reject',
  'promote-lt',
  'demote-lt',
  'remove',
])

const nameColor = computed(() => {
  const u = props.member?.user ?? null
  const slug = getHighestOrgRoleSlug(u?.roles, u?.rank)
  return getOrgRoleColor(slug)
})
</script>
