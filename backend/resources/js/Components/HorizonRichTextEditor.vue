<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import HorizonButton from '@/Components/HorizonButton.vue'
import { Editor, EditorContent } from '@tiptap/vue-3'
import { Extension } from '@tiptap/core'
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
import { Color } from '@tiptap/extension-color'
import { FontFamily } from '@tiptap/extension-font-family'

const FontSize = Extension.create({
  name: 'fontSize',
  addGlobalAttributes() {
    return [
      {
        types: ['textStyle'],
        attributes: {
          fontSize: {
            default: null,
            parseHTML: element => element.style.fontSize || null,
            renderHTML: attributes => attributes.fontSize ? { style: `font-size: ${attributes.fontSize}` } : {},
          },
        },
      },
    ]
  },
  addCommands() {
    return {
      setFontSize: (fontSize) => ({ chain }) => chain().setMark('textStyle', { fontSize }).run(),
      unsetFontSize: () => ({ chain }) => chain().setMark('textStyle', { fontSize: null }).run(),
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

const currentTextColor = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('textStyle')?.color || ''
})

const currentFontFamily = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('textStyle')?.fontFamily || ''
})

const currentFontSize = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('textStyle')?.fontSize || ''
})

const currentImageAlign = computed(() => {
  toolbarTick.value
  return editor?.getAttributes('image')?.['data-align'] || 'center'
})

const fontFamilyOptions = [
  { value: '', label: 'Font: Default', preview: '' },
  { value: 'system-ui', label: 'Font: System', preview: 'system-ui' },
  { value: 'sans-serif', label: 'Font: Sans', preview: 'sans-serif' },
  { value: 'arial', label: 'Font: Arial', preview: 'Arial, sans-serif' },
  { value: 'verdana', label: 'Font: Verdana', preview: 'Verdana, sans-serif' },
  { value: 'georgia', label: 'Font: Georgia', preview: 'Georgia, serif' },
  { value: 'monospace', label: 'Font: Mono', preview: 'monospace' },
]

const fontSizeOptions = [
  { value: '', label: 'Size: Default' },
  { value: '12px', label: '12px' },
  { value: '14px', label: '14px' },
  { value: '16px', label: '16px' },
  { value: '18px', label: '18px' },
  { value: '20px', label: '20px' },
  { value: '24px', label: '24px' },
  { value: '32px', label: '32px' },
]

const currentFontFamilyPreview = computed(() => {
  const current = String(currentFontFamily.value || '')
  const match = fontFamilyOptions.find(option => String(option.value).toLowerCase() === current.toLowerCase())
  return match?.preview || current
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
    WrappedImage.configure({
      inline: false,
      allowBase64: false,
      HTMLAttributes: {
        loading: 'lazy',
      },
    }),
    TextStyle,
    FontSize,
    Color,
    FontFamily,
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Table.configure({ resizable: false }),
    TableRow,
    TableHeader,
    TableCell,
    Link.configure({
      openOnClick: false,
      autolink: true,
      linkOnPaste: true,
      HTMLAttributes: {
        target: '_blank',
        rel: 'noopener noreferrer',
      },
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

function applyTextColor(color) {
  focusAndRestoreSelection()
  if (!color) {
    editor.chain().unsetColor().run()
    return
  }
  editor.chain().setColor(color).run()
}

function clearTextColor() {
  focusAndRestoreSelection()
  editor.chain().unsetColor().run()
}

function applyFontFamily(value) {
  focusAndRestoreSelection()
  if (!value) {
    editor.chain().unsetFontFamily().run()
    return
  }
  editor.chain().setFontFamily(value).run()
}

function applyFontSize(value) {
  focusAndRestoreSelection()
  if (!value) {
    editor.chain().unsetFontSize().run()
    return
  }
  editor.chain().setFontSize(value).run()
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
  editor.chain().setImage({
    src,
    alt,
    class: imageClassForAlign(align),
    'data-align': align,
  }).run()
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
  }).run()
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
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: formData,
    })

    const data = await response.json()
    if (!response.ok || data.status !== 'ok') {
      throw new Error(data.message || 'Image upload failed.')
    }

    const media = data.payload?.media
    const url = mediaDisplayUrl(media)
    if (!url) {
      throw new Error('Image uploaded, but no display URL was returned.')
    }

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

      <input type="color" class="hz-input" style="width: 44px; padding: 0.25rem;" title="Text color" :disabled="disabled" :value="currentTextColor || '#ffffff'" @mousedown.stop @change="applyTextColor($event.target.value)" />

      <HorizonButton type="button" size="xs" variant="ghost" :disabled="disabled" @mousedown.prevent @click="clearTextColor">Color ×</HorizonButton>
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
        class="hz-textarea rich-editor-body [&_p]:my-0 [&_p]:leading-relaxed [&_h1]:mt-3 [&_h1]:mb-1 [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:mt-3 [&_h2]:mb-1 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mt-3 [&_h3]:mb-1 [&_h3]:text-base [&_h3]:font-semibold [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:my-1 [&_a]:underline [&_blockquote]:my-2 [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_img]:my-2 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg [&_img]:border [&_img]:border-white/10 [&_table]:my-2 [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2"
        :class="disabled ? 'opacity-70 pointer-events-none' : ''"
        :style="{ minHeight: `${minHeightPx}px` }"
      />
    </div>
  </div>
</template>

<style scoped>
.rich-editor-body :deep(img.hz-rich-image-left) {
  float: left;
  max-width: min(45%, 22rem);
  margin: 0.35rem 1rem 0.75rem 0;
}

.rich-editor-body :deep(img.hz-rich-image-right) {
  float: right;
  max-width: min(45%, 22rem);
  margin: 0.35rem 0 0.75rem 1rem;
}

.rich-editor-body :deep(img.hz-rich-image-center) {
  display: block;
  margin-left: auto;
  margin-right: auto;
}

.rich-editor-body :deep(p::after) {
  content: '';
  display: block;
  clear: both;
}
</style>
