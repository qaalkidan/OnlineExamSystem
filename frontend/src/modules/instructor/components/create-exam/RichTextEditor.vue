<script setup lang="ts">
import { ref, shallowRef, watch, onBeforeUnmount, onMounted } from 'vue'
import { Editor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import { Table } from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'
import TextAlign from '@tiptap/extension-text-align'
import Highlight from '@tiptap/extension-highlight'
import { TextStyle } from '@tiptap/extension-text-style'
import Color from '@tiptap/extension-color'
import CharacterCount from '@tiptap/extension-character-count'
import Placeholder from '@tiptap/extension-placeholder'
import Superscript from '@tiptap/extension-superscript'
import Subscript from '@tiptap/extension-subscript'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight'
import { common, createLowlight } from 'lowlight'

const lowlight = createLowlight(common)

const props = defineProps<{
  modelValue: string
  placeholder?: string
}>()

const emit = defineEmits(['update:modelValue'])

const editor = shallowRef<Editor>()
const isFullscreen = ref(false)
const wordCount = ref(0)
const charCount = ref(0)

const toggleFullscreen = () => {
  isFullscreen.value = !isFullscreen.value
  if (isFullscreen.value) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
}

onMounted(() => {
  editor.value = new Editor({
    content: props.modelValue,
    extensions: [
      StarterKit.configure({
        codeBlock: false,
        link: false,
        underline: false,
      }),
      Underline,
      Link.configure({ openOnClick: false }),
      Image,
      Table.configure({ resizable: true }),
      TableRow,
      TableHeader,
      TableCell,
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      Highlight.configure({ multicolor: true }),
      TextStyle,
      Color,
      CharacterCount.configure({
        limit: 10000,
      }),
      Placeholder.configure({
        placeholder: props.placeholder || 'Type or paste your question here...',
      }),
      Superscript,
      Subscript,
      TaskList,
      TaskItem.configure({ nested: true }),
      CodeBlockLowlight.configure({
        lowlight,
      }),
    ],
    onUpdate: () => {
      if (editor.value) {
        emit('update:modelValue', editor.value.getHTML())
        wordCount.value = editor.value.storage.characterCount.words()
        charCount.value = editor.value.storage.characterCount.characters()
      }
    },
  })
})

watch(() => props.modelValue, (value) => {
  if (editor.value && editor.value.getHTML() !== value) {
    editor.value.commands.setContent(value, { emitUpdate: false })
  }
})

onBeforeUnmount(() => {
  if (editor.value) {
    editor.value.destroy()
  }
  document.body.style.overflow = ''
})

const addImage = () => {
  const url = window.prompt('URL of the image:')
  if (url && editor.value) {
    editor.value.chain().focus().setImage({ src: url }).run()
  }
}
const addLink = () => {
  const url = window.prompt('URL:')
  if (url && editor.value) {
    editor.value.chain().focus().setLink({ href: url }).run()
  }
}
const addTable = () => {
  if (editor.value) {
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
  }
}
</script>

<template>
  <div :class="[
    'flex flex-col border border-slate-200 rounded-xl overflow-hidden bg-white transition-all',
    isFullscreen ? 'fixed inset-0 z-[150] m-4 shadow-2xl min-h-[90vh]' : 'relative focus-within:border-[#5138ed] focus-within:ring-1 focus-within:ring-[#5138ed] min-h-[300px]'
  ]">
    
    <!-- Toolbar -->
    <div v-if="editor" class="sticky top-0 bg-slate-50 border-b border-slate-200 px-3 py-2 flex flex-wrap items-center gap-x-1 gap-y-2 z-10 rounded-t-xl">
      
      <!-- Text Formatting -->
      <div class="flex items-center space-x-1 border-r border-slate-200 pr-2 mr-1">
        <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('heading', { level: 1 }) }" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 text-slate-600 transition-colors font-bold text-[13px]" title="Heading 1">
          H₁
        </button>
        <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('heading', { level: 2 }) }" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 text-slate-600 transition-colors font-bold text-[13px]" title="Heading 2">
          H₂
        </button>
      </div>

      <div class="flex items-center space-x-1 border-r border-slate-200 pr-2 mr-1">
        <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('bold') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Bold">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 12a4 4 0 0 0 0-8H6v8"/><path d="M15 20a4 4 0 0 0 0-8H6v8Z"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('italic') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Italic">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('underline') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Underline">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" x2="20" y1="20" y2="20"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('strike') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Strikethrough">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" x2="20" y1="12" y2="12"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleHighlight().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('highlight') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Highlight">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11-6 6v3h9l3-3"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>
        </button>
      </div>

      <!-- Lists -->
      <div class="flex items-center space-x-1 border-r border-slate-200 pr-2 mr-1">
        <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('bulletList') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Bullet List">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('orderedList') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Numbered List">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleTaskList().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('taskList') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Checklist">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="6" height="6" rx="1"/><path d="m3 17 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/></svg>
        </button>
      </div>

      <!-- Alignment -->
      <div class="flex items-center space-x-1 border-r border-slate-200 pr-2 mr-1">
        <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive({ textAlign: 'left' }) }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Align Left">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="3" y1="6" y2="6"/><line x1="15" x2="3" y1="12" y2="12"/><line x1="17" x2="3" y1="18" y2="18"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive({ textAlign: 'center' }) }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Align Center">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="3" y1="6" y2="6"/><line x1="17" x2="7" y1="12" y2="12"/><line x1="19" x2="5" y1="18" y2="18"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive({ textAlign: 'right' }) }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Align Right">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" x2="3" y1="6" y2="6"/><line x1="21" x2="9" y1="12" y2="12"/><line x1="21" x2="7" y1="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Inserts & Academics -->
      <div class="flex items-center space-x-1 border-r border-slate-200 pr-2 mr-1">
        <button type="button" @click="addLink" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('link') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Insert Link">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        </button>
        <button type="button" @click="addImage" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Insert Image">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
        </button>
        <button type="button" @click="addTable" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Insert Table">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/><path d="M15 3v18"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().setHorizontalRule().run()" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Horizontal Divider">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" x2="19" y1="12" y2="12"/></svg>
        </button>
      </div>

      <div class="flex items-center space-x-1 border-r border-slate-200 pr-2 mr-1">
        <button type="button" @click="editor.chain().focus().toggleCode().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('code') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Inline Code">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('codeBlock') }" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" title="Code Block">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m10 10-2 2 2 2"/><path d="m14 14 2-2-2-2"/></svg>
        </button>
      </div>

      <div class="flex items-center space-x-1 border-r border-slate-200 pr-2 mr-1">
        <button type="button" @click="editor.chain().focus().toggleSuperscript().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('superscript') }" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 text-slate-600 transition-colors font-bold text-[13px]" title="Superscript">
          x²
        </button>
        <button type="button" @click="editor.chain().focus().toggleSubscript().run()" :class="{ 'bg-slate-200 text-slate-800': editor.isActive('subscript') }" class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 text-slate-600 transition-colors font-bold text-[13px]" title="Subscript">
          x₂
        </button>
      </div>

      <div class="flex items-center space-x-1">
        <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="p-1.5 rounded text-slate-600 hover:bg-slate-200 disabled:opacity-50 transition-colors" title="Undo">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="p-1.5 rounded text-slate-600 hover:bg-slate-200 disabled:opacity-50 transition-colors" title="Redo">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
        </button>
      </div>
      
      <div class="flex items-center space-x-1 ml-auto">
        <button type="button" @click="toggleFullscreen" class="p-1.5 rounded hover:bg-slate-200 text-slate-600 transition-colors" :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'">
          <svg v-if="!isFullscreen" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
          <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>
        </button>
      </div>
    </div>
    
    <!-- Editor Content -->
    <editor-content v-if="editor" :editor="editor" class="flex-1 p-4 overflow-y-auto prose prose-slate max-w-none text-[14px] outline-none" />
    
    <!-- Footer utilities -->
    <div v-if="editor" class="flex items-center justify-between px-4 py-2 border-t border-slate-100 bg-white text-[11px] font-medium text-slate-400 mt-auto rounded-b-xl">
      <div>
        <span class="mr-3">{{ wordCount }} words</span>
        <span>{{ charCount }} characters</span>
      </div>
      <div class="flex items-center gap-1 text-slate-300">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
        Auto-saving
      </div>
    </div>
  </div>
</template>

<style>
/* Tiptap Custom Styles for professional look */
.ProseMirror {
  outline: none;
  min-height: 160px;
}
.ProseMirror p.is-editor-empty:first-child::before {
  color: #94a3b8;
  content: attr(data-placeholder);
  float: left;
  height: 0;
  pointer-events: none;
}
.ProseMirror table {
  border-collapse: collapse;
  table-layout: fixed;
  width: 100%;
  margin: 0;
  overflow: hidden;
}
.ProseMirror table td,
.ProseMirror table th {
  min-width: 1em;
  border: 1px solid #e2e8f0;
  padding: 6px 12px;
  vertical-align: top;
  box-sizing: border-box;
  position: relative;
}
.ProseMirror table th {
  font-weight: bold;
  text-align: left;
  background-color: #f8fafc;
}
.ProseMirror .selectedCell:after {
  z-index: 2;
  position: absolute;
  content: "";
  left: 0; right: 0; top: 0; bottom: 0;
  background: rgba(81, 56, 237, 0.1);
  pointer-events: none;
}
.ProseMirror .column-resize-handle {
  position: absolute;
  right: -2px;
  top: 0;
  bottom: 0;
  width: 4px;
  background-color: #cbd5e1;
  pointer-events: none;
}
.ProseMirror p {
  margin-top: 0;
  margin-bottom: 0.5rem;
}
.ProseMirror ul[data-type="taskList"] {
  list-style: none;
  padding: 0;
}
.ProseMirror ul[data-type="taskList"] p {
  margin: 0;
}
.ProseMirror ul[data-type="taskList"] li {
  display: flex;
}
.ProseMirror ul[data-type="taskList"] li > label {
  flex: 0 0 auto;
  margin-right: 0.5rem;
  user-select: none;
}
.ProseMirror ul[data-type="taskList"] li > div {
  flex: 1 1 auto;
}
.ProseMirror ul {
  list-style-type: disc;
  padding-left: 1.5rem;
  margin-bottom: 0.5rem;
}
.ProseMirror ol {
  list-style-type: decimal;
  padding-left: 1.5rem;
  margin-bottom: 0.5rem;
}
.ProseMirror pre {
  background: #0f172a;
  color: #f8fafc;
  font-family: 'JetBrains Mono', monospace;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
}
.ProseMirror pre code {
  color: inherit;
  padding: 0;
  background: none;
  font-size: 0.8rem;
}
.ProseMirror mark {
  background-color: #fef08a;
  padding: 0.125em 0;
  border-radius: 0.25em;
}
.ProseMirror blockquote {
  border-left: 3px solid #cbd5e1;
  padding-left: 1rem;
  margin-left: 0;
  color: #64748b;
  font-style: italic;
}
.ProseMirror img {
  max-width: 100%;
  height: auto;
  border-radius: 0.5rem;
  margin: 1rem 0;
}
.ProseMirror hr {
  border: 0;
  border-top: 1px solid #e2e8f0;
  margin: 1.5rem 0;
}
</style>
