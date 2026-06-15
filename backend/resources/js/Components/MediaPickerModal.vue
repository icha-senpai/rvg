<template>
  <HorizonModal
    :open="open"
    close-label="Close media picker"
    max-width-class="max-w-4xl"
    @close="close"
  >
    <template #header>
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
          Media Library
        </div>

        <div class="mt-1 text-2xl font-black text-horizon-white">
          {{ title }}
        </div>

        <p class="mt-1 text-sm text-text-secondary">
          Browse existing assets or upload a new image into the Horizon media library.
        </p>
      </div>
    </template>

    <div class="relative flex-1 overflow-y-auto p-5">
        <div class="hz-surface-welcome mb-5 flex flex-wrap gap-2 rounded-[1.5rem] border border-white/[0.055] p-3">
          <HorizonButton
            size="sm"
            :variant="activeTab === 'browse' ? 'primary' : 'ghost'"
            @click="activeTab = 'browse'"
          >
            Browse
          </HorizonButton>
          <HorizonButton
            v-if="allowUpload"
            size="sm"
            :variant="activeTab === 'upload' ? 'primary' : 'ghost'"
            @click="activeTab = 'upload'"
          >
            Upload New
          </HorizonButton>
        </div>

        <!-- BROWSE TAB -->
        <div v-if="activeTab === 'browse'" class="space-y-5">
          <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
            <div class="mb-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Search Library
              </div>
              <p class="mt-1 text-sm text-text-secondary">
                Filter this media collection before picking an image.
              </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div class="w-full sm:max-w-xs">
                <HorizonInput
                  v-model="search"
                  type="text"
                  class="w-full"
                  placeholder="Search..."
                  @keyup.enter="loadMedia(1)"
                />
              </div>
              <HorizonButton variant="primary" size="sm" @click="loadMedia(1)">Search</HorizonButton>
            </div>

            <div
              v-if="pickerError"
              class="mt-4 rounded-[1rem] border border-red-300/25 bg-red-300/10 px-4 py-3 text-sm font-semibold text-red-100"
            >
              {{ pickerError }}
            </div>
          </section>

          <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
            <div v-if="loading" class="rounded-[1rem] border border-dashed border-white/15 px-4 py-10 text-center text-sm text-text-secondary">
              Loading...
            </div>

            <div v-else-if="!items.length" class="rounded-[1rem] border border-dashed border-white/15 px-4 py-10 text-center text-sm text-text-secondary">
              No media found in this collection.
            </div>

            <div v-else class="space-y-4">
              <div class="hz-picker-grid">
                <div
                  v-for="item in items"
                  :key="item.id"
                  class="hz-picker-thumb"
                  :class="{ 'hz-picker-thumb-selected': selectedId === item.id }"
                  @click="selectItem(item)"
                >
                  <img
                    :src="item.thumbnail_url || item.medium_url || item.url"
                    :alt="item.alt_text || item.original_filename"
                    loading="lazy"
                  />
                </div>
              </div>

              <div v-if="lastPage > 1" class="flex flex-wrap items-center justify-center gap-3 border-t border-white/[0.055] pt-4">
                <HorizonButton
                  variant="ghost"
                  size="sm"
                  :disabled="currentPage <= 1"
                  @click="loadMedia(currentPage - 1)"
                >
                  Previous
                </HorizonButton>

                <div class="text-sm text-text-secondary">{{ currentPage }} / {{ lastPage }}</div>

                <HorizonButton
                  variant="ghost"
                  size="sm"
                  :disabled="currentPage >= lastPage"
                  @click="loadMedia(currentPage + 1)"
                >
                  Next
                </HorizonButton>
              </div>
            </div>
          </section>
        </div>

        <!-- UPLOAD TAB -->
        <div v-if="allowUpload && activeTab === 'upload'" class="space-y-5">
          <section class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
            <div class="mb-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                File Metadata
              </div>
              <p class="mt-1 text-sm text-text-secondary">
                Add optional alt text, select an image, then upload it directly into this collection.
              </p>
            </div>

            <div class="space-y-4">
              <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Alt Text</label>
                <HorizonInput v-model="uploadAltText" class="w-full" placeholder="Describe the image..." />
              </div>

              <HorizonFileField
                label="File"
                description="JPEG, PNG, WEBP, or GIF."
                button-label="Choose Image"
                :selected-name="uploadFile?.name || ''"
                accept="image/jpeg,image/png,image/webp,image/gif"
                @change="handleFileSelect"
              />
            </div>
          </section>

          <section
            v-if="uploadPreview"
            class="hz-surface-welcome overflow-hidden rounded-[1.5rem] border border-white/[0.055]"
          >
            <img :src="uploadPreview" class="max-h-72 w-full bg-[color:var(--color-bg-elevated)] object-contain" />
            <div class="border-t border-white/[0.055] px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
              Preview
            </div>
          </section>

          <div
            v-if="uploadError"
            class="rounded-[1.25rem] border border-red-300/25 bg-red-300/10 p-4 text-sm font-semibold text-red-100"
          >
            {{ uploadError }}
          </div>

          <div class="flex justify-end">
            <HorizonButton
              variant="primary"
              size="sm"
              :disabled="uploading || !uploadFile"
              @click="submitUpload"
            >
              {{ uploading ? 'Uploading...' : 'Upload & Select' }}
            </HorizonButton>
          </div>
        </div>
      </div>

    <template v-if="selectedItem" #footer>
        <div class="hz-surface-welcome flex items-center gap-3 rounded-[1.25rem] border border-white/[0.055] bg-white/[0.02] p-3">
          <img
            :src="selectedItem.thumbnail_url || selectedItem.url"
            :alt="selectedItem.alt_text"
            class="h-12 w-12 rounded-lg object-cover"
          />

          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-semibold text-horizon-white">
              {{ selectedItem.original_filename }}
            </div>
            <div class="text-xs text-text-secondary">{{ selectedItem.human_size }}</div>
          </div>

          <HorizonButton variant="primary" size="sm" @click="confirmSelection">
            Select
          </HorizonButton>
        </div>
    </template>
  </HorizonModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonFileField from '@/Components/HorizonFileField.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonModal from '@/Components/HorizonModal.vue';

const props = defineProps({
  /** Whether the modal is open */
  open: { type: Boolean, default: false },
  /** Which collection to filter by */
  collection: { type: String, required: true },
  squadronId: { type: [Number, String], default: null },
  /** Modal title */
  title: { type: String, default: 'Select Image' },
  allowUpload: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'selected']);
const page = usePage();

/* ============================================================
   BROWSE STATE
============================================================ */
const activeTab = ref('browse');
const items = ref([]);
const loading = ref(false);
const search = ref('');
const currentPage = ref(1);
const lastPage = ref(1);
const selectedId = ref(null);
const selectedItem = ref(null);

const mediaPickerPayload = computed(() => {
  const payload = page.props?.mediaPicker ?? null;
  if (!payload) return null;
  if (payload.collection !== props.collection) return null;

  const payloadSquadronId = payload.squadronId === null || payload.squadronId === undefined
    ? ''
    : String(payload.squadronId);
  const propSquadronId = props.squadronId === null || props.squadronId === undefined || String(props.squadronId) === ''
    ? ''
    : String(props.squadronId);

  return payloadSquadronId === propSquadronId ? payload : null;
});

const flashMedia = computed(() => page.props?.flash?.media ?? null);
const pickerError = computed(() => mediaPickerPayload.value?.error ?? null);

function getCurrentQueryParams() {
  const params = new URLSearchParams(window.location.search);
  return Object.fromEntries(params.entries());
}

function applyPickerQuery(query, pageNumber = 1) {
  query.media_picker = '1';
  query.media_picker_collection = props.collection;

  if (props.squadronId !== null && props.squadronId !== undefined && String(props.squadronId) !== '') {
    query.media_picker_squadron_id = String(props.squadronId);
  } else {
    delete query.media_picker_squadron_id;
  }

  const trimmedSearch = String(search.value ?? '').trim();
  if (trimmedSearch) {
    query.media_picker_search = trimmedSearch;
  } else {
    delete query.media_picker_search;
  }

  if (pageNumber > 1) {
    query.media_picker_page = String(pageNumber);
  } else {
    delete query.media_picker_page;
  }

  return query;
}

function clearPickerQuery() {
  const query = getCurrentQueryParams();
  delete query.media_picker;
  delete query.media_picker_collection;
  delete query.media_picker_squadron_id;
  delete query.media_picker_search;
  delete query.media_picker_page;

  const nextSearch = new URLSearchParams(query).toString();
  const nextUrl = `${window.location.pathname}${nextSearch ? `?${nextSearch}` : ''}${window.location.hash ?? ''}`;

  if (window.history?.replaceState) {
    window.history.replaceState(window.history.state, '', nextUrl);
    return;
  }

  router.get(window.location.pathname, query, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ['mediaPicker'],
  });
}

async function loadMedia(page = 1) {
  loading.value = true;

  const query = applyPickerQuery(getCurrentQueryParams(), page);

  router.get(window.location.pathname, query, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ['mediaPicker', 'flash'],
    onFinish: () => {
      loading.value = false;
    },
  });
}

function selectItem(item) {
  selectedId.value = item.id;
  selectedItem.value = item;
}

function confirmSelection() {
  if (selectedItem.value) {
    emit('selected', selectedItem.value);
    close();
  }
}

/* ============================================================
   UPLOAD STATE
============================================================ */
const uploadFile = ref(null);
const uploadAltText = ref('');
const uploadPreview = ref(null);
const uploadError = ref('');
const uploading = ref(false);
function handleFileSelect(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  uploadFile.value = file;
  uploadError.value = '';

  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (e) => { uploadPreview.value = e.target.result; };
    reader.readAsDataURL(file);
  } else {
    uploadPreview.value = null;
  }
}

async function submitUpload() {
  if (!uploadFile.value) return;

  uploading.value = true;
  uploadError.value = '';

  const payload = {
    file: uploadFile.value,
    collection: props.collection,
    alt_text: uploadAltText.value || null,
  };

  if (props.squadronId !== null && props.squadronId !== undefined && String(props.squadronId) !== '') {
    payload.squadron_id = String(props.squadronId);
  }

  router.post('/media/upload', payload, {
    forceFormData: true,
    preserveScroll: true,
    preserveState: true,
    only: ['mediaPicker', 'flash'],
    onSuccess: () => {
      activeTab.value = 'browse';
    },
    onError: (errors) => {
      const firstKey = Object.keys(errors ?? {})[0];
      const firstValue = firstKey ? errors[firstKey] : null;
      const firstMessage = Array.isArray(firstValue) ? firstValue[0] : firstValue;
      uploadError.value = firstMessage ? String(firstMessage) : 'Upload failed.';
    },
    onFinish: () => {
      uploading.value = false;
    },
  });
}

/* ============================================================
   LIFECYCLE
============================================================ */
function close() {
  clearPickerQuery();
  emit('close');
}

function resetState() {
  activeTab.value = 'browse';
  items.value = [];
  search.value = '';
  selectedId.value = null;
  selectedItem.value = null;
  uploadFile.value = null;
  uploadAltText.value = '';
  uploadPreview.value = null;
  uploadError.value = '';
}

watch(
  () => mediaPickerPayload.value,
  (payload) => {
    items.value = Array.isArray(payload?.items) ? payload.items : [];
    currentPage.value = Number(payload?.pagination?.currentPage ?? 1);
    lastPage.value = Number(payload?.pagination?.lastPage ?? 1);

    if (payload && typeof payload.search === 'string') {
      search.value = payload.search;
    }
  },
  { immediate: true }
);

watch(
  () => flashMedia.value,
  (flash) => {
    if (!props.open) return;
    if (!flash?.item?.id) return;
    if (flash.item.collection !== props.collection) return;

    selectedId.value = flash.item.id;
    selectedItem.value = flash.item;
    activeTab.value = 'browse';
    uploadError.value = '';
  }
);

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    resetState();
    if (!props.allowUpload && activeTab.value === 'upload') {
      activeTab.value = 'browse';
    }
    loadMedia(1);
  }
});

watch(() => props.allowUpload, (allowed) => {
  if (!allowed && activeTab.value === 'upload') {
    activeTab.value = 'browse';
  }
});
</script>

<style scoped>
.hz-picker-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: var(--space-sm);
}

.hz-picker-thumb {
  border: 2px solid transparent;
  border-radius: var(--radius-xs);
  overflow: hidden;
  cursor: pointer;
  transition: border-color var(--motion-fast) var(--ease-smooth),
              box-shadow var(--motion-fast) var(--ease-smooth);
}

.hz-picker-thumb:hover {
  border-color: var(--color-horizon-blue);
}

.hz-picker-thumb-selected {
  border-color: var(--horizon-sunset-orange) !important;
  box-shadow: 0 0 10px rgba(255, 138, 61, 0.3);
}

.hz-picker-thumb img {
  width: 100%;
  height: 90px;
  object-fit: cover;
  display: block;
  background: var(--color-bg-base);
}
</style>




