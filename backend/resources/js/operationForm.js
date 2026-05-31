export function parseSquadronNames(rawValue) {
  return typeof rawValue === 'string' && rawValue.trim()
    ? rawValue.split(',').map(value => value.trim()).filter(Boolean)
    : []
}

export function buildTemplatePayload(form, selectedSquadronNames) {
  const trimmedSquadrons = parseSquadronNames(
    Array.isArray(selectedSquadronNames)
      ? selectedSquadronNames.join(', ')
      : selectedSquadronNames
  )

  return {
    roles: Array.isArray(form.roles) ? form.roles : [],
    title: form.title ?? '',
    gameplay_type: form.gameplay_type ?? '',
    description: form.description ?? '',
    extended_description: form.extended_description ?? '',
    visibility: form.visibility ?? 'open',
    squadron_name: trimmedSquadrons.length ? trimmedSquadrons.join(', ') : null,
    operation_type: form.operation_type ?? 'operation',
    branch: form.branch ?? '',
    operation_strictness: form.operation_strictness ?? '',
    start_location: form.start_location ?? '',
    operation_location: form.operation_location ?? '',
    slots: Array.isArray(form.roles)
      ? form.roles
          .map(role => typeof role?.role_display_name === 'string' ? role.role_display_name.trim() : '')
          .filter(Boolean)
      : Array.isArray(form.slots) ? form.slots : [],
  }
}

export function applyTemplatePayload(form, setSelectedSquadronNames, payload) {
  if (!payload || typeof payload !== 'object') return

  if ('title' in payload) form.title = payload.title ?? ''
  if ('gameplay_type' in payload) form.gameplay_type = payload.gameplay_type ?? ''
  else if ('type' in payload) form.gameplay_type = payload.type ?? ''
  if ('description' in payload) form.description = payload.description ?? ''
  if ('extended_description' in payload) form.extended_description = payload.extended_description ?? ''
  else if ('notes' in payload) form.extended_description = payload.notes ?? ''

  if ('visibility' in payload) form.visibility = payload.visibility ?? 'open'
  if ('operation_type' in payload) form.operation_type = payload.operation_type ?? 'operation'
  else if ('operation_kind' in payload) form.operation_type = payload.operation_kind ?? 'operation'
  if ('branch' in payload) form.branch = payload.branch ?? ''
  if ('operation_strictness' in payload) form.operation_strictness = payload.operation_strictness ?? ''
  if ('start_location' in payload) form.start_location = payload.start_location ?? ''
  if ('operation_location' in payload) form.operation_location = payload.operation_location ?? ''

  if ('roles' in payload) {
    form.roles = Array.isArray(payload.roles)
      ? payload.roles.map(role => ({
          id: role?.id ?? null,
          role_name: role?.role_name ?? '',
          role_display_name: role?.role_display_name ?? '',
          capacity: role?.capacity ?? '',
        }))
      : []
    form.slots = form.roles
      .map(role => role.role_display_name)
      .filter(Boolean)
  } else if ('slots' in payload) {
    form.slots = Array.isArray(payload.slots) ? [...payload.slots] : []
    form.roles = form.slots.map(slot => ({
      id: null,
      role_name: '',
      role_display_name: slot,
      capacity: '',
    }))
  }

  if ('squadron_name' in payload) {
    const raw = payload.squadron_name
    setSelectedSquadronNames(parseSquadronNames(raw))
    form.squadron_name = typeof raw === 'string' ? raw : ''
  }
}

export function splitUTC(iso) {
  if (!iso) return { date: '', time: '' }
  const clean = String(iso).replace('Z', '')
  return {
    date: clean.slice(0, 10),
    time: clean.slice(11, 16),
  }
}

export function buildDateTime(date, time) {
  if (!date || !time) return null
  const normalizedTime = String(time).length === 5 ? `${time}:00` : String(time)
  return `${date} ${normalizedTime}`
}

export function normalizeSquadronName(selectedSquadronNames) {
  const names = Array.isArray(selectedSquadronNames)
    ? selectedSquadronNames.map(value => (typeof value === 'string' ? value.trim() : '')).filter(Boolean)
    : parseSquadronNames(selectedSquadronNames)

  return names.length ? names.join(', ') : null
}

export function validateOperationSchedule({ startsAt, endsAt, rsvpDeadline }) {
  const dateTimePattern = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/

  if (!dateTimePattern.test(startsAt ?? '')) {
    return 'Start date and time are required.'
  }

  if (endsAt && !dateTimePattern.test(endsAt)) {
    return 'End time must include date and time.'
  }

  if (rsvpDeadline && !dateTimePattern.test(rsvpDeadline)) {
    return 'RSVP deadline must include date and time.'
  }

  return null
}

export function normalizeOperationSlots(slots) {
  if (!Array.isArray(slots)) {
    return {
      trimmedSlots: [],
      invalidIndexes: [],
    }
  }

  const trimmedSlots = slots.map(slot =>
    typeof slot === 'string' ? slot.trim() : slot
  )

  const invalidIndexes = trimmedSlots
    .map((slot, index) => ({ slot, index }))
    .filter(({ slot }) => typeof slot !== 'string' || slot.length === 0)
    .map(({ index }) => index + 1)

  return {
    trimmedSlots,
    invalidIndexes,
  }
}

export function normalizeOperationRoles(roles) {
  if (!Array.isArray(roles)) {
    return {
      normalizedRoles: [],
      invalidIndexes: [],
      invalidCapacityIndexes: [],
    }
  }

  const normalizedRoles = roles.map(role => {
    const displayName = typeof role?.role_display_name === 'string'
      ? role.role_display_name.trim()
      : ''

    const rawCapacity = role?.capacity
    const capacity = rawCapacity === '' || rawCapacity === null || typeof rawCapacity === 'undefined'
      ? null
      : Number(rawCapacity)

    return {
      id: role?.id ?? null,
      role_name: typeof role?.role_name === 'string' ? role.role_name.trim() : '',
      role_display_name: displayName,
      capacity,
    }
  })

  const invalidIndexes = normalizedRoles
    .map((role, index) => ({ role, index }))
    .filter(({ role }) => role.role_display_name.length === 0)
    .map(({ index }) => index + 1)

  const invalidCapacityIndexes = normalizedRoles
    .map((role, index) => ({ role, index }))
    .filter(({ role }) => role.capacity !== null && (!Number.isInteger(role.capacity) || role.capacity < 0))
    .map(({ index }) => index + 1)

  return {
    normalizedRoles,
    invalidIndexes,
    invalidCapacityIndexes,
  }
}

export function splitPickerValue(value) {
  if (!value) return { date: '', time: '' }
  const [date, timeRaw] = String(value).split('T')
  const time = (timeRaw ?? '').slice(0, 5)
  return { date: date ?? '', time }
}

export function computeRsvpPartsFromStart(startDate, startTime) {
  if (!startDate || !startTime) return null
  const [year, month, day] = String(startDate).split('-').map(Number)
  const [hour, minute] = String(startTime).split(':').map(Number)
  if (!year || !month || !day) return null
  if (!Number.isFinite(hour) || !Number.isFinite(minute)) return null

  const startUtc = new Date(Date.UTC(year, month - 1, day, hour, minute, 0))
  const rsvpUtc = new Date(startUtc.getTime() - 30 * 60 * 1000)
  const pad = (value) => String(value).padStart(2, '0')

  return {
    date: `${rsvpUtc.getUTCFullYear()}-${pad(rsvpUtc.getUTCMonth() + 1)}-${pad(rsvpUtc.getUTCDate())}`,
    time: `${pad(rsvpUtc.getUTCHours())}:${pad(rsvpUtc.getUTCMinutes())}`,
  }
}

export function resolveOperationIdFromResponse(response, fallbackId = null) {
  const directId = response?.props?.operation?.id ?? response?.operation?.id ?? null
  if (directId) return directId

  const flashId = response?.props?.flash?.operation?.id ?? fallbackId ?? null
  if (flashId) return flashId

  if (response?.url) {
    const match = String(response.url).match(/operations\/(\d+)/)
    if (match) return match[1]
  }

  return null
}
