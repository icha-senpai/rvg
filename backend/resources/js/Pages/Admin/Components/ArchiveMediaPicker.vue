<script setup>
import { computed, onMounted, ref } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: 'Image' },
  collection: { type: String, default: 'site_asset' },
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const isLoading = ref(false)
const error = ref('')
const search = ref('')
const media = ref([])

const selectedUrl = computed({
  get: () => props.modelValue ?? '',
  set: value => emit('update:modelValue', value ?? ''),
})

const filteredMedia = computed(() => {
  const needle = search.value.trim().toLowerCase()

  if (!needle) return media.value

  return media.value.filter(item => {
    const filename = String(item.original_filename ?? '').toLowerCase()
    const alt = String(item.alt_text ?? '').toLowerCase()

    return filename.includes(needle) || alt.includes(needle)
  })
})

function mediaDisplayUrl(item) {
  return item.display_url || item.medium_url || item.thumbnail_url || item.url || ''
}

function mediaPreviewUrl(item) {
  return item.thumbnail_url || item.medium_url || item.display_url || item.url || ''
}

async function loadMedia() {
  isLoading.value = true
  error.value = ''

  try {
    const response = await fetch(route('media.list', {
      collection: props.collection,
      per_page: 60,
    }), {
      headers: {
        Accept: 'application/json',
      },
    })

    const data = await response.json()

    if (!response.ok || data.status !== 'ok') {
      throw new Error(data.message || 'Unable to load media.')
    }

    media.value = data.payload?.media?.data ?? []
  } catch (err) {
    error.value = err?.message || 'Unable to load media.'
  } finally {
    isLoading.value = false
  }
}

function openPicker() {
  isOpen.value = true

  if (!media.value.length) {
    loadMedia()
  }
}

function closePicker() {
  isOpen.value = false
}

function selectMedia(item) {
  selectedUrl.value = mediaDisplayUrl(item)
  closePicker()
}

function clearSelection() {
  selectedUrl.value = ''
}

onMounted(() => {
  if (isOpen.value) {
    loadMedia()
  }
})
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between gap-3">
      <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">{{ label }}</label>
      <button type="button" class="text-xs font-bold text-[color:var(--horizon-sunset-blue)] hover:text-horizon-white" @click="openPicker">
        Choose from Media
      </button>
    </div>

    <div class="rounded-2xl border border-white/10 bg-black/20 p-3">
      <div v-if="selectedUrl" class="overflow-hidden rounded-xl border border-white/10 bg-black/30">
        <img :src="selectedUrl" :alt="label" class="h-32 w-full object-cover" />
      </div>
      <div v-else class="flex h-24 items-center justify-center rounded-xl border border-dashed border-white/10 bg-black/20 text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
        No image selected
      </div>

      <input
        v-model="selectedUrl"
        class="mt-3 w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-xs text-horizon-white"
        placeholder="Selected image URL"
      />

      <div class="mt-2 flex flex-wrap gap-2">
        <button type="button" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1.5 text-xs font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20" @click="openPicker">
          Browse
        </button>
        <button v-if="selectedUrl" type="button" class="rounded-xl border border-white/10 px-3 py-1.5 text-xs font-bold text-text-secondary hover:text-horizon-white" @click="clearSelection">
          Clear
        </button>
      </div>
    </div>

    <teleport to="body">
      <div v-if="isOpen" class="fixed inset-0 z-[11000] flex items-center justify-center bg-black/70 p-4" @click.self="closePicker">
        <section class="max-h-[85vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/10 bg-[color:var(--horizon-void-900)] shadow-[0_24px_80px_rgba(0,0,0,0.55)]">
          <header class="flex flex-col gap-4 border-b border-white/10 p-5 md:flex-row md:items-end md:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Media Picker</div>
              <h2 class="mt-1 text-2xl font-black text-horizon-white">Choose {{ label }}</h2>
              <p class="mt-1 text-sm text-text-secondary">Showing images from the {{ collection }} collection.</p>
            </div>

            <div class="flex gap-2">
              <input
                v-model="search"
                type="search"
                placeholder="Filter loaded media..."
                class="w-56 rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-horizon-white outline-none placeholder:text-text-muted focus:border-[color:var(--horizon-sunset-blue)]/45"
              />
              <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white" @click="closePicker">
                Close
              </button>
            </div>
          </header>

          <div class="max-h-[62vh] overflow-y-auto p-5">
            <div v-if="isLoading" class="rounded-2xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
              Loading media...
            </div>

            <div v-else-if="error" class="rounded-2xl border border-red-300/25 bg-red-300/10 p-6 text-sm text-red-200">
              {{ error }}
            </div>

            <div v-else-if="filteredMedia.length" class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
              <button
                v-for="item in filteredMedia"
                :key="item.id"
                type="button"
                class="group overflow-hidden rounded-2xl border border-white/10 bg-white/[0.035] text-left transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45 hover:bg-white/[0.055]"
                @click="selectMedia(item)"
              >
                <img :src="mediaPreviewUrl(item)" :alt="item.alt_text || item.original_filename" class="h-32 w-full bg-black/30 object-cover" />
                <div class="p-3">
                  <div class="truncate text-sm font-bold text-horizon-white">{{ item.original_filename }}</div>
                  <div class="mt-1 text-xs text-text-muted">{{ item.human_size || item.mime_type }}</div>
                </div>
              </button>
            </div>

            <div v-else class="rounded-2xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
              No media found in this collection. Upload site assets from the Media admin panel first.
            </div>
          </div>
        </section>
      </div>
    </teleport>
  </div>
</template>
