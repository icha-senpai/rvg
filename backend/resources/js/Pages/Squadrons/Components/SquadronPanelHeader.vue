<script setup>
const props = defineProps({
  squadron: Object,
})

const emit = defineEmits(['close'])
</script>

<template>
  <div class="hz-row-between">
    <div class="hz-row gap-3 items-center">
      <div
        v-if="squadron?.emblem_url"
        class="w-12 h-12 rounded-lg overflow-hidden border border-[color:var(--horizon-sunset-blue)] bg-bg-surface shrink-0"
      >
        <img
          :src="squadron?.emblem?.thumbnail_url || squadron?.emblem?.medium_url || squadron?.emblem?.url || squadron?.emblem_url"
          :alt="squadron?.emblem?.alt_text || `${squadron?.name} emblem`"
          class="w-full h-full object-contain"
          loading="lazy"
        />
      </div>

      <div>
        <div class="hz-title-md">{{ squadron?.name }}</div>
        <div class="hz-text-soft">{{ squadron?.motto }}</div>
      </div>
    </div>
  </div>
</template>
