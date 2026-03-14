<template>
  <div class="mx-auto max-w-5xl hz-stack">

    <!-- STATS BAR -->
    <div class="hz-panel hz-row-between !border !border-[color:var(--horizon-sunset-blue)]">
      <div class="hz-stack-sm">
        <div class="hz-title-lg">Media Library</div>
        <div class="hz-text-muted">
          {{ totalCount }} files · {{ humanTotalSize }} · If close to 8 GB consider Cloudflare R2 Storage
        </div>
      </div>

      <HorizonButton variant="primary" size="sm" @click="openUploadModal">
        Upload
      </HorizonButton>
    </div>

    <!-- FILTERS -->
    <div class="hz-panel hz-stack-sm !border !border-[color:var(--horizon-sunset-blue)]">
      <div class="hz-row" style="flex-wrap: wrap;">

        <input
          v-model="filters.search"
          @keyup.enter="loadMedia"
          type="text"
          class="hz-input"
          style="max-width: 280px;"
          placeholder="Search filename or alt text..."
        />

        <HorizonSelect
          v-model="filters.collection"
          :options="collectionOptions"
          label=""
          style="max-width: 200px;"
        />

        <HorizonButton variant="primary" size="sm" @click="loadMedia">
          Search
        </HorizonButton>

        <HorizonButton variant="ghost" size="sm" @click="clearFilters">
          Clear
        </HorizonButton>
      </div>
    </div>

    <!-- LOADING STATE -->
    <div v-if="loading" class="hz-panel hz-text-muted" style="text-align: center; padding: var(--space-xl);">
      Loading media...
    </div>

    <!-- EMPTY STATE -->
    <div v-else-if="!media.length" class="hz-panel hz-text-muted" style="text-align: center; padding: var(--space-xl);">
      No media found.
    </div>

    <!-- MEDIA GRID -->
    <div v-else class="hz-media-grid">
      <div
        v-for="item in media"
        :key="item.id"
        class="hz-media-thumb"
        @click="openDetailModal(item)"
      >
        <img
          :src="item.thumbnail_url || item.medium_url || item.url"
          :alt="item.alt_text || item.original_filename"
          loading="lazy"
        />

        <div class="hz-media-thumb-info">
          <div class="hz-tiny" style="color: var(--color-horizon-offwhite); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
            {{ item.original_filename }}
          </div>
          <div class="hz-tiny" style="color: var(--color-text-muted);">
            {{ item.human_size }} · {{ collectionLabel(item.collection) }}
          </div>
        </div>
      </div>
    </div>

    <!-- PAGINATION -->
    <div v-if="pagination.lastPage > 1" class="flex items-center justify-center flex-wrap gap-3">
      <HorizonButton
        variant="primary"
        size="sm"
        :disabled="!pagination.prevUrl"
        @click="goToPage(pagination.currentPage - 1)"
      >
        Previous
      </HorizonButton>

      <div class="hz-text-soft">
        Page {{ pagination.currentPage }} / {{ pagination.lastPage }}
      </div>

      <HorizonButton
        variant="primary"
        size="sm"
        :disabled="!pagination.nextUrl"
        @click="goToPage(pagination.currentPage + 1)"
      >
        Next
      </HorizonButton>
    </div>

    <!-- UPLOAD MODAL -->
    <div
      v-if="uploadModalOpen"
      class="hz-overlay flex items-center justify-center"
      @click.self="closeUploadModal"
    >
      <div class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop" style="max-width: 28rem;">
        <div class="hz-row-between">
          <div class="hz-title-lg">Upload Media</div>
          <HorizonButton variant="primary" size="sm" @click="closeUploadModal">✕</HorizonButton>
        </div>

        <div class="hz-stack">
          <!-- COLLECTION SELECT -->
          <HorizonSelect
            v-model="uploadForm.collection"
            :options="uploadCollectionOptions"
            label="Collection"
          />

          <!-- ALT TEXT -->
          <div>
            <label class="hz-text-soft">Alt Text (optional)</label>
            <input v-model="uploadForm.alt_text" class="hz-input" placeholder="Describe the image..." />
          </div>

          <!-- FILE INPUT -->
          <div>
            <label class="hz-text-soft">File</label>
            <input
              ref="fileInputRef"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              class="hz-input"
              style="padding: 0.5rem;"
              @change="handleFileSelect"
            />
          </div>

          <!-- PREVIEW -->
          <div v-if="uploadPreviewUrl" style="border-radius: var(--radius-sm); overflow: hidden; max-height: 200px;">
            <img :src="uploadPreviewUrl" style="width: 100%; height: 200px; object-fit: contain; background: var(--color-bg-elevated);" />
          </div>

          <!-- ERROR -->
          <div v-if="uploadError" class="hz-text-soft" style="color: var(--color-state-danger);">
            {{ uploadError }}
          </div>
        </div>

        <div class="hz-row-between" style="padding-top: var(--space-sm);">
          <HorizonButton variant="ghost" size="sm" @click="closeUploadModal">Cancel</HorizonButton>
          <HorizonButton
            variant="primary"
            size="sm"
            :disabled="uploading || !uploadForm.file"
            @click="submitUpload"
          >
            {{ uploading ? 'Uploading...' : 'Upload' }}
          </HorizonButton>
        </div>
      </div>
    </div>

    <!-- DETAIL / EDIT MODAL -->
    <div
      v-if="detailItem"
      class="hz-overlay flex items-center justify-center"
      @click.self="closeDetailModal"
    >
      <div class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop" style="max-width: 36rem;">
        <div class="hz-row-between">
          <div class="hz-title-lg" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80%;">
            {{ detailItem.original_filename }}
          </div>
          <HorizonButton variant="primary" size="sm" @click="closeDetailModal">✕</HorizonButton>
        </div>

        <!-- IMAGE PREVIEW -->
        <div style="border-radius: var(--radius-sm); overflow: hidden; background: var(--color-bg-elevated);">
          <img
            :src="detailItem.medium_url || detailItem.url"
            :alt="detailItem.alt_text || detailItem.original_filename"
            style="width: 100%; max-height: 360px; object-fit: contain;"
          />
        </div>

        <!-- METADATA -->
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

        <!-- EDIT NAME -->
        <div>
          <label class="hz-text-soft">Name</label>
          <input v-model="detailFilename" class="hz-input" placeholder="Display name..." />
        </div>

        <!-- EDIT ALT TEXT -->
        <div>
          <label class="hz-text-soft">Alt Text</label>
          <input v-model="detailAltText" class="hz-input" placeholder="Describe the image..." />
        </div>

        <!-- URLS (copyable) -->
        <div class="hz-stack-sm">
          <div class="hz-text-soft">URLs</div>
          <div
            v-for="(url, label) in detailUrls"
            :key="label"
            class="hz-row"
            style="font-size: var(--text-tiny); cursor: pointer;"
            @click="copyToClipboard(url)"
            :title="'Click to copy'"
          >
            <span class="hz-text-muted" style="min-width: 80px;">{{ label }}:</span>
            <span class="hz-text-soft" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ url }}</span>
          </div>
        </div>

        <!-- ACTIONS -->
        <div class="hz-row-between" style="padding-top: var(--space-sm);">
          <div class="hz-row" style="gap: var(--space-sm);">
            <a
              :href="`/admin/media/${detailItem.id}/download`"
              class="hz-btn hz-btn-sm hz-btn-ghost"
            >
              Download Original
            </a>

            <HorizonButton variant="danger" size="sm" @click="deleteMedia(detailItem.id)">
              Delete
            </HorizonButton>
          </div>

          <div class="hz-row">
            <HorizonButton variant="ghost" size="sm" @click="closeDetailModal">Cancel</HorizonButton>
            <HorizonButton variant="primary" size="sm" @click="saveAltText">Save</HorizonButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonSelect from '@/Components/HorizonSelect.vue';

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

/* ============================================================
   STATE
============================================================ */
const media = ref([]);
const loading = ref(false);
const totalCount = ref(0);
const totalSize = ref(0);

const filters = ref({
  search: '',
  collection: null,
});

function uploaderNameColor(uploader) {
  const slug = getHighestOrgRoleSlug(uploader?.roles, uploader?.rank)
  return getOrgRoleColor(slug)
}

const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  prevUrl: null,
  nextUrl: null,
});

/* ============================================================
   COLLECTION OPTIONS
============================================================ */
const collectionLabels = {
  avatar: 'Avatars',
  squadron_emblem: 'Squadron Emblems',
  operation_image: 'Operation Images',
  ship_image: 'Ship Images',
  site_asset: 'Site Assets',
};

const collectionOptions = [
  { label: 'All Collections', value: null },
  { label: 'Avatars', value: 'avatar' },
  { label: 'Squadron Emblems', value: 'squadron_emblem' },
  { label: 'Operation Images', value: 'operation_image' },
  { label: 'Ship Images', value: 'ship_image' },
  { label: 'Site Assets', value: 'site_asset' },
];

const uploadCollectionOptions = collectionOptions.filter(o => o.value !== null);

function collectionLabel(collection) {
  return collectionLabels[collection] || collection;
}

/* ============================================================
   LOAD MEDIA (AJAX)
============================================================ */
async function loadMedia(page = 1) {
  loading.value = true;

  const params = new URLSearchParams();
  params.set('page', page);

  if (filters.value.search) params.set('search', filters.value.search);
  if (filters.value.collection) params.set('collection', filters.value.collection);

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
      media.value = payload.data || [];
      pagination.value = {
        currentPage: payload.current_page || 1,
        lastPage: payload.last_page || 1,
        prevUrl: payload.prev_page_url || null,
        nextUrl: payload.next_page_url || null,
      };
    }
  } catch (err) {
    console.error('Failed to load media:', err);
  } finally {
    loading.value = false;
  }
}

async function loadStats() {
  try {
    const res = await fetch('/media/list?per_page=1', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = await res.json();
    totalCount.value = json.payload?.stats?.total ?? json.payload?.media?.total ?? 0;
    totalSize.value = json.payload?.stats?.total_size ?? 0;
  } catch (err) {
    // stats are non-critical
  }
}

function clearFilters() {
  filters.value.search = '';
  filters.value.collection = null;
  loadMedia(1);
}

function goToPage(page) {
  if (page < 1 || page > pagination.value.lastPage) return;
  loadMedia(page);
}

/* ============================================================
   UPLOAD MODAL
============================================================ */
const uploadModalOpen = ref(false);
const uploading = ref(false);
const uploadError = ref('');
const uploadPreviewUrl = ref(null);
const fileInputRef = ref(null);

const uploadForm = ref({
  collection: 'site_asset',
  alt_text: '',
  file: null,
});

function openUploadModal() {
  uploadModalOpen.value = true;
  uploadError.value = '';
  uploadPreviewUrl.value = null;
  uploadForm.value = {
    collection: 'site_asset',
    alt_text: '',
    file: null,
  };
}

function closeUploadModal() {
  uploadModalOpen.value = false;
  uploadPreviewUrl.value = null;
}

function handleFileSelect(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  uploadForm.value.file = file;
  uploadError.value = '';

  // Generate preview
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (e) => { uploadPreviewUrl.value = e.target.result; };
    reader.readAsDataURL(file);
  } else {
    uploadPreviewUrl.value = null;
  }
}

async function submitUpload() {
  if (!uploadForm.value.file) return;

  uploading.value = true;
  uploadError.value = '';

  const formData = new FormData();
  formData.append('file', uploadForm.value.file);
  formData.append('collection', uploadForm.value.collection);
  if (uploadForm.value.alt_text) {
    formData.append('alt_text', uploadForm.value.alt_text);
  }

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
      if (errors) {
        uploadError.value = Object.values(errors).flat().join(' ');
      } else {
        uploadError.value = json.message || 'Upload failed.';
      }
      return;
    }

    closeUploadModal();
    loadMedia(1);
    loadStats();
  } catch (err) {
    uploadError.value = 'Network error. Please try again.';
  } finally {
    uploading.value = false;
  }
}

/* ============================================================
   DETAIL MODAL
============================================================ */
const detailItem = ref(null);
const detailAltText = ref('');
const detailFilename = ref('');

function openDetailModal(item) {
  detailItem.value = item;
  detailAltText.value = item.alt_text || '';
  detailFilename.value = item.original_filename || '';
}

function closeDetailModal() {
  detailItem.value = null;
}

const detailUrls = computed(() => {
  if (!detailItem.value) return {};

  const urls = { Original: detailItem.value.url };
  if (detailItem.value.medium_url) urls['Medium'] = detailItem.value.medium_url;
  if (detailItem.value.thumbnail_url) urls['Thumb'] = detailItem.value.thumbnail_url;

  return urls;
});

async function saveAltText() {
  if (!detailItem.value) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const rawFilename = String(detailFilename.value ?? '').trim();
  const existingFilename = String(detailItem.value.original_filename ?? '').trim();

  const existingExtMatch = existingFilename.match(/(\.[a-z0-9]{1,10})$/i);
  const existingExt = existingExtMatch ? existingExtMatch[1] : '';
  const rawHasExt = /\.[a-z0-9]{1,10}$/i.test(rawFilename);

  let nextFilename = rawFilename;
  if (nextFilename && !rawHasExt && existingExt) {
    const maxBase = Math.max(0, 255 - existingExt.length);
    nextFilename = `${nextFilename.slice(0, maxBase)}${existingExt}`;
  } else if (nextFilename.length > 255) {
    nextFilename = nextFilename.slice(0, 255);
  }

  try {
    const res = await fetch(`/media/${detailItem.value.id}`, {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: JSON.stringify({
        alt_text: detailAltText.value,
        ...(nextFilename ? { original_filename: nextFilename } : {}),
      }),
    });

    if (res.ok) {
      // Update in local list
      const idx = media.value.findIndex(m => m.id === detailItem.value.id);
      if (idx !== -1) {
        media.value[idx].alt_text = detailAltText.value;
        if (nextFilename) {
          media.value[idx].original_filename = nextFilename;
        }
      }

      detailItem.value.alt_text = detailAltText.value;
      if (nextFilename) {
        detailItem.value.original_filename = nextFilename;
      }

      closeDetailModal();
    }
  } catch (err) {
    console.error('Failed to save alt text:', err);
  }
}

async function deleteMedia(id) {
  if (!confirm('Delete this media file? This cannot be undone.')) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    await fetch(`/media/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
    });

    closeDetailModal();
    loadMedia(pagination.value.currentPage);
    loadStats();
  } catch (err) {
    console.error('Failed to delete media:', err);
  }
}

function copyToClipboard(text) {
  navigator.clipboard?.writeText(text);
}

/* ============================================================
   HELPERS
============================================================ */
const humanTotalSize = computed(() => {
  const bytes = totalSize.value;
  if (bytes >= 1_073_741_824) return (bytes / 1_073_741_824).toFixed(1) + ' GB';
  if (bytes >= 1_048_576) return (bytes / 1_048_576).toFixed(1) + ' MB';
  if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return bytes + ' B';
});

function formatDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

/* ============================================================
   KEYBOARD
============================================================ */
function handleKeydown(e) {
  if (e.key !== 'Escape') return;
  if (detailItem.value) { closeDetailModal(); return; }
  if (uploadModalOpen.value) { closeUploadModal(); return; }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  loadMedia(1);
  loadStats();
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<style scoped>
.hz-media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: var(--space-md);
}

.hz-media-thumb {
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-bg-hover);
  border-radius: var(--radius-sm);
  overflow: hidden;
  cursor: pointer;
  transition: border-color var(--motion-fast) var(--ease-smooth),
              box-shadow var(--motion-fast) var(--ease-smooth);
}

.hz-media-thumb:hover {
  border-color: var(--color-horizon-blue);
  box-shadow: 0 0 12px rgba(42, 120, 200, 0.2);
}

.hz-media-thumb img {
  width: 100%;
  height: 140px;
  object-fit: cover;
  display: block;
  background: var(--color-bg-base);
}

.hz-media-thumb-info {
  padding: var(--space-xs) var(--space-sm);
}
</style>
