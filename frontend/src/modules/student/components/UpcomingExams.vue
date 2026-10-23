<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

interface ExamItem {
  id: number
  title?: string
  examTitle?: string
  code?: string
  examCode?: string
  courseCode?: string
  course_code?: string
  courseName?: string
  course_name?: string
  instructor?: string
  instructor_name?: string
  instructorEmail?: string
  section?: string
  examType?: string
  exam_type?: string
  scheduledAt?: string | null
  scheduled_at?: string | null
  endAt?: string | null
  end_at?: string | null
  date?: string
  startTime?: string
  start_time?: string
  endTime?: string
  end_time?: string
  timeRange?: string
  durationMinutes?: number
  duration_minutes?: number
  duration?: string
  totalMarks?: number
  total_marks?: number
  totalQuestions?: number
  total_questions?: number
  status?: string
  rawStatus?: string
  room?: string
  instructions?: string
  description?: string
  allowCalculator?: boolean
  attemptStatus?: string | null
  attemptId?: number | null
  attemptStartedAt?: string | null
  isResultPublished?: boolean
}

const props = withDefaults(defineProps<{
  exams: ExamItem[]
  serverTime?: string
  isLoading?: boolean
  error?: string | null
}>(), {
  exams: () => [],
  serverTime: '',
  isLoading: false,
  error: null,
})

const emit = defineEmits<{
  (e: 'start-exam', examId: number): void
  (e: 'continue-exam', examId: number): void
  (e: 'view-details', exam: ExamItem): void
  (e: 'retry'): void
}>()

const router = useRouter()

// ── Time Synchronization & Real-time Clock ──
const nowMs = ref(Date.now())
const timeOffset = ref(0)
let timerId: number | null = null

const syncServerTime = () => {
  if (props.serverTime) {
    const srv = Date.parse(props.serverTime)
    if (!isNaN(srv)) {
      timeOffset.value = srv - Date.now()
    }
  }
}

const currentEffectiveTime = computed(() => nowMs.value + timeOffset.value)

const updateClock = () => {
  nowMs.value = Date.now()
}

const handleVisibility = () => {
  if (!document.hidden) {
    updateClock()
    syncServerTime()
  }
}

onMounted(() => {
  syncServerTime()
  updateClock()
  timerId = window.setInterval(updateClock, 1000)
  window.addEventListener('focus', handleVisibility)
  document.addEventListener('visibilitychange', handleVisibility)
})

onUnmounted(() => {
  if (timerId !== null) {
    clearInterval(timerId)
    timerId = null
  }
  window.removeEventListener('focus', handleVisibility)
  document.removeEventListener('visibilitychange', handleVisibility)
})

// ── Computed Exam State Evaluation ──
interface ExamCardComputed {
  exam: ExamItem
  effectiveState: 'in_progress' | 'available_now' | 'starting_soon' | 'upcoming' | 'completed' | 'closed'
  statusBadge: { text: string; bg: string; textClass: string; dotClass?: string }
  countdown: {
    days: string
    hours: string
    minutes: string
    seconds: string
    isUrgent: boolean
  }
  canStart: boolean
  canContinue: boolean
  isStartingSoon: boolean
}

const computedExamCards = computed<ExamCardComputed[]>(() => {
  const current = currentEffectiveTime.value

  return props.exams.map(exam => {
    const startStr = exam.scheduledAt || exam.scheduled_at || ''
    const startMs = startStr ? Date.parse(startStr) : 0
    const durationMins = exam.durationMinutes || exam.duration_minutes || 60
    const durationMs = durationMins * 60 * 1000
    const endMs = startMs ? startMs + durationMs : 0

    const attStatus = (exam.attemptStatus || '').toLowerCase()
    const isSubmitted = attStatus === 'submitted' || attStatus === 'graded' || attStatus === 'published'

    let effectiveState: ExamCardComputed['effectiveState'] = 'upcoming'

    if (attStatus === 'in_progress') {
      effectiveState = 'in_progress'
    } else if (isSubmitted) {
      effectiveState = 'completed'
    } else if (startMs > 0 && endMs > 0) {
      if (current < startMs) {
        const diffMs = startMs - current
        if (diffMs <= 30 * 60 * 1000) {
          effectiveState = 'starting_soon'
        } else {
          effectiveState = 'upcoming'
        }
      } else if (current >= startMs && current < endMs) {
        effectiveState = 'available_now'
      } else {
        effectiveState = 'closed'
      }
    } else {
      // Unscheduled or flexible window
      effectiveState = 'available_now'
    }

    // Badge styling
    let statusBadge = {
      text: 'Upcoming',
      bg: 'bg-indigo-50 border-indigo-200/80',
      textClass: 'text-[#5138ed]',
      dotClass: '',
    }

    if (effectiveState === 'in_progress') {
      statusBadge = {
        text: 'In Progress',
        bg: 'bg-blue-50 border-blue-200',
        textClass: 'text-blue-700',
        dotClass: 'bg-blue-500 animate-pulse',
      }
    } else if (effectiveState === 'available_now') {
      statusBadge = {
        text: 'Available Now',
        bg: 'bg-emerald-50 border-emerald-200',
        textClass: 'text-emerald-700 font-black',
        dotClass: 'bg-emerald-500 animate-ping',
      }
    } else if (effectiveState === 'starting_soon') {
      statusBadge = {
        text: 'Starting Soon',
        bg: 'bg-amber-50 border-amber-200',
        textClass: 'text-amber-800 font-bold',
        dotClass: 'bg-amber-500 animate-pulse',
      }
    } else if (effectiveState === 'completed') {
      statusBadge = {
        text: 'Completed',
        bg: 'bg-slate-100 border-slate-200',
        textClass: 'text-slate-700',
        dotClass: '',
      }
    } else if (effectiveState === 'closed') {
      statusBadge = {
        text: 'Closed',
        bg: 'bg-rose-50 border-rose-200',
        textClass: 'text-rose-700',
        dotClass: '',
      }
    }

    // Countdown calculation (time until start)
    let days = '00'
    let hours = '00'
    let minutes = '00'
    let seconds = '00'
    let isUrgent = false

    if (startMs > 0 && current < startMs) {
      const remainingMs = Math.max(0, startMs - current)
      const d = Math.floor(remainingMs / (1000 * 60 * 60 * 24))
      const h = Math.floor((remainingMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
      const m = Math.floor((remainingMs % (1000 * 60 * 60)) / (1000 * 60))
      const s = Math.floor((remainingMs % (1000 * 60)) / 1000)

      days = String(d).padStart(2, '0')
      hours = String(h).padStart(2, '0')
      minutes = String(m).padStart(2, '0')
      seconds = String(s).padStart(2, '0')
      isUrgent = remainingMs <= 30 * 60 * 1000
    }

    return {
      exam,
      effectiveState,
      statusBadge,
      countdown: { days, hours, minutes, seconds, isUrgent },
      canStart: effectiveState === 'available_now',
      canContinue: effectiveState === 'in_progress',
      isStartingSoon: effectiveState === 'starting_soon',
    }
  })
})

// Filter to ONLY strictly upcoming/active exams (NO completed, NO closed, NO cancelled)
const validUpcomingCards = computed(() => {
  return computedExamCards.value.filter(c =>
    ['in_progress', 'available_now', 'starting_soon', 'upcoming'].includes(c.effectiveState)
  )
})

// 1st exam card displayed with full width
const firstExamCard = computed(() => (validUpcomingCards.value.length > 0 ? validUpcomingCards.value[0] : null))

// Subsequent exam cards displayed below in responsive square grid
const otherExamCards = computed(() => (validUpcomingCards.value.length > 1 ? validUpcomingCards.value.slice(1) : []))

// Summary Metrics for Section Header
const totalUpcomingCount = computed(() => {
  return validUpcomingCards.value.filter(c => c.effectiveState === 'upcoming' || c.effectiveState === 'starting_soon').length
})
const totalAvailableCount = computed(() => {
  return validUpcomingCards.value.filter(c => c.effectiveState === 'available_now').length
})
const totalInProgressCount = computed(() => {
  return validUpcomingCards.value.filter(c => c.effectiveState === 'in_progress').length
})
</script>

<template>
  <section class="space-y-4 sm:space-y-5">
    
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION HEADER WITH SUMMARY PILLS & LINK TO SCHEDULE                 -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <div class="flex flex-wrap items-center gap-2.5">
          <h2 class="text-lg sm:text-xl font-black text-[#17243A] tracking-tight flex items-center gap-2">
            <span>Upcoming Exams</span>
          </h2>

          <!-- Summary Metric Pills -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span
              v-if="totalAvailableCount > 0"
              class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-100/80 text-emerald-800 border border-emerald-300 flex items-center gap-1.5 shadow-2xs animate-pulse"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
              {{ totalAvailableCount }} Available Now
            </span>
            <span
              v-if="totalInProgressCount > 0"
              class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100/80 text-blue-800 border border-blue-300 flex items-center gap-1.5 shadow-2xs"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-blue-600 animate-ping"></span>
              {{ totalInProgressCount }} In Progress
            </span>
            <span
              v-if="totalUpcomingCount > 0"
              class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#EEF0FF] text-[#4F35F3] border border-indigo-200 shadow-2xs"
            >
              {{ totalUpcomingCount }} Scheduled
            </span>
          </div>
        </div>
        <p class="text-xs text-[#71819B] mt-0.5 font-medium">
          View your scheduled examinations and start when the exam window opens.
        </p>
      </div>

      <!-- View Full Schedule Link -->
      <button
        @click="router.push('/student/schedule')"
        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#4F35F3] hover:text-indigo-800 bg-white hover:bg-indigo-50/60 border border-indigo-100 px-3.5 py-2 rounded-xl transition-all shadow-2xs cursor-pointer shrink-0 self-start sm:self-auto group"
      >
        <svg class="w-4 h-4 text-[#4F35F3] group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span>View Full Schedule</span>
        <span class="text-xs group-hover:translate-x-0.5 transition-transform">→</span>
      </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- STATE 1: LOADING SKELETON                                             -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div v-if="isLoading" class="space-y-5">
      <!-- Full-Width Hero Skeleton Card -->
      <div class="w-full bg-white rounded-3xl border border-[#E6EBF3] p-6 sm:p-7 shadow-2xs animate-pulse space-y-4">
        <div class="flex items-center justify-between">
          <div class="h-6 w-32 bg-slate-200 rounded-lg"></div>
          <div class="h-6 w-24 bg-slate-200 rounded-full"></div>
        </div>
        <div class="h-7 w-2/5 bg-slate-200 rounded-lg"></div>
        <div class="h-4 w-1/4 bg-slate-100 rounded"></div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
          <div class="h-12 bg-slate-100 rounded-xl"></div>
          <div class="h-12 bg-slate-100 rounded-xl"></div>
          <div class="h-12 bg-slate-100 rounded-xl"></div>
          <div class="h-12 bg-slate-100 rounded-xl"></div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- STATE 2: API ERROR MESSAGE & RETRY ACTION                             -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div
      v-else-if="error"
      class="bg-rose-50/80 border border-rose-200 rounded-2xl p-6 text-center space-y-3 shadow-2xs"
    >
      <div class="w-11 h-11 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div>
        <h3 class="text-sm font-bold text-rose-950">Unable to Load Upcoming Examinations</h3>
        <p class="text-xs text-rose-700 mt-1 max-w-md mx-auto">{{ error }}</p>
      </div>
      <button
        @click="emit('retry')"
        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition shadow-xs cursor-pointer"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
        <span>Retry Connection</span>
      </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- STATE 3: EMPTY STATE (NO ACTIVE/UPCOMING EXAMS)                       -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div
      v-else-if="validUpcomingCards.length === 0"
      class="bg-white rounded-2xl border border-[#E6EBF3] p-8 sm:p-10 text-center shadow-2xs hover:border-slate-300 transition-all space-y-3"
    >
      <div class="w-14 h-14 rounded-2xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center mx-auto shadow-2xs">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
        </svg>
      </div>
      <div class="max-w-md mx-auto">
        <h3 class="text-base font-bold text-[#17243A]">No upcoming exams scheduled.</h3>
        <p class="text-xs text-[#71819B] mt-1 font-medium leading-relaxed">
          Your published examination schedules will appear here when they become available from your department head.
        </p>
      </div>
      <div class="pt-2">
        <button
          @click="router.push('/student/schedule')"
          class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-[#4F35F3] bg-[#EEF0FF] hover:bg-indigo-100 rounded-xl transition cursor-pointer"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
          <span>Check Academic Schedule</span>
        </button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- STATE 4: UPCOMING EXAMS DISPLAY                                       -->
    <!-- 1ST EXAM: FULL-WIDTH HERO CARD                                        -->
    <!-- SUBSEQUENT EXAMS: SQUARE CARDS GRID BELOW                             -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div v-else class="space-y-6">

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- 1ST UPCOMING EXAM: FULL-WIDTH HERO CARD                              -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div
        v-if="firstExamCard"
        class="w-full bg-white rounded-3xl border border-[#E6EBF3] p-6 sm:p-7 lg:p-8 shadow-xs hover:border-slate-300 transition-all overflow-hidden relative group"
        :class="{
          'ring-2 ring-emerald-500/30 border-emerald-300/80': firstExamCard.effectiveState === 'available_now',
          'ring-2 ring-blue-500/30 border-blue-300/80': firstExamCard.effectiveState === 'in_progress',
          'border-amber-300/80 ring-2 ring-amber-400/20': firstExamCard.effectiveState === 'starting_soon',
        }"
      >
        <!-- Top Gradient Accent Strip -->
        <div
          v-if="firstExamCard.effectiveState === 'available_now'"
          class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600"
        ></div>
        <div
          v-else-if="firstExamCard.effectiveState === 'in_progress'"
          class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-[#5138ed]"
        ></div>
        <div
          v-else-if="firstExamCard.effectiveState === 'starting_soon'"
          class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber-400 via-orange-500 to-amber-600"
        ></div>
        <div
          v-else
          class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-[#5138ed] via-indigo-500 to-purple-600"
        ></div>

        <!-- Horizontal Flex on Desktop (Spanning Entire Section Width) -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">

          <!-- Left / Main Exam Specifications -->
          <div class="flex-1 min-w-0 space-y-4">
            
            <!-- Badges Row -->
            <div class="flex flex-wrap items-center gap-2">
              <span class="px-3 py-1 rounded-lg text-xs font-mono font-black uppercase bg-[#EEF0FF] text-[#4F35F3] border border-indigo-200">
                {{ firstExamCard.exam.courseCode || firstExamCard.exam.course_code || 'COURSE' }}
              </span>
              <span class="px-3 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700">
                {{ firstExamCard.exam.examType || firstExamCard.exam.exam_type || 'Exam' }}
              </span>
              <span class="px-3 py-1 rounded-lg text-xs font-semibold text-slate-500 bg-slate-50 border border-slate-200/60">
                {{ firstExamCard.exam.section || 'All Sections' }}
              </span>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-indigo-50 text-[#5138ed] border border-indigo-200/80">
                Next Scheduled Exam
              </span>

              <!-- Status Badge -->
              <span
                :class="[
                  firstExamCard.statusBadge.bg,
                  firstExamCard.statusBadge.textClass,
                  'px-3 py-1 rounded-full text-xs font-bold border flex items-center gap-1.5 shadow-2xs ml-auto sm:ml-0'
                ]"
              >
                <span v-if="firstExamCard.statusBadge.dotClass" :class="[firstExamCard.statusBadge.dotClass, 'w-2 h-2 rounded-full']"></span>
                {{ firstExamCard.statusBadge.text }}
              </span>
            </div>

            <!-- Title & Course Details -->
            <div>
              <h3 class="text-xl sm:text-2xl font-black text-[#17243A] leading-tight group-hover:text-[#4F35F3] transition-colors">
                {{ firstExamCard.exam.title || firstExamCard.exam.examTitle }}
              </h3>
              <p class="text-sm font-semibold text-slate-500 mt-1">
                {{ firstExamCard.exam.courseName || firstExamCard.exam.course_name }}
              </p>
            </div>

            <!-- Metadata Specs Row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs text-slate-600 bg-[#F6F8FC] p-3.5 rounded-2xl border border-[#E6EBF3]">
              <!-- Date -->
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-[#4F35F3] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <div class="min-w-0">
                  <span class="text-[10px] uppercase font-bold text-slate-400 block">Date</span>
                  <span class="font-bold text-slate-800 text-xs truncate block">{{ firstExamCard.exam.date || 'TBD' }}</span>
                </div>
              </div>

              <!-- Window -->
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-[#4F35F3] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="min-w-0">
                  <span class="text-[10px] uppercase font-bold text-slate-400 block">Window</span>
                  <span class="font-bold text-slate-800 text-xs truncate block">{{ firstExamCard.exam.startTime || firstExamCard.exam.start_time || 'TBD' }}</span>
                </div>
              </div>

              <!-- Duration -->
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-[#4F35F3] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="min-w-0">
                  <span class="text-[10px] uppercase font-bold text-slate-400 block">Duration</span>
                  <span class="font-bold text-slate-800 text-xs block">{{ firstExamCard.exam.duration || ((firstExamCard.exam.durationMinutes || 60) + 'm') }}</span>
                </div>
              </div>

              <!-- Total Marks -->
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-[#4F35F3] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <div class="min-w-0">
                  <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Marks</span>
                  <span class="font-bold text-slate-800 text-xs block">{{ firstExamCard.exam.totalMarks || firstExamCard.exam.total_marks || 100 }} Pts</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right / Action & Countdown Section -->
          <div class="lg:w-[380px] shrink-0 border-t lg:border-t-0 lg:border-l border-slate-100 lg:pl-6 pt-4 lg:pt-0 space-y-3.5 flex flex-col justify-center">

            <!-- Case 1: In Progress -->
            <div v-if="firstExamCard.effectiveState === 'in_progress'" class="space-y-3">
              <div class="p-3 rounded-2xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-ping shrink-0"></span>
                <span class="text-xs leading-snug">You have an ongoing examination attempt in progress.</span>
              </div>
              <div class="grid grid-cols-2 gap-2.5">
                <button
                  @click="emit('view-details', firstExamCard.exam)"
                  class="py-3 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition flex items-center justify-center cursor-pointer shadow-2xs"
                >
                  <span>Details</span>
                </button>
                <button
                  @click="emit('continue-exam', firstExamCard.exam.id)"
                  class="py-3 px-5 rounded-xl bg-[#4F35F3] hover:bg-indigo-700 text-white font-bold text-xs transition flex items-center justify-center gap-2 shadow-md hover:shadow-lg cursor-pointer"
                >
                  <span>Continue Exam</span>
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
              </div>
            </div>

            <!-- Case 2: Available Now -->
            <div v-else-if="firstExamCard.effectiveState === 'available_now'" class="space-y-3">
              <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-600 shrink-0 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-xs leading-snug">Exam window is open! You may begin your test now.</span>
              </div>
              <div class="grid grid-cols-2 gap-2.5">
                <button
                  @click="emit('view-details', firstExamCard.exam)"
                  class="py-3 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition flex items-center justify-center cursor-pointer shadow-2xs"
                >
                  <span>Details</span>
                </button>
                <button
                  @click="emit('start-exam', firstExamCard.exam.id)"
                  class="py-3 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition flex items-center justify-center gap-2 shadow-md hover:shadow-lg cursor-pointer"
                >
                  <span>Start Exam Now</span>
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
              </div>
            </div>

            <!-- Case 3: Upcoming / Starting Soon -->
            <div v-else class="space-y-3">
              <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-slate-500 uppercase tracking-wider text-[11px]">Exam Starts In</span>
                <span
                  v-if="firstExamCard.countdown.isUrgent"
                  class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200 animate-pulse"
                >
                  Approaching Soon
                </span>
              </div>

              <!-- 4-Box Digital Countdown Component -->
              <div class="grid grid-cols-4 gap-2 text-center">
                <div class="bg-slate-50 border border-slate-200/80 rounded-2xl py-2 px-1">
                  <span class="font-mono font-black text-slate-900 text-lg leading-none block">{{ firstExamCard.countdown.days }}</span>
                  <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mt-1 block">Days</span>
                </div>
                <div class="bg-slate-50 border border-slate-200/80 rounded-2xl py-2 px-1">
                  <span class="font-mono font-black text-slate-900 text-lg leading-none block">{{ firstExamCard.countdown.hours }}</span>
                  <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mt-1 block">Hours</span>
                </div>
                <div class="bg-slate-50 border border-slate-200/80 rounded-2xl py-2 px-1">
                  <span class="font-mono font-black text-slate-900 text-lg leading-none block">{{ firstExamCard.countdown.minutes }}</span>
                  <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mt-1 block">Mins</span>
                </div>
                <div class="bg-slate-50 border border-slate-200/80 rounded-2xl py-2 px-1" :class="{ 'bg-amber-50 border-amber-300': firstExamCard.countdown.isUrgent }">
                  <span
                    class="font-mono font-black text-lg leading-none block"
                    :class="firstExamCard.countdown.isUrgent ? 'text-amber-700' : 'text-slate-900'"
                  >{{ firstExamCard.countdown.seconds }}</span>
                  <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mt-1 block">Secs</span>
                </div>
              </div>

              <!-- View Details Action Button -->
              <button
                @click="emit('view-details', firstExamCard.exam)"
                class="w-full py-3 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-2xs"
              >
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>View Exam Details & Rules</span>
              </button>
            </div>

          </div>

        </div>
      </div>

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- OTHER UPCOMING EXAMS: RESPONSIVE SQUARE CARDS GRID (BELOW)           -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div v-if="otherExamCards.length > 0" class="space-y-3.5 pt-2">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
            <span>Other Upcoming Examinations</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#EEF0FF] text-[#4F35F3] border border-indigo-200">{{ otherExamCards.length }}</span>
          </h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6 items-stretch">
          <div
            v-for="card in otherExamCards"
            :key="card.exam.id"
            class="bg-white rounded-2xl border border-[#E6EBF3] p-5 sm:p-6 shadow-2xs hover:shadow-md hover:border-slate-300 transition-all flex flex-col justify-between relative group overflow-hidden"
            :class="{
              'ring-2 ring-emerald-500/30 border-emerald-300/80': card.effectiveState === 'available_now',
              'ring-2 ring-blue-500/30 border-blue-300/80': card.effectiveState === 'in_progress',
              'border-amber-200': card.effectiveState === 'starting_soon',
            }"
          >
            <!-- Top Colored Accent Stripe -->
            <div
              v-if="card.effectiveState === 'available_now'"
              class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"
            ></div>
            <div
              v-else-if="card.effectiveState === 'in_progress'"
              class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-500"
            ></div>
            <div
              v-else-if="card.effectiveState === 'starting_soon'"
              class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 to-orange-500"
            ></div>
            <div
              v-else
              class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-[#5138ed] to-indigo-500"
            ></div>

            <!-- Upper Card: Badges, Title, Metadata -->
            <div class="space-y-3.5">
              
              <!-- Badges Row -->
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="px-2 py-0.5 rounded-md text-[11px] font-mono font-black uppercase bg-[#EEF0FF] text-[#4F35F3] border border-indigo-200/80">
                    {{ card.exam.courseCode || card.exam.course_code || 'COURSE' }}
                  </span>
                  <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-700">
                    {{ card.exam.examType || card.exam.exam_type || 'Exam' }}
                  </span>
                  <span class="px-2 py-0.5 rounded-md text-[10px] font-medium text-slate-500 bg-slate-50 border border-slate-100 hidden sm:inline">
                    {{ card.exam.section || 'All Sections' }}
                  </span>
                </div>

                <!-- Status Badge -->
                <span
                  :class="[
                    card.statusBadge.bg,
                    card.statusBadge.textClass,
                    'px-2.5 py-0.5 rounded-full text-[11px] font-bold border flex items-center gap-1.5 shrink-0 shadow-2xs'
                  ]"
                >
                  <span v-if="card.statusBadge.dotClass" :class="[card.statusBadge.dotClass, 'w-1.5 h-1.5 rounded-full']"></span>
                  {{ card.statusBadge.text }}
                </span>
              </div>

              <!-- Exam Title & Course Name -->
              <div>
                <h4 class="text-base font-bold text-[#17243A] leading-snug line-clamp-1 group-hover:text-[#4F35F3] transition-colors">
                  {{ card.exam.title || card.exam.examTitle }}
                </h4>
                <p class="text-xs text-[#71819B] font-medium truncate mt-0.5">
                  {{ card.exam.courseName || card.exam.course_name }}
                </p>
              </div>

              <!-- Metadata Specs Grid -->
              <div class="grid grid-cols-2 gap-2 text-xs text-slate-600 bg-[#F6F8FC] p-3 rounded-xl border border-[#E6EBF3]">
                <div class="flex items-start gap-1.5">
                  <svg class="w-3.5 h-3.5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <div class="min-w-0">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Date</span>
                    <span class="font-bold text-slate-800 text-[11px] truncate block">{{ card.exam.date || 'TBD' }}</span>
                  </div>
                </div>

                <div class="flex items-start gap-1.5">
                  <svg class="w-3.5 h-3.5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div class="min-w-0">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Window</span>
                    <span class="font-bold text-slate-800 text-[11px] truncate block">{{ card.exam.startTime || card.exam.start_time || 'TBD' }}</span>
                  </div>
                </div>

                <div class="flex items-start gap-1.5">
                  <svg class="w-3.5 h-3.5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div class="min-w-0">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Duration</span>
                    <span class="font-bold text-slate-800 text-[11px] block">{{ card.exam.duration || ((card.exam.durationMinutes || 60) + 'm') }}</span>
                  </div>
                </div>

                <div class="flex items-start gap-1.5">
                  <svg class="w-3.5 h-3.5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <div class="min-w-0">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Marks</span>
                    <span class="font-bold text-slate-800 text-[11px] block">{{ card.exam.totalMarks || card.exam.total_marks || 100 }} Pts</span>
                  </div>
                </div>
              </div>

            </div>

            <!-- Lower Card: Countdown or Action Button -->
            <div class="pt-4 mt-3 border-t border-slate-100 space-y-3">
              
              <!-- Case A: Upcoming / Starting Soon -->
              <div
                v-if="card.effectiveState === 'upcoming' || card.effectiveState === 'starting_soon'"
                class="space-y-2.5"
              >
                <div class="flex items-center justify-between text-[11px]">
                  <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">Exam Starts In</span>
                  <span
                    v-if="card.countdown.isUrgent"
                    class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200 animate-pulse"
                  >
                    Approaching Soon
                  </span>
                </div>

                <div class="grid grid-cols-4 gap-1.5 text-center">
                  <div class="bg-slate-50 border border-slate-200/80 rounded-xl py-2 px-1">
                    <span class="font-mono font-black text-slate-900 text-sm sm:text-base leading-none block">{{ card.countdown.days }}</span>
                    <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mt-0.5 block">Days</span>
                  </div>
                  <div class="bg-slate-50 border border-slate-200/80 rounded-xl py-2 px-1">
                    <span class="font-mono font-black text-slate-900 text-sm sm:text-base leading-none block">{{ card.countdown.hours }}</span>
                    <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mt-0.5 block">Hours</span>
                  </div>
                  <div class="bg-slate-50 border border-slate-200/80 rounded-xl py-2 px-1">
                    <span class="font-mono font-black text-slate-900 text-sm sm:text-base leading-none block">{{ card.countdown.minutes }}</span>
                    <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mt-0.5 block">Mins</span>
                  </div>
                  <div class="bg-slate-50 border border-slate-200/80 rounded-xl py-2 px-1" :class="{ 'bg-amber-50 border-amber-300': card.countdown.isUrgent }">
                    <span
                      class="font-mono font-black text-sm sm:text-base leading-none block"
                      :class="card.countdown.isUrgent ? 'text-amber-700' : 'text-slate-900'"
                    >{{ card.countdown.seconds }}</span>
                    <span class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mt-0.5 block">Secs</span>
                  </div>
                </div>

                <button
                  @click="emit('view-details', card.exam)"
                  class="w-full py-2.5 px-3 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
                >
                  <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <span>View Exam Details & Rules</span>
                </button>
              </div>

              <!-- Case B: Available Now -->
              <div v-else-if="card.effectiveState === 'available_now'" class="space-y-2.5">
                <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
                  <svg class="w-4 h-4 text-emerald-600 shrink-0 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="text-[11px] leading-tight">Exam window is open! You may begin your test now.</span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <button
                    @click="emit('view-details', card.exam)"
                    class="py-2.5 px-3 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition flex items-center justify-center cursor-pointer"
                  >
                    <span>Details</span>
                  </button>
                  <button
                    @click="emit('start-exam', card.exam.id)"
                    class="py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-sm cursor-pointer"
                  >
                    <span>Start Exam</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                  </button>
                </div>
              </div>

              <!-- Case C: In Progress -->
              <div v-else-if="card.effectiveState === 'in_progress'" class="space-y-2.5">
                <div class="p-2.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-blue-600 animate-ping"></span>
                  <span class="text-[11px] leading-tight">You have an ongoing attempt in progress.</span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <button
                    @click="emit('view-details', card.exam)"
                    class="py-2.5 px-3 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition flex items-center justify-center cursor-pointer"
                  >
                    <span>Details</span>
                  </button>
                  <button
                    @click="emit('continue-exam', card.exam.id)"
                    class="py-2.5 px-3 rounded-xl bg-[#4F35F3] hover:bg-indigo-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-sm cursor-pointer"
                  >
                    <span>Continue Exam</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                  </button>
                </div>
              </div>

            </div>

          </div>
        </div>
      </div>

    </div>

  </section>
</template>
