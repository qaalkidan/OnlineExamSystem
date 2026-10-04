<script setup lang="ts">
import { shallowRef, watch, onMounted, onBeforeUnmount } from 'vue'
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
import Placeholder from '@tiptap/extension-placeholder'
import Superscript from '@tiptap/extension-superscript'
import Subscript from '@tiptap/extension-subscript'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight'
import { common, createLowlight } from 'lowlight'
import { useSharedEditor } from '../../composables/useSharedEditor'

const lowlight = createLowlight(common)

const props = defineProps<{
  modelValue: string
  placeholder?: string
  label?: string
  minHeight?: string
}>()

const emit = defineEmits(['update:modelValue'])
const { setActiveEditor } = useSharedEditor()

const editor = shallowRef<Editor>()
const isFocused = shallowRef(false)

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
      Placeholder.configure({
        placeholder: props.placeholder || 'Click to edit...',
      }),
      Superscript,
      Subscript,
      TaskList,
      TaskItem.configure({ nested: true }),
      CodeBlockLowlight.configure({ lowlight }),
    ],
    onUpdate: () => {
      if (editor.value) {
        emit('update:modelValue', editor.value.getHTML())
      }
    },
    onFocus: () => {
      isFocused.value = true
      if (editor.value) {
        setActiveEditor(editor.value, props.label || '')
      }
      
      // Smart Auto Scroll
      setTimeout(() => {
        const el = document.querySelector(`.minimal-editor-wrap[data-label="${props.label}"]`)
        if (el) {
          const rect = el.getBoundingClientRect()
          // Check if obscured by sticky toolbar (approx 160px height + 80px navbar = 240px) 
          // or outside viewport
          if (rect.top < 240 || rect.bottom > window.innerHeight - 20) {
            const y = el.getBoundingClientRect().top + window.scrollY - 260 // Offset for toolbar
            window.scrollTo({ top: y, behavior: 'smooth' })
          }
        }
      }, 50)
    },
    onBlur: () => {
      isFocused.value = false
    },
  })
})

watch(() => props.modelValue, (value) => {
  if (editor.value && editor.value.getHTML() !== value) {
    editor.value.commands.setContent(value ?? '', { emitUpdate: false })
  }
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<template>
  <div
    :data-label="label"
    :class="[
      'minimal-editor-wrap border rounded-xl bg-white transition-all duration-300 overflow-hidden relative',
      isFocused
        ? 'border-[#4F46E5] ring-4 ring-[#4F46E5]/10 shadow-[0_0_15px_rgba(79,70,229,0.15)] z-10'
        : 'border-slate-200 hover:border-slate-300 shadow-sm'
    ]"
  >
    <EditorContent
      :editor="editor"
      class="minimal-editor-content px-5 py-4 text-[13.5px] text-slate-700 leading-relaxed"
      :style="{ minHeight: minHeight || '80px' }"
    />
  </div>
</template>

<style scoped>
.minimal-editor-content :deep(.ProseMirror) {
  outline: none;
  min-height: inherit;
}
.minimal-editor-content :deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  color: #94a3b8;
  pointer-events: none;
  float: left;
  height: 0;
}
.minimal-editor-content :deep(.ProseMirror pre) {
  background: #1e293b;
  color: #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
  font-family: 'Fira Code', monospace;
  font-size: 12px;
  overflow-x: auto;
}
.minimal-editor-content :deep(.ProseMirror table) {
  border-collapse: collapse;
  width: 100%;
  margin: 8px 0;
}
.minimal-editor-content :deep(.ProseMirror td),
.minimal-editor-content :deep(.ProseMirror th) {
  border: 1px solid #e2e8f0;
  padding: 6px 10px;
  text-align: left;
}
.minimal-editor-content :deep(.ProseMirror th) {
  background: #f8fafc;
  font-weight: 600;
}
</style>
