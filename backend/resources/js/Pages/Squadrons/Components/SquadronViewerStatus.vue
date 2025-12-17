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
  <div class="hz-panel">
    <div class="hz-section-label">Your Status</div>

    <p class="hz-soft">
      Status component placeholder
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
