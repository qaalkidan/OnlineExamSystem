<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useCreateExamStore } from '../../store/createExamStore'
import MinimalEditor from './MinimalEditor.vue'
import SharedEditorToolbar from './SharedEditorToolbar.vue'
import { GripVertical } from 'lucide-vue-next'
import SelectFromQuestionBank from './SelectFromQuestionBank.vue'
import ImportQuestionsFile from './ImportQuestionsFile.vue'
import AddedQuestionsList from './AddedQuestionsList.vue'

const props = defineProps<{ isSaving?: boolean }>()
const emit = defineEmits(['cancel', 'next', 'save-draft', 'prev'])
const formStore = useCreateExamStore()

// Question Source Tab: 'manual' | 'bank' | 'file'
const activeSourceTab = ref<'manual' | 'bank' | 'file'>('manual')

const onQuestionsAdded = () => {
  validationError.value = ''
}

// Common Form State
const questionType = ref('Multiple Choice (MCQ)')
const difficulty = ref('Medium')
const score = ref(5)
const chapter = ref('1')

const title = ref('') // Instructor Instruction
const description = ref('')
const questionText = ref('')

// Inline validation error message
const validationError = ref('')
const clearError = () => { validationError.value = '' }

// Map UI question type to backend type string (used for filtering instructions)
const mappedQuestionType = computed(() => {
  const map: Record<string, string> = {
    'Multiple Choice (MCQ)': 'multiple_choice',
    'True / False': 'true_false',
    'Fill in the Blank': 'fill_blank',
    'Short Answer': 'short_answer',
    'Matching': 'matching'
  }
  return map[questionType.value] || 'multiple_choice'
})

// Compute unique previous instructions filtered by current question type only
const previousInstructions = computed(() => {
  const instructions = formStore.questions
    .filter((q: any) => q.type === mappedQuestionType.value)
    .map((q: any) => q.instruction)
    .filter((inst: string) => inst && inst.trim() !== '')
  return [...new Set(instructions)] as string[]
})

// Auto-fill instruction when question type changes (or on mount)
watch(mappedQuestionType, () => {
  if (previousInstructions.value.length > 0) {
    title.value = previousInstructions.value[previousInstructions.value.length - 1]
  } else {
    title.value = ''
  }
}, { immediate: true })

// Multiple Choice (MCQ) State
const options = ref([
  { id: Date.now(), label: 'A', text: '' },
  { id: Date.now() + 1, label: 'B', text: '' },
  { id: Date.now() + 2, label: 'C', text: '' },
  { id: Date.now() + 3, label: 'D', text: '' }
])
const correctAnswer = ref('') // Used for both MCQ and True/False
const shuffleChoices = ref(true)

// Fill in the Blank State
const fibAnswers = ref([{ id: 1, text: '' }])
const fibCaseSensitive = ref(false)

// Short Answer State
const saExpectedAnswer = ref('')
const saMaxLength = ref(255)

// Matching State
const matchPairs = ref([
  { id: Date.now(), left: '', right: '' },
  { id: Date.now() + 1, left: '', right: '' },
  { id: Date.now() + 2, left: '', right: '' },
  { id: Date.now() + 3, left: '', right: '' }
])
const getLetterLabel = (index: number) => {
  let label = ''
  let num = index
  while (num >= 0) {
    label = String.fromCharCode(65 + (num % 26)) + label
    num = Math.floor(num / 26) - 1
  }
  return label
}

const matchShuffle = ref(true)
const matchMappings = ref(['A', 'B', 'C', 'D']) // correct answer mapping per row
const activeMatchingCell = ref<{ rowIndex: number, col: 'left' | 'right' } | null>(null)

const activeColACount = computed(() => {
  const count = matchPairs.value.filter(p => p.left.replace(/<[^>]*>?/gm, '').trim() !== '').length
  return count > 0 ? count : matchPairs.value.length
})

watch(questionType, (newVal) => {
  clearError()
  if (newVal === 'Matching') {
    if (!activeMatchingCell.value) {
      activeMatchingCell.value = { rowIndex: 0, col: 'left' }
    }
  } else {
    activeMatchingCell.value = null
  }
})

const sharedEditorContent = computed({
  get() {
    if (!activeMatchingCell.value) return ''
    const { rowIndex, col } = activeMatchingCell.value
    if (!matchPairs.value[rowIndex]) return ''
    return matchPairs.value[rowIndex][col]
  },
  set(val: string) {
    if (!activeMatchingCell.value) return
    const { rowIndex, col } = activeMatchingCell.value
    if (matchPairs.value[rowIndex]) {
      matchPairs.value[rowIndex][col] = val
    }
  }
})

// Handlers for Add/Remove Options
const addOption = () => {
  const nextLabel = String.fromCharCode(65 + options.value.length)
  options.value.push({ id: Date.now(), label: nextLabel, text: '' })
}
const removeOption = (index: number) => {
  options.value.splice(index, 1)
  options.value.forEach((opt, i) => {
    opt.label = String.fromCharCode(65 + i)
  })
}

const addFibAnswer = () => {
  fibAnswers.value.push({ id: Date.now(), text: '' })
}
const removeFibAnswer = (index: number) => {
  fibAnswers.value.splice(index, 1)
}

const addMatchPair = () => {
  matchPairs.value.push({ id: Date.now(), left: '', right: '' })
  matchMappings.value.push(getLetterLabel(matchPairs.value.length - 1))
}
const removeMatchPair = (index: number) => {
  matchPairs.value.splice(index, 1)
  matchMappings.value.splice(index, 1)
}

// Drag and drop for MCQ
let draggedOptionIndex: number | null = null
const onDragStart = (e: DragEvent, index: number) => {
  draggedOptionIndex = index
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.dropEffect = 'move'
  }
}
const onDrop = (e: DragEvent, dropIndex: number) => {
  if (draggedOptionIndex !== null && draggedOptionIndex !== dropIndex) {
    const item = options.value.splice(draggedOptionIndex, 1)[0]
    options.value.splice(dropIndex, 0, item)
    options.value.forEach((opt, i) => { opt.label = String.fromCharCode(65 + i) })
    if (correctAnswer.value === String.fromCharCode(65 + draggedOptionIndex)) {
        correctAnswer.value = String.fromCharCode(65 + dropIndex)
    }
  }
  draggedOptionIndex = null
}

const isFullscreen = ref(false)
const toggleFullscreen = () => { /* basic toggle if needed */ }

const setCorrectAnswer = (label: string) => {
  correctAnswer.value = label
}
const setTrueFalseAnswer = (answer: string) => {
  correctAnswer.value = answer
}

// ── Save Logic ──
const resetForm = () => {
  questionText.value = ''
  title.value = ''
  description.value = ''
  correctAnswer.value = ''
  options.value = [
    { id: Date.now(), label: 'A', text: '' },
    { id: Date.now() + 1, label: 'B', text: '' },
    { id: Date.now() + 2, label: 'C', text: '' },
    { id: Date.now() + 3, label: 'D', text: '' }
  ]
  fibAnswers.value = [{ id: 1, text: '' }]
  saExpectedAnswer.value = ''
  matchPairs.value = [
    { id: Date.now(), left: '', right: '' },
    { id: Date.now() + 1, left: '', right: '' },
    { id: Date.now() + 2, left: '', right: '' },
    { id: Date.now() + 3, left: '', right: '' }
  ]
  matchMappings.value = ['A', 'B', 'C', 'D']
}

const addQuestionToExam = () => {
  validationError.value = ''

  // 1. Question text required (except Matching)
  if (questionType.value !== 'Matching' && !questionText.value.replace(/<[^>]*>?/gm, '').trim()) {
    validationError.value = 'Please enter the question text.'
    return
  }

  // 2. Answer validation per type
  if (questionType.value === 'Multiple Choice (MCQ)') {
    if (!correctAnswer.value) {
      validationError.value = 'Exactly 1 correct answer must be selected.'
      return
    }
    const hasEmpty = options.value.some(o => !o.text.replace(/<[^>]*>?/gm, '').trim())
    if (hasEmpty) {
      validationError.value = 'All answer options must be filled in before adding the question.'
      return
    }
  }

  if (questionType.value === 'True / False') {
    if (!correctAnswer.value) {
      validationError.value = 'Exactly 1 correct answer must be selected.'
      return
    }
  }

  if (questionType.value === 'Fill in the Blank') {
    const hasAnswer = fibAnswers.value.some(a => a.text.trim())
    if (!hasAnswer) {
      validationError.value = 'Exactly 1 correct answer must be selected.'
      return
    }
  }

  if (questionType.value === 'Short Answer') {
    if (!saExpectedAnswer.value.trim()) {
      validationError.value = 'Exactly 1 correct answer must be selected.'
      return
    }
  }

  if (questionType.value === 'Matching') {
    const filledLefts = matchPairs.value.filter(p => p.left.replace(/<[^>]*>?/gm, '').trim())
    const filledRights = matchPairs.value.filter(p => p.right.replace(/<[^>]*>?/gm, '').trim())
    if (filledLefts.length < 1) {
      validationError.value = 'Please fill in at least one Column A (prompt) item.'
      return
    }
    if (filledRights.length < 1) {
      validationError.value = 'Please fill in at least one Column B (match) item.'
      return
    }
  }

  const mappedType = mappedQuestionType.value

  let qData: any = {
    type: mappedType,
    instruction: title.value,
    text: questionType.value === 'Matching' ? 'Matching Question' : questionText.value,
    difficulty: difficulty.value,
    marks: score.value,
    description: description.value
  }

  switch(mappedType) {
    case 'multiple_choice':
      qData.options = options.value.map(o => o.text)
      qData.correct_answer = correctAnswer.value || 'A'
      break;
    case 'true_false':
      qData.options = ['True', 'False']
      qData.correct_answer = correctAnswer.value === 'True' ? 'A' : 'B'
      break;
    case 'fill_blank':
      qData.correct_answer = fibAnswers.value.map(a => a.text).join('|')
      break;
    case 'short_answer':
      qData.correct_answer = saExpectedAnswer.value
      qData.max_words = saMaxLength.value
      break;
    case 'matching': {
      const correct_answers: Record<string, string> = {}
      let colAIndex = 1
      matchPairs.value.forEach((p, i) => {
        if (p.left.replace(/<[^>]*>?/gm, '').trim() !== '') {
          correct_answers[colAIndex.toString()] = matchMappings.value[i] || getLetterLabel(i)
          colAIndex++
        }
      })
      
      const column_a: string[] = []
      const column_b: { label: string, text: string }[] = []
      matchPairs.value.forEach((p, i) => {
        if (p.left.replace(/<[^>]*>?/gm, '').trim() !== '') {
          column_a.push(p.left.trim())
        }
        if (p.right.replace(/<[^>]*>?/gm, '').trim() !== '') {
          column_b.push({ label: getLetterLabel(i), text: p.right.trim() })
        }
      })
      
      const itemsCount = column_a.length > 0 ? column_a.length : 1
      const perItemMark = Number(score.value) || 1
      const totalMatchingMarks = itemsCount * perItemMark

      qData.column_a = column_a
      qData.column_b = column_b
      qData.correct_answers = correct_answers
      qData.pairs = matchPairs.value.map(p => ({ left: p.left.trim(), right: p.right.trim() }))
      qData.options = matchPairs.value.map(p => ({ left: p.left.trim(), right: p.right.trim() }))
      qData.correct_answer = Object.entries(correct_answers).map(([k, v]) => `${k}-${v}`).join(',')
      qData.columnA = 'Column A'
      qData.columnB = 'Column B'
      qData.marks_per_item = perItemMark
      qData.marks = totalMatchingMarks
      break;
    }
  }

  formStore.questions.push(qData)
  resetForm()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}


const handleNext = () => {
  // Determine if the instructor has started filling the manual question form
  const hasText = activeSourceTab.value === 'manual' && (
    questionType.value === 'Matching'
      ? matchPairs.value.some(p => p.left.replace(/<[^>]*>?/gm, '').trim() || p.right.replace(/<[^>]*>?/gm, '').trim())
      : questionText.value.replace(/<[^>]*>?/gm, '').trim()
  )

  if (hasText) {
    // Attempt to add - this runs full validation
    addQuestionToExam()
    // If validation failed, validationError will be set - do NOT navigate
    if (validationError.value) return
    // If addQuestionToExam succeeded, the form is reset - now we can navigate
  }

  // Require at least 1 question before advancing
  if (formStore.questions.length === 0) {
    validationError.value = 'Please add at least one question before proceeding to Exam Settings.'
    return
  }

  validationError.value = ''
  emit('next')
}

// ── Added questions info ──
const removeQuestion = (index: number) => {
  formStore.questions.splice(index, 1)
}

const getQuestionTypeLabel = (type: string) => {
  switch (type) {
    case 'multiple_choice': return 'MCQ'
    case 'true_false': return 'True/False'
    case 'fill_blank': return 'Fill in Blank'
    case 'short_answer': return 'Short Answer'
    case 'matching': return 'Matching'
    default: return type
  }
}
</script>

<template>
  <div class="space-y-6 w-full">
    
    <!-- Question Source Selector (Manual, Question Bank, Import from File) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-2 shadow-xs">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
        <!-- Tab 1: Manual Input -->
        <button
          type="button"
          @click="activeSourceTab = 'manual'"
          :class="activeSourceTab === 'manual' ? 'bg-[#5138ed] text-white shadow-sm ring-1 ring-indigo-600' : 'bg-slate-50 hover:bg-slate-100 text-slate-700'"
          class="flex items-center gap-3 p-3.5 rounded-xl transition-all text-left cursor-pointer group"
        >
          <div :class="activeSourceTab === 'manual' ? 'bg-white/20 text-white' : 'bg-indigo-100 text-[#5138ed]'" class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <p class="text-[13px] font-bold">1. Manual Input</p>
              <span v-if="activeSourceTab === 'manual'" class="text-[10px] font-bold uppercase bg-white/20 px-2 py-0.5 rounded-full">Active</span>
            </div>
            <p :class="activeSourceTab === 'manual' ? 'text-indigo-100' : 'text-slate-400'" class="text-[11px] truncate mt-0.5">Write custom questions directly</p>
          </div>
        </button>

        <!-- Tab 2: From Question Bank -->
        <button
          type="button"
          @click="activeSourceTab = 'bank'"
          :class="activeSourceTab === 'bank' ? 'bg-[#5138ed] text-white shadow-sm ring-1 ring-indigo-600' : 'bg-slate-50 hover:bg-slate-100 text-slate-700'"
          class="flex items-center gap-3 p-3.5 rounded-xl transition-all text-left cursor-pointer group"
        >
          <div :class="activeSourceTab === 'bank' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-600'" class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <p class="text-[13px] font-bold">2. From Question Bank</p>
              <span v-if="activeSourceTab === 'bank'" class="text-[10px] font-bold uppercase bg-white/20 px-2 py-0.5 rounded-full">Active</span>
            </div>
            <p :class="activeSourceTab === 'bank' ? 'text-indigo-100' : 'text-slate-400'" class="text-[11px] truncate mt-0.5">Select from question repositories</p>
          </div>
        </button>

        <!-- Tab 3: Import from File -->
        <button
          type="button"
          @click="activeSourceTab = 'file'"
          :class="activeSourceTab === 'file' ? 'bg-[#5138ed] text-white shadow-sm ring-1 ring-indigo-600' : 'bg-slate-50 hover:bg-slate-100 text-slate-700'"
          class="flex items-center gap-3 p-3.5 rounded-xl transition-all text-left cursor-pointer group"
        >
          <div :class="activeSourceTab === 'file' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-600'" class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <p class="text-[13px] font-bold">3. Import from File</p>
              <span v-if="activeSourceTab === 'file'" class="text-[10px] font-bold uppercase bg-white/20 px-2 py-0.5 rounded-full">Active</span>
            </div>
            <p :class="activeSourceTab === 'file' ? 'text-indigo-100' : 'text-slate-400'" class="text-[11px] truncate mt-0.5">Upload Excel (.xlsx) or CSV file</p>
          </div>
        </button>
      </div>
    </div>

    <!-- 1. MANUAL QUESTION INPUT SECTION -->
    <div v-show="activeSourceTab === 'manual'" class="space-y-6">
    <!-- Base Information -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-[#5138ed]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
          </div>
          <h2 class="text-[15px] font-bold text-slate-800">Dynamic Question Details</h2>
        </div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
          <label class="block text-[12px] font-bold text-slate-800 mb-2">Question Type <span class="text-rose-500">*</span></label>
          <select v-model="questionType" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] text-slate-700 font-semibold focus:outline-none focus:border-[#5138ed] appearance-none cursor-pointer">
            <option>Multiple Choice (MCQ)</option>
            <option>True / False</option>
            <option>Fill in the Blank</option>
            <option>Short Answer</option>
            <option>Matching</option>
          </select>
        </div>
        <div>
          <label class="block text-[12px] font-bold text-slate-800 mb-2">Difficulty <span class="text-slate-400 font-normal">(Optional)</span></label>
          <select v-model="difficulty" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] appearance-none cursor-pointer">
            <option>Easy</option>
            <option>Medium</option>
            <option>Hard</option>
          </select>
        </div>
        <div>
          <label class="block text-[12px] font-bold text-slate-800 mb-2">
            Score Marks <span class="text-rose-500">*</span>
            <span v-if="questionType === 'Matching'" class="text-[#5138ed] font-semibold text-[11px] block sm:inline sm:ml-1">
              (per Column A item: {{ activeColACount }} × {{ score || 0 }} = {{ activeColACount * (Number(score) || 0) }} total)
            </span>
          </label>
          <input v-model="score" type="number" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" min="1" />
        </div>
        <div>
          <label class="block text-[12px] font-bold text-slate-800 mb-2">Chapter <span class="text-slate-400 font-normal">(Optional)</span></label>
          <div class="flex items-center">
            <span class="px-3 py-2.5 bg-slate-100 border border-slate-200 border-r-0 rounded-l-xl text-[13px] text-slate-500 font-medium whitespace-nowrap">Chapter</span>
            <input v-model="chapter" type="number" min="1" class="w-full bg-white border border-slate-200 rounded-r-xl px-3 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" placeholder="e.g. 1" />
          </div>
        </div>
      </div>
    </div>

    <!-- Shared Toolbar (ONE toolbar for ALL fields) - moved to root so it stays sticky across all sections -->
    <SharedEditorToolbar class="mb-2" />

    <!-- Question Content -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
          <label class="block text-[12px] font-bold text-slate-800 mb-2">Instructor Instruction <span class="text-rose-500">*</span></label>
          <input type="text" v-model="title" list="exam-instruction-options" placeholder="e.g., Choose the correct answer" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:border-[#5138ed] transition-colors shadow-sm" />
          <datalist id="exam-instruction-options">
            <option v-for="inst in previousInstructions" :key="inst" :value="inst"></option>
          </datalist>
          <p v-if="previousInstructions.length > 0" class="text-[11px] text-indigo-500 mt-1.5 font-medium">💡 Questions sharing the same instruction will be grouped together in the exam.</p>
        </div>
        <div>
          <label class="block text-[12px] font-bold text-slate-800 mb-2">Question Description (Optional)</label>
          <input v-model="description" type="text" placeholder="Additional context..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" />
        </div>
      </div>

      <div v-if="questionType !== 'Matching'">
        <label class="block text-[12px] font-bold text-slate-800 mb-2">Question Text <span class="text-rose-500">*</span></label>
        <MinimalEditor v-model="questionText" placeholder="Type your question content here..." label="Question Text" minHeight="160px" />
      </div>

      <!-- Matching Question Editor -->
      <div v-else>
        <div class="mb-4">
          <h3 class="text-[14px] font-bold text-slate-900">Question Writing</h3>
          <p class="text-[12px] text-slate-500">Write the matching statements that students will see during the exam.</p>
        </div>
        
        <!-- Shared Editor -->
        <div class="mb-6 bg-slate-50 border border-slate-200 rounded-xl p-4 pt-6 relative" v-if="activeMatchingCell">
          <div class="absolute -top-3 left-4 px-2 py-0.5 bg-[#5138ed] text-white text-[10px] font-bold uppercase rounded shadow-sm">
            Currently Editing: Column {{ activeMatchingCell.col === 'left' ? 'A' : 'B' }} • Row {{ activeMatchingCell.rowIndex + 1 }}
          </div>
          <MinimalEditor v-model="sharedEditorContent" placeholder="Type your content here..." label="Matching Content" minHeight="120px" />
        </div>

        <div class="space-y-4">
          <div class="grid grid-cols-[1fr_1fr_auto] gap-6 font-bold text-[11px] text-slate-500">
            <div>Column A (Questions / Prompts) <span class="text-rose-500">*</span></div>
            <div>Column B (Items to Match) <span class="text-rose-500">*</span></div>
            <div class="w-8"></div>
          </div>

          <div v-for="(pair, index) in matchPairs" :key="pair.id" class="grid grid-cols-[1fr_1fr_auto] gap-6 items-center">
            <!-- Column A -->
            <div class="flex items-start gap-3">
              <div class="w-6 shrink-0 mt-3 font-bold text-slate-400 text-sm text-right">{{ index + 1 }}.</div>
              <div 
                @click="activeMatchingCell = { rowIndex: index, col: 'left' }"
                :class="[ 
                  'w-full min-h-[60px] max-h-[120px] overflow-y-auto bg-white border rounded-xl p-3 text-[13px] text-slate-700 cursor-pointer transition-all prose prose-sm',
                  activeMatchingCell?.rowIndex === index && activeMatchingCell?.col === 'left' ? 'border-[#5138ed] ring-1 ring-[#5138ed] shadow-sm' : 'border-slate-200 hover:border-slate-300'
                ]"
              >
                <div v-if="pair.left" v-html="pair.left"></div>
                <div v-else class="text-slate-400 italic mt-1">Click to edit...</div>
              </div>
            </div>

            <!-- Column B -->
            <div class="flex items-start gap-3">
              <div class="w-6 shrink-0 mt-3 font-bold text-slate-400 text-sm text-right">{{ getLetterLabel(index) }}.</div>
              <div 
                @click="activeMatchingCell = { rowIndex: index, col: 'right' }"
                :class="[ 
                  'w-full min-h-[60px] max-h-[120px] overflow-y-auto bg-white border rounded-xl p-3 text-[13px] text-slate-700 cursor-pointer transition-all prose prose-sm',
                  activeMatchingCell?.rowIndex === index && activeMatchingCell?.col === 'right' ? 'border-[#5138ed] ring-1 ring-[#5138ed] shadow-sm' : 'border-slate-200 hover:border-slate-300'
                ]"
              >
                <div v-if="pair.right" v-html="pair.right"></div>
                <div v-else class="text-slate-400 italic mt-1">Click to edit...</div>
              </div>
            </div>

            <button @click="removeMatchPair(index)" class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg self-start mt-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
          </div>
        </div>
        <button @click="addMatchPair" class="mt-4 px-4 py-2 bg-indigo-50 text-[#5138ed] border border-indigo-100 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-colors">+ Add Matching Row</button>
      </div>
    </div>

    <!-- Matching Answer Options -->
    <div v-if="questionType === 'Matching'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
      <div class="flex flex-col mb-4 pb-4 border-b border-slate-100">
        <h3 class="text-sm font-bold text-slate-800">Answer Options <span class="text-rose-500">*</span></h3>
        <div class="flex items-center justify-between mt-1">
          <p class="text-[12px] text-slate-500">Assign the correct matching pairs for automatic grading.</p>
          <label class="flex items-center gap-2 text-[12px] text-slate-700 cursor-pointer">
            <input type="checkbox" v-model="matchShuffle" class="rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" /> Shuffle Right Side Automatically
          </label>
        </div>
      </div>

      <div class="grid grid-cols-[1fr_auto] gap-6 mb-2 font-bold text-[11px] text-slate-500 px-2">
        <div>Column A (Prompt)</div><div>Correct Match</div>
      </div>

      <div class="space-y-3">
        <template v-for="(_, index) in matchMappings" :key="index">
          <div v-if="matchPairs[index] && matchPairs[index].left.replace(/<[^>]*>?/gm, '').trim()" class="grid grid-cols-[1fr_auto] gap-6 items-center bg-slate-50/50 p-3 rounded-xl border border-slate-200">
            <div class="text-[13px] text-slate-700 font-medium truncate flex items-center gap-2">
              <span class="font-bold text-slate-400 w-4">{{ index + 1 }}.</span>
              {{ matchPairs[index].left.replace(/<[^>]*>?/gm, '').substring(0, 80) }}
            </div>
            <div class="w-72">
              <select v-model="matchMappings[index]" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-[13px] text-slate-700 font-bold focus:outline-none focus:border-[#5138ed] appearance-none cursor-pointer shadow-sm">
                <option v-for="(pair, i) in matchPairs" :key="i" :value="getLetterLabel(i)">
                  {{ getLetterLabel(i) }} — {{ pair.right.replace(/<[^>]*>?/gm, '').substring(0, 40) || 'Empty' }}
                </option>
              </select>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- 1. Multiple Choice (MCQ) -->
    <div v-if="questionType === 'Multiple Choice (MCQ)'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
        <label class="block text-sm font-bold text-slate-800">Answer Options <span class="text-rose-500">*</span></label>
        <label class="flex items-center gap-2 text-[12px] text-slate-700 cursor-pointer">
          <input type="checkbox" v-model="shuffleChoices" class="rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" /> Shuffle Choices Automatically
        </label>
      </div>
      
      <div class="space-y-4 mb-4">
        <div 
          v-for="(option, index) in options" 
          :key="option.id" 
          class="flex items-start gap-4 p-4 rounded-xl border transition-colors relative group"
          :class="correctAnswer === option.label ? 'border-emerald-400 bg-emerald-50/30' : 'border-slate-200 bg-slate-50'"
          draggable="true"
          @dragstart="onDragStart($event, index)"
          @dragover.prevent
          @drop="onDrop($event, index)"
        >
          <div class="cursor-grab hover:text-slate-700 text-slate-400 mt-2" title="Drag to reorder">
            <GripVertical class="w-5 h-5" />
          </div>

          <div class="flex flex-col items-center gap-2 mt-2">
            <div class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 shadow-sm text-[13px] font-extrabold text-slate-700">{{ option.label }}</div>
            <button 
              @click="setCorrectAnswer(option.label)" 
              :class="correctAnswer === option.label ? 'bg-emerald-500 text-white shadow-md shadow-emerald-200' : 'bg-slate-200 hover:bg-slate-300 text-transparent'"
              class="w-6 h-6 rounded-full flex items-center justify-center transition-all"
              title="Mark as Correct Answer"
            >
              <svg v-if="correctAnswer === option.label" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </button>
          </div>

          <div class="flex-1">
            <MinimalEditor v-model="option.text" :placeholder="'Write option ' + option.label + '...'" :label="'Option ' + option.label" minHeight="80px" />
          </div>
          <button @click="removeOption(index)" class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
        </div>
      </div>
      <button @click="addOption" class="px-4 py-2 bg-indigo-50 text-[#5138ed] border border-indigo-100 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-colors">+ Add Option</button>
    </div>

    <!-- 2. True / False -->
    <div v-if="questionType === 'True / False'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
      <label class="block text-sm font-bold text-slate-800 mb-3">Select Correct Answer <span class="text-rose-500">*</span></label>
      <div class="flex items-center gap-3">
        <button @click="setTrueFalseAnswer('True')" :class="correctAnswer === 'True' ? 'bg-emerald-50 border-emerald-400 text-emerald-700 ring-1 ring-emerald-400' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'" class="flex items-center gap-2.5 px-6 py-3 border rounded-xl transition-all shadow-sm">
          <svg class="w-5 h-5" :class="correctAnswer === 'True' ? 'text-emerald-500' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span class="text-[13px] font-bold">True</span>
        </button>
        <button @click="setTrueFalseAnswer('False')" :class="correctAnswer === 'False' ? 'bg-emerald-50 border-emerald-400 text-emerald-700 ring-1 ring-emerald-400' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'" class="flex items-center gap-2.5 px-6 py-3 border rounded-xl transition-all shadow-sm">
          <svg class="w-5 h-5" :class="correctAnswer === 'False' ? 'text-emerald-500' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span class="text-[13px] font-bold">False</span>
        </button>
      </div>
    </div>

    <!-- 3. Fill in the Blank -->
    <div v-if="questionType === 'Fill in the Blank'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
        <div>
          <h3 class="text-sm font-bold text-slate-800">Acceptable Answers <span class="text-rose-500">*</span></h3>
          <p class="text-xs text-slate-500 mt-0.5">Provide all possible correct text variations (e.g., "HTML", "html", "Hypertext Markup Language").</p>
        </div>
        <label class="flex items-center gap-2 text-[12px] text-slate-700 cursor-pointer">
          <input type="checkbox" v-model="fibCaseSensitive" class="rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" /> Case Sensitive Match
        </label>
      </div>
      
      <div class="space-y-3 mb-4">
        <div v-for="(ans, index) in fibAnswers" :key="ans.id" class="flex items-center gap-3">
          <div class="flex-1 relative">
            <input v-model="ans.text" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-10 py-3 text-[13px] text-slate-800 font-medium focus:outline-none focus:border-[#5138ed] focus:bg-white transition-colors" placeholder="Enter acceptable answer..." />
            <svg class="w-5 h-5 text-emerald-500 absolute right-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
          </div>
          <button v-if="fibAnswers.length > 1" @click="removeFibAnswer(index)" class="p-3 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
        </div>
      </div>
      <button @click="addFibAnswer" class="px-4 py-2 bg-indigo-50 text-[#5138ed] border border-indigo-100 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-colors">+ Add Alternative Answer</button>
    </div>

    <!-- 4. Short Answer -->
    <div v-if="questionType === 'Short Answer'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
      <div class="mb-6">
        <label class="block text-[12px] font-bold text-slate-800 mb-2">Expected Answer / Rubric <span class="text-rose-500">*</span></label>
        <textarea v-model="saExpectedAnswer" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:bg-white transition-colors resize-none" placeholder="Outline the key points required for full credit..."></textarea>
      </div>
      <div class="flex items-center gap-4">
        <label class="text-[12px] font-bold text-slate-800">Max Word Count Limit:</label>
        <input v-model="saMaxLength" type="number" class="w-24 bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-[13px] text-center text-slate-700 focus:outline-none focus:border-[#5138ed]" min="10" />
        <span class="text-xs text-slate-400">words</span>
      </div>
    </div>

    <!-- Inline Validation Error -->
    <div v-if="validationError" class="flex items-center gap-3 px-4 py-3 bg-rose-50 border border-rose-200 rounded-xl">
      <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <span class="text-[13px] font-semibold text-rose-700">{{ validationError }}</span>
      <button @click="clearError" class="ml-auto p-1 text-rose-400 hover:text-rose-600"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
    </div>
    </div> <!-- END 1. MANUAL QUESTION INPUT SECTION -->

    <!-- 2. FROM QUESTION BANK SECTION -->
    <div v-if="activeSourceTab === 'bank'">
      <SelectFromQuestionBank
        @added="onQuestionsAdded"
        @switch-to-manual="activeSourceTab = 'manual'"
        @switch-to-file="activeSourceTab = 'file'"
      />
    </div>

    <!-- 3. IMPORT FROM FILE SECTION -->
    <div v-if="activeSourceTab === 'file'">
      <ImportQuestionsFile
        @imported="onQuestionsAdded"
        @switch-to-manual="activeSourceTab = 'manual'"
        @switch-to-bank="activeSourceTab = 'bank'"
      />
    </div>

    <!-- QUESTIONS CURRENTLY ADDED TO DRAFT -->
    <AddedQuestionsList />

    <!-- Action Bar -->
    <div class="flex items-center justify-between pt-6 border-t border-slate-200">
      <div class="flex items-center gap-3">
        <button @click="emit('prev')" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-[13px] rounded-xl transition-colors flex items-center gap-2 cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Previous
        </button>
        <button
          v-if="activeSourceTab === 'manual'"
          type="button"
          @click="addQuestionToExam"
          class="px-6 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-[#5138ed] font-bold text-[13px] rounded-xl transition-colors shadow-sm border border-indigo-100 cursor-pointer"
        >
          + Add Question to Draft
        </button>
        <button
          v-else
          type="button"
          @click="activeSourceTab = 'manual'"
          class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-[13px] rounded-xl transition-colors cursor-pointer flex items-center gap-2"
        >
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
          Write Question Manually
        </button>
      </div>
      <div class="flex gap-3">
        <button @click="emit('cancel')" class="px-6 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-[13px] rounded-xl transition-colors cursor-pointer">Cancel</button>
        <button @click="handleNext" class="px-6 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl transition-colors flex items-center gap-2 shadow-sm cursor-pointer">
          Next: Exam Settings
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </button>
      </div>
    </div>

    <!-- Draft question count indicator (no preview, just a badge) -->
    <div v-if="formStore.questions.length > 0" class="flex items-center gap-2 pt-2">
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 border border-indigo-100 text-[#5138ed] text-[12px] font-bold rounded-lg">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
        {{ formStore.questions.length }} question{{ formStore.questions.length !== 1 ? 's' : '' }} added to this exam
      </span>
      <span v-if="previousInstructions.length > 0" class="text-[11px] text-slate-400">• {{ previousInstructions.length }} instruction group{{ previousInstructions.length !== 1 ? 's' : '' }}</span>
    </div>
  </div>
</template>
