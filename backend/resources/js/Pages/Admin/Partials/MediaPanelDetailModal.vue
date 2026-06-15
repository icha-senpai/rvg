<template>
  <HorizonModal
    :open="Boolean(detailItem)"
    close-label="Close detail modal"
    max-width-class="max-w-5xl"
    max-height-class="sm:max-h-[90vh]"
    @close="$emit('close')"
  >
    <template #header>
      <div class="min-w-0">
        <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
          Media Asset Inspector
        </div>

        <div class="mt-1 truncate text-2xl font-black text-horizon-white">
          {{ detailItem?.original_filename }}
        </div>

        <div class="mt-2 flex flex-wrap gap-2">
          <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
            #{{ detailItem?.id }}
          </span>

          <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
            {{ detailItem ? collectionLabel(detailItem.collection) : '' }}
          </span>

          <span class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary">
            {{ detailItem?.human_size }}
          </span>
        </div>
      </div>
    </template>

    <div class="relative flex-1 overflow-y-auto p-5">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <section class="hz-surface-welcome overflow-hidden rounded-[1.5rem] border border-white/[0.055]">
            <img
              :src="detailItem.medium_url || detailItem.url"
              :alt="detailItem.alt_text || detailItem.original_filename"
              class="max-h-[34rem] w-full object-contain"
            />

            <div class="hz-surface-welcome border-t border-white/[0.055] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Preview
              </div>

              <div class="mt-1 text-sm text-text-secondary">
                {{ detailItem.mime_type }}
                <span v-if="detailItem.width"> · {{ detailItem.width }}×{{ detailItem.height }}px</span>
              </div>
            </div>
          </section>

          <aside class="space-y-5">
            <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Asset Details
                </div>

                <p class="mt-1 text-sm text-text-secondary">
                  Metadata and uploader information.
                </p>
              </div>

              <div class="space-y-3 text-sm">
                <div class="rounded-xl border border-white/10 bg-black/10 p-3">
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Uploaded By
                  </div>

                  <div
                    class="mt-1 font-semibold text-horizon-white"
                    :style="uploaderNameColor(detailItem.uploader) ? { color: uploaderNameColor(detailItem.uploader) } : undefined"
                  >
                    {{ detailItem.uploader?.rsi_handle || detailItem.uploader?.discord_name || 'Unknown' }}
                  </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-black/10 p-3">
                  <div class="text-xs uppercase tracking-wide text-text-muted">
                    Uploaded
                  </div>

                  <div class="mt-1 font-semibold text-horizon-white">
                    {{ formatDate(detailItem.created_at) }}
                  </div>
                </div>
              </div>
            </section>

            <section class="hz-surface-welcome rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/20 p-4">
              <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Editable Metadata
                </div>

                <p class="mt-1 text-sm text-text-secondary">
                  Update the filename display and alt text.
                </p>
              </div>

              <div class="space-y-4">
                <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  Name
                </label>

                  <HorizonInput
                    :value="detailFilename"
                    class="w-full"
                    placeholder="Display name..."
                    @input="$emit('update:filename', $event.target.value)"
                  />
                </div>

                <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  Alt Text
                </label>

                  <HorizonInput
                    :value="detailAltText"
                    class="w-full"
                    placeholder="Describe the image..."
                    @input="$emit('update:alt-text', $event.target.value)"
                  />
                </div>
              </div>
            </section>

            <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <div class="mb-4">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  URLs
                </div>

                <p class="mt-1 text-sm text-text-secondary">
                  Click a URL row to copy it.
                </p>
              </div>

              <div class="space-y-2">
                <button
                  v-for="(url, label) in detailUrls"
                  :key="label"
                  type="button"
                  class="w-full rounded-xl border border-white/[0.055] bg-white/[0.024] p-3 text-left transition hover:border-white/[0.055] hover:bg-white/[0.055]"
                  :title="'Click to copy'"
                  @click="$emit('copy', url)"
                >
                  <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                    {{ label }}
                  </div>

                  <div class="mt-1 truncate text-xs text-text-secondary">
                    {{ url }}
                  </div>
                </button>
              </div>
            </section>
          </aside>
        </div>
      </div>

    <template #footer>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex flex-wrap gap-2">
            <a
              :href="detailItem ? `/admin/media/${detailItem.id}/download` : '#'"
              class="inline-flex items-center justify-center rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-text-secondary transition hover:border-white/[0.055] hover:bg-white/[0.055] hover:text-horizon-white"
            >
              Download Original
            </a>

            <HorizonButton
              variant="danger"
              size="sm"
              @click="$emit('delete', detailItem.id)"
            >
              Delete
            </HorizonButton>
          </div>

          <div class="flex flex-wrap gap-2 lg:justify-end">
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
              @click="$emit('save')"
            >
              Save Metadata
            </HorizonButton>
          </div>
        </div>
    </template>
  </HorizonModal>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonModal from '@/Components/HorizonModal.vue'

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




