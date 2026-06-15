<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import HorizonButton from '@/Components/HorizonButton.vue'
import { Node, mergeAttributes } from '@tiptap/core'
import { Editor, EditorContent } from '@tiptap/vue-3'
import { BubbleMenu } from '@tiptap/vue-3/menus'
import { NodeSelection } from '@tiptap/pm/state'
import StarterKit from '@tiptap/starter-kit'
import Color from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import BaseImage from '@tiptap/extension-image'
import { TextAlign } from '@tiptap/extension-text-align'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableHeader } from '@tiptap/extension-table-header'
import { TableCell } from '@tiptap/extension-table-cell'
import { TextStyle } from '@tiptap/extension-text-style'

const MediaPickerModal = defineAsyncComponent(() => import('@/Components/MediaPickerModal.vue'))

const ExtendedTextStyle = TextStyle.extend({
  parseHTML() {
    return [
      {
        tag: 'span',
        getAttrs: element => {
          const style = element.getAttribute?.('style') || ''
          const className = element.getAttribute?.('class') || ''
          return style || /\bhz-rte-(size|font|color)-/.test(className) ? {} : false
        },
      },
    ]
  },

  addAttributes() {
    return {
      ...this.parent?.(),
      rteFontSize: {
        default: null,
        parseHTML: element => {
          const cls = Array.from(element.classList).find(name => name.startsWith('hz-rte-size-'))
          return cls || null
        },
        renderHTML: attributes => {
          if (!attributes.rteFontSize) return {}
          return { class: attributes.rteFontSize }
        },
      },
      rteFontFamily: {
        default: null,
        parseHTML: element => {
          const cls = Array.from(element.classList).find(name => name.startsWith('hz-rte-font-'))
          return cls || null
        },
        renderHTML: attributes => {
          if (!attributes.rteFontFamily) return {}
          return { class: attributes.rteFontFamily }
        },
      },
      rteColor: {
        default: null,
        parseHTML: element => {
          const cls = Array.from(element.classList).find(name => name.startsWith('hz-rte-color-'))
          return cls || null
        },
        renderHTML: attributes => {
          if (!attributes.rteColor) return {}
          return { class: attributes.rteColor }
        },
      },
    }
  },
  addCommands() {
    return {
      ...this.parent?.(),
      setRteFontSize: value => ({ chain }) => chain().setMark('textStyle', { rteFontSize: value || null }).run(),
      unsetRteFontSize: () => ({ chain }) => chain().setMark('textStyle', { rteFontSize: null }).run(),
      setRteFontFamily: value => ({ chain }) => chain().setMark('textStyle', { rteFontFamily: value || null }).run(),
      unsetRteFontFamily: () => ({ chain }) => chain().setMark('textStyle', { rteFontFamily: null }).run(),
      setRteColor: value => ({ chain }) => chain().setMark('textStyle', { rteColor: value || null }).run(),
      unsetRteColor: () => ({ chain }) => chain().setMark('textStyle', { rteColor: null }).run(),
    }
  },
})

function normalizeImageWidthPercent(value) {
  const raw = String(value ?? '').trim().replace(/%$/, '')
  if (!raw) return null

  const parsed = Number.parseFloat(raw)
  if (!Number.isFinite(parsed)) return null

  return Math.max(15, Math.min(100, Math.round(parsed)))
}

function extractImageWidthFromStyle(style) {
  const match = String(style || '').match(/(?:^|;)\s*width\s*:\s*(\d{1,3}(?:\.\d+)?)%/i)
  return match ? normalizeImageWidthPercent(match[1]) : null
}

function buildImageStyle(widthPercent) {
  const normalized = normalizeImageWidthPercent(widthPercent)
  return normalized ? `width: ${normalized}%; height: auto;` : null
}

function defaultImageWidthForAlign(align) {
  return align === 'center' ? 72 : 42
}

const WrappedImage = BaseImage.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      class: {
        default: 'hz-rich-image hz-rich-image-center',
        parseHTML: element => element.getAttribute('class') || 'hz-rich-image hz-rich-image-center',
        renderHTML: attributes => attributes.class ? { class: attributes.class } : {},
      },
      'data-align': {
        default: 'center',
        parseHTML: element => element.getAttribute('data-align') || 'center',
        renderHTML: attributes => ({ 'data-align': attributes['data-align'] || 'center' }),
      },
      widthPercent: {
        default: null,
        parseHTML: element => normalizeImageWidthPercent(
          element.getAttribute('data-width') || extractImageWidthFromStyle(element.getAttribute('style'))
        ),
        renderHTML: attributes => {
          const normalized = normalizeImageWidthPercent(attributes.widthPercent)

          if (!normalized) {
            return {}
          }

          return {
            'data-width': String(normalized),
            style: buildImageStyle(normalized),
          }
        },
      },
    }
  },
})

const CalloutNode = Node.create({
  name: 'calloutBox',
  group: 'block',
  content: 'block+',
  defining: true,

  parseHTML() {
    return [{ tag: 'div[data-type="hz-rte-callout"]' }]
  },

  addAttributes() {
    return {
      tone: {
        default: 'blue',
        parseHTML: element => element.getAttribute('data-tone') || (element.getAttribute('data-accent-color') ? 'custom' : 'blue'),
        renderHTML: attributes => ({ 'data-tone': attributes.tone || 'blue' }),
      },
      customColor: {
        default: null,
        parseHTML: element => normalizeHexColor(element.getAttribute('data-accent-color') || '') || null,
        renderHTML: attributes => {
          const normalized = normalizeHexColor(attributes.customColor)

          if (!normalized) {
            return {}
          }

          return { 'data-accent-color': normalized }
        },
      },
    }
  },

  renderHTML({ node, HTMLAttributes }) {
    const tone = node.attrs.tone || 'blue'
    const customColor = normalizeHexColor(node.attrs.customColor)
    const isCustomTone = Boolean(customColor)

    return [
      'div',
      mergeAttributes(HTMLAttributes, {
        'data-type': 'hz-rte-callout',
        class: `hz-rte-callout hz-rte-callout-${isCustomTone ? 'custom' : tone}`,
        style: isCustomTone
          ? `--hz-callout-custom-color: ${customColor}; border-color: color-mix(in srgb, ${customColor} 42%, transparent);`
          : null,
      }),
      0,
    ]
  },

  addCommands() {
    return {
      setCallout: attributes => ({ commands }) => commands.wrapIn(this.name, attributes),
      unsetCallout: () => ({ commands }) => commands.lift(this.name),
      toggleCallout: attributes => ({ commands }) => commands.toggleWrap(this.name, attributes),
    }
  },
})

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  rows: { type: Number, default: 6 },
  disabled: { type: Boolean, default: false },
  uploadCollection: { type: String, default: 'site_asset' },
})

const emit = defineEmits(['update:modelValue'])

const isFocused = ref(false)
const isEditorEmpty = ref(true)
const lastSelection = ref(null)
const toolbarTick = ref(0)
const uploadInput = ref(null)
const pendingImageAlign = ref('center')
const isUploadingImage = ref(false)
const isMediaPickerOpen = ref(false)
const uploadError = ref('')
const isMobileViewport = ref(false)
const isCompactToolbarExpanded = ref(false)
const editorViewport = ref(null)
const colorPickerPanel = ref(null)
const colorPickerField = ref(null)
const colorPickerSlider = ref(null)
const colorPickerPanelStyle = ref({})
const colorPickerAnchorRect = ref(null)
const selectedImageFrame = ref(null)
const isResizingImage = ref(false)
const isColorPickerOpen = ref(false)
const isColorPickerAdvancedOpen = ref(false)
const isBubbleBlockMenuOpen = ref(false)
const colorPickerTarget = ref('')
const colorPickerHexValue = ref('')
const colorPickerInitialHex = ref('')
const colorPickerHue = ref(0)
const colorPickerSaturation = ref(100)
const colorPickerValue = ref(100)
const colorPickerCustomColors = ref(Array(16).fill(''))
const activeCustomColorIndex = ref(-1)
const COLOR_PICKER_CUSTOM_COLORS_STORAGE_KEY = 'horizon.rich-editor.custom-colors'

let imageResizeCleanup = null
let colorPickerDragCleanup = null

function bumpToolbar() {
  toolbarTick.value += 1
}

function rememberSelection() {
  if (!editor) return

   const selection = editor.state.selection

  lastSelection.value = {
    from: selection.from,
    to: selection.to,
    type: selection instanceof NodeSelection ? 'node' : 'text',
  }
}

function restoreSelection() {
  if (!editor || !lastSelection.value) return

  if (lastSelection.value.type === 'node') {
    editor.commands.setNodeSelection(lastSelection.value.from)
    return
  }

  editor.commands.setTextSelection(lastSelection.value)
}

const isBoldActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('bold') ?? false
})
const isItalicActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('italic') ?? false
})
const isUnderlineActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('underline') ?? false
})
const isStrikeActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('strike') ?? false
})
const isBulletListActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('bulletList') ?? false
})
const isOrderedListActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('orderedList') ?? false
})
const isBlockquoteActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('blockquote') ?? false
})
const isLinkActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('link') ?? false
})
const isImageActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('image') ?? false
})
const canUndo = computed(() => {
  toolbarTick.value
  return editor?.can().chain().undo().run() ?? false
})
const canRedo = computed(() => {
  toolbarTick.value
  return editor?.can().chain().redo().run() ?? false
})

const currentHeadingLevel = computed(() => {
  toolbarTick.value
  if (!editor) return 0
  for (let level = 1; level <= 6; level += 1) {
    if (editor.isActive('heading', { level })) return level
  }
  return 0
})

const blockTypeValue = computed(() => currentHeadingLevel.value ? `h${currentHeadingLevel.value}` : 'p')
const currentBubbleBlockLabel = computed(() => {
  if (isBulletListActive.value) return 'Bulleted List'
  if (isOrderedListActive.value) return 'Numbered List'
  if (isBlockquoteActive.value) return 'Quote'
  if (currentHeadingLevel.value) return `Heading ${currentHeadingLevel.value}`
  return 'Normal Text'
})

const textAlignOptions = [
  { value: 'left', label: 'Left' },
  { value: 'center', label: 'Center' },
  { value: 'right', label: 'Right' },
  { value: 'justify', label: 'Justify' },
]

const currentTextAlign = computed(() => {
  toolbarTick.value
  if (!editor) return 'left'
  const attrs = editor.getAttributes(currentHeadingLevel.value > 0 ? 'heading' : 'paragraph') || {}
  return attrs.textAlign || 'left'
})

const currentFontSize = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('textStyle')?.rteFontSize || ''
})
const currentFontFamily = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('textStyle')?.rteFontFamily || ''
})
const currentTextColor = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('textStyle')?.rteColor || ''
})
const currentInlineTextColor = computed(() => {
  toolbarTick.value
  return normalizeHexColor(editor?.getAttributes('textStyle')?.color || '')
})
const isHighlightActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('highlight') ?? false
})
const currentHighlightColor = computed(() => {
  toolbarTick.value
  return normalizeHexColor(editor?.getAttributes('highlight')?.color || '')
})
const isCalloutActive = computed(() => {
  toolbarTick.value
  return editor?.isActive('calloutBox') ?? false
})
const currentCalloutTone = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('calloutBox')?.tone || ''
})
const currentCalloutCustomColor = computed(() => {
  toolbarTick.value
  return normalizeHexColor(editor?.getAttributes('calloutBox')?.customColor || '')
})
const currentImageAlign = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('image')?.['data-align'] || 'center'
})
const currentImageWidthPercent = computed(() => {
  toolbarTick.value
  const width = normalizeImageWidthPercent(editor?.getAttributes('image')?.widthPercent)
  return width || defaultImageWidthForAlign(currentImageAlign.value)
})

const imageWidthPresetOptions = [
  { value: 25, label: 'Image: Small (25%)' },
  { value: 40, label: 'Image: Medium (40%)' },
  { value: 60, label: 'Image: Large (60%)' },
  { value: 80, label: 'Image: XL (80%)' },
  { value: 100, label: 'Image: Full Width' },
]

const currentImageWidthSelectValue = computed(() => {
  if (!isImageActive.value) return ''

  const current = currentImageWidthPercent.value
  const match = imageWidthPresetOptions.find(option => option.value === current)

  return match ? String(match.value) : '__custom'
})

const fontFamilyOptions = [
  { value: '', label: 'Font: Default', shortLabel: 'Default', preview: '' },
  { value: 'hz-rte-font-system', label: 'Font: System', shortLabel: 'System', preview: 'system-ui' },
  { value: 'hz-rte-font-sans', label: 'Font: Sans', shortLabel: 'Sans', preview: 'sans-serif' },
  { value: 'hz-rte-font-arial', label: 'Font: Arial', shortLabel: 'Arial', preview: 'Arial, sans-serif' },
  { value: 'hz-rte-font-verdana', label: 'Font: Verdana', shortLabel: 'Verdana', preview: 'Verdana, sans-serif' },
  { value: 'hz-rte-font-georgia', label: 'Font: Georgia', shortLabel: 'Georgia', preview: 'Georgia, serif' },
  { value: 'hz-rte-font-mono', label: 'Font: Mono', shortLabel: 'Mono', preview: 'monospace' },
]

const fontSizeOptions = [
  { value: '', label: 'Size: Default', shortLabel: 'Default' },
  { value: 'hz-rte-size-12', label: '12px', shortLabel: '12px' },
  { value: 'hz-rte-size-14', label: '14px', shortLabel: '14px' },
  { value: 'hz-rte-size-16', label: '16px', shortLabel: '16px' },
  { value: 'hz-rte-size-18', label: '18px', shortLabel: '18px' },
  { value: 'hz-rte-size-20', label: '20px', shortLabel: '20px' },
  { value: 'hz-rte-size-24', label: '24px', shortLabel: '24px' },
  { value: 'hz-rte-size-32', label: '32px', shortLabel: '32px' },
]

const textColorOptions = [
  { value: '', label: 'Text: Default', hex: '#b6c2d3' },
  { value: 'hz-rte-color-white', label: 'White', hex: '#ffffff' },
  { value: 'hz-rte-color-white-soft', label: 'White Soft', hex: '#eef2ff' },
  { value: 'hz-rte-color-muted-light', label: 'Muted Light', hex: '#cbd5e1' },
  { value: 'hz-rte-color-muted', label: 'Muted', hex: '#6b7280' },
  { value: 'hz-rte-color-muted-dark', label: 'Muted Dark', hex: '#475569' },
  { value: 'hz-rte-color-blue-light', label: 'Blue Light', hex: '#60a5fa' },
  { value: 'hz-rte-color-blue', label: 'Blue', hex: '#1e40af' },
  { value: 'hz-rte-color-blue-dark', label: 'Blue Dark', hex: '#172554' },
  { value: 'hz-rte-color-cyan-light', label: 'Cyan Light', hex: '#67e8f9' },
  { value: 'hz-rte-color-cyan', label: 'Cyan', hex: '#38bdf8' },
  { value: 'hz-rte-color-cyan-dark', label: 'Cyan Dark', hex: '#0e7490' },
  { value: 'hz-rte-color-magenta-light', label: 'Magenta Light', hex: '#e879f9' },
  { value: 'hz-rte-color-magenta', label: 'Magenta', hex: '#c026d3' },
  { value: 'hz-rte-color-magenta-dark', label: 'Magenta Dark', hex: '#86198f' },
  { value: 'hz-rte-color-pink-light', label: 'Pink Light', hex: '#ff8db4' },
  { value: 'hz-rte-color-pink', label: 'Pink', hex: '#ff3d81' },
  { value: 'hz-rte-color-pink-dark', label: 'Pink Dark', hex: '#be185d' },
  { value: 'hz-rte-color-orange-light', label: 'Orange Light', hex: '#fdba74' },
  { value: 'hz-rte-color-orange', label: 'Orange', hex: '#ff8a3d' },
  { value: 'hz-rte-color-orange-dark', label: 'Orange Dark', hex: '#c2410c' },
  { value: 'hz-rte-color-green-light', label: 'Green Light', hex: '#86efac' },
  { value: 'hz-rte-color-green', label: 'Green', hex: '#22c55e' },
  { value: 'hz-rte-color-green-dark', label: 'Green Dark', hex: '#166534' },
  { value: 'hz-rte-color-red-light', label: 'Red Light', hex: '#fca5a5' },
  { value: 'hz-rte-color-red', label: 'Red', hex: '#ef4444' },
  { value: 'hz-rte-color-red-dark', label: 'Red Dark', hex: '#b91c1c' },
  { value: 'hz-rte-color-yellow-light', label: 'Yellow Light', hex: '#fde047' },
  { value: 'hz-rte-color-yellow', label: 'Yellow', hex: '#eab308' },
  { value: 'hz-rte-color-yellow-dark', label: 'Yellow Dark', hex: '#a16207' },
]

const calloutToneOptions = [
  { value: '', label: 'Field: None', hex: '#1b2035' },
  { value: 'blue', label: 'Field: Blue', hex: '#1e40af' },
  { value: 'cyan', label: 'Field: Cyan', hex: '#38bdf8' },
  { value: 'magenta', label: 'Field: Magenta', hex: '#c026d3' },
  { value: 'orange', label: 'Field: Orange', hex: '#ff8a3d' },
  { value: 'green', label: 'Field: Green', hex: '#22c55e' },
  { value: 'red', label: 'Field: Red', hex: '#ef4444' },
]

const defaultTextColorHex = '#b6c2d3'
const defaultHighlightHex = '#1e293b'
const defaultCalloutHex = '#1e40af'
const classicColorSwatches = [
  '#ff8080', '#ffff80', '#80ff80', '#00ff80', '#80ffff', '#0080ff', '#ff80c0', '#ff80ff',
  '#ff0000', '#ffff00', '#80ff00', '#00ff40', '#00ffff', '#0080c0', '#c080ff', '#ff00ff',
  '#804040', '#ff8040', '#00ff00', '#008040', '#008080', '#004080', '#8080c0', '#800040',
  '#800000', '#ff8000', '#008000', '#808000', '#0000ff', '#0000a0', '#8080ff', '#800080',
  '#400000', '#804000', '#004000', '#004040', '#000080', '#000040', '#400080', '#400040',
  '#000000', '#808080', '#408080', '#c0c0c0', '#4080ff', '#ffffff', '#d9d9d9', '#f5f5f5',
]
const textColorHexLookup = new Map(textColorOptions.filter(option => option.value).map(option => [option.value, option.hex]))
const calloutToneHexLookup = new Map(calloutToneOptions.filter(option => option.value).map(option => [option.value, option.hex]))

const currentFontFamilyPreview = computed(() => {
  const match = fontFamilyOptions.find(option => option.value === currentFontFamily.value)
  return match?.preview || ''
})

const currentTextColorHex = computed(() => currentInlineTextColor.value || textColorHexLookup.get(currentTextColor.value) || defaultTextColorHex)

const currentHighlightHex = computed(() => currentHighlightColor.value || defaultHighlightHex)

const currentCalloutHex = computed(() => currentCalloutCustomColor.value || calloutToneHexLookup.get(currentCalloutTone.value) || defaultCalloutHex)
const showAdvancedToolbar = computed(() => isCompactToolbarExpanded.value)
const isMacPlatform = computed(() => {
  if (typeof navigator === 'undefined') return false

  const platform = navigator.userAgentData?.platform || navigator.platform || ''

  return /Mac|iPhone|iPad|iPod/i.test(platform)
})
const colorPickerDraftHex = computed(() => {
  const rgb = hsvToRgb(colorPickerHue.value, colorPickerSaturation.value, colorPickerValue.value)
  return rgbToHex(rgb.r, rgb.g, rgb.b)
})
const colorPickerDraftRgb = computed(() => hexToRgb(colorPickerDraftHex.value))
const colorPickerHueInput = computed(() => Math.round(colorPickerHue.value))
const colorPickerSatInput = computed(() => Math.round((colorPickerSaturation.value / 100) * 240))
const colorPickerLumInput = computed(() => Math.round((colorPickerValue.value / 100) * 240))
const colorPickerPreviewLabel = computed(() => {
  if (colorPickerTarget.value === 'highlight') return 'Highlight'
  if (colorPickerTarget.value === 'callout') return 'Field'
  return 'Text'
})
const colorPickerFieldStyle = computed(() => ({
  background: `linear-gradient(to top, rgb(0 0 0 / 1), transparent), linear-gradient(to right, rgb(255 255 255 / 1), hsl(${colorPickerHue.value} 100% 50%))`,
}))
const colorPickerFieldCursorStyle = computed(() => ({
  left: `${colorPickerSaturation.value}%`,
  top: `${100 - colorPickerValue.value}%`,
}))
const colorPickerSliderCursorStyle = computed(() => ({
  top: `${(colorPickerHue.value / 360) * 100}%`,
}))

const bubbleSurfaceStyle = {
  border: '1px solid color-mix(in srgb, var(--horizon-sunset-blue) 24%, rgb(255 255 255 / 0.055))',
  backgroundColor: 'var(--color-surface-accent)',
  backgroundImage: 'none',
  boxShadow: 'inset 0 1px 0 rgb(255 255 255 / 0.04), 0 14px 30px rgb(2 6 23 / 0.22)',
  backdropFilter: 'blur(18px)',
}

const bubblePanelSurfaceStyle = {
  border: '1px solid color-mix(in srgb, var(--horizon-sunset-blue) 24%, rgb(255 255 255 / 0.055))',
  backgroundColor: 'var(--color-surface-accent)',
  backgroundImage: 'none',
  boxShadow: 'inset 0 1px 0 rgb(255 255 255 / 0.04), 0 14px 30px rgb(2 6 23 / 0.24)',
  backdropFilter: 'blur(18px)',
}

const toolbarSurfaceStyle = {
  border: '1px solid color-mix(in srgb, var(--horizon-sunset-blue) 24%, rgb(255 255 255 / 0.055))',
  backgroundColor: 'var(--color-surface-accent)',
  backgroundImage: 'none',
  boxShadow: 'inset 0 1px 0 rgb(255 255 255 / 0.04), 0 18px 42px rgb(2 6 23 / 0.28)',
  backdropFilter: 'blur(16px)',
}

function normalizeShortcutKey(part) {
  if (!part) return ''

  if (part.length === 1) {
    return part.toUpperCase()
  }

  return part
}

function formatShortcutLabel(shortcut) {
  if (!shortcut) return ''

  const macLabels = {
    Mod: '⌘',
    Shift: '⇧',
    Alt: '⌥',
    Ctrl: '⌃',
  }

  const defaultLabels = {
    Mod: 'Ctrl',
    Shift: 'Shift',
    Alt: 'Alt',
    Ctrl: 'Ctrl',
  }

  const tokens = String(shortcut)
    .split('-')
    .map(token => token.trim())
    .filter(Boolean)

  const labels = tokens.map(token => {
    if (isMacPlatform.value) {
      return macLabels[token] || normalizeShortcutKey(token)
    }

    return defaultLabels[token] || normalizeShortcutKey(token)
  })

  return isMacPlatform.value ? labels.join('') : labels.join('+')
}

function bubbleTooltipText(label, shortcut = '') {
  const formattedShortcut = formatShortcutLabel(shortcut)

  return formattedShortcut ? `${label} • ${formattedShortcut}` : label
}

function bubbleTooltipTextList(label, shortcuts = []) {
  const formattedShortcuts = shortcuts
    .map(formatShortcutLabel)
    .filter(Boolean)
    .join(' / ')

  return formattedShortcuts ? `${label} • ${formattedShortcuts}` : label
}

function normalizeHexColor(value) {
  const raw = String(value || '').trim()

  if (!raw) return ''

  const shortHex = raw.match(/^#([\da-f]{3})$/i)
  if (shortHex) {
    return `#${shortHex[1].split('').map(part => part + part).join('').toLowerCase()}`
  }

  const longHex = raw.match(/^#([\da-f]{6})$/i)
  if (longHex) {
    return `#${longHex[1].toLowerCase()}`
  }

  const rgbMatch = raw.match(/^rgba?\(([^)]+)\)$/i)
  if (!rgbMatch) return ''

  const parts = rgbMatch[1]
    .split(',')
    .slice(0, 3)
    .map(part => Number.parseInt(part.trim(), 10))

  if (parts.length !== 3 || parts.some(part => Number.isNaN(part))) {
    return ''
  }

  return `#${parts
    .map(part => Math.max(0, Math.min(255, part)))
    .map(part => part.toString(16).padStart(2, '0'))
    .join('')}`
}

function normalizeStoredCustomColors(value) {
  if (!Array.isArray(value)) {
    return Array(16).fill('')
  }

  return Array.from({ length: 16 }, (_, index) => normalizeHexColor(value[index]) || '')
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value))
}

function normalizeHueValue(value) {
  const numeric = Number(value)

  if (!Number.isFinite(numeric)) {
    return 0
  }

  const wrapped = numeric % 360
  return wrapped < 0 ? wrapped + 360 : wrapped
}

function rgbToHex(r, g, b) {
  return `#${[r, g, b]
    .map(channel => clamp(Math.round(channel), 0, 255).toString(16).padStart(2, '0'))
    .join('')}`
}

function hexToRgb(value) {
  const normalized = normalizeHexColor(value)

  if (!normalized) {
    return { r: 255, g: 255, b: 255 }
  }

  return {
    r: Number.parseInt(normalized.slice(1, 3), 16),
    g: Number.parseInt(normalized.slice(3, 5), 16),
    b: Number.parseInt(normalized.slice(5, 7), 16),
  }
}

function rgbToHsv(r, g, b) {
  const red = clamp(r, 0, 255) / 255
  const green = clamp(g, 0, 255) / 255
  const blue = clamp(b, 0, 255) / 255
  const max = Math.max(red, green, blue)
  const min = Math.min(red, green, blue)
  const delta = max - min

  let hue = 0

  if (delta !== 0) {
    if (max === red) {
      hue = 60 * (((green - blue) / delta) % 6)
    } else if (max === green) {
      hue = 60 * (((blue - red) / delta) + 2)
    } else {
      hue = 60 * (((red - green) / delta) + 4)
    }
  }

  if (hue < 0) {
    hue += 360
  }

  const saturation = max === 0 ? 0 : (delta / max) * 100
  const value = max * 100

  return { h: hue, s: saturation, v: value }
}

function hsvToRgb(hue, saturation, value) {
  const normalizedHue = normalizeHueValue(hue)
  const normalizedSaturation = clamp(saturation, 0, 100) / 100
  const normalizedValue = clamp(value, 0, 100) / 100
  const chroma = normalizedValue * normalizedSaturation
  const segment = normalizedHue / 60
  const second = chroma * (1 - Math.abs((segment % 2) - 1))
  const match = normalizedValue - chroma

  let red = 0
  let green = 0
  let blue = 0

  if (segment >= 0 && segment < 1) {
    red = chroma
    green = second
  } else if (segment < 2) {
    red = second
    green = chroma
  } else if (segment < 3) {
    green = chroma
    blue = second
  } else if (segment < 4) {
    green = second
    blue = chroma
  } else if (segment < 5) {
    red = second
    blue = chroma
  } else {
    red = chroma
    blue = second
  }

  return {
    r: Math.round((red + match) * 255),
    g: Math.round((green + match) * 255),
    b: Math.round((blue + match) * 255),
  }
}

function setColorPickerFromHex(value) {
  const rgb = hexToRgb(value)
  const hsv = rgbToHsv(rgb.r, rgb.g, rgb.b)

  colorPickerHue.value = hsv.h
  colorPickerSaturation.value = hsv.s
  colorPickerValue.value = hsv.v
}

function setColorPickerFromRgb(r, g, b) {
  const hsv = rgbToHsv(r, g, b)
  colorPickerHue.value = hsv.h
  colorPickerSaturation.value = hsv.s
  colorPickerValue.value = hsv.v
}

function targetHexForPicker(target) {
  if (target === 'highlight') return currentHighlightHex.value || defaultHighlightHex
  if (target === 'callout') return currentCalloutHex.value || defaultCalloutHex
  return currentTextColorHex.value || defaultTextColorHex
}

function positionColorPicker(triggerElement = null) {
  if (typeof window === 'undefined') {
    return
  }

  if (triggerElement instanceof HTMLElement) {
    colorPickerAnchorRect.value = triggerElement.getBoundingClientRect()
  }

  const rect = colorPickerAnchorRect.value
  if (!rect) {
    colorPickerPanelStyle.value = {}
    return
  }

  const panelWidth = Math.min(384, window.innerWidth - 24)
  const left = clamp(rect.left, 12, Math.max(12, window.innerWidth - panelWidth - 12))
  const estimatedHeight = colorPickerPanel.value instanceof HTMLElement
    ? colorPickerPanel.value.offsetHeight
    : 360
  const spaceBelow = window.innerHeight - rect.bottom - 12
  const spaceAbove = rect.top - 12
  const shouldOpenAbove = spaceBelow < Math.min(estimatedHeight, 280) && spaceAbove > spaceBelow
  const top = shouldOpenAbove
    ? Math.max(12, rect.top - estimatedHeight - 8)
    : Math.max(12, Math.min(rect.bottom + 8, window.innerHeight - estimatedHeight - 12))

  colorPickerPanelStyle.value = {
    position: 'fixed',
    top: `${top}px`,
    left: `${left}px`,
    width: `${panelWidth}px`,
    maxHeight: `${Math.max(220, window.innerHeight - 24)}px`,
    overflowY: 'auto',
    zIndex: '120',
  }
}

function openColorPicker(target, event = null) {
  if (props.disabled) return

  rememberSelection()

  const startingHex = targetHexForPicker(target)

  colorPickerTarget.value = target
  colorPickerHexValue.value = startingHex.toUpperCase()
  colorPickerInitialHex.value = startingHex
  setColorPickerFromHex(startingHex)
  isColorPickerAdvancedOpen.value = false
  positionColorPicker(event?.currentTarget ?? null)
  isColorPickerOpen.value = true
  nextTick(() => positionColorPicker())
}

function closeColorPicker() {
  isColorPickerOpen.value = false
  isColorPickerAdvancedOpen.value = false
  colorPickerTarget.value = ''
  colorPickerHexValue.value = ''
  activeCustomColorIndex.value = -1
  colorPickerPanelStyle.value = {}
  colorPickerAnchorRect.value = null
  stopColorPickerDrag()
}

function confirmColorPicker() {
  const nextColor = colorPickerDraftHex.value

  if (colorPickerTarget.value === 'highlight') {
    applyHighlightColor(nextColor)
  } else if (colorPickerTarget.value === 'callout') {
    applyCustomCalloutColor(nextColor)
  } else {
    applyCustomTextColor(nextColor)
  }

  closeColorPicker()
}

function applyColorPickerRgbChannel(channel, value) {
  const current = { ...colorPickerDraftRgb.value }
  current[channel] = clamp(Number.parseInt(value, 10) || 0, 0, 255)
  setColorPickerFromRgb(current.r, current.g, current.b)
}

function applyColorPickerHexInput(value) {
  colorPickerHexValue.value = String(value ?? '').trim().toUpperCase()

  const normalized = normalizeHexColor(colorPickerHexValue.value)

  if (!normalized) {
    return
  }

  setColorPickerFromHex(normalized)
  colorPickerHexValue.value = normalized.toUpperCase()
}

function confirmColorPickerHexInput() {
  const normalized = normalizeHexColor(colorPickerHexValue.value)

  if (!normalized) {
    colorPickerHexValue.value = colorPickerDraftHex.value.toUpperCase()
    return
  }

  setColorPickerFromHex(normalized)
  colorPickerHexValue.value = normalized.toUpperCase()
  confirmColorPicker()
}

function applyColorPickerHueInput(value) {
  colorPickerHue.value = normalizeHueValue(Number.parseInt(value, 10) || 0)
}

function applyColorPickerSatInput(value) {
  const numeric = clamp(Number.parseInt(value, 10) || 0, 0, 240)
  colorPickerSaturation.value = (numeric / 240) * 100
}

function applyColorPickerLumInput(value) {
  const numeric = clamp(Number.parseInt(value, 10) || 0, 0, 240)
  colorPickerValue.value = (numeric / 240) * 100
}

function updateColorPickerFieldFromPoint(clientX, clientY) {
  const field = colorPickerField.value
  if (!(field instanceof HTMLElement)) return

  const rect = field.getBoundingClientRect()
  const x = clamp((clientX - rect.left) / rect.width, 0, 1)
  const y = clamp((clientY - rect.top) / rect.height, 0, 1)

  colorPickerSaturation.value = x * 100
  colorPickerValue.value = (1 - y) * 100
}

function updateColorPickerSliderFromPoint(clientY) {
  const slider = colorPickerSlider.value
  if (!(slider instanceof HTMLElement)) return

  const rect = slider.getBoundingClientRect()
  const y = clamp((clientY - rect.top) / rect.height, 0, 1)
  colorPickerHue.value = y * 360
}

function stopColorPickerDrag() {
  colorPickerDragCleanup?.()
  colorPickerDragCleanup = null
}

function startColorPickerDrag(event, target) {
  if (props.disabled) return

  event.preventDefault()
  event.stopPropagation()

  const onMouseMove = moveEvent => {
    if (target === 'field') {
      updateColorPickerFieldFromPoint(moveEvent.clientX, moveEvent.clientY)
      return
    }

    updateColorPickerSliderFromPoint(moveEvent.clientY)
  }

  const onMouseUp = () => {
    stopColorPickerDrag()
  }

  colorPickerDragCleanup = () => {
    window.removeEventListener('mousemove', onMouseMove)
    window.removeEventListener('mouseup', onMouseUp)
  }

  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)

  if (target === 'field') {
    updateColorPickerFieldFromPoint(event.clientX, event.clientY)
  } else {
    updateColorPickerSliderFromPoint(event.clientY)
  }
}

function selectClassicColor(hex) {
  setColorPickerFromHex(hex)
}

function selectCustomColor(index) {
  const value = colorPickerCustomColors.value[index]
  if (!value) return

  activeCustomColorIndex.value = index
  setColorPickerFromHex(value)
}

function addCurrentColorToCustomColors() {
  const nextColor = colorPickerDraftHex.value
  const existingIndex = colorPickerCustomColors.value.findIndex(color => color === nextColor)

  if (existingIndex !== -1) {
    activeCustomColorIndex.value = existingIndex
    return
  }

  const nextIndex = colorPickerCustomColors.value.findIndex(color => !color)
  const targetIndex = nextIndex !== -1
    ? nextIndex
    : (activeCustomColorIndex.value !== -1 ? activeCustomColorIndex.value : colorPickerCustomColors.value.length - 1)

  const nextColors = [...colorPickerCustomColors.value]
  nextColors[targetIndex] = nextColor
  colorPickerCustomColors.value = nextColors
  activeCustomColorIndex.value = targetIndex
}

function clearSelectedCustomColor() {
  if (activeCustomColorIndex.value < 0) {
    return
  }

  const nextColors = [...colorPickerCustomColors.value]
  nextColors[activeCustomColorIndex.value] = ''
  colorPickerCustomColors.value = nextColors
  activeCustomColorIndex.value = -1
}

function loadStoredCustomColors() {
  if (typeof window === 'undefined') {
    return
  }

  try {
    const raw = window.localStorage.getItem(COLOR_PICKER_CUSTOM_COLORS_STORAGE_KEY)

    if (!raw) {
      return
    }

    colorPickerCustomColors.value = normalizeStoredCustomColors(JSON.parse(raw))
  } catch {
    colorPickerCustomColors.value = Array(16).fill('')
  }
}

function persistCustomColors(colors) {
  if (typeof window === 'undefined') {
    return
  }

  window.localStorage.setItem(
    COLOR_PICKER_CUSTOM_COLORS_STORAGE_KEY,
    JSON.stringify(normalizeStoredCustomColors(colors))
  )
}

function handleDocumentMouseDown(event) {
  const target = event.target
  if (!(target instanceof HTMLElement)) return

  if (isBubbleBlockMenuOpen.value && !target.closest('.rich-editor-bubble')) {
    isBubbleBlockMenuOpen.value = false
  }

  if (!isColorPickerOpen.value) return

  if (colorPickerPanel.value?.contains(target) || target.closest('.rich-editor-color-picker-trigger')) {
    return
  }

  closeColorPicker()
}

function refreshColorPickerPosition() {
  if (!isColorPickerOpen.value) return
  positionColorPicker()
}

function closeBubbleBlockMenu() {
  isBubbleBlockMenuOpen.value = false
}

function toggleBubbleBlockMenu() {
  if (props.disabled) return
  rememberSelection()
  isBubbleBlockMenuOpen.value = !isBubbleBlockMenuOpen.value
}

function bubbleMenuAppendTarget() {
  if (typeof document === 'undefined') {
    return undefined
  }

  return document.body
}

function shouldShowTextBubbleMenu({ editor, state, from, to }) {
  if (props.disabled || isColorPickerOpen.value) return false
  if (!editor?.isFocused || editor.isActive('image')) return false
  if (state.selection.empty || state.selection instanceof NodeSelection) return false

  return state.doc.textBetween(from, to, ' ').trim().length > 0
}

function syncViewportState() {
  if (typeof window === 'undefined') return

  const wasMobile = isMobileViewport.value
  const nextIsMobile = window.innerWidth < 768
  isMobileViewport.value = nextIsMobile

  if (nextIsMobile && !wasMobile) {
    isCompactToolbarExpanded.value = false
  } else if (!nextIsMobile && wasMobile) {
    isCompactToolbarExpanded.value = false
  }
}

function toggleCompactToolbar() {
  isCompactToolbarExpanded.value = !isCompactToolbarExpanded.value
}

function normalizeIncomingHtml(html) {
  const value = String(html || '').trim()
  if (!value) return ''

  const paragraphCount = (value.match(/<p(\s|>)/gi) || []).length
  const hasBr = /<br\s*\/?\s*>/i.test(value)
  const hasOtherBlockTags = /<(ul|ol|li|h1|h2|h3|h4|h5|h6|blockquote|pre|img|table|thead|tbody|tfoot|tr|td|th)\b/i.test(value)

  if (paragraphCount === 1 && hasBr && !hasOtherBlockTags) {
    return value
      .replace(/<br\s*\/?\s*>\s*/gi, '</p><p>')
      .replace(/<p>\s*<\/p>/gi, '<p></p>')
  }

  return value
}

function imageClassForAlign(align) {
  if (align === 'left') return 'hz-rich-image hz-rich-image-left'
  if (align === 'right') return 'hz-rich-image hz-rich-image-right'
  return 'hz-rich-image hz-rich-image-center'
}

function getSelectedImageElement() {
  if (!editor?.isActive('image')) return null

  const nodeDom = editor.view.nodeDOM(editor.state.selection.from)

  if (nodeDom instanceof HTMLImageElement) {
    return nodeDom
  }

  if (nodeDom instanceof HTMLElement) {
    const nestedImage = nodeDom.querySelector('img')

    if (nestedImage instanceof HTMLImageElement) {
      return nestedImage
    }
  }

  const fallback = editor.view.dom.querySelector('img.ProseMirror-selectednode')

  return fallback instanceof HTMLImageElement ? fallback : null
}

function updateSelectedImageFrame() {
  if (isResizingImage.value) {
    return
  }

  const viewport = editorViewport.value
  const image = getSelectedImageElement()

  if (!(viewport instanceof HTMLElement) || !(image instanceof HTMLImageElement)) {
    selectedImageFrame.value = null
    return
  }

  const viewportRect = viewport.getBoundingClientRect()
  const imageRect = image.getBoundingClientRect()

  if (imageRect.width <= 0 || imageRect.height <= 0) {
    selectedImageFrame.value = null
    return
  }

  selectedImageFrame.value = {
    top: imageRect.top - viewportRect.top,
    left: imageRect.left - viewportRect.left,
    width: imageRect.width,
    height: imageRect.height,
  }
}

function queueSelectedImageFrameUpdate() {
  nextTick(() => updateSelectedImageFrame())
}

function buildResizedImageFrame({ top, left, width, align, aspectRatio }, nextWidth) {
  const normalizedWidth = Math.max(0, nextWidth)
  const widthDelta = normalizedWidth - width

  let nextLeft = left

  if (align === 'center') {
    nextLeft = left - (widthDelta / 2)
  } else if (align === 'right') {
    nextLeft = left - widthDelta
  }

  return {
    top,
    left: nextLeft,
    width: normalizedWidth,
    height: normalizedWidth * aspectRatio,
  }
}

function setImageWidth(widthPercent) {
  if (!editor?.isActive('image')) return

  const normalized = normalizeImageWidthPercent(widthPercent)

  if (!normalized) return

  focusAndRestoreSelection()
  editor.chain().updateAttributes('image', { widthPercent: normalized }).run()
  bumpToolbar()
  queueSelectedImageFrameUpdate()
}

function applyImageWidthPreset(value) {
  if (value === '__custom') return

  const normalized = normalizeImageWidthPercent(value)

  if (!normalized) return

  setImageWidth(normalized)
}

function stopImageResize() {
  imageResizeCleanup?.()
  imageResizeCleanup = null
  isResizingImage.value = false
  queueSelectedImageFrameUpdate()
}

function startImageResize(event, handle = 'right') {
  if (props.disabled || !editor?.isActive('image')) return

  const viewport = editorViewport.value
  const image = getSelectedImageElement()

  if (!(viewport instanceof HTMLElement) || !(image instanceof HTMLImageElement)) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  rememberSelection()
  focusAndRestoreSelection()

  const viewportRect = viewport.getBoundingClientRect()
  const imageRect = image.getBoundingClientRect()
  const startX = event.clientX
  const startWidth = imageRect.width
  const minWidth = viewportRect.width * 0.15
  const imageAlign = currentImageAlign.value
  const startFrame = {
    top: imageRect.top - viewportRect.top,
    left: imageRect.left - viewportRect.left,
    width: imageRect.width,
    align: imageAlign,
    aspectRatio: imageRect.width > 0 ? imageRect.height / imageRect.width : 1,
  }

  isResizingImage.value = true

  const onMouseMove = moveEvent => {
    const horizontalDelta = moveEvent.clientX - startX
    const resizeFromLeft = ['left', 'top-left', 'bottom-left'].includes(handle)
    const deltaMultiplier = resizeFromLeft ? -1 : 1
    const nextWidthPx = Math.max(minWidth, Math.min(viewportRect.width, startWidth + (horizontalDelta * deltaMultiplier)))
    const normalized = normalizeImageWidthPercent((nextWidthPx / viewportRect.width) * 100)

    if (!normalized) {
      return
    }

    restoreSelection()
    editor.commands.updateAttributes('image', { widthPercent: normalized })
    bumpToolbar()
    selectedImageFrame.value = buildResizedImageFrame(startFrame, nextWidthPx)
  }

  const onMouseUp = () => {
    stopImageResize()
  }

  imageResizeCleanup = () => {
    window.removeEventListener('mousemove', onMouseMove)
    window.removeEventListener('mouseup', onMouseUp)
  }

  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)
}

function mediaDisplayUrl(media) {
  return media?.display_url || media?.medium_url || media?.url || media?.thumbnail_url || ''
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

const editor = new Editor({
  editorProps: {
    attributes: {
      class: 'outline-none whitespace-pre-wrap wrap-break-word',
    },
  },
  extensions: [
    StarterKit.configure({
      heading: { levels: [1, 2, 3, 4, 5, 6] },
      code: false,
      codeBlock: false,
    }),
    Color.configure({ types: ['textStyle'] }),
    Highlight.configure({ multicolor: true }),
    Underline,
    WrappedImage.configure({ inline: false, allowBase64: false, HTMLAttributes: { loading: 'lazy' } }),
    ExtendedTextStyle,
    CalloutNode,
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Table.configure({ resizable: false }),
    TableRow,
    TableHeader,
    TableCell,
    Link.configure({
      openOnClick: false,
      autolink: true,
      linkOnPaste: true,
      HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer' },
    }),
  ],
  content: normalizeIncomingHtml(props.modelValue),
  editable: !props.disabled,
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
    isEditorEmpty.value = editor.isEmpty
    bumpToolbar()
    queueSelectedImageFrameUpdate()
  },
  onSelectionUpdate: () => {
    rememberSelection()
    closeBubbleBlockMenu()
    bumpToolbar()
    queueSelectedImageFrameUpdate()
  },
  onFocus: () => {
    isFocused.value = true
    rememberSelection()
    bumpToolbar()
    queueSelectedImageFrameUpdate()
  },
  onBlur: ({ editor }) => {
    isFocused.value = false
    isEditorEmpty.value = editor.isEmpty
    rememberSelection()
    closeBubbleBlockMenu()
    bumpToolbar()
    queueSelectedImageFrameUpdate()
  },
})

isEditorEmpty.value = editor.isEmpty

const minHeightPx = computed(() => Math.max(120, props.rows * 22 + 20))
const showPlaceholder = computed(() => Boolean(props.placeholder) && !props.disabled && !isFocused.value && isEditorEmpty.value)

function focusAndRestoreSelection() {
  editor.chain().focus().run()
  restoreSelection()
}

function toggleBold() { focusAndRestoreSelection(); editor.chain().toggleBold().run() }
function toggleItalic() { focusAndRestoreSelection(); editor.chain().toggleItalic().run() }
function toggleUnderline() { focusAndRestoreSelection(); editor.chain().toggleUnderline().run() }
function toggleStrike() { focusAndRestoreSelection(); editor.chain().toggleStrike().run() }
function toggleUnorderedList() { focusAndRestoreSelection(); editor.chain().toggleBulletList().run() }
function toggleOrderedList() { focusAndRestoreSelection(); editor.chain().toggleOrderedList().run() }
function toggleBlockquote() { focusAndRestoreSelection(); editor.chain().toggleBlockquote().run() }
function insertDivider() { focusAndRestoreSelection(); editor.chain().setHorizontalRule().run() }
function undo() { focusAndRestoreSelection(); editor.chain().undo().run() }
function redo() { focusAndRestoreSelection(); editor.chain().redo().run() }

function applyBlockType(value) {
  focusAndRestoreSelection()
  if (value === 'p') {
    editor.chain().setParagraph().run()
    return
  }
  const match = String(value).match(/^h([1-6])$/)
  if (!match) return
  editor.chain().setHeading({ level: Number(match[1]) }).run()
}

function applyBubbleBlockType(value) {
  applyBlockType(value)
  closeBubbleBlockMenu()
}

function applyBubbleListType(type) {
  if (type === 'bullet') {
    toggleUnorderedList()
  } else if (type === 'ordered') {
    toggleOrderedList()
  }

  closeBubbleBlockMenu()
}

function applyBubbleQuoteType() {
  toggleBlockquote()
  closeBubbleBlockMenu()
}

function openBubbleColorPicker(target, event) {
  closeBubbleBlockMenu()
  openColorPicker(target, event)
}

function clearBubbleTextColor() {
  applyCustomTextColor('')
}

function clearBubbleHighlightColor() {
  applyHighlightColor('')
}

function clearBubbleFieldColor() {
  applyCustomCalloutColor('')
}

function bubbleClearTypography() {
  clearTypography()
}

function bubbleInsertDivider() {
  closeBubbleBlockMenu()
  insertDivider()
}

function bubbleInsertImageUrl() {
  closeBubbleBlockMenu()
  insertImage('center')
}

function bubbleTriggerImageUpload() {
  closeBubbleBlockMenu()
  triggerImageUpload('center')
}

function bubbleOpenMediaPicker() {
  closeBubbleBlockMenu()
  openMediaPicker('center')
}

function bubbleUndo() {
  closeBubbleBlockMenu()
  undo()
}

function bubbleRedo() {
  closeBubbleBlockMenu()
  redo()
}

function bubbleInsertTable() {
  closeBubbleBlockMenu()
  insertTable()
}

function bubbleAddTableRow() {
  closeBubbleBlockMenu()
  addTableRow()
}

function bubbleAddTableColumn() {
  closeBubbleBlockMenu()
  addTableColumn()
}

function bubbleDeleteTable() {
  closeBubbleBlockMenu()
  deleteTable()
}

function setBubbleTextAlign(value) {
  closeBubbleBlockMenu()
  setTextAlign(value)
}

function applyBubbleFontFamily(value) {
  closeBubbleBlockMenu()
  applyFontFamily(value)
}

function applyBubbleFontSize(value) {
  closeBubbleBlockMenu()
  applyFontSize(value)
}

function setTextAlign(value) {
  focusAndRestoreSelection()
  editor.chain().setTextAlign(value).run()
}

function applyCustomTextColor(value) {
  const normalized = normalizeHexColor(value)

  focusAndRestoreSelection()

  if (!normalized) {
    editor.chain().unsetColor().unsetRteColor().run()
    return
  }

  editor.chain().unsetRteColor().setColor(normalized).run()
}

function applyHighlightColor(value) {
  if (value === '__custom') return

  const normalized = normalizeHexColor(value)

  focusAndRestoreSelection()

  if (!normalized) {
    editor.chain().unsetHighlight().run()
    return
  }

  editor.chain().setHighlight({ color: normalized }).run()
}

function applyCustomCalloutColor(value) {
  const normalized = normalizeHexColor(value)

  focusAndRestoreSelection()

  if (!normalized) {
    if (editor.isActive('calloutBox')) {
      editor.chain().unsetCallout().run()
    }
    return
  }

  if (editor.isActive('calloutBox')) {
    editor.chain().updateAttributes('calloutBox', { tone: 'custom', customColor: normalized }).run()
    return
  }

  editor.chain().setCallout({ tone: 'custom', customColor: normalized }).run()
}

function applyFontFamily(value) {
  focusAndRestoreSelection()
  if (!value) {
    editor.chain().unsetRteFontFamily().run()
    return
  }
  editor.chain().setRteFontFamily(value).run()
}

function applyFontSize(value) {
  focusAndRestoreSelection()
  if (!value) {
    editor.chain().unsetRteFontSize().run()
    return
  }
  editor.chain().setRteFontSize(value).run()
}

function clearTypography() {
  focusAndRestoreSelection()
  editor.chain().unsetColor().unsetRteColor().unsetRteFontFamily().unsetRteFontSize().run()
}

function insertTable() {
  focusAndRestoreSelection()
  editor.chain().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}
function addTableRow() { focusAndRestoreSelection(); editor.chain().addRowAfter().run() }
function addTableColumn() { focusAndRestoreSelection(); editor.chain().addColumnAfter().run() }
function deleteTable() { focusAndRestoreSelection(); editor.chain().deleteTable().run() }

function insertImageWithAttributes(src, alt = '', align = 'center', widthPercent = defaultImageWidthForAlign(align)) {
  focusAndRestoreSelection()
  editor.chain().setImage({
    src,
    alt,
    class: imageClassForAlign(align),
    'data-align': align,
    widthPercent: normalizeImageWidthPercent(widthPercent),
  }).run()
  queueSelectedImageFrameUpdate()
}

function insertImage(align = 'center') {
  rememberSelection()
  const url = window.prompt('Image URL')
  if (!url) return
  const alt = window.prompt('Alt text (optional)')
  insertImageWithAttributes(url, alt || '', align)
}

function setImageAlign(align) {
  if (!editor?.isActive('image')) {
    insertImage(align)
    return
  }
  focusAndRestoreSelection()
  editor.chain().updateAttributes('image', {
    class: imageClassForAlign(align),
    'data-align': align,
    widthPercent: currentImageWidthPercent.value,
  }).run()
  bumpToolbar()
  queueSelectedImageFrameUpdate()
}

function triggerImageUpload(align = 'center') {
  if (props.disabled || isUploadingImage.value) return
  rememberSelection()
  pendingImageAlign.value = align
  uploadError.value = ''
  uploadInput.value?.click()
}

function openMediaPicker(align = 'center') {
  if (props.disabled) return

  rememberSelection()
  pendingImageAlign.value = align
  uploadError.value = ''
  closeBubbleBlockMenu()
  closeColorPicker()
  isMediaPickerOpen.value = true
}

function handleMediaPickerSelection(media) {
  const url = mediaDisplayUrl(media)

  if (!url) {
    uploadError.value = 'Selected media does not have a usable image URL.'
    return
  }

  uploadError.value = ''
  isMediaPickerOpen.value = false
  insertImageWithAttributes(
    url,
    media?.alt_text || media?.original_filename || 'Selected image',
    pendingImageAlign.value
  )
}

async function uploadImage(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  isUploadingImage.value = true
  uploadError.value = ''

  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('collection', props.uploadCollection)
    formData.append('alt_text', file.name)

    const response = await fetch(route('media.upload'), {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: formData,
    })

    const data = await response.json()
    if (!response.ok || data.status !== 'ok') throw new Error(data.message || 'Image upload failed.')

    const media = data.payload?.media
    const url = mediaDisplayUrl(media)
    if (!url) throw new Error('Image uploaded, but no display URL was returned.')

    insertImageWithAttributes(url, media?.alt_text || file.name, pendingImageAlign.value)
  } catch (error) {
    uploadError.value = error?.message || 'Image upload failed.'
  } finally {
    isUploadingImage.value = false
  }
}

function insertLink() {
  rememberSelection()
  const currentHref = editor.getAttributes('link')?.href || ''
  const url = window.prompt('Link URL', currentHref)
  if (url === null) return

  focusAndRestoreSelection()
  if (url.trim() === '') {
    editor.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }
  editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

watch(() => props.disabled, disabled => editor?.setEditable(!disabled))

watch(
  () => props.modelValue,
  (val) => {
    if (!editor) return
    const next = normalizeIncomingHtml(val || '')
    if (editor.getHTML() !== next) {
      editor.commands.setContent(next, false)
      isEditorEmpty.value = editor.isEmpty
      bumpToolbar()
      queueSelectedImageFrameUpdate()
    }
  }
)

function handleWindowResize() {
  syncViewportState()
  queueSelectedImageFrameUpdate()
}

onMounted(() => {
  syncViewportState()
  loadStoredCustomColors()
  window.addEventListener('resize', handleWindowResize)
  window.addEventListener('scroll', refreshColorPickerPosition, true)
  document.addEventListener('mousedown', handleDocumentMouseDown)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleWindowResize)
  window.removeEventListener('scroll', refreshColorPickerPosition, true)
  document.removeEventListener('mousedown', handleDocumentMouseDown)
  stopImageResize()
  stopColorPickerDrag()
})

watch(isColorPickerAdvancedOpen, () => {
  if (!isColorPickerOpen.value) return
  nextTick(() => positionColorPicker())
})

watch(colorPickerDraftHex, value => {
  if (!isColorPickerOpen.value) return

  const normalizedDraft = normalizeHexColor(value)
  const normalizedInput = normalizeHexColor(colorPickerHexValue.value)

  if (!normalizedInput || normalizedInput === normalizedDraft) {
    colorPickerHexValue.value = String(value ?? '').toUpperCase()
  }
})

watch(colorPickerCustomColors, colors => {
  persistCustomColors(colors)
}, { deep: true })

onBeforeUnmount(() => editor?.destroy())
</script>

<template>
  <div class="hz-stack gap-2 rich-editor-shell">
    <input ref="uploadInput" type="file" accept="image/*" class="hidden" @change="uploadImage" />

    <div class="hz-surface-welcome rich-editor-toolbar" :class="{ 'rich-editor-toolbar--compact-open': isCompactToolbarExpanded }" :style="toolbarSurfaceStyle">
      <div class="rich-editor-toolbar-group rich-editor-toolbar-primary">
        <div class="rich-editor-toolbar-scroller">
          <div class="rich-editor-toolbar-section rich-editor-toolbar-section--select">
            <div class="rich-editor-tooltip" data-tooltip="Block style">
              <select class="hz-input rich-editor-toolbar-select rich-editor-toolbar-select--block" :disabled="disabled" :value="blockTypeValue" @mousedown.stop @change="applyBlockType($event.target.value)">
                <option value="p">Paragraph</option>
                <option value="h1">Heading 1</option>
                <option value="h2">Heading 2</option>
                <option value="h3">Heading 3</option>
                <option value="h4">Heading 4</option>
                <option value="h5">Heading 5</option>
                <option value="h6">Heading 6</option>
              </select>
            </div>
          </div>

          <div class="rich-editor-toolbar-section">
            <div class="rich-editor-tooltip" data-tooltip="Bold">
              <HorizonButton type="button" size="xs" :variant="isBoldActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleBold">B</HorizonButton>
            </div>
            <div class="rich-editor-tooltip" data-tooltip="Italic">
              <HorizonButton type="button" size="xs" :variant="isItalicActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleItalic">I</HorizonButton>
            </div>
            <div class="rich-editor-tooltip" data-tooltip="Underline">
              <HorizonButton type="button" size="xs" :variant="isUnderlineActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleUnderline">U</HorizonButton>
            </div>
          </div>

          <div class="rich-editor-toolbar-section">
            <div class="rich-editor-tooltip" data-tooltip="Bulleted list">
              <HorizonButton type="button" size="xs" :variant="isBulletListActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleUnorderedList">• List</HorizonButton>
            </div>
            <div class="rich-editor-tooltip" data-tooltip="Numbered list">
              <HorizonButton type="button" size="xs" :variant="isOrderedListActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleOrderedList">1. List</HorizonButton>
            </div>
            <div class="rich-editor-tooltip" data-tooltip="Insert or edit link">
              <HorizonButton type="button" size="xs" :variant="isLinkActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="insertLink">Link</HorizonButton>
            </div>
          </div>

          <div class="rich-editor-toolbar-section rich-editor-toolbar-section--media">
            <div class="rich-editor-tooltip" data-tooltip="Upload image">
              <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled || isUploadingImage" @mousedown.prevent @click="triggerImageUpload('center')">{{ isUploadingImage ? 'Uploading…' : 'Upload' }}</HorizonButton>
            </div>
            <div class="rich-editor-tooltip" data-tooltip="Choose an existing image from media">
              <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="openMediaPicker('center')">Choose Media</HorizonButton>
            </div>
          </div>

          <div class="rich-editor-toolbar-section">
            <div class="rich-editor-tooltip" data-tooltip="Undo">
              <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled || !canUndo" @mousedown.prevent @click="undo">Undo</HorizonButton>
            </div>
            <div class="rich-editor-tooltip" data-tooltip="Redo">
              <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled || !canRedo" @mousedown.prevent @click="redo">Redo</HorizonButton>
            </div>
          </div>
        </div>

        <div class="rich-editor-toolbar-section rich-editor-toolbar-mobile-toggle">
          <div class="rich-editor-tooltip" :data-tooltip="isCompactToolbarExpanded ? 'Hide advanced tools' : 'Show advanced tools'">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="toggleCompactToolbar">
              {{ isCompactToolbarExpanded ? 'Less' : 'More' }}
            </HorizonButton>
          </div>
        </div>
      </div>

      <div v-if="showAdvancedToolbar" class="rich-editor-toolbar-group rich-editor-toolbar-secondary">
        <div class="rich-editor-toolbar-section rich-editor-toolbar-section--select">
          <div class="rich-editor-tooltip" data-tooltip="Text alignment">
            <select class="hz-input rich-editor-toolbar-select rich-editor-toolbar-select--align" :disabled="disabled" :value="currentTextAlign" @mousedown.stop @change="setTextAlign($event.target.value)">
              <option value="left">Align Left</option>
              <option value="center">Align Center</option>
              <option value="right">Align Right</option>
              <option value="justify">Justify</option>
            </select>
          </div>

          <div class="rich-editor-tooltip" data-tooltip="Font family">
            <select class="hz-input rich-editor-toolbar-select rich-editor-toolbar-select--font" :disabled="disabled" :value="currentFontFamily" :style="{ fontFamily: currentFontFamilyPreview }" @mousedown.stop @change="applyFontFamily($event.target.value)">
              <option v-for="opt in fontFamilyOptions" :key="opt.value || '__default'" :value="opt.value" :style="{ fontFamily: opt.preview }">{{ opt.label }}</option>
            </select>
          </div>

          <div class="rich-editor-tooltip" data-tooltip="Font size">
            <select class="hz-input rich-editor-toolbar-select rich-editor-toolbar-select--size" :disabled="disabled" :value="currentFontSize" @mousedown.stop @change="applyFontSize($event.target.value)">
              <option v-for="opt in fontSizeOptions" :key="opt.value || '__default'" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>

        <div class="rich-editor-toolbar-section">
          <div class="rich-editor-tooltip" data-tooltip="Custom text color">
            <button type="button" class="rich-editor-color-picker-trigger" :disabled="disabled" @mousedown.prevent @click="openColorPicker('text', $event)">
              <span class="rich-editor-color-trigger-swatch" :style="{ backgroundColor: currentTextColorHex }"></span>
              <span>Text Color</span>
            </button>
          </div>

          <div class="rich-editor-tooltip" data-tooltip="Custom highlight color">
            <button type="button" class="rich-editor-color-picker-trigger" :disabled="disabled" @mousedown.prevent @click="openColorPicker('highlight', $event)">
              <span class="rich-editor-color-trigger-swatch" :style="{ backgroundColor: currentHighlightHex }"></span>
              <span>Highlight Color</span>
            </button>
          </div>

          <div class="rich-editor-tooltip" data-tooltip="Custom field color">
            <button type="button" class="rich-editor-color-picker-trigger" :disabled="disabled" @mousedown.prevent @click="openColorPicker('callout', $event)">
              <span class="rich-editor-color-trigger-swatch" :style="{ backgroundColor: currentCalloutHex }"></span>
              <span>Field Color</span>
            </button>
          </div>
        </div>

        <div class="rich-editor-toolbar-section">
          <div class="rich-editor-tooltip" data-tooltip="Clear custom text styling">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="clearTypography">Clear Type</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Remove highlight">
            <HorizonButton type="button" size="xs" :variant="isHighlightActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="applyHighlightColor('')">Clear Highlight</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Remove field">
            <HorizonButton type="button" size="xs" :variant="isCalloutActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="applyCustomCalloutColor('')">Clear Field</HorizonButton>
          </div>
        </div>

        <div class="rich-editor-toolbar-section">
          <div class="rich-editor-tooltip" data-tooltip="Strikethrough">
            <HorizonButton type="button" size="xs" :variant="isStrikeActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleStrike">S</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Block quote">
            <HorizonButton type="button" size="xs" :variant="isBlockquoteActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleBlockquote">Quote</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Horizontal divider">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="insertDivider">Divider</HorizonButton>
          </div>
        </div>

        <div class="rich-editor-toolbar-section rich-editor-toolbar-section--media">
          <div class="rich-editor-tooltip" data-tooltip="Insert image from URL">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="insertImage('center')">Image URL</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Upload image">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled || isUploadingImage" @mousedown.prevent @click="triggerImageUpload('center')">{{ isUploadingImage ? 'Uploading…' : 'Upload Image' }}</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Float image left">
            <HorizonButton type="button" size="xs" :variant="isImageActive && currentImageAlign === 'left' ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="setImageAlign('left')">Img Left</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Center image">
            <HorizonButton type="button" size="xs" :variant="isImageActive && currentImageAlign === 'center' ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="setImageAlign('center')">Img Center</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Float image right">
            <HorizonButton type="button" size="xs" :variant="isImageActive && currentImageAlign === 'right' ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="setImageAlign('right')">Img Right</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Image size preset">
            <select class="hz-input rich-editor-toolbar-select rich-editor-toolbar-select--image" :disabled="disabled || !isImageActive" :value="currentImageWidthSelectValue" @mousedown.stop @change="applyImageWidthPreset($event.target.value)">
              <option value="" disabled>Image Size</option>
              <option v-if="currentImageWidthSelectValue === '__custom'" value="__custom" disabled>Image: Custom ({{ currentImageWidthPercent }}%)</option>
              <option v-for="option in imageWidthPresetOptions" :key="option.value" :value="String(option.value)">{{ option.label }}</option>
            </select>
          </div>
        </div>

        <div class="rich-editor-toolbar-section">
          <div class="rich-editor-tooltip" data-tooltip="Insert table">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="insertTable">Table</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Add table row">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="addTableRow">+Row</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Add table column">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="addTableColumn">+Col</HorizonButton>
          </div>
          <div class="rich-editor-tooltip" data-tooltip="Delete table">
            <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="deleteTable">Del Tbl</HorizonButton>
          </div>
        </div>
      </div>
    </div>

    <MediaPickerModal
      :open="isMediaPickerOpen"
      :collection="uploadCollection"
      title="Choose Image from Media"
      :allow-upload="false"
      @close="isMediaPickerOpen = false"
      @selected="handleMediaPickerSelection"
    />

    <Teleport to="body">
      <div
        v-if="isColorPickerOpen"
        ref="colorPickerPanel"
        class="hz-popover-surface rich-editor-color-panel overflow-hidden rounded-xl"
        :style="colorPickerPanelStyle"
        @mousedown.stop
      >
        <div class="space-y-3 px-3 py-3">
          <div class="flex items-center gap-2">
            <div class="rich-editor-color-preview-chip rich-editor-color-preview-chip--small" :style="{ backgroundColor: colorPickerInitialHex }"></div>
            <div class="rich-editor-color-preview-chip rich-editor-color-preview-chip--small" :style="{ backgroundColor: colorPickerDraftHex }"></div>
            <input
              type="text"
              class="hz-input rich-editor-color-hex-input"
              :value="colorPickerHexValue"
              spellcheck="false"
              @mousedown.stop
              @input="applyColorPickerHexInput($event.target.value)"
              @blur="applyColorPickerHexInput($event.target.value)"
              @keydown.enter.prevent="confirmColorPickerHexInput"
            />
            <div class="min-w-0">
              <div class="text-[11px] font-semibold text-[var(--color-text-primary)]">{{ colorPickerPreviewLabel }} Color</div>
              <div class="truncate text-[11px] font-mono uppercase text-[var(--color-text-secondary)]">{{ colorPickerDraftHex }}</div>
            </div>
            <div class="ml-auto flex items-center gap-1">
              <HorizonButton type="button" size="xs" variant="ghost" @mousedown.prevent @click="closeColorPicker">Close</HorizonButton>
              <HorizonButton type="button" size="xs" variant="primary" @mousedown.prevent @click="confirmColorPicker">Done</HorizonButton>
            </div>
          </div>

          <div class="grid grid-cols-[minmax(0,1fr)_1rem] gap-2">
            <div
              ref="colorPickerField"
              class="rich-editor-color-field"
              :style="colorPickerFieldStyle"
              @mousedown="startColorPickerDrag($event, 'field')"
            >
              <div class="rich-editor-color-field-cursor" :style="colorPickerFieldCursorStyle"></div>
            </div>

            <div
              ref="colorPickerSlider"
              class="rich-editor-color-slider"
              @mousedown="startColorPickerDrag($event, 'slider')"
            >
              <div class="rich-editor-color-slider-cursor" :style="colorPickerSliderCursorStyle"></div>
            </div>
          </div>

          <div class="hz-stack-xs">
            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Preset Colors</div>
            <div class="rich-editor-color-swatch-grid rich-editor-color-swatch-grid--compact">
              <button
                v-for="hex in classicColorSwatches"
                :key="hex"
                type="button"
                class="rich-editor-color-swatch"
                :class="colorPickerDraftHex === hex ? 'rich-editor-color-swatch--active' : ''"
                :style="{ backgroundColor: hex }"
                @mousedown.prevent
                @click="selectClassicColor(hex)"
              ></button>
            </div>
          </div>

          <div class="border-t border-[color:var(--color-surface-border)] pt-2">
            <button
              type="button"
              class="rich-editor-color-advanced-toggle"
              @mousedown.prevent
              @click="isColorPickerAdvancedOpen = !isColorPickerAdvancedOpen"
            >
              <span>Advanced</span>
              <span class="text-[10px] text-[var(--color-text-secondary)]">{{ isColorPickerAdvancedOpen ? 'Hide' : 'Show' }}</span>
            </button>
          </div>

          <div v-if="isColorPickerAdvancedOpen" class="space-y-3">
            <div class="hz-stack-xs">
              <div class="flex items-center justify-between gap-2">
                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Saved Colors</div>
                <div class="flex items-center gap-1">
                  <HorizonButton
                    type="button"
                    size="xs"
                    variant="ghost"
                    :disabled="activeCustomColorIndex < 0 || !colorPickerCustomColors[activeCustomColorIndex]"
                    @mousedown.prevent
                    @click="clearSelectedCustomColor"
                  >
                    Clear
                  </HorizonButton>
                  <HorizonButton type="button" size="xs" variant="ghost" @mousedown.prevent @click="addCurrentColorToCustomColors">Save</HorizonButton>
                </div>
              </div>
              <div class="rich-editor-color-swatch-grid rich-editor-color-swatch-grid--custom">
                <button
                  v-for="(hex, index) in colorPickerCustomColors"
                  :key="`custom-${index}`"
                  type="button"
                  class="rich-editor-color-swatch"
                  :class="activeCustomColorIndex === index ? 'rich-editor-color-swatch--active' : ''"
                  :style="{ backgroundColor: hex || 'transparent' }"
                  @mousedown.prevent
                  @click="selectCustomColor(index)"
                >
                  <span v-if="!hex" class="rich-editor-color-swatch-empty"></span>
                </button>
              </div>
              <div v-if="activeCustomColorIndex !== -1" class="text-[11px] font-medium text-[var(--color-text-secondary)]">
                Save will update slot {{ activeCustomColorIndex + 1 }}.
              </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <label class="hz-stack-xs">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Hue</span>
                <input type="number" class="hz-input" min="0" max="359" :value="colorPickerHueInput" @mousedown.stop @input="applyColorPickerHueInput($event.target.value)" />
              </label>

              <label class="hz-stack-xs">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Sat</span>
                <input type="number" class="hz-input" min="0" max="240" :value="colorPickerSatInput" @mousedown.stop @input="applyColorPickerSatInput($event.target.value)" />
              </label>

              <label class="hz-stack-xs">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Lum</span>
                <input type="number" class="hz-input" min="0" max="240" :value="colorPickerLumInput" @mousedown.stop @input="applyColorPickerLumInput($event.target.value)" />
              </label>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <label class="hz-stack-xs">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Red</span>
                <input type="number" class="hz-input" min="0" max="255" :value="colorPickerDraftRgb.r" @mousedown.stop @input="applyColorPickerRgbChannel('r', $event.target.value)" />
              </label>

              <label class="hz-stack-xs">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Green</span>
                <input type="number" class="hz-input" min="0" max="255" :value="colorPickerDraftRgb.g" @mousedown.stop @input="applyColorPickerRgbChannel('g', $event.target.value)" />
              </label>

              <label class="hz-stack-xs">
                <span class="text-[11px] text-[var(--color-text-secondary)]">Blue</span>
                <input type="number" class="hz-input" min="0" max="255" :value="colorPickerDraftRgb.b" @mousedown.stop @input="applyColorPickerRgbChannel('b', $event.target.value)" />
              </label>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <p v-if="uploadError" class="text-xs text-red-300">{{ uploadError }}</p>

    <div ref="editorViewport" class="relative">
      <BubbleMenu
        v-if="editor"
        class="rich-editor-bubble-root"
        :editor="editor"
        :appendTo="bubbleMenuAppendTarget"
        :should-show="shouldShowTextBubbleMenu"
        :options="{ strategy: 'fixed', placement: 'top', offset: 12 }"
      >
        <div class="hz-surface-welcome rich-editor-bubble" :style="bubbleSurfaceStyle" @mousedown.stop>
          <div class="rich-editor-bubble-row">
            <button
              type="button"
              class="rich-editor-bubble-type-trigger rich-editor-bubble-tooltip"
              :aria-expanded="isBubbleBlockMenuOpen ? 'true' : 'false'"
              :data-tooltip="bubbleTooltipText('Block style')"
              @mousedown.prevent
              @click="toggleBubbleBlockMenu"
            >
              <span class="rich-editor-bubble-type-prefix">Aa</span>
              <span class="truncate">{{ currentBubbleBlockLabel }}</span>
              <span class="rich-editor-bubble-chevron" :class="isBubbleBlockMenuOpen ? 'is-open' : ''">›</span>
            </button>
          </div>

          <div class="rich-editor-bubble-row rich-editor-bubble-actions">
            <button type="button" class="rich-editor-bubble-action rich-editor-bubble-tooltip" :class="{ 'is-active': isBoldActive }" :data-tooltip="bubbleTooltipText('Bold', 'Mod-b')" aria-label="Bold" @mousedown.prevent @click="toggleBold">B</button>
            <button type="button" class="rich-editor-bubble-action rich-editor-bubble-action-italic rich-editor-bubble-tooltip" :class="{ 'is-active': isItalicActive }" :data-tooltip="bubbleTooltipText('Italic', 'Mod-i')" aria-label="Italic" @mousedown.prevent @click="toggleItalic">I</button>
            <button type="button" class="rich-editor-bubble-action rich-editor-bubble-action-underline rich-editor-bubble-tooltip" :class="{ 'is-active': isUnderlineActive }" :data-tooltip="bubbleTooltipText('Underline', 'Mod-u')" aria-label="Underline" @mousedown.prevent @click="toggleUnderline">U</button>
            <button type="button" class="rich-editor-bubble-action rich-editor-bubble-action-strike rich-editor-bubble-tooltip" :class="{ 'is-active': isStrikeActive }" :data-tooltip="bubbleTooltipText('Strikethrough', 'Mod-Shift-s')" aria-label="Strikethrough" @mousedown.prevent @click="toggleStrike">S</button>
            <button type="button" class="rich-editor-bubble-action rich-editor-bubble-tooltip" :class="{ 'is-active': isLinkActive }" :data-tooltip="bubbleTooltipText('Link')" aria-label="Link" @mousedown.prevent @click="insertLink">↗</button>
            <button type="button" class="rich-editor-bubble-action rich-editor-bubble-tooltip" :class="{ 'is-active': isBulletListActive }" :data-tooltip="bubbleTooltipText('Bulleted list', 'Mod-Shift-8')" aria-label="Bulleted list" @mousedown.prevent @click="toggleUnorderedList">•</button>
            <button type="button" class="rich-editor-bubble-action rich-editor-bubble-tooltip" :class="{ 'is-active': isOrderedListActive }" :data-tooltip="bubbleTooltipText('Numbered list', 'Mod-Shift-7')" aria-label="Numbered list" @mousedown.prevent @click="toggleOrderedList">1.</button>
            <button type="button" class="rich-editor-bubble-action rich-editor-bubble-tooltip" :class="{ 'is-active': isBlockquoteActive }" :data-tooltip="bubbleTooltipText('Quote', 'Mod-Shift-b')" aria-label="Quote" @mousedown.prevent @click="toggleBlockquote">"</button>
          </div>

          <div class="rich-editor-bubble-row rich-editor-bubble-select-row">
            <div class="rich-editor-bubble-select-wrap rich-editor-bubble-tooltip" :data-tooltip="bubbleTooltipTextList('Text alignment', ['Mod-Shift-l', 'Mod-Shift-e', 'Mod-Shift-r', 'Mod-Shift-j'])">
              <select
                class="rich-editor-bubble-select"
                :disabled="disabled"
                :value="currentTextAlign"
                @mousedown.stop="closeBubbleBlockMenu"
                @change="setBubbleTextAlign($event.target.value)"
              >
                <option v-for="opt in textAlignOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>

            <div class="rich-editor-bubble-select-wrap rich-editor-bubble-select-wrap-font rich-editor-bubble-tooltip" :data-tooltip="bubbleTooltipText('Font family')">
              <select
                class="rich-editor-bubble-select"
                :disabled="disabled"
                :value="currentFontFamily"
                :style="{ fontFamily: currentFontFamilyPreview }"
                @mousedown.stop="closeBubbleBlockMenu"
                @change="applyBubbleFontFamily($event.target.value)"
              >
                <option
                  v-for="opt in fontFamilyOptions"
                  :key="opt.value || '__default'"
                  :value="opt.value"
                  :style="{ fontFamily: opt.preview }"
                >
                  {{ opt.shortLabel }}
                </option>
              </select>
            </div>

            <div class="rich-editor-bubble-select-wrap rich-editor-bubble-tooltip" :data-tooltip="bubbleTooltipText('Font size')">
              <select
                class="rich-editor-bubble-select"
                :disabled="disabled"
                :value="currentFontSize"
                @mousedown.stop="closeBubbleBlockMenu"
                @change="applyBubbleFontSize($event.target.value)"
              >
                <option v-for="opt in fontSizeOptions" :key="opt.value || '__default'" :value="opt.value">{{ opt.shortLabel }}</option>
              </select>
            </div>
          </div>

          <div class="rich-editor-bubble-row rich-editor-bubble-color-row">
            <button
              type="button"
              class="rich-editor-bubble-color-trigger rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Text color')"
              @mousedown.prevent
              @click="openBubbleColorPicker('text', $event)"
            >
              <span class="rich-editor-bubble-color-swatch" :style="{ backgroundColor: currentTextColorHex }"></span>
              <span class="rich-editor-bubble-color-label">Text</span>
            </button>
            <button
              type="button"
              class="rich-editor-bubble-color-trigger rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Highlight color')"
              @mousedown.prevent
              @click="openBubbleColorPicker('highlight', $event)"
            >
              <span class="rich-editor-bubble-color-swatch" :style="{ backgroundColor: currentHighlightHex }"></span>
              <span class="rich-editor-bubble-color-label">Highlight</span>
            </button>
            <button
              type="button"
              class="rich-editor-bubble-color-trigger rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Field color')"
              @mousedown.prevent
              @click="openBubbleColorPicker('callout', $event)"
            >
              <span class="rich-editor-bubble-color-swatch" :style="{ backgroundColor: currentCalloutHex }"></span>
              <span class="rich-editor-bubble-color-label">Field</span>
            </button>
          </div>

          <div class="rich-editor-bubble-row rich-editor-bubble-color-clear-row">
            <button
              type="button"
              class="rich-editor-bubble-color-clear rich-editor-bubble-tooltip"
              :class="{ 'is-active': Boolean(currentTextColor || currentInlineTextColor) }"
              :data-tooltip="bubbleTooltipText('Clear text color')"
              @mousedown.prevent
              @click="clearBubbleTextColor"
            >
              Clear Text
            </button>
            <button
              type="button"
              class="rich-editor-bubble-color-clear rich-editor-bubble-tooltip"
              :class="{ 'is-active': isHighlightActive }"
              :data-tooltip="bubbleTooltipText('Clear highlight color')"
              @mousedown.prevent
              @click="clearBubbleHighlightColor"
            >
              Clear Highlight
            </button>
            <button
              type="button"
              class="rich-editor-bubble-color-clear rich-editor-bubble-tooltip"
              :class="{ 'is-active': isCalloutActive }"
              :data-tooltip="bubbleTooltipText('Clear field color')"
              @mousedown.prevent
              @click="clearBubbleFieldColor"
            >
              Clear Field
            </button>
          </div>

          <div class="rich-editor-bubble-row rich-editor-bubble-utility-row">
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Clear typography')"
              @mousedown.prevent
              @click="bubbleClearTypography"
            >
              Clear Type
            </button>
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Horizontal divider')"
              @mousedown.prevent
              @click="bubbleInsertDivider"
            >
              Divider
            </button>
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :disabled="disabled || !canUndo"
              :data-tooltip="bubbleTooltipText('Undo', 'Mod-z')"
              @mousedown.prevent
              @click="bubbleUndo"
            >
              Undo
            </button>
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :disabled="disabled || !canRedo"
              :data-tooltip="bubbleTooltipTextList('Redo', ['Shift-Mod-z', 'Mod-y'])"
              @mousedown.prevent
              @click="bubbleRedo"
            >
              Redo
            </button>
          </div>

          <div class="rich-editor-bubble-row rich-editor-bubble-utility-row">
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Insert image from URL')"
              @mousedown.prevent
              @click="bubbleInsertImageUrl"
            >
              Image URL
            </button>
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :disabled="disabled || isUploadingImage"
              :data-tooltip="bubbleTooltipText('Upload image')"
              @mousedown.prevent
              @click="bubbleTriggerImageUpload"
            >
              {{ isUploadingImage ? 'Uploading…' : 'Upload' }}
            </button>
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Choose image from media')"
              @mousedown.prevent
              @click="bubbleOpenMediaPicker"
            >
              Choose Media
            </button>
          </div>

          <div class="rich-editor-bubble-row rich-editor-bubble-utility-row">
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Insert table')"
              @mousedown.prevent
              @click="bubbleInsertTable"
            >
              Table
            </button>
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Add table row')"
              @mousedown.prevent
              @click="bubbleAddTableRow"
            >
              +Row
            </button>
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Add table column')"
              @mousedown.prevent
              @click="bubbleAddTableColumn"
            >
              +Col
            </button>
            <button
              type="button"
              class="rich-editor-bubble-pill rich-editor-bubble-tooltip"
              :data-tooltip="bubbleTooltipText('Delete table')"
              @mousedown.prevent
              @click="bubbleDeleteTable"
            >
              Del Tbl
            </button>
          </div>

          <div v-if="isBubbleBlockMenuOpen" class="hz-surface-welcome rich-editor-bubble-panel" :style="bubblePanelSurfaceStyle">
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': blockTypeValue === 'p' && !isBulletListActive && !isOrderedListActive && !isBlockquoteActive }" :data-tooltip="bubbleTooltipText('Normal text', 'Mod-Alt-0')" @mousedown.prevent @click="applyBubbleBlockType('p')">
              <span class="rich-editor-bubble-panel-icon">T</span>
              <span class="rich-editor-bubble-panel-label">Normal Text</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': blockTypeValue === 'h1' }" :data-tooltip="bubbleTooltipText('Heading 1', 'Mod-Alt-1')" @mousedown.prevent @click="applyBubbleBlockType('h1')">
              <span class="rich-editor-bubble-panel-icon">H1</span>
              <span class="rich-editor-bubble-panel-label">Heading 1</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': blockTypeValue === 'h2' }" :data-tooltip="bubbleTooltipText('Heading 2', 'Mod-Alt-2')" @mousedown.prevent @click="applyBubbleBlockType('h2')">
              <span class="rich-editor-bubble-panel-icon">H2</span>
              <span class="rich-editor-bubble-panel-label">Heading 2</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': blockTypeValue === 'h3' }" :data-tooltip="bubbleTooltipText('Heading 3', 'Mod-Alt-3')" @mousedown.prevent @click="applyBubbleBlockType('h3')">
              <span class="rich-editor-bubble-panel-icon">H3</span>
              <span class="rich-editor-bubble-panel-label">Heading 3</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': blockTypeValue === 'h4' }" :data-tooltip="bubbleTooltipText('Heading 4', 'Mod-Alt-4')" @mousedown.prevent @click="applyBubbleBlockType('h4')">
              <span class="rich-editor-bubble-panel-icon">H4</span>
              <span class="rich-editor-bubble-panel-label">Heading 4</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': blockTypeValue === 'h5' }" :data-tooltip="bubbleTooltipText('Heading 5', 'Mod-Alt-5')" @mousedown.prevent @click="applyBubbleBlockType('h5')">
              <span class="rich-editor-bubble-panel-icon">H5</span>
              <span class="rich-editor-bubble-panel-label">Heading 5</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': blockTypeValue === 'h6' }" :data-tooltip="bubbleTooltipText('Heading 6', 'Mod-Alt-6')" @mousedown.prevent @click="applyBubbleBlockType('h6')">
              <span class="rich-editor-bubble-panel-icon">H6</span>
              <span class="rich-editor-bubble-panel-label">Heading 6</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': isBulletListActive }" :data-tooltip="bubbleTooltipText('Bulleted list', 'Mod-Shift-8')" @mousedown.prevent @click="applyBubbleListType('bullet')">
              <span class="rich-editor-bubble-panel-icon">•</span>
              <span class="rich-editor-bubble-panel-label">Bulleted List</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': isOrderedListActive }" :data-tooltip="bubbleTooltipText('Numbered list', 'Mod-Shift-7')" @mousedown.prevent @click="applyBubbleListType('ordered')">
              <span class="rich-editor-bubble-panel-icon">1.</span>
              <span class="rich-editor-bubble-panel-label">Numbered List</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
            <button type="button" class="rich-editor-bubble-panel-item rich-editor-bubble-tooltip" :class="{ 'is-active': isBlockquoteActive }" :data-tooltip="bubbleTooltipText('Quote', 'Mod-Shift-b')" @mousedown.prevent @click="applyBubbleQuoteType">
              <span class="rich-editor-bubble-panel-icon">"</span>
              <span class="rich-editor-bubble-panel-label">Quote</span>
              <span class="rich-editor-bubble-panel-check">✓</span>
            </button>
          </div>
        </div>
      </BubbleMenu>

      <div v-if="showPlaceholder" class="pointer-events-none absolute left-3 top-3 text-horizon-muted">{{ placeholder }}</div>
      <EditorContent
        :editor="editor"
        class="hz-textarea hz-rte-content rich-editor-body"
        :class="disabled ? 'opacity-70 pointer-events-none' : ''"
        :style="{ minHeight: `${minHeightPx}px` }"
      />
      <div
        v-if="selectedImageFrame"
        class="rich-editor-image-frame"
        :style="{
          top: `${selectedImageFrame.top}px`,
          left: `${selectedImageFrame.left}px`,
          width: `${selectedImageFrame.width}px`,
          height: `${selectedImageFrame.height}px`,
        }"
      >
        <div class="rich-editor-image-size-chip">{{ currentImageWidthPercent }}%</div>
        <button
          type="button"
          class="rich-editor-image-handle rich-editor-image-handle-top"
          aria-label="Resize selected image from top"
          @mousedown="startImageResize($event, 'top')"
        ></button>
        <button
          type="button"
          class="rich-editor-image-handle rich-editor-image-handle-right"
          aria-label="Resize selected image from right"
          @mousedown="startImageResize($event, 'right')"
        ></button>
        <button
          type="button"
          class="rich-editor-image-handle rich-editor-image-handle-bottom"
          aria-label="Resize selected image from bottom"
          @mousedown="startImageResize($event, 'bottom')"
        ></button>
        <button
          type="button"
          class="rich-editor-image-handle rich-editor-image-handle-left"
          aria-label="Resize selected image from left"
          @mousedown="startImageResize($event, 'left')"
        ></button>
        <button
          type="button"
          class="rich-editor-image-handle rich-editor-image-handle-top-left"
          aria-label="Resize selected image from top left"
          @mousedown="startImageResize($event, 'top-left')"
        ></button>
        <button
          type="button"
          class="rich-editor-image-handle rich-editor-image-handle-top-right"
          aria-label="Resize selected image from top right"
          @mousedown="startImageResize($event, 'top-right')"
        ></button>
        <button
          type="button"
          class="rich-editor-image-handle rich-editor-image-handle-bottom-left"
          aria-label="Resize selected image from bottom left"
          @mousedown="startImageResize($event, 'bottom-left')"
        ></button>
        <button
          type="button"
          class="rich-editor-image-handle-rail"
          aria-label="Resize selected image"
          @mousedown="startImageResize($event, 'right')"
        ></button>
        <button
          type="button"
          class="rich-editor-image-handle-corner"
          aria-label="Resize selected image"
          @mousedown="startImageResize($event, 'bottom-right')"
        ></button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.rich-editor-shell {
  position: relative;
  --rich-editor-surface-base: rgb(10 18 34 / 0.97);
  --rich-editor-surface-base-strong: rgb(8 14 28 / 0.985);
  --rich-editor-surface: linear-gradient(180deg, color-mix(in srgb, var(--color-surface-accent) 58%, var(--rich-editor-surface-base) 42%), color-mix(in srgb, var(--color-surface-accent) 42%, var(--rich-editor-surface-base-strong) 58%));
  --rich-editor-surface-strong: linear-gradient(180deg, color-mix(in srgb, var(--color-surface-accent) 48%, var(--rich-editor-surface-base) 52%), color-mix(in srgb, var(--color-surface-accent) 34%, var(--rich-editor-surface-base-strong) 66%));
  --rich-editor-surface-soft: color-mix(in srgb, var(--color-surface-soft) 55%, rgb(10 18 34 / 0.95) 45%);
  --rich-editor-surface-active: color-mix(in srgb, var(--horizon-sunset-blue) 18%, var(--color-surface-accent));
  --rich-editor-border: color-mix(in srgb, var(--horizon-sunset-blue) 24%, rgb(255 255 255 / 0.055));
  --rich-editor-border-soft: color-mix(in srgb, var(--color-surface-border) 80%, transparent);
  --rich-editor-hover: rgb(255 255 255 / 0.055);
  --rich-editor-hover-strong: rgb(255 255 255 / 0.085);
  --rich-editor-text: var(--color-text-primary);
  --rich-editor-text-soft: color-mix(in srgb, var(--color-text-secondary) 92%, transparent);
  --rich-editor-text-muted: color-mix(in srgb, var(--color-text-secondary) 72%, transparent);
  --rich-editor-accent: var(--horizon-sunset-blue);
  --rich-editor-shadow: 0 18px 42px rgb(2 6 23 / 0.28);
  --rich-editor-shadow-soft: 0 14px 30px rgb(2 6 23 / 0.22);
}

.rich-editor-bubble-root {
  z-index: 80;
}

.rich-editor-bubble {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0;
  min-width: 18rem;
  padding: 0.35rem;
  border: 1px solid var(--rich-editor-border);
  border-radius: 0.95rem;
  background-color: var(--rich-editor-surface-base-strong);
  background-image: var(--rich-editor-surface-strong);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.04),
    var(--rich-editor-shadow-soft);
  backdrop-filter: blur(18px);
}

.rich-editor-bubble-row {
  display: flex;
  align-items: center;
  padding: 0.12rem;
}

.rich-editor-bubble-row + .rich-editor-bubble-row {
  border-top: 1px solid var(--rich-editor-border-soft);
}

.rich-editor-bubble-type-trigger {
  display: inline-flex;
  width: 100%;
  min-width: 0;
  align-items: center;
  gap: 0.6rem;
  padding: 0.42rem 0.55rem;
  border: 0;
  border-radius: 0.65rem;
  background: transparent;
  color: var(--rich-editor-text);
  font-size: 0.82rem;
  font-weight: 600;
  text-align: left;
  transition: background 140ms ease, color 140ms ease;
}

.rich-editor-bubble-type-trigger:hover {
  background: var(--rich-editor-hover);
}

.rich-editor-bubble-type-prefix {
  display: inline-flex;
  width: 1.35rem;
  justify-content: center;
  color: var(--rich-editor-text-muted);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.rich-editor-bubble-chevron {
  margin-left: auto;
  color: var(--rich-editor-text-muted);
  transition: transform 140ms ease;
}

.rich-editor-bubble-chevron.is-open {
  transform: rotate(90deg);
}

.rich-editor-bubble-actions {
  flex-wrap: wrap;
  gap: 0.15rem;
}

.rich-editor-bubble-select-row {
  gap: 0.25rem;
  flex-wrap: wrap;
}

.rich-editor-bubble-select-wrap {
  position: relative;
  min-width: 0;
  flex: 1 1 4.8rem;
}

.rich-editor-bubble-select-wrap-font {
  flex-basis: 6.25rem;
}

.rich-editor-bubble-select-wrap::after {
  content: '▾';
  position: absolute;
  top: 50%;
  right: 0.55rem;
  color: var(--rich-editor-text-muted);
  font-size: 0.66rem;
  transform: translateY(-50%);
  pointer-events: none;
}

.rich-editor-bubble-select {
  width: 100%;
  min-width: 0;
  appearance: none;
  padding: 0.48rem 1.45rem 0.48rem 0.55rem;
  border: 0;
  border-radius: 0.6rem;
  background: var(--rich-editor-surface-soft);
  color: var(--rich-editor-text);
  font-size: 0.74rem;
  font-weight: 600;
  line-height: 1.2;
  transition: background 140ms ease, color 140ms ease;
}

.rich-editor-bubble-select:hover:not(:disabled),
.rich-editor-bubble-select:focus {
  background: var(--rich-editor-hover);
  outline: none;
}

.rich-editor-bubble-select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.rich-editor-bubble-color-row {
  gap: 0.2rem;
}

.rich-editor-bubble-color-clear-row {
  gap: 0.2rem;
}

.rich-editor-bubble-utility-row {
  flex-wrap: wrap;
  gap: 0.2rem;
}

.rich-editor-bubble-action {
  display: inline-flex;
  min-width: 2.1rem;
  height: 2.1rem;
  align-items: center;
  justify-content: center;
  padding: 0 0.45rem;
  border: 0;
  border-radius: 0.6rem;
  background: transparent;
  color: var(--rich-editor-text-soft);
  font-size: 0.84rem;
  font-weight: 700;
  transition: background 140ms ease, color 140ms ease;
}

.rich-editor-bubble-action:hover {
  background: var(--rich-editor-hover);
}

.rich-editor-bubble-action.is-active {
  background: var(--rich-editor-surface-active);
  color: var(--rich-editor-accent);
}

.rich-editor-bubble-action-italic {
  font-style: italic;
}

.rich-editor-bubble-action-underline {
  text-decoration: underline;
  text-underline-offset: 0.16em;
}

.rich-editor-bubble-action-strike {
  text-decoration: line-through;
}

.rich-editor-bubble-color-trigger {
  display: inline-flex;
  min-width: 0;
  flex: 1 1 0;
  align-items: center;
  gap: 0.45rem;
  padding: 0.48rem 0.55rem;
  border: 0;
  border-radius: 0.6rem;
  background: transparent;
  color: var(--rich-editor-text-soft);
  font-size: 0.76rem;
  font-weight: 600;
  transition: background 140ms ease, color 140ms ease;
}

.rich-editor-bubble-color-trigger:hover {
  background: var(--rich-editor-hover);
}

.rich-editor-bubble-color-swatch {
  width: 0.8rem;
  height: 0.8rem;
  flex: 0 0 0.8rem;
  border: 1px solid rgb(255 255 255 / 0.14);
  border-radius: 999px;
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.12);
}

.rich-editor-bubble-color-label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rich-editor-bubble-color-clear {
  display: inline-flex;
  min-width: 0;
  flex: 1 1 0;
  align-items: center;
  justify-content: center;
  padding: 0.36rem 0.45rem;
  border: 0;
  border-radius: 0.55rem;
  background: var(--rich-editor-surface-soft);
  color: var(--rich-editor-text-soft);
  font-size: 0.68rem;
  font-weight: 600;
  line-height: 1.1;
  transition: background 140ms ease, color 140ms ease;
}

.rich-editor-bubble-color-clear.is-active {
  background: var(--rich-editor-surface-active);
  color: var(--rich-editor-text);
}

.rich-editor-bubble-color-clear:hover {
  background: var(--rich-editor-hover);
  color: var(--rich-editor-text);
}

.rich-editor-bubble-pill {
  display: inline-flex;
  min-width: 0;
  flex: 1 1 0;
  align-items: center;
  justify-content: center;
  padding: 0.42rem 0.52rem;
  border: 0;
  border-radius: 0.58rem;
  background: var(--rich-editor-surface-soft);
  color: var(--rich-editor-text-soft);
  font-size: 0.7rem;
  font-weight: 600;
  line-height: 1.1;
  transition: background 140ms ease, color 140ms ease;
}

.rich-editor-bubble-pill:hover:not(:disabled) {
  background: var(--rich-editor-hover);
  color: var(--rich-editor-text);
}

.rich-editor-bubble-pill:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.rich-editor-bubble-panel {
  position: absolute;
  top: 0;
  left: calc(100% + 0.45rem);
  display: flex;
  min-width: 13rem;
  max-height: 22rem;
  overflow-y: auto;
  flex-direction: column;
  gap: 0.08rem;
  padding: 0.35rem;
  border: 1px solid var(--rich-editor-border);
  border-radius: 0.95rem;
  background-color: var(--rich-editor-surface-base-strong);
  background-image: var(--rich-editor-surface-strong);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.04),
    var(--rich-editor-shadow-soft);
}

.rich-editor-bubble-panel-item {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.65rem;
  justify-content: flex-start;
  padding: 0.5rem 0.62rem;
  border: 0;
  border-radius: 0.65rem;
  background: transparent;
  color: var(--rich-editor-text);
  font-size: 0.82rem;
  font-weight: 500;
  text-align: left;
  transition: background 140ms ease, color 140ms ease;
}

.rich-editor-bubble-panel-item:hover {
  background: var(--rich-editor-hover);
}

.rich-editor-bubble-panel-item.is-active {
  background: var(--rich-editor-surface-active);
  color: var(--rich-editor-text);
}

.rich-editor-bubble-panel-icon {
  display: inline-flex;
  width: 1.5rem;
  flex: 0 0 1.5rem;
  align-items: center;
  justify-content: center;
  color: var(--rich-editor-text-muted);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.rich-editor-bubble-panel-label {
  min-width: 0;
  flex: 1 1 auto;
}

.rich-editor-bubble-panel-check {
  opacity: 0;
  color: var(--rich-editor-accent);
  font-size: 0.78rem;
  font-weight: 700;
}

.rich-editor-bubble-panel-item.is-active .rich-editor-bubble-panel-check {
  opacity: 1;
}

.rich-editor-bubble-panel-item.is-active .rich-editor-bubble-panel-icon {
  color: var(--rich-editor-accent);
}

.rich-editor-toolbar {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.65rem;
  padding: 0.8rem;
  border: 1px solid var(--rich-editor-border);
  border-radius: 1.05rem;
  background-color: var(--rich-editor-surface-base);
  background-image: var(--rich-editor-surface);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.04),
    var(--rich-editor-shadow);
  backdrop-filter: blur(16px);
}

.rich-editor-toolbar-group {
  display: flex;
  width: 100%;
  min-width: 0;
  align-items: flex-start;
  gap: 0.55rem;
}

.rich-editor-toolbar-primary {
  justify-content: space-between;
}

.rich-editor-toolbar-scroller {
  display: flex;
  min-width: 0;
  flex: 1 1 auto;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.55rem;
}

.rich-editor-toolbar-section.rich-editor-toolbar-mobile-toggle {
  display: inline-flex;
  flex: 0 0 auto;
  margin-left: auto;
}

.rich-editor-toolbar-section {
  display: inline-flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  padding: 0.34rem;
  border: 1px solid var(--rich-editor-border-soft);
  border-radius: 0.95rem;
  background: var(--rich-editor-surface-soft);
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.03);
}

.rich-editor-toolbar-section--select {
  gap: 0.45rem;
}

.rich-editor-toolbar-section--media {
  background: color-mix(in srgb, var(--horizon-sunset-blue) 12%, var(--color-surface-accent));
  border-color: var(--rich-editor-border);
}

.rich-editor-toolbar-select {
  min-height: 2.2rem;
  padding: 0.35rem 0.62rem;
  border-color: var(--rich-editor-border-soft);
  border-radius: 0.8rem;
  background: var(--rich-editor-surface-soft);
  color: var(--rich-editor-text);
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.03);
}

.rich-editor-toolbar-select--block {
  min-width: 8.75rem;
}

.rich-editor-toolbar-select--align {
  min-width: 8.2rem;
}

.rich-editor-toolbar-select--font {
  min-width: 8.2rem;
}

.rich-editor-toolbar-select--size {
  min-width: 7rem;
}

.rich-editor-toolbar-select--image {
  min-width: 10rem;
}

.rich-editor-tooltip {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.rich-editor-bubble-tooltip {
  position: relative;
}

.rich-editor-toolbar :deep(.hz-btn) {
  min-height: 2.2rem;
  border-radius: 0.8rem;
}

.rich-editor-tooltip::before,
.rich-editor-tooltip::after,
.rich-editor-bubble-tooltip::before,
.rich-editor-bubble-tooltip::after {
  position: absolute;
  left: 50%;
  opacity: 0;
  pointer-events: none;
  transition: opacity 140ms ease, transform 140ms ease;
  z-index: 30;
}

.rich-editor-tooltip::before {
  content: '';
  bottom: calc(100% + 0.15rem);
  transform: translateX(-50%) translateY(0.35rem);
  border-width: 0.35rem 0.35rem 0;
  border-style: solid;
  border-color: var(--rich-editor-surface-strong) transparent transparent;
}

.rich-editor-bubble-tooltip::before {
  content: '';
  bottom: calc(100% + 0.15rem);
  transform: translateX(-50%) translateY(0.35rem);
  border-width: 0.35rem 0.35rem 0;
  border-style: solid;
  border-color: var(--rich-editor-surface-strong) transparent transparent;
}

.rich-editor-tooltip::after {
  content: attr(data-tooltip);
  bottom: calc(100% + 0.45rem);
  transform: translateX(-50%) translateY(0.35rem);
  width: max-content;
  max-width: 13rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid var(--rich-editor-border);
  border-radius: 0.75rem;
  background-color: var(--rich-editor-surface-base-strong);
  background-image: var(--rich-editor-surface-strong);
  color: var(--rich-editor-text);
  font-size: 0.72rem;
  font-weight: 600;
  line-height: 1.25;
  text-align: center;
  white-space: normal;
  box-shadow: var(--rich-editor-shadow-soft);
}

.rich-editor-bubble-tooltip::after {
  content: attr(data-tooltip);
  bottom: calc(100% + 0.45rem);
  transform: translateX(-50%) translateY(0.35rem);
  width: max-content;
  max-width: 14rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid var(--rich-editor-border);
  border-radius: 0.75rem;
  background-color: var(--rich-editor-surface-base-strong);
  background-image: var(--rich-editor-surface-strong);
  color: var(--rich-editor-text);
  font-size: 0.72rem;
  font-weight: 600;
  line-height: 1.25;
  text-align: center;
  white-space: normal;
  box-shadow: var(--rich-editor-shadow-soft);
  z-index: 120;
}

.rich-editor-tooltip:hover::before,
.rich-editor-tooltip:hover::after,
.rich-editor-tooltip:focus-within::before,
.rich-editor-tooltip:focus-within::after,
.rich-editor-bubble-tooltip:hover::before,
.rich-editor-bubble-tooltip:hover::after,
.rich-editor-bubble-tooltip:focus-within::before,
.rich-editor-bubble-tooltip:focus-within::after,
.rich-editor-bubble-tooltip:focus-visible::before,
.rich-editor-bubble-tooltip:focus-visible::after {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

.rich-editor-color-picker-trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  min-width: 8.9rem;
  min-height: 2.2rem;
  padding: 0.35rem 0.7rem;
  border: 1px solid var(--color-surface-border);
  border-radius: 0.8rem;
  background: var(--color-panel-surface);
  color: var(--color-text-primary);
  font-size: 0.78rem;
  font-weight: 600;
  transition: background 140ms ease, border-color 140ms ease, transform 140ms ease;
}

.rich-editor-color-picker-trigger:hover:not(:disabled) {
  background: var(--color-panel-open);
  border-color: color-mix(in srgb, var(--color-text-primary) 18%, transparent);
}

.rich-editor-color-picker-trigger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.rich-editor-color-trigger-swatch {
  width: 1rem;
  height: 1rem;
  border: 1px solid rgb(255 255 255 / 0.16);
  border-radius: 999px;
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.18);
}

.rich-editor-color-panel {
  border: 1px solid var(--rich-editor-border);
  background-color: var(--rich-editor-surface-base-strong);
  background-image: var(--rich-editor-surface-strong);
  box-shadow: var(--rich-editor-shadow);
  backdrop-filter: blur(18px);
}

.rich-editor-color-swatch-grid {
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 0.4rem;
}

.rich-editor-color-swatch-grid--compact {
  grid-template-columns: repeat(8, minmax(0, 1fr));
}

.rich-editor-color-swatch-grid--custom {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.rich-editor-color-swatch {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  border: 1px solid var(--color-surface-border);
  border-radius: 0.65rem;
  background: var(--color-panel-deep-surface);
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.12);
  transition: transform 140ms ease, border-color 140ms ease, box-shadow 140ms ease;
}

.rich-editor-color-swatch:hover {
  transform: translateY(-1px);
  border-color: color-mix(in srgb, var(--color-text-primary) 18%, transparent);
}

.rich-editor-color-swatch--active {
  border-color: var(--horizon-sunset-blue);
  box-shadow:
    0 0 0 2px color-mix(in srgb, var(--horizon-sunset-blue) 72%, transparent),
    inset 0 0 0 2px rgb(255 255 255 / 0.9),
    0 8px 18px rgb(37 99 235 / 0.22);
}

.rich-editor-color-swatch--active::after {
  content: '';
  position: absolute;
  top: 0.28rem;
  right: 0.28rem;
  width: 0.52rem;
  height: 0.52rem;
  border-radius: 999px;
  background: rgb(255 255 255 / 0.96);
  box-shadow:
    0 0 0 2px color-mix(in srgb, var(--horizon-sunset-blue) 82%, transparent),
    0 1px 4px rgb(15 23 42 / 0.32);
}

.rich-editor-color-swatch-empty {
  position: absolute;
  inset: 0.32rem;
  border: 1px dashed color-mix(in srgb, var(--color-text-secondary) 45%, transparent);
  border-radius: 0.45rem;
}

.rich-editor-color-field {
  position: relative;
  min-height: 10rem;
  border: 1px solid var(--color-surface-border);
  border-radius: 1rem;
  overflow: hidden;
  cursor: crosshair;
}

.rich-editor-color-slider {
  position: relative;
  min-height: 10rem;
  border: 1px solid var(--color-surface-border);
  border-radius: 999px;
  background: linear-gradient(
    to bottom,
    #ff0000 0%,
    #ffff00 17%,
    #00ff00 33%,
    #00ffff 50%,
    #0000ff 67%,
    #ff00ff 83%,
    #ff0000 100%
  );
  overflow: hidden;
  cursor: ns-resize;
}

.rich-editor-color-field-cursor,
.rich-editor-color-slider-cursor {
  position: absolute;
  pointer-events: none;
}

.rich-editor-color-field-cursor {
  width: 1rem;
  height: 1rem;
  border: 2px solid rgb(255 255 255 / 0.95);
  border-radius: 999px;
  box-shadow: 0 0 0 1px rgb(0 0 0 / 0.3);
  transform: translate(-50%, -50%);
}

.rich-editor-color-slider-cursor {
  left: 50%;
  width: calc(100% + 0.4rem);
  height: 0.55rem;
  border: 2px solid rgb(255 255 255 / 0.95);
  border-radius: 999px;
  box-shadow: 0 0 0 1px rgb(0 0 0 / 0.3);
  transform: translate(-50%, -50%);
}

.rich-editor-color-preview-stack {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.rich-editor-color-preview-chip {
  height: 3rem;
  border: 1px solid var(--color-surface-border);
  border-radius: 0.85rem;
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.12);
}

.rich-editor-color-preview-chip--small {
  width: 2rem;
  height: 2rem;
  border-radius: 0.65rem;
}

.rich-editor-color-hex-input {
  width: 6.9rem;
  min-width: 6.9rem;
  padding-inline: 0.55rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  text-transform: uppercase;
}

.rich-editor-color-advanced-toggle {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  color: var(--color-text-primary);
  font-size: 0.78rem;
  font-weight: 600;
}

@media (max-width: 767px) {
  .rich-editor-bubble {
    width: min(calc(100vw - 1rem), 21rem);
    min-width: min(calc(100vw - 1rem), 17rem);
    max-width: calc(100vw - 1rem);
  }

  .rich-editor-bubble-panel {
    top: calc(100% + 0.45rem);
    left: 0;
    right: auto;
    width: 100%;
    min-width: 0;
    max-width: 100%;
  }

  .rich-editor-toolbar {
    top: 0.5rem;
    gap: 0.55rem;
    padding: 0.55rem;
    border-radius: 0.9rem;
  }

  .rich-editor-toolbar-primary {
    flex-direction: column;
    align-items: stretch;
    gap: 0.45rem;
  }

  .rich-editor-toolbar-scroller {
    display: flex;
    min-width: 0;
    width: 100%;
    flex: 1 1 100%;
    align-items: stretch;
    gap: 0.45rem;
    overflow: visible;
    flex-wrap: wrap;
    padding-bottom: 0;
  }

  .rich-editor-toolbar-scroller::-webkit-scrollbar,
  .rich-editor-toolbar-secondary::-webkit-scrollbar {
    display: none;
  }

  .rich-editor-toolbar-secondary {
    display: flex;
    width: 100%;
    gap: 0.45rem;
    align-items: stretch;
    overflow: visible;
    flex-wrap: wrap;
    padding-top: 0.55rem;
    border-top: 1px solid rgb(255 255 255 / 0.08);
  }

  .rich-editor-toolbar-section {
    width: 100%;
    flex: 1 1 100%;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 0.3rem;
    padding: 0.3rem;
  }

  .rich-editor-toolbar-section.rich-editor-toolbar-mobile-toggle {
    display: flex;
    width: 100%;
    justify-content: flex-end;
    flex: 0 0 auto;
  }

  .rich-editor-toolbar-section--select {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
  }

  .rich-editor-toolbar-select,
  .rich-editor-toolbar-select--block,
  .rich-editor-toolbar-select--align,
  .rich-editor-toolbar-select--font,
  .rich-editor-toolbar-select--size,
  .rich-editor-toolbar-select--image {
    width: 100%;
    min-width: 0;
  }

  .rich-editor-toolbar :deep(.hz-btn) {
    min-height: 2.05rem;
    padding-inline: 0.62rem;
    font-size: 0.72rem;
  }

  .rich-editor-color-picker-trigger {
    width: 100%;
    min-width: 0;
    justify-content: flex-start;
  }

  .rich-editor-bubble-row {
    flex-wrap: wrap;
  }

  .rich-editor-bubble-actions {
    gap: 0.18rem;
  }

  .rich-editor-bubble-action {
    min-width: 0;
    flex: 1 1 calc(25% - 0.18rem);
  }

  .rich-editor-bubble-select-row {
    gap: 0.25rem;
  }

  .rich-editor-bubble-select-wrap,
  .rich-editor-bubble-select-wrap-font {
    flex: 1 1 100%;
  }

  .rich-editor-bubble-color-trigger,
  .rich-editor-bubble-color-clear,
  .rich-editor-bubble-pill {
    flex: 1 1 calc(50% - 0.2rem);
  }

  .rich-editor-bubble-color-row,
  .rich-editor-bubble-color-clear-row,
  .rich-editor-bubble-utility-row {
    gap: 0.18rem;
  }

  .rich-editor-tooltip::before,
  .rich-editor-tooltip::after,
  .rich-editor-bubble-tooltip::before,
  .rich-editor-bubble-tooltip::after {
    display: none;
  }

  .rich-editor-color-panel {
    max-width: calc(100vw - 24px);
    width: min(22rem, calc(100vw - 24px));
  }

  .rich-editor-color-picker-trigger {
    min-width: 0;
  }

  .rich-editor-color-panel .flex.items-center.gap-2 {
    flex-wrap: wrap;
    align-items: flex-start;
  }

  .rich-editor-color-panel .ml-auto.flex.items-center.gap-1 {
    margin-left: 0;
    width: 100%;
    justify-content: flex-end;
  }

  .rich-editor-color-hex-input {
    width: 100%;
    min-width: 0;
    flex: 1 1 8rem;
  }

  .rich-editor-color-swatch-grid {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }

  .rich-editor-color-swatch-grid--compact {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }

  .rich-editor-color-swatch-grid--custom {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .rich-editor-color-field,
  .rich-editor-color-slider {
    min-height: 8.5rem;
  }
}

.rich-editor-image-frame {
  position: absolute;
  border: 1px solid rgb(96 165 250 / 0.85);
  border-radius: 1rem;
  box-shadow: 0 0 0 1px rgb(15 23 42 / 0.7), 0 12px 26px rgb(2 6 23 / 0.22);
  pointer-events: none;
  z-index: 15;
}

.rich-editor-image-size-chip {
  position: absolute;
  top: -0.85rem;
  left: 0.65rem;
  padding: 0.22rem 0.45rem;
  border: 1px solid var(--rich-editor-border);
  border-radius: 999px;
  background-color: var(--rich-editor-surface-base-strong);
  background-image: var(--rich-editor-surface-strong);
  color: var(--rich-editor-text);
  font-size: 0.68rem;
  font-weight: 700;
  line-height: 1;
}

.rich-editor-image-handle-rail {
  position: absolute;
  top: 0.2rem;
  right: -0.85rem;
  bottom: 0.2rem;
  width: 1.7rem;
  border: 0;
  background: transparent;
  cursor: ew-resize;
  pointer-events: auto;
}

.rich-editor-image-handle {
  position: absolute;
  border: 0;
  background: transparent;
  pointer-events: auto;
}

.rich-editor-image-handle-top {
  top: -0.8rem;
  left: 0.8rem;
  right: 0.8rem;
  height: 1.6rem;
  cursor: ns-resize;
}

.rich-editor-image-handle-right {
  top: 0.8rem;
  right: -0.8rem;
  bottom: 0.8rem;
  width: 1.6rem;
  cursor: ew-resize;
}

.rich-editor-image-handle-bottom {
  left: 0.8rem;
  right: 0.8rem;
  bottom: -0.8rem;
  height: 1.6rem;
  cursor: ns-resize;
}

.rich-editor-image-handle-left {
  top: 0.8rem;
  left: -0.8rem;
  bottom: 0.8rem;
  width: 1.6rem;
  cursor: ew-resize;
}

.rich-editor-image-handle-top-left,
.rich-editor-image-handle-top-right,
.rich-editor-image-handle-bottom-left {
  width: 1.45rem;
  height: 1.45rem;
  border-radius: 999px;
}

.rich-editor-image-handle-top-left {
  top: -0.7rem;
  left: -0.7rem;
  cursor: nwse-resize;
}

.rich-editor-image-handle-top-right {
  top: -0.7rem;
  right: -0.7rem;
  cursor: nesw-resize;
}

.rich-editor-image-handle-bottom-left {
  bottom: -0.7rem;
  left: -0.7rem;
  cursor: nesw-resize;
}

.rich-editor-image-handle-corner {
  position: absolute;
  right: -0.55rem;
  bottom: -0.55rem;
  width: 1.2rem;
  height: 1.2rem;
  border: 1px solid rgb(255 255 255 / 0.12);
  border-radius: 999px;
  background: linear-gradient(135deg, rgb(96 165 250 / 1), rgb(59 130 246 / 0.92));
  box-shadow: 0 4px 14px rgb(37 99 235 / 0.45);
  cursor: ew-resize;
  pointer-events: auto;
}

.rich-editor-body :deep(img.ProseMirror-selectednode) {
  outline: 2px solid rgb(96 165 250 / 0.85);
  outline-offset: 2px;
}

.rich-editor-body :deep(.ProseMirror) {
  min-height: inherit;
  background: transparent;
}

.rich-editor-body :deep(.hz-rte-callout) {
  border-radius: 0 !important;
}

.rich-editor-body :deep(p::after) {
  content: '';
  display: block;
  clear: both;
}
</style>
