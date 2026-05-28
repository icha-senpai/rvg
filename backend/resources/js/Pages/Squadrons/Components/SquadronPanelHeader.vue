<script setup>
const props = defineProps({
  squadron: Object,
})

const emit = defineEmits(['close'])

function formatTitle(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return ''

  return raw
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(w => (w ? w.charAt(0).toUpperCase() + w.slice(1) : ''))
    .join(' ')
}
</script>

<template>
  <div class="hz-row-between">
    <div class="hz-row gap-3 items-center">
      <div
        v-if="squadron?.emblem_url"
        class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-[color:var(--horizon-void-800)] "
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
        <div v-if="squadron?.branch" class="hz-caption text-horizon-muted">
          {{ formatTitle(squadron.branch) }}<span v-if="squadron?.division"> · {{ formatTitle(squadron.division) }}</span>
        </div>
        <div class="hz-text-soft">{{ squadron?.motto }}</div>
      </div>
    </div>
  </div>
</template>








