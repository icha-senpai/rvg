<script setup>
import { computed, onMounted, ref } from 'vue'
import { route } from 'ziggy-js'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: 'Image' },
  collection: { type: String, default: 'site_asset' },
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const isLoading = ref(false)
const isUploading = ref(false)
const error = ref('')
const uploadError = ref('')
const uploadAltText = ref('')
const uploadInput = ref(null)
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

const hasSearch = computed(() => search.value.trim().length > 0)

function mediaDisplayUrl(item) {
  return item.display_url || item.medium_url || item.thumbnail_url || item.url || ''
}

function mediaPreviewUrl(item) {
  return item.thumbnail_url || item.medium_url || item.display_url || item.url || ''
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

async function loadMedia() {
  isLoading.value = true
  error.value = ''

  try {
    const response = await fetch(route('media.list', {
      collection: props.collection,
      per_page: 60,
    }), {
      headers: { Accept: 'application/json' },
    })

    const data = await response.json()
    if (!response.ok || data.status !== 'ok') throw new Error(data.message || 'Unable to load media.')

    media.value = data.payload?.media?.data ?? []
  } catch (err) {
    error.value = err?.message || 'Unable to load media.'
  } finally {
    isLoading.value = false
  }
}

async function uploadMedia() {
  const file = uploadInput.value?.files?.[0]

  if (!file) {
    uploadError.value = 'Choose an image file first.'
    return
  }

  isUploading.value = true
  uploadError.value = ''

  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('collection', props.collection)

    if (uploadAltText.value.trim()) {
      formData.append('alt_text', uploadAltText.value.trim())
    }

    const response = await fetch(route('media.upload'), {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: formData,
    })

    const data = await response.json()
    if (!response.ok || data.status !== 'ok') throw new Error(data.message || 'Unable to upload media.')

    const uploaded = data.payload?.media
    if (!uploaded) throw new Error('Upload completed, but no media payload was returned.')

    media.value = [uploaded, ...media.value.filter(item => item.id !== uploaded.id)]
    selectedUrl.value = mediaDisplayUrl(uploaded)
    uploadAltText.value = ''

    if (uploadInput.value) uploadInput.value.value = ''
  } catch (err) {
    uploadError.value = err?.message || 'Unable to upload media.'
  } finally {
    isUploading.value = false
  }
}

function openPicker() {
  isOpen.value = true
  if (!media.value.length) loadMedia()
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
  if (isOpen.value) loadMedia()
})
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between gap-3">
      <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">{{ label }}</label>
      <HorizonButton type="button" variant="ghost" size="xs" @click="openPicker">Choose or Upload</HorizonButton>
    </div>

    <div class="rounded-2xl border border-white/10 bg-black/20 p-3">
      <div v-if="selectedUrl" class="overflow-hidden rounded-xl border border-white/10 bg-black/30">
        <img :src="selectedUrl" :alt="label" class="h-32 w-full object-cover" />
      </div>
      <div v-else class="flex h-24 flex-col items-center justify-center rounded-xl border border-dashed border-white/10 bg-black/20 px-4 text-center">
        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">No image selected</div>
        <div class="mt-1 text-xs text-text-muted/80">Paste a URL or choose from the media library.</div>
      </div>

      <HorizonInput v-model="selectedUrl" class="mt-3" placeholder="Selected image URL" />

      <div class="mt-2 flex flex-wrap gap-2">
        <HorizonButton type="button" variant="ghost" size="xs" @click="openPicker">Browse / Upload</HorizonButton>
        <HorizonButton v-if="selectedUrl" type="button" variant="ghost" size="xs" @click="clearSelection">Clear</HorizonButton>
      </div>
    </div>

    <teleport to="body">
      <div v-if="isOpen" class="fixed inset-0 z-[11000] flex items-start justify-center overflow-y-auto bg-black/70 p-3 sm:items-center sm:p-4" @click.self="closePicker">
        <section class="my-auto max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl overflow-hidden rounded-[1.5rem] border border-white/10 bg-[color:var(--horizon-void-900)] shadow-[0_24px_80px_rgba(0,0,0,0.55)] sm:max-h-[88vh] sm:rounded-[2rem]">
          <header class="flex flex-col gap-4 border-b border-white/10 p-5 md:flex-row md:items-end md:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Media Picker</div>
              <h2 class="mt-1 text-2xl font-black text-horizon-white">Choose {{ label }}</h2>
              <p class="mt-1 text-sm text-text-secondary">Select an existing image or upload a new one into {{ collection }}.</p>
            </div>

            <div class="flex flex-col gap-2 md:flex-row md:items-end">
              <HorizonInput v-model="search" type="search" placeholder="Filter loaded media..." class="w-full md:w-56" />
              <HorizonButton type="button" variant="ghost" @click="closePicker">Close</HorizonButton>
            </div>
          </header>

          <div class="grid max-h-[calc(100dvh-10rem)] overflow-hidden lg:max-h-[70vh] lg:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="border-b border-white/10 p-5 lg:border-b-0 lg:border-r">
              <div class="rounded-2xl border border-white/[0.055] bg-white/[0.042] p-4">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--horizon-sunset-blue)]">Upload New</div>
                <p class="mt-2 text-sm leading-6 text-text-secondary">
                  Upload an image directly into the media library. It will be selected automatically after upload.
                </p>

                <input
                  ref="uploadInput"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  class="mt-4 block w-full cursor-pointer rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm text-text-secondary file:mr-3 file:rounded-lg file:border-0 file:bg-[color:var(--horizon-sunset-blue)]/20 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/35"
                />

                <HorizonInput v-model="uploadAltText" class="mt-3" placeholder="Alt text / description" />

                <HorizonButton type="button" class="mt-3 w-full" :disabled="isUploading" @click="uploadMedia">
                  {{ isUploading ? 'Uploading...' : 'Upload & Select' }}
                </HorizonButton>

                <div v-if="uploadError" class="mt-3 rounded-xl border border-red-300/25 bg-red-300/10 p-3 text-sm text-red-200">
                  {{ uploadError }}
                </div>
              </div>
            </aside>

            <div class="overflow-y-auto p-5">
              <div v-if="isLoading" class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-6 text-sm text-text-secondary">
                Loading media...
              </div>

              <div v-else-if="error" class="rounded-2xl border border-red-300/25 bg-red-300/10 p-6 text-sm text-red-200">
                {{ error }}
              </div>

              <div v-else-if="filteredMedia.length" class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                <button
                  v-for="item in filteredMedia"
                  :key="item.id"
                  type="button"
                  class="group overflow-hidden rounded-2xl border border-white/[0.055] bg-white/[0.024] text-left transition hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-blue)]/45 hover:bg-white/[0.055]"
                  @click="selectMedia(item)"
                >
                  <img :src="mediaPreviewUrl(item)" :alt="item.alt_text || item.original_filename" class="h-32 w-full bg-black/30 object-cover" />
                  <div class="p-3">
                    <div class="truncate text-sm font-bold text-horizon-white">{{ item.original_filename }}</div>
                    <div class="mt-1 text-xs text-text-muted">{{ item.human_size || item.mime_type }}</div>
                  </div>
                </button>
              </div>

              <div v-else-if="hasSearch" class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-6 text-sm text-text-secondary">
                No loaded media matched “{{ search }}”. Clear the filter or upload a new image.
              </div>

              <div v-else class="rounded-2xl border border-white/[0.055] bg-white/[0.024] p-6 text-sm text-text-secondary">
                No media found in this collection yet. Upload an image from the panel on the left and it will be selected automatically.
              </div>
            </div>
          </div>
        </section>
      </div>
    </teleport>
  </div>
</template>







