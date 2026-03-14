<template>
  <div
    v-if="open"
    class="hz-overlay flex items-center justify-center"
    @click.self="close"
  >
    <div class="hz-modal hz-stack hz-animate-pop" style="max-width: 48rem; max-height: 85vh; overflow-y: auto;">

      <!-- HEADER -->
      <div class="hz-row-between">
        <div class="hz-title-lg">{{ title }}</div>
        <HorizonButton variant="primary" size="sm" @click="close">✕</HorizonButton>
      </div>

      <!-- TABS: Browse / Upload -->
      <div class="hz-row" style="border-bottom: 1px solid var(--color-bg-hover); padding-bottom: var(--space-sm);">
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
      <div v-if="activeTab === 'browse'" class="hz-stack">

        <!-- SEARCH -->
        <div class="hz-row">
          <input
            v-model="search"
            @keyup.enter="loadMedia(1)"
            type="text"
            class="hz-input"
            style="max-width: 260px;"
            placeholder="Search..."
          />
          <HorizonButton variant="primary" size="sm" @click="loadMedia(1)">Search</HorizonButton>
        </div>

        <!-- LOADING -->
        <div v-if="loading" class="hz-text-muted" style="text-align: center; padding: var(--space-lg);">
          Loading...
        </div>

        <!-- EMPTY -->
        <div v-else-if="!items.length" class="hz-text-muted" style="text-align: center; padding: var(--space-lg);">
          No media found in this collection.
        </div>

        <!-- GRID -->
        <div v-else class="hz-picker-grid">
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

        <!-- PAGINATION -->
        <div v-if="lastPage > 1" class="flex items-center justify-center flex-wrap gap-3">
          <HorizonButton
            variant="ghost"
            size="sm"
            :disabled="currentPage <= 1"
            @click="loadMedia(currentPage - 1)"
          >
            Previous
          </HorizonButton>

          <div class="hz-text-muted">{{ currentPage }} / {{ lastPage }}</div>

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

      <!-- UPLOAD TAB -->
      <div v-if="allowUpload && activeTab === 'upload'" class="hz-stack">

        <div>
          <label class="hz-text-soft">Alt Text (optional)</label>
          <input v-model="uploadAltText" class="hz-input" placeholder="Describe the image..." />
        </div>

        <div>
          <label class="hz-text-soft">File</label>
          <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            class="hz-input"
            style="padding: 0.5rem;"
            @change="handleFileSelect"
          />
        </div>

        <!-- PREVIEW -->
        <div v-if="uploadPreview" style="border-radius: var(--radius-sm); overflow: hidden; max-height: 180px;">
          <img :src="uploadPreview" style="width: 100%; height: 180px; object-fit: contain; background: var(--color-bg-elevated);" />
        </div>

        <!-- ERROR -->
        <div v-if="uploadError" class="hz-text-soft" style="color: var(--color-state-danger);">
          {{ uploadError }}
        </div>

        <HorizonButton
          variant="primary"
          size="sm"
          :disabled="uploading || !uploadFile"
          @click="submitUpload"
          style="align-self: flex-end;"
        >
          {{ uploading ? 'Uploading...' : 'Upload & Select' }}
        </HorizonButton>
      </div>

      <!-- SELECTED PREVIEW + CONFIRM -->
      <div v-if="selectedItem" class="hz-panel hz-row" style="background: var(--color-bg-elevated);">
        <img
          :src="selectedItem.thumbnail_url || selectedItem.url"
          :alt="selectedItem.alt_text"
          style="width: 48px; height: 48px; object-fit: cover; border-radius: var(--radius-xs);"
        />

        <div class="hz-stack-2xs" style="flex: 1; min-width: 0;">
          <div class="hz-text-soft" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
            {{ selectedItem.original_filename }}
          </div>
          <div class="hz-tiny">{{ selectedItem.human_size }}</div>
        </div>

        <HorizonButton variant="primary" size="sm" @click="confirmSelection">
          Select
        </HorizonButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import HorizonButton from '@/Components/HorizonButton.vue';

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

async function loadMedia(page = 1) {
  loading.value = true;

  const params = new URLSearchParams();
  params.set('page', page);
  params.set('collection', props.collection);
  if (props.squadronId !== null && props.squadronId !== undefined && String(props.squadronId) !== '') {
    params.set('squadron_id', String(props.squadronId));
  }
  if (search.value) params.set('search', search.value);

  try {
    const res = await fetch(`/media/list?${params.toString()}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    const json = await res.json();
    const payload = json.payload?.media;

    if (payload) {
      items.value = payload.data || [];
      currentPage.value = payload.current_page || 1;
      lastPage.value = payload.last_page || 1;
    }
  } catch (err) {
    console.error('MediaPicker: failed to load', err);
  } finally {
    loading.value = false;
  }
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
const fileInput = ref(null);

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

  const formData = new FormData();
  formData.append('file', uploadFile.value);
  formData.append('collection', props.collection);
  if (props.squadronId !== null && props.squadronId !== undefined && String(props.squadronId) !== '') {
    formData.append('squadron_id', String(props.squadronId));
  }
  if (uploadAltText.value) formData.append('alt_text', uploadAltText.value);

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const res = await fetch('/media/upload', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: formData,
    });

    const json = await res.json();

    if (!res.ok) {
      const errors = json.errors;
      uploadError.value = errors
        ? Object.values(errors).flat().join(' ')
        : (json.message || 'Upload failed.');
      return;
    }

    const newMedia = json.payload?.media;
    if (newMedia) {
      selectedId.value = newMedia.id;
      selectedItem.value = newMedia;
      activeTab.value = 'browse';
      loadMedia(1);
    }
  } catch (err) {
    uploadError.value = 'Network error. Please try again.';
  } finally {
    uploading.value = false;
  }
}

/* ============================================================
   LIFECYCLE
============================================================ */
function close() {
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

function handleKeydown(e) {
  if (e.key === 'Escape' && props.open) close();
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));
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
