<template>
  <div
    v-if="detailItem"
    class="hz-overlay flex items-center justify-center"
    @click.self="$emit('close')"
  >
    <div class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop" style="max-width: 36rem;">
      <div class="hz-row-between">
        <div class="hz-title-lg" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80%;">
          {{ detailItem.original_filename }}
        </div>
        <HorizonButton variant="primary" size="sm" @click="$emit('close')">✕</HorizonButton>
      </div>

      <div style="border-radius: var(--radius-sm); overflow: hidden; background: var(--color-bg-elevated);">
        <img
          :src="detailItem.medium_url || detailItem.url"
          :alt="detailItem.alt_text || detailItem.original_filename"
          style="width: 100%; max-height: 360px; object-fit: contain;"
        />
      </div>

      <div class="hz-stack-sm">
        <div class="hz-text-muted">
          {{ detailItem.mime_type }} · {{ detailItem.human_size }}
          <span v-if="detailItem.width"> · {{ detailItem.width }}×{{ detailItem.height }}px</span>
        </div>

        <div class="hz-text-muted">
          Collection: {{ collectionLabel(detailItem.collection) }}
        </div>

        <div class="hz-text-muted">
          Uploaded by:
          <span :style="uploaderNameColor(detailItem.uploader) ? { color: uploaderNameColor(detailItem.uploader) } : undefined">
            {{ detailItem.uploader?.rsi_handle || detailItem.uploader?.discord_name || 'Unknown' }}
          </span>
        </div>

        <div class="hz-text-muted">
          {{ formatDate(detailItem.created_at) }}
        </div>
      </div>

      <div>
        <label class="hz-text-soft">Name</label>
        <input
          :value="detailFilename"
          class="hz-input"
          placeholder="Display name..."
          @input="$emit('update:filename', $event.target.value)"
        />
      </div>

      <div>
        <label class="hz-text-soft">Alt Text</label>
        <input
          :value="detailAltText"
          class="hz-input"
          placeholder="Describe the image..."
          @input="$emit('update:alt-text', $event.target.value)"
        />
      </div>

      <div class="hz-stack-sm">
        <div class="hz-text-soft">URLs</div>
        <div
          v-for="(url, label) in detailUrls"
          :key="label"
          class="hz-row"
          style="font-size: var(--text-tiny); cursor: pointer;"
          :title="'Click to copy'"
          @click="$emit('copy', url)"
        >
          <span class="hz-text-muted" style="min-width: 80px;">{{ label }}:</span>
          <span class="hz-text-soft" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ url }}</span>
        </div>
      </div>

      <div class="hz-row-between" style="padding-top: var(--space-sm);">
        <div class="hz-row" style="gap: var(--space-sm);">
          <a
            :href="`/admin/media/${detailItem.id}/download`"
            class="hz-btn hz-btn-sm hz-btn-ghost"
          >
            Download Original
          </a>

          <HorizonButton variant="danger" size="sm" @click="$emit('delete', detailItem.id)">
            Delete
          </HorizonButton>
        </div>

        <div class="hz-row">
          <HorizonButton variant="ghost" size="sm" @click="$emit('close')">Cancel</HorizonButton>
          <HorizonButton variant="primary" size="sm" @click="$emit('save')">Save</HorizonButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'

defineProps({
  detailItem: { type: Object, default: null },
  detailAltText: { type: String, required: true },
  detailFilename: { type: String, required: true },
  detailUrls: { type: Object, required: true },
  collectionLabel: { type: Function, required: true },
  uploaderNameColor: { type: Function, required: true },
  formatDate: { type: Function, required: true },
})

defineEmits(['close', 'save', 'delete', 'copy', 'update:filename', 'update:alt-text'])
</script>
