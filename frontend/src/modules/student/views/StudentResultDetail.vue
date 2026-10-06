<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { sampleAnnouncements } from '../data/mockData'
import { useStudentExamStore } from '../store/studentExamStore'
import { useStudentProfile } from '../composables/useStudentProfile'

import Header from '../components/Header.vue'
import HeroSection from '../components/HeroSection.vue'
import StudentSidebar from '../components/StudentSidebar.vue'
import ProfileModal from '../components/ProfileModal.vue'

const route = useRoute()
const router = useRouter()
const examStore = useStudentExamStore()
const { profile, fetchProfile } = useStudentProfile()

const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)
const isLoading = ref(true)
const selectedFilter = ref('all')
const expandedQuestions = ref<Record<number, boolean>>({})

// Detail data structure
const resultDetail = ref<any>({
  attempt: {},
  summary: {
    yourScore: 0,
    score: 0,
    totalMarks: 0,
    totalQuestions: 0,
    attempted: 0,
    correctAnswers: 0,
    correctPct: 0,
    wrongAnswers: 0,
    wrongPct: 0,
    partialAnswers: 0,
    passingMarks: 0,
    passingPct: 50,
  },
  scoreByType: [],
  questions: [],
})

const attemptId = computed(() => route.params.attemptId as string)

onMounted(async () => {
  await fetchProfile()
  await examStore.fetchResults()
  await loadDetail()
})

const loadDetail = async () => {
  isLoading.value = true
  try {
    const data = await examStore.fetchResultDetail(attemptId.value)
    if (data) {
      resultDetail.value = data
      // Expand all questions by default
      if (data.questions) {
        data.questions.forEach((q: any) => {
          expandedQuestions.value[q.id] = true
        })
      }
    }
  } catch (err) {
    console.error('Error loading result detail:', err)
  } finally {
    isLoading.value = false
  }
}

const toggleQuestion = (id: number) => {
  expandedQuestions.value[id] = !expandedQuestions.value[id]
}

// Filter calculations
const filterCounts = computed(() => {
  const list = resultDetail.value.questions || []
  return {
    all: list.length,
    multiple_choice: list.filter((q: any) => q.type === 'multiple_choice').length,
    true_false: list.filter((q: any) => q.type === 'true_false').length,
    fill_blank: list.filter((q: any) => q.type === 'fill_blank').length,
    short_answer: list.filter((q: any) => q.type === 'short_answer').length,
    matching: list.filter((q: any) => q.type === 'matching').length,
  }
})

const filteredQuestions = computed(() => {
  const list = resultDetail.value.questions || []
  if (selectedFilter.value === 'all') return list
  return list.filter((q: any) => q.type === selectedFilter.value)
})

const groupedFilteredQuestions = computed(() => {
  const list = filteredQuestions.value
  const groups: { instruction: string; questions: any[] }[] = []

  list.forEach((q: any) => {
    const inst = (q.instruction || '').trim()
    let group = groups.find(g => g.instruction === inst)
    if (!group) {
      group = { instruction: inst, questions: [] }
      groups.push(group)
    }
    group.questions.push(q)
  })

  return groups
})

const filterLabels: Record<string, string> = {
  all: 'All Questions',
  multiple_choice: 'Multiple Choice',
  true_false: 'True / False',
  fill_blank: 'Fill in the Blank',
  short_answer: 'Short Answer',
  matching: 'Matching',
}

const getTypeIconBg = (type: string) => {
  switch (type) {
    case 'multiple_choice': return 'bg-purple-50 text-purple-600 border-purple-100'
    case 'true_false':      return 'bg-blue-50 text-blue-600 border-blue-100'
    case 'fill_blank':      return 'bg-emerald-50 text-emerald-600 border-emerald-100'
    case 'short_answer':    return 'bg-amber-50 text-amber-600 border-amber-100'
    case 'matching':        return 'bg-rose-50 text-rose-600 border-rose-100'
    default:                return 'bg-indigo-50 text-indigo-600 border-indigo-100'
  }
}

const getTypeBadgeColor = (type: string) => {
  switch (type) {
    case 'multiple_choice': return 'bg-purple-50 text-purple-700 border-purple-200'
    case 'true_false':      return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'fill_blank':      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'short_answer':    return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'matching':        return 'bg-rose-50 text-rose-700 border-rose-200'
    default:                return 'bg-indigo-50 text-indigo-700 border-indigo-200'
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#f8f9fc] font-sans text-slate-800 antialiased flex flex-col">

    <!-- Top Navigation Header -->
    <Header
      :profile="profile"
      :announcements="sampleAnnouncements"
      @open-profile="isProfileOpen = true"
      @open-notifications="() => {}"
      @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
    />

    <!-- Off-Canvas Sidebar Drawer -->
    <StudentSidebar
      :profile="profile"
      :isOpen="isSidebarOpen"
      @close="isSidebarOpen = false"
    />

    <!-- Profile Modal -->
    <ProfileModal
      v-if="isProfileOpen"
      :profile="profile"
      @close="isProfileOpen = false"
      @update-profile="(updated) => profile = updated"
    />

    <!-- Row 1: Full-Width Hero Banner (Flush under header) -->
    <HeroSection
      :profile="profile"
      :stats="{ examsCompleted: examStore.results.length, upcomingExams: examStore.upcomingExams.length }"
    />

    <!-- Main Content Container -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 animate-in fade-in duration-300">

      <!-- Back Link -->
      <div>
        <router-link
          to="/student/results"
          class="inline-flex items-center gap-1.5 text-xs font-bold text-[#5138ed] hover:underline min-h-[44px] py-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
          <span>Back to Results</span>
        </router-link>
      </div>

      <!-- Title & Academic Term Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
        <div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Exam Result Details</h1>
          <p class="text-xs text-slate-500 font-medium mt-1">Here is the detailed breakdown of your answers and scores.</p>
        </div>

        <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 shadow-xs self-start sm:self-auto min-h-[44px]">
          <span>2025/2026 – Second Semester</span>
          <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="bg-white rounded-3xl border border-slate-100 shadow-sm p-12 sm:p-16 text-center">
        <div class="w-10 h-10 border-4 border-indigo-200 border-t-[#5138ed] rounded-full animate-spin mx-auto mb-4"></div>
        <h3 class="text-sm font-bold text-slate-800">Loading Exam Result Details...</h3>
        <p class="text-xs text-slate-400 mt-1">Please wait while we prepare your academic score breakdown.</p>
      </div>

      <div v-else class="space-y-6 sm:space-y-8">

        <!-- 7 Top Stat Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5 sm:gap-4">
          
          <!-- Card 1: Auto Score -->
          <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-xs flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center border border-cyan-100 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
              </div>
              <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider leading-tight">Auto Score</span>
            </div>
            <div>
              <span class="text-xl font-black text-cyan-600 tracking-tight block truncate">
                {{ resultDetail.summary.autoScore }} <span class="text-sm font-bold text-slate-400">/ {{ resultDetail.summary.autoScoreTotal }}</span>
              </span>
              <span class="text-[10px] font-semibold text-slate-500 mt-1 block">Marks</span>
            </div>
          </div>

          <!-- Card 2: Manual Score -->
          <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-xs flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center border border-orange-100 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </div>
              <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider leading-tight">Manual Score</span>
            </div>
            <div>
              <span class="text-xl font-black text-orange-500 tracking-tight block truncate">
                <template v-if="resultDetail.attempt.db_status !== 'graded' && resultDetail.summary.manualScoreTotal > 0">
                  <span class="text-sm">Pending</span>
                </template>
                <template v-else>
                  {{ resultDetail.summary.manualScore }}
                </template>
                <span class="text-sm font-bold text-slate-400">/ {{ resultDetail.summary.manualScoreTotal }}</span>
              </span>
              <span class="text-[10px] font-semibold text-slate-500 mt-1 block">Marks</span>
            </div>
          </div>

          <!-- Card 3: Your Score -->
          <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-xs flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center border border-purple-100 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider leading-tight">Your Score</span>
            </div>
            <div>
              <span class="text-xl font-black text-[#5138ed] tracking-tight block truncate">
                {{ resultDetail.summary.score }} <span class="text-sm font-bold text-slate-400">/ {{ resultDetail.summary.totalMarks }}</span>
              </span>
              <span class="text-[10px] font-semibold text-slate-500 mt-1 block truncate">
                Marks
              </span>
            </div>
          </div>

          <!-- Card 4: Total Questions -->
          <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-xs flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
              </div>
              <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider leading-tight">Questions</span>
            </div>
            <div>
              <span class="text-xl font-black text-emerald-600 tracking-tight block">
                {{ resultDetail.summary.totalQuestions }}
              </span>
              <span class="text-[10px] font-semibold text-slate-500 mt-1 block truncate">
                Atmpt: {{ resultDetail.summary.attempted }}
              </span>
            </div>
          </div>

          <!-- Card 5: Correct Answers -->
          <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-xs flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
              </div>
              <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider leading-tight">Correct</span>
            </div>
            <div>
              <span class="text-xl font-black text-blue-600 tracking-tight block">
                {{ resultDetail.summary.correctAnswers }}
              </span>
              <span class="text-[10px] font-semibold text-slate-500 mt-1 block">
                {{ resultDetail.summary.correctPct }}%
              </span>
            </div>
          </div>

          <!-- Card 6: Wrong Answers -->
          <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-xs flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </div>
              <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider leading-tight">Missed</span>
            </div>
            <div>
              <span class="text-xl font-black text-amber-500 tracking-tight block">
                {{ resultDetail.summary.wrongAnswers }}
              </span>
              <span class="text-[10px] font-semibold text-slate-500 mt-1 block">
                {{ resultDetail.summary.wrongPct }}%
              </span>
            </div>
          </div>

          <!-- Card 7: Total Marks -->
          <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-xs flex flex-col justify-between">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center border border-rose-100 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
              </div>
              <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider leading-tight">Total Marks</span>
            </div>
            <div>
              <span class="text-xl font-black text-rose-500 tracking-tight block">
                {{ resultDetail.summary.totalMarks }}
              </span>
              <span class="text-[10px] font-semibold text-slate-500 mt-1 block truncate">
                Pass: {{ resultDetail.summary.passingMarks }}
              </span>
            </div>
          </div>

        </div>

        <!-- Score by Question Type Section -->
        <div class="space-y-3">
          <h2 class="text-sm font-bold text-slate-900">Score by Question Type</h2>

          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            
            <div
              v-for="typeItem in resultDetail.scoreByType"
              :key="typeItem.key"
              class="bg-white rounded-2xl border border-slate-100 p-4 shadow-xs flex flex-col justify-between"
            >
              <!-- Type Top Header -->
              <div class="flex items-center gap-2 mb-3">
                <div :class="['w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold border', getTypeIconBg(typeItem.key)]">
                  <svg v-if="typeItem.key === 'multiple_choice'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                  <svg v-else-if="typeItem.key === 'true_false'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <svg v-else-if="typeItem.key === 'fill_blank'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  <svg v-else-if="typeItem.key === 'short_answer'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                  <svg v-else-if="typeItem.key === 'matching'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                  <span v-else>#</span>
                </div>
                <span class="text-xs font-bold text-slate-800 truncate">{{ typeItem.label }}</span>
              </div>

              <!-- Main Counts & Pill -->
              <div class="flex items-center justify-between mt-1">
                <span class="text-sm font-black text-slate-800">
                  {{ typeItem.earned }} / {{ typeItem.total }}
                </span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-600 border border-emerald-100">
                  {{ typeItem.percentage }}%
                </span>
              </div>

              <!-- Marks -->
              <span class="text-[11px] font-semibold text-slate-400 mt-2 block">
                Marks
              </span>
            </div>

          </div>

          <!-- Total Marks Footer Bar -->
          <div class="flex items-center justify-center gap-2 py-3 bg-purple-50/70 border border-purple-100/80 rounded-2xl text-[#5138ed] font-bold text-xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            <span>Total Marks: {{ resultDetail.summary.score }} / {{ resultDetail.summary.totalMarks }}</span>
          </div>
        </div>

        <!-- Legend Indicator Bar -->
        <div class="flex flex-wrap items-center gap-6 py-2 px-3 text-xs font-bold text-slate-600">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
            <span>Correct Answer</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-rose-500 ring-4 ring-rose-100"></span>
            <span>Wrong Answer</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 ring-4 ring-blue-100"></span>
            <span>Partially Correct</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-slate-400 ring-4 ring-slate-100"></span>
            <span>Unanswered</span>
          </div>
        </div>

        <!-- Horizontal Filter by Type Bar -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-3 sm:px-4 sm:py-3 flex flex-wrap items-center gap-2">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mr-2 shrink-0">Filter by Type:</span>
          <button
            v-for="(count, typeKey) in filterCounts"
            :key="typeKey"
            @click="selectedFilter = typeKey"
            :class="[
              'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all border min-h-[38px] sm:min-h-[40px]',
              selectedFilter === typeKey
                ? 'bg-[#5138ed] text-white border-[#5138ed] shadow-sm'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-indigo-200 hover:text-[#5138ed] hover:bg-indigo-50'
            ]"
          >
            <span>{{ filterLabels[typeKey] || typeKey }}</span>
            <span
              :class="[
                'px-1.5 py-0.5 rounded-full text-[9px] font-black',
                selectedFilter === typeKey ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-500'
              ]"
            >
              {{ count }}
            </span>
          </button>
        </div>

        <!-- Full-Width Question Cards Section -->
        <div class="space-y-4">

          <!-- List Header -->
          <div class="flex items-center justify-between px-1">
            <h3 class="text-sm font-extrabold text-slate-800">
              {{ filterLabels[selectedFilter] || 'All Questions' }}
            </h3>
            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 px-2.5 py-1 rounded-full">
              {{ filteredQuestions.length }} Questions
            </span>
          </div>

          <!-- Empty Filter State -->
          <div v-if="filteredQuestions.length === 0" class="bg-white rounded-3xl border border-slate-100 p-12 text-center text-slate-400 shadow-xs">
            <p class="text-xs font-bold">No questions found under this filter category.</p>
          </div>

          <!-- Question Groups by Instruction -->
          <div v-else class="space-y-6">
            <template v-for="(group, gIdx) in groupedFilteredQuestions" :key="gIdx">
              
              <!-- Instruction Group Header Divider -->
              <div class="flex items-center gap-3 my-4 first:mt-0">
                <div class="flex-1 h-px bg-slate-200/80"></div>
                <div
                  v-if="group.instruction"
                  class="flex items-center gap-2 px-4 py-1.5 bg-indigo-50 border border-indigo-100/80 rounded-full shrink-0 shadow-xs max-w-full truncate"
                >
                  <svg class="w-3.5 h-3.5 text-[#5138ed] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  <span class="text-[11px] font-bold text-[#5138ed] italic truncate">{{ group.instruction }}</span>
                </div>
                <div
                  v-else
                  class="flex items-center gap-2 px-4 py-1.5 bg-slate-50 border border-slate-200 rounded-full shrink-0"
                >
                  <span class="text-[11px] font-medium text-slate-400 italic">General Questions</span>
                </div>
                <div class="flex-1 h-px bg-slate-200/80"></div>
              </div>

              <!-- Questions within this Instruction Group -->
              <div class="space-y-4">
                <div
                  v-for="q in group.questions"
                  :key="q.id"
                  class="bg-white rounded-3xl border border-slate-100 shadow-xs p-4 sm:p-6 transition-all space-y-4 sm:space-y-5"
                >
                    <!-- Card Top Row -->
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 sm:gap-4">
                      
                      <!-- Left: Question Number, Type Badge & Statement -->
                      <div class="flex items-start gap-3 sm:gap-4 flex-1 min-w-0">
                        <!-- Number Circle -->
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border border-indigo-200 text-[#5138ed] bg-indigo-50/40 flex items-center justify-center text-xs font-black shrink-0 mt-0.5">
                          {{ q.number }}
                        </div>

                        <div class="space-y-2 flex-1 min-w-0">
                          <!-- Type Badge -->
                          <span :class="['inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border', getTypeBadgeColor(q.type)]">
                            {{ q.typeLabel || q.type }}
                          </span>

                          <!-- Question Prompt / Text -->
                          <p class="text-xs sm:text-sm font-bold text-slate-800 leading-relaxed break-words">
                            {{ q.text }}
                          </p>

                          <!-- Multiple Choice Option Choices (If MCQ) -->
                          <div v-if="q.type === 'multiple_choice' && q.options && q.options.length > 0" class="space-y-1.5 pt-1 pl-1">
                            <div
                              v-for="opt in q.options"
                              :key="opt.label"
                              class="text-xs font-semibold text-slate-600 flex items-start gap-2 break-words"
                            >
                              <span class="text-slate-400 font-bold shrink-0">{{ opt.label }}.</span>
                              <span>{{ opt.text }}</span>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Right: Marks & Outcome Status Icon & Chevron -->
                      <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                        <span class="text-xs sm:text-sm font-black text-slate-800">
                          {{ q.earnedMarks !== null ? q.earnedMarks : 'Pending' }} <template v-if="q.earnedMarks !== null">/</template><template v-else> of</template> {{ q.marks }}
                        </span>

                        <div class="flex items-center gap-1.5 sm:gap-2">
                          <!-- Status Icon Circle -->
                          <div
                            v-if="q.status === 'correct'"
                            class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-xs"
                            title="Correct"
                          >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                          </div>

                          <div
                            v-else-if="q.status === 'partial'"
                            class="w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center shadow-xs"
                            title="Partially Correct"
                          >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"/>
                            </svg>
                          </div>

                          <div
                            v-else-if="q.status === 'pending'"
                            class="w-6 h-6 rounded-full bg-indigo-400 text-white flex items-center justify-center shadow-xs"
                            title="Pending Instructor Review"
                          >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                          </div>

                          <div
                            v-else
                            class="w-6 h-6 rounded-full bg-rose-500 text-white flex items-center justify-center shadow-xs"
                            title="Incorrect"
                          >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                          </div>

                          <!-- Expand/Collapse Chevron -->
                          <button
                            @click="toggleQuestion(q.id)"
                            class="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center transition-colors"
                            :aria-label="expandedQuestions[q.id] ? 'Collapse question' : 'Expand question'"
                          >
                            <svg
                              :class="['w-4 h-4 transition-transform', expandedQuestions[q.id] ? 'rotate-180' : '']"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                          </svg>
                        </button>
                      </div>
                    </div>
                  </div><!-- end card top row -->

                  <!-- Collapsible Content -->
                  <div v-show="expandedQuestions[q.id]" class="pt-2 border-t border-slate-50 space-y-4">
                      
                      <!-- 1. MULTIPLE CHOICE / TRUE-FALSE / FILL IN THE BLANK -->
                      <div
                        v-if="q.type === 'multiple_choice' || q.type === 'true_false' || q.type === 'fill_blank'"
                        class="grid grid-cols-1 sm:grid-cols-2 gap-4"
                      >
                        <!-- Your Answer Box -->
                        <div class="space-y-1.5">
                          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Your Answer</span>
                          <div class="p-3.5 bg-slate-50/70 border border-slate-200/80 rounded-2xl flex items-center justify-between">
                            <span :class="['text-xs font-bold', q.status === 'correct' ? 'text-emerald-700' : 'text-rose-600']">
                              {{ q.studentAnswer }}
                            </span>
                            <span v-if="q.status === 'correct'" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600">
                              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                              <span>Correct</span>
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-600">
                              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                              <span>Incorrect</span>
                            </span>
                          </div>
                        </div>

                        <!-- Correct Answer Box -->
                        <div class="space-y-1.5">
                          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Correct Answer</span>
                          <div class="p-3.5 bg-emerald-50/40 border border-emerald-200/60 rounded-2xl flex items-center justify-between">
                            <span class="text-xs font-bold text-emerald-800">
                              {{ q.correctAnswer }}
                            </span>
                          </div>
                        </div>
                      </div>

                      <!-- 2. SHORT ANSWER / ESSAY -->
                      <div v-else-if="q.type === 'short_answer'" class="space-y-4">
                        <!-- Student Answer Text Box -->
                        <div class="space-y-1.5">
                          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Your Answer</span>
                          <div class="p-4 bg-slate-50/70 border border-slate-200/80 rounded-2xl text-xs text-slate-700 font-medium leading-relaxed">
                            {{ q.studentAnswer }}
                          </div>
                        </div>

                        <!-- Correct / Sample Answer Text Box -->
                        <div class="space-y-1.5">
                          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Correct Answer</span>
                          <div class="p-4 bg-emerald-50/30 border border-emerald-200/60 rounded-2xl text-xs text-slate-700 font-medium leading-relaxed">
                            {{ q.correctAnswer }}
                          </div>
                        </div>
                      </div>

                      <!-- 3. MATCHING QUESTIONS -->
                      <div v-else-if="q.type === 'matching'" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                          
                          <!-- Left: Your Matched Answers -->
                          <div class="space-y-2">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Your Answer</span>
                            <div class="p-4 bg-slate-50/70 border border-slate-200/80 rounded-2xl space-y-2.5">
                              <div
                                v-for="pair in (q.matchingPairs || [])"
                                :key="pair.index"
                                class="flex items-center justify-between gap-3 text-xs"
                              >
                                <span class="font-bold text-slate-700">{{ pair.index }}. {{ pair.left }}</span>
                                <span class="text-slate-400">➔</span>
                                <span :class="['font-bold', pair.isCorrect ? 'text-emerald-700' : 'text-rose-600']">
                                  {{ pair.studentRight }}
                                </span>
                              </div>
                            </div>
                          </div>

                          <!-- Right: Correct Matches -->
                          <div class="space-y-2">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Correct Answer</span>
                            <div class="p-4 bg-emerald-50/40 border border-emerald-200/60 rounded-2xl space-y-2.5">
                              <div
                                v-for="pair in (q.matchingPairs || [])"
                                :key="'corr-' + pair.index"
                                class="flex items-center justify-between gap-3 text-xs"
                              >
                                <span class="font-bold text-slate-700">{{ pair.index }}. {{ pair.left }}</span>
                                <span class="text-emerald-500">➔</span>
                                <span class="font-bold text-emerald-800">
                                  {{ pair.correctRight }}
                                </span>
                              </div>
                            </div>
                          </div>

                        </div>
                      </div>

                      <!-- Explanation (if available) -->
                      <div v-if="q.explanation" class="p-3.5 bg-indigo-50/40 border border-indigo-100 rounded-2xl text-xs text-indigo-900 leading-relaxed flex items-start gap-2">
                        <svg class="w-4 h-4 text-indigo-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                          <span class="font-bold block mb-0.5">Explanation:</span>
                          <span>{{ q.explanation }}</span>
                        </div>
                      </div>

                    </div>

                  </div><!-- end v-for card -->
                </div><!-- end space-y-4 -->

            </template>
          </div><!-- end v-else groups -->

        </div><!-- end full-width questions section -->

      </div><!-- end v-else main content -->

    </main>

  </div>
</template>
