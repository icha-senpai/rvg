<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
  >
    <div class="w-full max-w-xl hz-overlay-light rounded-2xl p-6 space-y-4">

      <div class="flex items-center justify-between">
        <div class="hz-title-lg">
          {{ isEdit ? `Edit Squadron · ${form.name}` : "Create Squadron" }}
        </div>
        <button @click="close" class="hz-caption hover:text-horizon-white">✕</button>
      </div>

      <!-- FORM -->
      <div class="grid grid-cols-1 gap-4">

        <div>
          <label class="hz-caption mb-1 block">Name</label>
          <input v-model="form.name" class="hz-input w-full" />
        </div>

        <div>
          <label class="hz-caption mb-1 block">Slug</label>
          <input v-model="form.slug" class="hz-input w-full" />
        </div>

        <div>
          <label class="hz-caption mb-1 block">Status</label>
          <select v-model="form.status" class="hz-input w-full">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="disbanded">Disbanded</option>
          </select>
        </div>

        <!-- LEADER SELECT -->
        <div>
          <label class="hz-caption mb-1 block">Leader</label>
          <select v-model="form.leader_id" class="hz-input w-full">
            <option value="">None</option>
            <option v-for="u in users" :key="u.id" :value="u.id">
              {{ u.discord_name }} (ID {{ u.id }})
            </option>
          </select>
        </div>

        <!-- EMBLEM UPLOAD -->
        <div>
          <label class="hz-caption mb-1 block">Emblem</label>

          <input type="file" accept="image/*" @change="handleEmblemUpload" class="hz-input w-full" />

          <div v-if="previewEmblem" class="mt-3">
            <div class="hz-caption mb-1">Preview:</div>
            <img :src="previewEmblem" class="w-32 h-32 object-cover rounded-xl border border-horizon-muted" />
          </div>

          <HorizonButton
            v-if="emblemFile"
            size="sm"
            variant="primary"
            class="mt-2"
            @click="uploadEmblem"
          >
            Upload Emblem
          </HorizonButton>
        </div>

      </div>

      <!-- FOOTER -->
      <div class="flex justify-between mt-4">

        <HorizonButton
          v-if="isEdit"
          variant="danger"
          size="sm"
          @click="deleteSquadron"
        >
          Delete Squadron
        </HorizonButton>

        <div class="flex gap-3">
          <HorizonButton variant="ghost" size="sm" @click="close">Cancel</HorizonButton>
          <HorizonButton variant="primary" size="sm" @click="save">
            {{ isEdit ? "Save" : "Create" }}
          </HorizonButton>
        </div>

      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import HorizonButton from "@/Components/HorizonButton.vue";

const props = defineProps({
  show: Boolean,
  users: Array,
  squadron: Object,
});

const emit = defineEmits(["close"]);

const isEdit = ref(!!props.squadron?.id);

const form = ref({
  id: props.squadron?.id || null,
  name: props.squadron?.name || "",
  slug: props.squadron?.slug || "",
  status: props.squadron?.status || "active",
  leader_id: props.squadron?.leader_id || "",
});

/* EMBLEM UPLOAD */
const previewEmblem = ref(null);
let emblemFile = null;

function handleEmblemUpload(e) {
  emblemFile = e.target.files[0];
  previewEmblem.value = URL.createObjectURL(emblemFile);
}

function uploadEmblem() {
  if (!emblemFile) return;

  const fd = new FormData();
  fd.append("emblem", emblemFile);

  router.post(route("squadrons.emblem.upload", form.value.id), fd, {
    forceFormData: true,
    onSuccess: () => {
      emblemFile = null;
      previewEmblem.value = null;
    },
  });
}

function save() {
  const routeName = isEdit.value
    ? "admin.squadrons.update"
    : "admin.squadrons.store";

  router.post(route(routeName), form.value, {
    onSuccess: () => emit("close"),
  });
}

function deleteSquadron() {
  router.post(route("admin.squadrons.delete"), { id: form.value.id }, {
    onSuccess: () => emit("close"),
  });
}

function close() {
  emit("close");
}
</script>
