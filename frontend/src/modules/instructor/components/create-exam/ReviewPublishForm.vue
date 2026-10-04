<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCreateExamStore } from '../../store/createExamStore'
import { useInstructorExamStore } from '../../store/instructorExamStore'
import { Pencil, Trash2 } from 'lucide-vue-next'
import MinimalEditor from './MinimalEditor.vue'
import SharedEditorToolbar from './SharedEditorToolbar.vue'
import apiClient from '../../../../core/api/apiClient'

const emit = defineEmits(['cancel', 'edit-step'])
const router = useRouter()
const formStore = useCreateExamStore()
const examStore = useInstructorExamStore()

const isSubmitting = ref(false)
const showQuestionsFullscreen = ref(false)
const isEditModeFullscreen = ref(false)
const showPublishConfirm = ref(false)

// Conflict / error state
const conflictError = ref<{
  title: string
  message: string
  conflictingExam: string
} | null>(null)
const generalError = ref<string | null>(null)

const showSingleEditModal = ref(false)
const editingQuestionIndex = ref(-1)
const editingQuestionData = ref<any>(null)

import { watch } from 'vue'

const activeMatchingCell = ref<{rowIndex: number, col: 'left'|'right'} | null>(null)
const sharedEditorContent = ref('')

watch(activeMatchingCell, (newCell, oldCell) => {
  if (oldCell && editingQuestionData.value?.pairs?.[oldCell.rowIndex]) {
    if (oldCell.col === 'left') editingQuestionData.value.pairs[oldCell.rowIndex].left = sharedEditorContent.value
    else editingQuestionData.value.pairs[oldCell.rowIndex].right = sharedEditorContent.value
  }
  if (newCell && editingQuestionData.value?.pairs?.[newCell.rowIndex]) {
    if (newCell.col === 'left') sharedEditorContent.value = editingQuestionData.value.pairs[newCell.rowIndex].left
    else sharedEditorContent.value = editingQuestionData.value.pairs[newCell.rowIndex].right
  } else {
    sharedEditorContent.value = ''
  }
}, { deep: true })

watch(sharedEditorContent, (val) => {
  if (activeMatchingCell.value && editingQuestionData.value?.pairs?.[activeMatchingCell.value.rowIndex]) {
    if (activeMatchingCell.value.col === 'left') {
      editingQuestionData.value.pairs[activeMatchingCell.value.rowIndex].left = val
    } else {
      editingQuestionData.value.pairs[activeMatchingCell.value.rowIndex].right = val
    }
  }
})

const getLetter = (index: number | string) => String.fromCharCode(65 + Number(index))

// Convert a letter label (A, B, C...) to a 0-based index
const letterToIndex = (letter: string): number => {
  if (!letter) return -1
  let result = 0
  for (let i = 0; i < letter.length; i++) {
    result = result * 26 + (letter.toUpperCase().charCodeAt(i) - 64)
  }
  return result - 1
}

// Get the text of a column_b item by its letter label
const getColumnBText = (columnB: any[], label: string): string => {
  if (!columnB || !columnB.length) return label
  // First try matching by label property
  const byLabel = columnB.find((b: any) => b.label === label)
  if (byLabel) {
    return (byLabel.text || byLabel.right || '').replace(/<[^>]*>/g, '').trim() || label
  }
  // Fallback: use letter as index (A=0, B=1, etc.)
  const idx = letterToIndex(label)
  if (idx >= 0 && columnB[idx]) {
    const item = columnB[idx]
    return (item.text || item.right || '').replace(/<[^>]*>/g, '').trim() || label
  }
  return label
}

// Get column_a item text by 1-based key
const getColumnAText = (columnA: any[], key: string): string => {
  if (!columnA) return 'Item ' + key
  const idx = Number(key) - 1
  if (idx < 0 || idx >= columnA.length) return 'Item ' + key
  const item = columnA[idx]
  if (typeof item === 'string') return item.replace(/<[^>]*>/g, '').trim() || ('Item ' + key)
  return (item.left || item.text || '').replace(/<[^>]*>/g, '').trim() || ('Item ' + key)
}

const getDifficultyClass = (diff: string) => {
  const d = (diff || '').toLowerCase()
  if (d === 'easy') return 'bg-emerald-50 text-emerald-600 border-emerald-100'
  if (d === 'hard') return 'bg-rose-50 text-rose-600 border-rose-100'
  return 'bg-amber-50 text-amber-600 border-amber-100'
}

const deleteQuestion = (index: number) => {
  if (confirm('Are you sure you want to delete this question?')) {
    formStore.questions.splice(index, 1)
  }
}

const editQuestion = (index: number) => {
  editingQuestionIndex.value = index
  const qData = JSON.parse(JSON.stringify(formStore.questions[index]))
  
  if (qData.type?.toLowerCase() === 'matching') {
    if (!qData.pairs) {
      qData.pairs = []
      const len = Math.max((qData.column_a || []).length, (qData.column_b || []).length)
      for (let i = 0; i < len; i++) {
        const colA = qData.column_a?.[i]
        const colB = qData.column_b?.[i]
        qData.pairs.push({
          left: typeof colA === 'string' ? colA : (colA?.left || colA?.text || ''),
          right: typeof colB === 'string' ? colB : (colB?.right || colB?.text || '')
        })
      }
    }
    
    if (!qData.correct_answers) {
      qData.correct_answers = {}
      qData.pairs.forEach((_: any, i: number) => {
        qData.correct_answers[(i + 1).toString()] = getLetter(i)
      })
    }

    const itemsLen = Math.max((qData.column_a || []).length, (qData.pairs || []).length, 1)
    qData.marks_per_item = qData.marks_per_item || (qData.marks ? Math.max(1, Math.round(qData.marks / itemsLen)) : 1)
  }

  editingQuestionData.value = qData
  showSingleEditModal.value = true
}

const saveEditedQuestion = () => {
  // Flush shared editor content if still editing
  if (activeMatchingCell.value && editingQuestionData.value?.pairs?.[activeMatchingCell.value.rowIndex]) {
    if (activeMatchingCell.value.col === 'left') {
      editingQuestionData.value.pairs[activeMatchingCell.value.rowIndex].left = sharedEditorContent.value
    } else {
      editingQuestionData.value.pairs[activeMatchingCell.value.rowIndex].right = sharedEditorContent.value
    }
  }

  if (editingQuestionIndex.value !== -1 && editingQuestionData.value) {
    if (editingQuestionData.value.type?.toLowerCase() === 'matching') {
      const correct_answers: Record<string, string> = {}
      const column_a: string[] = []
      const column_b: { label: string, text: string }[] = []
      const finalPairs: {left: string, right: string}[] = []

      let colAIndex = 1
      const pairs = editingQuestionData.value.pairs || []
      const existingAnswers = editingQuestionData.value.correct_answers || {}
      
      pairs.forEach((p: any, i: number) => {
        if ((p.left || '').replace(/<[^>]*>?/gm, '').trim() !== '') {
          correct_answers[colAIndex.toString()] = existingAnswers[(i + 1).toString()] || getLetterLabel(i)
          column_a.push((p.left || '').trim())
          colAIndex++
        }
        if ((p.right || '').replace(/<[^>]*>?/gm, '').trim() !== '') {
          column_b.push({ label: getLetterLabel(i), text: (p.right || '').trim() })
        }
        finalPairs.push({ left: (p.left || '').trim(), right: (p.right || '').trim() })
      })

      const itemsCount = column_a.length > 0 ? column_a.length : 1
      const perItemMark = Number(editingQuestionData.value.marks_per_item || editingQuestionData.value.marks || 1)
      
      editingQuestionData.value.column_a = column_a
      editingQuestionData.value.column_b = column_b
      editingQuestionData.value.correct_answers = correct_answers
      editingQuestionData.value.pairs = finalPairs
      editingQuestionData.value.options = finalPairs
      editingQuestionData.value.correct_answer = Object.entries(correct_answers).map(([k, v]) => `${k}-${v}`).join(',')
      editingQuestionData.value.columnA = 'Column A'
      editingQuestionData.value.columnB = 'Column B'
      editingQuestionData.value.marks_per_item = perItemMark
      editingQuestionData.value.marks = itemsCount * perItemMark
    }
    formStore.questions[editingQuestionIndex.value] = editingQuestionData.value
  }
  showSingleEditModal.value = false
}

const getLetterLabel = (index: number) => {
  let label = ''
  let num = index
  while (num >= 0) {
    label = String.fromCharCode(65 + (num % 26)) + label
    num = Math.floor(num / 26) - 1
  }
  return label
}

const getDisplayType = (type: string) => {
  let displayType = (type || '').toUpperCase()
  if (type === 'multiple_choice' || type === 'mcq') displayType = 'MULTIPLE CHOICE'
  if (type === 'true_false' || type === 'true/false') displayType = 'TRUE / FALSE'
  if (type === 'short_answer') displayType = 'SHORT ANSWER'
  if (type === 'fill_blank' || type === 'fill_in_the_blank') displayType = 'FILL IN THE BLANK'
  if (type === 'matching' || type === 'Matching') displayType = 'MATCHING'
  if (type === 'essay' || type === 'Essay') displayType = 'ESSAY'
  return displayType
}

const getQuestionIndexWithinType = (q: any) => {
  const targetType = getDisplayType(q.type)
  const sameTypeQuestions = formStore.questions.filter((x: any) => getDisplayType(x.type) === targetType)
  return sameTypeQuestions.findIndex((x: any) => x === q) + 1
}

const groupedQuestions = computed(() => {
  const groups: any[] = []
  formStore.questions.forEach((q: any) => {
    let displayType = getDisplayType(q.type)

    let typeGroup = groups.find((g: any) => g.questionType === displayType)
    if (!typeGroup) {
       typeGroup = { questionType: displayType, instructionGroups: [] }
       groups.push(typeGroup)
    }

    const instructionStr = q.instruction || ''
    let instGroup = typeGroup.instructionGroups.find((ig: any) => ig.instruction === instructionStr)
    if (!instGroup) {
       instGroup = { instruction: instructionStr, questions: [] }
       typeGroup.instructionGroups.push(instGroup)
    }
    instGroup.questions.push(q)
  })
  return groups
})

const questionTypeStats = computed(() => {
  const typeMap: Record<string, { label: string, icon: string, color: string, bg: string, border: string, count: number }> = {
    'MULTIPLE CHOICE': { label: 'Multiple Choice', icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z', color: 'text-indigo-600', bg: 'bg-indigo-50', border: 'border-indigo-100', count: 0 },
    'TRUE / FALSE': { label: 'True / False', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-emerald-600', bg: 'bg-emerald-50', border: 'border-emerald-100', count: 0 },
    'SHORT ANSWER': { label: 'Short Answer', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', color: 'text-amber-600', bg: 'bg-amber-50', border: 'border-amber-100', count: 0 },
    'FILL IN THE BLANK': { label: 'Fill in the Blank', icon: 'M4 6h16M4 12h8m-8 6h16', color: 'text-purple-600', bg: 'bg-purple-50', border: 'border-purple-100', count: 0 },
    'MATCHING': { label: 'Matching', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', color: 'text-teal-600', bg: 'bg-teal-50', border: 'border-teal-100', count: 0 },
  }
  formStore.questions.forEach((q: any) => {
    let key = getDisplayType(q.type)
    if (typeMap[key]) typeMap[key].count++
  })
  return Object.values(typeMap)
})


const getQuestionCount = (typeGroup: any) => {
  let count = 0
  if (typeGroup.instructionGroups) {
    typeGroup.instructionGroups.forEach((instGroup: any) => {
      if (instGroup.questions) count += instGroup.questions.length
    })
  }
  return count
}


const openQuestionsModal = (editMode = false) => {
  isEditModeFullscreen.value = editMode
  showQuestionsFullscreen.value = true
}

const handlePublish = async () => {
  if (isSubmitting.value) return // prevent duplicate submission
  isSubmitting.value = true
  conflictError.value = null
  generalError.value = null

  // Validate Total Marks vs sum of question marks
  const totalQuestionMarks = formStore.questions.reduce((sum: number, q: any) => sum + Number(q.marks || 1), 0)
  if (totalQuestionMarks !== Number(formStore.totalMarks)) {
    generalError.value = `Mark Mismatch: The total marks assigned to your questions (${totalQuestionMarks}) does not match the Total Marks set for the exam (${formStore.totalMarks}). Please adjust the question marks or update the exam Total Marks in Step 1.`
    isSubmitting.value = false
    showPublishConfirm.value = true
    return
  }

  try {
    const payload = {
      title: formStore.title || 'Untitled Exam',
      course_code: formStore.courseCode || 'NT-00',
      course_name: formStore.courseName || formStore.examType || 'Networking',
      section: formStore.section,
      duration_minutes: formStore.durationMinutes,
      total_marks: formStore.totalMarks,
      status: 'published' as const,
      scheduled_at: formStore.getScheduledAt(),
      questions: formStore.questions,
      settings: formStore.getSettingsPayload()
    }

    if (formStore.editingExamId) {
      // UPDATE existing exam
      const response = await apiClient.put(`/instructor/exams/${formStore.editingExamId}`, payload)
      const updated = response.data?.data ?? response.data
      const idx = examStore.exams.findIndex(e => e.id === formStore.editingExamId)
      if (idx !== -1) examStore.exams.splice(idx, 1, updated)
    } else {
      // CREATE new exam
      await examStore.createExam(payload)
    }

    // SUCCESS: navigate away and clean up
    formStore.reset()
    router.push('/instructor/exams')
  } catch (err: any) {
    // FAILURE: stay on page and show the error
    const status = err.response?.status
    const data = err.response?.data

    if (status === 409 || status === 422) {
      // Scheduling conflict or validation error
      const scheduledAtErrors = data?.errors?.scheduled_at
      const conflictDetail = Array.isArray(scheduledAtErrors) ? scheduledAtErrors[0] : null
      conflictError.value = {
        title: 'Exam Schedule Conflict',
        message: data?.message || 'This exam cannot be published because another exam is already scheduled during this time.',
        conflictingExam: conflictDetail || 'Please choose a different examination time.'
      }
    } else {
      generalError.value = data?.message || 'Something went wrong. Please try again.'
    }
    // Keep confirm modal open so the instructor sees the error inline
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="mb-6">
    <!-- Header -->
    <div class="mb-5">
      <h2 class="text-[18px] font-bold text-slate-800">Review &amp; Publish</h2>
      <p class="text-[13px] text-slate-500 mt-1">Review all exam details before publishing. You can go back to previous steps to make changes.</p>
    </div>

    <!-- Question Stats Row -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
      <!-- Total Questions card -->
      <div class="rounded-2xl border border-violet-200 bg-violet-50 p-3 flex flex-col items-center gap-1.5 shadow-sm">
        <div class="w-8 h-8 rounded-xl bg-violet-100 flex items-center justify-center">
          <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
        </div>
        <span class="text-[22px] font-black text-violet-600">{{ formStore.questions.length }}</span>
        <span class="text-[10px] font-bold text-slate-500 text-center leading-tight">Total Questions</span>
      </div>

      <!-- Per-type cards (only for types that exist) -->
      <div
        v-for="stat in questionTypeStats"
        :key="stat.label"
        :class="['rounded-2xl border p-3 flex flex-col items-center gap-1.5 shadow-sm', stat.bg, stat.border]"
      >
        <div :class="['w-8 h-8 rounded-xl flex items-center justify-center', stat.bg]">
          <svg :class="['w-4 h-4', stat.color]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon" />
          </svg>
        </div>
        <span :class="['text-[22px] font-black', stat.color]">{{ stat.count }}</span>
        <span class="text-[10px] font-bold text-slate-500 text-center leading-tight">{{ stat.label }}</span>
      </div>
    </div>


    <!-- 2. Questions -->
    <div class="mb-8 flex flex-col gap-8 bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-[#5138ed]">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
            </div>
            <h3 class="text-[18px] font-bold text-slate-800">2. Added Questions ({{ formStore.questions.length }})</h3>
        </div>
        <button @click="emit('edit-step', 2)" class="px-5 py-2 bg-indigo-50 text-[#5138ed] text-[12px] font-bold rounded-xl hover:bg-indigo-100 transition-colors flex items-center gap-2">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Add Question
        </button>
      </div>

      <div v-if="formStore.questions.length === 0" class="p-16 text-center bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
        <div class="w-16 h-16 bg-white text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-slate-100">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </div>
        <h3 class="text-base font-bold text-slate-800 mb-1">No Questions Added</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mb-6">Go back to step 2 to add questions to your exam.</p>
        <button @click="emit('edit-step', 2)" class="px-5 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl hover:bg-indigo-600 transition-colors">
          Add Questions
        </button>
      </div>

      <div v-else class="space-y-12">
        <!-- Iterate over Question Types -->
        <div v-for="(typeGroup, typeIdx) in groupedQuestions" :key="typeGroup.questionType">
          
          <!-- TYPE HEADER -->
          <div class="mb-6 border-b-2 border-slate-100 pb-3 flex items-center justify-between">
            <h2 class="text-[15px] font-black text-slate-800 uppercase tracking-widest flex items-center gap-3">
              <span class="w-1.5 h-5 bg-[#5138ed] rounded-full inline-block shadow-[0_0_10px_rgba(81,56,237,0.4)]"></span>
              {{ typeGroup.questionType }}
            </h2>
          </div>

          <!-- Iterate over Instructions within Type -->
          <div class="space-y-10 pl-0 sm:pl-6 border-l-[3px] border-slate-50 ml-1">
            <div v-for="(instGroup, instIdx) in typeGroup.instructionGroups" :key="instIdx">
              
              <!-- INSTRUCTION HEADER -->
              <div class="mb-5 relative">
                <div class="absolute -left-[30px] top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-[3px] border-indigo-400 rounded-full"></div>
                <div v-if="instGroup.instruction">
                  <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-1">Instructor Instruction:</span>
                  <h3 class="text-[14px] font-bold text-slate-700 italic px-2">"{{ instGroup.instruction }}"</h3>
                </div>
                <div v-else>
                  <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-1">Instructor Instruction:</span>
                  <h3 class="text-[13px] font-bold text-slate-400 italic px-2">No specific instruction provided.</h3>
                </div>
              </div>

              <!-- Questions for this Instruction -->
              <div class="space-y-5 pl-4">
                <div 
                  v-for="(q, qIdx) in instGroup.questions" 
                  :key="qIdx" 
                  class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm shadow-slate-100 relative group"
                >
                  <!-- Question Metadata Bar -->
                  <div class="flex flex-wrap items-center justify-between gap-3 mb-6 border-b border-slate-50 pb-4">
                    <div class="flex items-center gap-3">
                      <!-- Question Number -->
                      <span class="w-7 h-7 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center text-[12px] font-black border border-indigo-100/50">
                        {{ getQuestionIndexWithinType(q) }}
                      </span>
                      <span :class="getDifficultyClass(q.difficulty)" class="px-2.5 py-1 text-[10px] uppercase font-black rounded-md border tracking-widest">
                        {{ q.difficulty || 'Medium' }}
                      </span>
                      <span class="px-2.5 py-1 bg-slate-50 border border-slate-100 text-slate-600 text-[10px] font-black uppercase rounded-md tracking-widest">
                        {{ q.marks || 1 }} Mark{{ (q.marks || 1) > 1 ? 's' : '' }}
                      </span>
                      <span v-if="q.chapter" class="px-2.5 py-1 bg-slate-50 text-slate-500 text-[10px] font-bold uppercase rounded-md tracking-widest border border-slate-100">
                        {{ q.chapter }}
                      </span>
                    </div>
                    
                    <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button @click="editQuestion(formStore.questions.findIndex((x: any) => x === q))" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 flex items-center justify-center transition-colors shadow-sm border border-slate-100" title="Edit Question">
                        <Pencil class="w-4 h-4" />
                      </button>
                      <button @click="deleteQuestion(formStore.questions.findIndex((x: any) => x === q))" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors shadow-sm border border-slate-100" title="Delete Question">
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </div>
                  </div>

                  <!-- QUESTION CONTENT RENDERERS -->
                  <div class="text-sm text-slate-800">
                    
                    <!-- 1. MULTIPLE CHOICE -->
                    <template v-if="q.type === 'multiple_choice' || q.type === 'mcq' || q.type === 'Multiple Choice (MCQ)'">
                      <div class="font-medium mb-5 text-[15px] leading-relaxed" v-html="q.text"></div>
                      <div v-if="q.image_url" class="mb-5">
                        <img :src="q.image_url" alt="Question Image" class="max-h-56 rounded-xl border border-slate-200 object-cover shadow-sm" />
                      </div>

                      <!-- Options -->
                      <div v-if="q.options && q.options.length" class="space-y-3 mb-6 ml-2">
                        <div
                          v-for="(opt, oIdx) in q.options"
                          :key="oIdx"
                          class="flex items-start gap-4 p-2 rounded-lg"
                        >
                          <span class="w-7 h-7 shrink-0 rounded-lg bg-slate-50 text-slate-500 text-[11px] font-black flex items-center justify-center border border-slate-200 shadow-sm mt-0.5 uppercase">
                            {{ opt.label || getLetterLabel(Number(oIdx)) }}
                          </span>
                          <span class="text-[14px] leading-relaxed pt-0.5 text-slate-600" v-html="opt.text || opt"></span>
                        </div>
                      </div>

                      <!-- Correct Answer Box -->
                      <div class="bg-indigo-50/40 border border-indigo-100/60 p-4 rounded-xl flex items-center gap-3 text-[12px]">
                        <span class="font-black text-slate-700 text-[12px] uppercase tracking-wider">Answer:</span>
                        <span class="font-bold text-[#5138ed] text-[14px]">
                          {{ q.correct_answer || q.correctAnswer }}
                        </span>
                      </div>
                    </template>

                    <!-- 1b. TRUE / FALSE -->
                    <template v-else-if="q.type === 'true_false' || q.type === 'true/false' || q.type === 'True / False'">
                      <div class="font-medium mb-5 text-[15px] leading-relaxed" v-html="q.text"></div>
                      <div v-if="q.image_url" class="mb-5">
                        <img :src="q.image_url" alt="Question Image" class="max-h-56 rounded-xl border border-slate-200 object-cover shadow-sm" />
                      </div>

                      <!-- True / False pill options -->
                      <div class="flex items-center gap-4 mb-6 ml-2">
                        <div class="flex items-center gap-2.5 px-5 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-600">
                          <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                          <span class="text-[13px] font-bold">True</span>
                        </div>
                        <div class="flex items-center gap-2.5 px-5 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-600">
                          <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                          <span class="text-[13px] font-bold">False</span>
                        </div>
                      </div>

                      <!-- Correct Answer Box — always shows True or False text -->
                      <div class="bg-indigo-50/40 border border-indigo-100/60 p-4 rounded-xl flex items-center gap-3 text-[12px]">
                        <span class="font-black text-slate-700 text-[12px] uppercase tracking-wider">Answer:</span>
                        <span class="font-bold text-[#5138ed] text-[14px]">
                          {{
                            (q.correct_answer === 'A' || q.correctAnswer === 'A') ? 'True'
                            : (q.correct_answer === 'B' || q.correctAnswer === 'B') ? 'False'
                            : (q.correct_answer === 'True' || q.correct_answer === 'true' || q.correctAnswer === 'True') ? 'True'
                            : (q.correct_answer === 'False' || q.correct_answer === 'false' || q.correctAnswer === 'False') ? 'False'
                            : (q.correct_answer || q.correctAnswer)
                          }}
                        </span>
                      </div>
                    </template>

                    <!-- 2. SHORT ANSWER / FILL IN THE BLANK -->
                    <template v-else-if="q.type === 'short_answer' || q.type === 'fill_in_the_blank' || q.type === 'fill_blank' || q.type === 'Short Answer' || q.type === 'Fill in the Blank'">
                      <div class="font-medium mb-5 text-[15px] leading-relaxed" v-html="q.text"></div>
                      <div v-if="q.image_url" class="mb-5">
                        <img :src="q.image_url" alt="Question Image" class="max-h-56 rounded-xl border border-slate-200 object-cover shadow-sm" />
                      </div>

                      <div class="bg-indigo-50/40 border border-indigo-100/60 p-4 rounded-xl flex items-center gap-3 text-[12px]">
                        <span class="font-black text-slate-700 text-[12px] uppercase tracking-wider">Answer:</span>
                        <span class="font-bold text-[#5138ed] text-[14px]">{{ q.correct_answer || q.sample_answer || q.sampleAnswer || (q.question_data && q.question_data.answers ? q.question_data.answers.map((a: any)=>a.text).join(' OR ') : '') }}</span>
                      </div>
                    </template>

                    <!-- 3. ESSAY -->
                    <template v-else-if="q.type === 'essay' || q.type === 'Essay'">
                      <div class="font-medium mb-5 text-[15px] leading-relaxed" v-html="q.text"></div>
                      <div v-if="q.image_url" class="mb-5">
                        <img :src="q.image_url" alt="Question Image" class="max-h-56 rounded-xl border border-slate-200 object-cover shadow-sm" />
                      </div>

                      <div class="bg-indigo-50/30 border border-indigo-100/60 p-5 rounded-xl text-[12px]">
                        <span class="font-black text-[#5138ed] uppercase tracking-wider text-[11px] block mb-3">Sample Answer:</span>
                        <div class="text-slate-600 leading-relaxed text-[13px]" v-html="q.sample_answer || q.sampleAnswer || 'No sample answer provided.'"></div>
                      </div>
                    </template>

                    <!-- 4. MATCHING -->
                    <template v-else-if="q.type === 'matching' || q.type === 'Matching'">
                      <div v-if="q.text" class="font-medium mb-5 text-[15px] leading-relaxed" v-html="q.text"></div>

                      <div class="grid grid-cols-1 md:grid-cols-2 gap-10 mb-8">
                        <!-- Column A -->
                        <div>
                          <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 pb-2 border-b border-slate-100">Column A</h4>
                          <div class="space-y-4">
                            <div v-for="(colA, cIdx) in (q.column_a || q.pairs || [])" :key="'l'+cIdx" class="flex gap-4">
                              <span class="font-bold text-slate-400 text-[14px] w-5 shrink-0 text-right">{{ Number(cIdx) + 1 }}.</span>
                              <div class="text-[13px] bg-slate-50 border border-slate-100 rounded-xl p-3 w-full flex-1 leading-relaxed text-slate-600" v-html="typeof colA === 'string' ? colA : colA.left"></div>
                            </div>
                          </div>
                        </div>

                        <!-- Column B -->
                        <div>
                          <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 pb-2 border-b border-slate-100">Column B</h4>
                          <div class="space-y-4">
                            <div v-for="(colB, cIdx) in (q.column_b || q.pairs || [])" :key="'r'+cIdx" class="flex gap-4 items-center">
                              <span class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 text-[12px] font-black flex items-center justify-center border border-slate-200 shrink-0 uppercase shadow-sm">
                                {{ colB.label || getLetterLabel(Number(cIdx)) }}
                              </span>
                              <div class="text-[13px] flex-1 text-slate-600 leading-relaxed bg-white" v-html="colB.text || colB.right"></div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Answer inline format -->
                      <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-[13px] flex flex-wrap items-center gap-x-5 gap-y-1">
                        <span class="font-black text-slate-700 uppercase tracking-wide text-[12px] shrink-0">Answer:</span>
                        <template v-for="(ans, key) in (q.correct_answers || {})" :key="key">
                          <span class="flex items-center gap-1 font-semibold text-slate-800">
                            <span class="text-slate-500 font-bold">{{ key }}</span>
                            <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            <span class="text-[#5138ed] font-bold">{{ ans }}</span>
                          </span>
                        </template>
                      </div>
                    </template>

                  </div>

                </div>
              </div>

            </div>
          </div>
          
        </div>
      </div>
    </div>

    <!-- Meta Info Row: 1, 3, 4 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <!-- 1. Exam Information -->
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col hover:border-indigo-100 transition-colors">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-md bg-indigo-50 flex items-center justify-center text-[#5138ed]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <h3 class="text-[13px] font-bold text-slate-800">1. Exam Info</h3>
          </div>
          <button @click="emit('edit-step', 1)" class="text-[11px] font-bold text-[#5138ed] hover:text-indigo-700 transition-colors">Edit</button>
        </div>
        <div class="space-y-2.5 flex-1">
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Title</span>
            <span class="text-[11px] font-medium text-slate-800 truncate max-w-[120px]" :title="formStore.title">{{ formStore.title || 'Untitled' }}</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Course</span>
            <span class="text-[11px] font-medium text-slate-800 truncate max-w-[120px]">{{ formStore.courseCode || 'SWE-301' }}</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Type</span>
            <span class="text-[10px] font-bold text-[#5138ed] bg-indigo-50 px-2 py-0.5 rounded">{{ formStore.examType }}</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Marks</span>
            <span class="text-[11px] font-medium text-slate-800">{{ formStore.totalMarks }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[11px] font-bold text-slate-500">Pass</span>
            <span class="text-[11px] font-medium text-slate-800">{{ formStore.passingMarks }} ({{ Math.round((formStore.passingMarks / formStore.totalMarks) * 100) || 0 }}%)</span>
          </div>
        </div>
      </div>

      <!-- 3. Exam Settings -->
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col hover:border-indigo-100 transition-colors">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-md bg-indigo-50 flex items-center justify-center text-[#5138ed]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <h3 class="text-[13px] font-bold text-slate-800">3. Exam Settings</h3>
          </div>
          <button @click="emit('edit-step', 3)" class="text-[11px] font-bold text-[#5138ed] hover:text-indigo-700 transition-colors">Edit</button>
        </div>
        <div class="space-y-2.5 flex-1">
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Duration</span>
            <span class="text-[11px] font-medium text-slate-800">{{ formStore.durationMinutes }} mins</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Scheduled</span>
            <span class="text-[11px] font-medium text-slate-800 truncate max-w-[120px]">{{ formStore.scheduledDate }} {{ formStore.scheduledTime }}</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Shuffle Q's</span>
            <span :class="['text-[11px] font-medium', formStore.shuffleQuestions ? 'text-emerald-600' : 'text-slate-400']">{{ formStore.shuffleQuestions ? 'Yes' : 'No' }}</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Shuffle A's</span>
            <span :class="['text-[11px] font-medium', formStore.shuffleAnswers ? 'text-emerald-600' : 'text-slate-400']">{{ formStore.shuffleAnswers ? 'Yes' : 'No' }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[11px] font-bold text-slate-500">Auto Submit</span>
            <span :class="['text-[11px] font-medium', formStore.autoSubmitOnTimeFinish ? 'text-emerald-600' : 'text-slate-400']">{{ formStore.autoSubmitOnTimeFinish ? 'Yes' : 'No' }}</span>
          </div>
        </div>
      </div>

      <!-- 4. Security Settings -->
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col hover:border-indigo-100 transition-colors">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-md bg-indigo-50 flex items-center justify-center text-[#5138ed]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <h3 class="text-[13px] font-bold text-slate-800">4. Security</h3>
          </div>
          <button @click="emit('edit-step', 3)" class="text-[11px] font-bold text-[#5138ed] hover:text-indigo-700 transition-colors">Edit</button>
        </div>
        <div class="space-y-2.5 flex-1">
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Fullscreen</span>
            <span :class="['text-[11px] font-medium', formStore.enableFullscreenMode ? 'text-emerald-600' : 'text-slate-400']">{{ formStore.enableFullscreenMode ? 'Yes' : 'No' }}</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Right Click</span>
            <span :class="['text-[11px] font-medium', !formStore.disableRightClick ? 'text-emerald-600' : 'text-slate-400']">{{ formStore.disableRightClick ? 'Disabled' : 'Enabled' }}</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Copy/Paste</span>
            <span :class="['text-[11px] font-medium', !formStore.disableCopyPaste ? 'text-emerald-600' : 'text-slate-400']">{{ formStore.disableCopyPaste ? 'Disabled' : 'Enabled' }}</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-50">
            <span class="text-[11px] font-bold text-slate-500">Tab Monitor</span>
            <span :class="['text-[11px] font-medium', formStore.enableBrowserTabMonitoring ? 'text-emerald-600' : 'text-slate-400']">{{ formStore.enableBrowserTabMonitoring ? 'Yes' : 'No' }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-[11px] font-bold text-slate-500">Calculator</span>
            <span :class="['text-[11px] font-medium', formStore.allowCalculator ? 'text-emerald-600' : 'text-slate-400']">{{ formStore.allowCalculator ? 'Yes' : 'No' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex items-center justify-between pt-2 pb-10">
      <button @click="emit('cancel')" class="px-6 py-2.5 border border-slate-200 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors">
        Cancel
      </button>
      
      <div class="flex items-center gap-3">
        <button class="px-6 py-2.5 border border-slate-200 text-[#5138ed] font-bold text-[13px] rounded-xl hover:border-indigo-200 hover:bg-indigo-50 transition-colors">
          Save as Draft
        </button>
        <button @click="showPublishConfirm = true" :disabled="isSubmitting" class="px-8 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-colors flex items-center gap-2 disabled:opacity-50">
          <span v-if="isSubmitting">{{ formStore.editingExamId ? 'Saving...' : 'Publishing...' }}</span>
          <span v-else class="flex items-center gap-2">
            {{ formStore.editingExamId ? 'Save Changes' : 'Publish Exam' }}
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
          </span>
        </button>
      </div>
    </div>
  </div>

  <!-- Publish Confirmation Modal -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showPublishConfirm" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @keydown.escape="showPublishConfirm = false; conflictError = null; generalError = null">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" @click.stop>

          <!-- CONFLICT STATE -->
          <div v-if="conflictError" class="p-6">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h3 class="text-[18px] font-bold text-slate-800 mb-2 text-center">{{ conflictError.title }}</h3>
            <p class="text-[13px] text-slate-500 mb-4 text-center">{{ conflictError.message }}</p>
            <div class="bg-red-50 border border-red-100 rounded-xl p-4 mb-5">
              <p class="text-[12px] font-bold text-red-600 uppercase tracking-widest mb-1">Conflicting Exam Details</p>
              <p class="text-[13px] text-slate-700">{{ conflictError.conflictingExam }}</p>
            </div>
            <p class="text-[12px] text-slate-400 text-center mb-4">Please go back to Step 3 and choose a different date/time for this exam.</p>
            <button @click="showPublishConfirm = false; conflictError = null" class="w-full px-5 py-2.5 border border-slate-200 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors">
              Close &amp; Edit Schedule
            </button>
          </div>

          <!-- GENERAL ERROR STATE -->
          <div v-else-if="generalError" class="p-6">
            <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 class="text-[18px] font-bold text-slate-800 mb-2 text-center">Something Went Wrong</h3>
            <p class="text-[13px] text-slate-500 mb-5 text-center">{{ generalError }}</p>
            <div class="flex gap-3">
              <button @click="showPublishConfirm = false; generalError = null" class="flex-1 px-5 py-2.5 border border-slate-200 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
              <button @click="handlePublish" :disabled="isSubmitting" class="flex-1 px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2 disabled:opacity-50">
                <span v-if="isSubmitting">Retrying...</span>
                <span v-else>Try Again</span>
              </button>
            </div>
          </div>

          <!-- DEFAULT CONFIRM STATE -->
          <div v-else class="p-6 text-center">
            <div class="w-16 h-16 bg-indigo-50 text-[#5138ed] rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h3 class="text-[18px] font-bold text-slate-800 mb-2">{{ formStore.editingExamId ? 'Save Changes' : 'Publish Exam' }}</h3>
            <p class="text-[13px] text-slate-500 mb-6">
              {{ formStore.editingExamId ? 'Are you sure you want to save these changes to the exam?' : 'Are you sure you want to publish this exam? Once published, it will be available to assigned students according to the schedule.' }}
            </p>
            <div class="flex gap-3">
              <button @click="showPublishConfirm = false" class="flex-1 px-5 py-2.5 border border-slate-200 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-slate-50 transition-colors">
                Cancel
              </button>
              <button @click="handlePublish" :disabled="isSubmitting" class="flex-1 px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2 disabled:opacity-50">
                <span v-if="isSubmitting">{{ formStore.editingExamId ? 'Saving...' : 'Publishing...' }}</span>
                <span v-else>{{ formStore.editingExamId ? 'Confirm Save' : 'Confirm Publish' }}</span>
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Questions Fullscreen Modal -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showQuestionsFullscreen" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @keydown.escape="showQuestionsFullscreen = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl h-[90vh] flex flex-col overflow-hidden">

          <!-- Modal Header -->
          <div class="flex items-center justify-between px-8 py-5 border-b border-slate-100 shrink-0 bg-gradient-to-r from-indigo-50/60 to-white">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-[#5138ed] flex items-center justify-center text-white shadow-lg shadow-indigo-200/50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
              </div>
              <div>
                <h2 class="text-[16px] font-bold text-slate-800">All Questions</h2>
                <p class="text-[12px] text-slate-500 mt-0.5">{{ formStore.questions.length }} question{{ formStore.questions.length !== 1 ? 's' : '' }} · {{ formStore.totalMarks }} total marks</p>
              </div>
            </div>
            <button @click="showQuestionsFullscreen = false" class="w-9 h-9 flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <!-- Questions List -->
          <div class="overflow-y-auto flex-1 px-8 py-6 space-y-5">
            <div v-if="formStore.questions.length === 0" class="flex flex-col items-center justify-center h-full py-20 text-slate-400">
              <svg class="w-12 h-12 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
              <p class="text-[14px] font-medium">No questions added yet.</p>
            </div>

            <div v-for="(q, idx) in formStore.questions" :key="idx" class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-indigo-100 transition-all">
              
              <template v-if="isEditModeFullscreen">
                <!-- Edit Mode -->
                <div class="flex flex-col gap-4">
                  <!-- Header: Number & Type & Marks -->
                  <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-[#5138ed] text-white text-[11px] font-black flex items-center justify-center shrink-0">{{ idx + 1 }}</div>
                    <span v-if="q.type === 'mcq' || q.type === 'multiple_choice'" class="px-2 py-0.5 rounded-md bg-indigo-50 text-[#5138ed] text-[10px] font-bold uppercase tracking-wide">MCQ</span>
                    <span v-else-if="q.type === 'true_false' || q.type === 'true/false'" class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-wide">True / False</span>
                    <span v-else-if="q.type === 'short_answer'" class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-600 text-[10px] font-bold uppercase tracking-wide">Short Answer</span>
                    <span v-else-if="q.type === 'essay'" class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-500 text-[10px] font-bold uppercase tracking-wide">Essay</span>
                    <span v-else class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-wide">{{ q.type }}</span>
                    
                    <div class="flex items-center gap-1.5 ml-auto">
                      <label class="text-[11px] font-bold text-slate-500">Marks:</label>
                      <input type="number" v-model.number="q.marks" class="w-16 px-2 py-1 border border-slate-200 rounded-md text-[12px] font-semibold text-slate-800 focus:outline-none focus:border-[#5138ed]" min="1" />
                    </div>
                  </div>

                  <!-- Instruction Input -->
                  <div class="ml-10">
                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Instruction (Optional)</label>
                    <input type="text" v-model="q.instruction" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-[12px] text-slate-600 focus:outline-none focus:border-[#5138ed]" placeholder="e.g. Choose the correct answer..." />
                  </div>

                  <!-- Question Text Input -->
                  <div class="ml-10">
                    <textarea v-model="q.text" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-[13px] font-medium text-slate-800 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] resize-none" placeholder="Enter question text..."></textarea>
                  </div>

                  <!-- MCQ Options Input -->
                  <div v-if="(q.type === 'mcq' || q.type === 'multiple_choice') && (q.options || q.choices)" class="ml-10 space-y-2">
                    <div v-for="(_, oIdx) in (q.options || q.choices)" :key="oIdx" class="flex items-center gap-3">
                      <input type="radio" :name="'correct_' + idx" :value="getLetter(oIdx)" @change="q.correct_answer = getLetter(oIdx)" :checked="q.correct_answer === getLetter(oIdx) || q.correctAnswer === getLetter(oIdx)" class="w-4 h-4 text-[#5138ed] focus:ring-[#5138ed] cursor-pointer" title="Mark as correct answer" />
                      <input type="text" v-model="q.options[oIdx]" class="flex-1 px-3 py-2 border border-slate-200 rounded-lg text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] transition-colors" placeholder="Option text..." />
                    </div>
                  </div>

                  <!-- True/False Options Input -->
                  <div v-else-if="q.type === 'true_false' || q.type === 'true/false'" class="ml-10 flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="radio" :name="'correct_' + idx" value="A" @change="q.correct_answer = 'A'" :checked="q.correct_answer === 'A' || q.correctAnswer === 'A' || q.correct_answer === true || q.correctAnswer === true || q.correct_answer === 'true' || q.correctAnswer === 'true'" class="w-4 h-4 text-[#5138ed] focus:ring-[#5138ed]" />
                      <span class="text-[13px] font-medium text-slate-700">True</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="radio" :name="'correct_' + idx" value="B" @change="q.correct_answer = 'B'" :checked="q.correct_answer === 'B' || q.correctAnswer === 'B' || q.correct_answer === false || q.correctAnswer === false || q.correct_answer === 'false' || q.correctAnswer === 'false'" class="w-4 h-4 text-[#5138ed] focus:ring-[#5138ed]" />
                      <span class="text-[13px] font-medium text-slate-700">False</span>
                    </label>
                  </div>

                  <!-- Sample Answer Input -->
                  <div v-else-if="q.type === 'short_answer' || q.type === 'essay'" class="ml-10">
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Sample Answer (Optional)</label>
                    <input type="text" v-model="q.sample_answer" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" placeholder="Enter a sample answer..." />
                  </div>
                </div>
              </template>
              <template v-else>
                <!-- View Mode -->
                <!-- Question Header -->
                <div class="flex items-start gap-4 mb-4">
                  <div class="w-7 h-7 rounded-lg bg-[#5138ed] text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">{{ idx + 1 }}</div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                      <!-- Type badge -->
                      <span v-if="q.type === 'mcq' || q.type === 'multiple_choice'" class="px-2 py-0.5 rounded-md bg-indigo-50 text-[#5138ed] text-[10px] font-bold uppercase tracking-wide">MCQ</span>
                      <span v-else-if="q.type === 'true_false' || q.type === 'true/false'" class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-wide">True / False</span>
                      <span v-else-if="q.type === 'short_answer'" class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-600 text-[10px] font-bold uppercase tracking-wide">Short Answer</span>
                      <span v-else-if="q.type === 'essay'" class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-500 text-[10px] font-bold uppercase tracking-wide">Essay</span>
                      <span v-else-if="q.type === 'fill_blank'" class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-600 text-[10px] font-bold uppercase tracking-wide">Fill in the Blank</span>
                      <span v-else-if="q.type === 'matching'" class="px-2 py-0.5 rounded-md bg-teal-50 text-teal-600 text-[10px] font-bold uppercase tracking-wide">Matching</span>
                      <span v-else class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-wide">{{ q.type }}</span>
                      <!-- Marks badge -->
                      <span class="px-2 py-0.5 rounded-md bg-slate-50 text-slate-500 text-[10px] font-bold border border-slate-100">{{ q.marks || q.points || 1 }} mark{{ (q.marks || q.points || 1) > 1 ? 's' : '' }}</span>
                    </div>
                    <!-- Instruction -->
                    <p v-if="q.instruction" class="text-[11px] font-semibold text-indigo-500 mb-1 italic">📋 {{ q.instruction }}</p>
                    <p class="text-[14px] font-semibold text-slate-800 leading-snug">{{ q.text || q.question }}</p>
                  </div>
                </div>

                <!-- MCQ Options -->
                <div v-if="(q.type === 'mcq' || q.type === 'multiple_choice') && (q.options || q.choices)" class="ml-11 space-y-2">
                  <div v-for="(opt, oIdx) in (q.options || q.choices)" :key="oIdx"
                    :class="[
                      'flex items-center gap-3 px-4 py-2.5 rounded-xl border text-[13px] font-medium transition-colors',
                      (opt === q.correctAnswer || opt === q.correct_answer || oIdx === q.correctAnswerIndex || oIdx === q.correct_answer_index || getLetter(oIdx) === q.correct_answer || getLetter(oIdx) === q.correctAnswer)
                        ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                        : 'bg-slate-50 border-slate-100 text-slate-600'
                    ]"
                  >
                    <div :class="[
                      'w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0',
                      (opt === q.correctAnswer || opt === q.correct_answer || oIdx === q.correctAnswerIndex || oIdx === q.correct_answer_index || getLetter(oIdx) === q.correct_answer || getLetter(oIdx) === q.correctAnswer)
                        ? 'border-emerald-500 bg-emerald-500'
                        : 'border-slate-300 bg-white'
                    ]">
                      <svg v-if="opt === q.correctAnswer || opt === q.correct_answer || oIdx === q.correctAnswerIndex || oIdx === q.correct_answer_index || getLetter(oIdx) === q.correct_answer || getLetter(oIdx) === q.correctAnswer" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <span>{{ typeof opt === 'object' ? (opt.text || opt.label || opt.value) : opt }}</span>
                    <span v-if="opt === q.correctAnswer || opt === q.correct_answer || oIdx === q.correctAnswerIndex || oIdx === q.correct_answer_index || getLetter(oIdx) === q.correct_answer || getLetter(oIdx) === q.correctAnswer" class="ml-auto text-[10px] font-bold text-emerald-600 bg-emerald-100 px-1.5 py-0.5 rounded">Correct</span>
                  </div>
                </div>

                <!-- True / False Options -->
                <div v-else-if="q.type === 'true_false' || q.type === 'true/false'" class="ml-11 flex gap-3">
                  <div :class="[
                    'flex items-center gap-2 px-4 py-2.5 rounded-xl border text-[13px] font-medium flex-1 justify-center',
                    (q.correctAnswer === true || q.correctAnswer === 'true' || q.correct_answer === true || q.correct_answer === 'true' || q.correct_answer === 'A' || q.correctAnswer === 'A')
                      ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                      : 'bg-slate-50 border-slate-100 text-slate-600'
                  ]">
                    <div :class="[
                      'w-5 h-5 rounded-full border-2 flex items-center justify-center',
                      (q.correctAnswer === true || q.correctAnswer === 'true' || q.correct_answer === true || q.correct_answer === 'true' || q.correct_answer === 'A' || q.correctAnswer === 'A')
                        ? 'border-emerald-500 bg-emerald-500'
                        : 'border-slate-300 bg-white'
                    ]">
                      <svg v-if="q.correctAnswer === true || q.correctAnswer === 'true' || q.correct_answer === true || q.correct_answer === 'true' || q.correct_answer === 'A' || q.correctAnswer === 'A'" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    True
                  </div>
                  <div :class="[
                    'flex items-center gap-2 px-4 py-2.5 rounded-xl border text-[13px] font-medium flex-1 justify-center',
                    (q.correctAnswer === false || q.correctAnswer === 'false' || q.correct_answer === false || q.correct_answer === 'false' || q.correct_answer === 'B' || q.correctAnswer === 'B')
                      ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                      : 'bg-slate-50 border-slate-100 text-slate-600'
                  ]">
                    <div :class="[
                      'w-5 h-5 rounded-full border-2 flex items-center justify-center',
                      (q.correctAnswer === false || q.correctAnswer === 'false' || q.correct_answer === false || q.correct_answer === 'false' || q.correct_answer === 'B' || q.correctAnswer === 'B')
                        ? 'border-emerald-500 bg-emerald-500'
                        : 'border-slate-300 bg-white'
                    ]">
                      <svg v-if="q.correctAnswer === false || q.correctAnswer === 'false' || q.correct_answer === false || q.correct_answer === 'false' || q.correct_answer === 'B' || q.correctAnswer === 'B'" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    False
                  </div>
                </div>

                <!-- Short Answer / Essay hint -->
                <div v-else-if="q.type === 'short_answer' || q.type === 'essay'" class="ml-11">
                  <div class="px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-[12px] text-slate-400 italic">
                    {{ q.type === 'essay' ? 'Open-ended essay response' : 'Short written answer' }}
                    <span v-if="q.sampleAnswer || q.sample_answer" class="block mt-1 not-italic text-slate-600 font-medium">Sample: {{ q.sampleAnswer || q.sample_answer }}</span>
                  </div>
                </div>

                <!-- Fill in the Blank hint -->
                <div v-else-if="q.type === 'fill_blank'" class="ml-11">
                  <div class="px-4 py-3 bg-purple-50 border border-purple-100 rounded-xl text-[12px] text-purple-600 font-medium">
                    ✏️ Student types the missing word/phrase.
                    <span v-if="q.correct_answer" class="block mt-1 text-emerald-700 font-bold">Correct Answer: {{ q.correct_answer }}</span>
                  </div>
                </div>

                <!-- Matching pairs -->
                <div v-else-if="q.type === 'matching' && q.pairs" class="ml-11 space-y-2">
                  <div v-for="(pair, pIdx) in q.pairs" :key="pIdx" class="flex items-center gap-3">
                    <span class="px-3 py-1.5 bg-teal-50 border border-teal-100 rounded-lg text-[12px] font-medium text-teal-800 flex-1">{{ pair.left }}</span>
                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    <span class="px-3 py-1.5 bg-emerald-50 border border-emerald-100 rounded-lg text-[12px] font-medium text-emerald-800 flex-1">{{ pair.right }}</span>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-8 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
            <span class="text-[12px] text-slate-500 font-medium">{{ formStore.questions.length }} question{{ formStore.questions.length !== 1 ? 's' : '' }} · {{ formStore.totalMarks }} total marks</span>
            <div class="flex gap-3">
              <button @click="showQuestionsFullscreen = false" class="px-5 py-2 bg-[#5138ed] text-white text-[13px] font-bold rounded-xl hover:bg-indigo-600 transition-colors shadow-sm">
                {{ isEditModeFullscreen ? 'Done Editing' : 'Close' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Single Question Edit Modal -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showSingleEditModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" @keydown.escape="showSingleEditModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-indigo-50/60 to-white shrink-0">
             <div class="flex items-center gap-3">
               <div class="w-9 h-9 rounded-xl bg-[#5138ed] flex items-center justify-center text-white shadow-lg shadow-indigo-200/50">
                 <Pencil class="w-5 h-5" />
               </div>
               <div>
                 <h2 class="text-[16px] font-bold text-slate-800">Edit Question</h2>
                 <p class="text-[12px] text-slate-500 mt-0.5">Question {{ editingQuestionIndex + 1 }}</p>
               </div>
             </div>
             <button @click="showSingleEditModal = false" class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
               <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
             </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto max-h-[70vh] relative">
              <SharedEditorToolbar class="mb-6 !top-0 z-[100]" style="top: 0 !important;" />
              <div v-if="editingQuestionData" class="flex flex-col gap-5">
                  <!-- Header: Type & Marks -->
                  <div class="flex flex-wrap items-center gap-3">
                    <span v-if="editingQuestionData.type === 'mcq' || editingQuestionData.type === 'multiple_choice'" class="px-2 py-0.5 rounded-md bg-indigo-50 text-[#5138ed] text-[10px] font-bold uppercase tracking-wide">MCQ</span>
                    <span v-else-if="editingQuestionData.type === 'true_false' || editingQuestionData.type === 'true/false'" class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-wide">True / False</span>
                    <span v-else-if="editingQuestionData.type === 'short_answer'" class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-600 text-[10px] font-bold uppercase tracking-wide">Short Answer</span>
                    <span v-else-if="editingQuestionData.type === 'essay'" class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-500 text-[10px] font-bold uppercase tracking-wide">Essay</span>
                    <span v-else class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-wide">{{ editingQuestionData.type }}</span>
                    
                    <div class="flex items-center gap-2">
                      <label class="text-[12px] font-bold text-slate-600">
                        {{ editingQuestionData.type?.toLowerCase() === 'matching' ? 'Marks per Column A item:' : 'Marks:' }}
                      </label>
                      <input type="number" v-if="editingQuestionData.type?.toLowerCase() === 'matching'" v-model.number="editingQuestionData.marks_per_item" class="w-16 px-2 py-1 border border-slate-200 rounded-md text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" min="1" />
                      <input type="number" v-else v-model.number="editingQuestionData.marks" class="w-16 px-2 py-1 border border-slate-200 rounded-md text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" min="1" />
                      <span v-if="editingQuestionData.type?.toLowerCase() === 'matching'" class="text-[11px] font-semibold text-[#5138ed]">
                        ({{ (editingQuestionData.pairs?.filter((p: any) => (p.left || '').trim()).length || 1) }} items × {{ editingQuestionData.marks_per_item || 1 }} = {{ (editingQuestionData.pairs?.filter((p: any) => (p.left || '').trim()).length || 1) * (editingQuestionData.marks_per_item || 1) }} total marks)
                      </span>
                    </div>

                    <div class="flex items-center gap-2 ml-auto">
                      <label class="text-[12px] font-bold text-slate-600">Difficulty:</label>
                      <select v-model="editingQuestionData.difficulty" class="px-2 py-1 border border-slate-200 rounded-md text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]">
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                      </select>
                    </div>
                  </div>

                  <!-- Instruction Input -->
                  <div>
                    <label class="block text-[12px] font-bold text-slate-600 mb-1.5">Instructor Instruction (Optional)</label>
                    <input type="text" v-model="editingQuestionData.instruction" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" placeholder="e.g. Choose the correct answer..." />
                  </div>

                  <!-- Question Text Input -->
                  <div>
                    <label class="block text-[12px] font-bold text-slate-600 mb-1.5">Question Text</label>
                    <MinimalEditor v-model="editingQuestionData.text" placeholder="Enter question text..." minHeight="100px" />
                  </div>

                  <!-- MCQ Options Input -->
                  <div v-if="(editingQuestionData.type === 'mcq' || editingQuestionData.type === 'multiple_choice') && (editingQuestionData.options || editingQuestionData.choices)" class="space-y-3">
                    <label class="block text-[12px] font-bold text-slate-600 mb-1">Options (Select the correct one)</label>
                    <div v-for="(_, oIdx) in (editingQuestionData.options || editingQuestionData.choices)" :key="oIdx" class="flex items-start gap-3">
                      <div class="pt-2">
                        <input type="radio" name="single_edit_correct" :value="getLetter(oIdx)" @change="editingQuestionData.correct_answer = getLetter(oIdx)" :checked="editingQuestionData.correct_answer === getLetter(oIdx) || editingQuestionData.correctAnswer === getLetter(oIdx)" class="w-4 h-4 text-[#5138ed] focus:ring-[#5138ed] cursor-pointer" title="Mark as correct answer" />
                      </div>
                      <div class="flex-1">
                        <MinimalEditor v-model="editingQuestionData.options[oIdx]" placeholder="Option text..." minHeight="40px" />
                      </div>
                    </div>
                  </div>

                  <!-- True/False Options Input -->
                  <div v-else-if="editingQuestionData.type === 'true_false' || editingQuestionData.type === 'true/false'" class="space-y-2">
                    <label class="block text-[12px] font-bold text-slate-600 mb-1">Correct Answer</label>
                    <div class="flex gap-6">
                      <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="single_edit_correct" value="A" @change="editingQuestionData.correct_answer = 'A'" :checked="editingQuestionData.correct_answer === 'A' || editingQuestionData.correctAnswer === 'A' || editingQuestionData.correct_answer === true || editingQuestionData.correctAnswer === true || editingQuestionData.correct_answer === 'true' || editingQuestionData.correctAnswer === 'true'" class="w-4 h-4 text-[#5138ed] focus:ring-[#5138ed]" />
                        <span class="text-[13px] font-medium text-slate-700">True</span>
                      </label>
                      <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="single_edit_correct" value="B" @change="editingQuestionData.correct_answer = 'B'" :checked="editingQuestionData.correct_answer === 'B' || editingQuestionData.correctAnswer === 'B' || editingQuestionData.correct_answer === false || editingQuestionData.correctAnswer === false || editingQuestionData.correct_answer === 'false' || editingQuestionData.correctAnswer === 'false'" class="w-4 h-4 text-[#5138ed] focus:ring-[#5138ed]" />
                        <span class="text-[13px] font-medium text-slate-700">False</span>
                      </label>
                    </div>
                  </div>

                  <!-- Sample Answer Input for Short Answer/Essay/Fill in the Blank -->
                  <div v-else-if="['short_answer', 'essay', 'fill_blank', 'fill_in_the_blank'].includes(editingQuestionData.type?.toLowerCase())" class="space-y-2">
                    <label class="block text-[12px] font-bold text-slate-600 mb-1">Correct / Sample Answer</label>
                    <input type="text" v-model="editingQuestionData.correct_answer" @input="editingQuestionData.sample_answer = editingQuestionData.correct_answer" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" placeholder="Enter the correct or sample answer..." />
                  </div>

                  <!-- Matching Edit -->
                  <div v-else-if="editingQuestionData.type?.toLowerCase() === 'matching'" class="space-y-6">
                    <div>
                      <h3 class="text-[14px] font-bold text-slate-900">Question Writing</h3>
                      <p class="text-[12px] text-slate-500 mb-4">Write the matching statements that students will see during the exam.</p>
                      
                      <!-- Shared Editor -->
                      <div class="mb-6 bg-slate-50 border border-slate-200 rounded-xl p-4 pt-6 relative" v-if="activeMatchingCell">
                        <div class="absolute -top-3 left-4 px-2 py-0.5 bg-[#5138ed] text-white text-[10px] font-bold uppercase rounded shadow-sm">
                          CURRENTLY EDITING: COLUMN {{ activeMatchingCell.col === 'left' ? 'A' : 'B' }} - ROW {{ activeMatchingCell.rowIndex + 1 }}
                        </div>
                        <MinimalEditor v-model="sharedEditorContent" placeholder="Type your content here..." label="Matching Content" minHeight="120px" />
                      </div>

                      <div class="space-y-4">
                        <div class="grid grid-cols-[1fr_1fr_auto] gap-6 font-bold text-[11px] text-slate-500">
                          <div>Column A (Questions / Prompts) <span class="text-rose-500">*</span></div>
                          <div>Column B (Items to Match) <span class="text-rose-500">*</span></div>
                          <div class="w-8"></div>
                        </div>

                        <div v-for="(pair, index) in editingQuestionData.pairs" :key="index" class="grid grid-cols-[1fr_1fr_auto] gap-6 items-center">
                          <!-- Column A -->
                          <div class="flex items-start gap-3">
                            <div class="w-6 shrink-0 mt-3 font-bold text-slate-400 text-sm text-right">{{ Number(index) + 1 }}.</div>
                            <div 
                              @click="activeMatchingCell = { rowIndex: Number(index), col: 'left' }"
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
                            <div class="w-6 shrink-0 mt-3 font-bold text-slate-400 text-sm text-right">{{ getLetterLabel(Number(index)) }}.</div>
                            <div 
                              @click="activeMatchingCell = { rowIndex: Number(index), col: 'right' }"
                              :class="[ 
                                'w-full min-h-[60px] max-h-[120px] overflow-y-auto bg-white border rounded-xl p-3 text-[13px] text-slate-700 cursor-pointer transition-all prose prose-sm',
                                activeMatchingCell?.rowIndex === index && activeMatchingCell?.col === 'right' ? 'border-[#5138ed] ring-1 ring-[#5138ed] shadow-sm' : 'border-slate-200 hover:border-slate-300'
                              ]"
                            >
                              <div v-if="pair.right" v-html="pair.right"></div>
                              <div v-else class="text-slate-400 italic mt-1">Click to edit...</div>
                            </div>
                          </div>

                          <button @click="editingQuestionData.pairs.splice(Number(index), 1); delete editingQuestionData.correct_answers[(Number(index) + 1).toString()]" class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg self-start mt-2">
                            <Trash2 class="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                      <button @click="editingQuestionData.pairs.push({left: '', right: ''}); editingQuestionData.correct_answers[editingQuestionData.pairs.length.toString()] = getLetterLabel(editingQuestionData.pairs.length - 1)" class="mt-4 px-4 py-2 bg-indigo-50 text-[#5138ed] border border-indigo-100 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-colors">+ Add Matching Row</button>
                    </div>

                    <!-- Matching Answer Options -->
                    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                      <div class="flex flex-col mb-4 pb-4 border-b border-slate-100">
                        <h3 class="text-sm font-bold text-slate-800">Answer Options <span class="text-rose-500">*</span></h3>
                        <div class="flex items-center justify-between mt-1">
                          <p class="text-[12px] text-slate-500">Assign the correct matching pairs for automatic grading.</p>
                        </div>
                      </div>

                      <div class="grid grid-cols-[1fr_auto] gap-6 mb-2 font-bold text-[11px] text-slate-500 px-2">
                        <div>Column A (Prompt)</div><div>Correct Match</div>
                      </div>

                      <div class="space-y-3">
                        <template v-for="(_, index) in editingQuestionData.pairs" :key="index">
                          <div v-if="editingQuestionData.pairs[Number(index)] && (editingQuestionData.pairs[Number(index)].left || '').replace(/<[^>]*>?/gm, '').trim()" class="grid grid-cols-[1fr_auto] gap-6 items-center bg-slate-50/50 p-3 rounded-xl border border-slate-200">
                            <div class="text-[13px] text-slate-700 font-medium truncate flex items-center gap-2">
                              <span class="font-bold text-slate-400 w-4">{{ Number(index) + 1 }}.</span>
                              {{ (editingQuestionData.pairs[Number(index)].left || '').replace(/<[^>]*>?/gm, '').substring(0, 80) }}
                            </div>
                            <div class="w-72">
                              <select v-model="editingQuestionData.correct_answers[(Number(index) + 1).toString()]" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-[13px] text-slate-700 font-bold focus:outline-none focus:border-[#5138ed] appearance-none cursor-pointer shadow-sm">
                                <option v-for="(pair, i) in editingQuestionData.pairs" :key="i" :value="getLetterLabel(Number(i))">
                                  {{ getLetterLabel(Number(i)) }} — {{ (pair.right || '').replace(/<[^>]*>?/gm, '').substring(0, 40) || 'Empty' }}
                                </option>
                              </select>
                            </div>
                          </div>
                        </template>
                      </div>
                    </div>
                  </div>
              </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50 shrink-0">
             <button @click="showSingleEditModal = false" class="px-5 py-2.5 text-slate-600 font-bold text-[13px] rounded-xl hover:bg-slate-200 transition-colors">
               Cancel
             </button>
             <button @click="saveEditedQuestion" class="px-6 py-2.5 bg-[#5138ed] text-white text-[13px] font-bold rounded-xl hover:bg-indigo-600 transition-colors shadow-sm">
               Save Changes
             </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

