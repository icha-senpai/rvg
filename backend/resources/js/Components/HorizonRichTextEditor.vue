<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import HorizonButton from '@/Components/HorizonButton.vue'
import { Editor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
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
  { value: '', label: 'Color: Default' },
  { value: 'hz-rte-color-white', label: 'White' },
  { value: 'hz-rte-color-muted', label: 'Muted' },
  { value: 'hz-rte-color-blue', label: 'Blue' },
  { value: 'hz-rte-color-cyan', label: 'Cyan' },
  { value: 'hz-rte-color-magenta', label: 'Magenta' },
  { value: 'hz-rte-color-pink', label: 'Pink' },
  { value: 'hz-rte-color-orange', label: 'Orange' },
  { value: 'hz-rte-color-green', label: 'Green' },
  { value: 'hz-rte-color-red', label: 'Red' },
  { value: 'hz-rte-color-yellow', label: 'Yellow' },
]

const currentFontFamilyPreview = computed(() => {
  const match = fontFamilyOptions.find(option => option.value === currentFontFamily.value)
  return match?.preview || ''
})

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
    Underline,
    WrappedImage.configure({ inline: false, allowBase64: false, HTMLAttributes: { loading: 'lazy' } }),
    ExtendedTextStyle,
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
  focusAndRestoreSelection()
  if (!value) {
    editor.chain().unsetRteColor().run()
    return
  }
  editor.chain().setRteColor(value).run()
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
  editor.chain().unsetRteColor().unsetRteFontFamily().unsetRteFontSize().run()
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
  <div class="hz-stack gap-2">
    <input ref="uploadInput" type="file" accept="image/*" class="hidden" @change="uploadImage" />

    <div class="hz-row gap-2 flex-wrap">
      <select class="hz-input" style="max-width: 140px; padding: 0.3rem 0.55rem;" title="Block style" :disabled="disabled" :value="blockTypeValue" @mousedown.stop @change="applyBlockType($event.target.value)">
        <option value="p">Paragraph</option>
        <option value="h1">Heading 1</option>
        <option value="h2">Heading 2</option>
        <option value="h3">Heading 3</option>
        <option value="h4">Heading 4</option>
        <option value="h5">Heading 5</option>
        <option value="h6">Heading 6</option>
      </select>

      <select class="hz-input" style="max-width: 140px; padding: 0.3rem 0.55rem;" title="Text alignment" :disabled="disabled" :value="currentTextAlign" @mousedown.stop @change="setTextAlign($event.target.value)">
        <option value="left">Align Left</option>
        <option value="center">Align Center</option>
        <option value="right">Align Right</option>
        <option value="justify">Justify</option>
      </select>

      <select class="hz-input" style="max-width: 140px; padding: 0.3rem 0.55rem;" title="Font family" :disabled="disabled" :value="currentFontFamily" :style="{ fontFamily: currentFontFamilyPreview }" @mousedown.stop @change="applyFontFamily($event.target.value)">
        <option v-for="opt in fontFamilyOptions" :key="opt.value || '__default'" :value="opt.value" :style="{ fontFamily: opt.preview }">{{ opt.label }}</option>
      </select>

      <select class="hz-input" style="max-width: 130px; padding: 0.3rem 0.55rem;" title="Font size" :disabled="disabled" :value="currentFontSize" @mousedown.stop @change="applyFontSize($event.target.value)">
        <option v-for="opt in fontSizeOptions" :key="opt.value || '__default'" :value="opt.value">{{ opt.label }}</option>
      </select>

      <select class="hz-input" style="max-width: 145px; padding: 0.3rem 0.55rem;" title="Text color" :disabled="disabled" :value="currentTextColor" @mousedown.stop @change="applyTextColor($event.target.value)">
        <option v-for="opt in textColorOptions" :key="opt.value || '__default'" :value="opt.value">{{ opt.label }}</option>
      </select>

      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="clearTypography">Clear Type</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isBoldActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleBold">B</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isItalicActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleItalic">I</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isUnderlineActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleUnderline">U</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isStrikeActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleStrike">S</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isBulletListActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleUnorderedList">• List</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isOrderedListActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleOrderedList">1. List</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isBlockquoteActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="toggleBlockquote">Quote</HorizonButton>
      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="insertDivider">Divider</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isLinkActive ? 'primary' : 'ghost'" :disabled="disabled" @mousedown.prevent @click="insertLink">Link</HorizonButton>

      <HorizonButton type="button" size="xs" variant="ghost" title="Insert image URL centered" :disabled="disabled" @mousedown.prevent @click="insertImage('center')">Image URL</HorizonButton>
      <HorizonButton type="button" size="xs" variant="ghost" title="Upload centered image" :disabled="disabled || isUploadingImage" @mousedown.prevent @click="triggerImageUpload('center')">{{ isUploadingImage ? 'Uploading…' : 'Upload Image' }}</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isImageActive && currentImageAlign === 'left' ? 'primary' : 'ghost'" title="Image left with text wrap" :disabled="disabled" @mousedown.prevent @click="setImageAlign('left')">Img Left</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isImageActive && currentImageAlign === 'center' ? 'primary' : 'ghost'" title="Image centered" :disabled="disabled" @mousedown.prevent @click="setImageAlign('center')">Img Center</HorizonButton>
      <HorizonButton type="button" size="xs" :variant="isImageActive && currentImageAlign === 'right' ? 'primary' : 'ghost'" title="Image right with text wrap" :disabled="disabled" @mousedown.prevent @click="setImageAlign('right')">Img Right</HorizonButton>

      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="insertTable">Table</HorizonButton>
      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="addTableRow">+Row</HorizonButton>
      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="addTableColumn">+Col</HorizonButton>
      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="deleteTable">Del Tbl</HorizonButton>
      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled || !canUndo" @mousedown.prevent @click="undo">Undo</HorizonButton>
      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled || !canRedo" @mousedown.prevent @click="redo">Redo</HorizonButton>
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
.rich-editor-body :deep(p::after) {
  content: '';
  display: block;
  clear: both;
}
</style>








