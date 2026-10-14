<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useInstructorQbStore } from '../store/instructorQbStore'
import { useSemesterLockStore } from '../store/semesterLockStore'
import RichTextEditor from '../../../components/RichTextEditor.vue'
import ImportQuestionsFile from '../components/create-exam/ImportQuestionsFile.vue'
import type { ParsedQuestionRow } from '../utils/questionImportValidator'
import {
  GripVertical,
  Layers,
  FileText,
  Upload,
  CheckCircle2,
  HelpCircle,
  Plus,
  Trash2,
  Edit3,
  Eye,
  Send,
  ArrowLeft,
  ArrowRight,
  Check,
  AlertCircle,
  BookOpen,
  Sparkles,
  Maximize2,
  Minimize2,
  FileSpreadsheet,
  Shuffle
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const qbStore = useInstructorQbStore()
const lockStore = useSemesterLockStore()

const bankId = route.params.id as string
const bank = ref<any>(null)
const isLoading = ref(true)

// Step Wizard State: 1 = Bank Info, 2 = Add Questions, 3 = Review & Publish
const currentStep = ref(1)

// Step 2 Input Mode: 'manual' | 'file'
const activeInputMode = ref<'manual' | 'file'>('manual')
const importQuestionsRef = ref<any>(null)
const isImportingFile = ref(false)

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

// Compute total marks across all draft questions
const totalDraftMarks = computed(() => {
  let marks = 0
  if (!Array.isArray(draftQuestions.value)) return 0
  draftQuestions.value.forEach((typeGroup: any) => {
    if (typeGroup.instructionGroups) {
      typeGroup.instructionGroups.forEach((instGroup: any) => {
        if (instGroup.questions) {
          instGroup.questions.forEach((q: any) => {
            marks += Number(q.marks) || 0
          })
        }
      })
    }
  })
  return marks
})

// Compute breakdown of drafts by type
const draftsByType = computed(() => {
  const counts: Record<string, number> = {}
  if (!Array.isArray(draftQuestions.value)) return counts
  draftQuestions.value.forEach((typeGroup: any) => {
    let typeCount = 0
    if (typeGroup.instructionGroups) {
      typeGroup.instructionGroups.forEach((instGroup: any) => {
        if (instGroup.questions) typeCount += instGroup.questions.length
      })
    }
    counts[typeGroup.questionType] = typeCount
  })
  return counts
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

// Convert parsed question row from file import to bank draft payload
const convertParsedQuestionToBankDraft = (q: ParsedQuestionRow, currentChapter: string = '1') => {
  let mappedType = q.type // 'multiple_choice' | 'true_false' | 'fill_in_blank' | 'short_answer' | 'matching'
  if (mappedType === 'fill_blank') mappedType = 'fill_in_blank'
  if (mappedType === 'mcq') mappedType = 'multiple_choice'
  if (mappedType === 'tf') mappedType = 'true_false'
  if (mappedType === 'sa') mappedType = 'short_answer'

  let qData: any = null
  let optionsPayload: any = null
  let corrAns = q.correct_answer || ''

  if (mappedType === 'multiple_choice') {
    const opts = (q.options || []).map((opt: any, idx: number) => {
      const label = String.fromCharCode(65 + idx)
      const text = typeof opt === 'object' && opt.text ? opt.text : String(opt)
      return { label, text }
    })
    optionsPayload = opts
    qData = {
      options: opts,
      shuffle_choices: true
    }
  } else if (mappedType === 'true_false') {
    const lower = String(corrAns).toLowerCase().trim()
    if (lower === 'true' || lower === 'a' || lower === '1' || lower === 'yes' || lower === 't') corrAns = 'True'
    else if (lower === 'false' || lower === 'b' || lower === '0' || lower === 'no' || lower === 'f') corrAns = 'False'
    optionsPayload = [
      { label: 'True', text: 'True' },
      { label: 'False', text: 'False' }
    ]
  } else if (mappedType === 'fill_in_blank') {
    const answers = (q as any).question_data?.answers || (corrAns ? String(corrAns).split(',').map(s => s.trim()).filter(Boolean) : [])
    qData = {
      answers: answers.length > 0 ? answers : [String(corrAns || '')],
      case_sensitive: false
    }
  } else if (mappedType === 'short_answer') {
    qData = {
      expected_answer: corrAns,
      max_length: 255
    }
  } else if (mappedType === 'matching') {
    const colA = q.column_a || (q as any).question_data?.column_a || []
    const rawColB = q.column_b || (q as any).question_data?.column_b || []
    const colB = rawColB.map((item: any, idx: number) => ({
      label: (typeof item === 'object' && item.label) ? item.label : String.fromCharCode(65 + idx),
      text: (typeof item === 'object' && item.text) ? item.text : String(item)
    }))
    const correctAnswers = q.correct_answers || (q as any).question_data?.correct_answers || {}
    const perItemMark = q.marks_per_item || (q as any).question_data?.marks_per_item || (colA.length > 0 ? Math.round(((q.marks || 1) / colA.length) * 100) / 100 : 1)

    qData = {
      column_a: colA,
      column_b: colB,
      correct_answers: correctAnswers,
      marks_per_item: perItemMark
    }
  }

  const cleanText = (q.text && q.text.trim()) ? q.text : (mappedType === 'matching' ? '<p>Match the following items:</p>' : '<p>Question</p>')

  return {
    type: mappedType,
    title: q.instruction || 'Instruction',
    instruction: q.instruction || '',
    description: q.description || '',
    text: cleanText,
    options: optionsPayload,
    correct_answer: corrAns ? String(corrAns) : null,
    explanation: '',
    marks: Math.max(1, Math.round(Number(q.marks) || 1)),
    difficulty: q.difficulty || 'Medium',
    chapter: `Chapter ${currentChapter || '1'}`,
    settings: {
      requiredQuestion: true,
      allowPartialMarks: false,
      negativeMarking: false,
      showExplanation: true
    },
    question_data: qData,
    status: 'draft'
  }
}

// Bulk save handler passed to ImportQuestionsFile
const saveImportedQuestionsToBank = async (questionsToImport: ParsedQuestionRow[]) => {
  const converted = questionsToImport.map(q => convertParsedQuestionToBankDraft(q, chapter.value))
  try {
    const res = await qbStore.bulkSaveQuestionDrafts(bankId, converted)
    await qbStore.fetchDraftQuestions(bankId)
    const marksSum = converted.reduce((sum, q) => sum + (Number(q.marks) || 0), 0)
    triggerToast(`Successfully imported ${converted.length} question(s) (${marksSum} marks) as drafts!`)
    return {
      count: converted.length,
      marks: marksSum
    }
  } catch (err: any) {
    console.error("Bulk save failed, attempting individual draft saves...", err)
    let savedCount = 0
    let totalMarks = 0
    for (const qPayload of converted) {
      await qbStore.saveQuestionDraft(bankId, qPayload)
      savedCount++
      totalMarks += Number(qPayload.marks) || 0
    }
    await qbStore.fetchDraftQuestions(bankId)
    triggerToast(`Successfully imported ${savedCount} question(s) as drafts!`)
    return { count: savedCount, marks: totalMarks }
  }
}

const onFileImported = async (data: { count: number; marks: number }) => {
  await qbStore.fetchDraftQuestions(bankId)
  triggerToast(`Import complete: ${data.count} draft questions added!`)
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
    if (activeInputMode.value === 'file' && importQuestionsRef.value && !importQuestionsRef.value.isImported && importQuestionsRef.value.hasSelectedQuestions) {
      isImportingFile.value = true
      try {
        await importQuestionsRef.value.importSelectedQuestions()
      } catch (err: any) {
        console.error("Auto-import before Step 3 failed", err)
      } finally {
        isImportingFile.value = false
      }
    }
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
  if (activeInputMode.value === 'file') {
    if (importQuestionsRef.value && !importQuestionsRef.value.isImported && importQuestionsRef.value.hasSelectedQuestions) {
      isImportingFile.value = true
      try {
        await importQuestionsRef.value.importSelectedQuestions()
      } catch (err: any) {
        console.error("Auto import before review failed", err)
        triggerToast(err.message || "Failed to import file questions. Please check the file.")
        isImportingFile.value = false
        return
      } finally {
        isImportingFile.value = false
      }
    }
    await goToStep(3)
    return
  }
  
  if (isFormEmpty()) {
    await goToStep(3)
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
    await qbStore.fetchDraftQuestions(bankId)
  } catch (err: any) {
    console.error("Failed auto-saving draft before review", err)
    triggerToast(err.message || "Failed to save draft. Please try again.")
    return
  }
  await goToStep(3)
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
    activeInputMode.value = 'manual'
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
  <div class="min-h-screen bg-[#f8fafc] p-4 sm:p-6 lg:p-10 font-sans pb-36">

    <!-- Toast Notification -->
    <transition enter-active-class="transition duration-300 ease-out" enter-from-class="transform translate-y-2 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="transform translate-y-0 opacity-100" leave-to-class="transform translate-y-2 opacity-0">
      <div v-if="showToast" class="fixed top-6 right-6 z-[200] flex items-center gap-3 px-5 py-3.5 bg-slate-900 text-white text-xs font-semibold rounded-2xl shadow-2xl border border-slate-800">
        <div class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
          <Check class="w-3.5 h-3.5" />
        </div>
        <span>{{ toastMessage }}</span>
      </div>
    </transition>

    <!-- Top Navigation Breadcrumbs -->
    <div class="max-w-[1240px] mx-auto mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 flex-wrap">
          <router-link to="/instructor/question-banks" class="hover:text-slate-700 transition-colors flex items-center gap-1.5">
            <BookOpen class="w-3.5 h-3.5 text-slate-400" />
            <span>Question Banks</span>
          </router-link>
          <span class="text-slate-300">/</span>
          <router-link :to="`/instructor/question-banks/${bankId}`" class="hover:text-slate-700 transition-colors truncate max-w-[200px] sm:max-w-none text-slate-600 font-bold">
            {{ bank?.title || 'Question Bank' }}
          </router-link>
          <span class="text-slate-300">/</span>
          <span class="px-2 py-0.5 rounded-md bg-[#5138ed]/10 text-[#5138ed] font-bold text-[11px]">
            Question Wizard
          </span>
        </div>

        <router-link
          :to="`/instructor/question-banks/${bankId}`"
          class="px-3.5 py-2 bg-white border border-slate-200/90 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-colors flex items-center gap-2 shadow-2xs whitespace-nowrap self-start min-h-[38px]"
        >
          <ArrowLeft class="w-3.5 h-3.5 text-slate-400" />
          <span>Exit to Bank</span>
        </router-link>
      </div>

      <!-- Modern Interactive Stepper Hero Card -->
      <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5 sm:p-7 relative overflow-hidden">
        <!-- Background subtle glow -->
        <div class="absolute -top-24 -right-24 w-72 h-72 bg-gradient-to-br from-indigo-100/40 via-purple-50/20 to-transparent rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 border border-indigo-100 text-[#5138ed] text-[11px] font-bold uppercase tracking-wider">
                Question Bank Builder
              </span>
              <span v-if="bank?.course_code" class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-bold">
                {{ bank?.course_code }}
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
              {{ currentStep === 1 ? 'Step 1: Question Bank Details' : currentStep === 2 ? 'Step 2: Add or Import Questions' : 'Step 3: Review & Publish' }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xl">
              {{ currentStep === 1 
                  ? 'Review target bank details, enrolled course assignments, and questions metrics.' 
                  : currentStep === 2 
                  ? 'Compose dynamic questions manually or bulk-import via Excel, CSV, JSON, or PDF.' 
                  : 'Review all questions grouped by type and instructor instructions before publishing.' }}
            </p>
          </div>

          <!-- Desktop & Tablet Connected Stepper (3 Steps) -->
          <div class="flex items-center gap-1.5 sm:gap-3 bg-slate-50/80 p-2 sm:p-2.5 rounded-2xl border border-slate-200/80 shrink-0">
            <!-- Step 1 Button -->
            <button
              @click="goToStep(1)"
              :class="[
                'flex items-center gap-2.5 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-bold transition-all min-h-[42px] cursor-pointer',
                currentStep === 1
                  ? 'bg-[#5138ed] text-white shadow-md shadow-indigo-300/40'
                  : currentStep > 1
                  ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                  : 'text-slate-500 hover:bg-slate-200/60'
              ]"
            >
              <span
                :class="[
                  'w-6 h-6 rounded-lg flex items-center justify-center text-xs font-extrabold shrink-0',
                  currentStep === 1
                    ? 'bg-white/20 text-white'
                    : currentStep > 1
                    ? 'bg-emerald-500 text-white'
                    : 'bg-slate-200 text-slate-600'
                ]"
              >
                <Check v-if="currentStep > 1" class="w-3.5 h-3.5 stroke-[3]" />
                <span v-else>1</span>
              </span>
              <div class="text-left hidden sm:block">
                <span class="block leading-none">Bank Info</span>
                <span :class="currentStep === 1 ? 'text-indigo-100' : 'text-slate-400'" class="text-[10px] font-normal">Overview</span>
              </div>
            </button>

            <!-- Connector -->
            <div :class="currentStep > 1 ? 'bg-emerald-400' : 'bg-slate-200'" class="w-3 sm:w-6 h-[2px] rounded-full transition-colors"></div>

            <!-- Step 2 Button -->
            <button
              @click="goToStep(2)"
              :class="[
                'flex items-center gap-2.5 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-bold transition-all min-h-[42px] cursor-pointer',
                currentStep === 2
                  ? 'bg-[#5138ed] text-white shadow-md shadow-indigo-300/40'
                  : currentStep > 2
                  ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                  : 'text-slate-500 hover:bg-slate-200/60'
              ]"
            >
              <span
                :class="[
                  'w-6 h-6 rounded-lg flex items-center justify-center text-xs font-extrabold shrink-0',
                  currentStep === 2
                    ? 'bg-white/20 text-white'
                    : currentStep > 2
                    ? 'bg-emerald-500 text-white'
                    : 'bg-slate-200 text-slate-600'
                ]"
              >
                <Check v-if="currentStep > 2" class="w-3.5 h-3.5 stroke-[3]" />
                <span v-else>2</span>
              </span>
              <div class="text-left hidden sm:block">
                <span class="block leading-none">Add Questions</span>
                <span :class="currentStep === 2 ? 'text-indigo-100' : 'text-slate-400'" class="text-[10px] font-normal">Manual &amp; Import</span>
              </div>
            </button>

            <!-- Connector -->
            <div :class="currentStep > 2 ? 'bg-emerald-400' : 'bg-slate-200'" class="w-3 sm:w-6 h-[2px] rounded-full transition-colors"></div>

            <!-- Step 3 Button -->
            <button
              @click="goToStep(3)"
              :class="[
                'flex items-center gap-2.5 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-bold transition-all min-h-[42px] cursor-pointer relative',
                currentStep === 3
                  ? 'bg-[#5138ed] text-white shadow-md shadow-indigo-300/40'
                  : 'text-slate-500 hover:bg-slate-200/60'
              ]"
            >
              <span
                :class="[
                  'w-6 h-6 rounded-lg flex items-center justify-center text-xs font-extrabold shrink-0',
                  currentStep === 3 ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'
                ]"
              >
                3
              </span>
              <div class="text-left hidden sm:block">
                <span class="block leading-none">Review &amp; Publish</span>
                <span :class="currentStep === 3 ? 'text-indigo-100' : 'text-slate-400'" class="text-[10px] font-normal">
                  {{ (totalDraftCount > 0 || (activeInputMode === 'file' && importQuestionsRef?.selectedCount)) ? `${totalDraftCount || importQuestionsRef?.selectedCount} Drafts` : 'Ready' }}
                </span>
              </div>

              <!-- Draft count pill if any -->
              <span
                v-if="(totalDraftCount > 0 || (activeInputMode === 'file' && importQuestionsRef?.selectedCount)) && currentStep !== 3"
                class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-amber-500 text-white text-[10px] font-black rounded-full shadow-xs"
              >
                {{ totalDraftCount || importQuestionsRef?.selectedCount }}
              </span>
            </button>
          </div>
        </div>

        <!-- Mobile Step Title Bar -->
        <div class="sm:hidden mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold">
          <span class="text-slate-400">Step {{ currentStep }} of 3</span>
          <span class="text-[#5138ed]">
            {{ currentStep === 1 ? '1. Bank Info' : currentStep === 2 ? '2. Add Questions' : '3. Review & Publish' }}
          </span>
        </div>
      </div>
    </div>

    <!-- MAIN STEP CONTAINER -->
    <div class="max-w-[1240px] mx-auto">

      <!-- ====================================================================== -->
      <!-- STEP 1: QUESTION BANK DETAILS & OVERVIEW                               -->
      <!-- ====================================================================== -->
      <div v-if="currentStep === 1" class="space-y-6">
        
        <!-- Loading State -->
        <div v-if="isLoading" class="p-12 text-center bg-white rounded-3xl border border-slate-200/80 shadow-xs">
          <div class="w-10 h-10 border-3 border-[#5138ed] border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-sm font-bold text-slate-700">Loading Question Bank Information...</p>
        </div>

        <template v-else>
          <!-- Bank Hero Card -->
          <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs relative overflow-hidden">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-6 border-b border-slate-100">
              <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center shrink-0 shadow-md shadow-indigo-200">
                  <BookOpen class="w-7 h-7" />
                </div>
                <div>
                  <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold rounded-md">
                      ● Active Bank
                    </span>
                    <span class="px-2.5 py-0.5 bg-indigo-50 text-[#5138ed] text-[11px] font-bold rounded-md">
                      {{ bank?.course_code || 'CS301' }}
                    </span>
                    <span class="text-xs text-slate-400">•</span>
                    <span class="text-xs text-slate-500 font-semibold">
                      {{ bank?.academic_year || 'Academic Year' }} • {{ bank?.semester || 'Semester I' }}
                    </span>
                  </div>
                  <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ bank?.title }}</h2>
                  <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl leading-relaxed">
                    {{ bank?.description || 'No description provided for this question repository.' }}
                  </p>
                </div>
              </div>

              <!-- Quick action to proceed to Step 2 -->
              <button
                @click="goToStep(2)"
                class="px-5 py-3 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-200 transition-all flex items-center gap-2 cursor-pointer self-start shrink-0"
              >
                <span>Add / Import Questions</span>
                <ArrowRight class="w-4 h-4" />
              </button>
            </div>

            <!-- Key Metrics Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
              <!-- Metric 1: Total Bank Questions -->
              <div class="bg-slate-50/70 p-4 sm:p-5 rounded-2xl border border-slate-200/70 hover:border-indigo-200 transition-colors">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Bank Questions</span>
                  <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center">
                    <Layers class="w-4 h-4" />
                  </div>
                </div>
                <p class="text-2xl font-black text-slate-900">{{ bank?.total_questions || 0 }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Published &amp; ready for exams</p>
              </div>

              <!-- Metric 2: Total Marks -->
              <div class="bg-slate-50/70 p-4 sm:p-5 rounded-2xl border border-slate-200/70 hover:border-indigo-200 transition-colors">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Marks</span>
                  <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <CheckCircle2 class="w-4 h-4" />
                  </div>
                </div>
                <p class="text-2xl font-black text-slate-900">{{ bank?.total_marks || 0 }} pts</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Cumulative points available</p>
              </div>

              <!-- Metric 3: Active Session Drafts -->
              <div class="bg-slate-50/70 p-4 sm:p-5 rounded-2xl border border-slate-200/70 hover:border-indigo-200 transition-colors">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Current Drafts</span>
                  <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <Edit3 class="w-4 h-4" />
                  </div>
                </div>
                <p class="text-2xl font-black text-amber-600">{{ totalDraftCount }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Pending review in this session</p>
              </div>

              <!-- Metric 4: Assigned Course -->
              <div class="bg-slate-50/70 p-4 sm:p-5 rounded-2xl border border-slate-200/70 hover:border-indigo-200 transition-colors">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course &amp; Dept</span>
                  <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <BookOpen class="w-4 h-4" />
                  </div>
                </div>
                <p class="text-sm font-bold text-slate-800 truncate">{{ bank?.course_name }}</p>
                <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ bank?.department || 'Computer Science' }}</p>
              </div>
            </div>
          </div>

          <!-- Question Input Workflows Card -->
          <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
            <h3 class="text-base font-bold text-slate-900 mb-2 flex items-center gap-2">
              <Sparkles class="w-4 h-4 text-[#5138ed]" />
              <span>How Would You Like to Add Questions?</span>
            </h3>
            <p class="text-xs text-slate-500 mb-6">Choose between manual authoring or bulk file import. You can use both in the same bank.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <!-- Option Card 1: Manual Input -->
              <div 
                @click="activeInputMode = 'manual'; goToStep(2)"
                class="group p-6 rounded-2xl border-2 border-slate-200 hover:border-[#5138ed] bg-slate-50/40 hover:bg-indigo-50/20 transition-all cursor-pointer relative"
              >
                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-[#5138ed] group-hover:bg-[#5138ed] group-hover:text-white flex items-center justify-center transition-colors mb-4 shadow-2xs">
                  <Edit3 class="w-6 h-6" />
                </div>
                <h4 class="text-base font-bold text-slate-900 group-hover:text-[#5138ed] transition-colors">
                  1. Manual Question Entry
                </h4>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                  Design individual questions with rich text, images, formula support, difficulty grading, and interactive letter-matched answer keys.
                </p>
                <div class="mt-4 flex items-center gap-2 text-xs font-bold text-[#5138ed]">
                  <span>Launch Manual Form</span>
                  <ArrowRight class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" />
                </div>
              </div>

              <!-- Option Card 2: File Import -->
              <div 
                @click="activeInputMode = 'file'; goToStep(2)"
                class="group p-6 rounded-2xl border-2 border-slate-200 hover:border-emerald-500 bg-slate-50/40 hover:bg-emerald-50/20 transition-all cursor-pointer relative"
              >
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center transition-colors mb-4 shadow-2xs">
                  <Upload class="w-6 h-6" />
                </div>
                <div class="flex items-center gap-2 mb-1">
                  <h4 class="text-base font-bold text-slate-900 group-hover:text-emerald-700 transition-colors">
                    2. Import from File
                  </h4>
                  <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">
                    Fast Bulk Upload
                  </span>
                </div>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                  Upload dozens of questions in seconds using Excel (.xlsx), CSV, JSON, or PDF formats with automated schema validation and preview.
                </p>
                <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-600">
                  <span>Open File Importer</span>
                  <ArrowRight class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" />
                </div>
              </div>
            </div>
          </div>
        </template>

      </div>

      <!-- ====================================================================== -->
      <!-- STEP 2: ADD OR IMPORT QUESTIONS                                         -->
      <!-- ====================================================================== -->
      <Teleport to="body" :disabled="!isEditingModalOpen">
        <div 
          v-show="currentStep === 2 || isEditingModalOpen" 
          :class="isEditingModalOpen ? 'fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm' : ''"
        >
          <div :class="isEditingModalOpen ? 'bg-[#f8fafc] w-full max-w-[1240px] rounded-3xl shadow-2xl relative my-auto max-h-[92vh] flex flex-col overflow-hidden border border-slate-200' : 'w-full'">
            
            <!-- Modal Header (Only shown when editing an existing draft from Step 3) -->
            <div v-if="isEditingModalOpen" class="flex items-center justify-between p-5 sm:p-6 border-b border-slate-200 shrink-0 bg-white">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center">
                  <Edit3 class="w-4 h-4" />
                </div>
                <div>
                  <h2 class="text-base font-bold text-slate-900">Edit Question Draft</h2>
                  <p class="text-xs text-slate-500">Update question content, marks, or options</p>
                </div>
              </div>
              <button @click="closeEditModal" class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
              </button>
            </div>

            <!-- Content Area -->
            <div :class="isEditingModalOpen ? 'p-5 sm:p-6 overflow-y-auto space-y-6 flex-1' : 'space-y-6 w-full'">

              <!-- Modern Source Selector Bar (Manual vs File Import) - Hidden during Edit Modal -->
              <div v-if="!isEditingModalOpen" class="bg-white rounded-3xl border border-slate-200/80 p-4 sm:p-6 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 pb-4 border-b border-slate-100">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center font-black">
                      2
                    </div>
                    <div>
                      <h3 class="text-sm font-bold text-slate-900">Select Question Creation Method</h3>
                      <p class="text-xs text-slate-500">Choose manual input or import existing question documents.</p>
                    </div>
                  </div>

                  <!-- Quick draft count & jump button -->
                  <div class="flex items-center gap-2 self-start sm:self-auto">
                    <span class="px-3 py-1 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold rounded-xl flex items-center gap-1.5">
                      <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                      {{ totalDraftCount }} Drafts in Session
                    </span>

                    <button
                      v-if="totalDraftCount > 0"
                      @click="goToStep(3)"
                      class="px-3 py-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition-colors flex items-center gap-1 shadow-2xs cursor-pointer"
                    >
                      <Eye class="w-3.5 h-3.5 text-slate-400" />
                      <span>Review Drafts →</span>
                    </button>

                    <button
                      @click="toggleFullscreen"
                      class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer"
                      :title="isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'"
                    >
                      <Maximize2 v-if="!isFullscreen" class="w-4 h-4" />
                      <Minimize2 v-else class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <!-- Two Source Tabs: [ Manual Entry ] vs [ Import from File ] -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <!-- Tab 1: Manual Entry -->
                  <button
                    type="button"
                    @click="activeInputMode = 'manual'"
                    :class="[
                      'flex items-center gap-4 p-4 rounded-2xl border-2 transition-all text-left cursor-pointer group',
                      activeInputMode === 'manual'
                        ? 'border-[#5138ed] bg-indigo-50/30 shadow-xs'
                        : 'border-slate-200 hover:border-slate-300 bg-white'
                    ]"
                  >
                    <div 
                      :class="activeInputMode === 'manual' ? 'bg-[#5138ed] text-white shadow-xs' : 'bg-slate-100 text-slate-600 group-hover:bg-indigo-50 group-hover:text-[#5138ed]'"
                      class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                    >
                      <Edit3 class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-slate-900">1. Manual Question Entry</p>
                        <span v-if="activeInputMode === 'manual'" class="text-[10px] font-black uppercase bg-[#5138ed] text-white px-2 py-0.5 rounded-full">
                          Active
                        </span>
                      </div>
                      <p class="text-xs text-slate-500 truncate mt-0.5">Author questions with rich-text editor and choices</p>
                    </div>
                  </button>

                  <!-- Tab 2: Import from File -->
                  <button
                    type="button"
                    @click="activeInputMode = 'file'"
                    :class="[
                      'flex items-center gap-4 p-4 rounded-2xl border-2 transition-all text-left cursor-pointer group',
                      activeInputMode === 'file'
                        ? 'border-emerald-500 bg-emerald-50/30 shadow-xs'
                        : 'border-slate-200 hover:border-slate-300 bg-white'
                    ]"
                  >
                    <div 
                      :class="activeInputMode === 'file' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 group-hover:bg-emerald-50 group-hover:text-emerald-600'"
                      class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                    >
                      <Upload class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                          <p class="text-sm font-bold text-slate-900">2. Import from File</p>
                          <span class="text-[10px] font-bold px-1.5 py-0.2 bg-emerald-100 text-emerald-800 rounded">New</span>
                        </div>
                        <span v-if="activeInputMode === 'file'" class="text-[10px] font-black uppercase bg-emerald-600 text-white px-2 py-0.5 rounded-full">
                          Active
                        </span>
                      </div>
                      <p class="text-xs text-slate-500 truncate mt-0.5">Excel (.xlsx), CSV, JSON, or PDF documents</p>
                    </div>
                  </button>
                </div>
              </div>

              <!-- ========================================================== -->
              <!-- SUB-SECTION A: MANUAL QUESTION INPUT                       -->
              <!-- ========================================================== -->
              <div v-show="activeInputMode === 'manual' || isEditingModalOpen" class="space-y-6">
                <!-- Base Details Card -->
                <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs">
                  <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                      <span class="w-2.5 h-2.5 rounded-full bg-[#5138ed]"></span>
                      <h3 class="text-sm font-bold text-slate-900">Dynamic Question Details</h3>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-400">Configure grading, chapter, and question type</span>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                    <!-- Question Type -->
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1.5">Question Type <span class="text-rose-500">*</span></label>
                      <select v-model="questionType" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] appearance-none cursor-pointer shadow-2xs">
                        <option>Multiple Choice (MCQ)</option>
                        <option>True / False</option>
                        <option>Fill in the Blank</option>
                        <option>Short Answer</option>
                        <option>Matching</option>
                      </select>
                    </div>

                    <!-- Difficulty -->
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1.5">Difficulty</label>
                      <select v-model="difficulty" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] appearance-none cursor-pointer shadow-2xs">
                        <option>Easy</option>
                        <option>Medium</option>
                        <option>Hard</option>
                      </select>
                    </div>

                    <!-- Score Marks -->
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Score Marks <span class="text-rose-500">*</span>
                      </label>
                      <input v-model="score" type="number" min="1" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] shadow-2xs" />
                    </div>

                    <!-- Chapter -->
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1.5">Chapter <span class="text-rose-500">*</span></label>
                      <div class="flex items-center">
                        <span class="px-3 py-2.5 bg-slate-100 border border-slate-200 border-r-0 rounded-l-xl text-xs text-slate-500 font-semibold whitespace-nowrap">
                          Chapter
                        </span>
                        <input v-model="chapter" type="number" min="1" class="w-full bg-white border border-slate-200 rounded-r-xl px-3.5 py-2.5 text-xs font-semibold text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] shadow-2xs" placeholder="1" />
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Instruction & Question Content -->
                <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1.5">Instructor Instruction <span class="text-rose-500">*</span></label>
                      <input 
                        type="text" 
                        v-model="title" 
                        list="instruction-options" 
                        placeholder="e.g., Choose the correct answer for each question" 
                        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] shadow-2xs transition-colors" 
                      />
                      <datalist id="instruction-options">
                        <option v-for="inst in instructionOptions" :key="inst" :value="inst"></option>
                      </datalist>
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1.5">Question Description (Optional)</label>
                      <input v-model="description" type="text" placeholder="Additional hints or context for students..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] shadow-2xs" />
                    </div>
                  </div>

                  <!-- Question Text (Single Editor for non-matching) -->
                  <div v-if="questionType !== 'Matching'">
                    <label class="block text-xs font-bold text-slate-700 mb-2">Question Text <span class="text-rose-500">*</span></label>
                    <RichTextEditor v-model="questionText" placeholder="Type or paste your question content here..." minHeight="180px" />
                  </div>

                  <!-- Matching Editor (Dual Column) -->
                  <div v-else>
                    <div class="mb-4">
                      <h4 class="text-sm font-bold text-slate-900">Matching Pairs Setup</h4>
                      <p class="text-xs text-slate-500">Configure Column A prompts and Column B items to be matched.</p>
                    </div>

                    <!-- Shared Active Cell Editor -->
                    <div v-if="activeMatchingCell" class="mb-5 bg-indigo-50/40 border border-indigo-200 rounded-2xl p-4 pt-6 relative">
                      <div class="absolute -top-3 left-4 px-2.5 py-0.5 bg-[#5138ed] text-white text-[10px] font-bold uppercase rounded-md shadow-xs">
                        Editing: Column {{ activeMatchingCell.col === 'left' ? 'A' : 'B' }} • Row {{ activeMatchingCell.rowIndex + 1 }}
                      </div>
                      <RichTextEditor v-model="sharedEditorContent" placeholder="Type statement content..." minHeight="110px" />
                    </div>

                    <div class="space-y-3">
                      <div class="grid grid-cols-[1fr_1fr_auto] gap-4 font-bold text-[11px] text-slate-400 uppercase tracking-wider px-2">
                        <div>Column A (Prompts) <span class="text-rose-500">*</span></div>
                        <div>Column B (Choices) <span class="text-rose-500">*</span></div>
                        <div class="w-8"></div>
                      </div>

                      <div v-for="(pair, index) in matchPairs" :key="pair.id" class="grid grid-cols-[1fr_1fr_auto] gap-4 items-center">
                        <!-- Column A cell -->
                        <div class="flex items-start gap-2.5">
                          <span class="w-6 shrink-0 mt-3 font-bold text-slate-400 text-xs text-right">{{ index + 1 }}.</span>
                          <div 
                            @click="activeMatchingCell = { rowIndex: index, col: 'left' }"
                            :class="[
                              'w-full min-h-[56px] max-h-[110px] overflow-y-auto bg-white border rounded-xl p-3 text-xs text-slate-700 cursor-pointer transition-all prose prose-sm',
                              activeMatchingCell?.rowIndex === index && activeMatchingCell?.col === 'left' 
                                ? 'border-[#5138ed] ring-2 ring-indigo-100 shadow-xs' 
                                : 'border-slate-200 hover:border-slate-300'
                            ]"
                          >
                            <div v-if="pair.left" v-html="pair.left"></div>
                            <div v-else class="text-slate-400 italic">Click to edit Column A...</div>
                          </div>
                        </div>

                        <!-- Column B cell -->
                        <div class="flex items-start gap-2.5">
                          <span class="w-6 shrink-0 mt-3 font-bold text-slate-400 text-xs text-right">{{ getLetterLabel(index) }}.</span>
                          <div 
                            @click="activeMatchingCell = { rowIndex: index, col: 'right' }"
                            :class="[
                              'w-full min-h-[56px] max-h-[110px] overflow-y-auto bg-white border rounded-xl p-3 text-xs text-slate-700 cursor-pointer transition-all prose prose-sm',
                              activeMatchingCell?.rowIndex === index && activeMatchingCell?.col === 'right' 
                                ? 'border-[#5138ed] ring-2 ring-indigo-100 shadow-xs' 
                                : 'border-slate-200 hover:border-slate-300'
                            ]"
                          >
                            <div v-if="pair.right" v-html="pair.right"></div>
                            <div v-else class="text-slate-400 italic">Click to edit Column B...</div>
                          </div>
                        </div>

                        <!-- Delete Pair -->
                        <button 
                          @click="removeMatchPair(index)" 
                          class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors cursor-pointer self-start mt-2"
                          title="Remove row"
                        >
                          <Trash2 class="w-4 h-4" />
                        </button>
                      </div>
                    </div>

                    <button 
                      @click="addMatchPair" 
                      class="mt-4 px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-[#5138ed] border border-indigo-200 text-xs font-bold rounded-xl transition-colors cursor-pointer inline-flex items-center gap-1.5"
                    >
                      <Plus class="w-3.5 h-3.5" />
                      <span>Add Matching Row</span>
                    </button>
                  </div>
                </div>

                <!-- Question Type Specific Content -->
                <!-- 1. MCQ -->
                <div v-if="questionType === 'Multiple Choice (MCQ)'" class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs">
                  <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                    <label class="text-sm font-bold text-slate-900">Answer Options <span class="text-rose-500">*</span></label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer">
                      <input type="checkbox" v-model="shuffleChoices" class="rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" /> 
                      Shuffle Choices Automatically
                    </label>
                  </div>

                  <div class="space-y-3.5 mb-4">
                    <div 
                      v-for="(option, index) in options" 
                      :key="option.id"
                      class="flex items-start gap-3.5 p-3.5 sm:p-4 rounded-2xl border transition-all"
                      :class="correctAnswer === option.label ? 'border-emerald-400 bg-emerald-50/20 ring-1 ring-emerald-300' : 'border-slate-200 bg-slate-50/50'"
                      draggable="true"
                      @dragstart="onDragStart($event, index)"
                      @dragover.prevent
                      @drop="onDrop($event, index)"
                    >
                      <div class="cursor-grab hover:text-slate-700 text-slate-400 mt-2 shrink-0" title="Drag to reorder">
                        <GripVertical class="w-4 h-4" />
                      </div>

                      <div class="flex flex-col items-center gap-2 mt-1 shrink-0">
                        <div class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-xs font-black text-slate-700 shadow-2xs">
                          {{ option.label }}
                        </div>
                        <div 
                          class="w-5 h-5 rounded-full border-2 flex items-center justify-center cursor-pointer transition-colors"
                          :class="correctAnswer === option.label ? 'border-emerald-500' : 'border-slate-300 hover:border-slate-400'"
                          @click="correctAnswer = option.label"
                          title="Mark as correct answer"
                        >
                          <div v-if="correctAnswer === option.label" class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                        </div>
                      </div>

                      <div class="flex-1 min-w-0">
                        <RichTextEditor v-model="option.text" :placeholder="`Enter Option ${option.label} content...`" minHeight="70px" />
                      </div>

                      <button 
                        @click="removeOption(index)" 
                        class="p-2 text-rose-400 hover:bg-rose-50 hover:text-rose-600 rounded-xl transition-colors shrink-0 cursor-pointer mt-1"
                        title="Delete option"
                      >
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </div>
                  </div>

                  <div class="flex items-center gap-3">
                    <button @click="addOption" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-[#5138ed] border border-indigo-200 text-xs font-bold rounded-xl transition-colors cursor-pointer inline-flex items-center gap-1.5">
                      <Plus class="w-3.5 h-3.5" />
                      <span>Add Option</span>
                    </button>
                    <span class="text-[11px] text-slate-400">Up to 10 options allowed.</span>
                  </div>
                </div>

                <!-- 2. True / False -->
                <div v-if="questionType === 'True / False'" class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs">
                  <label class="block text-xs font-bold text-slate-800 mb-3">Select Correct Answer <span class="text-rose-500">*</span></label>
                  <div class="grid grid-cols-2 gap-4 max-w-md">
                    <button 
                      type="button" 
                      @click="correctAnswer = 'True'" 
                      :class="[
                        'p-4 rounded-2xl border-2 font-bold text-xs flex items-center justify-center gap-2 transition-all cursor-pointer',
                        correctAnswer === 'True' 
                          ? 'border-emerald-500 bg-emerald-50 text-emerald-800 shadow-xs' 
                          : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                      ]"
                    >
                      <CheckCircle2 v-if="correctAnswer === 'True'" class="w-4 h-4 text-emerald-600" />
                      <span>True</span>
                    </button>
                    <button 
                      type="button" 
                      @click="correctAnswer = 'False'" 
                      :class="[
                        'p-4 rounded-2xl border-2 font-bold text-xs flex items-center justify-center gap-2 transition-all cursor-pointer',
                        correctAnswer === 'False' 
                          ? 'border-emerald-500 bg-emerald-50 text-emerald-800 shadow-xs' 
                          : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                      ]"
                    >
                      <CheckCircle2 v-if="correctAnswer === 'False'" class="w-4 h-4 text-emerald-600" />
                      <span>False</span>
                    </button>
                  </div>
                </div>

                <!-- 3. Fill in the Blank -->
                <div v-if="questionType === 'Fill in the Blank'" class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs">
                  <div class="flex items-center justify-between mb-3">
                    <label class="text-xs font-bold text-slate-800">Accepted Answers <span class="text-rose-500">*</span></label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer">
                      <input type="checkbox" v-model="fibCaseSensitive" class="rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" /> Case Sensitive
                    </label>
                  </div>
                  <p class="text-[11px] text-slate-400 mb-3">List all acceptable variations (e.g., "Addis Ababa", "addis ababa").</p>
                  <div class="space-y-2.5 mb-4">
                    <div v-for="(ans, index) in fibAnswers" :key="ans.id" class="flex items-center gap-2.5">
                      <input v-model="ans.text" type="text" placeholder="e.g. Addis Ababa" class="w-full max-w-md bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-[#5138ed]" />
                      <button @click="removeFibAnswer(index)" class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors cursor-pointer"><Trash2 class="w-4 h-4" /></button>
                    </div>
                  </div>
                  <button @click="addFibAnswer" class="text-xs font-bold text-[#5138ed] hover:text-indigo-700 cursor-pointer inline-flex items-center gap-1">
                    <Plus class="w-3.5 h-3.5" />
                    <span>Add Accepted Answer</span>
                  </button>
                </div>

                <!-- 4. Short Answer -->
                <div v-if="questionType === 'Short Answer'" class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                      <label class="block text-xs font-bold text-slate-800 mb-1.5">Expected Answer Keywords (Optional)</label>
                      <input v-model="saExpectedAnswer" type="text" placeholder="Expected phrase or keywords..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-[#5138ed]" />
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-800 mb-1.5">Maximum Character Limit</label>
                      <input v-model="saMaxLength" type="number" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-700 focus:outline-none focus:border-[#5138ed]" />
                    </div>
                  </div>
                </div>

                <!-- 5. Matching Answer Keys -->
                <div v-if="questionType === 'Matching'" class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs">
                  <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                    <div>
                      <h4 class="text-sm font-bold text-slate-900">Answer Key Mapping</h4>
                      <p class="text-xs text-slate-500">Pair each Column A statement with its correct Column B answer.</p>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer">
                      <input type="checkbox" v-model="matchShuffle" class="rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" />
                      Shuffle Column B during Exam
                    </label>
                  </div>

                  <div class="grid grid-cols-[1fr_auto] gap-4 mb-2 font-bold text-[11px] text-slate-400 uppercase tracking-wider px-2">
                    <div>Column A Prompt</div>
                    <div>Target Choice</div>
                  </div>

                  <div class="space-y-2.5">
                    <template v-for="(_, index) in matchMappings" :key="index">
                      <div v-if="matchPairs[index]?.left.replace(/<[^>]*>?/gm, '').trim()" class="grid grid-cols-[1fr_auto] gap-4 items-center bg-slate-50/70 p-3 rounded-xl border border-slate-200/80">
                        <div class="text-xs text-slate-700 font-medium truncate flex items-center gap-2">
                          <span class="font-bold text-slate-400 w-5">{{ index + 1 }}.</span>
                          <span class="truncate">{{ matchPairs[index].left.replace(/<[^>]*>?/gm, '').substring(0, 80) }}</span>
                        </div>
                        <div class="w-64 sm:w-80">
                          <select v-model="matchMappings[index]" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-700 font-bold focus:outline-none focus:border-[#5138ed] cursor-pointer shadow-2xs">
                            <option v-for="(pair, i) in matchPairs" :key="i" :value="getLetterLabel(i)">
                              {{ getLetterLabel(i) }} — {{ pair.right.replace(/<[^>]*>?/gm, '').substring(0, 40) || 'Empty' }}
                            </option>
                          </select>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>

                <!-- Explanation -->
                <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-xs">
                  <label class="block text-xs font-bold text-slate-800 mb-2">Global Explanation / Feedback (Optional)</label>
                  <RichTextEditor v-model="explanation" placeholder="Provide feedback or rationales shown after grading..." minHeight="100px" />
                </div>
              </div>

              <!-- ========================================================== -->
              <!-- SUB-SECTION B: IMPORT QUESTIONS FROM FILE                  -->
              <!-- ========================================================== -->
              <div v-show="activeInputMode === 'file' && !isEditingModalOpen">
                <ImportQuestionsFile
                  ref="importQuestionsRef"
                  mode="bank"
                  targetName="Question Bank"
                  :bankId="bankId"
                  :importHandler="saveImportedQuestionsToBank"
                  @import-questions="saveImportedQuestionsToBank"
                  @imported="onFileImported"
                  @next="goToStep(3)"
                  @switch-to-manual="activeInputMode = 'manual'"
                />
              </div>

            </div> <!-- End Content Area -->

            <!-- Edit Modal Footer -->
            <div v-if="isEditingModalOpen" class="p-5 sm:p-6 border-t border-slate-200 shrink-0 bg-white flex justify-end gap-3 rounded-b-3xl">
              <button @click="closeEditModal" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">
                Cancel
              </button>
              <button @click="saveEditModal" class="px-5 py-2.5 rounded-xl bg-[#5138ed] text-white text-xs font-bold hover:bg-indigo-600 transition-colors shadow-md shadow-indigo-200 cursor-pointer">
                Save Changes
              </button>
            </div>

          </div>
        </div>
      </Teleport>

      <!-- ====================================================================== -->
      <!-- STEP 3: REVIEW & PUBLISH DRAFTS                                        -->
      <!-- ====================================================================== -->
      <div v-if="currentStep === 3" class="space-y-6">

        <!-- Top Summary Bar -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-100">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                <span class="text-xs font-bold text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200">
                  Ready for Review
                </span>
              </div>
              <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Review Draft Questions</h2>
              <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Inspect and refine draft questions before publishing them into {{ bank?.title }}.
              </p>
            </div>

            <!-- Publish & Add More Buttons -->
            <div class="flex items-center gap-3 shrink-0 self-start md:self-auto">
              <button
                @click="goToStep(2)"
                class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
              >
                <Plus class="w-4 h-4 text-[#5138ed]" />
                <span>Add More Questions</span>
              </button>

              <button
                @click="handlePublishQuestions"
                :disabled="totalDraftCount === 0 || isPublishing"
                class="px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 disabled:opacity-40 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-indigo-200 flex items-center gap-2 cursor-pointer disabled:cursor-not-allowed"
              >
                <Send v-if="!isPublishing" class="w-4 h-4" />
                <span v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>{{ isPublishing ? 'Publishing...' : `Publish ${totalDraftCount} Question(s)` }}</span>
              </button>
            </div>
          </div>

          <!-- Metrics Strip -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
            <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/60">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Drafts</p>
              <p class="text-xl font-black text-slate-900">{{ totalDraftCount }} Questions</p>
            </div>
            <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/60">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Marks</p>
              <p class="text-xl font-black text-[#5138ed]">{{ totalDraftMarks }} Points</p>
            </div>
            <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/60">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Target Course</p>
              <p class="text-sm font-bold text-slate-800 truncate">{{ bank?.course_code || 'General' }}</p>
            </div>
            <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/60">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Bank Status</p>
              <p class="text-sm font-bold text-emerald-600">Active Repository</p>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="totalDraftCount === 0" class="p-16 text-center bg-white rounded-3xl border border-slate-200/80 shadow-xs">
          <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-[#5138ed] mx-auto flex items-center justify-center mb-4">
            <FileText class="w-8 h-8" />
          </div>
          <h3 class="text-base font-bold text-slate-900 mb-1">No draft questions in this session yet</h3>
          <p class="text-xs text-slate-500 max-w-sm mx-auto mb-5">
            Add questions manually or import an Excel, CSV, JSON, or PDF file from Step 2.
          </p>
          <button 
            @click="goToStep(2)" 
            class="px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-200 transition-colors inline-flex items-center gap-2 cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>Go to Step 2: Add Questions</span>
          </button>
        </div>

        <!-- Drafts List Grouped by Type -->
        <div v-else class="space-y-8">
          <div 
            v-for="typeGroup in draftQuestions" 
            :key="typeGroup.questionType"
            class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs"
          >
            <!-- Type Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
              <div class="flex items-center gap-3">
                <span class="w-3 h-6 bg-[#5138ed] rounded-full inline-block"></span>
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">
                  {{ typeGroup.questionType }}
                </h3>
              </div>
              <span class="px-3 py-1 bg-indigo-50 text-[#5138ed] text-xs font-bold rounded-full">
                {{ draftsByType[typeGroup.questionType] || 0 }} Questions
              </span>
            </div>

            <!-- Iterate Instruction Groups -->
            <div class="space-y-6">
              <div 
                v-for="(instGroup, instIdx) in typeGroup.instructionGroups" 
                :key="instIdx"
                class="space-y-4"
              >
                <!-- Instruction Header -->
                <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/60 flex items-center gap-2.5">
                  <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Instruction:</span>
                  <p class="text-xs font-bold text-slate-800 italic">
                    {{ instGroup.instruction || 'Default Instruction' }}
                  </p>
                </div>

                <!-- Questions in Instruction Group -->
                <div class="space-y-4 pl-2 sm:pl-4 border-l-2 border-indigo-100">
                  <div 
                    v-for="(q, qIdx) in instGroup.questions" 
                    :key="q.id"
                    class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs hover:border-indigo-200 hover:shadow-xs transition-all"
                  >
                    <!-- Question Card Header -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="w-6 h-6 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center text-xs font-black">
                          {{ Number(qIdx) + 1 }}
                        </span>
                        <span :class="getDifficultyClass(q.difficulty)" class="px-2 py-0.5 text-[11px] font-bold rounded-md border">
                          {{ q.difficulty || 'Medium' }}
                        </span>
                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-md bg-slate-100 text-slate-700">
                          {{ q.marks || 1 }} pt{{ (q.marks || 1) > 1 ? 's' : '' }}
                        </span>
                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-md bg-slate-100 text-slate-600">
                          {{ q.chapter || 'Chapter 1' }}
                        </span>
                      </div>

                      <div class="flex items-center gap-1.5">
                        <button 
                          @click="handleEditDraft(q)" 
                          class="p-2 text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 rounded-xl transition-colors cursor-pointer"
                          title="Edit question"
                        >
                          <Edit3 class="w-4 h-4" />
                        </button>
                        <button 
                          @click="handleDeleteDraft(q.id)" 
                          class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors cursor-pointer"
                          title="Delete draft"
                        >
                          <Trash2 class="w-4 h-4" />
                        </button>
                      </div>
                    </div>

                    <!-- Question Text -->
                    <div class="text-xs sm:text-sm text-slate-800 leading-relaxed mb-4 prose prose-sm max-w-none" v-html="q.text"></div>

                    <!-- Options for Multiple Choice -->
                    <div v-if="q.options && q.options.length" class="space-y-2 mb-4">
                      <div 
                        v-for="(opt, oIdx) in q.options" 
                        :key="oIdx"
                        :class="[
                          'flex items-start gap-2.5 p-2 rounded-xl text-xs border transition-colors',
                          q.correct_answer === (opt.label || String.fromCharCode(65 + Number(oIdx)))
                            ? 'bg-emerald-50/50 border-emerald-300 text-emerald-900 font-semibold'
                            : 'bg-slate-50/60 border-slate-200/70 text-slate-700'
                        ]"
                      >
                        <span 
                          :class="q.correct_answer === (opt.label || String.fromCharCode(65 + Number(oIdx))) ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-700'"
                          class="w-5 h-5 shrink-0 rounded-md text-[10px] font-black flex items-center justify-center mt-0.5"
                        >
                          {{ opt.label || String.fromCharCode(65 + Number(oIdx)) }}
                        </span>
                        <span class="pt-0.5" v-html="opt.text || opt"></span>
                      </div>
                    </div>

                    <!-- Display for Matching -->
                    <div v-if="q.type === 'matching'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                      <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 space-y-1.5">
                        <span class="text-[10px] font-black uppercase text-slate-400">Column A (Prompts)</span>
                        <div v-for="(colA, idx) in (q.question_data?.column_a || q.column_a || [])" :key="'A'+idx" class="flex items-start gap-2 text-xs">
                          <span class="w-5 h-5 rounded bg-white text-slate-600 font-bold text-[10px] flex items-center justify-center shrink-0 border border-slate-200 mt-0.5">{{ Number(idx) + 1 }}</span>
                          <span class="pt-0.5" v-html="colA"></span>
                        </div>
                      </div>

                      <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 space-y-1.5">
                        <span class="text-[10px] font-black uppercase text-slate-400">Column B (Choices)</span>
                        <div v-for="(colB, idx) in (q.question_data?.column_b || q.column_b || [])" :key="'B'+idx" class="flex items-start gap-2 text-xs">
                          <span class="w-5 h-5 rounded bg-white text-slate-600 font-bold text-[10px] flex items-center justify-center shrink-0 border border-slate-200 mt-0.5">{{ typeof colB === 'object' ? colB.label : String.fromCharCode(65 + Number(idx)) }}</span>
                          <span class="pt-0.5" v-html="typeof colB === 'object' ? colB.text : colB"></span>
                        </div>
                      </div>
                    </div>

                    <!-- Correct Answer Badge Strip -->
                    <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/70 flex items-center gap-2 text-xs">
                      <span class="font-bold text-slate-500">Correct Answer:</span>
                      <span v-if="q.type === 'multiple_choice' || q.type === 'true_false'" class="font-bold text-emerald-700 bg-emerald-100/60 px-2.5 py-0.5 rounded-md">
                        {{ q.correct_answer }}
                      </span>
                      <span v-else-if="q.type === 'fill_in_blank' || q.type === 'fill_blank'" class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md">
                        {{ q.question_data?.answers?.join(', ') || q.correct_answer }}
                      </span>
                      <span v-else-if="q.type === 'short_answer'" class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md">
                        {{ q.question_data?.expected_answer || q.correct_answer || 'Manual Review' }}
                      </span>
                      <span v-else-if="q.type === 'matching'" class="flex flex-wrap gap-1.5">
                        <template v-if="q.question_data?.correct_answers && Object.keys(q.question_data.correct_answers).length > 0">
                          <span 
                            v-for="(ans, key) in q.question_data.correct_answers" 
                            :key="key" 
                            class="font-bold text-emerald-700 bg-emerald-100/60 px-2 py-0.5 rounded-md text-[11px]"
                          >
                            {{ key }} → {{ ans }}
                          </span>
                        </template>
                        <span v-else-if="q.correct_answer" class="font-bold text-emerald-700 bg-emerald-100/60 px-2 py-0.5 rounded-md text-[11px]">
                          {{ q.correct_answer }}
                        </span>
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

    <!-- STICKY BOTTOM NAVIGATION BAR -->
    <div class="fixed bottom-0 left-0 lg:left-56 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200/90 px-4 sm:px-8 py-3.5 z-40 flex items-center justify-between gap-3 shadow-lg">
      <!-- Step 1 Actions -->
      <template v-if="currentStep === 1">
        <button 
          @click="handleCancel" 
          class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors min-h-[42px] cursor-pointer"
        >
          Cancel &amp; Return
        </button>
        <button 
          @click="goToStep(2)" 
          class="px-6 py-2.5 rounded-xl bg-[#5138ed] hover:bg-indigo-600 text-white text-xs font-bold transition-all shadow-md shadow-indigo-200 flex items-center gap-2 min-h-[42px] cursor-pointer"
        >
          <span>Next: Add Questions</span>
          <ArrowRight class="w-4 h-4" />
        </button>
      </template>

      <!-- Step 2 Actions -->
      <template v-else-if="currentStep === 2">
        <button 
          @click="goToStep(1)" 
          class="px-4 sm:px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors flex items-center gap-1.5 min-h-[42px] cursor-pointer"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Bank Info</span>
        </button>

        <div class="flex items-center gap-2 sm:gap-3">
          <button 
            v-if="activeInputMode === 'manual'"
            @click="handleAddAnother" 
            class="px-4 sm:px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-1.5 min-h-[42px] cursor-pointer"
          >
            <Plus class="w-4 h-4 text-[#5138ed]" />
            <span class="hidden sm:inline">Save &amp; Add Another</span>
            <span class="sm:hidden">Add Another</span>
          </button>

          <button 
            @click="handleReviewAndPublish" 
            :disabled="isImportingFile"
            class="px-5 sm:px-6 py-2.5 rounded-xl bg-[#5138ed] hover:bg-indigo-600 disabled:opacity-50 text-white text-xs font-bold transition-all shadow-md shadow-indigo-200 flex items-center gap-1.5 min-h-[42px] cursor-pointer"
          >
            <span v-if="isImportingFile" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>Review &amp; Publish ({{ totalDraftCount > 0 ? totalDraftCount : (activeInputMode === 'file' && importQuestionsRef?.selectedCount ? importQuestionsRef.selectedCount : 0) }})</span>
            <ArrowRight v-if="!isImportingFile" class="w-4 h-4" />
          </button>
        </div>
      </template>

      <!-- Step 3 Actions -->
      <template v-else-if="currentStep === 3">
        <button 
          @click="goToStep(2)" 
          class="px-4 sm:px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors flex items-center gap-1.5 min-h-[42px] cursor-pointer"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Add Questions</span>
        </button>

        <button 
          @click="handlePublishQuestions" 
          :disabled="isPublishing || totalDraftCount === 0" 
          class="px-5 sm:px-6 py-2.5 rounded-xl bg-[#5138ed] hover:bg-indigo-600 disabled:opacity-40 text-white text-xs font-bold transition-all shadow-md shadow-indigo-200 flex items-center gap-2 min-h-[42px] cursor-pointer disabled:cursor-not-allowed"
        >
          <Send v-if="!isPublishing" class="w-4 h-4" />
          <span v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>{{ isPublishing ? 'Publishing...' : `Publish ${totalDraftCount} Question(s)` }}</span>
        </button>
      </template>
    </div>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body" :disabled="!isDeleteModalOpen">
      <div v-if="isDeleteModalOpen" class="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all border border-slate-200">
          <div class="p-6 text-center">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 mx-auto flex items-center justify-center mb-4">
              <Trash2 class="w-6 h-6" />
            </div>
            <h3 class="text-base font-bold text-slate-900 mb-1">Delete Draft Question?</h3>
            <p class="text-xs text-slate-500">This question draft will be removed from your current editing session.</p>
          </div>
          <div class="flex items-center border-t border-slate-100 bg-slate-50">
            <button @click="closeDeleteModal" class="flex-1 px-4 py-3 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors border-r border-slate-100 cursor-pointer">
              Cancel
            </button>
            <button @click="confirmDeleteDraft" class="flex-1 px-4 py-3 text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
              Delete
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Publish Confirmation Modal -->
    <Teleport to="body" :disabled="!isPublishModalOpen">
      <div v-if="isPublishModalOpen" class="fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all border border-slate-200">
          <div class="p-6 text-center">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-[#5138ed] mx-auto flex items-center justify-center mb-4">
              <Send class="w-6 h-6" />
            </div>
            <h3 class="text-base font-bold text-slate-900 mb-1">Publish All Draft Questions?</h3>
            <p class="text-xs text-slate-500">
              Publish {{ totalDraftCount }} draft question(s) directly to <strong>{{ bank?.title }}</strong>. They will become available for instructors to add to exams.
            </p>
          </div>
          <div class="flex items-center border-t border-slate-100 bg-slate-50">
            <button @click="closePublishModal" class="flex-1 px-4 py-3 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors border-r border-slate-100 cursor-pointer">
              Cancel
            </button>
            <button @click="confirmPublishQuestions" :disabled="isPublishing" class="flex-1 px-4 py-3 text-xs font-bold text-[#5138ed] hover:bg-indigo-50 transition-colors disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer">
              <span v-if="isPublishing" class="w-4 h-4 border-2 border-[#5138ed] border-t-transparent rounded-full animate-spin"></span>
              <span>{{ isPublishing ? 'Publishing...' : 'Publish' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
