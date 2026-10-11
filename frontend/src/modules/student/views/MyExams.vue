<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { sampleAnnouncements } from '../data/mockData'
import type { RecentResult, UpcomingExam } from '../types'
import { useStudentExamStore } from '../store/studentExamStore'
import { useStudentProfile } from '../composables/useStudentProfile'

import Header from '../components/Header.vue'
import HeroSection from '../components/HeroSection.vue'
import StudentSidebar from '../components/StudentSidebar.vue'
import ProfileModal from '../components/ProfileModal.vue'
import ResultReviewModal from '../components/ResultReviewModal.vue'

const router = useRouter()
const examStore = useStudentExamStore()
const { profile, fetchProfile } = useStudentProfile()

const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)
const selectedResult = ref<RecentResult | null>(null)
const isResultModalOpen = ref(false)
const windowRef = window

// Reactive data from store
const upcomingExams = computed(() => examStore.upcomingExams)
const results = computed(() => examStore.results)
const activeExam = computed(() => examStore.activeExam)
const isLoading = computed(() => examStore.isLoading)

// Filter state
const activeFilter = ref<'todays' | 'ongoing' | 'completed' | 'all'>('todays')
const selectedCourse = ref<string>('All Courses')

// Course filter dropdown options
const availableCourses = computed(() => {
  const courses = new Set<string>()
  upcomingExams.value.forEach(e => {
    if (e.courseName) courses.add(e.courseName)
  })
  results.value.forEach(r => {
    if (r.courseName) courses.add(r.courseName)
  })
  return Array.from(courses)
})

// Helper to check if exam is scheduled for today
const isExamToday = (e: UpcomingExam) => {
  if (!e.scheduledAt && !e.scheduledDate) return true
  const rawDate = e.scheduledAt || e.scheduledDate
  const examDate = new Date(rawDate).toDateString()
  const todayDate = new Date().toDateString()
  return examDate === todayDate
}

// Dynamically filtered exam lists
const todaysExams = computed(() => {
  let list = upcomingExams.value.filter(e => isExamToday(e) || e.attemptStatus === 'in_progress')
  if (selectedCourse.value !== 'All Courses') {
    list = list.filter(e => e.courseName === selectedCourse.value || e.courseCode === selectedCourse.value)
  }
  return list
})

const ongoingExamsList = computed(() => {
  let list = upcomingExams.value.filter(e => e.attemptStatus === 'in_progress')
  if (activeExam.value && !list.some(e => e.id === activeExam.value?.id)) {
    list.unshift({
      id: activeExam.value.id,
      courseCode: activeExam.value.courseCode,
      courseName: activeExam.value.courseName,
      instructor: activeExam.value.instructor,
      examType: activeExam.value.examTitle,
      scheduledAt: activeExam.value.date,
      scheduledDate: activeExam.value.date,
      startTime: activeExam.value.time,
      durationMinutes: activeExam.value.durationMinutes,
      totalQuestions: activeExam.value.totalQuestions,
      totalMarks: activeExam.value.totalMarks,
      status: 'Ready',
      attemptStatus: 'in_progress',
    })
  }
  if (selectedCourse.value !== 'All Courses') {
    list = list.filter(e => e.courseName === selectedCourse.value || e.courseCode === selectedCourse.value)
  }
  return list
})

const filteredCompletedExams = computed(() => {
  let list = results.value
  if (selectedCourse.value !== 'All Courses') {
    list = list.filter(r => r.courseName === selectedCourse.value || r.courseCode === selectedCourse.value)
  }
  return list
})

const futureUpcomingExams = computed(() => {
  let list = upcomingExams.value.filter(e => !isExamToday(e) && e.attemptStatus !== 'in_progress')
  if (selectedCourse.value !== 'All Courses') {
    list = list.filter(e => e.courseName === selectedCourse.value || e.courseCode === selectedCourse.value)
  }
  return list
})

// Real summary stats for sidebar
const summaryStats = computed(() => ({
  todays: todaysExams.value.length,
  ongoing: ongoingExamsList.value.length,
  completed: results.value.length,
  total: upcomingExams.value.length + results.value.length,
}))

// Countdown timer for ongoing exam
const countdown = ref('45:32')
let timerInterval: ReturnType<typeof setInterval> | null = null

const startCountdown = (endTimeStr: string) => {
  if (timerInterval) clearInterval(timerInterval)
  timerInterval = setInterval(() => {
    const now = Date.now()
    const end = new Date(endTimeStr).getTime()
    const diff = Math.max(0, Math.floor((end - now) / 1000))
    const m = Math.floor(diff / 60).toString().padStart(2, '0')
    const s = (diff % 60).toString().padStart(2, '0')
    countdown.value = `${m}:${s}`
  }, 1000)
}

onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    examStore.fetchExams(),
    examStore.fetchResults(),
    examStore.fetchDashboard(),
  ])
  if (activeExam.value?.time) {
    const end = new Date(Date.now() + 45 * 60 * 1000 + 32 * 1000).toISOString()
    startCountdown(end)
  }
})

onUnmounted(() => {
  if (timerInterval) {
    clearInterval(timerInterval)
    timerInterval = null
  }
})

const handleStartExam = async (examId: number) => {
  try {
    await examStore.startExam(examId)
    router.push('/student/exam/take')
  } catch (err: any) {
    await Promise.all([
      examStore.fetchExams(true),
      examStore.fetchResults()
    ])
    windowRef.alert(err.message || 'Failed to start exam')
  }
}

const openResultReview = (result: RecentResult) => {
  selectedResult.value = result
  isResultModalOpen.value = true
}

const getCourseAbbr = (name: string, code?: string) => {
  if (code && code.length <= 4) return code.toUpperCase()
  if (!name) return 'EX'
  const words = name.trim().split(/\s+/)
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

// Mini Calendar Days
const today = new Date()
const calMonth = ref(today.getMonth())
const calYear = ref(today.getFullYear())
const calMonthName = computed(() =>
  new Date(calYear.value, calMonth.value).toLocaleString('en-US', { month: 'short', year: 'numeric' })
)
const calDays = computed(() => {
  const firstDay = new Date(calYear.value, calMonth.value, 1).getDay()
  const daysInMonth = new Date(calYear.value, calMonth.value + 1, 0).getDate()
  const cells: (number | null)[] = Array(firstDay).fill(null)
  for (let d = 1; d <= daysInMonth; d++) cells.push(d)
  return cells
})
const isToday = (d: number | null) => d === today.getDate() && calMonth.value === today.getMonth() && calYear.value === today.getFullYear()

const hasExamOnDay = (d: number | null) => {
  if (!d) return false
  return upcomingExams.value.some(e => {
    const raw = (e as any).scheduledAt || e.scheduledDate
    if (!raw) return false
    const dt = new Date(raw)
    return dt.getDate() === d && dt.getMonth() === calMonth.value && dt.getFullYear() === calYear.value
  })
}

const todayFormatted = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })

const quickTips = [
  'Ensure stable internet connection',
  'Close all other browser tabs and apps',
  'Read instructions carefully before beginning',
  'Manage your time effectively during the test',
]
</script>

<template>
  <div class="min-h-screen bg-[#F5F7FC] font-sans text-[#17243A] antialiased flex flex-col selection:bg-[#4F35F3] selection:text-white">

    <!-- Header & Sidebar -->
    <Header
      :profile="profile"
      :announcements="sampleAnnouncements"
      @open-profile="isProfileOpen = true"
      @open-notifications="() => {}"
      @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
    />
    <StudentSidebar :profile="profile" :isOpen="isSidebarOpen" @close="isSidebarOpen = false" />
    <ProfileModal
      v-if="isProfileOpen"
      :profile="profile"
      @close="isProfileOpen = false"
      @update-profile="(updated) => profile = updated"
    />
    <ResultReviewModal
      v-if="isResultModalOpen && selectedResult"
      :result="selectedResult"
      @close="isResultModalOpen = false"
    />

    <!-- Protected Hero Section (Structure & Background Kept 100% Intact) -->
    <HeroSection 
      :profile="profile" 
      :stats="{ examsCompleted: results.length, upcomingExams: upcomingExams.length }" 
      subtitle="ASSESSMENTS"
      title="MY EXAMS"
      description="Manage your ongoing and upcoming examinations in one place."
    />

    <!-- Main Exam Page Body (Content Below Hero Only) -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-5 sm:py-6 space-y-5 animate-in fade-in duration-200">
      
      <!-- ── Top Filter & Navigation Bar ── -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <!-- Compact Segmented Tab Control -->
        <div class="inline-flex items-center bg-white p-1 rounded-xl border border-[#E5EAF3] shadow-2xs gap-1 overflow-x-auto no-scrollbar max-w-full">
          <!-- 1. Today's Exams Tab -->
          <button
            @click="activeFilter = 'todays'"
            :class="[
              'px-3.5 sm:px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-150 whitespace-nowrap min-h-[36px] flex items-center gap-1.5 cursor-pointer',
              activeFilter === 'todays'
                ? 'bg-[#4F35F3] text-white shadow-xs'
                : 'text-[#71819B] hover:text-[#17243A] hover:bg-[#F5F7FC]'
            ]"
          >
            <span>Today's Exams</span>
            <span
              :class="[
                'text-[10px] font-bold px-1.5 py-0.2 rounded-full leading-none transition-colors',
                activeFilter === 'todays' ? 'bg-white/20 text-white' : 'bg-[#F5F7FC] text-[#71819B]'
              ]"
            >
              {{ summaryStats.todays }}
            </span>
          </button>

          <!-- 2. Ongoing Tab -->
          <button
            @click="activeFilter = 'ongoing'"
            :class="[
              'px-3.5 sm:px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-150 whitespace-nowrap min-h-[36px] flex items-center gap-1.5 cursor-pointer',
              activeFilter === 'ongoing'
                ? 'bg-[#4F35F3] text-white shadow-xs'
                : 'text-[#71819B] hover:text-[#17243A] hover:bg-[#F5F7FC]'
            ]"
          >
            <span>Ongoing</span>
            <span
              :class="[
                'text-[10px] font-bold px-1.5 py-0.2 rounded-full leading-none transition-colors',
                activeFilter === 'ongoing' ? 'bg-white/20 text-white' : 'bg-[#F5F7FC] text-[#71819B]'
              ]"
            >
              {{ summaryStats.ongoing }}
            </span>
          </button>

          <!-- 3. Completed Tab -->
          <button
            @click="activeFilter = 'completed'"
            :class="[
              'px-3.5 sm:px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-150 whitespace-nowrap min-h-[36px] flex items-center gap-1.5 cursor-pointer',
              activeFilter === 'completed'
                ? 'bg-[#4F35F3] text-white shadow-xs'
                : 'text-[#71819B] hover:text-[#17243A] hover:bg-[#F5F7FC]'
            ]"
          >
            <span>Completed</span>
            <span
              :class="[
                'text-[10px] font-bold px-1.5 py-0.2 rounded-full leading-none transition-colors',
                activeFilter === 'completed' ? 'bg-white/20 text-white' : 'bg-[#F5F7FC] text-[#71819B]'
              ]"
            >
              {{ summaryStats.completed }}
            </span>
          </button>

          <!-- 4. All Exams Tab -->
          <button
            @click="activeFilter = 'all'"
            :class="[
              'px-3.5 sm:px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-150 whitespace-nowrap min-h-[36px] flex items-center gap-1.5 cursor-pointer',
              activeFilter === 'all'
                ? 'bg-[#4F35F3] text-white shadow-xs'
                : 'text-[#71819B] hover:text-[#17243A] hover:bg-[#F5F7FC]'
            ]"
          >
            <span>All Exams</span>
            <span
              :class="[
                'text-[10px] font-bold px-1.5 py-0.2 rounded-full leading-none transition-colors',
                activeFilter === 'all' ? 'bg-white/20 text-white' : 'bg-[#F5F7FC] text-[#71819B]'
              ]"
            >
              {{ summaryStats.total }}
            </span>
          </button>
        </div>

        <!-- Course Selector Dropdown -->
        <div class="relative w-full sm:w-auto">
          <select
            v-model="selectedCourse"
            class="w-full sm:w-auto appearance-none text-xs font-medium text-[#17243A] bg-white border border-[#E5EAF3] rounded-xl pl-3.5 pr-8 py-2 shadow-2xs hover:border-[#4F35F3]/30 focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] h-[38px] cursor-pointer transition-colors"
          >
            <option>All Courses</option>
            <option v-for="c in availableCourses" :key="c" :value="c">{{ c }}</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-[#71819B]">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- Main Layout: 2 Columns with Balanced Spacing -->
      <div class="flex flex-col lg:flex-row gap-5 items-start">

        <!-- ── Left Main Content Column ── -->
        <div class="flex-1 w-full space-y-5 min-w-0">

          <!-- Loading Indicator Skeleton -->
          <div v-if="isLoading" class="p-6 text-center bg-white rounded-xl border border-[#E5EAF3] shadow-2xs">
            <svg class="animate-spin h-6 w-6 text-[#4F35F3] mx-auto mb-2.5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-xs font-semibold text-[#17243A]">Connecting to examination portal...</p>
            <p class="text-[11px] text-[#71819B] mt-0.5">Fetching latest exams and schedule records</p>
          </div>

          <template v-else>

            <!-- ── Section 1: Today's Exam Card ── -->
            <div
              v-if="activeFilter === 'todays' || activeFilter === 'all'"
              class="bg-white rounded-xl border border-[#E5EAF3] shadow-2xs p-4 sm:p-5 space-y-3.5 hover:border-slate-300 transition-colors"
            >
              <!-- Card Header -->
              <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div>
                    <h2 class="text-sm sm:text-base font-semibold text-[#17243A] leading-tight">Today's Exam</h2>
                    <p class="text-[11px] text-[#71819B] font-normal mt-0.5">{{ todayFormatted }}</p>
                  </div>
                </div>
                <span class="text-xs font-semibold text-[#4F35F3] bg-[#EEF0FF] px-2.5 py-1 rounded-full">
                  {{ todaysExams.length }} Exam{{ todaysExams.length === 1 ? '' : 's' }} Today
                </span>
              </div>

              <!-- Refined Notice Banner -->
              <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg bg-[#EEF0FF]/60 border border-[#4F35F3]/15 text-[#17243A] text-xs">
                <div class="w-5 h-5 rounded-md bg-[#4F35F3]/10 text-[#4F35F3] flex items-center justify-center shrink-0">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                  </svg>
                </div>
                <p class="leading-relaxed text-[11.5px]">
                  <span class="font-semibold text-[#4F35F3]">Notice for Student:</span> Please ensure a stable connection and turn on camera before clicking <span class="font-semibold text-[#4F35F3]">Start Exam</span>.
                </p>
              </div>

              <!-- Today's Exam List (When exams exist) -->
              <div v-if="todaysExams.length > 0" class="space-y-3">
                <div
                  v-for="exam in todaysExams"
                  :key="exam.id"
                  class="bg-[#F8FAFC] hover:bg-white rounded-lg border border-[#E5EAF3] hover:border-[#4F35F3]/30 p-3.5 sm:p-4 transition-all shadow-2xs"
                >
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-3 border-b border-[#E5EAF3]">
                    <!-- Course & Exam Info -->
                    <div class="flex items-center gap-3 min-w-0">
                      <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[#4F35F3] to-indigo-700 text-white font-semibold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                        {{ getCourseAbbr(exam.courseName, exam.courseCode) }}
                      </div>
                      <div class="min-w-0">
                        <h3 class="text-xs sm:text-sm font-semibold text-[#17243A] leading-tight truncate">{{ exam.courseName }}</h3>
                        <p class="text-[11px] text-[#71819B] mt-0.5 truncate">{{ exam.courseCode }} • {{ exam.examType }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-[#71819B]">
                          <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7 7z"/>
                          </svg>
                          <span class="truncate">Instructor: {{ exam.instructor || 'Wollo University' }}</span>
                        </div>
                      </div>
                    </div>

                    <!-- Timing Details -->
                    <div class="space-y-1 text-xs text-[#71819B]">
                      <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-[#4F35F3] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-semibold text-[#17243A]">{{ exam.startTime || 'Scheduled Time' }}</span>
                      </div>
                      <div class="flex items-center gap-1.5 text-[11px]">
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ exam.durationMinutes }} Mins ({{ exam.totalMarks }} Marks)</span>
                      </div>
                    </div>

                    <!-- Status -->
                    <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-1 shrink-0">
                      <span
                        :class="[
                          'inline-flex items-center gap-1 text-[10px] font-semibold px-2.5 py-0.5 rounded-full border',
                          exam.attemptStatus === 'in_progress'
                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        ]"
                      >
                        <span
                          :class="[
                            'w-1.5 h-1.5 rounded-full',
                            exam.attemptStatus === 'in_progress' ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'
                          ]"
                        ></span>
                        {{ exam.attemptStatus === 'in_progress' ? 'In Progress' : 'Ready' }}
                      </span>
                    </div>
                  </div>

                  <!-- Action Button -->
                  <div class="pt-2.5">
                    <button
                      @click="handleStartExam(exam.id)"
                      class="w-full min-h-[40px] bg-[#4F35F3] hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold text-xs py-2 px-4 rounded-lg shadow-xs transition-colors flex items-center justify-center gap-2 cursor-pointer"
                    >
                      <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                      <span>{{ exam.attemptStatus === 'in_progress' ? 'Continue Exam' : 'Start Exam' }}</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Compact, Refined Empty State (No massive blank space) -->
              <div v-else class="py-5 px-4 text-center rounded-lg bg-[#F8FAFC] border border-[#E5EAF3] flex flex-col items-center justify-center">
                <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center mb-2 shadow-2xs">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                  </svg>
                </div>
                <h3 class="text-xs font-semibold text-[#17243A]">No exams scheduled today</h3>
                <p class="text-[11px] text-[#71819B] max-w-sm mx-auto mt-0.5">
                  You have no exams scheduled for today, {{ todayFormatted }}.
                </p>
                <button
                  @click="activeFilter = 'all'"
                  class="mt-2.5 px-3 py-1.5 rounded-lg bg-white border border-[#E5EAF3] hover:border-[#4F35F3]/40 text-xs font-medium text-[#4F35F3] hover:bg-[#EEF0FF] shadow-2xs transition-colors inline-flex items-center gap-1 cursor-pointer"
                >
                  <span>View All Exams</span>
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- ── Section 2: Ongoing Exams ── -->
            <div
              v-if="(activeFilter === 'ongoing' || activeFilter === 'all') && ongoingExamsList.length > 0"
              class="bg-white rounded-xl border border-[#E5EAF3] shadow-2xs p-4 sm:p-5 space-y-3.5 hover:border-slate-300 transition-colors"
            >
              <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <div>
                    <h2 class="text-sm sm:text-base font-semibold text-[#17243A] leading-tight">Ongoing Exams</h2>
                    <p class="text-[11px] text-[#71819B] mt-0.5">Exams currently in progress and awaiting completion</p>
                  </div>
                </div>
                <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                  {{ ongoingExamsList.length }} Active
                </span>
              </div>

              <div class="space-y-3">
                <div
                  v-for="exam in ongoingExamsList"
                  :key="exam.id"
                  class="bg-[#F8FAFC] hover:bg-white rounded-lg border border-[#E5EAF3] p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 transition-all shadow-2xs hover:border-emerald-200"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-600 to-teal-700 text-white font-semibold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                      {{ getCourseAbbr(exam.courseName, exam.courseCode) }}
                    </div>
                    <div class="min-w-0">
                      <h3 class="text-xs sm:text-sm font-semibold text-[#17243A] leading-tight truncate">{{ exam.courseName }}</h3>
                      <p class="text-[11px] text-[#71819B] mt-0.5 truncate">{{ exam.courseCode }} • {{ exam.examType }}</p>
                      <div class="flex items-center gap-1 mt-0.5 text-[10px] text-emerald-600 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                        <span>Online Proctored • Session Live</span>
                      </div>
                    </div>
                  </div>

                  <div class="text-xs text-[#71819B] space-y-0.5">
                    <p>Started: <span class="font-semibold text-[#17243A]">{{ exam.startTime || 'Active' }}</span></p>
                    <p>Duration: <span class="font-semibold text-[#17243A]">{{ exam.durationMinutes }} Mins</span></p>
                  </div>

                  <div class="text-left sm:text-center shrink-0">
                    <p class="text-[10px] text-[#71819B] font-semibold uppercase tracking-wider">Remaining</p>
                    <p class="text-lg sm:text-xl font-bold text-[#17243A] font-mono leading-tight mt-0.5">{{ countdown }}</p>
                  </div>

                  <div class="shrink-0">
                    <button
                      @click="handleStartExam(exam.id)"
                      class="w-full sm:w-auto min-h-[38px] bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-semibold py-2 px-5 rounded-lg shadow-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                      <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                      <span>Continue Exam</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Ongoing Empty State -->
            <div
              v-else-if="activeFilter === 'ongoing' && ongoingExamsList.length === 0"
              class="py-5 px-4 text-center rounded-lg bg-white border border-[#E5EAF3] shadow-2xs flex flex-col items-center justify-center"
            >
              <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2 shadow-2xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <h3 class="text-xs font-semibold text-[#17243A]">No ongoing exams</h3>
              <p class="text-[11px] text-[#71819B] mt-0.5">There are currently no active exams in progress.</p>
              <button
                @click="activeFilter = 'todays'"
                class="mt-2.5 px-3 py-1.5 rounded-lg bg-[#EEF0FF] text-xs font-medium text-[#4F35F3] hover:bg-indigo-100 transition-colors inline-flex items-center gap-1 cursor-pointer"
              >
                <span>Check Today's Schedule</span>
              </button>
            </div>

            <!-- ── Section 3: Completed Exams ── -->
            <div
              v-if="activeFilter === 'completed' || activeFilter === 'all'"
              class="bg-white rounded-xl border border-[#E5EAF3] shadow-2xs p-4 sm:p-5 space-y-3.5 hover:border-slate-300 transition-colors"
            >
              <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                  </div>
                  <div>
                    <h2 class="text-sm sm:text-base font-semibold text-[#17243A] leading-tight">Completed Exams</h2>
                    <p class="text-[11px] text-[#71819B] mt-0.5">Recorded examination submissions and score sheets</p>
                  </div>
                </div>
                <span class="text-xs font-semibold text-[#4F35F3] bg-[#EEF0FF] px-2.5 py-0.5 rounded-full">
                  {{ filteredCompletedExams.length }} Recorded
                </span>
              </div>

              <div v-if="filteredCompletedExams.length > 0" class="space-y-3">
                <div
                  v-for="res in filteredCompletedExams"
                  :key="res.id"
                  class="bg-[#F8FAFC] hover:bg-white rounded-lg border border-[#E5EAF3] p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 transition-all shadow-2xs hover:border-[#4F35F3]/30"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-600 to-purple-700 text-white font-semibold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                      {{ getCourseAbbr(res.courseName, res.courseCode) }}
                    </div>
                    <div class="min-w-0">
                      <h3 class="text-xs sm:text-sm font-semibold text-[#17243A] leading-tight truncate">{{ res.courseName }}</h3>
                      <p class="text-[11px] text-[#71819B] mt-0.5 truncate">{{ res.courseCode }} • {{ res.examTitle }}</p>
                      <div class="flex items-center gap-1 mt-0.5 text-[10px] text-emerald-600 font-medium">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Official Result Logged</span>
                      </div>
                    </div>
                  </div>

                  <div class="text-xs text-[#71819B]">
                    <p class="text-[10px] text-[#71819B]">Completed on</p>
                    <p class="font-semibold text-[#17243A] mt-0.5">{{ res.completedDate || 'Recently' }}</p>
                  </div>

                  <div class="text-left sm:text-center shrink-0">
                    <p class="text-[10px] text-[#71819B] font-semibold uppercase tracking-wider">Score</p>
                    <p :class="['text-base sm:text-lg font-bold leading-tight mt-0.5 font-mono', res.status === 'Passed' ? 'text-emerald-600' : 'text-rose-500']">
                      {{ res.percentage }}%
                    </p>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <span :class="['text-[10px] font-semibold px-2.5 py-0.5 rounded-full border', res.status === 'Passed' ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-rose-700 bg-rose-50 border-rose-200']">
                      {{ res.status || 'Completed' }}
                    </span>
                    <button
                      @click="openResultReview(res)"
                      class="h-[34px] bg-white hover:bg-[#EEF0FF] border border-[#E5EAF3] hover:border-[#4F35F3]/30 text-[#17243A] hover:text-[#4F35F3] text-xs font-medium px-3.5 rounded-lg transition-all shadow-2xs flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                      <span>View Results</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Completed Empty State -->
              <div v-else class="py-5 px-4 text-center rounded-lg bg-[#F8FAFC] border border-[#E5EAF3] flex flex-col items-center justify-center">
                <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center mb-2 shadow-2xs">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <h3 class="text-xs font-semibold text-[#17243A]">No completed exams yet</h3>
                <p class="text-[11px] text-[#71819B] mt-0.5">Your official score sheets will appear here once graded.</p>
              </div>
            </div>

            <!-- ── Section 4: Upcoming Scheduled Exams (When viewing All Exams) ── -->
            <div
              v-if="activeFilter === 'all' && futureUpcomingExams.length > 0"
              class="bg-white rounded-xl border border-[#E5EAF3] shadow-2xs p-4 sm:p-5 space-y-3.5 hover:border-slate-300 transition-colors"
            >
              <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div>
                    <h2 class="text-sm sm:text-base font-semibold text-[#17243A] leading-tight">Upcoming Scheduled Exams</h2>
                    <p class="text-[11px] text-[#71819B] mt-0.5">Exams scheduled for upcoming calendar dates</p>
                  </div>
                </div>
                <span class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-full">
                  {{ futureUpcomingExams.length }} Scheduled
                </span>
              </div>

              <div class="space-y-3">
                <div
                  v-for="exam in futureUpcomingExams"
                  :key="exam.id"
                  class="bg-[#F8FAFC] hover:bg-white rounded-lg border border-[#E5EAF3] p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 transition-all shadow-2xs hover:border-amber-200"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 text-white font-semibold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                      {{ getCourseAbbr(exam.courseName, exam.courseCode) }}
                    </div>
                    <div class="min-w-0">
                      <h3 class="text-xs sm:text-sm font-semibold text-[#17243A] leading-tight truncate">{{ exam.courseName }}</h3>
                      <p class="text-[11px] text-[#71819B] mt-0.5 truncate">{{ exam.courseCode }} • {{ exam.examType }}</p>
                      <div class="flex items-center gap-1 mt-0.5 text-[10px] text-[#71819B]">
                        <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7 7z"/>
                        </svg>
                        <span class="truncate">Instructor: {{ exam.instructor || 'Wollo University' }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="text-xs text-[#71819B]">
                    <p>Date: <span class="font-semibold text-[#17243A]">{{ exam.scheduledDate || exam.scheduledAt || 'Upcoming' }}</span></p>
                    <p>Duration: <span class="font-semibold text-[#17243A]">{{ exam.durationMinutes }} Mins</span></p>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-full">
                      Scheduled
                    </span>
                    <button
                      @click="handleStartExam(exam.id)"
                      class="h-[34px] bg-[#4F35F3] hover:bg-indigo-700 text-white text-xs font-semibold px-4 rounded-lg shadow-xs transition-colors flex items-center justify-center gap-1 cursor-pointer"
                    >
                      <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                      <span>Start Exam</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── Section 5: Quick Actions Card ── -->
            <div class="bg-white rounded-xl border border-[#E5EAF3] shadow-2xs p-4 sm:p-5 space-y-3.5 hover:border-slate-300 transition-colors">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                  </div>
                  <div>
                    <h2 class="text-sm sm:text-base font-semibold text-[#17243A]">Quick Actions</h2>
                    <p class="text-[11px] text-[#71819B]">Direct shortcuts to examination tools and resources</p>
                  </div>
                </div>
              </div>

              <!-- Refined Cohesive Grid -->
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-3">
                <!-- 1. Take Exam -->
                <div
                  @click="activeFilter = 'todays'"
                  class="p-3 sm:p-3.5 rounded-lg border border-[#E5EAF3] bg-[#F8FAFC] hover:bg-white hover:border-[#4F35F3]/30 hover:shadow-2xs transition-all cursor-pointer group flex flex-col justify-between min-h-[92px]"
                >
                  <div class="flex items-center justify-between">
                    <div class="w-7 h-7 rounded-md bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center group-hover:scale-105 transition-transform">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </div>
                    <span class="text-[#71819B] group-hover:text-[#4F35F3] group-hover:translate-x-0.5 transition-all text-xs font-bold">›</span>
                  </div>
                  <div class="mt-2">
                    <p class="text-xs font-semibold text-[#17243A] group-hover:text-[#4F35F3] transition-colors leading-tight">Take Exam</p>
                    <p class="text-[10px] text-[#71819B] mt-0.5 truncate">Start available exam</p>
                  </div>
                </div>

                <!-- 2. View Results -->
                <div
                  @click="activeFilter = 'completed'"
                  class="p-3 sm:p-3.5 rounded-lg border border-[#E5EAF3] bg-[#F8FAFC] hover:bg-white hover:border-[#4F35F3]/30 hover:shadow-2xs transition-all cursor-pointer group flex flex-col justify-between min-h-[92px]"
                >
                  <div class="flex items-center justify-between">
                    <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="text-[#71819B] group-hover:text-[#4F35F3] group-hover:translate-x-0.5 transition-all text-xs font-bold">›</span>
                  </div>
                  <div class="mt-2">
                    <p class="text-xs font-semibold text-[#17243A] group-hover:text-[#4F35F3] transition-colors leading-tight">View Results</p>
                    <p class="text-[10px] text-[#71819B] mt-0.5 truncate">Check latest results</p>
                  </div>
                </div>

                <!-- 3. Exam Guidelines -->
                <div
                  @click="windowRef.alert('ACADEMIC INTEGRITY & EXAM GUIDELINES:\n1. Ensure a stable internet connection.\n2. Enable your webcam for proctoring.\n3. Do not switch browser tabs or windows.\n4. Complete all questions within the allocated time.')"
                  class="p-3 sm:p-3.5 rounded-lg border border-[#E5EAF3] bg-[#F8FAFC] hover:bg-white hover:border-[#4F35F3]/30 hover:shadow-2xs transition-all cursor-pointer group flex flex-col justify-between min-h-[92px]"
                >
                  <div class="flex items-center justify-between">
                    <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <span class="text-[#71819B] group-hover:text-[#4F35F3] group-hover:translate-x-0.5 transition-all text-xs font-bold">›</span>
                  </div>
                  <div class="mt-2">
                    <p class="text-xs font-semibold text-[#17243A] group-hover:text-[#4F35F3] transition-colors leading-tight">Exam Guidelines</p>
                    <p class="text-[10px] text-[#71819B] mt-0.5 truncate">Read instructions</p>
                  </div>
                </div>

                <!-- 4. My Schedule -->
                <div
                  @click="activeFilter = 'todays'"
                  class="p-3 sm:p-3.5 rounded-lg border border-[#E5EAF3] bg-[#F8FAFC] hover:bg-white hover:border-[#4F35F3]/30 hover:shadow-2xs transition-all cursor-pointer group flex flex-col justify-between min-h-[92px]"
                >
                  <div class="flex items-center justify-between">
                    <div class="w-7 h-7 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <span class="text-[#71819B] group-hover:text-[#4F35F3] group-hover:translate-x-0.5 transition-all text-xs font-bold">›</span>
                  </div>
                  <div class="mt-2">
                    <p class="text-xs font-semibold text-[#17243A] group-hover:text-[#4F35F3] transition-colors leading-tight">My Schedule</p>
                    <p class="text-[10px] text-[#71819B] mt-0.5 truncate">View exam schedule</p>
                  </div>
                </div>

                <!-- 5. Calculator -->
                <div
                  @click="windowRef.alert('Scientific Calculator tool available inside the active exam console window.')"
                  class="p-3 sm:p-3.5 rounded-lg border border-[#E5EAF3] bg-[#F8FAFC] hover:bg-white hover:border-[#4F35F3]/30 hover:shadow-2xs transition-all cursor-pointer group flex flex-col justify-between min-h-[92px]"
                >
                  <div class="flex items-center justify-between">
                    <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <span class="text-[#71819B] group-hover:text-[#4F35F3] group-hover:translate-x-0.5 transition-all text-xs font-bold">›</span>
                  </div>
                  <div class="mt-2">
                    <p class="text-xs font-semibold text-[#17243A] group-hover:text-[#4F35F3] transition-colors leading-tight">Calculator</p>
                    <p class="text-[10px] text-[#71819B] mt-0.5 truncate">Open calculator</p>
                  </div>
                </div>
              </div>
            </div>

          </template>

        </div>

        <!-- ── Right Sidebar Column ── -->
        <div class="w-full lg:w-76 xl:w-80 shrink-0 space-y-4">

          <!-- 1. Exam Summary Card (Refined, reduced visual noise) -->
          <div class="bg-white rounded-xl border border-[#E5EAF3] shadow-2xs p-4 sm:p-5 space-y-3 hover:border-slate-300 transition-colors">
            <div class="flex items-center justify-between">
              <h3 class="text-sm sm:text-base font-semibold text-[#17243A]">Exam Summary</h3>
              <span class="text-[10px] font-semibold text-[#71819B] bg-[#F5F7FC] px-2 py-0.5 rounded">Live</span>
            </div>
            
            <div class="space-y-1.5">
              <!-- Today's Exams -->
              <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#F8FAFC] hover:bg-[#F5F7FC] transition-colors">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-md bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                  <span class="text-xs font-medium text-[#17243A]">Today's Exams</span>
                </div>
                <span class="text-sm font-bold font-mono text-[#4F35F3]">{{ summaryStats.todays }}</span>
              </div>

              <!-- Ongoing Exams -->
              <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#F8FAFC] hover:bg-[#F5F7FC] transition-colors">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                  <span class="text-xs font-medium text-[#17243A]">Ongoing Exams</span>
                </div>
                <span class="text-sm font-bold font-mono text-emerald-600">{{ summaryStats.ongoing }}</span>
              </div>

              <!-- Completed Exams -->
              <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#F8FAFC] hover:bg-[#F5F7FC] transition-colors">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                  <span class="text-xs font-medium text-[#17243A]">Completed Exams</span>
                </div>
                <span class="text-sm font-bold font-mono text-blue-600">{{ summaryStats.completed }}</span>
              </div>

              <!-- Total Exams -->
              <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#F8FAFC] hover:bg-[#F5F7FC] transition-colors">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                  </div>
                  <span class="text-xs font-medium text-[#17243A]">Total Exams</span>
                </div>
                <span class="text-sm font-bold font-mono text-amber-600">{{ summaryStats.total }}</span>
              </div>
            </div>
          </div>

          <!-- 2. Calendar Card (Compact & Clean) -->
          <div class="bg-white rounded-xl border border-[#E5EAF3] shadow-2xs p-4 sm:p-5 space-y-3 hover:border-slate-300 transition-colors">
            <div class="flex items-center justify-between">
              <h3 class="text-sm sm:text-base font-semibold text-[#17243A]">Calendar</h3>
              <div class="flex items-center gap-1">
                <button
                  @click="calMonth > 0 ? calMonth-- : (calMonth = 11, calYear--)"
                  class="w-6 h-6 rounded-md hover:bg-[#F5F7FC] flex items-center justify-center text-[#71819B] hover:text-[#17243A] transition-colors cursor-pointer"
                  title="Previous Month"
                >
                  ‹
                </button>
                <span class="text-xs font-semibold text-[#17243A] min-w-[80px] text-center">{{ calMonthName }}</span>
                <button
                  @click="calMonth < 11 ? calMonth++ : (calMonth = 0, calYear++)"
                  class="w-6 h-6 rounded-md hover:bg-[#F5F7FC] flex items-center justify-center text-[#71819B] hover:text-[#17243A] transition-colors cursor-pointer"
                  title="Next Month"
                >
                  ›
                </button>
              </div>
            </div>

            <!-- Weekday Headers -->
            <div class="grid grid-cols-7 text-center">
              <div v-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d" class="text-[10px] font-semibold text-[#71819B] py-0.5">
                {{ d }}
              </div>
              <!-- Calendar Days -->
              <div
                v-for="(day, i) in calDays"
                :key="i"
                class="py-0.5 flex flex-col items-center justify-center"
              >
                <div
                  v-if="day"
                  :class="[
                    'w-6 h-6 flex items-center justify-center rounded-md text-[11px] transition-colors cursor-pointer relative mx-auto',
                    isToday(day)
                      ? 'bg-[#4F35F3] text-white font-semibold shadow-2xs'
                      : 'text-[#17243A] hover:bg-[#EEF0FF] hover:text-[#4F35F3] font-normal'
                  ]"
                >
                  {{ day }}
                  <span
                    v-if="hasExamOnDay(day) && !isToday(day)"
                    class="absolute bottom-0.5 w-1 h-1 rounded-full bg-[#4F35F3]"
                  ></span>
                </div>
              </div>
            </div>

            <!-- Calendar Legend -->
            <div class="flex items-center justify-between gap-1 pt-2.5 border-t border-[#E5EAF3] text-[10px] text-[#71819B]">
              <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#4F35F3]"></span><span>Exam</span></div>
              <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#10B981]"></span><span>Ongoing</span></div>
              <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#EF4444]"></span><span>Deadline</span></div>
              <div class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#3295FF]"></span><span>Event</span></div>
            </div>
          </div>

          <!-- 3. Quick Tips Card -->
          <div class="bg-white rounded-xl border border-[#E5EAF3] shadow-2xs p-4 sm:p-5 space-y-3 hover:border-slate-300 transition-colors">
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
              </div>
              <h3 class="text-xs font-semibold text-[#17243A]">Quick Tips</h3>
            </div>
            <ul class="space-y-1.5 text-xs text-[#71819B]">
              <li v-for="tip in quickTips" :key="tip" class="flex items-start gap-2 text-[11.5px]">
                <svg class="w-3 h-3 text-[#10B981] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>{{ tip }}</span>
              </li>
            </ul>
            <button
              @click="windowRef.alert('ACADEMIC INTEGRITY & EXAM GUIDELINES:\n1. Ensure a stable internet connection.\n2. Enable your webcam for proctoring.\n3. Do not switch browser tabs or windows.\n4. Complete all questions within the allocated time.')"
              class="w-full mt-1 bg-[#EEF0FF] hover:bg-[#EEF0FF]/80 text-[#4F35F3] border border-[#4F35F3]/20 text-xs font-semibold py-2 px-3 rounded-lg transition-colors cursor-pointer"
            >
              View Exam Guidelines
            </button>
          </div>

        </div>

      </div>

      <!-- Footer matching Wollo University standard -->
      <footer class="mt-8 pt-5 border-t border-[#E5EAF3] flex flex-col sm:flex-row items-center justify-between text-[11px] font-medium text-[#71819B] gap-3">
        <div>&copy; 2025 Wollo University. All rights reserved.</div>
        <div class="flex items-center gap-5">
          <a href="https://wu.edu.et" target="_blank" rel="noopener" class="hover:text-[#17243A] transition-colors">Privacy Policy</a>
          <a href="https://wu.edu.et" target="_blank" rel="noopener" class="hover:text-[#17243A] transition-colors">Terms of Service</a>
        </div>
      </footer>

    </main>
  </div>
</template>
