<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Ziggy } from '../../../ziggy'

// Horizon Components
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
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
  normalizeOperationRoles,
  buildTemplatePayload as buildSharedTemplatePayload,
  computeRsvpPartsFromStart,
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

function initialRoleRows(mission) {
  if (Array.isArray(mission?.roles) && mission.roles.length) {
    return mission.roles.map((role) => ({
      id: role?.id ?? null,
      role_name: role?.role_name ?? '',
      role_display_name: role?.role_display_name ?? '',
      capacity: role?.capacity ?? '',
    }))
  }

  if (Array.isArray(mission?.slots) && mission.slots.length) {
    return mission.slots.map((slot) => ({
      id: null,
      role_name: '',
      role_display_name: slot ?? '',
      capacity: '',
    }))
  }

  return []
}

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

const renameTemplateConfirmDialog = ref(null)
const pendingRenameTemplate = ref(null)

function askRenameTemplate() {
  if (!selectedTemplate.value) return
  if (templatesUpdating.value || templatesDeleting.value) return

  pendingRenameTemplate.value = selectedTemplate.value
  renameTemplateConfirmDialog.value?.show()
}

function confirmRenameTemplate({ close, finish, text }) {
  const template = pendingRenameTemplate.value
  if (!template) {
    finish()
    return
  }

  const nextName = text?.trim()
  if (!nextName) {
    finish()
    return
  }

  templatesUpdating.value = true
  renameOperationTemplate({
    router,
    route,
    Ziggy,
    templateId: template.id,
    name: nextName,
    onSuccess: (visitPage) => {
      close()
      pendingRenameTemplate.value = null
      handleTemplateMutationSuccess(visitPage, 'template-updated', template?.id ?? null)
    },
    onError: (errors) => {
      finish()
      notifyErrorFromErrors(errors, 'Template name is invalid.')
    },
    onFinish: () => {
      templatesUpdating.value = false
    },
  })
}

const updateTemplateConfirmDialog = ref(null)
const pendingUpdateTemplate = ref(null)

function askUpdateTemplate() {
  if (!selectedTemplate.value) return
  if (templatesUpdating.value || templatesDeleting.value) return

  pendingUpdateTemplate.value = selectedTemplate.value
  updateTemplateConfirmDialog.value?.show()
}

function confirmUpdateTemplate({ close }) {
  const template = pendingUpdateTemplate.value
  if (!template) {
    close()
    return
  }

  templatesUpdating.value = true
  updateOperationTemplate({
    router,
    route,
    Ziggy,
    templateId: template.id,
    payload: buildSharedTemplatePayload(form, selectedSquadronNames.value),
    onSuccess: (visitPage) => {
      close()
      pendingUpdateTemplate.value = null
      handleTemplateMutationSuccess(visitPage, 'template-updated', template?.id ?? null)
    },
    onError: (errors) => {
      notifyErrorFromErrors(errors, 'Template data is invalid.')
    },
    onFinish: () => {
      templatesUpdating.value = false
    },
  })
}

const deleteTemplateConfirmDialog = ref(null)
const pendingDeleteTemplate = ref(null)

function askDeleteTemplate() {
  if (!selectedTemplate.value) return
  if (templatesDeleting.value || templatesUpdating.value) return

  pendingDeleteTemplate.value = selectedTemplate.value
  deleteTemplateConfirmDialog.value?.show()
}

function confirmDeleteTemplate({ close }) {
  const template = pendingDeleteTemplate.value
  if (!template) {
    close()
    return
  }

  templatesDeleting.value = true
  deleteOperationTemplate({
    router,
    route,
    Ziggy,
    templateId: template.id,
    onSuccess: () => {
      close()
      pendingDeleteTemplate.value = null
      selectedTemplateId.value = ''
      emit('template-deleted', { id: template.id })
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
const templateConsoleOpen = ref(false)

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

function toggleTemplateConsole() {
  templateConsoleOpen.value = !templateConsoleOpen.value
}

const saveTemplateConfirmDialog = ref(null)
const pendingSaveTemplateScope = ref(null)

function askSaveTemplate(scope) {
  if (templatesSaving.value) return

  pendingSaveTemplateScope.value = scope
  saveTemplateConfirmDialog.value?.show()
}

function confirmSaveTemplate({ close, finish, text }) {
  const scope = pendingSaveTemplateScope.value
  const name = text?.trim()

  if (!name) {
    finish()
    return
  }

  const squadronId = scope === 'squadron' ? props.squadronId : null

  templatesSaving.value = true
  createOperationTemplate({
    router,
    route,
    Ziggy,
    name,
    scope,
    squadronId,
    payload: buildSharedTemplatePayload(form, selectedSquadronNames.value),
    onSuccess: (visitPage) => {
      close()
      pendingSaveTemplateScope.value = null
      handleTemplateMutationSuccess(visitPage, 'template-saved')
    },
    onError: (errors) => {
      finish()
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
  roles: initialRoleRows(props.mission),
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

watch(
  () => selectedTemplateId.value,
  (value) => {
    if (value) {
      templateConsoleOpen.value = true
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

const signUpCloseSummary = computed(() => {
  if (!form.start_date || !form.start_time || !form.rsvp_date || !form.rsvp_time) {
    return 'Not set'
  }

  const startValue = Date.parse(`${form.start_date}T${form.start_time}:00Z`)
  const closeValue = Date.parse(`${form.rsvp_date}T${form.rsvp_time}:00Z`)

  if (Number.isNaN(startValue) || Number.isNaN(closeValue)) {
    return rsvpDateTime.value || 'Not set'
  }

  const diffMs = startValue - closeValue
  if (diffMs <= 0) {
    return 'At operation start'
  }

  const minutes = Math.round(diffMs / 60000)
  const noun = minutes === 1 ? 'minute' : 'minutes'

  return `${minutes} ${noun} before start`
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
  mediaPickerOpen.value = false
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
  form.roles.push({
    id: null,
    role_name: '',
    role_display_name: '',
    capacity: '',
  })
}

function removeSlot(index) {
  form.roles.splice(index, 1)
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

  if (Array.isArray(form.roles) && form.roles.length) {
    const {
      normalizedRoles,
      invalidIndexes,
      invalidCapacityIndexes,
    } = normalizeOperationRoles(form.roles)

    if (invalidIndexes.length) {
      openSlotWarning(
        `Role slots can’t be empty. Please fill or remove role(s): ${invalidIndexes.join(', ')}`
      )
      return
    }

    if (invalidCapacityIndexes.length) {
      openSlotWarning(
        `Role amounts must be whole numbers of 0 or more. Fix role(s): ${invalidCapacityIndexes.join(', ')}`
      )
      return
    }

    form.roles = normalizedRoles
    form.slots = normalizedRoles.map(role => role.role_display_name)
  } else {
    form.roles = []
    form.slots = []
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

const deleteOperationConfirmDialog = ref(null)

// ----------------------
// DELETE HANDLER
// ----------------------
function askDestroyOperation() {
  if (!props.mission) return
  deleteOperationConfirmDialog.value?.show()
}

function confirmDestroyOperation({ close, text }) {
  if (!props.mission) {
    close()
    return
  }

  router.delete(route('operations.destroy', props.mission.id, Ziggy), {
    data: {
      reason: text,
    },
    preserveScroll: true,
    onSuccess: () => {
      close()
      emit('deleted', props.mission.id)
    },
  })
}
</script>

<template>
  <HorizonContainer class="py-0">
    <div class="mx-auto max-w-5xl space-y-6">
      <!-- Editor command hero -->
      <section class="hz-surface-welcome relative z-30 overflow-visible rounded-[2rem] border border-white/[0.055] p-6">
        <div class="pointer-events-none absolute inset-0 opacity-20">
          <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
          <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-[0.28em] text-[color:var(--horizon-text-secondary)]">
              {{ isEdit ? 'Operation Edit Console' : 'Operation Creation Console' }}
            </div>

            <h1 class="mt-2 text-3xl font-black tracking-tight text-horizon-white md:text-5xl">
              {{ isEdit ? 'Edit Operation' : 'Create Operation' }}
            </h1>

            <p class="mt-3 max-w-3xl text-sm text-text-secondary md:text-base">
              Build the mission identity, schedule, briefing, media, role slots, and template payload from one command surface.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full border-transparent bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)] shadow-none">
                {{ isEdit ? 'Editing Existing Operation' : 'New Draft' }}
              </span>

              <span class="rounded-full border-transparent bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)] shadow-none">
                UTC Schedule Engine
              </span>

              <span
                v-if="selectedTemplate"
                class="rounded-full border-transparent bg-white/[0.042] px-3 py-1 text-xs font-semibold text-text-secondary shadow-none"
              >
                Template: {{ selectedTemplate.name }}
              </span>
            </div>
          </div>

          <div class="flex flex-col gap-2 lg:min-w-40">
            <HorizonButton
              variant="ghost"
              class="w-full"
              @click="embedded ? emit('cancel') : $inertia.visit(route('operations.index'))"
            >
              Cancel
            </HorizonButton>
          </div>
        </div>
      </section>

      <section class="hz-surface-welcome overflow-hidden rounded-[2rem] border border-white/[0.055] divide-y divide-white/[0.055]">
        <!-- Editor status strip -->
        <div class="grid overflow-hidden md:grid-cols-3">
          <div class="border-l border-white/[0.055] p-5 first:border-l-0">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Mode
          </div>

          <div class="mt-2 text-2xl font-black text-horizon-white">
            {{ isEdit ? 'Update' : 'Create' }}
          </div>

          <div class="mt-1 text-sm text-text-secondary">
            {{ isEdit ? 'Saving changes to an existing operation.' : 'Building a new operation draft.' }}
          </div>
          </div>

          <div class="border-l border-white/[0.055] p-5 first:border-l-0">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Sign-up Close Logic
          </div>

          <div class="mt-2 text-2xl font-black text-horizon-white">
            {{ rsvpAuto ? 'Auto' : 'Manual' }}
          </div>

          <div class="mt-1 text-sm text-text-secondary">
            Sign-ups close 30 minutes before start unless manually overridden.
          </div>
          </div>

          <div class="border-l border-white/[0.055] p-5 first:border-l-0">
          <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
            Templates
          </div>

          <div class="mt-2 text-2xl font-black text-horizon-white">
            {{ templates.length }}
          </div>

          <div class="mt-1 text-sm text-text-secondary">
            Available operation templates.
          </div>
          </div>
        </div>

        <!-- UTC warning -->
        <section class="p-5">
          <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Time Handling
            </div>

            <p class="mt-1 text-sm text-text-secondary">
              This editor uses UTC for saved operation calculations. Operation cards and dossiers convert the schedule to the viewer’s local time.
            </p>
          </div>

          <div class="shrink-0 rounded-full border-transparent bg-white/[0.042] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--horizon-text-primary)] shadow-none">
            UTC Source of Truth
          </div>
          </div>
        </section>

        <!-- Templates command section -->
        <section class="relative z-40 overflow-visible p-5">
          <button
            type="button"
            class="flex w-full flex-col gap-3 text-left lg:flex-row lg:items-start lg:justify-between"
            @click="toggleTemplateConsole"
          >
            <div class="min-w-0">
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Template Console
              </div>

              <h2 class="mt-1 text-xl font-black text-horizon-white">
                Operation Templates
              </h2>

              <p class="mt-1 text-sm text-text-secondary">
                Load reusable mission structures, update saved templates, or save this operation setup for later.
              </p>
            </div>

            <div class="flex items-center gap-3 self-start lg:self-auto">
              <div
                v-if="selectedTemplate"
                class="hz-surface-welcome rounded-2xl border border-white/[0.055] px-4 py-3"
              >
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Selected
                </div>

                <div class="mt-1 max-w-60 truncate text-sm font-semibold text-horizon-white">
                  {{ selectedTemplate.name }}
                </div>
              </div>

              <span class="flex h-10 w-10 items-center justify-center rounded-full border border-white/[0.055] bg-white/[0.03] text-lg font-bold text-horizon-white">
                {{ templateConsoleOpen ? '−' : '+' }}
              </span>
            </div>
          </button>

          <div v-if="templateConsoleOpen" class="mt-5 space-y-5 border-t border-white/[0.055] pt-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
            <div class="relative z-[9999] min-w-0">
              <HorizonSelect
                label="Load Template"
                v-model="selectedTemplateId"
                :options="templateOptions"
              />
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:min-w-[26rem]">
              <HorizonButton
                size="sm"
                variant="primary"
                class="w-full"
                :disabled="!selectedTemplateId"
                @click="applySelectedTemplate"
              >
                Apply
              </HorizonButton>

              <HorizonButton
                size="sm"
                variant="ghost"
                class="w-full"
                :disabled="!selectedTemplateId || templatesUpdating || templatesDeleting"
                @click="askRenameTemplate"
              >
                Rename
              </HorizonButton>

              <HorizonButton
                size="sm"
                variant="ghost"
                class="w-full"
                :disabled="!selectedTemplateId || templatesUpdating || templatesDeleting"
                @click="askUpdateTemplate"
              >
                Update
              </HorizonButton>

              <HorizonButton
                size="sm"
                variant="ghost"
                class="w-full"
                :disabled="!selectedTemplateId || templatesUpdating || templatesDeleting"
                @click="askDeleteTemplate"
              >
                Delete
              </HorizonButton>
            </div>
            </div>

            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <div class="mb-3">
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  Save Current Setup
                </div>

                <p class="mt-1 text-sm text-text-secondary">
                  Save the current form values as a reusable template scope.
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <HorizonButton
                  size="sm"
                  variant="ghost"
                  :disabled="templatesSaving"
                  @click="askSaveTemplate('personal')"
                >
                  Save Personal
                </HorizonButton>

                <HorizonButton
                  v-if="canSaveSquadronTemplate"
                  size="sm"
                  variant="ghost"
                  :disabled="templatesSaving"
                  @click="askSaveTemplate('squadron')"
                >
                  Save Squadron
                </HorizonButton>

                <HorizonButton
                  v-if="isDirectorLike"
                  size="sm"
                  variant="ghost"
                  :disabled="templatesSaving"
                  @click="askSaveTemplate('global')"
                >
                  Save Global
                </HorizonButton>
              </div>
            </div>
          </div>
        </section>

                <!-- Operation Details -->
        <section class="relative z-30 overflow-visible p-5">
          <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Operation Identity
              </div>

              <h2 class="mt-1 text-xl font-black text-horizon-white">
                Core Mission Details
              </h2>

              <p class="mt-1 text-sm text-text-secondary">
                Define what this operation is, who it belongs to, and how it should appear across Horizon.
              </p>
            </div>

            <div class="hz-surface-welcome rounded-2xl border border-white/[0.055] px-4 py-3">
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Current Status
              </div>

              <div class="mt-1 text-sm font-semibold text-horizon-white">
                {{ form.status ? form.status.replace(/[_-]+/g, ' ') : 'draft' }}
              </div>
            </div>
          </div>

          <div class="space-y-5">
            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <HorizonInput
                v-model="form.title"
                label="Title"
                placeholder="Operation name..."
              />

              <p
                v-if="form.errors.title"
                class="mt-2 text-sm text-red-300"
              >
                {{ form.errors.title }}
              </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
              <div class="hz-surface-welcome relative z-[9999] rounded-[1.5rem] border border-white/[0.055] p-4">
                <HorizonSelect
                  v-model="form.operation_type"
                  label="Operation Type"
                  :options="[
                    { label: 'Operation', value: 'operation' },
                    { label: 'Squadron Training', value: 'squadron_training' },
                    { label: 'Wing Training', value: 'wing_training' },
                    { label: 'Roleplay', value: 'roleplay' },
                    { label: 'Meeting', value: 'meeting' },
                    { label: 'Event', value: 'event' },
                  ]"
                />

                <p
                  v-if="form.errors.operation_type"
                  class="mt-2 text-sm text-red-300"
                >
                  {{ form.errors.operation_type }}
                </p>
              </div>

              <div class="hz-surface-welcome relative z-[9998] rounded-[1.5rem] border border-white/[0.055] p-4">
                <HorizonSelect
                  v-model="form.branch"
                  label="Branch"
                  :options="[
                    { label: 'No Branch / General', value: '' },
                    { label: 'Defence', value: 'defence' },
                    { label: 'Frontiers', value: 'frontiers' },
                    { label: 'Industries', value: 'industries' },
                    { label: 'Lifelines', value: 'lifelines' },
                  ]"
                />

                <p
                  v-if="form.errors.branch"
                  class="mt-2 text-sm text-red-300"
                >
                  {{ form.errors.branch }}
                </p>
              </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
              <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
                <HorizonInput
                  v-model="form.gameplay_type"
                  label="Gameplay Type"
                  placeholder="Mining, escort, salvage, recon, logistics..."
                />

                <p
                  v-if="form.errors.gameplay_type"
                  class="mt-2 text-sm text-red-300"
                >
                  {{ form.errors.gameplay_type }}
                </p>
              </div>

              <div class="hz-surface-welcome relative z-[9997] rounded-[1.5rem] border border-white/[0.055] p-4">
                <HorizonSelect
                  v-model="selectedSquadronNames"
                  label="Squadron Assignment"
                  :options="squadronOptions"
                  :multiple="true"
                />

                <p class="mt-2 text-xs text-text-muted">
                  Leave empty for a global operation, or select one or more squadrons.
                </p>

                <p
                  v-if="form.errors.squadron_name"
                  class="mt-2 text-sm text-red-300"
                >
                  {{ form.errors.squadron_name }}
                </p>
              </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
              <div class="hz-surface-welcome relative z-[9996] rounded-[1.5rem] border border-white/[0.055] p-4">
                <HorizonSelect
                  v-model="form.visibility"
                  label="Visibility"
                  :options="[
                    { label: 'Open', value: 'open' },
                    { label: 'Squadron Only', value: 'squadron_only' },
                    { label: 'Private', value: 'private' },
                  ]"
                />

                <p
                  v-if="form.errors.visibility"
                  class="mt-2 text-sm text-red-300"
                >
                  {{ form.errors.visibility }}
                </p>
              </div>

              <div class="hz-surface-welcome relative z-[9995] rounded-[1.5rem] border border-white/[0.055] p-4">
                <HorizonSelect
                  v-model="form.operation_strictness"
                  label="Comms Strictness"
                  :options="[
                    { label: 'Default', value: '' },
                    { label: 'Casual', value: 'casual' },
                    { label: 'Normal', value: 'normal' },
                    { label: 'Strict', value: 'strict' },
                    { label: 'Roleplay', value: 'roleplay' },
                  ]"
                />

                <p
                  v-if="form.errors.operation_strictness"
                  class="mt-2 text-sm text-red-300"
                >
                  {{ form.errors.operation_strictness }}
                </p>
              </div>

              </div>

            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                Identity Preview
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <span class="rounded-full border-transparent bg-white/[0.042] shadow-none px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ form.operation_type ? form.operation_type.replace(/[_-]+/g, ' ') : 'operation' }}
                </span>

                <span class="rounded-full border-transparent bg-white/[0.042] shadow-none px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ form.branch ? form.branch.replace(/[_-]+/g, ' ') : 'general' }}
                </span>

                <span class="rounded-full border-transparent bg-white/[0.042] shadow-none px-3 py-1 text-xs font-semibold text-text-secondary">
                  {{ form.visibility ? form.visibility.replace(/[_-]+/g, ' ') : 'open' }}
                </span>

                <span
                  v-if="form.gameplay_type"
                  class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]"
                >
                  {{ form.gameplay_type }}
                </span>
              </div>
            </div>
          </div>
        </section>

                <!-- Schedule -->
        <section class="relative z-20 overflow-visible p-5">
          <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Schedule Engine
              </div>

              <h2 class="mt-1 text-xl font-black text-horizon-white">
                Time Windows
              </h2>

              <p class="mt-1 text-sm text-text-secondary">
                Set the operation start, optional end window, and when sign-ups close. Saved values are calculated in UTC.
              </p>
            </div>

            <div
              class="rounded-2xl border px-4 py-3"
              :class="rsvpAuto
                ? 'border-emerald-300/25 bg-emerald-300/10'
                : 'border-white/[0.055] bg-white/[0.042]'"
            >
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Close Window Mode
              </div>

              <div class="mt-1 text-sm font-semibold text-horizon-white">
                {{ rsvpAuto ? 'Auto From Start' : 'Manual Override' }}
              </div>
            </div>
          </div>

          <div class="grid gap-4 lg:grid-cols-3">
            <div class="hz-surface-welcome relative z-[9999] rounded-[1.5rem] border border-white/[0.055] p-4">
              <HorizonDateTimePicker
                v-model="startDateTime"
                label="Start Time"
                :minute-options="quarterHourMinuteOptions"
              />

              <p class="mt-2 text-xs text-text-muted">
                Required. This is the main launch window.
              </p>

              <p
                v-if="form.errors.starts_at || form.errors.start_date || form.errors.start_time"
                class="mt-2 text-sm text-red-300"
              >
                {{ form.errors.starts_at || form.errors.start_date || form.errors.start_time }}
              </p>
            </div>

            <div class="hz-surface-welcome relative z-[9998] rounded-[1.5rem] border border-white/[0.055] p-4">
              <HorizonDateTimePicker
                v-model="endDateTime"
                label="End Time"
                :minute-options="quarterHourMinuteOptions"
              />

              <p class="mt-2 text-xs text-text-muted">
                Optional. Leave empty if the operation has no planned end.
              </p>

              <p
                v-if="form.errors.ends_at || form.errors.end_date || form.errors.end_time"
                class="mt-2 text-sm text-red-300"
              >
                {{ form.errors.ends_at || form.errors.end_date || form.errors.end_time }}
              </p>
            </div>

            <div class="hz-surface-welcome relative z-[9997] rounded-[1.5rem] border border-white/[0.055] p-4">
              <HorizonDateTimePicker
                v-model="rsvpDateTime"
                label="Sign-ups Close"
                :minute-options="quarterHourMinuteOptions"
              />

              <p class="mt-2 text-xs text-text-muted">
                Defaults to 30 minutes before start until manually changed.
              </p>

              <p
                v-if="form.errors.rsvp_deadline || form.errors.rsvp_date || form.errors.rsvp_time"
                class="mt-2 text-sm text-red-300"
              >
                {{ form.errors.rsvp_deadline || form.errors.rsvp_date || form.errors.rsvp_time }}
              </p>
            </div>
          </div>

          <div class="hz-surface-welcome mt-5 rounded-[1.5rem] border border-white/[0.055] p-4">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Schedule Notes
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-3">
              <div class="rounded-xl border border-white/10 bg-black/10 p-3">
                <div class="text-xs uppercase tracking-wide text-text-muted">
                  Start
                </div>
                <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                  {{ startDateTime || 'Not set' }}
                </div>
              </div>

              <div class="rounded-xl border border-white/10 bg-black/10 p-3">
                <div class="text-xs uppercase tracking-wide text-text-muted">
                  End
                </div>
                <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                  {{ endDateTime || 'Optional' }}
                </div>
              </div>

              <div class="rounded-xl border border-white/10 bg-black/10 p-3">
                <div class="text-xs uppercase tracking-wide text-text-muted">
                  Sign-ups Close
                </div>
                <div class="mt-1 truncate text-sm font-semibold text-horizon-white">
                  {{ signUpCloseSummary }}
                </div>
                <div class="mt-1 truncate text-xs text-text-secondary">
                  {{ rsvpDateTime || 'Not set' }}
                </div>
              </div>
            </div>
          </div>
        </section>
                <!-- Locations -->
        <section class="relative z-10 overflow-visible p-5">
          <div class="mb-5">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Deployment Geography
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              Start + Operation Locations
            </h2>

            <p class="mt-1 text-sm text-text-secondary">
              Define where members should gather and where the actual operation takes place.
            </p>
          </div>

          <div class="grid gap-4 lg:grid-cols-2">
            <div class="hz-surface-welcome relative z-[9996] rounded-[1.5rem] border border-white/[0.055] p-4">
              <HorizonSelect
                v-model="form.start_location"
                label="Start Location"
                :options="[
                  { label: 'No Start Location', value: '' },
                  ...startLocationOptions,
                ]"
              />

              <p class="mt-2 text-xs text-text-muted">
                Rally point, station, city, or gateway.
              </p>

              <p
                v-if="form.errors.start_location"
                class="mt-2 text-sm text-red-300"
              >
                {{ form.errors.start_location }}
              </p>
            </div>

            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <HorizonInput
                v-model="form.operation_location"
                label="Operation Location"
                placeholder="Target location, route, AO, moon, bunker, belt..."
              />

              <p class="mt-2 text-xs text-text-muted">
                The actual mission area or target zone.
              </p>

              <p
                v-if="form.errors.operation_location"
                class="mt-2 text-sm text-red-300"
              >
                {{ form.errors.operation_location }}
              </p>
            </div>
          </div>

          <div class="hz-surface-welcome mt-5 rounded-[1.5rem] border border-white/[0.055] p-4">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
              Location Preview
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2">
              <div class="rounded-xl border border-white/10 bg-black/10 p-3">
                <div class="text-xs uppercase tracking-wide text-text-muted">
                  Rally Point
                </div>

                <div class="mt-1 text-sm font-semibold text-horizon-white">
                  {{ form.start_location || 'Not set' }}
                </div>
              </div>

              <div class="rounded-xl border border-white/10 bg-black/10 p-3">
                <div class="text-xs uppercase tracking-wide text-text-muted">
                  Area of Operation
                </div>

                <div class="mt-1 text-sm font-semibold text-horizon-white">
                  {{ form.operation_location || 'Not set' }}
                </div>
              </div>
            </div>
          </div>
        </section>
                <!-- Briefings -->
        <section class="p-5">
          <div class="mb-5">
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
              Mission Writing
            </div>

            <h2 class="mt-1 text-xl font-black text-horizon-white">
              Briefing Text
            </h2>

            <p class="mt-1 text-sm text-text-secondary">
              Write the visible mission summary and optional extended briefing notes.
            </p>
          </div>

          <div class="grid gap-4 lg:grid-cols-2">
            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Public Briefing
              </label>

              <textarea
                v-model="form.description"
                rows="9"
                class="hz-input min-h-52 resize-y"
                placeholder="Short public-facing operation briefing..."
              ></textarea>

              <p class="mt-2 text-xs text-text-muted">
                This appears on cards and the operation dossier.
              </p>

              <p
                v-if="form.errors.description"
                class="mt-2 text-sm text-red-300"
              >
                {{ form.errors.description }}
              </p>
            </div>

            <div class="hz-surface-welcome rounded-[1.5rem] border border-white/[0.055] p-4">
              <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                Extended Briefing
              </label>

              <textarea
                v-model="form.extended_description"
                rows="9"
                class="hz-input min-h-52 resize-y"
                placeholder="Longer briefing, tactical notes, roleplay context, or extra instructions..."
              ></textarea>

              <p class="mt-2 text-xs text-text-muted">
                Optional deeper context for the full mission view.
              </p>

              <p
                v-if="form.errors.extended_description"
                class="mt-2 text-sm text-red-300"
              >
                {{ form.errors.extended_description }}
              </p>
            </div>
          </div>
        </section>
                <!-- Media -->
        <section class="p-5">
          <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Mission Visual
              </div>

              <h2 class="mt-1 text-xl font-black text-horizon-white">
                Operation Image
              </h2>

              <p class="mt-1 text-sm text-text-secondary">
                Attach an operation image from the media library for cards, modals, and dossiers.
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <HorizonButton
                v-if="canUploadOperationImages"
                size="sm"
                variant="primary"
                @click="openMediaPicker"
              >
                Choose Image
              </HorizonButton>

              <HorizonButton
                v-if="selectedMedia"
                size="sm"
                variant="ghost"
                @click="clearSelectedMedia"
              >
                Clear
              </HorizonButton>
            </div>
          </div>

          <div
            v-if="selectedMedia"
            class="hz-surface-welcome overflow-hidden rounded-[1.5rem] border border-white/[0.055]"
          >
            <img
              :src="selectedMedia.medium_url || selectedMedia.url"
              :alt="selectedMedia.alt_text || selectedMedia.name || 'Operation image'"
              class="max-h-96 w-full object-contain"
              loading="lazy"
            />

            <div class="hz-surface-welcome border-t border-white/[0.055] p-4">
              <div class="text-sm font-semibold text-horizon-white">
                {{ selectedMedia.name || 'Selected media' }}
              </div>

              <div class="mt-1 text-xs text-text-muted">
                Media ID: {{ selectedMedia.id }}
              </div>
            </div>
          </div>

          <div
            v-else
            class="hz-surface-welcome rounded-[1.5rem] border border-dashed border-white/15 p-8 text-center"
          >
            <div class="text-sm font-bold uppercase tracking-[0.22em] text-text-muted">
              No Image Selected
            </div>

            <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
              This operation will use text-only presentation until an image is selected.
            </p>

            <HorizonButton
              v-if="canUploadOperationImages"
              size="sm"
              variant="ghost"
              class="mt-4"
              @click="openMediaPicker"
            >
              Open Media Picker
            </HorizonButton>
          </div>

          <p
            v-if="form.errors.media_id"
            class="mt-2 text-sm text-red-300"
          >
            {{ form.errors.media_id }}
          </p>
        </section>

               <!-- Role Slots -->
        <section class="p-5">
          <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Deployment Roles
              </div>

              <h2 class="mt-1 text-xl font-black text-horizon-white">
                Role Slots
              </h2>

              <p class="mt-1 text-sm text-text-secondary">
                Define optional sign-up roles such as Pilot, Security, Medic, Scout, Cargo, or Lead.
              </p>
            </div>

            <HorizonButton
              size="sm"
              variant="primary"
              @click="addSlot"
            >
              Add Role
            </HorizonButton>
          </div>

          <div v-if="form.roles.length" class="space-y-3">
            <div
              v-for="(role, index) in form.roles"
              :key="index"
              class="hz-surface-welcome grid gap-3 rounded-[1.25rem] border border-white/[0.055] p-3 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-end"
            >
              <HorizonInput
                v-model="form.roles[index].role_display_name"
                :label="`Role Slot ${index + 1}`"
                placeholder="Pilot, Medic, Gunner, Salvage Lead..."
              />

              <HorizonInput
                v-model="form.roles[index].capacity"
                :label="`Spots`"
                type="number"
                min="0"
                placeholder="Unlimited"
              />

              <HorizonButton
                size="sm"
                variant="ghost"
                class="md:mb-0.5"
                @click="removeSlot(index)"
              >
                Remove
              </HorizonButton>
            </div>
          </div>

          <div
            v-else
            class="hz-surface-welcome rounded-[1.5rem] border border-dashed border-white/15 p-6 text-center"
          >
            <div class="text-sm font-bold uppercase tracking-[0.22em] text-text-muted">
              No Role Slots
            </div>

            <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
              Members can still join with “No Role.” Add slots if this operation needs structured assignments.
            </p>
          </div>

          <p
            v-if="form.errors.slots || form.errors.roles"
            class="mt-2 text-sm text-red-300"
          >
            {{ form.errors.roles ?? form.errors.slots }}
          </p>
        </section>


               <!-- Form Actions -->
        <section class="static z-30 bg-[rgba(11,13,20,0.92)] p-4 shadow-[0_-12px_48px_rgba(0,0,0,0.35)] backdrop-blur-sm md:sticky md:bottom-0 md:p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                Finalize Operation
              </div>

              <p class="mt-1 text-sm text-text-secondary">
                Save as draft, publish to the operation board, or cancel editing.
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <HorizonButton
                variant="ghost"
                :disabled="form.processing"
                @click="embedded ? emit('cancel') : $inertia.visit(route('operations.index'))"
              >
                Cancel
              </HorizonButton>

              <HorizonButton
                v-if="isEdit"
                variant="danger"
                :disabled="form.processing"
                @click="askDestroyOperation"
              >
                Cancel Operation
              </HorizonButton>

              <HorizonButton
                variant="ghost"
                :disabled="form.processing"
                @click="submit('draft')"
              >
                {{ form.processing ? 'Saving…' : 'Save Draft' }}
              </HorizonButton>

              <HorizonButton
                variant="primary"
                :disabled="form.processing"
                @click="submit('published')"
              >
                {{ form.processing ? 'Publishing…' : (isEdit ? 'Save / Publish' : 'Publish Operation') }}
              </HorizonButton>
            </div>
          </div>
        </section>
      </section>
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
  <MediaPickerModal
    :open="mediaPickerOpen"
    collection="operation_image"
    title="Choose Operation Image"
    :allow-upload="canUploadOperationImages"
    @close="mediaPickerOpen = false"
    @selected="handleMediaSelected"
  />
  <HorizonConfirmDialog
    ref="updateTemplateConfirmDialog"
    title="Overwrite Template"
    confirm-label="Overwrite"
    cancel-label="Cancel"
    variant="warning"
    message="Overwrite this template with the current form values?"
    @confirm="confirmUpdateTemplate"
  />

  <HorizonConfirmDialog
    ref="deleteTemplateConfirmDialog"
    title="Delete Template"
    confirm-label="Delete"
    cancel-label="Cancel"
    variant="danger"
    message="Delete this template? This cannot be undone."
    @confirm="confirmDeleteTemplate"
  />

  <HorizonConfirmDialog
    ref="deleteOperationConfirmDialog"
    title="Cancel Operation"
    confirm-label="Cancel Operation"
    cancel-label="Cancel"
    variant="danger"
    message="Cancel this operation? This cannot be undone."
    :close-on-confirm="false"
    :requires-text-input="true"
    text-input-label="Cancellation reason:"
    text-input-placeholder="Enter reason..."
    @confirm="confirmDestroyOperation"
  />

  <HorizonConfirmDialog
    ref="renameTemplateConfirmDialog"
    title="Rename Template"
    confirm-label="Rename"
    cancel-label="Cancel"
    variant="default"
    message="Enter a new name for this template."
    :requires-text-input="true"
    text-input-label="New template name"
    text-input-placeholder="Enter name..."
    :close-on-confirm="false"
    @confirm="confirmRenameTemplate"
  />

  <HorizonConfirmDialog
    ref="saveTemplateConfirmDialog"
    title="Save Template"
    confirm-label="Save"
    cancel-label="Cancel"
    variant="success"
    message="Enter a name for this new template."
    :requires-text-input="true"
    text-input-label="Template name"
    text-input-placeholder="Enter name..."
    :close-on-confirm="false"
    @confirm="confirmSaveTemplate"
  />
</template>





