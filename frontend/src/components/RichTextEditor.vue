<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import Color from '@tiptap/extension-color'
import { TextStyle } from '@tiptap/extension-text-style'
import Highlight from '@tiptap/extension-highlight'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import { Table } from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import Placeholder from '@tiptap/extension-placeholder'
import {
  Bold, Italic, Underline as UnderlineIcon, Strikethrough, Heading1, Heading2,
  List, ListOrdered, CheckSquare, AlignLeft, AlignCenter, AlignRight, AlignJustify,
  Quote, Code, CodeXml, Minus, Table as TableIcon, Link as LinkIcon, Image as ImageIcon,
  Superscript as SuperscriptIcon, Subscript as SubscriptIcon, Undo, Redo, RemoveFormatting,
  Maximize
} from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Type your content here...'
  },
  minHeight: {
    type: String,
    default: '150px'
  }
})

const emit = defineEmits(['update:modelValue'])

const isFullScreen = ref(false)
const wrapper = ref<HTMLElement | null>(null)

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      underline: false,
      link: false,
    }),
    Underline,
    TextStyle,
    Color,
    Highlight.configure({ multicolor: true }),
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    TaskList,
    TaskItem.configure({ nested: true }),
    Table.configure({ resizable: true }),
    TableRow,
    TableHeader,
    TableCell,
    Link.configure({ openOnClick: false }),
    Image.configure({ inline: true, allowBase64: true }),
    Subscript,
    Superscript,
    Placeholder.configure({
      placeholder: props.placeholder,
    })
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
})

watch(() => props.modelValue, (value) => {
  const isSame = editor.value?.getHTML() === value
  if (editor.value && !isSame) {
    editor.value.commands.setContent(value, { emitUpdate: false })
  }
})

const toggleFullScreen = () => {
  isFullScreen.value = !isFullScreen.value
  if (isFullScreen.value) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
}

onBeforeUnmount(() => {
  if (editor.value) {
    editor.value.destroy()
  }
  document.body.style.overflow = ''
})

const addLink = () => {
  if (!editor.value) return
  const previousUrl = editor.value.getAttributes('link').href
  const url = window.prompt('URL', previousUrl)
  if (url === null) return
  if (url === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }
  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

const addImage = () => {
  if (!editor.value) return
  const url = window.prompt('Image URL')
  if (url) {
    editor.value.chain().focus().setImage({ src: url }).run()
  }
}

const insertTable = () => {
  editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}
</script>

<template>
  <div ref="wrapper" :class="[
    'flex flex-col bg-white border border-slate-200 rounded-xl overflow-hidden transition-all duration-200',
    isFullScreen ? 'fixed inset-0 z-[100] border-none rounded-none' : 'relative focus-within:border-[#5138ed] focus-within:ring-1 focus-within:ring-[#5138ed]'
  ]">
    
    <!-- Toolbar -->
    <div v-if="editor" class="flex flex-wrap items-center gap-1 p-2 bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
      
      <!-- History -->
      <div class="flex items-center gap-0.5 pr-2 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-200 disabled:opacity-50"><Undo class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-200 disabled:opacity-50"><Redo class="w-4 h-4" /></button>
      </div>

      <!-- Formatting -->
      <div class="flex items-center gap-0.5 px-2 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('bold') ? 'bg-indigo-100 text-indigo-700' : '']" title="Bold (Ctrl+B)"><Bold class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('italic') ? 'bg-indigo-100 text-indigo-700' : '']" title="Italic (Ctrl+I)"><Italic class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('underline') ? 'bg-indigo-100 text-indigo-700' : '']" title="Underline (Ctrl+U)"><UnderlineIcon class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('strike') ? 'bg-indigo-100 text-indigo-700' : '']" title="Strikethrough"><Strikethrough class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleSubscript().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('subscript') ? 'bg-indigo-100 text-indigo-700' : '']" title="Subscript"><SubscriptIcon class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleSuperscript().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('superscript') ? 'bg-indigo-100 text-indigo-700' : '']" title="Superscript"><SuperscriptIcon class="w-4 h-4" /></button>
      </div>

      <!-- Headings -->
      <div class="flex items-center gap-0.5 px-2 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('heading', { level: 1 }) ? 'bg-indigo-100 text-indigo-700' : '']"><Heading1 class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('heading', { level: 2 }) ? 'bg-indigo-100 text-indigo-700' : '']"><Heading2 class="w-4 h-4" /></button>
      </div>

      <!-- Alignment -->
      <div class="flex items-center gap-0.5 px-2 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive({ textAlign: 'left' }) ? 'bg-indigo-100 text-indigo-700' : '']"><AlignLeft class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive({ textAlign: 'center' }) ? 'bg-indigo-100 text-indigo-700' : '']"><AlignCenter class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive({ textAlign: 'right' }) ? 'bg-indigo-100 text-indigo-700' : '']"><AlignRight class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().setTextAlign('justify').run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive({ textAlign: 'justify' }) ? 'bg-indigo-100 text-indigo-700' : '']"><AlignJustify class="w-4 h-4" /></button>
      </div>

      <!-- Lists -->
      <div class="flex items-center gap-0.5 px-2 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('bulletList') ? 'bg-indigo-100 text-indigo-700' : '']"><List class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('orderedList') ? 'bg-indigo-100 text-indigo-700' : '']"><ListOrdered class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleTaskList().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('taskList') ? 'bg-indigo-100 text-indigo-700' : '']"><CheckSquare class="w-4 h-4" /></button>
      </div>

      <!-- Elements -->
      <div class="flex items-center gap-0.5 px-2 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('blockquote') ? 'bg-indigo-100 text-indigo-700' : '']"><Quote class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleCode().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('code') ? 'bg-indigo-100 text-indigo-700' : '']"><Code class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('codeBlock') ? 'bg-indigo-100 text-indigo-700' : '']"><CodeXml class="w-4 h-4" /></button>
        <button type="button" @click="editor.chain().focus().setHorizontalRule().run()" class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-200"><Minus class="w-4 h-4" /></button>
      </div>

      <!-- Inserts -->
      <div class="flex items-center gap-0.5 px-2 border-r border-slate-200">
        <button type="button" @click="addLink" :class="['p-1.5 rounded-lg text-slate-600 hover:bg-slate-200', editor.isActive('link') ? 'bg-indigo-100 text-indigo-700' : '']"><LinkIcon class="w-4 h-4" /></button>
        <button type="button" @click="addImage" class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-200"><ImageIcon class="w-4 h-4" /></button>
        <button type="button" @click="insertTable" class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-200"><TableIcon class="w-4 h-4" /></button>
      </div>

      <!-- Colors & Utilities -->
      <div class="flex items-center gap-1 pl-2">
        <input type="color" @input="(event) => { if (editor) editor.chain().focus().setColor((event.target as HTMLInputElement).value).run() }" :value="editor?.getAttributes('textStyle').color" class="w-6 h-6 p-0 border-0 rounded cursor-pointer" title="Text Color" />
        <button type="button" @click="() => { if (editor) editor.chain().focus().unsetAllMarks().clearNodes().run() }" class="p-1.5 rounded-lg text-slate-600 hover:bg-rose-100 hover:text-rose-600 ml-1" title="Clear Formatting"><RemoveFormatting class="w-4 h-4" /></button>
      </div>

      <!-- Full Screen -->
      <div class="flex-1 flex justify-end pr-2">
        <button type="button" @click="toggleFullScreen" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-200" :title="isFullScreen ? 'Exit Full Screen' : 'Full Screen'"><Maximize class="w-4 h-4" /></button>
      </div>
    </div>

    <!-- Editor Content -->
    <div class="flex-1 overflow-y-auto bg-white cursor-text" :style="{ minHeight: minHeight, maxHeight: isFullScreen ? 'calc(100vh - 50px)' : 'auto' }" @click="editor?.commands.focus()">
      <editor-content v-if="editor" :editor="editor" class="h-full prose prose-sm max-w-none focus:outline-none p-4" />
    </div>

  </div>
</template>

<style>
/* ProseMirror default styles */
.ProseMirror {
  outline: none;
  min-height: inherit;
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
  padding: 3px 5px;
  vertical-align: top;
  box-sizing: border-box;
  position: relative;
}
.ProseMirror table th {
  font-weight: bold;
  text-align: left;
  background-color: #f8fafc;
}
.ProseMirror img {
  max-width: 100%;
  height: auto;
  border-radius: 0.5rem;
}
.ProseMirror pre {
  background: #0f172a;
  color: #f8fafc;
  font-family: 'JetBrainsMono', monospace;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
}
.ProseMirror code {
  font-size: 0.9rem;
  padding: 0.25em 0.3em;
  background-color: #f1f5f9;
  border-radius: 0.25rem;
}
.ProseMirror pre code {
  color: inherit;
  padding: 0;
  background: none;
  font-size: 0.8rem;
}
.ProseMirror ul[data-type="taskList"] {
  list-style: none;
  padding: 0;
}
.ProseMirror ul[data-type="taskList"] li {
  display: flex;
  align-items: center;
}
.ProseMirror ul[data-type="taskList"] li > label {
  margin-right: 0.5rem;
  user-select: none;
}
</style>
