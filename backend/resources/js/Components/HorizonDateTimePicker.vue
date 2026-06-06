<template>
  <div ref="container" class="hz-stack-xs">
    <label v-if="label" class="hz-section-label">{{ label }}</label>

    <button
      type="button"
      class="hz-input w-full cursor-pointer text-left flex items-center justify-between gap-3"
      @click="togglePicker"
    >
      <span class="truncate">{{ displayValue }}</span>
      <span class="text-xs text-[var(--color-text-secondary)] shrink-0">Edit</span>
    </button>

    <div
      v-if="pickerOpen"
      class="hz-popover-surface mt-2 w-full overflow-visible rounded-xl"
    >
      <div class="hz-shell-header flex items-center justify-between gap-2 border-b px-3 py-3">
        <button
          type="button"
          class="hz-surface-soft hz-shell-hover rounded-lg px-2 py-1"
          @click="goPrevMonth"
        >
          ‹
        </button>

        <div class="text-sm font-semibold text-horizon-white">
          {{ monthLabel }} {{ pickerYear }}
        </div>

        <button
          type="button"
          class="hz-surface-soft hz-shell-hover rounded-lg px-2 py-1"
          @click="goNextMonth"
        >
          ›
        </button>
      </div>

      <div class="px-3 py-3">
        <div class="grid grid-cols-7 gap-1 text-[10px] text-[var(--color-text-secondary)] sm:text-[11px]">
          <div v-for="w in weekdayLabels" :key="w" class="text-center py-1">{{ w }}</div>
        </div>

        <div class="grid grid-cols-7 gap-1">
          <button
            v-for="cell in calendarCells"
            :key="cell.key"
            type="button"
            class="h-10 rounded-lg text-sm sm:h-9"
            :class="[
              cell.isBlank
                ? 'opacity-0 pointer-events-none'
                : (cell.isSelected
                ? 'bg-[color:var(--horizon-sunset-blue)] text-horizon-white border border-[color:var(--horizon-sunset-blue)] font-semibold'
                    : 'text-[var(--color-text-primary)] hover:bg-[color:var(--color-hover-frost)]'),
              (!cell.isBlank && cell.isTodayHighlight && !cell.isSelected)
                ? 'w-10 justify-self-center rounded-full border border-[color:var(--horizon-sunset-blue)] text-[color:var(--horizon-sunset-blue)] font-semibold hover:bg-transparent sm:w-9'
                : '',
            ]"
            @click="!cell.isBlank && selectDay(cell.day)"
          >
            {{ cell.day }}
          </button>
        </div>
      </div>

      <div class="hz-shell-header border-t px-3 py-3">
        <div class="hz-stack-sm">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-2">
            <div class="hz-stack-xs w-20">
              <div class="text-[11px] text-[var(--color-text-secondary)]">Hour</div>
              <button
                type="button"
                ref="hourButton"
                class="hz-input flex w-20 items-center justify-between px-3 py-2 text-left"
                @click="toggleHourMenu"
              >
                <span>{{ String(hour).padStart(2, '0') }}</span>
                <span class="text-[10px] text-[var(--color-text-secondary)]">▾</span>
              </button>

              <div
                v-if="hourMenuOpen"
                ref="hourMenu"
                class="hz-popover-surface fixed z-50 max-h-40 overflow-y-auto rounded-lg p-1"
                :style="hourMenuStyle"
              >
                <button
                  v-for="h in 24"
                  :key="h"
                  type="button"
                  class="w-full px-2 py-1 rounded-md text-sm text-left"
                  :class="(hour === (h - 1))
                    ? 'bg-[color:var(--color-panel-active)] text-horizon-white border border-[color:var(--color-surface-border)]'
                    : 'text-[var(--color-text-primary)] hover:bg-[color:var(--color-hover-frost)]'"
                  @click="selectHour(h - 1)"
                >
                  {{ String(h - 1).padStart(2, '0') }}
                </button>
              </div>
            </div>

            <div class="hz-stack-xs w-24">
              <div class="text-[11px] text-[var(--color-text-secondary)]">Minute</div>
              <button
                type="button"
                ref="minuteButton"
                class="hz-input flex w-24 items-center justify-between px-3 py-2 text-left"
                @click="toggleMinuteMenu"
              >
                <span>{{ String(minute).padStart(2, '0') }}</span>
                <span class="text-[10px] text-[var(--color-text-secondary)]">▾</span>
              </button>

              <div
                v-if="minuteMenuOpen"
                ref="minuteMenu"
                class="hz-popover-surface fixed z-50 max-h-40 overflow-y-auto rounded-lg p-1"
                :style="minuteMenuStyle"
              >
                <button
                  v-for="m in minuteChoices"
                  :key="m"
                  type="button"
                  class="w-full px-2 py-1 rounded-md text-sm text-left"
                  :class="(minute === m)
                    ? 'bg-[color:var(--color-panel-active)] text-horizon-white border border-[color:var(--color-surface-border)]'
                    : 'text-[var(--color-text-primary)] hover:bg-[color:var(--color-hover-frost)]'"
                  @click="selectMinute(m)"
                >
                  {{ String(m).padStart(2, '0') }}
                </button>
              </div>
            </div>

            <div v-if="showNow" class="sm:ml-auto">
              <button
                type="button"
                class="hz-surface-soft hz-shell-hover w-full rounded-lg px-3 py-2 text-sm sm:w-auto"
                @click="setNow"
              >
                Now
              </button>
            </div>
          </div>

          <div v-if="clearable" class="flex items-center justify-end gap-2 flex-wrap">
            <button
              type="button"
              class="hz-surface-soft hz-shell-hover rounded-lg px-3 py-2 text-sm"
              @click="clear"
            >
              Clear
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  label: { type: String, default: '' },
  clearable: { type: Boolean, default: false },
  showNow: { type: Boolean, default: false },
  minuteOptions: { type: Array, default: null },
})

const model = defineModel({ type: String, default: '' })

const container = ref(null)

const pickerOpen = ref(false)
const pickerYear = ref(new Date().getFullYear())
const pickerMonthIndex = ref(new Date().getMonth())

const hourMenuOpen = ref(false)
const minuteMenuOpen = ref(false)
const hourButton = ref(null)
const minuteButton = ref(null)
const hourMenu = ref(null)
const minuteMenu = ref(null)
const hourMenuStyle = ref({})
const minuteMenuStyle = ref({})

const weekdayLabels = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']
const monthLabels = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
]

const monthLabel = computed(() => monthLabels[pickerMonthIndex.value] ?? '')

function parseLocalDatetime(value) {
  if (!value) return null
  const [datePart, timePartRaw] = String(value).split('T')
  if (!datePart) return null

  const [year, month, day] = datePart.split('-').map(Number)
  if (!year || !month || !day) return null

  const timePart = timePartRaw || '00:00'
  const [hour, minute] = timePart.split(':').map(Number)

  return {
    year,
    month,
    day,
    hour: Number.isFinite(hour) ? hour : 0,
    minute: Number.isFinite(minute) ? minute : 0,
  }
}

function buildLocalDatetime({ year, month, day, hour, minute }) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${year}-${pad(month)}-${pad(day)}T${pad(hour)}:${pad(minute)}`
}

function ensureParts() {
  const parsed = parseLocalDatetime(model.value)
  if (parsed) return parsed

  const now = new Date()
  return {
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate(),
    hour: 0,
    minute: 0,
  }
}

const hour = computed({
  get() {
    return ensureParts().hour
  },
  set(next) {
    const parts = ensureParts()
    parts.hour = Number(next)
    model.value = buildLocalDatetime(parts)
  },
})

const minute = computed({
  get() {
    return ensureParts().minute
  },
  set(next) {
    const parts = ensureParts()
    parts.minute = Number(next)
    model.value = buildLocalDatetime(parts)
  },
})

const minuteChoices = computed(() => {
  const raw = Array.isArray(props.minuteOptions) && props.minuteOptions.length
    ? props.minuteOptions
    : Array.from({ length: 60 }, (_, i) => i)

  const normalized = raw
    .map(n => Number(n))
    .filter(n => Number.isFinite(n) && n >= 0 && n < 60)

  const unique = Array.from(new Set(normalized)).sort((a, b) => a - b)

  const current = minute.value
  if (Number.isFinite(current) && current >= 0 && current < 60 && !unique.includes(current)) {
    unique.push(current)
    unique.sort((a, b) => a - b)
  }

  return unique
})

const displayValue = computed(() => {
  if (!model.value) return 'Not set'
  return String(model.value).replace('T', ' ')
})

function openPicker() {
  pickerOpen.value = true
  const parts = parseLocalDatetime(model.value)
  if (parts) {
    pickerYear.value = parts.year
    pickerMonthIndex.value = parts.month - 1
    return
  }

  const now = new Date()
  pickerYear.value = now.getFullYear()
  pickerMonthIndex.value = now.getMonth()
}

function closePicker() {
  hourMenuOpen.value = false
  minuteMenuOpen.value = false
  pickerOpen.value = false
}

function togglePicker() {
  if (pickerOpen.value) {
    closePicker()
    return
  }

  openPicker()
}

function goPrevMonth() {
  if (pickerMonthIndex.value === 0) {
    pickerMonthIndex.value = 11
    pickerYear.value -= 1
    return
  }

  pickerMonthIndex.value -= 1
}

function goNextMonth() {
  if (pickerMonthIndex.value === 11) {
    pickerMonthIndex.value = 0
    pickerYear.value += 1
    return
  }

  pickerMonthIndex.value += 1
}

function selectDay(day) {
  const parts = ensureParts()
  parts.year = pickerYear.value
  parts.month = pickerMonthIndex.value + 1
  parts.day = Number(day)
  model.value = buildLocalDatetime(parts)
}

function setNow() {
  const now = new Date()
  model.value = buildLocalDatetime({
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate(),
    hour: now.getHours(),
    minute: now.getMinutes(),
  })
  pickerYear.value = now.getFullYear()
  pickerMonthIndex.value = now.getMonth()
}

function clear() {
  model.value = ''
}

function computeFixedMenuStyle(triggerEl) {
  if (!triggerEl) return {}

  const rect = triggerEl.getBoundingClientRect()
  const menuHeight = 160
  const margin = 10

  const openUp = rect.bottom + menuHeight + margin > window.innerHeight
  const top = openUp ? rect.top - menuHeight - 6 : rect.bottom + 6

  const width = rect.width
  let left = rect.left

  if (left + width > window.innerWidth - margin) {
    left = window.innerWidth - margin - width
  }

  if (left < margin) left = margin

  return {
    top: `${Math.max(margin, top)}px`,
    left: `${left}px`,
    width: `${width}px`,
  }
}

function toggleHourMenu() {
  minuteMenuOpen.value = false

  if (hourMenuOpen.value) {
    hourMenuOpen.value = false
    return
  }

  hourMenuStyle.value = computeFixedMenuStyle(hourButton.value)
  hourMenuOpen.value = true
}

function toggleMinuteMenu() {
  hourMenuOpen.value = false

  if (minuteMenuOpen.value) {
    minuteMenuOpen.value = false
    return
  }

  minuteMenuStyle.value = computeFixedMenuStyle(minuteButton.value)
  minuteMenuOpen.value = true
}

function selectHour(value) {
  hour.value = Number(value)
  hourMenuOpen.value = false
}

function selectMinute(value) {
  minute.value = Number(value)
  minuteMenuOpen.value = false
}

const calendarCells = computed(() => {
  const year = pickerYear.value
  const monthIndex = pickerMonthIndex.value

  const first = new Date(year, monthIndex, 1)
  const startWeekday = (first.getDay() + 6) % 7
  const daysInMonth = new Date(year, monthIndex + 1, 0).getDate()

  const selected = parseLocalDatetime(model.value)
  const shouldHighlightToday = !selected

  const today = new Date()
  const todayYear = today.getFullYear()
  const todayMonth = today.getMonth() + 1
  const todayDay = today.getDate()

  const cells = []
  const total = 42

  for (let i = 0; i < total; i++) {
    const day = i - startWeekday + 1
    const isBlank = day < 1 || day > daysInMonth
    const isSelected =
      !isBlank &&
      !!selected &&
      selected.year === year &&
      selected.month === monthIndex + 1 &&
      selected.day === day

    const isToday =
      !isBlank &&
      year === todayYear &&
      monthIndex + 1 === todayMonth &&
      day === todayDay

    const isTodayHighlight = isToday && shouldHighlightToday

    cells.push({
      key: `${year}-${monthIndex}-${i}`,
      day: isBlank ? '' : day,
      isBlank,
      isSelected,
      isToday,
      isTodayHighlight,
    })
  }

  return cells
})

function handleClickOutside(event) {
  if (!pickerOpen.value) return
  const el = container.value
  if (!el) return
  if (el.contains(event.target)) return
  closePicker()
}

function handleViewportChanged(event) {
  if (!pickerOpen.value) return

  if (event?.type === 'scroll') {
    const target = event.target

    if (hourMenu.value && target && (hourMenu.value === target || hourMenu.value.contains(target))) {
      return
    }

    if (minuteMenu.value && target && (minuteMenu.value === target || minuteMenu.value.contains(target))) {
      return
    }
  }

  hourMenuOpen.value = false
  minuteMenuOpen.value = false
}

onMounted(() => {
  window.addEventListener('pointerdown', handleClickOutside)
  window.addEventListener('resize', handleViewportChanged)
  window.addEventListener('scroll', handleViewportChanged, true)
})

onBeforeUnmount(() => {
  window.removeEventListener('pointerdown', handleClickOutside)
  window.removeEventListener('resize', handleViewportChanged)
  window.removeEventListener('scroll', handleViewportChanged, true)
})
</script>






