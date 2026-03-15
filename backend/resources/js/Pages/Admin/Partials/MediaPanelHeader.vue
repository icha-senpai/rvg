<template>
  <div class="hz-panel hz-row-between border! border-(--horizon-sunset-blue)!">
    <div class="hz-stack-sm">
      <div class="hz-title-lg">Media Library</div>
      <div class="hz-text-muted">
        {{ totalCount }} files · {{ humanTotalSize }} · If close to 8 GB consider Cloudflare R2 Storage
      </div>
    </div>

    <HorizonButton variant="primary" size="sm" @click="$emit('upload')">
      Upload
    </HorizonButton>
  </div>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'

defineProps({
  totalCount: { type: Number, required: true },
  humanTotalSize: { type: String, required: true },
})

defineEmits(['upload'])
</script>
