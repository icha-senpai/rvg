<script setup>
import HorizonButton from '@/Components/HorizonButton.vue';
const props = defineProps({
  viewerMembership: Object,
  permissions: Object,
  squadron: Object,
  isLoading: Boolean,
  activeAction: String, // ✅ ADD THIS
})

const emit = defineEmits(['apply', 'leave'])
</script>

<template>
  <div class="rounded-2xl border border-[color:var(--horizon-sunset-blue)]/25 bg-[linear-gradient(135deg,rgba(30,64,175,0.12),rgba(255,255,255,0.025))] p-4 shadow-[0_0_24px_rgba(30,64,175,0.10)]">
    <div class="hz-section-label">Your Status</div>

    <p class="hz-soft">
      {{ viewerMembership?.membership_status }}
    </p>
    <div
      v-if="viewerMembership && viewerMembership.membership_status === 'pending'"
      class="hz-alert hz-alert-info"
    >
      <div class="hz-alert-title">Application Pending</div>
      <div class="hz-alert-body">
    Your request to join this squadron is awaiting approval by leadership.
  </div>
</div>
    <div class="hz-row mt-2">
      <HorizonButton
        v-if="permissions?.can_apply"
        variant="primary"
        size="sm"
        :disabled="isLoading || activeAction === 'apply'"
        @click="emit('apply')"
      >
        Apply
      </HorizonButton>

      <HorizonButton
        v-if="permissions?.can_leave"
        variant="secondary"
        size="sm"
        :disabled="isLoading || activeAction === 'leave'"
        @click="emit('leave')"
      >
        Leave
      </HorizonButton>
    </div>
  </div>
</template>
