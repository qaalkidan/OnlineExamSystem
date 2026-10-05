<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useInstructorQbStore } from '../store/instructorQbStore'
import { useSemesterLockStore } from '../store/semesterLockStore'
import RichTextEditor from '../../../components/RichTextEditor.vue'
import { GripVertical } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const qbStore = useInstructorQbStore()
const lockStore = useSemesterLockStore()

const bankId = route.params.id as string
const bank = ref<any>(null)
const isLoading = ref(true)

// Step Wizard State: 1 = Bank Info, 2 = Add Questions, 3 = Review & Publish
const currentStep = ref(1)

// Common Form State
const questionType = ref('Multiple Choice (MCQ)')
const difficulty = ref('Medium')
const score = ref(1)
const chapter = ref('1')

const title = ref('') // Labeled as "Instructor Instruction"
const description = ref('')
const questionText = ref('')
const explanation = ref('')

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

const matchMappings = ref<string[]>(['A', 'B', 'C', 'D'])
const matchShuffle = ref(true)
const activeMatchingCell = ref<{ rowIndex: number, col: 'left' | 'right' } | null>(null)

watch(questionType, (newVal) => {
  if (newVal === 'Matching') {
    if (!activeMatchingCell.value) {
      activeMatchingCell.value = { rowIndex: 0, col: 'left' }
    }
  } else {
    activeMatchingCell.value = null
  }
  // Re-fetch instructions filtered by the selected question type
  const mappedType = questionTypeToBackendType(newVal)
  qbStore.fetchInstructions(mappedType, bankId).then((data: string[]) => {
    instructionOptions.value = data || []
  })
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

// Settings
const requiredQuestion = ref(true)
const allowPartialMarks = ref(false)
const negativeMarking = ref(false)
const showExplanation = ref(true)

// UI & Action States
const showToast = ref(false)
const toastMessage = ref('')
const isPublishing = ref(false)
const editingDraftId = ref<number | string | null>(null)

const instructionOptions = ref<string[]>([])

const draftQuestions = computed(() => qbStore.draftQuestions)

// Compute total draft count from grouped structure
const totalDraftCount = computed(() => {
  let count = 0
  if (!Array.isArray(draftQuestions.value)) return 0
  draftQuestions.value.forEach((typeGroup: any) => {
    if (typeGroup.instructionGroups) {
      typeGroup.instructionGroups.forEach((instGroup: any) => {
        if (instGroup.questions) count += instGroup.questions.length
      })
    }
  })
  return count
})

const triggerToast = (msg: string) => {
  toastMessage.value = msg
  showToast.value = true
  setTimeout(() => { showToast.value = false }, 3500)
}

const resetForm = () => {
  title.value = ''
  description.value = ''
  questionText.value = ''
  explanation.value = ''
  correctAnswer.value = ''
  editingDraftId.value = null
  chapter.value = '1'
  
  options.value = [
    { id: Date.now() + 1, label: 'A', text: '' },
    { id: Date.now() + 2, label: 'B', text: '' },
    { id: Date.now() + 3, label: 'C', text: '' },
    { id: Date.now() + 4, label: 'D', text: '' }
  ]
  fibAnswers.value = [{ id: Date.now(), text: '' }]
  fibCaseSensitive.value = false
  saExpectedAnswer.value = ''
  saMaxLength.value = 255
  matchPairs.value = [
    { id: Date.now(), left: '', right: '' },
    { id: Date.now() + 1, left: '', right: '' },
    { id: Date.now() + 2, left: '', right: '' },
    { id: Date.now() + 3, left: '', right: '' }
  ]
  matchMappings.value = ['A', 'B', 'C', 'D']
  matchShuffle.value = true
  if (questionType.value === 'Matching') {
    activeMatchingCell.value = { rowIndex: 0, col: 'left' }
  } else {
    activeMatchingCell.value = null
  }
}

const questionTypeToBackendType = (uiType: string): string => {
  const map: Record<string, string> = {
    'Multiple Choice (MCQ)': 'multiple_choice',
    'True / False': 'true_false',
    'Fill in the Blank': 'fill_in_blank',
    'Short Answer': 'short_answer',
    'Matching': 'matching'
  }
  return map[uiType] || 'multiple_choice'
}

const buildQuestionPayload = () => {
  let mappedType = 'multiple_choice'
  let qData: any = null

  if (questionType.value === 'Multiple Choice (MCQ)') {
    mappedType = 'multiple_choice'
    qData = { 
      options: options.value.map(o => ({ label: o.label, text: o.text })),
      shuffle_choices: shuffleChoices.value
    }
  } else if (questionType.value === 'True / False') {
    mappedType = 'true_false'
  } else if (questionType.value === 'Fill in the Blank') {
    mappedType = 'fill_in_blank'
    qData = {
      answers: fibAnswers.value.map(a => a.text).filter(t => t),
      case_sensitive: fibCaseSensitive.value
    }
  } else if (questionType.value === 'Short Answer') {
    mappedType = 'short_answer'
    qData = {
      expected_answer: saExpectedAnswer.value,
      max_length: saMaxLength.value
    }
  } else if (questionType.value === 'Matching') {
    mappedType = 'matching'
    
    const validLefts = matchPairs.value.map(p => p.left).filter(l => l.replace(/<[^>]*>?/gm, '').trim() !== '')
    const correct_answers: Record<string, string> = {}
    
    let colAIndex = 1
    matchPairs.value.forEach((p, i) => {
      if (p.left.replace(/<[^>]*>?/gm, '').trim() !== '') {
        correct_answers[colAIndex.toString()] = matchMappings.value[i]
        colAIndex++
      }
    })

    qData = {
      column_a: validLefts,
      column_b: matchPairs.value.map((p, i) => ({
        label: getLetterLabel(i),
        text: p.right
      })),
      correct_answers
    }
  }

  return {
    type: mappedType,
    title: title.value || 'Instruction',
    instruction: title.value || '',
    description: description.value,
    text: questionType.value === 'Matching' ? '<p>Match the following items:</p>' : questionText.value,
    options: questionType.value === 'Multiple Choice (MCQ)' ? options.value.map(o => ({ label: o.label, text: o.text })) : null,
    correct_answer: correctAnswer.value,
    explanation: explanation.value,
    marks: Number(score.value) || 1,
    difficulty: difficulty.value,
    chapter: `Chapter ${chapter.value}`,
    settings: {
      requiredQuestion: requiredQuestion.value,
      allowPartialMarks: allowPartialMarks.value,
      negativeMarking: negativeMarking.value,
      showExplanation: showExplanation.value
    },
    question_data: qData,
    status: 'draft'
  }
}

onMounted(async () => {
  await lockStore.fetchLockStatus()
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('create or edit questions')
    router.replace(`/instructor/question-banks/${bankId}`)
    return
  }
  try {
    const data = await qbStore.fetchQuestionBank(bankId)
    bank.value = data.bank
    await qbStore.fetchDraftQuestions(bankId)
    const mappedType = questionTypeToBackendType(questionType.value)
    instructionOptions.value = await qbStore.fetchInstructions(mappedType, bankId)

    if (route.query.step) {
      currentStep.value = Number(route.query.step) || 1
    }
    if (route.query.editDraftId) {
      const draft = qbStore.draftQuestions.find((q: any) => String(q.id) === String(route.query.editDraftId))
      if (draft) {
        populateFormForEdit(draft)
        currentStep.value = 2
      }
    }
  } catch (err) {
    console.error("Failed to load bank", err)
  } finally {
    isLoading.value = false
  }
})

const populateFormForEdit = (draft: any) => {
  resetForm()
  editingDraftId.value = draft.id
  title.value = draft.title || ''
  description.value = draft.description || ''
  questionText.value = draft.text || ''
  explanation.value = draft.explanation || ''
  correctAnswer.value = draft.correct_answer || ''
  difficulty.value = draft.difficulty || 'Medium'
  score.value = draft.marks || 1
  chapter.value = (draft.chapter || 'Chapter 1').replace('Chapter ', '')
  title.value = draft.instruction || draft.title || ''

  // Map type back to UI
  const typeMap: Record<string, string> = {
    'multiple_choice': 'Multiple Choice (MCQ)',
    'true_false': 'True / False',
    'fill_in_blank': 'Fill in the Blank',
    'short_answer': 'Short Answer',
    'matching': 'Matching'
  }
  questionType.value = typeMap[draft.type] || 'Multiple Choice (MCQ)'

  const qData = draft.question_data || {}

  if (draft.type === 'multiple_choice' && qData.options) {
    options.value = qData.options.map((o: any, idx: number) => ({
      id: Date.now() + idx,
      label: o.label || String.fromCharCode(65 + idx),
      text: o.text || o
    }))
    shuffleChoices.value = qData.shuffle_choices ?? true
  } else if (draft.type === 'fill_in_blank') {
    if (qData.answers && Array.isArray(qData.answers)) {
      fibAnswers.value = qData.answers.map((a: string, idx: number) => ({ id: Date.now() + idx, text: a }))
    }
    fibCaseSensitive.value = !!qData.case_sensitive
  } else if (draft.type === 'short_answer') {
    saExpectedAnswer.value = qData.expected_answer || ''
    saMaxLength.value = qData.max_length || 255
  } else if (draft.type === 'matching') {
    if (qData.column_a && qData.column_b) {
      // New Letter-Based Format
      const maxLen = Math.max(qData.column_a.length, qData.column_b.length, 2)
      matchPairs.value = Array.from({ length: maxLen }).map((_, idx) => ({
        id: Date.now() + idx,
        left: qData.column_a[idx] || '',
        right: qData.column_b[idx]?.text || ''
      }))
      const newMappings: string[] = []
      let colAIndex = 1
      for (let i = 0; i < maxLen; i++) {
        if (qData.column_a[i]) {
          newMappings.push(qData.correct_answers?.[colAIndex.toString()] || getLetterLabel(i))
          colAIndex++
        } else {
          newMappings.push(getLetterLabel(i))
        }
      }
      matchMappings.value = newMappings
    } else if (qData.question_content && qData.answer_pairs) {
      // Legacy Format fallback (v1)
      const colA = qData.question_content.column_a || []
      const colB = qData.question_content.column_b || []
      const maxLen = Math.max(colA.length, colB.length, 2)
      matchPairs.value = Array.from({ length: maxLen }).map((_, idx) => ({
        id: Date.now() + idx,
        left: colA[idx] || '',
        right: colB[idx] || ''
      }))
      matchMappings.value = (qData.answer_pairs || []).map((m: any) => getLetterLabel(m.right))
    } else if (qData.pairs) {
      // Legacy Format fallback (v0)
      matchPairs.value = qData.pairs.map((p: any, idx: number) => ({ id: Date.now() + idx, left: p.left, right: p.right }))
      matchMappings.value = qData.pairs.map((_: any, idx: number) => getLetterLabel(idx))
    }
    matchShuffle.value = qData.shuffle_pairs ?? true
    activeMatchingCell.value = { rowIndex: 0, col: 'left' }
  }
}

// Option re-labeling helper
const relabelOptions = () => {
  options.value.forEach((opt, i) => {
    opt.label = String.fromCharCode(65 + i)
  })
}

// Helpers for Dynamic Fields
const addOption = () => {
  if (options.value.length >= 10) {
    triggerToast("Maximum of 10 options allowed.")
    return
  }
  const nextLabel = String.fromCharCode(65 + options.value.length)
  options.value.push({ id: Date.now(), label: nextLabel, text: '' })
}
const removeOption = (index: number) => {
  if (options.value.length <= 2) {
    triggerToast("Minimum of 2 options required.")
    return
  }
  // if removing correct answer, clear it
  if (correctAnswer.value === options.value[index].label) {
    correctAnswer.value = ''
  }
  options.value.splice(index, 1)
  relabelOptions()
}

// Drag and drop options
const dragIndex = ref<number | null>(null)
const onDragStart = (e: DragEvent, index: number) => {
  dragIndex.value = index
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.dropEffect = 'move'
  }
}
const onDrop = (e: DragEvent, index: number) => {
  if (dragIndex.value === null) return
  if (dragIndex.value === index) return
  
  const movedItem = options.value.splice(dragIndex.value, 1)[0]
  options.value.splice(index, 0, movedItem)
  relabelOptions()
  
  dragIndex.value = null
}

const addFibAnswer = () => fibAnswers.value.push({ id: Date.now(), text: '' })
const removeFibAnswer = (index: number) => fibAnswers.value.splice(index, 1)

const addMatchPair = () => {
  if (matchPairs.value.length >= 20) {
    triggerToast("Maximum of 20 matching rows allowed.")
    return
  }
  matchPairs.value.push({ id: Date.now(), left: '', right: '' })
  const newIdx = matchPairs.value.length - 1
  matchMappings.value.push(getLetterLabel(newIdx))
}
const removeMatchPair = (index: number) => {
  if (matchPairs.value.length <= 2) {
    triggerToast("Minimum of 2 matching rows required.")
    return
  }
  matchPairs.value.splice(index, 1)
  matchMappings.value.splice(index, 1)
  
  if (activeMatchingCell.value?.rowIndex === index) {
    activeMatchingCell.value = { rowIndex: Math.max(0, index - 1), col: 'left' }
  } else if (activeMatchingCell.value && activeMatchingCell.value.rowIndex > index) {
    activeMatchingCell.value.rowIndex--
  }
}

// Step Navigation Handlers
const goToStep = async (step: number) => {
  if (step === 3) {
    // Always re-fetch grouped drafts when entering Step 3
    await qbStore.fetchDraftQuestions(bankId)
  }
  currentStep.value = step
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const validateDraft = () => {
  if (questionType.value !== 'Matching') {
    const strippedText = questionText.value.replace(/<[^>]*>?/gm, '').trim()
    if (!strippedText) {
      triggerToast("Question text is required.")
      return false
    }
  }

  if (questionType.value === 'Multiple Choice (MCQ)') {
    if (options.value.length < 2) {
      triggerToast("Multiple Choice requires at least 2 options.")
      return false
    }
    const hasEmptyOption = options.value.some(o => !o.text || o.text.replace(/<[^>]*>?/gm, '').trim() === '')
    if (hasEmptyOption) {
      triggerToast("No option can be empty.")
      return false
    }
    if (!correctAnswer.value) {
      triggerToast("Exactly 1 correct answer must be selected.")
      return false
    }
  }

  if (questionType.value === 'Fill in the Blank' && !fibAnswers.value.some(a => a.text)) {
    triggerToast("Fill in the Blank requires at least one accepted answer.")
    return false
  }
  if (questionType.value === 'Matching') {
    if (matchPairs.value.length < 2) {
      triggerToast("Matching requires at least two pairs.")
      return false
    }
    const hasEmptyRight = matchPairs.value.some(p => !p.right.replace(/<[^>]*>?/gm, '').trim())
    if (hasEmptyRight) {
      triggerToast("All Column B (Match) options must be filled.")
      return false
    }

    const validLefts = matchPairs.value.map(p => p.left.replace(/<[^>]*>?/gm, '').trim())
    let foundEmptyLeft = false
    for (let i = 0; i < validLefts.length; i++) {
      if (!validLefts[i]) {
        foundEmptyLeft = true
      } else if (foundEmptyLeft) {
        triggerToast("Column A cannot have empty distractor rows between filled rows. Place distractors at the bottom.")
        return false
      }
    }
    
    const activeLefts = validLefts.filter(l => l)
    if (activeLefts.length < 2) {
      triggerToast("Matching requires at least 2 Column A questions.")
      return false
    }

    if (new Set(activeLefts).size !== activeLefts.length) {
      triggerToast("Column A (Questions) contains duplicate entries.")
      return false
    }
    const rights = matchPairs.value.map(p => p.right.replace(/<[^>]*>?/gm, '').trim())
    if (new Set(rights).size !== rights.length) {
      triggerToast("Column B (Matches) contains duplicate entries.")
      return false
    }
    const activeMappings = matchMappings.value.filter((l, i) => matchPairs.value[i].left.replace(/<[^>]*>?/gm, '').trim())
    if (new Set(activeMappings).size !== activeMappings.length) {
      triggerToast("Duplicate answers assigned. Each letter must be assigned only once.")
      return false
    }
  }
  return true
}

const handleAddAnother = async () => {
  if (!validateDraft()) return
  try {
    const payload = buildQuestionPayload()
    if (editingDraftId.value) {
      await qbStore.updateQuestion(editingDraftId.value, payload)
      triggerToast("Draft question updated successfully!")
    } else {
      await qbStore.saveQuestionDraft(bankId, payload)
      triggerToast("Question saved as draft successfully! You can add another.")
    }
    resetForm()
  } catch (err: any) {
    triggerToast(err.message || "Failed to save draft. Please try again.")
  }
}

const isFormEmpty = () => {
  if (questionType.value === 'Matching') {
    return matchPairs.value.every(p => 
      p.left.replace(/<[^>]*>?/gm, '').trim() === '' && 
      p.right.replace(/<[^>]*>?/gm, '').trim() === ''
    )
  }
  const strippedText = questionText.value.replace(/<[^>]*>?/gm, '').trim()
  return !strippedText
}

const handleReviewAndPublish = async () => {
  // If the form is completely empty (e.g. they just clicked "Save & Add Another"),
  // just skip saving and go directly to Step 3.
  if (isFormEmpty()) {
    goToStep(3)
    return
  }
  
  if (!validateDraft()) return
  try {
    const payload = buildQuestionPayload()
    if (editingDraftId.value) {
      await qbStore.updateQuestion(editingDraftId.value, payload)
    } else {
      await qbStore.saveQuestionDraft(bankId, payload)
    }
    resetForm()
    // Re-fetch grouped drafts so Step 3 displays them immediately
    await qbStore.fetchDraftQuestions(bankId)
  } catch (err: any) {
    console.error("Failed auto-saving draft before review", err)
    triggerToast(err.message || "Failed to save draft. Please try again.")
    return
  }
  goToStep(3)
}

const isEditingModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const isPublishModalOpen = ref(false)
const draftToDelete = ref<number | string | null>(null)
const isFullscreen = ref(false)

const toggleFullscreen = () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(err => {
      console.error(`Error attempting to enable fullscreen mode: ${err.message}`)
    })
  } else {
    document.exitFullscreen()
  }
}

onMounted(() => {
  document.addEventListener('fullscreenchange', () => {
    isFullscreen.value = !!document.fullscreenElement
  })
})

const confirmDeleteDraft = async () => {
  if (!draftToDelete.value) return
  try {
    await qbStore.deleteQuestion(draftToDelete.value)
    triggerToast("Draft question deleted.")
    await qbStore.fetchDraftQuestions(bankId)
  } catch (err) {
    triggerToast("Failed to delete draft question.")
  } finally {
    isDeleteModalOpen.value = false
    draftToDelete.value = null
  }
}

const closeDeleteModal = () => {
  isDeleteModalOpen.value = false
  draftToDelete.value = null
}

const handleEditDraft = (q: any) => {
  populateFormForEdit(q)
  if (currentStep.value === 3) {
    isEditingModalOpen.value = true
  } else {
    goToStep(2)
  }
}

const closeEditModal = () => {
  isEditingModalOpen.value = false
  resetForm()
}

const saveEditModal = async () => {
  if (!validateDraft()) return
  try {
    const payload = buildQuestionPayload()
    if (editingDraftId.value) {
      await qbStore.updateQuestion(editingDraftId.value, payload)
      triggerToast("Draft question updated successfully!")
    } else {
      await qbStore.saveQuestionDraft(bankId, payload)
      triggerToast("Question saved as draft successfully!")
    }
    await qbStore.fetchDraftQuestions(bankId)
    closeEditModal()
  } catch (err: any) {
    triggerToast(err.message || "Failed to save draft.")
  }
}

const handleDeleteDraft = (id: number | string) => {
  draftToDelete.value = id
  isDeleteModalOpen.value = true
}

const handlePublishQuestions = () => {
  if (!draftQuestions.value.length) {
    triggerToast("No draft questions available to publish.")
    return
  }
  isPublishModalOpen.value = true
}

const confirmPublishQuestions = async () => {
  isPublishing.value = true
  try {
    await qbStore.publishQuestions(bankId)
    triggerToast("All questions published successfully!")
    setTimeout(() => {
      router.push(`/instructor/question-banks/${bankId}`)
    }, 1200)
  } catch (err) {
    triggerToast("Failed to publish questions. Please try again.")
    isPublishing.value = false
  } finally {
    isPublishModalOpen.value = false
  }
}

const closePublishModal = () => {
  isPublishModalOpen.value = false
}

const handleCancel = () => router.push(`/instructor/question-banks/${bankId}`)

const getDifficultyClass = (diff: string) => {
  switch (diff?.toLowerCase()) {
    case 'easy': return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'hard': return 'bg-rose-50 text-rose-700 border-rose-200'
    default: return 'bg-amber-50 text-amber-700 border-amber-200'
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 sm:p-6 lg:p-10 font-sans pb-32">

    <!-- Toast Notification -->
    <transition enter-active-class="transition duration-300 ease-out" enter-from-class="transform translate-y-2 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="transform translate-y-0 opacity-100" leave-to-class="transform translate-y-2 opacity-0">
      <div v-if="showToast" class="fixed top-6 right-6 z-[200] flex items-center gap-3 px-5 py-3.5 bg-slate-900 text-white text-xs font-semibold rounded-xl shadow-2xl border border-slate-800">
        <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <span>{{ toastMessage }}</span>
      </div>
    </transition>

    <!-- Page Header & Stepper -->
    <div class="max-w-[1200px] mx-auto mb-6 sm:mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-3">
        <div class="flex items-center gap-1.5 sm:gap-2 text-xs font-semibold text-slate-400 flex-wrap">
          <router-link to="/instructor/question-banks" class="hover:text-slate-600 transition-colors">Question Banks</router-link>
          <span>/</span>
          <router-link :to="`/instructor/question-banks/${bankId}`" class="hover:text-slate-600 transition-colors truncate max-w-[120px] sm:max-w-none">{{ bank?.title || 'Question Bank' }}</router-link>
          <span>/</span>
          <span class="text-[#5138ed]">Wizard</span>
        </div>
        <router-link :to="`/instructor/question-banks/${bankId}`" class="px-3.5 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-50 transition-colors flex items-center justify-center gap-2 shadow-xs whitespace-nowrap self-start min-h-[38px]">
           <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
           Back to Bank
        </router-link>
      </div>

      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 sm:p-6 rounded-2xl border border-slate-100 shadow-xs mb-6">
        <div>
          <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Create Questions Wizard</h1>
          <p class="text-xs text-slate-500 mt-0.5 sm:mt-1">Complete the steps below to add and publish dynamic questions.</p>
        </div>

        <!-- Mobile Stepper (sm:hidden) -->
        <div class="sm:hidden flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs font-bold">
          <span class="text-slate-600">Step {{ currentStep }} of 3</span>
          <span class="text-[#5138ed]">{{ currentStep === 1 ? '1. Bank Info' : currentStep === 2 ? '2. Add Questions' : '3. Review & Publish' }}</span>
        </div>

        <!-- Tablet/Desktop Stepper (hidden sm:flex) -->
        <div class="hidden sm:flex items-center gap-2 sm:gap-4">
          <button @click="goToStep(1)" :class="['flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all min-h-[40px]', currentStep === 1 ? 'bg-[#5138ed] text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"><span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center text-[11px]">1</span><span>Bank Info</span></button>
          <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <button @click="goToStep(2)" :class="['flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all min-h-[40px]', currentStep === 2 ? 'bg-[#5138ed] text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"><span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center text-[11px]">2</span><span>Add Questions</span></button>
          <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <button @click="goToStep(3)" :class="['flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all min-h-[40px]', currentStep === 3 ? 'bg-[#5138ed] text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"><span class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center text-[11px]">3</span><span>Review & Publish</span></button>
        </div>
      </div>
    </div>

    <!-- MAIN STEP CONTENT -->
    <div class="max-w-[1200px] mx-auto">
      
      <!-- ==================== STEP 1: QUESTION BANK INFORMATION ==================== -->
      <div v-if="currentStep === 1" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-100 p-8 shadow-sm">
          <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-[#5138ed]">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <div>
              <h2 class="text-lg font-bold text-slate-900">Step 1: Question Bank Details</h2>
              <p class="text-xs text-slate-500">Review the target question bank information before adding questions.</p>
            </div>
          </div>

          <div v-if="isLoading" class="p-8 text-center text-slate-500 text-xs font-medium">Loading question bank information...</div>
          <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200/60">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Question Bank Name</p>
              <p class="text-sm font-bold text-slate-800">{{ bank?.title }}</p>
            </div>
            <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200/60">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Course</p>
              <p class="text-sm font-bold text-slate-800">{{ bank?.course_name }} ({{ bank?.course_code }})</p>
            </div>
            <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200/60">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Department</p>
              <p class="text-sm font-bold text-slate-800">{{ bank?.department || 'Computer Science' }}</p>
            </div>
            <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200/60">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Questions & Marks</p>
              <p class="text-sm font-bold text-slate-800">{{ bank?.total_questions || 0 }} Questions • {{ bank?.total_marks || 0 }} Total Marks</p>
            </div>
            <div class="md:col-span-3 bg-slate-50/70 p-4 rounded-xl border border-slate-200/60">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Description</p>
              <p class="text-xs text-slate-700 leading-relaxed">{{ bank?.description || 'No description specified.' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ==================== STEP 2: ADD QUESTIONS FORM ==================== -->
      <Teleport to="body" :disabled="!isEditingModalOpen">
        <div v-show="currentStep === 2 || isEditingModalOpen" 
             :class="isEditingModalOpen ? 'fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm' : ''">
          
          <div :class="isEditingModalOpen ? 'bg-[#f8fafc] w-full max-w-[1200px] rounded-2xl shadow-2xl relative my-auto max-h-full flex flex-col' : 'w-full'">
            
            <!-- Modal Header -->
            <div v-if="isEditingModalOpen" class="flex items-center justify-between p-5 sm:p-6 border-b border-slate-200 shrink-0 bg-white rounded-t-2xl">
              <h2 class="text-lg font-bold text-slate-800">Edit Question</h2>
              <button @click="closeEditModal" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>

            <!-- Form Content -->
            <div :class="isEditingModalOpen ? 'p-5 sm:p-6 overflow-y-auto space-y-6 flex-1' : 'space-y-6 w-full'">
        
        <!-- Base Information -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-[#5138ed]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
              </div>
              <h2 class="text-[15px] font-bold text-slate-800">Dynamic Question Details</h2>
            </div>
            <button @click="toggleFullscreen" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors" :title="isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'">
              <svg v-if="!isFullscreen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14L4 20M10 14v6m0-6H4m10 0l6 6m-6-6v6m0-6h6M14 10L20 4M14 10V4m0 6h6M10 10L4 4M10 10V4m0 6H4"></path></svg>
            </button>
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
              <label class="block text-[12px] font-bold text-slate-800 mb-2">Difficulty</label>
              <select v-model="difficulty" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] appearance-none cursor-pointer">
                <option>Easy</option>
                <option>Medium</option>
                <option>Hard</option>
              </select>
            </div>
            <div>
              <label class="block text-[12px] font-bold text-slate-800 mb-2">Score Marks <span class="text-rose-500">*</span></label>
              <input v-model="score" type="number" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" min="1" />
            </div>
            <div>
              <label class="block text-[12px] font-bold text-slate-800 mb-2">Chapter <span class="text-rose-500">*</span></label>
              <div class="flex items-center">
                <span class="px-3 py-2.5 bg-slate-100 border border-slate-200 border-r-0 rounded-l-xl text-[13px] text-slate-500 font-medium whitespace-nowrap">Chapter</span>
                <input v-model="chapter" type="number" min="1" class="w-full bg-white border border-slate-200 rounded-r-xl px-3 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" placeholder="e.g. 1" />
              </div>
            </div>
          </div>
        </div>

        <!-- Question Content -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label class="block text-[12px] font-bold text-slate-800 mb-2">Instructor Instruction <span class="text-rose-500">*</span></label>
              <input type="text" v-model="title" list="instruction-options" placeholder="e.g., Choose the correct answer" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:border-[#5138ed] transition-colors shadow-sm" />
              <datalist id="instruction-options">
                <option v-for="inst in instructionOptions" :key="inst" :value="inst"></option>
              </datalist>
            </div>
            <div>
              <label class="block text-[12px] font-bold text-slate-800 mb-2">Question Description (Optional)</label>
              <input v-model="description" type="text" placeholder="Additional context..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" />
            </div>
          </div>

          <div v-if="questionType !== 'Matching'">
            <label class="block text-[12px] font-bold text-slate-800 mb-2">Question Text <span class="text-rose-500">*</span></label>
            <RichTextEditor v-model="questionText" placeholder="Type your question content here..." minHeight="200px" />
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
              <RichTextEditor v-model="sharedEditorContent" placeholder="Type your content here..." minHeight="120px" />
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

        <!-- ================= DYNAMIC QUESTION TYPE SECTIONS ================= -->

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
              <!-- Drag Handle -->
              <div class="cursor-grab hover:text-slate-700 text-slate-400 mt-2" title="Drag to reorder">
                <GripVertical class="w-5 h-5" />
              </div>

              <!-- Label & Correct Answer Radio -->
              <div class="flex flex-col items-center gap-2 mt-2">
                <div class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 shadow-sm text-[13px] font-extrabold text-slate-700">{{ option.label }}</div>
                <div 
                  class="w-5 h-5 rounded-full border-2 flex items-center justify-center cursor-pointer transition-colors" 
                  :class="correctAnswer === option.label ? 'border-emerald-500' : 'border-slate-300 hover:border-slate-400'"
                  @click="correctAnswer = option.label"
                  title="Mark as correct answer"
                >
                  <div v-if="correctAnswer === option.label" class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                </div>
              </div>

              <!-- Rich Text Editor for Option -->
              <div class="flex-1">
                <RichTextEditor v-model="option.text" :placeholder="`Enter content for Option ${option.label}...`" minHeight="80px" />
              </div>

              <!-- Remove Option Button -->
              <button @click="removeOption(index)" class="w-8 h-8 text-rose-400 hover:bg-rose-100 hover:text-rose-600 rounded-lg flex items-center justify-center mt-2" title="Delete Option">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
              </button>
            </div>
          </div>
          
          <div class="flex items-center gap-4">
            <button @click="addOption" class="px-4 py-2 bg-indigo-50 text-[#5138ed] border border-indigo-100 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-colors">+ Add Option</button>
            <p class="text-[11px] text-slate-500">Max 10 options. Drag handle to reorder.</p>
          </div>
        </div>

        <!-- 2. True / False -->
        <div v-if="questionType === 'True / False'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
          <label class="block text-[12px] font-bold text-slate-800 mb-3">Select Correct Answer <span class="text-rose-500">*</span></label>
          <div class="flex gap-4 max-w-sm">
            <label class="flex-1 cursor-pointer" @click="correctAnswer = 'True'">
              <div :class="['flex items-center justify-center gap-2 p-3 rounded-xl border font-bold text-xs transition-colors', correctAnswer === 'True' ? 'border-[#5138ed] bg-indigo-50/50 text-[#5138ed]' : 'border-slate-200 text-slate-600 hover:bg-slate-50']">True</div>
            </label>
            <label class="flex-1 cursor-pointer" @click="correctAnswer = 'False'">
              <div :class="['flex items-center justify-center gap-2 p-3 rounded-xl border font-bold text-xs transition-colors', correctAnswer === 'False' ? 'border-[#5138ed] bg-indigo-50/50 text-[#5138ed]' : 'border-slate-200 text-slate-600 hover:bg-slate-50']">False</div>
            </label>
          </div>
        </div>

        <!-- 3. Fill in the Blank -->
        <div v-if="questionType === 'Fill in the Blank'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <label class="block text-[12px] font-bold text-slate-800">Accepted Answers <span class="text-rose-500">*</span></label>
            <label class="flex items-center gap-2 text-[12px] text-slate-700 cursor-pointer">
              <input type="checkbox" v-model="fibCaseSensitive" class="rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" /> Case Sensitive Evaluation
            </label>
          </div>
          <p class="text-[11px] text-slate-500 mb-4">List all possible correct variations (e.g. "Addis Ababa", "addis ababa").</p>
          <div class="space-y-3 mb-4">
            <div v-for="(ans, index) in fibAnswers" :key="ans.id" class="flex items-center gap-3">
              <input v-model="ans.text" type="text" placeholder="e.g. Addis Ababa" class="w-full max-w-md bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" />
              <button @click="removeFibAnswer(index)" class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
            </div>
          </div>
          <button @click="addFibAnswer" class="text-[12px] font-bold text-[#5138ed] hover:text-indigo-700">+ Add Another Accepted Answer</button>
        </div>

        <!-- 4. Short Answer -->
        <div v-if="questionType === 'Short Answer'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-[12px] font-bold text-slate-800 mb-2">Expected Answer Keyword(s)</label>
              <input v-model="saExpectedAnswer" type="text" placeholder="Optional expected answer..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" />
            </div>
            <div>
              <label class="block text-[12px] font-bold text-slate-800 mb-2">Maximum Character Length</label>
              <input v-model="saMaxLength" type="number" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed]" />
            </div>
          </div>
        </div>

        <!-- 5. Matching -->
        <div v-if="questionType === 'Matching'" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
          <div class="flex flex-col mb-4 pb-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-slate-800">Answer Options</h3>
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

          <div class="space-y-3 mb-4">
            <template v-for="(_, index) in matchMappings" :key="index">
              <div v-if="matchPairs[index].left.replace(/<[^>]*>?/gm, '').trim()" class="grid grid-cols-[1fr_auto] gap-6 items-center bg-slate-50/50 p-3 rounded-xl border border-slate-200">
                <div class="text-[13px] text-slate-700 font-medium truncate flex items-center gap-2">
                  <span class="font-bold text-slate-400 w-4">{{ index + 1 }}.</span>
                  {{ matchPairs[index].left.replace(/<[^>]*>?/gm, '').substring(0, 80) }}
                </div>
                <div class="w-72">
                  <select v-model="matchMappings[index]" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-[13px] text-slate-700 font-bold focus:outline-none focus:border-[#5138ed] appearance-none cursor-pointer shadow-sm">
                    <option v-for="(pair, i) in matchPairs" :value="getLetterLabel(i)">
                      {{ getLetterLabel(i) }} — {{ pair.right.replace(/<[^>]*>?/gm, '').substring(0, 40) || 'Empty' }}
                    </option>
                  </select>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Global Explanation -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm mt-6">
          <label class="block text-[12px] font-bold text-slate-800 mb-2">Global Explanation (Optional)</label>
          <RichTextEditor v-model="explanation" placeholder="Provide feedback shown to students after answering..." minHeight="100px" />
        </div>

            </div> <!-- End Form Content -->
            
            <!-- Modal Footer -->
            <div v-if="isEditingModalOpen" class="p-5 sm:p-6 border-t border-slate-200 shrink-0 bg-white flex justify-end gap-3 rounded-b-2xl">
              <button @click="closeEditModal" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</button>
              <button @click="saveEditModal" class="px-5 py-2.5 rounded-xl bg-[#5138ed] text-white text-xs font-bold hover:bg-indigo-600 transition-colors shadow-md">Save Changes</button>
            </div>

          </div>
        </div>
      </Teleport>

      <!-- ==================== STEP 3: REVIEW & PUBLISH ==================== -->
      <div v-if="currentStep === 3" class="space-y-6">
        <div class="flex items-center justify-between bg-white p-6 rounded-2xl border border-slate-100 shadow-xs">
          <div>
            <h2 class="text-lg font-bold text-slate-900">Step 3: Review Draft Questions</h2>
            <p class="text-xs text-slate-500">Review all grouped draft questions created in this session before publishing.</p>
          </div>
          <span class="px-3 py-1 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold rounded-full uppercase">
            {{ totalDraftCount }} Drafts Ready
          </span>
        </div>

        <div v-if="totalDraftCount === 0" class="p-16 text-center bg-white rounded-2xl border border-slate-100">
          <p class="text-sm font-bold text-slate-700 mb-2">No draft questions created yet.</p>
          <button @click="goToStep(2)" class="px-4 py-2 bg-[#5138ed] text-white text-xs font-bold rounded-xl mt-2">Go to Step 2: Add Questions</button>
        </div>

        <div v-else class="space-y-10">
          <!-- Iterate over Question Type Groups -->
          <div v-for="typeGroup in draftQuestions" :key="typeGroup.questionType">
            <div class="mb-4 border-b-2 border-slate-200 pb-3">
              <h3 class="text-base font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                <span class="w-2 h-5 bg-[#5138ed] rounded-full inline-block"></span>
                {{ typeGroup.questionType }}
              </h3>
            </div>

            <!-- Iterate over Instruction Groups -->
            <div class="space-y-8 pl-4 border-l-[3px] border-slate-100 ml-1">
              <div v-for="(instGroup, instIdx) in typeGroup.instructionGroups" :key="instIdx">
                <div class="mb-4 relative">
                  <div class="absolute -left-[18px] top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-[3px] border-indigo-400 rounded-full"></div>
                  <div v-if="instGroup.instruction">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Instructor Instruction:</span>
                    <p class="text-[13px] font-bold text-slate-800 italic">"{{ instGroup.instruction }}"</p>
                  </div>
                  <div v-else>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Instructor Instruction:</span>
                    <p class="text-[13px] font-bold text-slate-400 italic">No specific instruction provided.</p>
                  </div>
                </div>

                <!-- Questions -->
                <div class="space-y-4 pl-4">
                  <div v-for="(q, qIdx) in instGroup.questions" :key="q.id" class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:border-indigo-200 transition-all">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-4 border-b border-slate-100">
                      <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center text-xs font-extrabold">{{ Number(qIdx) + 1 }}</span>
                        <span :class="getDifficultyClass(q.difficulty)" class="px-2.5 py-1 text-[11px] font-bold rounded-md border">{{ q.difficulty || 'Medium' }}</span>
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-md bg-slate-100 text-slate-600">{{ q.marks || 1 }} Mark{{ (q.marks || 1) > 1 ? 's' : '' }}</span>
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-md bg-slate-100 text-slate-600">{{ q.chapter || 'Chapter 1' }}</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-[11px] font-bold rounded-full uppercase">Draft</span>
                        <button @click="handleEditDraft(q)" class="p-2 text-slate-400 hover:text-indigo-600 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                        <button @click="handleDeleteDraft(q.id)" class="p-2 text-slate-400 hover:text-rose-600 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                      </div>
                    </div>

                    <p class="text-sm text-slate-800 leading-relaxed mb-3" v-html="q.text"></p>

                    <!-- Options for MCQ / True-False -->
                    <div v-if="q.options && q.options.length" class="space-y-1.5 mb-4">
                      <div v-for="(opt, oIdx) in q.options" :key="oIdx" class="flex items-start gap-2 text-xs">
                        <span class="w-5 h-5 shrink-0 rounded bg-slate-100 text-slate-600 text-[10px] font-bold flex items-center justify-center border border-slate-200 mt-0.5">{{ opt.label || String.fromCharCode(65 + Number(oIdx)) }}</span>
                        <span class="pt-0.5 [&>p]:m-0 [&>p]:inline" v-html="opt.text || opt"></span>
                      </div>
                    </div>

                    <!-- Display for Matching -->
                    <div v-if="q.type === 'matching' && q.question_data?.column_a" class="grid grid-cols-2 gap-4 mb-4">
                      <!-- Column A -->
                      <div class="space-y-1.5">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase">Column A</h4>
                        <div v-for="(colA, idx) in q.question_data.column_a" :key="'A'+idx" class="flex items-start gap-2 text-xs">
                          <span class="w-5 h-5 shrink-0 rounded bg-slate-100 text-slate-600 text-[10px] font-bold flex items-center justify-center border border-slate-200 mt-0.5">{{ Number(idx) + 1 }}</span>
                          <span class="pt-0.5" v-html="colA"></span>
                        </div>
                      </div>
                      <!-- Column B -->
                      <div class="space-y-1.5">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase">Column B</h4>
                        <div v-for="(colB, idx) in q.question_data.column_b" :key="'B'+idx" class="flex items-start gap-2 text-xs">
                          <span class="w-5 h-5 shrink-0 rounded bg-slate-100 text-slate-600 text-[10px] font-bold flex items-center justify-center border border-slate-200 mt-0.5">{{ colB.label }}</span>
                          <span class="pt-0.5" v-html="colB.text"></span>
                        </div>
                      </div>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs">
                      <span class="font-bold text-slate-600">Answer:</span>
                      <span v-if="q.type === 'multiple_choice' || q.type === 'true_false'" class="ml-2 text-[#5138ed] font-medium">{{ q.correct_answer }}</span>
                      <span v-else-if="q.type === 'fill_in_blank'" class="ml-2 text-[#5138ed] font-medium">{{ q.question_data?.answers?.join(', ') }}</span>
                      <span v-else-if="q.type === 'short_answer'" class="ml-2 text-[#5138ed] font-medium">{{ q.question_data?.expected_answer }}</span>
                      <span v-else-if="q.type === 'matching'" class="ml-2 text-[#5138ed] font-medium">
                        <span v-for="(ans, key) in (q.question_data?.correct_answers || {})" :key="key" class="inline-block mr-3">{{ key }} → {{ ans }}</span>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- STICKY ACTION FOOTER -->
    <div class="fixed bottom-0 left-0 lg:left-56 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200 px-4 sm:px-8 py-3 sm:py-4 z-40 flex items-center justify-between gap-3 shadow-lg">
      <template v-if="currentStep === 1">
        <button @click="handleCancel" class="px-4 sm:px-6 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors min-h-[44px]">Cancel</button>
        <button @click="goToStep(2)" class="px-5 sm:px-6 py-2.5 rounded-xl bg-[#5138ed] text-white text-xs font-bold hover:bg-indigo-600 transition-colors flex items-center gap-2 shadow-md shadow-indigo-200 min-h-[44px]"><span>Next → Add Questions</span></button>
      </template>

      <template v-else-if="currentStep === 2">
        <button @click="goToStep(1)" class="px-3.5 sm:px-6 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors flex items-center gap-1.5 sm:gap-2 min-h-[44px]"><span>← Previous</span></button>
        <div class="flex items-center gap-2 sm:gap-3">
          <button @click="handleAddAnother" class="px-3 sm:px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-1.5 sm:gap-2 min-h-[44px]"><svg class="w-4 h-4 text-[#5138ed] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg><span class="hidden sm:inline">Save & Add Another</span><span class="sm:hidden">Add Another</span></button>
          <button @click="handleReviewAndPublish" class="px-4 sm:px-6 py-2.5 rounded-xl bg-[#5138ed] text-white text-xs font-bold hover:bg-indigo-600 transition-colors shadow-md shadow-indigo-200 flex items-center gap-1.5 sm:gap-2 min-h-[44px]"><span>Review & Publish →</span></button>
        </div>
      </template>

      <template v-else-if="currentStep === 3">
        <button @click="goToStep(2)" class="px-3.5 sm:px-6 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors flex items-center gap-1.5 sm:gap-2 min-h-[44px]"><span>← Previous</span></button>
        <button @click="handlePublishQuestions" :disabled="isPublishing || totalDraftCount === 0" class="px-4 sm:px-6 py-2.5 rounded-xl bg-[#5138ed] text-white text-xs font-bold hover:bg-indigo-600 transition-colors shadow-md shadow-indigo-200 flex items-center gap-2 min-h-[44px]"><svg v-if="!isPublishing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>{{ isPublishing ? 'Publishing...' : 'Publish Questions' }}</span></button>
      </template>
    </div>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body" :disabled="!isDeleteModalOpen">
      <div v-if="isDeleteModalOpen" class="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all">
          <div class="p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-500 mx-auto flex items-center justify-center mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Delete Draft Question?</h3>
            <p class="text-sm text-slate-500">Are you sure you want to delete this question? This action cannot be undone.</p>
          </div>
          <div class="flex items-center border-t border-slate-100 bg-slate-50">
            <button @click="closeDeleteModal" class="flex-1 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors border-r border-slate-100">Cancel</button>
            <button @click="confirmDeleteDraft" class="flex-1 px-4 py-3 text-sm font-bold text-rose-600 hover:bg-rose-100 transition-colors">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Publish Confirmation Modal -->
    <Teleport to="body" :disabled="!isPublishModalOpen">
      <div v-if="isPublishModalOpen" class="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all">
          <div class="p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-indigo-100 text-[#5138ed] mx-auto flex items-center justify-center mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7M5 13l4-4M19 7l-4 4"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Publish Questions?</h3>
            <p class="text-sm text-slate-500">Are you sure you want to publish {{ totalDraftCount }} question(s)? They will become active in this Question Bank.</p>
          </div>
          <div class="flex items-center border-t border-slate-100 bg-slate-50">
            <button @click="closePublishModal" class="flex-1 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors border-r border-slate-100">Cancel</button>
            <button @click="confirmPublishQuestions" :disabled="isPublishing" class="flex-1 px-4 py-3 text-sm font-bold text-[#5138ed] hover:bg-indigo-50 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
              <svg v-if="isPublishing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <span>{{ isPublishing ? 'Publishing...' : 'Publish' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
