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
import DownloadTranscriptModal from '../components/DownloadTranscriptModal.vue'

import {
  ArrowLeft,
  Printer,
  Download,
  BookOpen,
  Award,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Calendar,
  ChevronDown,
  ChevronUp,
  Clock,
  FileText,
  Check,
  X,
  Zap,
  FileEdit,
  ListChecks,
  Target,
  Sparkles,
  HelpCircle,
  Maximize2,
  Minimize2,
  Layers,
  GraduationCap,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const examStore = useStudentExamStore()
const { profile, fetchProfile } = useStudentProfile()

const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)
const isTranscriptOpen = ref(false)
const isLoading = ref(true)

// Filters & Accordion
const selectedFilter = ref('all')
const selectedOutcomeFilter = ref('all')
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

const areAllExpanded = computed(() => {
  const qs = filteredQuestions.value || []
  if (qs.length === 0) return false
  return qs.every((q: any) => expandedQuestions.value[q.id])
})

const toggleAllQuestions = () => {
  const target = !areAllExpanded.value
  const qs = resultDetail.value.questions || []
  qs.forEach((q: any) => {
    expandedQuestions.value[q.id] = target
  })
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

const outcomeCounts = computed(() => {
  const list = resultDetail.value.questions || []
  return {
    all: list.length,
    correct: list.filter((q: any) => q.status === 'correct').length,
    wrong: list.filter((q: any) => q.status === 'wrong').length,
    unanswered: list.filter((q: any) => q.status === 'unanswered' || q.studentAnswer === 'Not answered').length,
    partial: list.filter((q: any) => q.status === 'partial').length,
    pending: list.filter((q: any) => q.status === 'pending').length,
  }
})

const filteredQuestions = computed(() => {
  let list = resultDetail.value.questions || []
  if (selectedFilter.value !== 'all') {
    list = list.filter((q: any) => q.type === selectedFilter.value)
  }
  if (selectedOutcomeFilter.value !== 'all') {
    if (selectedOutcomeFilter.value === 'correct') {
      list = list.filter((q: any) => q.status === 'correct')
    } else if (selectedOutcomeFilter.value === 'wrong') {
      list = list.filter((q: any) => q.status === 'wrong')
    } else if (selectedOutcomeFilter.value === 'unanswered') {
      list = list.filter((q: any) => q.status === 'unanswered' || q.studentAnswer === 'Not answered')
    } else if (selectedOutcomeFilter.value === 'partial') {
      list = list.filter((q: any) => q.status === 'partial')
    } else if (selectedOutcomeFilter.value === 'pending') {
      list = list.filter((q: any) => q.status === 'pending')
    }
  }
  return list
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
    case 'multiple_choice': return 'bg-purple-50 text-purple-600 border-purple-200'
    case 'true_false':      return 'bg-blue-50 text-blue-600 border-blue-200'
    case 'fill_blank':      return 'bg-emerald-50 text-emerald-600 border-emerald-200'
    case 'short_answer':    return 'bg-amber-50 text-amber-600 border-amber-200'
    case 'matching':        return 'bg-rose-50 text-rose-600 border-rose-200'
    default:                return 'bg-[#EEF0FF] text-[#4F35F3] border-[#D6DBFF]'
  }
}

const getTypeBadgeColor = (type: string) => {
  switch (type) {
    case 'multiple_choice': return 'bg-purple-50 text-purple-700 border-purple-200'
    case 'true_false':      return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'fill_blank':      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'short_answer':    return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'matching':        return 'bg-rose-50 text-rose-700 border-rose-200'
    default:                return 'bg-[#EEF0FF] text-[#4F35F3] border-[#D6DBFF]'
  }
}

const getScoreColor = (percentage: number) => {
  const p = Number(percentage || 0)
  if (p >= 85) return 'text-emerald-600'
  if (p >= 70) return 'text-[#4F35F3]'
  if (p >= 50) return 'text-amber-600'
  return 'text-rose-600'
}

const getScoreBarColor = (percentage: number) => {
  const p = Number(percentage || 0)
  if (p >= 85) return 'bg-emerald-500'
  if (p >= 70) return 'bg-[#4F35F3]'
  if (p >= 50) return 'bg-amber-500'
  return 'bg-rose-500'
}

const getGradeBadgeClass = (grade: string) => {
  const g = (grade || '').trim().toUpperCase()
  if (g.startsWith('A')) return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (g.startsWith('B')) return 'bg-[#EEF0FF] text-[#4F35F3] border-[#D6DBFF]'
  if (g.startsWith('C') || g.startsWith('D')) return 'bg-amber-50 text-amber-700 border-amber-200'
  if (g === 'PENDING') return 'bg-purple-50 text-purple-700 border-purple-200'
  return 'bg-rose-50 text-rose-700 border-rose-200'
}

const getStatusBadge = (status: string) => {
  const s = (status || '').toLowerCase()
  if (s === 'passed') {
    return {
      dot: 'bg-emerald-500',
      pill: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      label: 'Passed Examination',
    }
  }
  if (s === 'pending') {
    return {
      dot: 'bg-amber-500 animate-pulse',
      pill: 'bg-amber-50 text-amber-700 border-amber-200',
      label: 'Pending Instructor Review',
    }
  }
  return {
    dot: 'bg-rose-500',
    pill: 'bg-rose-50 text-rose-700 border-rose-200',
    label: 'Failed Examination',
  }
}

const handlePrint = () => {
  window.print()
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F7FC] font-sans text-[#17243A] antialiased flex flex-col selection:bg-[#4F35F3] selection:text-white">

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

    <!-- Download Transcript Modal -->
    <DownloadTranscriptModal
      v-if="isTranscriptOpen"
      :profile="profile"
      :results="examStore.results"
      @close="isTranscriptOpen = false"
    />

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- PROTECTED HERO SECTION (Structure & Background Kept 100% Intact) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <HeroSection
      :profile="profile"
      :stats="{ examsCompleted: examStore.results.length, upcomingExams: examStore.upcomingExams.length }"
      subtitle="ACADEMIC PERFORMANCE"
      title="RESULT DETAILS"
      description="Detailed assessment breakdown, question-by-question scoring, and official grade review."
    />

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- MAIN CONTENT BODY (Below the Hero Section) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-5 sm:py-6 space-y-5 animate-in fade-in duration-200">

      <!-- ── Breadcrumb & Top Action Toolbar ── -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-[#E5EAF3] shadow-2xs no-print">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-xs font-semibold text-[#71819B]">
            <router-link
              to="/student/results"
              class="inline-flex items-center gap-1.5 text-[#4F35F3] font-bold hover:underline"
            >
              <ArrowLeft :size="13" class="stroke-[2.5]" />
              <span>Back to Results</span>
            </router-link>
            <span>•</span>
            <span>Assessment Breakdown</span>
            <span v-if="resultDetail.attempt.courseCode">•</span>
            <span v-if="resultDetail.attempt.courseCode" class="font-bold text-[#17243A]">{{ resultDetail.attempt.courseCode }}</span>
          </div>

          <h1 class="text-xl sm:text-2xl font-black text-[#17243A] tracking-tight flex items-center gap-2.5 flex-wrap">
            <span>{{ resultDetail.attempt.examTitle || 'Exam Result Details' }}</span>
            <span v-if="resultDetail.attempt.courseCode" class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-[#EEF0FF] text-[#4F35F3] border border-[#D6DBFF]">
              {{ resultDetail.attempt.courseCode }}
            </span>
          </h1>

          <p class="text-xs text-[#71819B]">
            Course: <strong class="text-slate-800">{{ resultDetail.attempt.courseName || resultDetail.attempt.courseCode || 'Course Assessment' }}</strong>
            <span v-if="resultDetail.attempt.completedDate"> • Completed on {{ resultDetail.attempt.completedDate }}</span>
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap shrink-0">
          <button
            @click="handlePrint"
            class="px-3.5 py-2 rounded-xl text-xs font-bold text-[#71819B] hover:text-[#17243A] bg-[#F5F7FC] hover:bg-slate-100 border border-[#E5EAF3] transition-all flex items-center gap-1.5 cursor-pointer"
            title="Print result summary"
          >
            <Printer :size="14" class="stroke-[2.5]" />
            <span>Print Slip</span>
          </button>

          <button
            @click="isTranscriptOpen = true"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#4F35F3] hover:bg-[#3D25D8] shadow-xs transition-all flex items-center gap-2 cursor-pointer"
          >
            <Download :size="14" class="stroke-[2.5]" />
            <span>Official Transcript</span>
          </button>
        </div>
      </div>

      <!-- ── Loading State ── -->
      <div v-if="isLoading" class="bg-white rounded-2xl border border-[#E5EAF3] p-12 sm:p-16 text-center space-y-4 shadow-2xs">
        <div class="w-12 h-12 rounded-2xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center mx-auto animate-spin">
          <Clock :size="24" class="stroke-[2.5]" />
        </div>
        <div>
          <h3 class="text-sm font-bold text-[#17243A]">Loading Detailed Assessment Breakdown...</h3>
          <p class="text-xs text-[#71819B] mt-1">Retrieving question answers, evaluation points, and teacher marks from the server.</p>
        </div>
      </div>

      <!-- ── Main Content When Loaded ── -->
      <div v-else class="space-y-5">

        <!-- ── Section 1: Master Academic Scorecard & KPIs ── -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

          <!-- Left Column (5 cols): Primary Outcome & Score Card -->
          <div class="lg:col-span-5 bg-white rounded-2xl border border-[#E5EAF3] p-5 sm:p-6 shadow-2xs flex flex-col justify-between relative overflow-hidden">
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-[#71819B] flex items-center gap-1.5">
                  <Sparkles :size="13" class="text-[#4F35F3]" />
                  Overall Performance
                </span>
                <!-- Outcome Pill -->
                <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold border', getStatusBadge(resultDetail.attempt.status).pill]">
                  <span :class="['w-2 h-2 rounded-full', getStatusBadge(resultDetail.attempt.status).dot]"></span>
                  <span>{{ getStatusBadge(resultDetail.attempt.status).label }}</span>
                </span>
              </div>

              <!-- Main Score Display -->
              <div class="pt-1">
                <div class="flex items-baseline gap-2.5">
                  <span :class="['text-4xl sm:text-5xl font-black font-mono tracking-tight', getScoreColor(resultDetail.summary.yourScore)]">
                    {{ Number(resultDetail.summary.yourScore || 0).toFixed(1) }}%
                  </span>
                  <div class="text-left">
                    <span class="text-xs font-bold text-[#71819B] block font-mono">
                      {{ resultDetail.summary.score }} / {{ resultDetail.summary.totalMarks }} Marks
                    </span>
                    <span class="text-[11px] text-slate-500 font-medium">Earned Score</span>
                  </div>
                </div>

                <!-- Visual Progress Bar with Passing Threshold Marker -->
                <div class="mt-4 space-y-1.5">
                  <div class="flex items-center justify-between text-[11px] font-semibold text-[#71819B]">
                    <span>Score Progress</span>
                    <span>Pass Target: {{ resultDetail.summary.passingMarks || 5 }} Marks (50%)</span>
                  </div>
                  <div class="h-2.5 w-full bg-slate-100 rounded-full overflow-hidden relative">
                    <div
                      :class="['h-full rounded-full transition-all duration-500', getScoreBarColor(resultDetail.summary.yourScore)]"
                      :style="{ width: `${Math.min(Math.max(Number(resultDetail.summary.yourScore || 0), 0), 100)}%` }"
                    ></div>
                    <!-- 50% pass threshold pin line -->
                    <div class="absolute top-0 bottom-0 left-1/2 w-0.5 bg-slate-400/80 z-10" title="50% Passing Line"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Bottom Grade & Assessment Details -->
            <div class="mt-5 pt-4 border-t border-[#F0F3F8] flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-xs text-[#71819B] font-medium">Grade Awarded:</span>
                <span :class="['px-3 py-1 rounded-xl border text-sm font-black font-mono shadow-2xs', getGradeBadgeClass(resultDetail.attempt.grade)]">
                  {{ resultDetail.attempt.grade || 'N/A' }}
                </span>
              </div>
              <div class="text-right text-[11px] text-[#71819B]">
                <span class="font-medium">Total Questions:</span> <strong class="text-[#17243A]">{{ resultDetail.summary.totalQuestions }}</strong>
              </div>
            </div>
          </div>

          <!-- Right Column (7 cols): 4 Balanced Supporting KPI Tiles -->
          <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">

            <!-- KPI 1: Auto-Graded Score -->
            <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4.5 shadow-2xs flex flex-col justify-between hover:shadow-xs transition-all">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-[#71819B]">Auto-Graded Marks</p>
                  <div class="flex items-baseline gap-1.5 mt-1.5">
                    <span class="text-2xl font-black font-mono text-[#17243A]">
                      {{ resultDetail.summary.autoScore }}
                    </span>
                    <span class="text-xs font-semibold text-[#71819B] font-mono">
                      / {{ resultDetail.summary.autoScoreTotal }} Marks
                    </span>
                  </div>
                </div>
                <div class="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center shrink-0 border border-cyan-100">
                  <Zap :size="16" class="stroke-[2.5]" />
                </div>
              </div>
              <div class="mt-3 pt-2.5 border-t border-[#F0F3F8] text-[11px] text-[#71819B] flex items-center justify-between">
                <span>Objective format</span>
                <span class="font-bold text-cyan-700 bg-cyan-50 px-2 py-0.5 rounded-full">System Evaluated</span>
              </div>
            </div>

            <!-- KPI 2: Manual Subjective Marks -->
            <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4.5 shadow-2xs flex flex-col justify-between hover:shadow-xs transition-all">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-[#71819B]">Manual Marks</p>
                  <div class="flex items-baseline gap-1.5 mt-1.5">
                    <template v-if="resultDetail.attempt.db_status !== 'graded' && resultDetail.summary.manualScoreTotal > 0">
                      <span class="text-base font-bold text-amber-600">Pending Review</span>
                    </template>
                    <template v-else>
                      <span class="text-2xl font-black font-mono text-[#17243A]">
                        {{ resultDetail.summary.manualScore }}
                      </span>
                    </template>
                    <span class="text-xs font-semibold text-[#71819B] font-mono">
                      / {{ resultDetail.summary.manualScoreTotal }} Marks
                    </span>
                  </div>
                </div>
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-100">
                  <FileEdit :size="16" class="stroke-[2.5]" />
                </div>
              </div>
              <div class="mt-3 pt-2.5 border-t border-[#F0F3F8] text-[11px] text-[#71819B] flex items-center justify-between">
                <span>Subjective format</span>
                <span class="font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">
                  {{ resultDetail.summary.manualScoreTotal === 0 ? 'None in Exam' : 'Instructor Scored' }}
                </span>
              </div>
            </div>

            <!-- KPI 3: Accuracy & Answers Rate -->
            <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4.5 shadow-2xs flex flex-col justify-between hover:shadow-xs transition-all">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-[#71819B]">Response Accuracy</p>
                  <div class="flex items-baseline gap-1.5 mt-1.5">
                    <span class="text-2xl font-black font-mono text-emerald-600">
                      {{ resultDetail.summary.correctAnswers }}
                    </span>
                    <span class="text-xs font-semibold text-[#71819B]">
                      Correct ({{ resultDetail.summary.correctPct }}%)
                    </span>
                  </div>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-[#10B981] flex items-center justify-center shrink-0 border border-emerald-100">
                  <Target :size="16" class="stroke-[2.5]" />
                </div>
              </div>
              <div class="mt-3 pt-2.5 border-t border-[#F0F3F8] text-[11px] text-[#71819B] flex items-center justify-between">
                <span>Wrong/Missed:</span>
                <span class="font-bold text-rose-600 font-mono">
                  {{ resultDetail.summary.wrongAnswers }} ({{ resultDetail.summary.wrongPct }}%)
                </span>
              </div>
            </div>

            <!-- KPI 4: Questions Attempted -->
            <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4.5 shadow-2xs flex flex-col justify-between hover:shadow-xs transition-all">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-[#71819B]">Attempt Coverage</p>
                  <div class="flex items-baseline gap-1.5 mt-1.5">
                    <span class="text-2xl font-black font-mono text-[#17243A]">
                      {{ resultDetail.summary.attempted }}
                    </span>
                    <span class="text-xs font-semibold text-[#71819B]">
                      of {{ resultDetail.summary.totalQuestions }} Attempted
                    </span>
                  </div>
                </div>
                <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 border border-[#D6DBFF]">
                  <ListChecks :size="16" class="stroke-[2.5]" />
                </div>
              </div>
              <div class="mt-3 pt-2.5 border-t border-[#F0F3F8] text-[11px] text-[#71819B] flex items-center justify-between">
                <span>Unanswered:</span>
                <span class="font-bold text-slate-700 font-mono">
                  {{ resultDetail.summary.totalQuestions - resultDetail.summary.attempted }} questions
                </span>
              </div>
            </div>

          </div>

        </div>

        <!-- ── Section 2: Question Type Performance Breakdown ── -->
        <div v-if="resultDetail.scoreByType && resultDetail.scoreByType.length > 0" class="bg-white rounded-2xl border border-[#E5EAF3] p-4 sm:p-5 shadow-2xs space-y-3.5">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-sm font-bold text-[#17243A] flex items-center gap-2">
                <Layers :size="15" class="text-[#4F35F3]" />
                Performance by Question Format
              </h2>
              <p class="text-xs text-[#71819B] mt-0.5">Evaluation accuracy across question types.</p>
            </div>
            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-[#FAFBFD] border border-[#E5EAF3] text-[#71819B]">
              {{ resultDetail.scoreByType.length }} Format{{ resultDetail.scoreByType.length === 1 ? '' : 's' }}
            </span>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div
              v-for="typeItem in resultDetail.scoreByType"
              :key="typeItem.key"
              class="p-3.5 rounded-xl border border-[#E5EAF3] bg-[#FAFBFD]/60 flex flex-col justify-between space-y-2.5"
            >
              <div class="flex items-center justify-between gap-1.5">
                <span class="text-xs font-bold text-[#17243A] truncate">{{ typeItem.label }}</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-white border border-[#E5EAF3] text-[#71819B] font-mono">
                  {{ typeItem.count }} Qs
                </span>
              </div>

              <div>
                <div class="flex items-baseline justify-between">
                  <span class="font-mono font-black text-sm text-[#17243A]">
                    {{ typeItem.earned }} <span class="text-[11px] font-semibold text-[#71819B]">/ {{ typeItem.total }} pts</span>
                  </span>
                  <span :class="['text-[11px] font-extrabold font-mono', typeItem.percentage >= 50 ? 'text-emerald-600' : 'text-rose-600']">
                    {{ typeItem.percentage }}%
                  </span>
                </div>
                <!-- Mini Progress Bar -->
                <div class="h-1.5 w-full bg-slate-200/70 rounded-full mt-1.5 overflow-hidden">
                  <div
                    :class="['h-full rounded-full', typeItem.percentage >= 50 ? 'bg-emerald-500' : 'bg-rose-500']"
                    :style="{ width: `${typeItem.percentage}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Section 3: Question Review Toolbar (Filters + Controls + Legend) ── -->
        <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4 sm:p-5 shadow-2xs space-y-4">

          <!-- Row 1: Question Type Pills + Expand/Collapse Button -->
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-xs font-bold text-[#71819B] mr-1 flex items-center gap-1">
                <FileText :size="13" /> Format:
              </span>
              <button
                v-for="(count, typeKey) in filterCounts"
                :key="typeKey"
                @click="selectedFilter = typeKey"
                :class="[
                  'px-3 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5 cursor-pointer',
                  selectedFilter === typeKey
                    ? 'bg-[#4F35F3] text-white border-[#4F35F3] shadow-xs'
                    : 'bg-[#F5F7FC] text-[#71819B] hover:text-[#17243A] hover:bg-[#EEF0FF] border-[#E5EAF3]'
                ]"
              >
                <span>{{ filterLabels[typeKey] || typeKey }}</span>
                <span
                  :class="[
                    'px-1.5 py-0.2 rounded-full text-[9px] font-mono font-black',
                    selectedFilter === typeKey ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-700'
                  ]"
                >
                  {{ count }}
                </span>
              </button>
            </div>

            <!-- Expand / Collapse All Toggle -->
            <button
              @click="toggleAllQuestions"
              class="self-start md:self-auto px-3.5 py-1.5 rounded-xl border border-[#E5EAF3] bg-[#F5F7FC] hover:bg-[#EEF0FF] text-xs font-bold text-[#71819B] hover:text-[#4F35F3] transition-colors flex items-center gap-1.5 cursor-pointer shrink-0"
            >
              <component :is="areAllExpanded ? Minimize2 : Maximize2" :size="13" />
              <span>{{ areAllExpanded ? 'Collapse All' : 'Expand All' }}</span>
            </button>
          </div>

          <!-- Row 2: Outcome Filter Pills + Unified Visual Legend -->
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pt-3 border-t border-[#F0F3F8]">

            <!-- Outcome Segmented Buttons -->
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-xs font-bold text-[#71819B] mr-1">Status:</span>

              <button
                @click="selectedOutcomeFilter = 'all'"
                :class="[
                  'px-2.5 py-1 rounded-lg text-xs font-bold transition-all border cursor-pointer',
                  selectedOutcomeFilter === 'all'
                    ? 'bg-slate-800 text-white border-slate-800 shadow-2xs'
                    : 'bg-white text-[#71819B] hover:text-[#17243A] border-[#E5EAF3]'
                ]"
              >
                All ({{ outcomeCounts.all }})
              </button>

              <button
                @click="selectedOutcomeFilter = 'correct'"
                :class="[
                  'px-2.5 py-1 rounded-lg text-xs font-bold transition-all border flex items-center gap-1 cursor-pointer',
                  selectedOutcomeFilter === 'correct'
                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs'
                    : 'bg-white text-emerald-700 border-emerald-200 hover:bg-emerald-50'
                ]"
              >
                <Check :size="12" class="stroke-[3]" />
                <span>Correct ({{ outcomeCounts.correct }})</span>
              </button>

              <button
                @click="selectedOutcomeFilter = 'wrong'"
                :class="[
                  'px-2.5 py-1 rounded-lg text-xs font-bold transition-all border flex items-center gap-1 cursor-pointer',
                  selectedOutcomeFilter === 'wrong'
                    ? 'bg-rose-600 text-white border-rose-600 shadow-2xs'
                    : 'bg-white text-rose-700 border-rose-200 hover:bg-rose-50'
                ]"
              >
                <X :size="12" class="stroke-[3]" />
                <span>Incorrect ({{ outcomeCounts.wrong }})</span>
              </button>

              <button
                v-if="outcomeCounts.unanswered > 0"
                @click="selectedOutcomeFilter = 'unanswered'"
                :class="[
                  'px-2.5 py-1 rounded-lg text-xs font-bold transition-all border flex items-center gap-1 cursor-pointer',
                  selectedOutcomeFilter === 'unanswered'
                    ? 'bg-slate-600 text-white border-slate-600 shadow-2xs'
                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                ]"
              >
                <span>Unanswered ({{ outcomeCounts.unanswered }})</span>
              </button>
            </div>

            <!-- Legend Dots -->
            <div class="flex items-center gap-4 text-[11px] font-bold text-[#71819B] flex-wrap">
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-emerald-100"></span>
                <span>Correct</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 ring-2 ring-rose-100"></span>
                <span>Incorrect</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 ring-2 ring-amber-100"></span>
                <span>Partial</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-slate-400 ring-2 ring-slate-100"></span>
                <span>Unanswered</span>
              </div>
            </div>

          </div>

        </div>

        <!-- ── Section 4: Questions List Section ── -->
        <div class="space-y-4">

          <!-- Section Heading -->
          <div class="flex items-center justify-between px-1">
            <h3 class="text-sm font-black text-[#17243A] flex items-center gap-2">
              <HelpCircle :size="16" class="text-[#4F35F3]" />
              Question Responses & Evaluation
            </h3>
            <span class="text-xs font-bold text-[#4F35F3] bg-[#EEF0FF] border border-[#D6DBFF] px-2.5 py-0.5 rounded-full">
              Showing {{ filteredQuestions.length }} of {{ resultDetail.questions?.length || 0 }} Questions
            </span>
          </div>

          <!-- Empty Filter Alert -->
          <div v-if="filteredQuestions.length === 0" class="bg-white rounded-2xl border border-[#E5EAF3] p-10 text-center space-y-3 shadow-2xs">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto border border-amber-100">
              <AlertCircle :size="20" class="stroke-[2.5]" />
            </div>
            <div>
              <h4 class="text-sm font-bold text-[#17243A]">No Questions Under This Filter</h4>
              <p class="text-xs text-[#71819B] mt-0.5">Try resetting your format or status filter to see other questions.</p>
            </div>
            <button
              @click="selectedFilter = 'all'; selectedOutcomeFilter = 'all'"
              class="px-4 py-1.5 rounded-xl bg-[#F5F7FC] hover:bg-[#EEF0FF] text-[#4F35F3] border border-[#E5EAF3] text-xs font-bold transition-all inline-flex items-center gap-1 cursor-pointer"
            >
              <X :size="12" /> Reset Filters
            </button>
          </div>

          <!-- Question Groups by Instruction -->
          <div v-else class="space-y-5">
            <template v-for="(group, gIdx) in groupedFilteredQuestions" :key="gIdx">

              <!-- Instruction Divider -->
              <div v-if="group.instruction" class="flex items-center gap-3 pt-2">
                <div class="flex-1 h-px bg-[#E5EAF3]"></div>
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-[#EEF0FF] border border-[#D6DBFF] text-[#4F35F3] text-xs font-bold shrink-0 shadow-2xs">
                  <FileText :size="13" class="stroke-[2.5]" />
                  <span>{{ group.instruction }}</span>
                </div>
                <div class="flex-1 h-px bg-[#E5EAF3]"></div>
              </div>

              <!-- Question Cards within Group -->
              <div class="space-y-3.5">
                <div
                  v-for="q in group.questions"
                  :key="q.id"
                  class="bg-white rounded-2xl border border-[#E5EAF3] p-4 sm:p-5.5 shadow-2xs hover:shadow-xs transition-all space-y-4"
                >
                  <!-- Card Header: Number + Type + Prompt + Outcome Badge + Chevron -->
                  <div class="flex items-start justify-between gap-3">

                    <div class="flex items-start gap-3 min-w-0 flex-1">
                      <!-- Question Number Badge -->
                      <div class="w-8 h-8 rounded-xl bg-[#EEF0FF] text-[#4F35F3] border border-[#D6DBFF] flex items-center justify-center font-mono font-black text-xs shrink-0 mt-0.5">
                        Q{{ q.number }}
                      </div>

                      <div class="space-y-2 min-w-0 flex-1">
                        <!-- Type Pill & Marks Pill -->
                        <div class="flex items-center gap-2 flex-wrap">
                          <span :class="['px-2.5 py-0.5 rounded-md text-[10px] font-extrabold border', getTypeBadgeColor(q.type)]">
                            {{ q.typeLabel || q.type }}
                          </span>
                          <span class="text-[11px] font-bold font-mono text-[#71819B] bg-[#F5F7FC] px-2 py-0.5 rounded-md border border-[#E5EAF3]">
                            Weight: {{ q.marks }} Marks
                          </span>
                        </div>

                        <!-- Question Statement Prompt -->
                        <div class="text-sm font-bold text-[#17243A] leading-relaxed break-words">
                          {{ q.text }}
                        </div>

                        <!-- MCQ Options Display (If Multiple Choice) -->
                        <div v-if="q.type === 'multiple_choice' && q.options && q.options.length > 0" class="space-y-1.5 pt-1">
                          <div
                            v-for="opt in q.options"
                            :key="opt.label"
                            class="text-xs font-medium text-slate-700 flex items-start gap-2 bg-[#FAFBFD] p-2 rounded-lg border border-[#E5EAF3]/80"
                          >
                            <span class="font-bold text-[#4F35F3] font-mono shrink-0 w-5">{{ opt.label }}.</span>
                            <span>{{ opt.text }}</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Right Side: Marks Score + Status Badge + Chevron -->
                    <div class="flex items-center gap-2 shrink-0">
                      <!-- Status Outcome Badge -->
                      <div
                        v-if="q.status === 'correct'"
                        class="px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-black flex items-center gap-1.5"
                      >
                        <Check :size="13" class="stroke-[3]" />
                        <span>+{{ q.earnedMarks }} pts</span>
                      </div>

                      <div
                        v-else-if="q.status === 'partial'"
                        class="px-2.5 py-1 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 text-xs font-black flex items-center gap-1.5"
                      >
                        <AlertCircle :size="13" class="stroke-[2.5]" />
                        <span>+{{ q.earnedMarks }} pts</span>
                      </div>

                      <div
                        v-else-if="q.status === 'pending'"
                        class="px-2.5 py-1 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 text-xs font-bold flex items-center gap-1.5"
                      >
                        <Clock :size="13" class="stroke-[2.5]" />
                        <span>Pending</span>
                      </div>

                      <div
                        v-else
                        class="px-2.5 py-1 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 text-xs font-black flex items-center gap-1.5"
                      >
                        <X :size="13" class="stroke-[3]" />
                        <span>0 / {{ q.marks }} pts</span>
                      </div>

                      <!-- Expand / Collapse Button -->
                      <button
                        @click="toggleQuestion(q.id)"
                        class="p-1.5 rounded-lg border border-[#E5EAF3] bg-[#F5F7FC] hover:bg-[#EEF0FF] text-[#71819B] hover:text-[#4F35F3] transition-colors cursor-pointer"
                        :title="expandedQuestions[q.id] ? 'Collapse response' : 'Expand response'"
                      >
                        <component :is="expandedQuestions[q.id] ? ChevronUp : ChevronDown" :size="14" class="stroke-[2.5]" />
                      </button>
                    </div>

                  </div>

                  <!-- Collapsible Response Comparison Panels -->
                  <div v-show="expandedQuestions[q.id]" class="pt-3 border-t border-[#F0F3F8] space-y-3">

                    <!-- Standard Question (MCQ, True/False, Fill Blank) -->
                    <div
                      v-if="q.type === 'multiple_choice' || q.type === 'true_false' || q.type === 'fill_blank'"
                      class="grid grid-cols-1 sm:grid-cols-2 gap-3"
                    >
                      <!-- Your Answer Box -->
                      <div class="space-y-1.5">
                        <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider block">Your Selected Answer</span>
                        <div :class="[
                          'p-3.5 rounded-xl border flex items-center justify-between text-xs',
                          q.status === 'correct'
                            ? 'bg-emerald-50/40 border-emerald-200 text-emerald-800'
                            : 'bg-rose-50/40 border-rose-200 text-rose-800'
                        ]">
                          <span class="font-bold">
                            {{ q.studentAnswer || 'Not answered' }}
                          </span>
                          <span :class="['inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full', q.status === 'correct' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800']">
                            <component :is="q.status === 'correct' ? Check : X" :size="12" class="stroke-[3]" />
                            <span>{{ q.status === 'correct' ? 'Correct' : 'Incorrect' }}</span>
                          </span>
                        </div>
                      </div>

                      <!-- Correct Answer Box -->
                      <div class="space-y-1.5">
                        <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider block">Official Correct Key</span>
                        <div class="p-3.5 rounded-xl border bg-emerald-50/60 border-emerald-200 text-xs flex items-center justify-between text-emerald-900">
                          <span class="font-black text-emerald-900">
                            {{ q.correctAnswer || 'Provided by instructor' }}
                          </span>
                          <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded-full">
                            <Check :size="12" class="stroke-[3]" />
                            <span>Correct Key</span>
                          </span>
                        </div>
                      </div>
                    </div>

                    <!-- Short Answer / Essay -->
                    <div v-else-if="q.type === 'short_answer'" class="space-y-3">
                      <div class="space-y-1.5">
                        <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider block">Your Submitted Response</span>
                        <div class="p-3.5 rounded-xl border border-[#E5EAF3] bg-[#FAFBFD] text-xs text-slate-800 leading-relaxed font-medium">
                          {{ q.studentAnswer || 'No written response submitted.' }}
                        </div>
                      </div>
                      <div v-if="q.correctAnswer" class="space-y-1.5">
                        <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider block">Expected Key / Benchmark</span>
                        <div class="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/40 text-xs text-emerald-900 leading-relaxed font-medium">
                          {{ q.correctAnswer }}
                        </div>
                      </div>
                    </div>

                    <!-- Matching Pairs Comparison -->
                    <div v-else-if="q.type === 'matching'" class="space-y-2">
                      <span class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider block">Matched Column Pairs</span>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <!-- Student Matched Pairs -->
                        <div class="p-3.5 rounded-xl border border-[#E5EAF3] bg-[#FAFBFD] space-y-2 text-xs">
                          <div class="font-bold text-[#71819B] text-[11px] uppercase tracking-wider pb-1 border-b border-slate-200">
                            Your Matching
                          </div>
                          <div
                            v-for="pair in (q.matchingPairs || [])"
                            :key="'std-' + pair.index"
                            class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-white border border-[#E5EAF3]"
                          >
                            <span class="font-bold text-[#17243A]">{{ pair.index }}. {{ pair.left }}</span>
                            <span class="text-[#71819B]">➔</span>
                            <span :class="['font-bold font-mono px-2 py-0.5 rounded', pair.isCorrect ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700']">
                              {{ pair.studentRight || 'Not matched' }}
                            </span>
                          </div>
                        </div>

                        <!-- Correct Matched Pairs -->
                        <div class="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/30 space-y-2 text-xs">
                          <div class="font-bold text-emerald-700 text-[11px] uppercase tracking-wider pb-1 border-b border-emerald-200">
                            Correct Key
                          </div>
                          <div
                            v-for="pair in (q.matchingPairs || [])"
                            :key="'corr-' + pair.index"
                            class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-white border border-emerald-200/80"
                          >
                            <span class="font-bold text-[#17243A]">{{ pair.index }}. {{ pair.left }}</span>
                            <span class="text-emerald-500">➔</span>
                            <span class="font-bold font-mono px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">
                              {{ pair.correctRight }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Explanation Callout (If Available) -->
                    <div v-if="q.explanation" class="p-3.5 rounded-xl bg-[#EEF0FF] border border-[#D6DBFF] text-xs text-[#17243A] flex items-start gap-2.5">
                      <HelpCircle :size="16" class="text-[#4F35F3] shrink-0 mt-0.5" />
                      <div>
                        <strong class="text-[#4F35F3] font-bold block mb-0.5">Instructor Explanation:</strong>
                        <p class="text-[#17243A] leading-relaxed">{{ q.explanation }}</p>
                      </div>
                    </div>

                  </div>

                </div>
              </div>

            </template>
          </div>

        </div>

      </div>

    </main>

  </div>
</template>

<style scoped>
@media print {
  :deep(header),
  :deep(nav),
  :deep(.no-print) {
    display: none !important;
  }
}
</style>
