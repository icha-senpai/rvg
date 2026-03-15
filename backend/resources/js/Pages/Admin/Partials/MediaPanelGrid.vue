<template>
  <div>
    <div v-if="loading" class="hz-panel hz-text-muted" style="text-align: center; padding: var(--space-xl);">
      Loading media...
    </div>

    <div v-else-if="!media.length" class="hz-panel hz-text-muted" style="text-align: center; padding: var(--space-xl);">
      No media found.
    </div>

    <div v-else class="hz-media-grid">
      <div
        v-for="item in media"
        :key="item.id"
        class="hz-media-thumb"
        @click="$emit('select', item)"
      >
        <img
          :src="item.thumbnail_url || item.medium_url || item.url"
          :alt="item.alt_text || item.original_filename"
          loading="lazy"
        />

        <div class="hz-media-thumb-info">
          <div class="hz-tiny" style="color: var(--color-horizon-offwhite); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
            {{ item.original_filename }}
          </div>
          <div class="hz-tiny" style="color: var(--color-text-muted);">
            {{ item.human_size }} · {{ collectionLabel(item.collection) }}
          </div>
        </div>
      </div>
    </div>

    <div v-if="pagination.lastPage > 1" class="flex items-center justify-center flex-wrap gap-3">
      <HorizonButton
        variant="primary"
        size="sm"
        :disabled="!pagination.prevUrl"
        @click="$emit('page', pagination.currentPage - 1)"
      >
        Previous
      </HorizonButton>

      <div class="hz-text-soft">
        Page {{ pagination.currentPage }} / {{ pagination.lastPage }}
      </div>

      <HorizonButton
        variant="primary"
        size="sm"
        :disabled="!pagination.nextUrl"
        @click="$emit('page', pagination.currentPage + 1)"
      >
        Next
      </HorizonButton>
    </div>
  </div>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'

defineProps({
  loading: { type: Boolean, required: true },
  media: { type: Array, required: true },
  pagination: { type: Object, required: true },
  collectionLabel: { type: Function, required: true },
})

defineEmits(['select', 'page'])
</script>

<style scoped>
.hz-media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: var(--space-md);
}

.hz-media-thumb {
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-bg-hover);
  border-radius: var(--radius-sm);
  overflow: hidden;
  cursor: pointer;
  transition: border-color var(--motion-fast) var(--ease-smooth),
              box-shadow var(--motion-fast) var(--ease-smooth);
}

.hz-media-thumb:hover {
  border-color: var(--color-horizon-blue);
  box-shadow: 0 0 12px rgba(42, 120, 200, 0.2);
}

.hz-media-thumb img {
  width: 100%;
  height: 140px;
  object-fit: cover;
  display: block;
  background: var(--color-bg-base);
}

.hz-media-thumb-info {
  padding: var(--space-xs) var(--space-sm);
}
</style>
