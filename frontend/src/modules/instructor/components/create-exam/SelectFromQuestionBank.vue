<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useCreateExamStore } from '../../store/createExamStore'
import apiClient from '../../../../core/api/apiClient'

const emit = defineEmits(['added', 'switch-to-manual', 'switch-to-file'])
const formStore = useCreateExamStore()

const isLoadingBanks = ref(false)
const isLoadingQuestions = ref(false)
const banks = ref<any[]>([])
const selectedBankId = ref<number | string>('')
const bankQuestions = ref<any[]>([])
const currentBank = ref<any>(null)

const searchQuery = ref('')
const selectedType = ref('All Types')
const selectedDifficulty = ref('All Difficulties')
const selectedChapter = ref('All Chapters')

const selectedQuestionIds = ref<number[]>([])
const successBanner = ref('')

onMounted(async () => {
  await fetchBanks()
})

const fetchBanks = async () => {
  isLoadingBanks.value = true
  try {
    const res = await apiClient.get('/instructor/question-banks?all=true')
    banks.value = res.data?.data?.banks || []
    if (banks.value.length > 0) {
      if (!selectedBankId.value || !banks.value.some(b => b.id == selectedBankId.value)) {
        selectedBankId.value = banks.value[0].id
      }
      await loadBankQuestions(selectedBankId.value)
    } else {
      selectedBankId.value = ''
      currentBank.value = null
      bankQuestions.value = []
    }
  } catch (err) {
    console.error('Failed to load question banks:', err)
  } finally {
    isLoadingBanks.value = false
  }
}

const onBankChange = async () => {
  if (!selectedBankId.value) {
    bankQuestions.value = []
    currentBank.value = null
    return
  }
  await loadBankQuestions(selectedBankId.value)
}

const loadBankQuestions = async (bankId: number | string) => {
  isLoadingQuestions.value = true
  selectedQuestionIds.value = []
  successBanner.value = ''
  try {
    const res = await apiClient.get(`/instructor/question-banks/${bankId}`)
    const data = res.data?.data
    currentBank.value = data?.bank || null
    bankQuestions.value = data?.questions || []
  } catch (err) {
    console.error('Failed to load questions from bank:', err)
    bankQuestions.value = []
    currentBank.value = null
  } finally {
    isLoadingQuestions.value = false
  }
}

// Available chapters for filter
const availableChapters = computed(() => {
  const set = new Set<string>()
  bankQuestions.value.forEach(q => {
    if (q.chapter) set.add(String(q.chapter))
  })
  return ['All Chapters', ...Array.from(set)]
})

// Normalization of question types for filtering
const getNormalizedType = (type: string) => {
  const t = (type || '').toLowerCase()
  if (t === 'multiple_choice' || t === 'mcq') return 'MCQ'
  if (t === 'true_false' || t === 'true/false') return 'True/False'
  if (t === 'fill_blank' || t === 'fill_in_the_blank') return 'Fill in Blank'
  if (t === 'short_answer') return 'Short Answer'
  if (t === 'matching') return 'Matching'
  return type
}

// Filtered questions
const filteredQuestions = computed(() => {
  return bankQuestions.value.filter(q => {
    // 1. Search text
    if (searchQuery.value.trim()) {
      const query = searchQuery.value.toLowerCase()
      const textMatch = (q.text || '').toLowerCase().includes(query)
      const topicMatch = (q.topic || '').toLowerCase().includes(query)
      const chapterMatch = (String(q.chapter || '')).toLowerCase().includes(query)
      let optionsMatch = false
      if (Array.isArray(q.options)) {
        optionsMatch = q.options.some((o: any) => 
          (typeof o === 'string' ? o : o.text || o.label || '').toLowerCase().includes(query)
        )
      }
      if (!textMatch && !topicMatch && !chapterMatch && !optionsMatch) return false
    }

    // 2. Type filter
    if (selectedType.value !== 'All Types') {
      if (getNormalizedType(q.type) !== selectedType.value) return false
    }

    // 3. Difficulty filter
    if (selectedDifficulty.value !== 'All Difficulties') {
      if ((q.difficulty || '').toLowerCase() !== selectedDifficulty.value.toLowerCase()) return false
    }

    // 4. Chapter filter
    if (selectedChapter.value !== 'All Chapters') {
      if (String(q.chapter || '') !== selectedChapter.value) return false
    }

    return true
  })
})

// Check if question is already in exam draft
const isQuestionAlreadyInExam = (q: any) => {
  const cleanText = (q.text || '').replace(/<[^>]*>?/gm, '').trim().toLowerCase()
  return formStore.questions.some(eq => {
    const eqClean = (eq.text || '').replace(/<[^>]*>?/gm, '').trim().toLowerCase()
    return cleanText && eqClean === cleanText
  })
}

// Selection helpers
const isAllFilteredSelected = computed(() => {
  if (filteredQuestions.value.length === 0) return false
  return filteredQuestions.value.every(q => selectedQuestionIds.value.includes(q.id))
})

const toggleSelectAll = () => {
  if (isAllFilteredSelected.value) {
    const filteredIds = new Set(filteredQuestions.value.map(q => q.id))
    selectedQuestionIds.value = selectedQuestionIds.value.filter(id => !filteredIds.has(id))
  } else {
    const newSelected = new Set(selectedQuestionIds.value)
    filteredQuestions.value.forEach(q => newSelected.add(q.id))
    selectedQuestionIds.value = Array.from(newSelected)
  }
}

const toggleQuestion = (id: number) => {
  const idx = selectedQuestionIds.value.indexOf(id)
  if (idx > -1) {
    selectedQuestionIds.value.splice(idx, 1)
  } else {
    selectedQuestionIds.value.push(id)
  }
}

// Selected questions stats
const selectedQuestionsCount = computed(() => selectedQuestionIds.value.length)
const selectedQuestionsMarks = computed(() => {
  return bankQuestions.value
    .filter(q => selectedQuestionIds.value.includes(q.id))
    .reduce((sum, q) => sum + (Number(q.marks) || 1), 0)
})

// Helper: Normalize options
const normalizeOptions = (rawOptions: any, type: string) => {
  if (type === 'true_false') {
    return ['True', 'False']
  }
  if (!rawOptions) return []
  if (Array.isArray(rawOptions)) {
    return rawOptions.map((o: any) => {
      if (typeof o === 'string') return o
      return o.text || o.label || JSON.stringify(o)
    })
  }
  if (typeof rawOptions === 'string') {
    try {
      const parsed = JSON.parse(rawOptions)
      if (Array.isArray(parsed)) {
        return parsed.map((o: any) => typeof o === 'string' ? o : o.text || '')
      }
    } catch {
      return rawOptions.split(',').map((s: string) => s.trim())
    }
  }
  return []
}

// Helper: Default instruction based on type
const getDefaultInstruction = (type: string) => {
  const t = (type || '').toLowerCase()
  if (t === 'multiple_choice' || t === 'mcq') return 'Choose the correct answer'
  if (t === 'true_false') return 'Select True or False'
  if (t === 'fill_blank') return 'Fill in the blank with the correct term'
  if (t === 'short_answer') return 'Provide a concise and accurate answer'
  if (t === 'matching') return 'Match the items in Column A with Column B'
  return 'Answer the following question'
}

// Add selected questions to exam draft
const addSelectedToExam = () => {
  const questionsToAdd = bankQuestions.value.filter(q => selectedQuestionIds.value.includes(q.id))
  if (questionsToAdd.length === 0) return

  let addedCount = 0
  let totalAddedMarks = 0

  questionsToAdd.forEach(q => {
    const rawType = (q.type || 'multiple_choice').toLowerCase()
    let mappedType = 'multiple_choice'
    if (rawType.includes('true') || rawType === 'tf') mappedType = 'true_false'
    else if (rawType.includes('fill') || rawType === 'fill_blank') mappedType = 'fill_blank'
    else if (rawType.includes('short') || rawType === 'sa') mappedType = 'short_answer'
    else if (rawType.includes('match')) mappedType = 'matching'

    const instruction = q.instruction || (q.topic ? `Topic: ${q.topic}` : getDefaultInstruction(mappedType))
    const marks = Number(q.marks) || 5

    const rawCorrect = q.correct_answer !== undefined && q.correct_answer !== null 
      ? q.correct_answer 
      : (q.correctAnswer !== undefined && q.correctAnswer !== null ? q.correctAnswer : '')
    let finalCorrect = String(rawCorrect).trim()
    if (!finalCorrect) {
      if (mappedType === 'multiple_choice') finalCorrect = 'A'
      else if (mappedType === 'true_false') finalCorrect = 'True'
    } else if (mappedType === 'true_false') {
      const lower = finalCorrect.toLowerCase()
      if (lower === 'true' || lower === 'a' || lower === '1' || lower === 'yes') finalCorrect = 'True'
      else if (lower === 'false' || lower === 'b' || lower === '0' || lower === 'no') finalCorrect = 'False'
    }

    const questionObj: any = {
      type: mappedType,
      instruction,
      text: q.text || 'Untitled Question',
      difficulty: q.difficulty || 'Medium',
      marks,
      description: q.description || q.topic || '',
      correct_answer: finalCorrect,
      options: normalizeOptions(q.options, mappedType),
      question_data: q.question_data || {}
    }

    // Special handling for matching questions
    if (mappedType === 'matching') {
      const qData = q.question_data || {}
      const pairs = qData.pairs || q.pairs || (Array.isArray(q.options) ? q.options : [])
      const columnA = qData.column_a || q.column_a || pairs.map((p: any) => typeof p === 'string' ? p : p.left || '')
      const columnB = qData.column_b || q.column_b || pairs.map((p: any, i: number) => ({
        label: String.fromCharCode(65 + i),
        text: typeof p === 'string' ? p : p.right || ''
      }))
      const correctAnswers = qData.correct_answers || q.correct_answers || {}

      questionObj.column_a = columnA
      questionObj.column_b = columnB
      questionObj.correct_answers = correctAnswers
      questionObj.pairs = pairs
      questionObj.marks_per_item = qData.marks_per_item || q.marks_per_item || 1
      questionObj.marks = marks
      questionObj.question_data = {
        column_a: columnA,
        column_b: columnB,
        correct_answers: correctAnswers,
        pairs: pairs,
        marks_per_item: questionObj.marks_per_item
      }
    }

    formStore.questions.push(questionObj)
    addedCount++
    totalAddedMarks += marks
  })

  // Clear selections
  selectedQuestionIds.value = []

  successBanner.value = `Successfully added ${addedCount} question${addedCount !== 1 ? 's' : ''} (${totalAddedMarks} marks) from "${currentBank.value?.title || 'Question Bank'}" to your exam draft!`
  emit('added', { count: addedCount, marks: totalAddedMarks })

  // Auto clear success banner after 6 seconds
  setTimeout(() => {
    successBanner.value = ''
  }, 6000)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Success Banner -->
    <div v-if="successBanner" class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between text-emerald-800 shadow-xs animate-in fade-in duration-200">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <div>
          <p class="text-[13px] font-bold">{{ successBanner }}</p>
          <p class="text-[11px] text-emerald-600 mt-0.5">Total questions currently in this exam: <strong class="text-emerald-800">{{ formStore.questions.length }}</strong></p>
        </div>
      </div>
      <button @click="successBanner = ''" class="text-emerald-500 hover:text-emerald-700 font-bold text-sm px-2 py-1">&times;</button>
    </div>

    <!-- Question Bank Selector Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pb-5 border-b border-slate-100">
        <div>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
            <h3 class="text-[16px] font-bold text-slate-800">Choose Question Bank</h3>
          </div>
          <p class="text-[12px] text-slate-500 mt-1">Select from your prepared question repositories to import curated questions into this exam.</p>
        </div>

        <!-- Bank Selector Dropdown -->
        <div class="w-full md:w-80">
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Select Repository</label>
            <button
              type="button"
              @click="fetchBanks"
              :disabled="isLoadingBanks"
              title="Refresh Question Banks"
              class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 disabled:opacity-50 cursor-pointer transition-colors"
            >
              <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoadingBanks }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
              Refresh
            </button>
          </div>
          <div class="relative">
            <select
              v-model="selectedBankId"
              @change="onBankChange"
              :disabled="isLoadingBanks || banks.length === 0"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#5138ed] focus:bg-white appearance-none cursor-pointer disabled:opacity-60 transition-colors"
            >
              <option value="" disabled>-- Select a Question Bank --</option>
              <option v-for="b in banks" :key="b.id" :value="b.id">
                {{ b.title }} ({{ b.total_questions || 0 }} questions)
              </option>
            </select>
            <div class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Current Bank Overview Badge -->
      <div v-if="currentBank" class="flex flex-wrap items-center justify-between gap-4 p-4 bg-slate-50 rounded-xl border border-slate-100">
        <div class="flex items-center gap-4">
          <div class="w-11 h-11 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 font-bold shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
          </div>
          <div>
            <h4 class="text-[14px] font-bold text-slate-800">{{ currentBank.title }}</h4>
            <p class="text-[12px] text-slate-500">
              Course: <span class="font-semibold text-slate-700">{{ currentBank.course_code || 'General' }}</span> • 
              Total Questions: <span class="font-semibold text-slate-700">{{ bankQuestions.length }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <span class="px-3 py-1 bg-white border border-slate-200 rounded-lg text-[12px] font-semibold text-slate-600">
            {{ filteredQuestions.length }} of {{ bankQuestions.length }} visible
          </span>
        </div>
      </div>

      <!-- Empty State if No Banks Exist -->
      <div v-if="!isLoadingBanks && banks.length === 0" class="py-12 text-center">
        <div class="w-14 h-14 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center mx-auto mb-3 border border-amber-100">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <h4 class="text-[15px] font-bold text-slate-800 mb-1">No Question Banks Found</h4>
        <p class="text-[12px] text-slate-500 max-w-sm mx-auto mb-4">You have not created or shared any question banks yet. You can refresh, write questions manually, or import an Excel/CSV file.</p>
        <div class="flex items-center justify-center gap-3">
          <button @click="fetchBanks" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[12px] font-bold rounded-xl transition-colors">
            ↻ Refresh Banks
          </button>
          <button @click="emit('switch-to-manual')" class="px-4 py-2 bg-[#5138ed] text-white text-[12px] font-bold rounded-xl hover:bg-indigo-600 transition-colors">
            Switch to Manual Input
          </button>
          <button @click="emit('switch-to-file')" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-[12px] font-bold rounded-xl hover:bg-slate-50 transition-colors">
            Import from File
          </button>
        </div>
      </div>

      <!-- Empty State if Bank has 0 Questions -->
      <div v-else-if="!isLoadingQuestions && currentBank && bankQuestions.length === 0" class="mt-4 p-8 text-center bg-slate-50/60 rounded-xl border border-slate-100">
        <div class="w-10 h-10 bg-indigo-50 text-[#5138ed] rounded-xl flex items-center justify-center mx-auto mb-2 border border-indigo-100">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </div>
        <h5 class="text-[14px] font-bold text-slate-800 mb-1">No Questions in This Bank</h5>
        <p class="text-[12px] text-slate-500 max-w-sm mx-auto mb-3">This question bank does not have any questions yet.</p>
        <div class="flex items-center justify-center gap-2">
          <button @click="emit('switch-to-manual')" class="px-3 py-1.5 bg-[#5138ed] text-white text-[11.5px] font-bold rounded-lg hover:bg-indigo-600 transition-colors">
            Add Questions Manually
          </button>
          <button @click="emit('switch-to-file')" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-700 text-[11.5px] font-bold rounded-lg hover:bg-slate-50 transition-colors">
            Import from File
          </button>
        </div>
      </div>
    </div>

    <!-- Filter and Search Bar -->
    <div v-if="bankQuestions.length > 0" class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Search Query -->
        <div class="relative">
          <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search questions by text or keyword..."
            class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-[12.5px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:bg-white transition-colors"
          />
        </div>

        <!-- Type Filter -->
        <div>
          <select
            v-model="selectedType"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-[12.5px] font-medium text-slate-700 focus:outline-none focus:border-[#5138ed] focus:bg-white cursor-pointer"
          >
            <option>All Types</option>
            <option>MCQ</option>
            <option>True/False</option>
            <option>Fill in Blank</option>
            <option>Short Answer</option>
            <option>Matching</option>
          </select>
        </div>

        <!-- Difficulty Filter -->
        <div>
          <select
            v-model="selectedDifficulty"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-[12.5px] font-medium text-slate-700 focus:outline-none focus:border-[#5138ed] focus:bg-white cursor-pointer"
          >
            <option>All Difficulties</option>
            <option>Easy</option>
            <option>Medium</option>
            <option>Hard</option>
          </select>
        </div>

        <!-- Chapter Filter -->
        <div>
          <select
            v-model="selectedChapter"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-[12.5px] font-medium text-slate-700 focus:outline-none focus:border-[#5138ed] focus:bg-white cursor-pointer"
          >
            <option v-for="ch in availableChapters" :key="ch" :value="ch">
              {{ ch === 'All Chapters' ? 'All Chapters' : `Chapter ${ch}` }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Questions Selection Table / List Header -->
    <div v-if="bankQuestions.length > 0" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200">
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2.5 cursor-pointer select-none">
          <input
            type="checkbox"
            :checked="isAllFilteredSelected"
            @change="toggleSelectAll"
            class="w-4 h-4 rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed] cursor-pointer"
          />
          <span class="text-[13px] font-bold text-slate-700">
            Select All Filtered ({{ filteredQuestions.length }})
          </span>
        </label>
        
        <span v-if="selectedQuestionsCount > 0" class="text-[12px] font-bold text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-full">
          {{ selectedQuestionsCount }} selected • {{ selectedQuestionsMarks }} marks
        </span>
      </div>

      <!-- Add Button -->
      <button
        type="button"
        @click="addSelectedToExam"
        :disabled="selectedQuestionsCount === 0"
        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Add Selected ({{ selectedQuestionsCount }}) to Exam
      </button>
    </div>

    <!-- Loading Questions Spinner -->
    <div v-if="isLoadingQuestions" class="py-16 text-center bg-white rounded-2xl border border-slate-100">
      <div class="w-9 h-9 border-3 border-indigo-200 border-t-[#5138ed] rounded-full animate-spin mx-auto mb-3"></div>
      <p class="text-[13px] font-bold text-slate-700">Loading questions from repository...</p>
    </div>

    <!-- Questions List -->
    <div v-else-if="filteredQuestions.length > 0" class="space-y-3">
      <div
        v-for="(q, idx) in filteredQuestions"
        :key="q.id"
        @click="toggleQuestion(q.id)"
        :class="[
          selectedQuestionIds.includes(q.id) ? 'border-[#5138ed] bg-indigo-50/20 ring-1 ring-[#5138ed]' : 'border-slate-200 bg-white hover:border-slate-300',
          'p-5 rounded-2xl border transition-all cursor-pointer'
        ]"
      >
        <div class="flex items-start gap-4">
          <!-- Checkbox -->
          <div class="pt-1" @click.stop>
            <input
              type="checkbox"
              :value="q.id"
              v-model="selectedQuestionIds"
              class="w-4 h-4 rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed] cursor-pointer"
            />
          </div>

          <!-- Question Content -->
          <div class="flex-1 min-w-0">
            <!-- Badges -->
            <div class="flex flex-wrap items-center gap-2 mb-2">
              <span class="text-[11px] font-mono font-bold text-slate-400">#{{ idx + 1 }}</span>
              
              <!-- Type Badge -->
              <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full uppercase bg-indigo-50 text-indigo-700 border border-indigo-100">
                {{ getNormalizedType(q.type) }}
              </span>

              <!-- Difficulty Badge -->
              <span
                :class="[
                  (q.difficulty || '').toLowerCase() === 'easy' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' :
                  (q.difficulty || '').toLowerCase() === 'hard' ? 'bg-rose-50 text-rose-700 border-rose-100' :
                  'bg-amber-50 text-amber-700 border-amber-100',
                  'text-[10.5px] font-bold px-2 py-0.5 rounded-md border'
                ]"
              >
                {{ q.difficulty || 'Medium' }}
              </span>

              <!-- Marks -->
              <span class="text-[11px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
                {{ q.marks || 1 }} Marks
              </span>

              <!-- Chapter -->
              <span v-if="q.chapter" class="text-[11px] font-medium text-slate-500 bg-slate-50 px-2 py-0.5 rounded-md border border-slate-100">
                Ch. {{ q.chapter }}
              </span>

              <!-- Already Added Badge -->
              <span v-if="isQuestionAlreadyInExam(q)" class="text-[10.5px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md ml-auto flex items-center gap-1">
                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                Already in Exam
              </span>
            </div>

            <!-- Question Text -->
            <div class="text-[13.5px] font-medium text-slate-800 leading-relaxed mb-3" v-html="q.text"></div>

            <!-- Options Preview -->
            <div v-if="q.type === 'multiple_choice' && Array.isArray(q.options) && q.options.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
              <div
                v-for="(opt, oIdx) in q.options"
                :key="oIdx"
                :class="[
                  (String.fromCharCode(65 + Number(oIdx)) === q.correct_answer || (typeof opt === 'object' && opt.is_correct))
                    ? 'bg-emerald-50/70 border-emerald-300 text-emerald-800 font-semibold'
                    : 'bg-slate-50/80 border-slate-100 text-slate-600',
                  'px-3 py-1.5 rounded-lg border text-[12px] flex items-center gap-2'
                ]"
              >
                <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold font-mono shrink-0"
                  :class="(String.fromCharCode(65 + Number(oIdx)) === q.correct_answer) ? 'bg-emerald-200 text-emerald-800' : 'bg-slate-200 text-slate-700'">
                  {{ String.fromCharCode(65 + Number(oIdx)) }}
                </span>
                <span class="truncate">{{ typeof opt === 'string' ? opt : opt.text }}</span>
              </div>
            </div>

            <!-- True/False Preview -->
            <div v-else-if="q.type === 'true_false'" class="flex items-center gap-3 mt-2 text-[12px]">
              <span class="font-bold text-slate-500">Correct Answer:</span>
              <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 font-bold rounded-md border border-emerald-200">
                {{ q.correct_answer || 'True' }}
              </span>
            </div>

            <!-- Matching or FIB or SA preview -->
            <div v-else-if="q.correct_answer" class="text-[12px] text-slate-500 mt-2">
              <span class="font-bold text-slate-700">Answer:</span> {{ q.correct_answer }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty filtered results -->
    <div v-else-if="bankQuestions.length > 0 && filteredQuestions.length === 0" class="py-12 text-center bg-white rounded-2xl border border-slate-200">
      <p class="text-[14px] font-bold text-slate-700">No questions match your filter criteria.</p>
      <p class="text-[12px] text-slate-400 mt-1">Try resetting the search query or difficulty/type filters.</p>
    </div>

    <!-- Floating / Sticky Selection Bar when items are selected -->
    <div v-if="selectedQuestionsCount > 0" class="sticky bottom-4 z-20 bg-slate-900 text-white p-4 rounded-2xl shadow-xl flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-indigo-500/30 flex items-center justify-center text-indigo-300 font-bold text-sm">
          {{ selectedQuestionsCount }}
        </div>
        <div>
          <p class="text-[13px] font-bold leading-tight">{{ selectedQuestionsCount }} question{{ selectedQuestionsCount !== 1 ? 's' : '' }} selected</p>
          <p class="text-[11px] text-slate-400 mt-0.5">Adds +{{ selectedQuestionsMarks }} marks to the exam</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="selectedQuestionIds = []"
          class="px-3.5 py-2 text-[12px] font-semibold text-slate-300 hover:text-white transition-colors cursor-pointer"
        >
          Clear
        </button>
        <button
          type="button"
          @click="addSelectedToExam"
          class="px-5 py-2 bg-[#5138ed] hover:bg-indigo-600 text-white text-[13px] font-bold rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Add to Exam Now
        </button>
      </div>
    </div>
  </div>
</template>
