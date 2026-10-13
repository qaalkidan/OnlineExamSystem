<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { sampleAnnouncements } from '../data/mockData'
import type { RecentResult } from '../types'
import { useStudentExamStore } from '../store/studentExamStore'
import { useStudentProfile } from '../composables/useStudentProfile'

import Header from '../components/Header.vue'
import HeroSection from '../components/HeroSection.vue'
import StudentSidebar from '../components/StudentSidebar.vue'
import ProfileModal from '../components/ProfileModal.vue'
import ResultReviewModal from '../components/ResultReviewModal.vue'
import DownloadTranscriptModal from '../components/DownloadTranscriptModal.vue'

import {
  Search,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Eye,
  Download,
  Printer,
  BookOpen,
  Award,
  GraduationCap,
  Calendar,
  ChevronRight,
  ChevronLeft,
  ChevronsLeft,
  ChevronsRight,
  RotateCcw,
  FileText,
  Sparkles,
  SlidersHorizontal,
  X,
  TrendingUp,
  Layers,
  ArrowUpDown,
  ExternalLink,
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const examStore = useStudentExamStore()
const { profile, fetchProfile } = useStudentProfile()

// UI state
const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)
const isTranscriptOpen = ref(false)
const isResultModalOpen = ref(false)
const selectedResult = ref<RecentResult | null>(null)
const isRefreshing = ref(false)

// Real store state
const results = computed(() => examStore.results)
const isLoading = computed(() => examStore.isLoading)
const upcomingExams = computed(() => examStore.upcomingExams)

// Filters & Controls
const searchQuery = ref(String(route.query.search || ''))
const selectedStatus = ref<'all' | 'Passed' | 'Failed' | 'Pending'>('all')
const selectedCourse = ref('All Courses')
const selectedGradeRange = ref('All Grades')
const sortBy = ref<'date-desc' | 'date-asc' | 'score-desc' | 'score-asc' | 'course-asc'>('date-desc')

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(10)

onMounted(async () => {
  if (route.query.search) {
    searchQuery.value = String(route.query.search)
  }
  await Promise.all([
    fetchProfile(),
    examStore.fetchResults(),
    examStore.fetchExams(),
  ])
})

watch(() => route.query.search, (val) => {
  if (val !== undefined) {
    searchQuery.value = String(val || '')
  }
})

// Reset pagination on filter change
watch([searchQuery, selectedStatus, selectedCourse, selectedGradeRange, sortBy], () => {
  currentPage.value = 1
})

// Available course list for dropdown filter
const availableCourses = computed(() => {
  const map = new Map<string, string>()
  results.value.forEach(r => {
    if (r.courseCode) {
      map.set(r.courseCode, r.courseName || r.courseCode)
    }
  })
  return Array.from(map.entries()).map(([code, name]) => ({ code, name }))
})

// Summary metrics (purely calculated from real data)
const passedCount = computed(() => results.value.filter(r => r.status === 'Passed').length)
const failedCount = computed(() => results.value.filter(r => r.status === 'Failed').length)
const pendingCount = computed(() => results.value.filter(r => r.status === 'Pending').length)

const averageScore = computed(() => {
  if (results.value.length === 0) return '0.0'
  const total = results.value.reduce((sum, r) => sum + Number(r.percentage || 0), 0)
  return (total / results.value.length).toFixed(1)
})

const passRate = computed(() => {
  if (results.value.length === 0) return 0
  return Math.round((passedCount.value / results.value.length) * 100)
})

// Filtered and sorted results
const filteredResults = computed(() => {
  let list = [...results.value]

  // Status filter
  if (selectedStatus.value !== 'all') {
    list = list.filter(r => r.status === selectedStatus.value)
  }

  // Course filter
  if (selectedCourse.value !== 'All Courses') {
    list = list.filter(r => r.courseCode === selectedCourse.value || r.courseName === selectedCourse.value)
  }

  // Grade range filter
  if (selectedGradeRange.value !== 'All Grades') {
    if (selectedGradeRange.value === 'A Range') {
      list = list.filter(r => (r.grade || '').toUpperCase().startsWith('A'))
    } else if (selectedGradeRange.value === 'B Range') {
      list = list.filter(r => (r.grade || '').toUpperCase().startsWith('B'))
    } else if (selectedGradeRange.value === 'C/D Range') {
      list = list.filter(r => {
        const g = (r.grade || '').toUpperCase()
        return g.startsWith('C') || g.startsWith('D')
      })
    } else if (selectedGradeRange.value === 'F Range') {
      list = list.filter(r => (r.grade || '').toUpperCase().startsWith('F'))
    }
  }

  // Search filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(r =>
      (r.courseCode && r.courseCode.toLowerCase().includes(q)) ||
      (r.courseName && r.courseName.toLowerCase().includes(q)) ||
      (r.examTitle && r.examTitle.toLowerCase().includes(q)) ||
      (r.examType && r.examType.toLowerCase().includes(q)) ||
      (r.grade && r.grade.toLowerCase().includes(q))
    )
  }

  // Sorting
  list.sort((a, b) => {
    if (sortBy.value === 'score-desc') {
      return Number(b.percentage || 0) - Number(a.percentage || 0)
    }
    if (sortBy.value === 'score-asc') {
      return Number(a.percentage || 0) - Number(b.percentage || 0)
    }
    if (sortBy.value === 'course-asc') {
      return (a.courseCode || '').localeCompare(b.courseCode || '')
    }
    if (sortBy.value === 'date-asc') {
      return new Date(a.completedDate || 0).getTime() - new Date(b.completedDate || 0).getTime()
    }
    // Default date-desc (id-desc)
    return b.id - a.id
  })

  return list
})

// Paginated view
const totalPages = computed(() => {
  if (itemsPerPage.value === -1) return 1
  return Math.ceil(filteredResults.value.length / itemsPerPage.value) || 1
})

const paginatedResults = computed(() => {
  if (itemsPerPage.value === -1) return filteredResults.value
  const start = (currentPage.value - 1) * itemsPerPage.value
  return filteredResults.value.slice(start, start + itemsPerPage.value)
})

// Actions
const openResultDetails = (res: RecentResult) => {
  router.push(`/student/results/${res.id}`)
}

const openQuickReview = (res: RecentResult) => {
  selectedResult.value = res
  isResultModalOpen.value = true
}

const handleRefresh = async () => {
  isRefreshing.value = true
  try {
    await Promise.all([
      fetchProfile(),
      examStore.fetchResults(),
      examStore.fetchExams(),
    ])
  } finally {
    setTimeout(() => {
      isRefreshing.value = false
    }, 400)
  }
}

const handlePrintPage = () => {
  window.print()
}

const resetAllFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = 'all'
  selectedCourse.value = 'All Courses'
  selectedGradeRange.value = 'All Grades'
  sortBy.value = 'date-desc'
  currentPage.value = 1
}

// Visual helpers
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
  if (g.startsWith('A')) {
    return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  }
  if (g.startsWith('B')) {
    return 'bg-[#EEF0FF] text-[#4F35F3] border-[#D6DBFF]'
  }
  if (g.startsWith('C') || g.startsWith('D')) {
    return 'bg-amber-50 text-amber-700 border-amber-200'
  }
  if (g === 'PENDING') {
    return 'bg-purple-50 text-purple-700 border-purple-200'
  }
  return 'bg-rose-50 text-rose-700 border-rose-200'
}

const getStatusBadge = (status: string) => {
  const s = (status || '').toLowerCase()
  if (s === 'passed') {
    return {
      dot: 'bg-emerald-500',
      pill: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      label: 'Passed',
    }
  }
  if (s === 'pending') {
    return {
      dot: 'bg-amber-500 animate-pulse',
      pill: 'bg-amber-50 text-amber-700 border-amber-200',
      label: 'Pending',
    }
  }
  return {
    dot: 'bg-rose-500',
    pill: 'bg-rose-50 text-rose-700 border-rose-200',
    label: 'Failed',
  }
}

const getCourseInitials = (code: string) => {
  if (!code) return 'EX'
  const clean = code.replace(/[^a-zA-Z0-9]/g, '').slice(0, 3)
  return clean.toUpperCase() || 'EX'
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

    <!-- Result Quick Review Modal -->
    <ResultReviewModal
      v-if="isResultModalOpen && selectedResult"
      :result="selectedResult"
      @close="isResultModalOpen = false"
    />

    <!-- Official Download / Print Transcript Modal -->
    <DownloadTranscriptModal
      v-if="isTranscriptOpen"
      :profile="profile"
      :results="results"
      @close="isTranscriptOpen = false"
    />

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- PROTECTED HERO SECTION (Structure & Background Kept 100% Intact) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <HeroSection
      :profile="profile"
      :stats="{ examsCompleted: results.length, upcomingExams: upcomingExams.length }"
      subtitle="ACADEMIC RECORDS"
      title="RESULTS"
      description="View your past exam performance, check your grades, and download your academic transcripts."
    />

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- MAIN CONTENT BODY (Below the Hero Section) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-5 sm:py-6 space-y-5 animate-in fade-in duration-200">

      <!-- ── Section Header & Actions Toolbar ── -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 sm:p-5 rounded-2xl border border-[#E5EAF3] shadow-2xs">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-[#71819B] mb-1">
            <span class="inline-flex items-center gap-1.5 text-[#4F35F3] font-bold">
              <GraduationCap :size="14" class="stroke-[2.5]" />
              Academic Records
            </span>
            <span>•</span>
            <span>Published Examination Results</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-[#17243A] tracking-tight flex items-center gap-2">
            My Examination Results
            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-[#EEF0FF] text-[#4F35F3] border border-[#D6DBFF]">
              {{ results.length }} Total Record{{ results.length === 1 ? '' : 's' }}
            </span>
          </h1>
          <p class="text-xs sm:text-sm text-[#71819B] mt-0.5">
            Review detailed grade breakdowns, score percentages, and download official university transcripts.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap shrink-0 no-print">
          <button
            @click="handleRefresh"
            :disabled="isRefreshing"
            class="px-3.5 py-2 rounded-xl text-xs font-bold text-[#71819B] hover:text-[#17243A] bg-[#F5F7FC] hover:bg-[#EEF0FF] border border-[#E5EAF3] transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            title="Refresh results"
          >
            <RotateCcw :size="14" :class="['stroke-[2.5]', isRefreshing ? 'animate-spin text-[#4F35F3]' : '']" />
            <span class="hidden sm:inline">Sync Records</span>
          </button>

          <button
            @click="handlePrintPage"
            class="px-3.5 py-2 rounded-xl text-xs font-bold text-[#71819B] hover:text-[#17243A] bg-[#F5F7FC] hover:bg-slate-100 border border-[#E5EAF3] transition-all flex items-center gap-1.5 cursor-pointer"
            title="Print this results sheet"
          >
            <Printer :size="14" class="stroke-[2.5]" />
            <span class="hidden sm:inline">Print Slip</span>
          </button>

          <button
            @click="isTranscriptOpen = true"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#4F35F3] hover:bg-[#3D25D8] shadow-xs hover:shadow-sm transition-all flex items-center gap-2 cursor-pointer"
          >
            <Download :size="14" class="stroke-[2.5]" />
            <span>Official Transcript</span>
          </button>
        </div>
      </div>

      <!-- ── Academic Performance Summary Cards (KPIs) ── -->
      <section aria-label="Academic Summary" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">

        <!-- Card 1: Total Completed Exams -->
        <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4 sm:p-5 shadow-2xs hover:shadow-xs transition-all relative overflow-hidden group">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-[#71819B]">Completed Exams</p>
              <div class="flex items-baseline gap-2 mt-1.5">
                <span class="text-2xl sm:text-3xl font-black font-mono text-[#17243A] tracking-tight">
                  {{ results.length }}
                </span>
                <span class="text-xs font-semibold text-[#71819B]">Assessments</span>
              </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 border border-[#D6DBFF]/60 group-hover:scale-105 transition-transform">
              <BookOpen :size="18" class="stroke-[2.5]" />
            </div>
          </div>
          <div class="mt-3.5 pt-3 border-t border-[#F0F3F8] flex items-center justify-between text-[11px]">
            <span class="text-[#71819B]">Published records</span>
            <span class="font-bold text-[#4F35F3] bg-[#EEF0FF] px-2 py-0.5 rounded-full">
              {{ results.length }} Logged
            </span>
          </div>
        </div>

        <!-- Card 2: Cumulative Average Score -->
        <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4 sm:p-5 shadow-2xs hover:shadow-xs transition-all relative overflow-hidden group">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-[#71819B]">Average Score</p>
              <div class="flex items-baseline gap-2 mt-1.5">
                <span :class="['text-2xl sm:text-3xl font-black font-mono tracking-tight', getScoreColor(Number(averageScore))]">
                  {{ averageScore }}%
                </span>
              </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#3295FF] flex items-center justify-center shrink-0 border border-blue-100 group-hover:scale-105 transition-transform">
              <TrendingUp :size="18" class="stroke-[2.5]" />
            </div>
          </div>
          <div class="mt-3.5 pt-3 border-t border-[#F0F3F8] flex items-center justify-between text-[11px]">
            <span class="text-[#71819B]">Across all assessments</span>
            <span :class="['font-bold px-2 py-0.5 rounded-full text-[10px]', Number(averageScore) >= 50 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700']">
              {{ Number(averageScore) >= 50 ? 'Passing Avg' : 'Below 50%' }}
            </span>
          </div>
        </div>

        <!-- Card 3: Pass Rate & Passed Assessments -->
        <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4 sm:p-5 shadow-2xs hover:shadow-xs transition-all relative overflow-hidden group">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-[#71819B]">Passing Rate</p>
              <div class="flex items-baseline gap-2 mt-1.5">
                <span class="text-2xl sm:text-3xl font-black font-mono text-[#10B981] tracking-tight">
                  {{ passRate }}%
                </span>
                <span class="text-xs font-semibold text-[#71819B]">
                  ({{ passedCount }}/{{ results.length }})
                </span>
              </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#10B981] flex items-center justify-center shrink-0 border border-emerald-100 group-hover:scale-105 transition-transform">
              <CheckCircle2 :size="18" class="stroke-[2.5]" />
            </div>
          </div>
          <div class="mt-3.5 pt-3 border-t border-[#F0F3F8] flex items-center justify-between text-[11px]">
            <span class="text-[#71819B]">Outcomes</span>
            <span class="font-bold text-slate-700">
              <span class="text-emerald-600 font-extrabold">{{ passedCount }} Pass</span>
              <span class="text-slate-300 mx-1">•</span>
              <span class="text-rose-600 font-extrabold">{{ failedCount }} Fail</span>
            </span>
          </div>
        </div>

        <!-- Card 4: Academic Standing & Cumulative GPA -->
        <div class="bg-white rounded-2xl border border-[#E5EAF3] p-4 sm:p-5 shadow-2xs hover:shadow-xs transition-all relative overflow-hidden group">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-[#71819B]">Academic Standing</p>
              <div class="flex items-baseline gap-1.5 mt-1.5">
                <span class="text-xl sm:text-2xl font-black text-[#17243A] tracking-tight truncate max-w-[150px]">
                  {{ profile.cgpa > 0 ? profile.cgpa.toFixed(2) + ' CGPA' : (passRate >= 70 ? 'Good Standing' : 'Active Status') }}
                </span>
              </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 border border-purple-100 group-hover:scale-105 transition-transform">
              <Award :size="18" class="stroke-[2.5]" />
            </div>
          </div>
          <div class="mt-3.5 pt-3 border-t border-[#F0F3F8] flex items-center justify-between text-[11px]">
            <span class="text-[#71819B] truncate max-w-[120px]">{{ profile.department || 'Informatics' }}</span>
            <span class="font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full">
              {{ profile.yearLevel || '1st Year' }}
            </span>
          </div>
        </div>

      </section>

      <!-- ── Results Main Card Container ── -->
      <div class="bg-white rounded-2xl border border-[#E5EAF3] shadow-2xs overflow-hidden">

        <!-- ── Filter & Search Toolbar ── -->
        <div class="p-4 sm:p-5 border-b border-[#E5EAF3] space-y-3.5 bg-[#FAFBFD]/60">

          <!-- Top Row: Status Tabs & Quick Counts -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            
            <!-- Segmented Status Tabs -->
            <div class="inline-flex items-center bg-[#F0F3F8] p-1 rounded-xl gap-1 max-w-full overflow-x-auto no-scrollbar">
              <button
                @click="selectedStatus = 'all'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5',
                  selectedStatus === 'all'
                    ? 'bg-white text-[#4F35F3] shadow-2xs border border-[#E5EAF3]'
                    : 'text-[#71819B] hover:text-[#17243A]'
                ]"
              >
                <span>All Results</span>
                <span :class="['px-1.5 py-0.2 rounded-full text-[10px] font-mono', selectedStatus === 'all' ? 'bg-[#EEF0FF] text-[#4F35F3]' : 'bg-slate-200 text-slate-600']">
                  {{ results.length }}
                </span>
              </button>

              <button
                @click="selectedStatus = 'Passed'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5',
                  selectedStatus === 'Passed'
                    ? 'bg-white text-emerald-700 shadow-2xs border border-emerald-200'
                    : 'text-[#71819B] hover:text-[#17243A]'
                ]"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Passed</span>
                <span :class="['px-1.5 py-0.2 rounded-full text-[10px] font-mono', selectedStatus === 'Passed' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600']">
                  {{ passedCount }}
                </span>
              </button>

              <button
                @click="selectedStatus = 'Failed'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5',
                  selectedStatus === 'Failed'
                    ? 'bg-white text-rose-700 shadow-2xs border border-rose-200'
                    : 'text-[#71819B] hover:text-[#17243A]'
                ]"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                <span>Failed</span>
                <span :class="['px-1.5 py-0.2 rounded-full text-[10px] font-mono', selectedStatus === 'Failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-200 text-slate-600']">
                  {{ failedCount }}
                </span>
              </button>

              <button
                v-if="pendingCount > 0"
                @click="selectedStatus = 'Pending'"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5',
                  selectedStatus === 'Pending'
                    ? 'bg-white text-amber-700 shadow-2xs border border-amber-200'
                    : 'text-[#71819B] hover:text-[#17243A]'
                ]"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Pending</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-amber-100 text-amber-800">
                  {{ pendingCount }}
                </span>
              </button>
            </div>

            <!-- Total Filtered Status Info -->
            <div class="text-xs font-semibold text-[#71819B] flex items-center gap-2">
              <span>Showing <strong class="text-[#17243A]">{{ filteredResults.length }}</strong> of {{ results.length }} exam{{ results.length === 1 ? '' : 's' }}</span>
              <button
                v-if="searchQuery || selectedStatus !== 'all' || selectedCourse !== 'All Courses' || selectedGradeRange !== 'All Grades' || sortBy !== 'date-desc'"
                @click="resetAllFilters"
                class="text-[#4F35F3] hover:underline flex items-center gap-1 text-[11px] font-bold cursor-pointer"
              >
                <X :size="12" /> Reset filters
              </button>
            </div>

          </div>

          <!-- Bottom Row: Search Box & Dropdown Selectors -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">

            <!-- Search Input (5 cols on lg) -->
            <div class="lg:col-span-5 relative">
              <Search :size="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#71819B] stroke-[2.5]" />
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search course code, title, exam type..."
                class="w-full pl-9.5 pr-8 py-2 bg-white rounded-xl border border-[#E5EAF3] text-xs font-medium text-[#17243A] placeholder-[#71819B] focus:outline-none focus:border-[#4F35F3] focus:ring-2 focus:ring-[#EEF0FF] transition-all"
              />
              <button
                v-if="searchQuery"
                @click="searchQuery = ''"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5 rounded cursor-pointer"
              >
                <X :size="13" />
              </button>
            </div>

            <!-- Course Filter (3 cols on lg) -->
            <div class="lg:col-span-3">
              <select
                v-model="selectedCourse"
                class="w-full px-3 py-2 bg-white rounded-xl border border-[#E5EAF3] text-xs font-semibold text-[#17243A] focus:outline-none focus:border-[#4F35F3] focus:ring-2 focus:ring-[#EEF0FF] cursor-pointer"
              >
                <option value="All Courses">All Courses</option>
                <option
                  v-for="c in availableCourses"
                  :key="c.code"
                  :value="c.code"
                >
                  {{ c.code }} — {{ c.name }}
                </option>
              </select>
            </div>

            <!-- Grade Range Filter (2 cols on lg) -->
            <div class="lg:col-span-2">
              <select
                v-model="selectedGradeRange"
                class="w-full px-3 py-2 bg-white rounded-xl border border-[#E5EAF3] text-xs font-semibold text-[#17243A] focus:outline-none focus:border-[#4F35F3] focus:ring-2 focus:ring-[#EEF0FF] cursor-pointer"
              >
                <option value="All Grades">All Grades</option>
                <option value="A Range">A Range (A+, A, A-)</option>
                <option value="B Range">B Range (B+, B, B-)</option>
                <option value="C/D Range">C & D Grades</option>
                <option value="F Range">Failing (F)</option>
              </select>
            </div>

            <!-- Sort By (2 cols on lg) -->
            <div class="lg:col-span-2">
              <select
                v-model="sortBy"
                class="w-full px-3 py-2 bg-white rounded-xl border border-[#E5EAF3] text-xs font-semibold text-[#17243A] focus:outline-none focus:border-[#4F35F3] focus:ring-2 focus:ring-[#EEF0FF] cursor-pointer"
              >
                <option value="date-desc">Latest Date</option>
                <option value="date-asc">Oldest Date</option>
                <option value="score-desc">Highest Score</option>
                <option value="score-asc">Lowest Score</option>
                <option value="course-asc">Course Code A-Z</option>
              </select>
            </div>

          </div>

        </div>

        <!-- ── Content States ── -->

        <!-- 1. Loading State -->
        <div v-if="isLoading" class="p-8 sm:p-12 text-center space-y-4">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-[#EEF0FF] text-[#4F35F3] animate-spin">
            <RotateCcw :size="22" class="stroke-[2.5]" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-[#17243A]">Loading Examination Records...</h3>
            <p class="text-xs text-[#71819B] mt-1">Retrieving official grades and assessment scores from university server.</p>
          </div>
        </div>

        <!-- 2. Zero Results Logged (Empty State) -->
        <div v-else-if="results.length === 0" class="p-10 sm:p-16 text-center space-y-4">
          <div class="w-16 h-16 rounded-3xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center mx-auto border border-[#D6DBFF]">
            <BookOpen :size="28" class="stroke-[2]" />
          </div>
          <div class="max-w-md mx-auto">
            <h3 class="text-base font-black text-[#17243A]">No Examination Results Yet</h3>
            <p class="text-xs text-[#71819B] mt-1.5 leading-relaxed">
              You haven't completed any published examinations yet. As soon as you complete upcoming tests and scores are finalized, your records will appear here.
            </p>
          </div>
          <div class="pt-2">
            <button
              @click="router.push('/student/exams')"
              class="px-4 py-2.5 rounded-xl bg-[#4F35F3] hover:bg-[#3D25D8] text-white text-xs font-bold transition-all shadow-xs inline-flex items-center gap-2 cursor-pointer"
            >
              <Calendar :size="14" class="stroke-[2.5]" />
              <span>View Upcoming Examinations</span>
            </button>
          </div>
        </div>

        <!-- 3. Filter Produced Zero Matches -->
        <div v-else-if="filteredResults.length === 0" class="p-10 sm:p-14 text-center space-y-4">
          <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto border border-amber-100">
            <AlertCircle :size="24" class="stroke-[2]" />
          </div>
          <div class="max-w-md mx-auto">
            <h3 class="text-sm sm:text-base font-bold text-[#17243A]">No Matching Results Found</h3>
            <p class="text-xs text-[#71819B] mt-1">
              No exam results match your current filter criteria:
              <span v-if="searchQuery" class="font-semibold text-slate-800">"{{ searchQuery }}"</span>
            </p>
          </div>
          <div>
            <button
              @click="resetAllFilters"
              class="px-4 py-2 rounded-xl bg-[#F5F7FC] hover:bg-[#EEF0FF] text-[#4F35F3] border border-[#E5EAF3] text-xs font-bold transition-all inline-flex items-center gap-1.5 cursor-pointer"
            >
              <X :size="13" />
              <span>Clear All Filters</span>
            </button>
          </div>
        </div>

        <!-- 4. Active Results List -->
        <div v-else>

          <!-- ── Desktop / Tablet Table (>= md) ── -->
          <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
              <thead>
                <tr class="bg-[#FAFBFD] border-b border-[#E5EAF3] text-[11px] font-bold uppercase tracking-wider text-[#71819B]">
                  <th scope="col" class="py-3 px-4 w-12 text-center">#</th>
                  <th scope="col" class="py-3 px-4">Course</th>
                  <th scope="col" class="py-3 px-4">Exam Type</th>
                  <th scope="col" class="py-3 px-4">Date Completed</th>
                  <th scope="col" class="py-3 px-4">Score & Marks</th>
                  <th scope="col" class="py-3 px-4 text-center">Grade</th>
                  <th scope="col" class="py-3 px-4 text-center">Status</th>
                  <th scope="col" class="py-3 px-4 text-right pr-6">Action</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-[#F0F3F8] text-xs">
                <tr
                  v-for="(res, index) in paginatedResults"
                  :key="res.id"
                  class="hover:bg-[#F8FAFC] transition-colors group"
                >
                  <!-- # Index -->
                  <td class="py-3.5 px-4 text-center font-mono text-[#71819B] font-semibold text-[11px]">
                    {{ (currentPage - 1) * (itemsPerPage === -1 ? 0 : itemsPerPage) + index + 1 }}
                  </td>

                  <!-- Course Code & Title -->
                  <td class="py-3.5 px-4 min-w-[220px]">
                    <div class="flex items-center gap-3">
                      <!-- Course Abbr Box -->
                      <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] border border-[#D6DBFF]/60 flex items-center justify-center font-mono font-black text-[11px] shrink-0">
                        {{ getCourseInitials(res.courseCode) }}
                      </div>
                      <div class="min-w-0">
                        <div class="font-bold text-[#17243A] text-xs group-hover:text-[#4F35F3] transition-colors truncate">
                          {{ res.courseCode }}
                        </div>
                        <div class="text-[11px] text-[#71819B] truncate font-medium max-w-[240px]">
                          {{ res.courseName || res.courseCode }}
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Exam Type -->
                  <td class="py-3.5 px-4 font-semibold text-slate-700">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-[#F5F7FC] border border-[#E5EAF3] text-[11px] text-[#17243A]">
                      <FileText :size="12" class="text-[#71819B]" />
                      {{ res.examType || res.examTitle || 'Mid Exam' }}
                    </span>
                  </td>

                  <!-- Date Completed -->
                  <td class="py-3.5 px-4 text-[#71819B] font-medium text-[11px]">
                    <span class="inline-flex items-center gap-1.5">
                      <Calendar :size="12" class="text-slate-400" />
                      {{ res.completedDate || 'Recently' }}
                    </span>
                  </td>

                  <!-- Score & Mini Bar -->
                  <td class="py-3.5 px-4 min-w-[140px]">
                    <div>
                      <div class="flex items-baseline gap-1.5">
                        <span :class="['font-mono font-black text-sm', getScoreColor(res.percentage)]">
                          {{ Number(res.percentage || 0).toFixed(2) }}%
                        </span>
                        <span v-if="res.totalMarks" class="text-[10px] text-[#71819B] font-mono">
                          ({{ res.score }}/{{ res.totalMarks }} pts)
                        </span>
                      </div>
                      <!-- Mini visual progress indicator -->
                      <div class="w-24 h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden">
                        <div
                          :class="['h-full rounded-full transition-all duration-300', getScoreBarColor(res.percentage)]"
                          :style="{ width: `${Math.min(Math.max(Number(res.percentage || 0), 0), 100)}%` }"
                        ></div>
                      </div>
                    </div>
                  </td>

                  <!-- Grade Badge -->
                  <td class="py-3.5 px-4 text-center">
                    <span :class="['inline-block min-w-[36px] px-2.5 py-0.5 rounded-lg border text-xs font-black font-mono shadow-2xs', getGradeBadgeClass(res.grade)]">
                      {{ res.grade || 'N/A' }}
                    </span>
                  </td>

                  <!-- Status Pill -->
                  <td class="py-3.5 px-4 text-center">
                    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border', getStatusBadge(res.status).pill]">
                      <span :class="['w-1.5 h-1.5 rounded-full', getStatusBadge(res.status).dot]"></span>
                      <span>{{ getStatusBadge(res.status).label }}</span>
                    </span>
                  </td>

                  <!-- Actions -->
                  <td class="py-3.5 px-4 text-right pr-6">
                    <div class="inline-flex items-center gap-1.5">
                      <!-- Quick Modal Review Button -->
                      <button
                        @click="openQuickReview(res)"
                        class="p-1.5 rounded-lg border border-[#E5EAF3] bg-white hover:bg-[#EEF0FF] hover:border-[#D6DBFF] text-[#71819B] hover:text-[#4F35F3] transition-colors cursor-pointer"
                        title="Quick Question Review"
                      >
                        <Eye :size="13" class="stroke-[2.5]" />
                      </button>

                      <!-- Full Details Button -->
                      <button
                        @click="openResultDetails(res)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#EEF0FF] hover:bg-[#4F35F3] text-[#4F35F3] hover:text-white border border-[#D6DBFF] hover:border-[#4F35F3] font-bold text-xs transition-all shadow-2xs cursor-pointer"
                      >
                        <span>View Details</span>
                        <ChevronRight :size="12" class="stroke-[2.5]" />
                      </button>
                    </div>
                  </td>

                </tr>
              </tbody>
            </table>
          </div>

          <!-- ── Mobile Card Layout (< md) ── -->
          <div class="md:hidden divide-y divide-[#F0F3F8] p-3 space-y-3">
            <div
              v-for="(res, index) in paginatedResults"
              :key="res.id"
              class="p-4 rounded-xl border border-[#E5EAF3] bg-white shadow-2xs space-y-3"
            >
              <!-- Card Header: Course Code, Index, Grade -->
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] border border-[#D6DBFF] flex items-center justify-center font-mono font-black text-xs shrink-0">
                    {{ getCourseInitials(res.courseCode) }}
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="font-bold text-sm text-[#17243A]">{{ res.courseCode }}</span>
                      <span class="text-[10px] text-[#71819B] font-mono">#{{ (currentPage - 1) * (itemsPerPage === -1 ? 0 : itemsPerPage) + index + 1 }}</span>
                    </div>
                    <p class="text-xs text-[#71819B] font-medium line-clamp-1">{{ res.courseName || res.courseCode }}</p>
                  </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                  <span :class="['px-2.5 py-0.5 rounded-lg border text-xs font-black font-mono', getGradeBadgeClass(res.grade)]">
                    {{ res.grade || 'N/A' }}
                  </span>
                </div>
              </div>

              <!-- Exam info & Date -->
              <div class="flex items-center justify-between text-xs text-[#71819B] pt-1">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-[#F5F7FC] border border-[#E5EAF3] text-[11px] font-semibold text-slate-700">
                  <FileText :size="11" />
                  {{ res.examType || res.examTitle || 'Mid Exam' }}
                </span>
                <span class="inline-flex items-center gap-1 text-[11px]">
                  <Calendar :size="11" />
                  {{ res.completedDate }}
                </span>
              </div>

              <!-- Score & Status Grid -->
              <div class="flex items-center justify-between pt-2.5 border-t border-[#F0F3F8]">
                <div>
                  <span class="text-[10px] uppercase font-bold text-[#71819B] tracking-wider block">Score Earned</span>
                  <div class="flex items-baseline gap-1.5 mt-0.5">
                    <span :class="['font-mono font-black text-base', getScoreColor(res.percentage)]">
                      {{ Number(res.percentage || 0).toFixed(1) }}%
                    </span>
                    <span v-if="res.totalMarks" class="text-[10px] text-[#71819B] font-mono">
                      ({{ res.score }}/{{ res.totalMarks }} pts)
                    </span>
                  </div>
                  <div class="w-24 h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden">
                    <div
                      :class="['h-full rounded-full', getScoreBarColor(res.percentage)]"
                      :style="{ width: `${Math.min(Math.max(Number(res.percentage || 0), 0), 100)}%` }"
                    ></div>
                  </div>
                </div>

                <div class="text-right">
                  <span class="text-[10px] uppercase font-bold text-[#71819B] tracking-wider block">Outcome</span>
                  <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border mt-1', getStatusBadge(res.status).pill]">
                    <span :class="['w-1.5 h-1.5 rounded-full', getStatusBadge(res.status).dot]"></span>
                    <span>{{ getStatusBadge(res.status).label }}</span>
                  </span>
                </div>
              </div>

              <!-- Mobile Actions -->
              <div class="grid grid-cols-2 gap-2 pt-2 border-t border-[#F0F3F8]">
                <button
                  @click="openQuickReview(res)"
                  class="py-2 px-3 rounded-xl border border-[#E5EAF3] bg-slate-50 hover:bg-[#EEF0FF] text-[#17243A] text-xs font-bold transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                >
                  <Eye :size="13" />
                  <span>Quick Review</span>
                </button>

                <button
                  @click="openResultDetails(res)"
                  class="py-2 px-3 rounded-xl bg-[#4F35F3] hover:bg-[#3D25D8] text-white text-xs font-bold transition-colors flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs"
                >
                  <span>Full Details</span>
                  <ChevronRight :size="13" />
                </button>
              </div>

            </div>
          </div>

          <!-- ── Pagination Bar ── -->
          <div class="p-3.5 sm:p-4 border-t border-[#E5EAF3] bg-[#FAFBFD] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[#71819B]">

            <!-- Left: Items Per Page & Stats -->
            <div class="flex items-center gap-3">
              <span class="font-medium">
                Showing <strong class="text-[#17243A]">{{ paginatedResults.length }}</strong> of {{ filteredResults.length }} results
              </span>
              <div class="hidden sm:flex items-center gap-1.5">
                <span class="text-[11px]">Rows:</span>
                <select
                  v-model="itemsPerPage"
                  class="px-2 py-1 bg-white rounded-lg border border-[#E5EAF3] text-xs font-bold text-[#17243A] focus:outline-none focus:border-[#4F35F3] cursor-pointer"
                >
                  <option :value="5">5</option>
                  <option :value="10">10</option>
                  <option :value="20">20</option>
                  <option :value="-1">All</option>
                </select>
              </div>
            </div>

            <!-- Right: Page Controls -->
            <div v-if="totalPages > 1" class="flex items-center gap-1">
              <button
                @click="currentPage = 1"
                :disabled="currentPage === 1"
                class="p-1.5 rounded-lg border border-[#E5EAF3] bg-white text-[#71819B] hover:text-[#17243A] hover:bg-[#F0F3F8] disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                title="First page"
              >
                <ChevronsLeft :size="14" />
              </button>
              <button
                @click="currentPage = Math.max(currentPage - 1, 1)"
                :disabled="currentPage === 1"
                class="p-1.5 rounded-lg border border-[#E5EAF3] bg-white text-[#71819B] hover:text-[#17243A] hover:bg-[#F0F3F8] disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                title="Previous page"
              >
                <ChevronLeft :size="14" />
              </button>

              <div class="flex items-center gap-1 px-1">
                <span class="text-xs font-bold text-[#17243A] font-mono">Page {{ currentPage }}</span>
                <span class="text-xs text-[#71819B]">of {{ totalPages }}</span>
              </div>

              <button
                @click="currentPage = Math.min(currentPage + 1, totalPages)"
                :disabled="currentPage === totalPages"
                class="p-1.5 rounded-lg border border-[#E5EAF3] bg-white text-[#71819B] hover:text-[#17243A] hover:bg-[#F0F3F8] disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                title="Next page"
              >
                <ChevronRight :size="14" />
              </button>
              <button
                @click="currentPage = totalPages"
                :disabled="currentPage === totalPages"
                class="p-1.5 rounded-lg border border-[#E5EAF3] bg-white text-[#71819B] hover:text-[#17243A] hover:bg-[#F0F3F8] disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                title="Last page"
              >
                <ChevronsRight :size="14" />
              </button>
            </div>

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
