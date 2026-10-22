<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useStudentProfile } from '../composables/useStudentProfile'
import { sampleAnnouncements } from '../data/mockData'

import Header from '../components/Header.vue'
import StudentSidebar from '../components/StudentSidebar.vue'
import ProfileModal from '../components/ProfileModal.vue'

import {
  Calendar,
  Clock,
  Search,
  Filter,
  Eye,
  RefreshCw,
  Printer,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  X,
  AlertCircle,
  CheckCircle2,
  BookOpen,
  User,
  GraduationCap,
  MapPin,
  FileText,
  Calculator,
  Info,
  ShieldAlert
} from 'lucide-vue-next'

interface StudentScheduleItem {
  id: number
  title: string
  code: string
  course_code: string
  course_name: string
  instructor_name: string
  instructor_email: string
  section: string
  date: string | null
  date_formatted: string
  start_time: string
  end_time: string
  time_range: string
  duration: string
  duration_minutes: number
  exam_type: string
  total_marks: number
  status: 'Scheduled' | 'Published' | 'Completed' | 'Cancelled' | 'Active' | string
  raw_status: string
  room?: string
  semester?: string
  academic_year?: string
  description?: string
  instructions?: string
  allow_calculator?: boolean
  attempt_status?: string | null
  attempt_id?: number | null
  questions_count?: number
}

const router = useRouter()
const route = useRoute()
const { profile, fetchProfile } = useStudentProfile()

// ── Layout Drawer States ───────────────────────────────────────────────────
const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)
const isNotificationsOpen = ref(false)

// ── Data & Loading States ──────────────────────────────────────────────────
const schedules = ref<StudentScheduleItem[]>([])
const isLoading = ref(true)
const isRefreshing = ref(false)
const errorMessage = ref<string | null>(null)

// ── Filter Options from Backend ────────────────────────────────────────────
const backendSemesters = ref<string[]>([])
const backendCourses = ref<{ code: string; name: string }[]>([])
const backendSections = ref<string[]>([])
const backendStatuses = ref<string[]>(['Scheduled', 'Published', 'Completed', 'Cancelled'])

// ── Filter Values ──────────────────────────────────────────────────────────
const searchQuery = ref(String(route.query.search || ''))
const selectedSemester = ref('all')
const selectedCourse = ref('all')
const selectedSection = ref('all')
const selectedStatus = ref('all')

// ── Pagination State ───────────────────────────────────────────────────────
const currentPage = ref(1)
const perPage = ref(10)

// ── Details Modal ──────────────────────────────────────────────────────────
const selectedExamForModal = ref<StudentScheduleItem | null>(null)
const isDetailsModalOpen = ref(false)

// ── Fetch authoritative student schedules from backend ─────────────────────
async function loadSchedules(silent = false) {
  if (!silent) isLoading.value = true
  else isRefreshing.value = true
  errorMessage.value = null

  try {
    const response = await apiClient.get('/student/schedule')
    const res = response.data
    schedules.value = res.data || []

    if (res.filter_options) {
      backendSemesters.value = res.filter_options.semesters || []
      backendCourses.value = res.filter_options.courses || []
      backendSections.value = res.filter_options.sections || []
      if (res.filter_options.statuses?.length) {
        backendStatuses.value = res.filter_options.statuses
      }
    }
  } catch (err: any) {
    console.error('Failed to load student exam schedules:', err)
    errorMessage.value = err.response?.data?.message || err.message || 'Unable to load examination schedule from server.'
  } finally {
    isLoading.value = false
    isRefreshing.value = false
  }
}

// ── Reset Filters ──────────────────────────────────────────────────────────
function resetFilters() {
  searchQuery.value = ''
  selectedSemester.value = 'all'
  selectedCourse.value = 'all'
  selectedSection.value = 'all'
  selectedStatus.value = 'all'
  currentPage.value = 1
}

const hasActiveFilters = computed(() => {
  return (
    searchQuery.value.trim() !== '' ||
    selectedSemester.value !== 'all' ||
    selectedCourse.value !== 'all' ||
    selectedSection.value !== 'all' ||
    selectedStatus.value !== 'all'
  )
})

// ── Filtered Schedules ─────────────────────────────────────────────────────
const filteredSchedules = computed(() => {
  return schedules.value.filter((item) => {
    // 1. Search Query (title, course_name, course_code, code)
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.trim().toLowerCase()
      const match =
        (item.title && item.title.toLowerCase().includes(q)) ||
        (item.code && item.code.toLowerCase().includes(q)) ||
        (item.course_code && item.course_code.toLowerCase().includes(q)) ||
        (item.course_name && item.course_name.toLowerCase().includes(q)) ||
        (item.instructor_name && item.instructor_name.toLowerCase().includes(q))
      if (!match) return false
    }

    // 2. Semester Filter
    if (selectedSemester.value !== 'all') {
      const sem = (item.semester || '').toLowerCase()
      if (!sem.includes(selectedSemester.value.toLowerCase())) return false
    }

    // 3. Course Filter
    if (selectedCourse.value !== 'all') {
      if (
        item.course_code !== selectedCourse.value &&
        item.course_name !== selectedCourse.value
      ) {
        return false
      }
    }

    // 4. Section Filter
    if (selectedSection.value !== 'all') {
      const itemSec = (item.section || '').toLowerCase()
      const targetSec = selectedSection.value.toLowerCase()
      if (itemSec !== targetSec && !itemSec.includes(targetSec) && !itemSec.includes('all') && !itemSec.includes('both')) {
        return false
      }
    }

    // 5. Status Filter
    if (selectedStatus.value !== 'all') {
      const target = selectedStatus.value.toLowerCase()
      const current = (item.status || '').toLowerCase()
      const raw = (item.raw_status || '').toLowerCase()
      if (current !== target && raw !== target) return false
    }

    return true
  })
})

// ── Pagination Math ────────────────────────────────────────────────────────
const totalMatching = computed(() => filteredSchedules.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(totalMatching.value / perPage.value)))

watch([searchQuery, selectedSemester, selectedCourse, selectedSection, selectedStatus], () => {
  currentPage.value = 1
})

const paginatedSchedules = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredSchedules.value.slice(start, start + perPage.value)
})

const pageStartItem = computed(() => {
  if (totalMatching.value === 0) return 0
  return (currentPage.value - 1) * perPage.value + 1
})

const pageEndItem = computed(() => {
  return Math.min(currentPage.value * perPage.value, totalMatching.value)
})

// ── Open Details Modal ─────────────────────────────────────────────────────
function openDetails(item: StudentScheduleItem) {
  selectedExamForModal.value = item
  isDetailsModalOpen.value = true
}

function closeDetails() {
  isDetailsModalOpen.value = false
  selectedExamForModal.value = null
}

// ── Print Timetable ────────────────────────────────────────────────────────
function printSchedule() {
  window.print()
}

// ── Status Badge Styling ───────────────────────────────────────────────────
function getStatusBadgeClass(status: string) {
  const s = (status || '').toLowerCase()
  if (s === 'scheduled') {
    return 'bg-emerald-50 text-emerald-700 border border-emerald-200/90'
  }
  if (s === 'published') {
    return 'bg-purple-50 text-purple-700 border border-purple-200/90'
  }
  if (s === 'completed') {
    return 'bg-slate-100 text-slate-700 border border-slate-200/90'
  }
  if (s === 'cancelled' || s === 'canceled') {
    return 'bg-rose-50 text-rose-700 border border-rose-200/90'
  }
  if (s === 'active' || s === 'in_progress') {
    return 'bg-blue-50 text-blue-700 border border-blue-200/90 animate-pulse'
  }
  return 'bg-slate-100 text-slate-700 border border-slate-200/90'
}

onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    loadSchedules()
  ])
})
</script>

<template>
  <div class="min-h-screen bg-[#F6F8FC] font-sans text-[#17243A] antialiased selection:bg-[#4F35F3] selection:text-white flex flex-col">
    <!-- Top Navigation Header -->
    <Header
      :profile="profile"
      :announcements="sampleAnnouncements"
      @open-profile="isProfileOpen = true"
      @open-notifications="isNotificationsOpen = true"
      @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
    />

    <!-- Off-Canvas Sidebar Drawer -->
    <StudentSidebar
      :profile="profile"
      :isOpen="isSidebarOpen"
      @close="isSidebarOpen = false"
    />

    <!-- Main Container -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">

      <!-- ── Breadcrumb & Page Header ───────────────────────────────────────── -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200/70 pb-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
            <button @click="router.push('/student')" class="hover:text-[#4F35F3] transition-colors cursor-pointer">
              Dashboard
            </button>
            <span class="text-slate-300">/</span>
            <span class="text-[#4F35F3]">Exam Schedule</span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-[#17243A] tracking-tight">
            Exam Schedule
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">
            Authoritative examination schedules and publication records for your enrolled courses.
          </p>
        </div>

        <!-- Right: Action Buttons -->
        <div class="flex items-center gap-2.5 print:hidden">
          <button
            type="button"
            @click="loadSchedules(true)"
            :disabled="isRefreshing"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200/90 rounded-xl hover:bg-slate-50 hover:text-slate-900 transition shadow-2xs cursor-pointer disabled:opacity-60"
            title="Refresh examination timetable"
          >
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isRefreshing }" />
            <span>{{ isRefreshing ? 'Refreshing...' : 'Refresh' }}</span>
          </button>

          <button
            type="button"
            @click="printSchedule"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200/90 rounded-xl hover:bg-slate-50 hover:text-slate-900 transition shadow-2xs cursor-pointer"
            title="Print examination schedule"
          >
            <Printer class="w-3.5 h-3.5 text-slate-500" />
            <span>Print</span>
          </button>
        </div>
      </div>

      <!-- ── Error Banner State ──────────────────────────────────────────────── -->
      <div v-if="errorMessage" class="p-4 bg-rose-50 border border-rose-200/90 rounded-2xl flex items-start gap-3 text-rose-800 animate-in fade-in">
        <AlertCircle class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
        <div class="flex-1 text-xs">
          <p class="font-bold text-rose-900">Failed to load examination timetable</p>
          <p class="mt-0.5 text-rose-700">{{ errorMessage }}</p>
        </div>
        <button
          @click="loadSchedules()"
          class="px-3 py-1 bg-rose-600 text-white font-semibold rounded-lg text-xs hover:bg-rose-700 transition shadow-2xs cursor-pointer"
        >
          Retry
        </button>
      </div>

      <!-- ── Filter Toolbar (Matches Image 2 Reference) ──────────────────────── -->
      <div class="bg-white rounded-2xl border border-slate-200/80 p-3 sm:p-4 shadow-xs print:hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-center">

          <!-- 1. Search Bar -->
          <div class="relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Search by title, course, or code..."
              class="w-full pl-9 pr-8 py-2 text-xs sm:text-[13px] bg-slate-50/60 border border-slate-200/90 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] text-slate-800 placeholder-slate-400 transition"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- 2. Semester Filter -->
          <div class="relative">
            <select
              v-model="selectedSemester"
              class="w-full appearance-none px-3.5 py-2 text-xs sm:text-[13px] bg-slate-50/60 border border-slate-200/90 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] text-slate-700 cursor-pointer transition pr-8 font-medium"
            >
              <option value="all">All Semesters</option>
              <option v-for="sem in backendSemesters" :key="sem" :value="sem">{{ sem }}</option>
              <option v-if="!backendSemesters.length" value="First Semester">First Semester</option>
              <option v-if="!backendSemesters.length" value="Second Semester">Second Semester</option>
            </select>
            <ChevronDown class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>

          <!-- 3. Course Filter -->
          <div class="relative">
            <select
              v-model="selectedCourse"
              class="w-full appearance-none px-3.5 py-2 text-xs sm:text-[13px] bg-slate-50/60 border border-slate-200/90 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] text-slate-700 cursor-pointer transition pr-8 font-medium"
            >
              <option value="all">All Courses</option>
              <option v-for="c in backendCourses" :key="c.code" :value="c.code">
                {{ c.code }} - {{ c.name }}
              </option>
            </select>
            <ChevronDown class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>

          <!-- 4. Section Filter -->
          <div class="relative">
            <select
              v-model="selectedSection"
              class="w-full appearance-none px-3.5 py-2 text-xs sm:text-[13px] bg-slate-50/60 border border-slate-200/90 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] text-slate-700 cursor-pointer transition pr-8 font-medium"
            >
              <option value="all">All Sections</option>
              <option v-for="sec in backendSections" :key="sec" :value="sec">
                {{ sec }}
              </option>
              <option v-if="!backendSections.length" value="Both Sections">Both Sections</option>
              <option v-if="!backendSections.length" value="Section A">Section A</option>
              <option v-if="!backendSections.length" value="Section B">Section B</option>
            </select>
            <ChevronDown class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>

          <!-- 5. Status Filter -->
          <div class="relative flex items-center gap-2">
            <div class="relative flex-1">
              <select
                v-model="selectedStatus"
                class="w-full appearance-none px-3.5 py-2 text-xs sm:text-[13px] bg-slate-50/60 border border-slate-200/90 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] text-slate-700 cursor-pointer transition pr-8 font-medium"
              >
                <option value="all">All Statuses</option>
                <option value="Scheduled">Scheduled</option>
                <option value="Published">Published</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
              </select>
              <ChevronDown class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>

            <!-- Reset Filter Button -->
            <button
              v-if="hasActiveFilters"
              @click="resetFilters"
              class="px-2.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition shrink-0 cursor-pointer"
              title="Reset all filters"
            >
              Reset
            </button>
          </div>

        </div>
      </div>

      <!-- ── Exam Schedule Table Card (Matches Image 2 Reference) ────────────── -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">

        <!-- Card Header -->
        <div class="px-5 py-4.5 border-b border-slate-200/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <h2 class="text-base sm:text-lg font-bold text-[#17243A]">
              Exam Schedule Table
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Authoritative departmental examination schedules and publication records.
            </p>
          </div>
          <div class="text-xs font-medium text-slate-500 self-start sm:self-auto">
            Showing {{ totalMatching }} of {{ schedules.length }} exams
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="p-12 text-center">
          <div class="w-10 h-10 border-3 border-indigo-200 border-t-[#4F35F3] rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-sm font-semibold text-slate-700">Loading examination schedules...</p>
          <p class="text-xs text-slate-400 mt-1">Connecting to authoritative departmental timetable</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredSchedules.length === 0" class="p-12 sm:p-16 text-center">
          <div class="w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#4F35F3] flex items-center justify-center mx-auto mb-3.5 shadow-2xs">
            <Calendar class="w-7 h-7" />
          </div>
          <h3 class="text-base font-bold text-slate-800">No Examination Schedules Found</h3>
          <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto mt-1.5">
            {{
              hasActiveFilters
                ? 'No examination schedules match your active filter criteria. Try resetting your search filters.'
                : 'There are currently no examination schedules published for your enrolled courses and section. Check back soon or contact your department head.'
            }}
          </p>
          <div class="mt-4">
            <button
              v-if="hasActiveFilters"
              @click="resetFilters"
              class="px-4 py-2 bg-[#4F35F3] text-white text-xs font-semibold rounded-xl hover:bg-indigo-700 transition shadow-2xs cursor-pointer"
            >
              Reset Filters
            </button>
            <button
              v-else
              @click="loadSchedules()"
              class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-200 transition cursor-pointer"
            >
              Refresh Timetable
            </button>
          </div>
        </div>

        <!-- Table View (Desktop & Tablet) -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left border-collapse min-w-[960px]">
            <thead>
              <tr class="bg-slate-50/70 border-b border-slate-200/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                <th class="py-3.5 px-5">EXAM TITLE</th>
                <th class="py-3.5 px-4">COURSE</th>
                <th class="py-3.5 px-4">INSTRUCTOR</th>
                <th class="py-3.5 px-4">SECTION</th>
                <th class="py-3.5 px-4">DATE & TIME</th>
                <th class="py-3.5 px-4">DURATION</th>
                <th class="py-3.5 px-4">TYPE</th>
                <th class="py-3.5 px-4">STATUS</th>
                <th class="py-3.5 px-5 text-right">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
              <tr
                v-for="exam in paginatedSchedules"
                :key="exam.id"
                class="hover:bg-slate-50/70 transition-colors group"
              >
                <!-- 1. EXAM TITLE -->
                <td class="py-4 px-5">
                  <div class="font-bold text-slate-900 text-sm group-hover:text-[#4F35F3] transition-colors leading-tight">
                    {{ exam.title }}
                  </div>
                  <div class="text-[11px] font-mono text-slate-400 mt-0.5">
                    {{ exam.code }}
                  </div>
                </td>

                <!-- 2. COURSE -->
                <td class="py-4 px-4">
                  <div class="inline-block px-2.5 py-0.5 bg-slate-100 text-slate-700 font-bold rounded-md text-[11px] tracking-wide mb-1 border border-slate-200/60">
                    {{ exam.course_code }}
                  </div>
                  <div class="text-xs text-slate-600 truncate max-w-[160px] font-medium" :title="exam.course_name">
                    {{ exam.course_name }}
                  </div>
                </td>

                <!-- 3. INSTRUCTOR -->
                <td class="py-4 px-4">
                  <div class="font-semibold text-slate-900 text-xs">
                    {{ exam.instructor_name }}
                  </div>
                  <div class="text-[11px] text-slate-400 truncate max-w-[170px]" :title="exam.instructor_email">
                    {{ exam.instructor_email || '—' }}
                  </div>
                </td>

                <!-- 4. SECTION -->
                <td class="py-4 px-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200/60">
                    {{ exam.section }}
                  </span>
                </td>

                <!-- 5. DATE & TIME -->
                <td class="py-4 px-4 whitespace-nowrap">
                  <div class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                    <Calendar class="w-3.5 h-3.5 text-slate-400" />
                    <span>{{ exam.date || exam.date_formatted }}</span>
                  </div>
                  <div class="text-[11px] text-slate-500 font-medium mt-0.5 ml-5">
                    {{ exam.time_range }}
                  </div>
                </td>

                <!-- 6. DURATION -->
                <td class="py-4 px-4 font-semibold text-slate-800 whitespace-nowrap">
                  {{ exam.duration }}
                </td>

                <!-- 7. TYPE -->
                <td class="py-4 px-4 font-semibold text-slate-800 whitespace-nowrap">
                  {{ exam.exam_type }}
                </td>

                <!-- 8. STATUS -->
                <td class="py-4 px-4 whitespace-nowrap">
                  <span
                    :class="['inline-flex items-center px-3 py-1 rounded-full text-xs font-bold tracking-wide shadow-2xs', getStatusBadgeClass(exam.status)]"
                  >
                    {{ exam.status }}
                  </span>
                </td>

                <!-- 9. ACTIONS -->
                <td class="py-4 px-5 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="openDetails(exam)"
                      class="p-2 rounded-xl text-slate-400 hover:text-[#4F35F3] hover:bg-[#EEF0FF] transition-colors cursor-pointer"
                      title="View Schedule Details"
                    >
                      <Eye class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Table Footer / Pagination -->
        <div
          v-if="filteredSchedules.length > 0"
          class="px-5 py-3.5 border-t border-slate-200/80 bg-slate-50/40 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500"
        >
          <div>
            Showing {{ pageStartItem }} to {{ pageEndItem }} of {{ totalMatching }} exams
          </div>

          <!-- Pagination Controls -->
          <div class="flex items-center gap-1.5 self-center sm:self-auto">
            <button
              @click="currentPage--"
              :disabled="currentPage <= 1"
              class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
              title="Previous Page"
            >
              <ChevronLeft class="w-4 h-4" />
            </button>

            <template v-for="p in totalPages" :key="p">
              <button
                v-if="p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1"
                @click="currentPage = p"
                :class="[
                  'min-w-[32px] h-8 px-2.5 rounded-lg text-xs font-bold transition cursor-pointer',
                  currentPage === p
                    ? 'bg-[#4F35F3] text-white shadow-xs'
                    : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'
                ]"
              >
                {{ p }}
              </button>
              <span
                v-else-if="(p === 2 && currentPage > 3) || (p === totalPages - 1 && currentPage < totalPages - 2)"
                class="px-1 text-slate-400 font-bold"
              >
                ...
              </span>
            </template>

            <button
              @click="currentPage++"
              :disabled="currentPage >= totalPages"
              class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
              title="Next Page"
            >
              <ChevronRight class="w-4 h-4" />
            </button>
          </div>
        </div>

      </div>

    </main>

    <!-- ── View Exam Schedule Details Modal ────────────────────────────────── -->
    <Transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="isDetailsModalOpen && selectedExamForModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs" @click="closeDetails"></div>

        <!-- Modal Card -->
        <div class="relative z-10 w-full max-w-xl bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden animate-in fade-in zoom-in-95 duration-200 max-h-[90vh] flex flex-col">

          <!-- Header -->
          <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between bg-gradient-to-r from-slate-50 to-indigo-50/30">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="inline-block px-2.5 py-0.5 bg-[#4F35F3]/10 text-[#4F35F3] font-bold rounded-md text-[11px] tracking-wide border border-[#4F35F3]/20">
                  {{ selectedExamForModal.course_code }}
                </span>
                <span :class="['px-2.5 py-0.5 rounded-full text-[11px] font-bold shadow-2xs', getStatusBadgeClass(selectedExamForModal.status)]">
                  {{ selectedExamForModal.status }}
                </span>
              </div>
              <h3 class="text-lg font-extrabold text-slate-900 leading-tight">
                {{ selectedExamForModal.title }}
              </h3>
              <p class="text-xs font-mono text-slate-400 mt-0.5">
                {{ selectedExamForModal.code }}
              </p>
            </div>

            <button
              @click="closeDetails"
              class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto space-y-5 text-xs text-slate-700 flex-1">

            <!-- Primary Metadata Grid -->
            <div class="grid grid-cols-2 gap-3.5">
              <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Date & Time</div>
                <div class="text-xs font-bold text-slate-900 mt-1 flex items-center gap-1.5">
                  <Calendar class="w-3.5 h-3.5 text-[#4F35F3]" />
                  <span>{{ selectedExamForModal.date || selectedExamForModal.date_formatted }}</span>
                </div>
                <div class="text-[11px] text-slate-500 font-medium mt-0.5 ml-5">
                  {{ selectedExamForModal.time_range }}
                </div>
              </div>

              <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Duration & Marks</div>
                <div class="text-xs font-bold text-slate-900 mt-1 flex items-center gap-1.5">
                  <Clock class="w-3.5 h-3.5 text-amber-500" />
                  <span>{{ selectedExamForModal.duration }} ({{ selectedExamForModal.duration_minutes }} mins)</span>
                </div>
                <div class="text-[11px] text-slate-500 font-medium mt-0.5 ml-5">
                  Total: {{ selectedExamForModal.total_marks }} marks
                </div>
              </div>

              <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Course & Term</div>
                <div class="text-xs font-bold text-slate-900 mt-1 truncate" :title="selectedExamForModal.course_name">
                  {{ selectedExamForModal.course_name }}
                </div>
                <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                  {{ selectedExamForModal.semester }} • {{ selectedExamForModal.academic_year }}
                </div>
              </div>

              <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Section & Room</div>
                <div class="text-xs font-bold text-slate-900 mt-1 flex items-center gap-1.5">
                  <MapPin class="w-3.5 h-3.5 text-rose-500" />
                  <span>{{ selectedExamForModal.room || 'Room 101' }}</span>
                </div>
                <div class="text-[11px] text-slate-500 font-medium mt-0.5 ml-5">
                  Section: {{ selectedExamForModal.section }}
                </div>
              </div>
            </div>

            <!-- Assigned Instructor Info -->
            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center font-bold text-sm shrink-0">
                <User class="w-4 h-4" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Assigned Faculty</div>
                <div class="text-xs font-bold text-slate-900 truncate">{{ selectedExamForModal.instructor_name }}</div>
                <div class="text-[11px] text-slate-400 truncate">{{ selectedExamForModal.instructor_email || 'Faculty' }}</div>
              </div>
            </div>

            <!-- Examination Guidelines & Instructions -->
            <div class="space-y-1.5">
              <div class="text-[11px] uppercase font-bold text-slate-500 tracking-wider flex items-center gap-1.5">
                <FileText class="w-3.5 h-3.5 text-slate-400" />
                <span>Guidelines & Rules</span>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 text-slate-600 leading-relaxed text-xs">
                <p v-if="selectedExamForModal.instructions">
                  {{ selectedExamForModal.instructions }}
                </p>
                <p v-else-if="selectedExamForModal.description">
                  {{ selectedExamForModal.description }}
                </p>
                <p v-else class="text-slate-400 italic">
                  Standard university examination rules apply. Ensure you arrive or log in at least 10 minutes prior to start time.
                </p>

                <!-- Calculator allowed badge -->
                <div class="mt-2.5 pt-2 border-t border-slate-200/60 flex items-center gap-2">
                  <Calculator class="w-3.5 h-3.5" :class="selectedExamForModal.allow_calculator ? 'text-emerald-600' : 'text-slate-400'" />
                  <span class="text-[11px] font-semibold" :class="selectedExamForModal.allow_calculator ? 'text-emerald-700' : 'text-slate-500'">
                    {{ selectedExamForModal.allow_calculator ? 'Scientific Calculator Allowed' : 'Calculators Not Permitted' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Cancelled Notice if status is cancelled -->
            <div
              v-if="(selectedExamForModal.status || '').toLowerCase() === 'cancelled'"
              class="p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-rose-800 text-xs flex items-start gap-2.5"
            >
              <ShieldAlert class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
              <div>
                <span class="font-bold">Examination Cancelled:</span>
                This scheduled exam has been cancelled by the department head. You do not need to attend this session.
              </div>
            </div>

          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/70 flex items-center justify-between">
            <span class="text-[11px] text-slate-400">
              Exam ID: #{{ selectedExamForModal.id }}
            </span>
            <button
              @click="closeDetails"
              class="px-4 py-2 bg-[#4F35F3] hover:bg-indigo-700 text-white font-semibold text-xs rounded-xl transition shadow-2xs cursor-pointer"
            >
              Close
            </button>
          </div>

        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
@media print {
  body {
    background: white !important;
  }
}
</style>
