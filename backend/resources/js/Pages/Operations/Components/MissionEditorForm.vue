<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../../../ziggy'

// Horizon Components
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSection from '@/Components/HorizonSection.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import HorizonDateTimePicker from '@/Components/HorizonDateTimePicker.vue'
import MediaPickerModal from '@/Components/MediaPickerModal.vue'

// Shared auth and error helpers
import {
  canSaveSquadronTemplate as userCanSaveSquadronTemplate,
  canUploadOperationImages as userCanUploadOperationImages,
  isDirectorLike as userIsDirectorLike,
} from '@/auth'
import { notifyError, notifyErrorFromErrors } from '@/errors'
import {
  applyTemplatePayload as applySharedTemplatePayload,
  buildDateTime,
  buildTemplatePayload as buildSharedTemplatePayload,
  computeRsvpPartsFromStart,
  normalizeOperationSlots,
  normalizeSquadronName,
  parseSquadronNames,
  resolveOperationIdFromResponse,
  splitPickerValue,
  splitUTC,
  validateOperationSchedule,
} from '@/operationForm'
import {
  createOperationTemplate,
  deleteOperationTemplate,
  renameOperationTemplate,
  resolveOperationTemplateVisit,
  updateOperationTemplate,
} from '@/operationTemplateCrud'

// ----------------------
// EMITS
// ----------------------
const emit = defineEmits([
  'saved',
  'deleted',
  'cancel',
  'template-saved',
  'template-updated',
  'template-deleted',
])

// ----------------------
// PROPS
// ----------------------
const props = defineProps({
  squadronId: { type: Number, required: false, default: null },
  mission: { type: Object, default: null },
  embedded: { type: Boolean, default: false },
  prefillTemplateId: { type: [Number, String], default: null },
})

const page = usePage()

const squadrons = computed(() => page.props?.squadrons ?? [])
const templates = computed(() => page.props?.operationTemplates ?? [])
const operationFlash = computed(() => page.props?.flash?.operation ?? null)
const operationTemplateFlash = computed(() => page.props?.flash?.operationTemplate ?? null)

const selectedSquadronNames = ref(parseSquadronNames(props.mission?.squadron_name))

watch(
  () => props.mission?.squadron_name,
  (next) => {
    selectedSquadronNames.value = parseSquadronNames(next)

    form.squadron_name = typeof next === 'string' ? next : ''
  }
)

async function renameSelectedTemplate() {
  if (!selectedTemplate.value) return
  if (templatesUpdating.value || templatesDeleting.value) return

  const nextName = prompt('New template name:', selectedTemplate.value?.name ?? '')
  if (!nextName || !String(nextName).trim()) return

  templatesUpdating.value = true
  renameOperationTemplate({
    router,
    route,
    Ziggy,
    templateId: selectedTemplate.value.id,
    name: String(nextName).trim(),
    onSuccess: (visitPage) => {
      handleTemplateMutationSuccess(visitPage, 'template-updated', selectedTemplate.value?.id ?? null)
    },
    onError: (errors) => {
      notifyErrorFromErrors(errors, 'Template name is invalid.')
    },
    onFinish: () => {
      templatesUpdating.value = false
    },
  })
}

async function updateSelectedTemplate() {
  if (!selectedTemplate.value) return
  if (templatesUpdating.value || templatesDeleting.value) return

  const ok = confirm(`Overwrite template "${selectedTemplate.value?.name ?? ''}" with the current form values?`)
  if (!ok) return

  templatesUpdating.value = true
  updateOperationTemplate({
    router,
    route,
    Ziggy,
    templateId: selectedTemplate.value.id,
    payload: buildSharedTemplatePayload(form, selectedSquadronNames.value),
    onSuccess: (visitPage) => {
      handleTemplateMutationSuccess(visitPage, 'template-updated', selectedTemplate.value?.id ?? null)
    },
    onError: (errors) => {
      notifyErrorFromErrors(errors, 'Template data is invalid.')
    },
    onFinish: () => {
      templatesUpdating.value = false
    },
  })
}

async function deleteSelectedTemplate() {
  if (!selectedTemplate.value) return
  if (templatesDeleting.value || templatesUpdating.value) return

  const ok = confirm(`Delete template "${selectedTemplate.value?.name ?? ''}"? This cannot be undone.`)
  if (!ok) return

  templatesDeleting.value = true
  deleteOperationTemplate({
    router,
    route,
    Ziggy,
    templateId: selectedTemplate.value.id,
    onSuccess: () => {
      selectedTemplateId.value = ''
      emit('template-deleted', { id: selectedTemplate.value.id })
    },
    onError: (errors) => {
      notifyErrorFromErrors(errors, 'Failed to delete template.')
    },
    onFinish: () => {
      templatesDeleting.value = false
    },
  })
}

const squadronOptions = computed(() => {
  return (squadrons.value ?? []).map(s => ({
    label: s.name,
    value: s.name,
  }))
})

const startLocationOptions = [
  { label: 'Stanton / Hurston / Lorville', value: 'Stanton / Hurston / Lorville' },
  { label: 'Stanton / Hurston / Everus Harbor', value: 'Stanton / Hurston / Everus Harbor' },
  { label: 'Stanton / Hurston / HUR-L1 Green Glade Station', value: 'Stanton / Hurston / HUR-L1 Green Glade Station' },
  { label: 'Stanton / Hurston / HUR-L2 Faithful Dream Station', value: 'Stanton / Hurston / HUR-L2 Faithful Dream Station' },
  { label: 'Stanton / Hurston / HUR-L3 Thundering Express Station', value: 'Stanton / Hurston / HUR-L3 Thundering Express Station' },
  { label: 'Stanton / Hurston / HUR-L4 Melodic Fields Station', value: 'Stanton / Hurston / HUR-L4 Melodic Fields Station' },
  { label: 'Stanton / Hurston / HUR-L5 High Course Station', value: 'Stanton / Hurston / HUR-L5 High Course Station' },
  { label: 'Stanton / Arccorp / Area 18', value: 'Stanton / Arccorp / Area 18' },
  { label: 'Stanton / Arccorp / Baijini Point', value: 'Stanton / Arccorp / Baijini Point' },
  { label: 'Stanton / Arccorp / ARC-L1 Wide Forest Station', value: 'Stanton / Arccorp / ARC-L1 Wide Forest Station' },
  { label: 'Stanton / Arccorp / ARC-L2 Lively Pathway Station', value: 'Stanton / Arccorp / ARC-L2 Lively Pathway Station' },
  { label: 'Stanton / Arccorp / ARC-L3 Modern Express Station', value: 'Stanton / Arccorp / ARC-L3 Modern Express Station' },
  { label: 'Stanton / Arccorp / ARC-L4 Faint Glen Station', value: 'Stanton / Arccorp / ARC-L4 Faint Glen Station' },
  { label: 'Stanton / Arccorp / ARC-L5 Yellow Core Station', value: 'Stanton / Arccorp / ARC-L5 Yellow Core Station' },
  { label: 'Stanton / MicroTech / New Babbage', value: 'Stanton / MicroTech / New Babbage' },
  { label: 'Stanton / MicroTech / Port Tressler', value: 'Stanton / MicroTech / Port Tressler' },
  { label: 'Stanton / MicroTech / MIC-L1 Shallow Frontier Station', value: 'Stanton / MicroTech / MIC-L1 Shallow Frontier Station' },
  { label: 'Stanton / MicroTech / MIC-L2 Long Forest Station', value: 'Stanton / MicroTech / MIC-L2 Long Forest Station' },
  { label: 'Stanton / MicroTech / MIC-L3 Endless Odyssey Station', value: 'Stanton / MicroTech / MIC-L3 Endless Odyssey Station' },
  { label: 'Stanton / MicroTech / MIC-L4 Red Crossroads Station', value: 'Stanton / MicroTech / MIC-L4 Red Crossroads Station' },
  { label: 'Stanton / MicroTech / MIC-L5 Wide Expanse Station', value: 'Stanton / MicroTech / MIC-L5 Wide Expanse Station' },
  { label: 'Stanton / Crusader / Orison', value: 'Stanton / Crusader / Orison' },
  { label: 'Stanton / Crusader / Seraphim Station', value: 'Stanton / Crusader / Seraphim Station' },
  { label: 'Stanton / Crusader / CRU-L1 Ambitious Dream Station', value: 'Stanton / Crusader / CRU-L1 Ambitious Dream Station' },
  { label: 'Stanton / Crusader / CRU-L2 Faithful Dawn Station', value: 'Stanton / Crusader / CRU-L2 Faithful Dawn Station' },
  { label: 'Stanton / Crusader / CRU-L3 Wildwood Station', value: 'Stanton / Crusader / CRU-L3 Wildwood Station' },
  { label: 'Stanton / Crusader / CRU-L4 Shallow Fields Station', value: 'Stanton / Crusader / CRU-L4 Shallow Fields Station' },
  { label: 'Stanton / Crusader / CRU-L5 Beautiful Glen Station', value: 'Stanton / Crusader / CRU-L5 Beautiful Glen Station' },
  { label: 'Stanton / Crusader / Grim HEX', value: 'Stanton / Crusader / Grim HEX' },
  { label: 'Stanton / Gateway / Nyx', value: 'Stanton / Gateway / Nyx' },
  { label: 'Stanton / Gateway / Pyro', value: 'Stanton / Gateway / Pyro' },
  { label: 'Stanton / Gateway / Terra', value: 'Stanton / Gateway / Terra' },
  { label: 'Pyro / Pyro I / Ruin Station', value: 'Pyro / Pyro I / Ruin Station' },
  { label: 'Pyro / Pyro IV / Checkmate Station', value: 'Pyro / Pyro IV / Checkmate Station' },
  { label: 'Pyro / Pyro V / Orbituary Station', value: 'Pyro / Pyro V / Orbituary Station' },
  { label: 'Pyro / Pyro VI / Rats Nest', value: 'Pyro / Pyro VI / Rats Nest' },
  { label: 'Pyro / Gateway / Stanton ', value: 'Pyro / Gateway / Stanton' },
  { label: 'Pyro / Gateway / Nyx ', value: 'Pyro / Gateway / Nyx' },
  { label: 'Nyx / Delamar / Levski', value: 'Nyx / Delamar / Levski' },
  { label: 'Nyx / Gateway / Stanton', value: 'Nyx / Gateway / Stanton' },
  { label: 'Nyx / Gateway / Pyro', value: 'Nyx / Gateway / Pyro' },
]

const authUser = computed(() => page.props.auth?.user ?? null)

const isDirectorLike = computed(() => {
  return userIsDirectorLike(authUser.value)
})

const canUploadOperationImages = computed(() => {
  return userCanUploadOperationImages(authUser.value)
})

const canSaveSquadronTemplate = computed(() => {
  return userCanSaveSquadronTemplate(authUser.value, props.squadronId)
})

const templatesSaving = ref(false)
const templatesUpdating = ref(false)
const templatesDeleting = ref(false)
const selectedTemplateId = ref('')
const prefillAppliedId = ref(null)

const selectedTemplate = computed(() => {
  if (!selectedTemplateId.value) return null
  return (templates.value ?? []).find(t => Number(t?.id) === Number(selectedTemplateId.value)) ?? null
})

const templateOptions = computed(() => {
  return (templates.value ?? []).map(t => {
    const scopeLabel = t.scope === 'personal'
      ? 'Personal'
      : t.scope === 'squadron'
        ? 'Squadron'
        : 'Global'

    return {
      label: `${scopeLabel}: ${t.name}`,
      value: t.id,
    }
  })
})

function applyTemplateById(id) {
  const template = (templates.value ?? []).find(t => Number(t?.id) === Number(id))
  if (!template) return
  applyTemplatePayload(template.payload)
}

function applySelectedTemplate() {
  if (!selectedTemplateId.value) return
  applyTemplateById(selectedTemplateId.value)
}

async function saveTemplate(scope) {
  if (templatesSaving.value) return
  const name = prompt('Template name:')
  if (!name || !String(name).trim()) return

  const squadronId = scope === 'squadron' ? props.squadronId : null

  templatesSaving.value = true
  createOperationTemplate({
    router,
    route,
    Ziggy,
    name: String(name).trim(),
    scope,
    squadronId,
    payload: buildSharedTemplatePayload(form, selectedSquadronNames.value),
    onSuccess: (visitPage) => {
      handleTemplateMutationSuccess(visitPage, 'template-saved')
    },
    onError: (errors) => {
      notifyErrorFromErrors(errors, 'Template data is invalid. Please check fields and try again.')
    },
    onFinish: () => {
      templatesSaving.value = false
    },
  })
}

function applyTemplatePayload(payload) {
  applySharedTemplatePayload(form, (names) => {
    selectedSquadronNames.value = names
  }, payload)
}

function handleTemplateMutationSuccess(visitPage, eventName, fallbackTemplateId = null) {
  const { templateId, template } = resolveOperationTemplateVisit(visitPage, fallbackTemplateId)

  if (templateId) {
    selectedTemplateId.value = templateId
  }

  if (template && eventName) {
    emit(eventName, template)
  }
}

// ----------------------
// MODE
// ----------------------
const isEdit = computed(() => props.mission !== null)

const slotWarningOpen = ref(false)
const slotWarningMessage = ref('')

function openSlotWarning(message) {
  slotWarningMessage.value = message
  slotWarningOpen.value = true
}

function closeSlotWarning() {
  slotWarningOpen.value = false
  slotWarningMessage.value = ''
}

// ----------------------
// FORM
// ----------------------
const start = splitUTC(props.mission?.starts_at)
const end = splitUTC(props.mission?.ends_at)
const rsvp = splitUTC(props.mission?.rsvp_deadline)

const form = useForm({
  title: props.mission?.title ?? '',
  operation_type: props.mission?.operation_type ?? props.mission?.operation_kind ?? 'operation',
  branch: props.mission?.branch ?? '',
  gameplay_type: props.mission?.gameplay_type ?? props.mission?.type ?? '',

  start_location: props.mission?.start_location ?? '',
  operation_location: props.mission?.operation_location ?? '',

  start_date: start.date,
  start_time: start.time,
  end_date: end.date,
  end_time: end.time,
  rsvp_date: rsvp.date,
  rsvp_time: rsvp.time,

  starts_at: buildDateTime(start.date, start.time),
  ends_at: buildDateTime(end.date, end.time),
  rsvp_deadline: buildDateTime(rsvp.date, rsvp.time),

  description: props.mission?.description ?? '',
  extended_description: props.mission?.extended_description ?? props.mission?.notes ?? '',
  visibility: props.mission?.visibility ?? 'open',
  difficulty: props.mission?.difficulty ?? '',
  operation_strictness: props.mission?.operation_strictness ?? '',
  icon: props.mission?.icon ?? '',
  image_url: props.mission?.image_url ?? '',
  slots: props.mission?.slots ?? [],
  status: props.mission?.status ?? 'draft',
  squadron_id: props.squadronId,
  squadron_name: props.mission?.squadron_name ?? '',
  media_id: props.mission?.media_image?.id ?? null,
})

watch(
  () => props.prefillTemplateId,
  (id) => {
    if (!id) return
    if (prefillAppliedId.value === id) return

    const numericId = Number(id)
    if (!Number.isFinite(numericId)) return

    selectedTemplateId.value = numericId
    applyTemplateById(numericId)
    prefillAppliedId.value = id
  }
)

watch(
  () => templates.value,
  () => {
    if (!props.prefillTemplateId || prefillAppliedId.value === props.prefillTemplateId) return

    const numericId = Number(props.prefillTemplateId)
    if (!Number.isFinite(numericId)) return

    selectedTemplateId.value = numericId
    applyTemplateById(numericId)
    prefillAppliedId.value = props.prefillTemplateId
  },
  { immediate: true }
)

watch(
  () => operationTemplateFlash.value,
  (flash) => {
    if (!flash?.id) return

    if (flash.event === 'deleted' && Number(selectedTemplateId.value) === Number(flash.id)) {
      selectedTemplateId.value = ''
      return
    }

    if (flash.event === 'created' || flash.event === 'updated') {
      selectedTemplateId.value = flash.id
    }
  }
)

const quarterHourMinuteOptions = [0, 15, 30, 45]

const rsvpAuto = ref(false)
let rsvpAutoApplying = false

const hasRsvpValue = !!(form.rsvp_date && form.rsvp_time)
const initialComputedRsvp = computeRsvpPartsFromStart(form.start_date, form.start_time)

if (!hasRsvpValue) {
  rsvpAuto.value = true
} else if (initialComputedRsvp) {
  rsvpAuto.value = form.rsvp_date === initialComputedRsvp.date && form.rsvp_time === initialComputedRsvp.time
}

watch(
  () => [form.start_date, form.start_time],
  () => {
    if (!rsvpAuto.value) return
    const computed = computeRsvpPartsFromStart(form.start_date, form.start_time)
    if (!computed) return

    rsvpAutoApplying = true
    form.rsvp_date = computed.date
    form.rsvp_time = computed.time
    rsvpAutoApplying = false
  },
  { immediate: true }
)

watch(
  () => [form.rsvp_date, form.rsvp_time],
  () => {
    if (rsvpAutoApplying) return
    const computed = computeRsvpPartsFromStart(form.start_date, form.start_time)
    if (!computed) {
      rsvpAuto.value = false
      return
    }

    rsvpAuto.value = form.rsvp_date === computed.date && form.rsvp_time === computed.time
  }
)

const startDateTime = computed({
  get() {
    if (!form.start_date || !form.start_time) return ''
    return `${form.start_date}T${form.start_time}`
  },
  set(value) {
    const parts = splitPickerValue(value)
    form.start_date = parts.date
    form.start_time = parts.time
  },
})

const endDateTime = computed({
  get() {
    if (!form.end_date || !form.end_time) return ''
    return `${form.end_date}T${form.end_time}`
  },
  set(value) {
    const parts = splitPickerValue(value)
    form.end_date = parts.date
    form.end_time = parts.time
  },
})

const rsvpDateTime = computed({
  get() {
    if (!form.rsvp_date || !form.rsvp_time) return ''
    return `${form.rsvp_date}T${form.rsvp_time}`
  },
  set(value) {
    const parts = splitPickerValue(value)
    form.rsvp_date = parts.date
    form.rsvp_time = parts.time
  },
})

// ----------------------
// MEDIA PICKER
// ----------------------
const mediaPickerOpen = ref(false)
const selectedMedia = ref(props.mission?.media_image ?? null)

function openMediaPicker() {
  mediaPickerOpen.value = true
}

function handleMediaSelected(media) {
  selectedMedia.value = media
  form.media_id = media.id
}

function clearSelectedMedia() {
  selectedMedia.value = null
  form.media_id = null
}

watch(
  () => props.mission?.media_image,
  (next) => {
    selectedMedia.value = next ?? null
    form.media_id = next?.id ?? null
  }
)

// ----------------------
// SLOT HANDLERS
// ----------------------
function addSlot() {
  form.slots.push('')
}

function removeSlot(index) {
  form.slots.splice(index, 1)
}

// ----------------------
// SUBMIT HANDLER
// ----------------------
async function submit(mode) {
  form.squadron_name = normalizeSquadronName(selectedSquadronNames.value)

  const currentStatus = props.mission?.status ?? 'draft'
  const isPublishing = mode === 'published'
  const shouldPublishTransition = isPublishing && (!isEdit.value || currentStatus === 'draft')

  if (!isEdit.value) {
    form.status = isPublishing ? 'published' : 'draft'
  } else {
    form.status = currentStatus
  }

  form.starts_at = buildDateTime(form.start_date, form.start_time)
  form.ends_at = buildDateTime(form.end_date, form.end_time)
  form.rsvp_deadline = buildDateTime(form.rsvp_date, form.rsvp_time)

  const scheduleValidationMessage = validateOperationSchedule({
    startsAt: form.starts_at,
    endsAt: form.ends_at,
    rsvpDeadline: form.rsvp_deadline,
  })

  if (scheduleValidationMessage) {
    notifyError({ message: scheduleValidationMessage })
    return
  }

  if (Array.isArray(form.slots) && form.slots.length) {
    const { trimmedSlots, invalidIndexes } = normalizeOperationSlots(form.slots)

    if (invalidIndexes.length) {
      openSlotWarning(
        `Role slots can’t be empty. Please fill or remove role(s): ${invalidIndexes.join(', ')}`
      )
      return
    }

    form.slots = trimmedSlots
  }

  if (props.embedded) {
    form.processing = true
    form.clearErrors()

    const onEmbeddedError = (errors) => {
      form.setError(errors)
      notifyErrorFromErrors(errors)
    }

    if (isEdit.value) {
      router.put(
        route('operations.update', props.mission.id, Ziggy),
        {
          ...form.data(),
          stay_on_page: true,
        },
        {
          preserveScroll: true,
          preserveState: true,
          only: ['operations', 'activeOperation', 'editingOperation', 'flash'],
          onSuccess: () => {
            if (shouldPublishTransition && currentStatus === 'draft') {
              router.post(
                route('operations.publish', props.mission.id, Ziggy),
                {
                  stay_on_page: true,
                },
                {
                  preserveScroll: true,
                  preserveState: true,
                  only: ['operations', 'activeOperation', 'editingOperation', 'flash'],
                  onSuccess: () => {
                    emit('saved', {
                      id: props.mission.id,
                      mode: 'edit',
                    })
                  },
                  onError: onEmbeddedError,
                  onFinish: () => {
                    form.processing = false
                  },
                }
              )

              return
            }

            emit('saved', {
              id: props.mission.id,
              mode: 'edit',
            })
          },
          onError: onEmbeddedError,
          onFinish: () => {
            if (!(shouldPublishTransition && currentStatus === 'draft')) {
              form.processing = false
            }
          },
        }
      )

      return
    }

    const storeUrl = props.squadronId
      ? route('operations.store', { squadron: props.squadronId }, Ziggy)
      : route('operations.storeGlobal', {}, Ziggy)

    router.post(
      storeUrl,
      {
        ...form.data(),
        stay_on_page: true,
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['operations', 'activeOperation', 'editingOperation', 'flash'],
        onSuccess: (visitPage) => {
          const newId = resolveOperationIdFromResponse(visitPage, operationFlash.value?.id ?? null)

          if (!newId) {
            notifyErrorFromErrors(null, 'Operation was created, but the editor could not resolve its ID.')
            return
          }

          emit('saved', {
            id: newId,
            mode: 'create',
          })
        },
        onError: onEmbeddedError,
        onFinish: () => {
          form.processing = false
        },
      }
    )

    return
  }

  // ----------------------
  // EDIT MODE
  // ----------------------
  if (isEdit.value) {
    return form.put(
      route('operations.update', props.mission.id, Ziggy),
      {
        preserveScroll: true,
        onSuccess() {
          if (shouldPublishTransition && currentStatus === 'draft') {
            router.post(route('operations.publish', props.mission.id, Ziggy), {}, {
              preserveScroll: true,
            })
            return
          }

          emit('saved', {
            id: props.mission.id,
            mode: 'edit',
          })
        },
        onError: (errors) => {
          notifyErrorFromErrors(errors, 'Failed to update operation.')
        },
      }
    )
  }

  // ----------------------
  // CREATE MODE
  // ----------------------
  try {
    const storeUrl = props.squadronId
      ? route('operations.store', { squadron: props.squadronId }, Ziggy)
      : route('operations.storeGlobal', {}, Ziggy)

    const response = await form.post(storeUrl, {
      preserveScroll: true,
      onError: (errors) => {
        notifyErrorFromErrors(errors, 'Failed to create operation.')
      },
    })

    const newId = resolveOperationIdFromResponse(response, operationFlash.value?.id ?? null)

    if (!newId) {
      notifyErrorFromErrors(null, 'Operation was created, but the editor could not resolve its ID.')
      return
    }

    emit('saved', {
      id: newId,
      mode: 'create',
    })

  } catch (err) {
    notifyErrorFromErrors(null, 'Failed to create operation.')
  }
}

// ----------------------
// DELETE HANDLER
// ----------------------
async function destroyOperation() {
  if (!props.mission) return
  if (!confirm('Delete this operation? This cannot be undone.')) return

  await form.delete(
    route('operations.destroy', props.mission.id, Ziggy),
    {
      preserveScroll: true,
      onSuccess: () => {
        emit('deleted', props.mission.id)
      },
    }
  )
}
</script>

<template>
  <HorizonContainer class="space-y-10">

    <!-- Header -->
    <div class="mx-auto max-w-5xl flex items-center justify-between mb-4">
      <div class="hz-stack-sm">
        <div class="hz-section-label">
          {{ isEdit ? 'Update Operation' : 'New Operation' }}
        </div>

        <h1 class="hz-title-lg text-horizon-white">
          {{ isEdit ? 'Edit Operation' : 'Create Operation' }}
        </h1>
      </div>

      <HorizonButton
        variant="ghost"
        @click="embedded ? emit('cancel') : $inertia.visit(route('operations.index'))"
      >
        Cancel
      </HorizonButton>
    </div>

    <!-- Main Layout -->
    <div class="mx-auto max-w-5xl space-y-10">
      <div class="text-sm text-horizon-offwhite mt-2 opacity-80">
        Uses UTC for all calculations.<br />
        Automatically converts to your local timezone in the operation card.
      </div>
      <!-- LEFT SIDE -->
      <div class="space-y-6">

        <HorizonSection title="Templates">
          <div class="hz-stack">
            <HorizonSelect
              label="Load Template"
              v-model="selectedTemplateId"
              :options="templateOptions"
            />

            <div class="flex flex-wrap gap-3">
              <HorizonButton
                size="sm"
                variant="ghost"
                :disabled="!selectedTemplateId"
                @click="applySelectedTemplate"
              >
                Apply
              </HorizonButton>

              <HorizonButton
                size="sm"
                variant="ghost"
                :disabled="!selectedTemplateId || templatesUpdating || templatesDeleting"
                @click="renameSelectedTemplate"
              >
                Rename
              </HorizonButton>

              <HorizonButton
                size="sm"
                variant="ghost"
                :disabled="!selectedTemplateId || templatesUpdating || templatesDeleting"
                @click="updateSelectedTemplate"
              >
                Update
              </HorizonButton>

              <HorizonButton
                size="sm"
                variant="ghost"
                :disabled="!selectedTemplateId || templatesUpdating || templatesDeleting"
                @click="deleteSelectedTemplate"
              >
                Delete
              </HorizonButton>

              <HorizonButton
                size="sm"
                variant="ghost"
                :disabled="templatesSaving"
                @click="saveTemplate('personal')"
              >
                Save Personal
              </HorizonButton>

              <HorizonButton
                v-if="canSaveSquadronTemplate"
                size="sm"
                variant="ghost"
                :disabled="templatesSaving"
                @click="saveTemplate('squadron')"
              >
                Save Squadron
              </HorizonButton>

              <HorizonButton
                v-if="isDirectorLike"
                size="sm"
                variant="ghost"
                :disabled="templatesSaving"
                @click="saveTemplate('global')"
              >
                Save Global
              </HorizonButton>
            </div>
          </div>
        </HorizonSection>

        <!-- Operation Details -->
        <HorizonSection title="Operation Details">
          <div class="hz-stack">
            <HorizonInput
              v-model="form.title"
              label="Title"
              placeholder="Convoy Escort - Stanton Corridor"
            />

            <HorizonInput
              label="Gameplay Type"
              placeholder="Escort / Recon / Patrol / Meeting / Other"
              v-model="form.gameplay_type"
            />
          </div>
        </HorizonSection>

        <!-- Timing -->
        <HorizonSection title="Scheduling">
          <div class="grid md:grid-cols-2 gap-4">
            <HorizonDateTimePicker
              label="Start (UTC)"
              v-model="startDateTime"
              :minute-options="quarterHourMinuteOptions"
            />

            <HorizonDateTimePicker
              label="End (UTC) (optional)"
              v-model="endDateTime"
              :clearable="true"
              :minute-options="quarterHourMinuteOptions"
            />

            <HorizonDateTimePicker
              label="Sign Up Deadline (UTC) (optional)"
              v-model="rsvpDateTime"
              :clearable="true"
              :minute-options="quarterHourMinuteOptions"
            />
          </div>
        </HorizonSection>

        <!-- Description -->
        <HorizonSection title="Operation Briefing">
          <textarea
            v-model="form.description"
            rows="6"
            class="hz-textarea w-full"
            maxlength="510"
            placeholder="Operation overview (max 510 characters). Be Creative! It's for Discord."
          ></textarea>
        </HorizonSection>

        <!-- Notes -->
        <HorizonSection title="Operation Extended Briefing">
          <textarea
            v-model="form.extended_description"
            rows="6"
            class="hz-textarea w-full"
            maxlength="5000"
            placeholder="Expanded detail (max 5000 characters). Write your heart out!"
          ></textarea>
        </HorizonSection>

      </div>


     
      <div class="space-y-6">

        <!-- Meta -->
        <HorizonSection title="Meta Information">
          <div class="hz-stack">

            <HorizonSelect
              label="Visibility"
              v-model="form.visibility"
              :options="[
                { label: 'Open', value: 'open' },
                { label: 'Squadron Only', value: 'squadron' },
              ]"
            />

            <HorizonSelect
              label="Squadrons"
              v-model="selectedSquadronNames"
              :options="squadronOptions"
              :multiple="true"
            />

            <HorizonSelect
              label="Operation Type"
              v-model="form.operation_type"
              :options="[
                { label: 'Operation', value: 'operation' },
                { label: 'Squadron Training', value: 'squadron_training' },
                { label: 'Wing Training', value: 'wing_training' },
                { label: 'Roleplay', value: 'roleplay' },
                { label: 'Meeting', value: 'meeting' },
                { label: 'Event', value: 'event' },
              ]"
            />

            <HorizonSelect
              label="Branch"
              v-model="form.branch"
              :options="[
                { label: 'None', value: '' },
                { label: 'Industries', value: 'industries' },
                { label: 'Defence', value: 'defence' },
                { label: 'Frontiers', value: 'frontiers' },
                { label: 'Lifelines', value: 'lifelines' },
              ]"
            />

            <HorizonSelect
              label="Comms Strictness"
              v-model="form.operation_strictness"
              :options="[
                { label: 'Default', value: '' },
                { label: 'Casual', value: 'casual' },
                { label: 'Normal', value: 'normal' },
                { label: 'Strict', value: 'strict' },
                { label: 'Roleplay', value: 'roleplay' }
              ]"
            />

            <HorizonSelect
              label="Start Location"
              v-model="form.start_location"
              :options="startLocationOptions"
            />

            <HorizonInput
              label="Operation Location"
              v-model="form.operation_location"
              placeholder="e.g. Hurston / MicroTech / etc."
            />



          </div>
        </HorizonSection>

        <!-- Media -->
        <HorizonSection title="Operation Image">
          <div class="hz-stack">

            <!-- SELECTED IMAGE PREVIEW -->
            <div v-if="selectedMedia" class="hz-stack-sm">
              <div
                style="border-radius: var(--radius-sm); overflow: hidden; background: var(--color-bg-elevated);"
              >
                <img
                  :src="selectedMedia.medium_url || selectedMedia.url"
                  :alt="selectedMedia.alt_text || selectedMedia.original_filename"
                  style="width: 100%; max-height: 240px; object-fit: cover;"
                />
              </div>

              <div class="hz-row-between">
                <div class="hz-text-muted" style="font-size: var(--text-tiny); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  {{ selectedMedia.original_filename }}
                </div>

                <div class="hz-row">
                  <HorizonButton size="xs" variant="ghost" @click="openMediaPicker">
                    Change
                  </HorizonButton>
                  <HorizonButton size="xs" variant="ghost" @click="clearSelectedMedia">
                    Remove
                  </HorizonButton>
                </div>
              </div>
            </div>

            <!-- NO IMAGE SELECTED -->
            <div v-else>
              <HorizonButton variant="ghost" @click="openMediaPicker">
                Select Image
              </HorizonButton>
              <div class="hz-text-muted" style="font-size: var(--text-tiny); margin-top: var(--space-xs);">
                Browse the media library or upload a new image.
              </div>
            </div>
          </div>
        </HorizonSection>

        <!-- Media Picker Modal -->
        <MediaPickerModal
          :open="mediaPickerOpen"
          collection="operation_image"
          :allowUpload="canUploadOperationImages"
          title="Select Operation Image"
          @close="mediaPickerOpen = false"
          @selected="handleMediaSelected"
        />

        <!-- Roles -->
        <HorizonSection title="Roles">
          <div class="flex justify-between items-center mb-3">
            <div class="hz-section-label">Roles</div>

            <HorizonButton size="sm" variant="ghost" @click="addSlot">
              Add Role
            </HorizonButton>
          </div>

          <div v-if="form.slots.length" class="space-y-3">
            <div
              v-for="(slot, index) in form.slots"
              :key="index"
              class="flex items-center gap-3"
            >
              <HorizonInput
                v-model="form.slots[index]"
                placeholder="Escort / Medic / Lead"
              />

              <HorizonButton
                size="xs"
                variant="outline"
                @click="removeSlot(index)"
              >
                ✕
              </HorizonButton>
            </div>
          </div>

          <p v-else class="hz-caption hz-text-muted">
            No roles defined. Operation allows freeform participation.
          </p>
        </HorizonSection>


        <!-- ACTION BUTTONS -->
        <div class="flex gap-4 pt-4">
          <HorizonButton
            variant="primary"
            class="flex-1"
            :disabled="form.processing"
            @click="submit('published')"
          >
            {{ isEdit ? 'Save Changes' : 'Publish Operation' }}
          </HorizonButton>

          <HorizonButton
            variant="outline"
            class="flex-1"
            :disabled="form.processing"
            @click="submit('draft')"
          >
            Save as Draft
          </HorizonButton>
        </div>

      </div>
    </div>

    <div
      v-if="slotWarningOpen"
      class="hz-overlay flex items-center justify-center"
      @click.self="closeSlotWarning"
    >
      <div class="hz-modal hz-stack max-h-[85vh] overflow-y-auto hz-animate-pop">
        <div class="hz-row-between">
          <div class="hz-title-lg">Missing Role</div>
          <HorizonButton variant="primary" size="sm" @click="closeSlotWarning">
            ✕
          </HorizonButton>
        </div>

        <div class="hz-text-soft">{{ slotWarningMessage }}</div>

        <div class="hz-row-between pt-2">
          <div></div>
          <HorizonButton variant="primary" size="sm" @click="closeSlotWarning">
            OK
          </HorizonButton>
        </div>
      </div>
    </div>

  </HorizonContainer>
</template>
