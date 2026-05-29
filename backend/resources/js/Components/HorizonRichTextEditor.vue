<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import HorizonButton from '@/Components/HorizonButton.vue'
import { Node, mergeAttributes } from '@tiptap/core'
import { Editor, EditorContent } from '@tiptap/vue-3'
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
          ? `border-color: color-mix(in srgb, ${customColor} 45%, transparent); background: color-mix(in srgb, ${customColor} 14%, rgb(27 32 53 / 1));`
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
const uploadError = ref('')

function bumpToolbar() {
  toolbarTick.value += 1
}

function rememberSelection() {
  if (!editor) return
  lastSelection.value = {
    from: editor.state.selection.from,
    to: editor.state.selection.to,
  }
}

function restoreSelection() {
  if (!editor || !lastSelection.value) return
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

const fontFamilyOptions = [
  { value: '', label: 'Font: Default', preview: '' },
  { value: 'hz-rte-font-system', label: 'Font: System', preview: 'system-ui' },
  { value: 'hz-rte-font-sans', label: 'Font: Sans', preview: 'sans-serif' },
  { value: 'hz-rte-font-arial', label: 'Font: Arial', preview: 'Arial, sans-serif' },
  { value: 'hz-rte-font-verdana', label: 'Font: Verdana', preview: 'Verdana, sans-serif' },
  { value: 'hz-rte-font-georgia', label: 'Font: Georgia', preview: 'Georgia, serif' },
  { value: 'hz-rte-font-mono', label: 'Font: Mono', preview: 'monospace' },
]

const fontSizeOptions = [
  { value: '', label: 'Size: Default' },
  { value: 'hz-rte-size-12', label: '12px' },
  { value: 'hz-rte-size-14', label: '14px' },
  { value: 'hz-rte-size-16', label: '16px' },
  { value: 'hz-rte-size-18', label: '18px' },
  { value: 'hz-rte-size-20', label: '20px' },
  { value: 'hz-rte-size-24', label: '24px' },
  { value: 'hz-rte-size-32', label: '32px' },
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

const highlightColorOptions = [
  { value: '', label: 'Highlight: None', hex: '#1e293b' },
  { value: '#60a5fa', label: 'Highlight Blue', hex: '#60a5fa' },
  { value: '#67e8f9', label: 'Highlight Cyan', hex: '#67e8f9' },
  { value: '#e879f9', label: 'Highlight Magenta', hex: '#e879f9' },
  { value: '#ff8db4', label: 'Highlight Pink', hex: '#ff8db4' },
  { value: '#fdba74', label: 'Highlight Orange', hex: '#fdba74' },
  { value: '#86efac', label: 'Highlight Green', hex: '#86efac' },
  { value: '#fca5a5', label: 'Highlight Red', hex: '#fca5a5' },
  { value: '#fde047', label: 'Highlight Yellow', hex: '#fde047' },
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
const textColorHexLookup = new Map(textColorOptions.filter(option => option.value).map(option => [option.value, option.hex]))
const highlightColorValueLookup = new Set(highlightColorOptions.filter(option => option.value).map(option => option.value))
const calloutToneHexLookup = new Map(calloutToneOptions.filter(option => option.value).map(option => [option.value, option.hex]))

const currentFontFamilyPreview = computed(() => {
  const match = fontFamilyOptions.find(option => option.value === currentFontFamily.value)
  return match?.preview || ''
})

const currentTextColorSelectValue = computed(() => {
  if (currentTextColor.value) return currentTextColor.value
  return currentInlineTextColor.value ? '__custom' : ''
})

const currentTextColorHex = computed(() => currentInlineTextColor.value || textColorHexLookup.get(currentTextColor.value) || defaultTextColorHex)

const currentHighlightSelectValue = computed(() => {
  if (!currentHighlightColor.value) return ''
  return highlightColorValueLookup.has(currentHighlightColor.value) ? currentHighlightColor.value : '__custom'
})

const currentHighlightHex = computed(() => currentHighlightColor.value || defaultHighlightHex)

const currentCalloutSelectValue = computed(() => {
  if (!isCalloutActive.value) return ''
  if (currentCalloutCustomColor.value) return '__custom'
  return currentCalloutTone.value || ''
})

const currentCalloutHex = computed(() => currentCalloutCustomColor.value || calloutToneHexLookup.get(currentCalloutTone.value) || defaultCalloutHex)

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
  },
  onSelectionUpdate: () => {
    rememberSelection()
    bumpToolbar()
  },
  onFocus: () => {
    isFocused.value = true
    rememberSelection()
    bumpToolbar()
  },
  onBlur: ({ editor }) => {
    isFocused.value = false
    isEditorEmpty.value = editor.isEmpty
    rememberSelection()
    bumpToolbar()
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

function setTextAlign(value) {
  focusAndRestoreSelection()
  editor.chain().setTextAlign(value).run()
}

function applyTextColor(value) {
  if (value === '__custom') return
  focusAndRestoreSelection()
  if (!value) {
    editor.chain().unsetColor().unsetRteColor().run()
    return
  }
  editor.chain().unsetColor().setRteColor(value).run()
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

function applyCalloutTone(value) {
  if (value === '__custom') return

  focusAndRestoreSelection()

  if (!value) {
    if (editor.isActive('calloutBox')) {
      editor.chain().unsetCallout().run()
    }
    return
  }

  if (editor.isActive('calloutBox')) {
    editor.chain().updateAttributes('calloutBox', { tone: value, customColor: null }).run()
    return
  }

  editor.chain().setCallout({ tone: value, customColor: null }).run()
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

function insertImageWithAttributes(src, alt = '', align = 'center') {
  focusAndRestoreSelection()
  editor.chain().setImage({ src, alt, class: imageClassForAlign(align), 'data-align': align }).run()
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
  editor.chain().updateAttributes('image', { class: imageClassForAlign(align), 'data-align': align }).run()
  bumpToolbar()
}

function triggerImageUpload(align = 'center') {
  if (props.disabled || isUploadingImage.value) return
  rememberSelection()
  pendingImageAlign.value = align
  uploadError.value = ''
  uploadInput.value?.click()
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
    }
  }
)

onBeforeUnmount(() => editor?.destroy())
</script>

<template>
  <div class="hz-stack gap-2 rich-editor-shell">
    <input ref="uploadInput" type="file" accept="image/*" class="hidden" @change="uploadImage" />

    <div class="rich-editor-toolbar hz-row gap-2 flex-wrap">
      <div class="rich-editor-tooltip" data-tooltip="Block style">
        <select class="hz-input" style="max-width: 140px; padding: 0.3rem 0.55rem;" :disabled="disabled" :value="blockTypeValue" @mousedown.stop @change="applyBlockType($event.target.value)">
          <option value="p">Paragraph</option>
          <option value="h1">Heading 1</option>
          <option value="h2">Heading 2</option>
          <option value="h3">Heading 3</option>
          <option value="h4">Heading 4</option>
          <option value="h5">Heading 5</option>
          <option value="h6">Heading 6</option>
        </select>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Text alignment">
        <select class="hz-input" style="max-width: 140px; padding: 0.3rem 0.55rem;" :disabled="disabled" :value="currentTextAlign" @mousedown.stop @change="setTextAlign($event.target.value)">
          <option value="left">Align Left</option>
          <option value="center">Align Center</option>
          <option value="right">Align Right</option>
          <option value="justify">Justify</option>
        </select>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Font family">
        <select class="hz-input" style="max-width: 140px; padding: 0.3rem 0.55rem;" :disabled="disabled" :value="currentFontFamily" :style="{ fontFamily: currentFontFamilyPreview }" @mousedown.stop @change="applyFontFamily($event.target.value)">
          <option v-for="opt in fontFamilyOptions" :key="opt.value || '__default'" :value="opt.value" :style="{ fontFamily: opt.preview }">{{ opt.label }}</option>
        </select>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Font size">
        <select class="hz-input" style="max-width: 130px; padding: 0.3rem 0.55rem;" :disabled="disabled" :value="currentFontSize" @mousedown.stop @change="applyFontSize($event.target.value)">
          <option v-for="opt in fontSizeOptions" :key="opt.value || '__default'" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Text color preset">
        <select class="hz-input" style="max-width: 190px; padding: 0.3rem 0.55rem;" :disabled="disabled" :value="currentTextColorSelectValue" @mousedown.stop @change="applyTextColor($event.target.value)">
          <option value="">Text: Default</option>
          <option value="__custom" disabled>Text: Custom</option>
          <option v-for="opt in textColorOptions.filter(option => option.value)" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Custom text color">
        <div class="rich-editor-color-group">
          <input type="color" class="rich-editor-color-chip" :disabled="disabled" :value="currentTextColorHex" @mousedown.stop @input="applyCustomTextColor($event.target.value)" />
          <input type="text" class="hz-input rich-editor-hex-input" :disabled="disabled" :value="currentTextColorHex" @mousedown.stop @change="applyCustomTextColor($event.target.value)" />
        </div>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Highlight color preset">
        <select class="hz-input" style="max-width: 200px; padding: 0.3rem 0.55rem;" :disabled="disabled" :value="currentHighlightSelectValue" @mousedown.stop @change="applyHighlightColor($event.target.value)">
          <option value="">Highlight: None</option>
          <option value="__custom" disabled>Highlight: Custom</option>
          <option v-for="opt in highlightColorOptions.filter(option => option.value)" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Custom highlight color">
        <div class="rich-editor-color-group">
          <input type="color" class="rich-editor-color-chip" :disabled="disabled" :value="currentHighlightHex" @mousedown.stop @input="applyHighlightColor($event.target.value)" />
          <input type="text" class="hz-input rich-editor-hex-input" :disabled="disabled" :value="currentHighlightHex" @mousedown.stop @change="applyHighlightColor($event.target.value)" />
        </div>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Field preset">
        <select class="hz-input" style="max-width: 180px; padding: 0.3rem 0.55rem;" :disabled="disabled" :value="currentCalloutSelectValue" @mousedown.stop @change="applyCalloutTone($event.target.value)">
          <option value="__custom" disabled>Field: Custom</option>
          <option v-for="opt in calloutToneOptions" :key="opt.value || '__none'" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Custom field color">
        <div class="rich-editor-color-group">
          <input type="color" class="rich-editor-color-chip" :disabled="disabled" :value="currentCalloutHex" @mousedown.stop @input="applyCustomCalloutColor($event.target.value)" />
          <input type="text" class="hz-input rich-editor-hex-input" :disabled="disabled" :value="currentCalloutHex" @mousedown.stop @change="applyCustomCalloutColor($event.target.value)" />
        </div>
      </div>

      <div class="rich-editor-tooltip" data-tooltip="Clear custom text styling">
        <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="clearTypography">Clear Type</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Remove highlight">
        <HorizonButton type="button" size="xs" :variant="isHighlightActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="applyHighlightColor('')">Clear Highlight</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Bold">
        <HorizonButton type="button" size="xs" :variant="isBoldActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleBold">B</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Italic">
        <HorizonButton type="button" size="xs" :variant="isItalicActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleItalic">I</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Underline">
        <HorizonButton type="button" size="xs" :variant="isUnderlineActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleUnderline">U</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Strikethrough">
        <HorizonButton type="button" size="xs" :variant="isStrikeActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleStrike">S</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Bulleted list">
        <HorizonButton type="button" size="xs" :variant="isBulletListActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleUnorderedList">• List</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Numbered list">
        <HorizonButton type="button" size="xs" :variant="isOrderedListActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleOrderedList">1. List</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Block quote">
        <HorizonButton type="button" size="xs" :variant="isBlockquoteActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleBlockquote">Quote</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Horizontal divider">
        <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="insertDivider">Divider</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Insert or edit link">
        <HorizonButton type="button" size="xs" :variant="isLinkActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="insertLink">Link</HorizonButton>
      </div>

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
      <div class="rich-editor-tooltip" data-tooltip="Undo">
        <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled || !canUndo" @mousedown.prevent @click="undo">Undo</HorizonButton>
      </div>
      <div class="rich-editor-tooltip" data-tooltip="Redo">
        <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled || !canRedo" @mousedown.prevent @click="redo">Redo</HorizonButton>
      </div>
    </div>

    <p v-if="uploadError" class="text-xs text-red-300">{{ uploadError }}</p>

    <div class="relative">
      <div v-if="showPlaceholder" class="pointer-events-none absolute left-3 top-3 text-horizon-muted">{{ placeholder }}</div>
      <EditorContent
        :editor="editor"
        class="hz-textarea hz-rte-content rich-editor-body"
        :class="disabled ? 'opacity-70 pointer-events-none' : ''"
        :style="{ minHeight: `${minHeightPx}px` }"
      />
    </div>
  </div>
</template>

<style scoped>
.rich-editor-shell {
  position: relative;
}

.rich-editor-toolbar {
  position: sticky;
  top: 0.75rem;
  z-index: 20;
  padding: 0.7rem;
  border: 1px solid rgb(255 255 255 / 0.08);
  border-radius: 1rem;
  background: rgb(16 19 28 / 0.88);
  backdrop-filter: blur(14px);
}

.rich-editor-tooltip {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.rich-editor-tooltip::before,
.rich-editor-tooltip::after {
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
  border-color: rgb(9 12 18 / 0.96) transparent transparent;
}

.rich-editor-tooltip::after {
  content: attr(data-tooltip);
  bottom: calc(100% + 0.45rem);
  transform: translateX(-50%) translateY(0.35rem);
  width: max-content;
  max-width: 13rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid rgb(255 255 255 / 0.08);
  border-radius: 0.75rem;
  background: rgb(9 12 18 / 0.96);
  color: rgb(238 242 255 / 1);
  font-size: 0.72rem;
  font-weight: 600;
  line-height: 1.25;
  text-align: center;
  white-space: normal;
  box-shadow: 0 14px 28px rgb(0 0 0 / 0.32);
}

.rich-editor-tooltip:hover::before,
.rich-editor-tooltip:hover::after,
.rich-editor-tooltip:focus-within::before,
.rich-editor-tooltip:focus-within::after {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

.rich-editor-color-group {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
}

.rich-editor-color-chip {
  width: 2.5rem;
  height: 2.35rem;
  padding: 0.2rem;
  border: 1px solid rgb(255 255 255 / 0.1);
  border-radius: 0.85rem;
  background: rgb(27 32 53 / 1);
  cursor: pointer;
}

.rich-editor-hex-input {
  width: 7.5rem;
  min-width: 7.5rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  text-transform: uppercase;
}

.rich-editor-body :deep(p::after) {
  content: '';
  display: block;
  clear: both;
}
</style>








