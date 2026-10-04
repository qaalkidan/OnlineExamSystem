<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
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
  if (!e.scheduledAt) return true
  const examDate = new Date(e.scheduledAt).toDateString()
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
    // If activeExam is present in store
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

const filteredAllExams = computed(() => {
  let upcoming = upcomingExams.value
  let res = results.value
  if (selectedCourse.value !== 'All Courses') {
    upcoming = upcoming.filter(e => e.courseName === selectedCourse.value || e.courseCode === selectedCourse.value)
    res = res.filter(r => r.courseName === selectedCourse.value || r.courseCode === selectedCourse.value)
  }
  return { upcoming, completed: res }
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

const handleStartExam = async (examId: number) => {
  try {
    await examStore.startExam(examId)
    router.push('/student/exam/take')
  } catch (err: any) {
    await examStore.fetchExams(true)
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
  new Date(calYear.value, calMonth.value).toLocaleString('en-US', { month: 'long', year: 'numeric' })
)
const calDays = computed(() => {
  const firstDay = new Date(calYear.value, calMonth.value, 1).getDay()
  const daysInMonth = new Date(calYear.value, calMonth.value + 1, 0).getDate()
  const cells: (number | null)[] = Array(firstDay).fill(null)
  for (let d = 1; d <= daysInMonth; d++) cells.push(d)
  return cells
})
const isToday = (d: number | null) => d === today.getDate() && calMonth.value === today.getMonth() && calYear.value === today.getFullYear()

const todayFormatted = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })

const quickTips = [
  'Ensure stable internet connection',
  'Close all other applications',
  'Read instructions carefully',
  'Manage your time effectively',
]
</script>

<template>
  <div class="min-h-screen bg-[#f5f6fa] font-sans text-slate-800 antialiased flex flex-col">

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

    <!-- Hero Section -->
    <HeroSection 
      :profile="profile" 
      :stats="{ examsCompleted: results.length, upcomingExams: upcomingExams.length }" 
      subtitle="ASSESSMENTS"
      title="MY EXAMS"
      description="Manage your ongoing and upcoming examinations in one place."
    />

    <!-- Main Exam Page Body -->
    <main class="flex-1 w-full mx-auto max-w-[1340px] px-4 sm:px-6 lg:px-8 py-8">
      <div class="flex flex-col lg:flex-row gap-6">

        <!-- ── Left Main Content Column ── -->
        <div class="flex-1 space-y-6">

          <!-- ── Top Filter Bar ── -->
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center bg-slate-100/80 p-1.5 rounded-2xl gap-1.5">
              <button
                @click="activeFilter = 'todays'"
                :class="['px-5 py-2.5 rounded-2xl text-xs font-bold transition-all duration-200',
                  activeFilter === 'todays' ? 'bg-indigo-50 text-indigo-600 border border-indigo-200/80 shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/50']"
              >
                Today's Exams
              </button>
              <button
                @click="activeFilter = 'ongoing'"
                :class="['px-5 py-2.5 rounded-2xl text-xs font-bold transition-all duration-200',
                  activeFilter === 'ongoing' ? 'bg-indigo-50 text-indigo-600 border border-indigo-200/80 shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/50']"
              >
                Ongoing
              </button>
              <button
                @click="activeFilter = 'completed'"
                :class="['px-5 py-2.5 rounded-2xl text-xs font-bold transition-all duration-200',
                  activeFilter === 'completed' ? 'bg-indigo-50 text-indigo-600 border border-indigo-200/80 shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/50']"
              >
                Completed
              </button>
              <button
                @click="activeFilter = 'all'"
                :class="['px-5 py-2.5 rounded-2xl text-xs font-bold transition-all duration-200',
                  activeFilter === 'all' ? 'bg-indigo-50 text-indigo-600 border border-indigo-200/80 shadow-sm' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/50']"
              >
                All Exams
              </button>
            </div>

            <!-- Course Selector Dropdown -->
            <select
              v-model="selectedCourse"
              class="text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-2xl px-4 py-2.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
            >
              <option>All Courses</option>
              <option v-for="c in availableCourses" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Loading Indicator -->
          <div v-if="isLoading" class="p-8 text-center bg-white rounded-3xl border border-slate-100 shadow-sm">
            <svg class="animate-spin h-6 w-6 text-indigo-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-xs font-semibold text-slate-500">Loading exams from Wollo University portal...</p>
          </div>

          <template v-else>

            <!-- ── Section 1: Today's Exam Card (Red Border Container) ── -->
            <div v-if="activeFilter === 'todays' || activeFilter === 'all'" class="bg-gradient-to-br from-red-50/30 via-white to-white rounded-3xl border-2 border-red-600/80 shadow-md p-6 space-y-5 ring-4 ring-red-50/50">
              <!-- Section Header -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-2xl bg-indigo-100/80 border border-indigo-200 flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div>
                    <h2 class="text-base font-bold text-slate-900 leading-tight">Today's Exam</h2>
                    <p class="text-[11px] text-slate-400">{{ todayFormatted }}</p>
                  </div>
                </div>
                <span class="text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 px-3.5 py-1 rounded-full shadow-2xs">
                  {{ todaysExams.length }} Exam{{ todaysExams.length === 1 ? '' : 's' }} Today
                </span>
              </div>

              <!-- Student Notification & Instruction Banner -->
              <div class="flex items-center gap-3 p-3.5 rounded-2xl bg-indigo-50/90 border border-indigo-100 text-indigo-950 text-xs font-medium shadow-2xs">
                <span class="flex-shrink-0 text-base">🔔</span>
                <p class="leading-relaxed">
                  <span class="font-bold text-indigo-700">Notice for Student:</span> Please ensure a stable internet connection and turn on your camera before clicking <span class="font-semibold text-indigo-600">Start Exam</span>.
                </p>
              </div>

              <!-- Real Today's Exam List -->
              <div v-if="todaysExams.length > 0" class="space-y-4">
                <div
                  v-for="exam in todaysExams"
                  :key="exam.id"
                  class="bg-white/90 rounded-2xl border border-indigo-100 p-5 shadow-2xs"
                >
                  <div class="flex flex-wrap md:flex-nowrap items-center justify-between gap-6 pb-5 border-b border-slate-100">
                    <!-- Course & Exam Info -->
                    <div class="flex items-center gap-4 min-w-[240px]">
                      <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-bold text-base flex items-center justify-center shadow-md shadow-indigo-100 flex-shrink-0">
                        {{ getCourseAbbr(exam.courseName, exam.courseCode) }}
                      </div>
                      <div>
                        <h3 class="text-base font-bold text-slate-900 leading-tight">{{ exam.courseName }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ exam.courseCode }} - {{ exam.examType }}</p>
                        <div class="flex items-center gap-1 mt-1 text-[11px] text-slate-400">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                          Instructor: {{ exam.instructor || 'Wollo University' }}
                        </div>
                      </div>
                    </div>

                    <!-- Timing Details -->
                    <div class="space-y-1 text-xs text-slate-600">
                      <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="font-medium text-slate-700">{{ exam.startTime || 'Scheduled Time' }}</span>
                      </div>
                      <div class="flex items-center gap-2 text-slate-400">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Duration: {{ exam.durationMinutes }} Mins ({{ exam.totalMarks }} Marks)</span>
                      </div>
                    </div>

                    <!-- Status & Countdown -->
                    <div class="text-right flex-shrink-0">
                      <span :class="[
                        'inline-block text-[11px] font-bold px-3 py-1 rounded-full mb-1 border',
                        exam.attemptStatus === 'in_progress' ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200'
                      ]">
                        {{ exam.attemptStatus === 'in_progress' ? 'In Progress' : 'Ready' }}
                      </span>
                      <p class="text-[11px] text-slate-400">Status</p>
                      <p class="text-xl font-black text-slate-900 leading-none">
                        {{ exam.attemptStatus === 'in_progress' ? 'Active' : 'Scheduled' }}
                      </p>
                    </div>
                  </div>

                  <!-- Full-Width Action Button with Green Border -->
                  <div class="pt-4">
                    <button
                      @click="handleStartExam(exam.id)"
                      class="w-full bg-indigo-50 hover:bg-indigo-100 text-emerald-600 border-2 border-emerald-500 font-bold py-3.5 px-6 rounded-2xl shadow-2xs transition-all duration-200 flex items-center justify-center gap-2"
                    >
                      <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                      <span>{{ exam.attemptStatus === 'in_progress' ? 'Continue Exam' : 'Start Exam' }}</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Today's Exam Empty State -->
              <div v-else class="p-6 text-center bg-white/70 rounded-2xl border border-slate-100">
                <p class="text-xs font-semibold text-slate-500">No exams scheduled for today.</p>
                <p class="text-[11px] text-slate-400 mt-1">Check back later or view all upcoming exams below.</p>
              </div>
            </div>

            <!-- ── Section 2: Ongoing Exams ── -->
            <div v-if="(activeFilter === 'ongoing' || activeFilter === 'all') && ongoingExamsList.length > 0" class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 space-y-4">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="text-base font-bold text-slate-900">Ongoing Exams</h2>
              </div>

              <div
                v-for="exam in ongoingExamsList"
                :key="exam.id"
                class="bg-slate-50/50 rounded-2xl border border-slate-100 p-5 flex flex-wrap md:flex-nowrap items-center justify-between gap-6"
              >
                <!-- Course Info -->
                <div class="flex items-center gap-4 min-w-[220px]">
                  <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-600 to-purple-700 text-white font-bold text-base flex items-center justify-center shadow-md shadow-indigo-100 flex-shrink-0">
                    {{ getCourseAbbr(exam.courseName, exam.courseCode) }}
                  </div>
                  <div>
                    <h3 class="text-base font-bold text-slate-900 leading-tight">{{ exam.courseName }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ exam.courseCode }} - {{ exam.examType }}</p>
                    <div class="flex items-center gap-1 mt-1 text-[11px] text-slate-400">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                      Online Proctored
                    </div>
                  </div>
                </div>

                <!-- Start Time & Duration -->
                <div class="text-xs text-slate-500 space-y-1">
                  <p>Started: <span class="font-semibold text-slate-700">{{ exam.startTime || 'Active' }}</span></p>
                  <p>Duration: <span class="font-semibold text-slate-700">{{ exam.durationMinutes }} Mins</span></p>
                </div>

                <!-- Time Remaining -->
                <div class="text-center flex-shrink-0">
                  <p class="text-[11px] text-slate-400 font-medium">Time Remaining</p>
                  <p class="text-3xl font-black text-slate-900 font-mono leading-tight">{{ countdown }}</p>
                </div>

                <!-- Action Button -->
                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                  <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">
                    In Progress
                  </span>
                  <button
                    @click="handleStartExam(exam.id)"
                    class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-200/80 text-indigo-600 text-xs font-bold py-3 px-6 rounded-2xl shadow-2xs transition-all duration-200 flex items-center gap-2"
                  >
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    Continue Exam
                  </button>
                </div>
              </div>
            </div>

            <!-- ── Section 3: Completed Exams ── -->
            <div v-if="activeFilter === 'completed' || activeFilter === 'all'" class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 space-y-4">
              <!-- Header -->
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <h2 class="text-base font-bold text-slate-900">Completed Exams</h2>
              </div>

              <!-- Real Completed Exams List -->
              <div v-if="filteredCompletedExams.length > 0" class="space-y-4">
                <div
                  v-for="res in filteredCompletedExams"
                  :key="res.id"
                  class="bg-slate-50/50 rounded-2xl border border-slate-100 p-5 flex flex-wrap md:flex-nowrap items-center justify-between gap-6"
                >
                  <!-- Course Info -->
                  <div class="flex items-center gap-4 min-w-[220px]">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-500 to-red-600 text-white font-bold text-base flex items-center justify-center shadow-md shadow-rose-100 flex-shrink-0">
                      {{ getCourseAbbr(res.courseName, res.courseCode) }}
                    </div>
                    <div>
                      <h3 class="text-base font-bold text-slate-900 leading-tight">{{ res.courseName }}</h3>
                      <p class="text-xs text-slate-500 mt-0.5">{{ res.courseCode }} - {{ res.examTitle }}</p>
                      <div class="flex items-center gap-1 mt-1 text-[11px] text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Official Result Logged
                      </div>
                    </div>
                  </div>

                  <!-- Date -->
                  <div class="text-xs text-slate-500">
                    <p class="text-[11px] text-slate-400">Completed on</p>
                    <p class="font-semibold text-slate-700 mt-0.5">{{ res.completedDate || 'Recently' }}</p>
                  </div>

                  <!-- Score -->
                  <div class="text-center flex-shrink-0">
                    <p class="text-[11px] text-slate-400 font-medium">Score</p>
                    <p :class="['text-2xl font-black leading-tight', res.status === 'Passed' ? 'text-emerald-500' : 'text-rose-500']">
                      {{ res.percentage }}%
                    </p>
                  </div>

                  <!-- Action Button -->
                  <div class="flex flex-col items-end gap-2 flex-shrink-0">
                    <span :class="['text-[11px] font-bold px-3 py-1 rounded-full border', res.status === 'Passed' ? 'text-blue-600 bg-blue-50 border-blue-200' : 'text-rose-600 bg-rose-50 border-rose-200']">
                      {{ res.status || 'Completed' }}
                    </span>
                    <button
                      @click="openResultReview(res)"
                      class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-200/80 text-indigo-600 text-xs font-bold py-2.5 px-6 rounded-2xl transition-all duration-200"
                    >
                      View Results
                    </button>
                  </div>
                </div>
              </div>

              <!-- Completed Empty State -->
              <div v-else class="p-6 text-center bg-slate-50/50 rounded-2xl border border-slate-100">
                <p class="text-xs font-semibold text-slate-500">No completed exams yet.</p>
                <p class="text-[11px] text-slate-400 mt-1">Once you complete an exam, your score sheets will appear here.</p>
              </div>
            </div>

            <!-- ── Section 4: Quick Actions Row ── -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 space-y-4">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-2xl bg-purple-50 border border-purple-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                </div>
                <h2 class="text-base font-bold text-slate-900">Quick Actions</h2>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <!-- 1. Take Exam -->
                <div
                  @click="activeFilter = 'todays'"
                  class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-indigo-50/60 hover:border-indigo-100 transition-all cursor-pointer group"
                >
                  <div class="w-8 h-8 rounded-xl bg-purple-100/80 text-purple-600 flex items-center justify-center mb-2.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                  </div>
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-900">Take Exam</p>
                    <span class="text-slate-400 group-hover:translate-x-0.5 transition-transform">›</span>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-0.5">Start available exam</p>
                </div>

                <!-- 2. View Results -->
                <div
                  @click="activeFilter = 'completed'"
                  class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-indigo-50/60 hover:border-indigo-100 transition-all cursor-pointer group"
                >
                  <div class="w-8 h-8 rounded-xl bg-emerald-100/80 text-emerald-600 flex items-center justify-center mb-2.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-900">View Results</p>
                    <span class="text-slate-400 group-hover:translate-x-0.5 transition-transform">›</span>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-0.5">Check latest results</p>
                </div>

                <!-- 3. Exam Guidelines -->
                <div
                  @click="windowRef.alert('ACADEMIC INTEGRITY & EXAM GUIDELINES:\n1. Ensure a stable internet connection.\n2. Enable your webcam for proctoring.\n3. Do not switch browser tabs or windows.\n4. Complete all questions within the allocated time.')"
                  class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-indigo-50/60 hover:border-indigo-100 transition-all cursor-pointer group"
                >
                  <div class="w-8 h-8 rounded-xl bg-amber-100/80 text-amber-600 flex items-center justify-center mb-2.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  </div>
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-900">Exam Guidelines</p>
                    <span class="text-slate-400 group-hover:translate-x-0.5 transition-transform">›</span>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-0.5">Read instructions</p>
                </div>

                <!-- 4. My Schedule -->
                <div
                  @click="activeFilter = 'todays'"
                  class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-indigo-50/60 hover:border-indigo-100 transition-all cursor-pointer group"
                >
                  <div class="w-8 h-8 rounded-xl bg-indigo-100/80 text-indigo-600 flex items-center justify-center mb-2.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-900">My Schedule</p>
                    <span class="text-slate-400 group-hover:translate-x-0.5 transition-transform">›</span>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-0.5">View exam schedule</p>
                </div>

                <!-- 5. Calculator -->
                <div
                  @click="windowRef.alert('Scientific Calculator tool available inside the active exam console window.')"
                  class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-indigo-50/60 hover:border-indigo-100 transition-all cursor-pointer group"
                >
                  <div class="w-8 h-8 rounded-xl bg-blue-100/80 text-blue-600 flex items-center justify-center mb-2.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                  </div>
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-900">Calculator</p>
                    <span class="text-slate-400 group-hover:translate-x-0.5 transition-transform">›</span>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-0.5">Open calculator</p>
                </div>
              </div>
            </div>

          </template>

        </div>

        <!-- ── Right Sidebar Column ── -->
        <div class="w-full lg:w-72 flex-shrink-0 space-y-5">

          <!-- 1. Exam Summary Card -->
          <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 space-y-4">
            <h3 class="text-sm font-bold text-slate-900">Exam Summary</h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between p-3 rounded-2xl bg-purple-50/60 border border-purple-100/80">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-xl bg-purple-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                  <span class="text-xs font-semibold text-slate-700">Today's Exams</span>
                </div>
                <span class="text-base font-extrabold text-slate-900">{{ summaryStats.todays }}</span>
              </div>

              <div class="flex items-center justify-between p-3 rounded-2xl bg-emerald-50/60 border border-emerald-100/80">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-xl bg-emerald-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </div>
                  <span class="text-xs font-semibold text-slate-700">Ongoing Exams</span>
                </div>
                <span class="text-base font-extrabold text-slate-900">{{ summaryStats.ongoing }}</span>
              </div>

              <div class="flex items-center justify-between p-3 rounded-2xl bg-blue-50/60 border border-blue-100/80">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-xl bg-blue-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                  <span class="text-xs font-semibold text-slate-700">Completed Exams</span>
                </div>
                <span class="text-base font-extrabold text-slate-900">{{ summaryStats.completed }}</span>
              </div>

              <div class="flex items-center justify-between p-3 rounded-2xl bg-amber-50/60 border border-amber-100/80">
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-xl bg-amber-100 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                  </div>
                  <span class="text-xs font-semibold text-slate-700">Total Exams</span>
                </div>
                <span class="text-base font-extrabold text-slate-900">{{ summaryStats.total }}</span>
              </div>
            </div>
          </div>

          <!-- 2. Calendar Card -->
          <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-bold text-slate-900">Calendar</h3>
              <div class="flex items-center gap-1">
                <button @click="calMonth > 0 ? calMonth-- : (calMonth = 11, calYear--)" class="w-6 h-6 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-700 transition-colors">
                  ‹
                </button>
                <span class="text-xs font-bold text-slate-700 min-w-[90px] text-center">{{ calMonthName }}</span>
                <button @click="calMonth < 11 ? calMonth++ : (calMonth = 0, calYear++)" class="w-6 h-6 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-700 transition-colors">
                  ›
                </button>
              </div>
            </div>
            <div class="grid grid-cols-7 text-center">
              <div v-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d" class="text-[10px] font-bold text-slate-400 py-1">{{ d }}</div>
              <div v-for="(day, i) in calDays" :key="i"
                :class="['text-[11px] py-1 rounded-xl cursor-pointer transition-colors',
                  day ? (isToday(day) ? 'bg-indigo-50 text-indigo-600 font-bold border border-indigo-200' : 'text-slate-600 hover:bg-indigo-50/50') : '']">
                {{ day || '' }}
              </div>
            </div>
            <div class="flex flex-wrap gap-2.5 pt-3 border-t border-slate-100 text-[10px] text-slate-500">
              <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-purple-500"></span>Exam</div>
              <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Ongoing</div>
              <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-500"></span>Deadline</div>
              <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span>Event</div>
            </div>
          </div>

          <!-- 3. Quick Tips Card -->
          <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 space-y-3.5">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
              <h3 class="text-sm font-bold text-slate-900">Quick Tips</h3>
            </div>
            <ul class="space-y-2 text-xs text-slate-600">
              <li v-for="tip in quickTips" :key="tip" class="flex items-start gap-2">
                <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>{{ tip }}</span>
              </li>
            </ul>
            <button
              @click="windowRef.alert('ACADEMIC INTEGRITY & EXAM GUIDELINES:\n1. Ensure a stable internet connection.\n2. Enable your webcam for proctoring.\n3. Do not switch browser tabs or windows.\n4. Complete all questions within the allocated time.')"
              class="w-full mt-2 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200/80 text-indigo-600 text-xs font-bold py-2.5 px-4 rounded-2xl transition-all duration-200"
            >
              View Exam Guidelines
            </button>
          </div>

        </div>

      </div>
    </main>
  </div>
</template>
