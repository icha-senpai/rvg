<template>
  <div
    v-if="open"
    class="hz-overlay flex items-center justify-center"
    @click.self="$emit('close')"
  >
    <div class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop" style="max-width: 28rem;">
      <div class="hz-row-between">
        <div class="hz-title-lg">Upload Media</div>
        <HorizonButton variant="primary" size="sm" @click="$emit('close')">✕</HorizonButton>
      </div>

      <div class="hz-stack">
        <HorizonSelect
          :model-value="uploadForm.collection"
          :options="uploadCollectionOptions"
          label="Collection"
          @update:model-value="$emit('update:collection', $event)"
        />

        <div>
          <label class="hz-text-soft">Alt Text (optional)</label>
          <input
            :value="uploadForm.alt_text"
            class="hz-input"
            placeholder="Describe the image..."
            @input="$emit('update:alt-text', $event.target.value)"
          />
        </div>

        <div>
          <label class="hz-text-soft">File</label>
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            class="hz-input"
            style="padding: 0.5rem;"
            @change="$emit('file-select', $event)"
          />
        </div>

        <div v-if="uploadPreviewUrl" style="border-radius: var(--radius-sm); overflow: hidden; max-height: 200px;">
          <img :src="uploadPreviewUrl" style="width: 100%; height: 200px; object-fit: contain; background: var(--color-bg-elevated);" />
        </div>

        <div v-if="uploadError" class="hz-text-soft" style="color: var(--color-state-danger);">
          {{ uploadError }}
        </div>
      </div>

      <div class="hz-row-between" style="padding-top: var(--space-sm);">
        <HorizonButton variant="ghost" size="sm" @click="$emit('close')">Cancel</HorizonButton>
        <HorizonButton
          variant="primary"
          size="sm"
          :disabled="uploading || !uploadForm.file"
          @click="$emit('submit')"
        >
          {{ uploading ? 'Uploading...' : 'Upload' }}
        </HorizonButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

defineProps({
  open: { type: Boolean, required: true },
  uploading: { type: Boolean, required: true },
  uploadError: { type: String, required: true },
  uploadPreviewUrl: { type: String, default: null },
  uploadForm: { type: Object, required: true },
  uploadCollectionOptions: { type: Array, required: true },
})

defineEmits(['close', 'submit', 'file-select', 'update:collection', 'update:alt-text'])
</script>
