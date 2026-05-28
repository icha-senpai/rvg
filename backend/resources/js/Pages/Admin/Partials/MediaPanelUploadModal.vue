<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[90] flex items-center justify-center bg-black/75 p-4 backdrop-blur-md"
    @click.self="$emit('close')"
  >
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
      <div class="absolute left-1/4 top-10 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-blue)]/16 blur-3xl"></div>
      <div class="absolute bottom-10 right-1/4 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-magenta)]/14 blur-3xl"></div>
    </div>

    <div class="relative z-10 flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-[2rem] border border-white/[0.055] bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))]  hz-animate-pop">
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <header class="relative shrink-0 border-b border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
        <div class="flex items-start justify-between gap-4">
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

          <button
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/[0.055] bg-white/[0.024] text-lg font-bold text-text-secondary transition hover:border-white/[0.055] hover:bg-white/[0.042] hover:text-horizon-white"
            aria-label="Close upload modal"
            @click="$emit('close')"
          >
            ✕
          </button>
        </div>
      </header>

      <div class="relative flex-1 overflow-y-auto p-5">
        <div class="space-y-5">
          <section class="relative z-30 rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.042] p-4">
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

          <section class="rounded-[1.5rem] border border-white/[0.055] bg-white/[0.024] p-4">
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

                <input
                  :value="uploadForm.alt_text"
                  class="hz-input w-full"
                  placeholder="Describe the image..."
                  @input="$emit('update:alt-text', $event.target.value)"
                />
              </div>

              <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  File
                </label>

                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  class="hz-input w-full"
                  style="padding: 0.5rem;"
                  @change="$emit('file-select', $event)"
                />
              </div>
            </div>
          </section>

          <section
            v-if="uploadPreviewUrl"
            class="overflow-hidden rounded-[1.5rem] border border-white/[0.055] bg-black/30"
          >
            <img
              :src="uploadPreviewUrl"
              alt="Upload preview"
              class="max-h-80 w-full object-contain"
            />

            <div class="border-t border-white/[0.055] bg-white/[0.024] p-3 text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
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

      <footer class="relative shrink-0 border-t border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
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
      </footer>
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







