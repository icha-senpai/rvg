<template>
  <div class="mx-auto max-w-5xl hz-stack">
    <MediaPanelHeader
      :total-count="totalCount"
      :human-total-size="humanTotalSize"
      @upload="openUploadModal"
    />

    <MediaPanelFilters
      :search="filters.search"
      :collection="filters.collection"
      :collection-options="collectionOptions"
      @update:search="updateSearchFilter"
      @update:collection="updateCollectionFilter"
      @search="loadMedia(1)"
      @clear="clearFilters"
    />

    <MediaPanelGrid
      :loading="loading"
      :media="media"
      :pagination="pagination"
      :collection-label="collectionLabel"
      @select="openDetailModal"
      @page="goToPage"
    />

    <MediaPanelUploadModal
      :open="uploadModalOpen"
      :uploading="uploading"
      :upload-error="uploadError"
      :upload-preview-url="uploadPreviewUrl"
      :upload-form="uploadForm"
      :upload-collection-options="uploadCollectionOptions"
      @close="closeUploadModal"
      @submit="submitUpload"
      @file-select="handleFileSelect"
      @update:collection="updateUploadCollection"
      @update:alt-text="updateUploadAltText"
    />

    <MediaPanelDetailModal
      :detail-item="detailItem"
      :detail-alt-text="detailAltText"
      :detail-filename="detailFilename"
      :detail-urls="detailUrls"
      :collection-label="collectionLabel"
      :uploader-name-color="uploaderNameColor"
      :format-date="formatDate"
      @close="closeDetailModal"
      @update:alt-text="updateDetailAltText"
      @update:filename="updateDetailFilename"
      @save="saveAltText"
      @delete="askDeleteMedia"
    />

    <HorizonConfirmDialog
      ref="deleteConfirmDialog"
      title="Delete Media"
      confirm-label="Delete"
      cancel-label="Cancel"
      variant="danger"
      message="Delete this media file? This cannot be undone."
      @confirm="confirmDeleteMedia"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue';
import MediaPanelDetailModal from '@/Pages/Admin/Partials/MediaPanelDetailModal.vue'
import MediaPanelFilters from '@/Pages/Admin/Partials/MediaPanelFilters.vue'
import MediaPanelGrid from '@/Pages/Admin/Partials/MediaPanelGrid.vue'
import MediaPanelHeader from '@/Pages/Admin/Partials/MediaPanelHeader.vue'
import MediaPanelUploadModal from '@/Pages/Admin/Partials/MediaPanelUploadModal.vue'
import { extractFirstErrorMessage, notifyErrorFromErrors } from '@/errors'

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

    if (!res.ok) {
      notifyErrorFromErrors(json?.errors ?? json?.message, 'Failed to load media.')
      return
    }

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
    notifyErrorFromErrors(null, 'Failed to load media.')
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

function updateSearchFilter(value) {
  filters.value.search = value;
}

function updateCollectionFilter(value) {
  filters.value.collection = value;
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

function updateUploadCollection(value) {
  uploadForm.value.collection = value;
}

function updateUploadAltText(value) {
  uploadForm.value.alt_text = value;
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
      uploadError.value = extractFirstErrorMessage(json.errors ?? json.message, 'Upload failed.');
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

function updateDetailAltText(value) {
  detailAltText.value = value;
}

function updateDetailFilename(value) {
  detailFilename.value = value;
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
      return;
    }

    notifyErrorFromErrors(null, 'Failed to save media details.');
  } catch (err) {
    notifyErrorFromErrors(null, 'Failed to save media details.');
  }
}

const deleteConfirmDialog = ref(null);
const pendingDeleteMediaId = ref(null);

function askDeleteMedia(id) {
  pendingDeleteMediaId.value = id;
  deleteConfirmDialog.value?.show();
}

async function confirmDeleteMedia({ close }) {
  const id = pendingDeleteMediaId.value;
  if (!id) {
    close();
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch(`/media/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
    });

    if (!res.ok) {
      notifyErrorFromErrors(null, 'Failed to delete media.');
      return;
    }

    close();
    pendingDeleteMediaId.value = null;
    closeDetailModal();
    loadMedia(pagination.value.currentPage);
    loadStats();
  } catch (err) {
    notifyErrorFromErrors(null, 'Failed to delete media.');
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








