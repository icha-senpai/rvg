<template>
  <HorizonModal
    :open="open"
    close-label="Close upload modal"
    max-width-class="max-w-2xl"
    @close="$emit('close')"
  >
    <template #header>
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
          Media Upload Console
        </div>

        <div class="mt-1 text-2xl font-black text-horizon-white">
          Upload Media
        </div>

        <p class="mt-1 text-sm text-text-secondary">
          Add a new image asset to the Horizon media library.
        </p>
      </div>
    </template>

    <div class="relative flex-1 overflow-y-auto p-5">
        <div class="space-y-5">
          <section class="hz-surface-welcome relative z-30 rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/20 p-4">
            <div class="mb-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Upload Target
              </div>

              <p class="mt-1 text-sm text-text-secondary">
                Choose which collection this asset belongs to.
              </p>
            </div>

            <HorizonSelect
              :model-value="uploadForm.collection"
              :options="uploadCollectionOptions"
              label="Collection"
              @update:model-value="$emit('update:collection', $event)"
            />
          </section>

          <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
            <div class="mb-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                File Metadata
              </div>

              <p class="mt-1 text-sm text-text-secondary">
                Add optional alt text and select an image file.
              </p>
            </div>

            <div class="space-y-4">
              <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  Alt Text
                </label>

                <HorizonInput
                  :value="uploadForm.alt_text"
                  class="w-full"
                  placeholder="Describe the image..."
                  @input="$emit('update:alt-text', $event.target.value)"
                />
              </div>

              <HorizonFileField
                label="File"
                description="JPEG, PNG, WEBP, or GIF."
                button-label="Choose Image"
                :selected-name="uploadForm.file?.name ?? ''"
                accept="image/jpeg,image/png,image/webp,image/gif"
                @change="$emit('file-select', $event)"
              />
            </div>
          </section>

          <section
            v-if="uploadPreviewUrl"
            class="hz-surface-welcome overflow-hidden rounded-[1.5rem] border border-white/[0.055]"
          >
            <img
              :src="uploadPreviewUrl"
              alt="Upload preview"
              class="max-h-80 w-full object-contain"
            />

            <div class="hz-surface-welcome border-t border-white/[0.055] p-3 text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
              Preview
            </div>
          </section>

          <div
            v-if="uploadError"
            class="rounded-[1.25rem] border border-red-300/25 bg-red-300/10 p-4 text-sm font-semibold text-red-100"
          >
            {{ uploadError }}
          </div>
        </div>
      </div>

    <template #footer>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="text-sm text-text-secondary">
            {{ uploadForm.file ? 'Ready to upload selected file.' : 'Select a file to enable upload.' }}
          </div>

          <div class="flex flex-wrap gap-2 sm:justify-end">
            <HorizonButton
              variant="ghost"
              size="sm"
              @click="$emit('close')"
            >
              Cancel
            </HorizonButton>

            <HorizonButton
              variant="primary"
              size="sm"
              :disabled="uploading || !uploadForm.file"
              @click="$emit('submit')"
            >
              {{ uploading ? 'Uploading…' : 'Upload Media' }}
            </HorizonButton>
          </div>
        </div>
    </template>
  </HorizonModal>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonFileField from '@/Components/HorizonFileField.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonModal from '@/Components/HorizonModal.vue'
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


