<script setup>
import SharedLedgerPage from '@/Components/SharedLedgerPage.vue'

defineProps({
  squadron: {
    type: Object,
    default: null,
  },
  ledger: {
    type: Object,
    required: true,
  },
  permissions: {
    type: Object,
    default: () => ({}),
  },
  context: {
    type: Object,
    default: () => ({
      mode: 'personal',
    }),
  },
  filters: {
    type: Object,
    default: () => ({
      wipe: 'current',
    }),
  },
})
</script>

<template>
  <SharedLedgerPage v-bind="$props" />
</template>
