<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'

const route = useRoute()
const router = useRouter()

const bankId = route.params.id as string
const bank = ref<any>(null)
const questions = ref<any[]>([])
const isLoading = ref(true)

// Read filter params from query
const filterType = ref((route.query.type as string) || 'All Types')
const filterDifficulty = ref((route.query.difficulty as string) || 'All Difficulties')
const filterStatus = ref((route.query.status as string) || 'All Statuses')
const filterChapter = ref((route.query.chapter as string) || 'All Chapters')
const filterSearch = ref((route.query.search as string) || '')

const typeMap: Record<string, string> = {
  'MCQ': 'multiple_choice',
  'Short Answer': 'short_answer',
  'True/False': 'true_false',
  'Matching': 'matching',
  'Fill in Blank': 'fill_in_blank',
}

const typeDisplayMap: Record<string, string> = {
  multiple_choice: 'Multiple Choice',
  short_answer: 'Short Answer',
  true_false: 'True/False',
  matching: 'Matching',
  fill_in_blank: 'Fill in the Blank',
}

// Filter questions based on query params
const filteredQuestions = computed(() => {
  let result = questions.value

  if (filterType.value !== 'All Types') {
    const backendType = typeMap[filterType.value]
    if (backendType) result = result.filter(q => q.type === backendType)
  }

  if (filterDifficulty.value !== 'All Difficulties') {
    result = result.filter(q => q.difficulty === filterDifficulty.value)
  }

  if (filterStatus.value !== 'All Statuses') {
    result = result.filter(q => q.status === filterStatus.value)
  }

  if (filterChapter.value !== 'All Chapters') {
    result = result.filter(q => q.chapter === filterChapter.value)
  }

  if (filterSearch.value.trim()) {
    const s = filterSearch.value.toLowerCase()
    result = result.filter(item => item.text?.toLowerCase().includes(s) || item.topic?.toLowerCase().includes(s) || item.chapter?.toLowerCase().includes(s))
  }

  return result
})

// Group filtered questions by type
const groupedByType = computed(() => {
  const groups: Record<string, any[]> = {}
  filteredQuestions.value.forEach(q => {
    const label = typeDisplayMap[q.type] || q.type
    if (!groups[label]) groups[label] = []
    groups[label].push(q)
  })
  // Return as array of { type, questions }
  const order = ['Multiple Choice', 'True/False', 'Matching', 'Fill in the Blank', 'Short Answer']
  const result: { type: string; questions: any[] }[] = []
  order.forEach(t => {
    if (groups[t]) result.push({ type: t, questions: groups[t] })
  })
  // Add any remaining types not in order
  Object.keys(groups).forEach(t => {
    if (!order.includes(t)) result.push({ type: t, questions: groups[t] })
  })
  return result
})

// Active filter description
const activeFilters = computed(() => {
  const parts: string[] = []
  if (filterType.value !== 'All Types') parts.push(filterType.value)
  if (filterDifficulty.value !== 'All Difficulties') parts.push(filterDifficulty.value)
  if (filterStatus.value !== 'All Statuses') parts.push(filterStatus.value)
  if (filterChapter.value !== 'All Chapters') parts.push(filterChapter.value)
  if (filterSearch.value.trim()) parts.push(`"${filterSearch.value}"`)
  return parts
})

onMounted(async () => {
  try {
    const response = await apiClient.get(`/instructor/question-banks/${bankId}`)
    const data = response.data.data
    bank.value = data.bank
    questions.value = data.questions
  } catch (err) {
    console.error('Failed to load questions', err)
  } finally {
    isLoading.value = false
  }
})

const getDifficultyClass = (diff: string) => {
  switch (diff?.toLowerCase()) {
    case 'easy': return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'hard': return 'bg-rose-50 text-rose-700 border-rose-200'
    default: return 'bg-amber-50 text-amber-700 border-amber-200'
  }
}

const getLetterLabel = (index: number) => {
  return String.fromCharCode(65 + index)
}

const stripHtml = (html: string) => {
  if (!html) return ''
  const tempDiv = document.createElement('div')
  tempDiv.innerHTML = html
  return (tempDiv.textContent || tempDiv.innerText || '').trim()
}

const goBack = () => {
  router.push(`/instructor/question-banks/${bankId}`)
}


</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] p-3.5 sm:p-6 lg:p-10 font-sans pb-28">

    <!-- Header & Breadcrumbs -->
    <div class="max-w-5xl mx-auto mb-6 sm:mb-8">
      <div class="flex items-center gap-1.5 sm:gap-2 text-xs font-semibold text-slate-400 mb-3 flex-wrap">
        <router-link to="/instructor/question-banks" class="hover:text-slate-600 transition-colors">Question Banks</router-link>
        <span>/</span>
        <router-link :to="`/instructor/question-banks/${bankId}`" class="hover:text-slate-600 transition-colors truncate max-w-[140px] sm:max-w-none">{{ bank?.title || 'Question Bank' }}</router-link>
        <span>/</span>
        <span class="text-[#5138ed]">Filtered Questions</span>
      </div>

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
        <div>
          <div class="flex items-center gap-2.5 sm:gap-3 flex-wrap">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Filtered Questions</h1>
            <span class="px-2.5 py-0.5 sm:py-1 bg-indigo-50 border border-indigo-200 text-[#5138ed] text-[11px] sm:text-xs font-bold rounded-md uppercase tracking-wider">
              {{ filteredQuestions.length }} Questions
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Viewing questions based on your selected filters.</p>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
          <button @click="goBack" class="w-full sm:w-auto px-4 sm:px-5 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl hover:bg-indigo-600 transition-colors shadow-md shadow-indigo-200 flex items-center justify-center gap-2 min-h-[44px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Bank
          </button>
        </div>
      </div>

      <!-- Active Filters Display -->
      <div v-if="activeFilters.length > 0" class="flex items-center gap-1.5 sm:gap-2 mt-4 flex-wrap">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Filters:</span>
        <span v-for="(f, i) in activeFilters" :key="i" class="px-2.5 py-0.5 bg-indigo-50 border border-indigo-100 text-[#5138ed] text-[10px] font-bold rounded-full uppercase tracking-wider">
          {{ f }}
        </span>
      </div>
    </div>

    <!-- Questions Container -->
    <div class="max-w-5xl mx-auto">
      <div v-if="isLoading" class="p-12 text-center bg-white rounded-2xl border border-slate-100">
        <svg class="animate-spin w-8 h-8 text-[#5138ed] mx-auto mb-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <p class="text-xs text-slate-500 font-medium">Loading questions...</p>
      </div>

      <div v-else-if="filteredQuestions.length === 0" class="p-16 text-center bg-white rounded-2xl border border-slate-100 shadow-sm">
        <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </div>
        <h3 class="text-base font-bold text-slate-800 mb-1">No Questions Found</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mb-6">No questions match your current filter criteria. Try adjusting your filters.</p>
        <button @click="goBack" class="px-5 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl hover:bg-indigo-600 transition-colors">
          Back to Question Bank
        </button>
      </div>

      <div v-else class="space-y-12">
        <!-- Iterate over Question Type Groups -->
        <div v-for="group in groupedByType" :key="group.type">
          
          <!-- TYPE HEADER -->
          <div class="mb-6 border-b-2 border-slate-200 pb-3 flex items-center justify-between">
            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
              <span class="w-2 h-6 bg-[#5138ed] rounded-full inline-block"></span>
              {{ group.type }} ({{ group.questions.length }} Questions)
            </h2>
          </div>

          <!-- Questions Cards -->
          <div class="space-y-4 pl-0 sm:pl-6 border-l-[3px] border-slate-100 ml-1">
            <div 
              v-for="(q, qIdx) in group.questions" 
              :key="q.id" 
              class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:border-indigo-200 hover:shadow-md transition-all relative group"
            >
              <!-- Question Metadata Bar -->
              <div class="flex flex-wrap items-center justify-between gap-3 mb-5 border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                  <!-- Question Number -->
                  <span class="w-8 h-8 rounded-full bg-indigo-50 text-[#5138ed] flex items-center justify-center text-[13px] font-black border border-indigo-100">
                    {{ qIdx + 1 }}
                  </span>
                  <span :class="getDifficultyClass(q.difficulty)" class="px-2.5 py-1 text-[10px] uppercase font-black rounded-md border tracking-widest">
                    {{ q.difficulty || 'Medium' }}
                  </span>
                  <span class="px-2.5 py-1 bg-slate-50 border border-slate-200 text-slate-600 text-[10px] font-black uppercase rounded-md tracking-widest">
                    {{ q.marks || 1 }} Mark{{ (q.marks || 1) > 1 ? 's' : '' }}
                  </span>
                  <span v-if="q.chapter" class="px-2.5 py-1 bg-slate-100 text-slate-500 text-[10px] font-bold uppercase rounded-md tracking-widest">
                    {{ q.chapter }}
                  </span>
                </div>

                <span class="px-2.5 py-1 text-[10px] font-black uppercase rounded-md tracking-widest"
                  :class="q.status === 'Active' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-amber-50 text-amber-600 border border-amber-200'">
                  {{ q.status || 'Active' }}
                </span>
              </div>

              <!-- QUESTION CONTENT RENDERERS -->
              <div class="text-sm text-slate-800">
                
                <!-- 1. MULTIPLE CHOICE / TRUE-FALSE -->
                <template v-if="q.type === 'multiple_choice' || q.type === 'true_false'">
                  <div class="font-medium mb-4 text-[15px]" v-html="q.text"></div>
                  
                  <!-- Options -->
                  <div v-if="q.options && q.options.length" class="space-y-2 mb-5">
                    <div 
                      v-for="(opt, oIdx) in q.options" 
                      :key="oIdx"
                      class="flex items-start gap-3 p-2 rounded-lg"
                    >
                      <span class="w-6 h-6 shrink-0 rounded-md bg-slate-100 text-slate-600 text-[11px] font-bold flex items-center justify-center border border-slate-200 mt-0.5">
                        {{ getLetterLabel(Number(oIdx)) }}
                      </span>
                      <span class="text-[14px] leading-relaxed pt-0.5" v-html="typeof opt === 'string' ? opt : opt.text || opt"></span>
                    </div>
                  </div>
                  
                  <!-- Correct Answer Box -->
                  <div class="bg-emerald-50/50 border border-emerald-100 p-3 rounded-xl flex items-center gap-2 text-[12px]">
                    <span class="font-bold text-emerald-700 uppercase tracking-wider text-[10px]">Answer:</span>
                    <span class="font-bold text-slate-800">{{ q.correct_answer || 'N/A' }}</span>
                  </div>
                </template>

                <!-- 2. SHORT ANSWER -->
                <template v-else-if="q.type === 'short_answer'">
                  <div class="font-medium mb-4 text-[15px]" v-html="q.text"></div>

                  <div class="bg-slate-50 border border-slate-200 border-dashed p-3 rounded-xl mb-4 text-slate-400 text-xs text-center italic">
                    [ Student's Answer Field ]
                  </div>

                  <div class="bg-emerald-50/50 border border-emerald-100 p-3 rounded-xl flex items-center gap-2 text-[12px]">
                    <span class="font-bold text-emerald-700 uppercase tracking-wider text-[10px]">Expected Answer:</span>
                    <span class="font-bold text-slate-800">{{ q.question_data?.expected_answer || q.correct_answer || 'N/A' }}</span>
                  </div>
                </template>

                <!-- 3. FILL IN THE BLANK -->
                <template v-else-if="q.type === 'fill_in_blank'">
                  <div class="font-medium mb-4 text-[15px]" v-html="q.text"></div>

                  <div class="bg-slate-50 border border-slate-200 border-dashed p-3 rounded-xl mb-4 text-slate-400 text-xs text-center italic">
                    [ Fill in the Blank ]
                  </div>

                  <div class="bg-emerald-50/50 border border-emerald-100 p-3 rounded-xl flex items-center gap-2 text-[12px]">
                    <span class="font-bold text-emerald-700 uppercase tracking-wider text-[10px]">Acceptable Answers:</span>
                    <span class="font-bold text-slate-800">{{ (q.question_data?.answers?.length ? q.question_data.answers.join(', ') : null) || q.correct_answer || 'N/A' }}</span>
                  </div>
                </template>

                <!-- 4. MATCHING -->
                <template v-else-if="q.type === 'matching'">
                  <div v-if="q.text" class="font-medium mb-4 text-[15px]" v-html="q.text"></div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                    <!-- Column A -->
                    <div>
                      <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-3 pb-2 border-b border-slate-100">Column A</h4>
                      <div class="space-y-3">
                        <div v-for="(colA, cIdx) in (q.question_data?.column_a || [])" :key="cIdx" class="flex gap-3">
                          <span class="font-bold text-slate-400 text-[13px] w-4 shrink-0 text-right">{{ Number(cIdx) + 1 }}.</span>
                          <div class="text-[13px] bg-slate-50 border border-slate-200 rounded-lg p-2.5 w-full flex-1 leading-relaxed" v-html="colA"></div>
                        </div>
                      </div>
                    </div>

                    <!-- Column B -->
                    <div>
                      <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-3 pb-2 border-b border-slate-100">Column B</h4>
                      <div class="space-y-3">
                        <div v-for="(colB, cIdx) in (q.question_data?.column_b || [])" :key="cIdx" class="flex gap-3 items-center">
                          <span class="w-6 h-6 rounded-md bg-indigo-50 text-[#5138ed] text-[11px] font-bold flex items-center justify-center border border-indigo-100 shrink-0">
                            {{ colB.label || getLetterLabel(Number(cIdx)) }}
                          </span>
                          <div class="text-[13px] flex-1 text-slate-700 leading-relaxed" v-html="colB.text || colB"></div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Correct Matches -->
                  <div class="bg-emerald-50/50 border border-emerald-100 p-4 rounded-xl text-[12px]">
                    <span class="font-bold text-emerald-700 uppercase tracking-wider text-[10px] block mb-3">Correct Matches:</span>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                      <div v-for="(ans, key) in (q.question_data?.correct_answers || {})" :key="key" class="flex items-center gap-2 font-bold text-slate-800 bg-white border border-emerald-100 px-3 py-1.5 rounded-lg w-fit">
                        <span>{{ key }}</span>
                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        <span>{{ ans }}</span>
                      </div>
                    </div>
                  </div>
                </template>

                <!-- Fallback -->
                <template v-else>
                  <div class="font-medium mb-4 text-[15px]" v-html="q.text"></div>
                  <div v-if="q.correct_answer" class="bg-emerald-50/50 border border-emerald-100 p-3 rounded-xl flex items-center gap-2 text-[12px]">
                    <span class="font-bold text-emerald-700 uppercase tracking-wider text-[10px]">Answer:</span>
                    <span class="font-bold text-slate-800">{{ q.correct_answer }}</span>
                  </div>
                </template>

              </div>

            </div>
          </div>
          
        </div>
      </div>
      
    </div>
  </div>
</template>
