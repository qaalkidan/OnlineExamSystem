<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { sampleAnnouncements, sampleCalendarEvents } from '../data/mockData'
import type { StudentProfile, UpcomingExam, RecentResult } from '../types'
import { useStudentExamStore } from '../store/studentExamStore'
import { useStudentProfile } from '../composables/useStudentProfile'

// Component imports
import Header from '../components/Header.vue'
import HeroSection from '../components/HeroSection.vue'
import UpcomingExams from '../components/UpcomingExams.vue'
import ProgressStats from '../components/ProgressStats.vue'
import RecentResults from '../components/RecentResults.vue'
import Announcements from '../components/Announcements.vue'
import CalendarSection from '../components/CalendarSection.vue'
import QuickActions from '../components/QuickActions.vue'
import ResultReviewModal from '../components/ResultReviewModal.vue'
import DownloadTranscriptModal from '../components/DownloadTranscriptModal.vue'
import ProfileModal from '../components/ProfileModal.vue'
import StudentSidebar from '../components/StudentSidebar.vue'
import TodayExamCard from '../components/TodayExamCard.vue'

// Store & Router
const examStore = useStudentExamStore()
const router = useRouter()

// Real profile from auth store / backend
const { profile, fetchProfile } = useStudentProfile()

// Connect to store data via computed refs for reactivity
const upcomingExams = computed(() => examStore.upcomingExams)
const results = computed(() => examStore.results)
const activeExam = computed(() => examStore.activeExam)

// Overlay controller states
const selectedResultForReview = ref<RecentResult | null>(null)
const isNotificationsOpen = ref<boolean>(false)
const windowRef = window
const isTranscriptOpen = ref<boolean>(false)
const isProfileOpen = ref<boolean>(false)
const upcomingGuidelines = ref<UpcomingExam | null>(null)
const isSidebarOpen = ref<boolean>(false)

// Reactive timer for Dashboard & real-time polling
const currentTime = ref(Date.now())
let dashboardTimer: number | null = null
let pollCounter = 0

const handleTabFocus = async () => {
  if (!document.hidden) {
    await Promise.all([
      examStore.fetchExams(true),
      examStore.fetchDashboard()
    ])
  }
}

onMounted(async () => {
  dashboardTimer = window.setInterval(async () => {
    currentTime.value = Date.now()
    pollCounter++
    // Poll for new/updated exams every 5 seconds silently in the background
    if (pollCounter >= 5) {
      pollCounter = 0
      if (!document.hidden) {
        await Promise.all([
          examStore.fetchExams(true),
          examStore.fetchDashboard()
        ])
      }
    }
  }, 1000)

  window.addEventListener('focus', handleTabFocus)
  document.addEventListener('visibilitychange', handleTabFocus)

  await Promise.all([
    fetchProfile(),           // ← real user data from backend
    examStore.fetchExams(),
    examStore.fetchResults(),
    examStore.fetchDashboard(),
  ])
})

import { onUnmounted } from 'vue'
onUnmounted(() => {
  if (dashboardTimer) {
    clearInterval(dashboardTimer)
    dashboardTimer = null
  }
  window.removeEventListener('focus', handleTabFocus)
  document.removeEventListener('visibilitychange', handleTabFocus)
})

// Today's Exam Logic — show ready/ongoing window and exclude already submitted exams
const todayExams = computed(() => {
  if (upcomingExams.value.length === 0) return []
  const now = currentTime.value
  const TEN_MIN = 10 * 60 * 1000

  // Find all exams that are in the ready/ongoing window and not completed
  return upcomingExams.value.filter(exam => {
    // 1. Exclude already completed/submitted attempts
    if (exam.attemptStatus === 'submitted' || exam.attemptStatus === 'graded' || exam.attemptStatus === 'published' || (exam as any).submitted_at) {
      return false
    }

    // 2. If in_progress or explicitly marked Ready by backend, show it immediately
    if (exam.attemptStatus === 'in_progress' || exam.status === 'Ready') {
      return true
    }

    // 3. Check scheduled time window
    const rawDate = (exam as any).scheduledAt || exam.scheduledDate
    if (!rawDate) return true // Published exam without strict schedule is ready now!

    const startMs = new Date(rawDate).getTime()
    const endMs = startMs + (exam.durationMinutes * 60 * 1000)
    // Show card 10 minutes before start until exam ends
    return now >= startMs - TEN_MIN && now < endMs
  })
})

const filteredUpcomingExams = computed(() => {
  const nonSubmitted = upcomingExams.value.filter(exam => 
    !['submitted', 'graded', 'published'].includes(exam.attemptStatus as string)
  )
  if (todayExams.value.length === 0) return nonSubmitted
  const todayIds = new Set(todayExams.value.map(e => e.id))
  return nonSubmitted.filter(exam => !todayIds.has(exam.id))
})

// Stats calculation
const completedCount = computed(() => results.value.length)
const remainingCount = computed(() => upcomingExams.value.length + (activeExam.value ? 1 : 0))

const averageScore = computed(() => {
  if (results.value.length === 0) return 0
  return Math.round(results.value.reduce((acc, curr) => acc + curr.percentage, 0) / results.value.length)
})
const passRate = 100

// Dynamic Gamification
const encouragementData = computed(() => {
  if (averageScore.value >= 85) return { title: "You're Doing<br>Great!", subtitle: "Keep up the excellent work and achieve your goals.", emoji: "🏆" }
  if (averageScore.value >= 70) return { title: "Keep<br>Pushing!", subtitle: "You are on the right track, keep up the effort.", emoji: "🚀" }
  if (averageScore.value > 0) return { title: "You Can<br>Do It!", subtitle: "Don't give up, keep studying to improve your scores.", emoji: "💪" }
  return { title: "Welcome<br>Aboard!", subtitle: "Complete your first exam to see your progress here.", emoji: "👋" }
})

// Real data is fetched in the timer onMounted hook above

// Action helper when the student starts an exam from the upcoming list
const handleStartUpcomingExam = async (examId: number) => {
  try {
    await examStore.startExam(examId)
    router.push('/student/exam/take')
  } catch (err: any) {
    await Promise.all([
      examStore.fetchExams(true),
      examStore.fetchDashboard()
    ])
    windowRef.alert(err.message || 'Failed to start exam')
  }
}

// Quick Action Click Router
const handleQuickAction = (actionKey: 'take-exam' | 'view-results' | 'download-results' | 'schedule' | 'academic-calendar' | 'update-profile') => {
  switch (actionKey) {
    case 'take-exam':
      if (activeExam.value) {
        router.push('/student/exam/take')
      } else if (upcomingExams.value.length > 0) {
        handleStartUpcomingExam(upcomingExams.value[0].id)
      } else {
        windowRef.alert("All current scheduled examinations have been completed. Please check back next week.")
      }
      break
    case 'view-results':
      router.push('/student/results')
      break
    case 'download-results':
      isTranscriptOpen.value = true
      break
    case 'schedule':
      document.getElementById('upcoming-exams-section')?.scrollIntoView({ behavior: 'smooth' })
      break
    case 'academic-calendar':
      document.getElementById('calendar-section')?.scrollIntoView({ behavior: 'smooth' })
      break
    case 'update-profile':
      router.push('/student/profile')
      break
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#f8f9fc] font-sans text-slate-800 antialiased selection:bg-indigo-500 selection:text-white flex flex-col">
    
    <!-- Top Navigation Header -->
    <Header
      :profile="profile"
      :announcements="sampleAnnouncements"
      @open-profile="isProfileOpen = true"
      @open-notifications="isNotificationsOpen = true"
      @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
    />

    <!-- Off-Canvas Sidebar Drawer (Displays only when ≡ is clicked) -->
    <StudentSidebar 
      :profile="profile" 
      :isOpen="isSidebarOpen" 
      @close="isSidebarOpen = false" 
    />

    <!-- Row 1: Full-Width Hero Banner (Placed flush right under header, no top/side margins) -->
    <HeroSection 
      :profile="profile" 
      :stats="{ examsCompleted: completedCount, upcomingExams: upcomingExams.length }" 
    />

    <!-- Main Content Body (Padded content below full-width hero section) -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 animate-in fade-in duration-300">

      <!-- Row 2: Quick Action Buttons Grid -->
      <QuickActions @action="handleQuickAction" />

      <!-- Today's Exam Card(s) (Dynamically displayed if exams are ready/ongoing) -->
      <div v-if="todayExams.length > 0" class="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
        <TodayExamCard 
          v-for="readyExam in todayExams"
          :key="readyExam.id"
          :exam="readyExam" 
          @start-exam="handleStartUpcomingExam" 
        />
      </div>

      <!-- Row 3: 3-Column Grid (Upcoming Exams, Academic Calendar, Academic Progress) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
        <!-- Col 1: Upcoming Exams -->
        <div id="upcoming-exams-section" class="h-full">
          <UpcomingExams
            :exams="filteredUpcomingExams"
            @start-exam="handleStartUpcomingExam"
            @view-details="(exam) => upcomingGuidelines = exam"
          />
        </div>

        <!-- Col 2: Academic Calendar -->
        <div id="calendar-section" class="h-full">
          <CalendarSection :events="sampleCalendarEvents" />
        </div>

        <!-- Col 3: Academic Progress -->
        <div class="h-full md:col-span-2 lg:col-span-1">
          <ProgressStats
            :profile="profile"
            :completedCount="completedCount"
            :remainingCount="remainingCount"
            :averageScore="averageScore"
            :passRate="passRate"
          />
        </div>
      </div>

      <!-- Row 4: 4-Column Bottom Grid (Latest Results, Official Notices, Need Help, Gamification) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-stretch">

        <!-- Col 1: Latest Results -->
        <div id="recent-results-section" class="h-full">
          <RecentResults
            :results="results"
            @view-result="(res) => selectedResultForReview = res"
            @download-transcript="isTranscriptOpen = true"
          />
        </div>
        
        <!-- Col 2: Official Notices -->
        <div class="h-full">
          <Announcements :announcements="sampleAnnouncements" />
        </div>

        <!-- Col 3: Need Help? — Light flat card, full height -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 sm:p-6 shadow-sm h-full flex flex-col">
          <h3 class="text-base font-bold text-slate-900">Need Help?</h3>
          <p class="text-xs text-slate-500 mt-0.5">We're here to support you</p>

          <div class="mt-4 sm:mt-5 space-y-3 flex-1 overflow-y-auto">
            <!-- Help Center Item -->
            <div class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer min-h-[44px]">
              <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-[13px] font-bold text-slate-900">Help Center</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Find answers to common questions</p>
              </div>
            </div>

            <!-- Contact Support Item -->
            <div class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer min-h-[44px]">
              <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
              </div>
              <div>
                <p class="text-[13px] font-bold text-slate-900">Contact Support</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Reach our support team directly</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Col 4: You're Doing Great — Trophy image on right, text on left -->
        <div class="bg-slate-50 rounded-2xl border border-slate-100 p-5 sm:p-6 lg:p-7 shadow-sm relative overflow-hidden h-full flex flex-col justify-between">
          <!-- Trophy image on the right -->
          <div class="absolute right-2 top-1/2 -translate-y-1/2 opacity-30 sm:opacity-50 lg:opacity-100 pointer-events-none">
            <span class="text-5xl sm:text-6xl select-none">{{ encouragementData.emoji }}</span>
          </div>

          <!-- Text on the left -->
          <div class="relative z-10 pr-12 sm:pr-14">
            <p class="text-sm font-black text-slate-900 leading-snug" v-html="encouragementData.title"></p>
            <p class="text-[11px] text-slate-500 mt-2 leading-relaxed">
              {{ encouragementData.subtitle }}
            </p>
            <button class="mt-4 w-full min-h-[44px] bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-2.5 px-4 rounded-xl transition-colors shadow-sm flex items-center justify-center">
              View Achievements
            </button>
          </div>
        </div>

      </div>

    </main>

    <!-- Global Modals / Drawers -->
    <ResultReviewModal
      v-if="selectedResultForReview"
      :result="selectedResultForReview"
      @close="selectedResultForReview = null"
    />

    <DownloadTranscriptModal
      v-if="isTranscriptOpen"
      :profile="profile"
      :results="results"
      @close="isTranscriptOpen = false"
    />

    <ProfileModal
      v-if="isProfileOpen"
      :profile="profile"
      @close="isProfileOpen = false"
      @update-profile="(updated) => {
        profile = updated;
        windowRef.alert('SUCCESS:\nYour registry credentials have been updated successfully.');
      }"
    />

  </div>
</template>
