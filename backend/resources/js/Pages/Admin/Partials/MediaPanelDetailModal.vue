<template>
  <div
    v-if="detailItem"
    class="fixed inset-0 z-[90] flex items-center justify-center bg-black/75 p-4 backdrop-blur-md"
    @click.self="$emit('close')"
  >
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
      <div class="absolute left-1/4 top-10 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-blue)]/16 blur-3xl"></div>
      <div class="absolute bottom-10 right-1/4 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-magenta)]/14 blur-3xl"></div>
    </div>

    <div class="relative z-10 flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/45 bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))] shadow-[0_0_72px_rgba(67,56,202,0.28)] hz-animate-pop">
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <header class="relative shrink-0 border-b border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Media Asset Inspector
            </div>

            <div class="mt-1 truncate text-2xl font-black text-horizon-white">
              {{ detailItem.original_filename }}
            </div>

            <div class="mt-2 flex flex-wrap gap-2">
              <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                #{{ detailItem.id }}
              </span>

              <span class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                {{ collectionLabel(detailItem.collection) }}
              </span>

              <span class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-semibold text-text-secondary">
                {{ detailItem.human_size }}
              </span>
            </div>
          </div>

          <button
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] text-lg font-bold text-text-secondary transition hover:border-[color:var(--horizon-sunset-magenta)]/35 hover:bg-[color:var(--horizon-sunset-magenta)]/10 hover:text-horizon-white"
            aria-label="Close detail modal"
            @click="$emit('close')"
          >
            ✕
          </button>
        </div>
      </header>

      <div class="relative flex-1 overflow-y-auto p-5">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
          <section class="overflow-hidden rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-black/30">
            <img
              :src="detailItem.medium_url || detailItem.url"
              :alt="detailItem.alt_text || detailItem.original_filename"
              class="max-h-[34rem] w-full object-contain"
            />

            <div class="border-t border-white/10 bg-white/[0.025] p-4">
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
            <section class="rounded-[1.5rem] border border-white/10 bg-white/[0.025] p-4">
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

            <section class="rounded-[1.5rem] border border-[color:var(--horizon-sunset-blue)]/20 bg-[color:var(--horizon-sunset-blue)]/10 p-4">
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

                  <input
                    :value="detailFilename"
                    class="hz-input w-full"
                    placeholder="Display name..."
                    @input="$emit('update:filename', $event.target.value)"
                  />
                </div>

                <div>
                  <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                    Alt Text
                  </label>

                  <input
                    :value="detailAltText"
                    class="hz-input w-full"
                    placeholder="Describe the image..."
                    @input="$emit('update:alt-text', $event.target.value)"
                  />
                </div>
              </div>
            </section>

            <section class="rounded-[1.5rem] border border-[color:var(--horizon-sunset-magenta)]/20 bg-[color:var(--horizon-sunset-magenta)]/10 p-4">
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
                  class="w-full rounded-xl border border-white/10 bg-white/[0.025] p-3 text-left transition hover:border-[color:var(--horizon-sunset-blue)]/30 hover:bg-white/[0.055]"
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

      <footer class="relative shrink-0 border-t border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex flex-wrap gap-2">
            <a
              :href="`/admin/media/${detailItem.id}/download`"
              class="inline-flex items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary transition hover:border-[color:var(--horizon-sunset-blue)]/30 hover:bg-white/[0.055] hover:text-horizon-white"
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
      </footer>
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