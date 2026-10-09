<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../../modules/auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'

const router = useRouter()
const authStore = useAuthStore()
const settingsStore = useSettingsStore()

// ── State Variables ──────────────────────────────────────────────────────────
const isLoading = ref(true)
const isRefreshing = ref(false)
const isExporting = ref(false)
const exportFormatLoading = ref<string>('')
const showExportDropdown = ref(false)
const showExportModal = ref(false)
const selectedExportFormat = ref<'pdf' | 'excel' | 'csv' | 'print'>('pdf')

// Active Tabs
const activeTab = ref<'overview' | 'courses' | 'exams' | 'students' | 'instructors'>('overview')

// Department & Academic Meta
const deptName = ref(authStore.user?.department?.name || 'Computer Science')
const deptCode = ref(authStore.user?.department?.code || 'CS')
const academicYear = ref(settingsStore.academicYear || '2028')
const semester = ref(settingsStore.semester || 'Second Semester')

// Filters
const selectedPeriod = ref('This Semester')
const selectedAcademicYear = ref('all')
const selectedSemester = ref('all')
const selectedCourse = ref('all')
const searchQuery = ref('')
const sortCourseBy = ref<'avgScore_desc' | 'avgScore_asc' | 'passRate_desc' | 'passRate_asc' | 'name_asc'>('avgScore_desc')
const sortExamBy = ref<'date_desc' | 'attempts_desc' | 'passRate_desc' | 'avg_desc'>('date_desc')
const studentGradeFilter = ref<'all' | 'passed' | 'failed' | 'in_progress'>('all')
const trendViewMode = ref<'monthly' | 'exams'>('monthly')

// Chart Interactive Hover Points
const activeHoverPoint = ref<{ month: string; avg_score: number; attempts_count: number; has_data: boolean; x: number; y: number } | null>(null)
const activeHoverExam = ref<any | null>(null)

// Toast Feedback
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'success'
})
let toastTimer: any = null

const showToast = (message: string, type: 'success' | 'error' | 'info' = 'success') => {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

// ── KPI Interfaces & State ───────────────────────────────────────────────────
interface KpiItem {
  id?: string
  label: string
  value: string
  change: string
  trend: 'up' | 'down'
  bg: string
  ic: string
  icon: string
  route?: string
}

const kpis = ref<KpiItem[]>([
  {
    id: 'students',
    label: 'Total Students',
    value: '0',
    change: '0 enrolled',
    trend: 'up',
    bg: 'bg-indigo-50',
    ic: 'text-[#5138ed]',
    icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    route: '/dept-head/students'
  },
  {
    id: 'exams',
    label: 'Total Exams Conducted',
    value: '0',
    change: '0 active',
    trend: 'up',
    bg: 'bg-sky-50',
    ic: 'text-sky-500',
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    route: '/dept-head/exams'
  },
  {
    id: 'pass_rate',
    label: 'Department Pass Rate',
    value: '0%',
    change: '0 passed',
    trend: 'up',
    bg: 'bg-emerald-50',
    ic: 'text-emerald-500',
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    route: '/dept-head/results'
  },
  {
    id: 'avg_score',
    label: 'Average Score',
    value: '0%',
    change: 'N/A',
    trend: 'up',
    bg: 'bg-amber-50',
    ic: 'text-amber-500',
    icon: 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z',
    route: '/dept-head/results'
  }
])

// ── Grade Breakdown & Analytics Data ─────────────────────────────────────────
const gradeDistribution = ref<{
  A: number
  B: number
  C: number
  D: number
  F: number
  in_progress: number
  passed_count: number
  failed_count: number
  total_graded: number
  total_all: number
  pass_rate: number
  avg_score: number
}>({
  A: 0,
  B: 0,
  C: 0,
  D: 0,
  F: 0,
  in_progress: 0,
  passed_count: 0,
  failed_count: 0,
  total_graded: 0,
  total_all: 0,
  pass_rate: 0,
  avg_score: 0
})

// Trend Charts State
interface TrendPoint {
  month: string
  avg_score: number
  attempts_count: number
  has_data: boolean
}

const trendData = ref<TrendPoint[]>([])
const chartPoints = ref<[number, number][]>([])
const hasTrendData = ref(false)
const chronologicalTrend = ref<any[]>([])

// Detailed Datasets
const coursePerformance = ref<any[]>([])
const examResults = ref<any[]>([])
const studentPerformance = ref<any[]>([])
const instructorSummary = ref<any[]>([])
const filterOptions = ref<{
  academic_years: string[]
  semesters: string[]
  periods: string[]
}>({
  academic_years: ['2028', '2027', '2026', '2025'],
  semesters: ['First Semester', 'Second Semester'],
  periods: ['This Semester', 'Last 30 Days', 'This Academic Year', 'All Time']
})

// SVG Computed Lines
const svgLine = computed(() => {
  if (!chartPoints.value || chartPoints.value.length === 0) return ''
  return chartPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'}${p[0]},${p[1]}`).join(' ')
})

const svgFill = computed(() => {
  if (!chartPoints.value || chartPoints.value.length === 0) return ''
  const last = chartPoints.value[chartPoints.value.length - 1]
  return `${svgLine.value} L${last[0]},200 L0,200 Z`
})

// Academic Term Pill
const activeTermBadge = computed(() => {
  return `${academicYear.value} ${semester.value}`
})

// ── Fetch Report Data from Real Backend ──────────────────────────────────────
const fetchReportData = async (isManualRefresh = false) => {
  if (isManualRefresh) {
    isRefreshing.value = true
  } else {
    isLoading.value = true
  }

  try {
    const res = await apiClient.get('/dept-head/reports', {
      params: {
        period: selectedPeriod.value,
        academic_year: selectedAcademicYear.value,
        semester: selectedSemester.value,
        course_code: selectedCourse.value
      }
    })

    if (res.data?.status === 'success' && res.data?.data) {
      const d = res.data.data
      if (d.department_name) deptName.value = d.department_name
      if (d.department_code) deptCode.value = d.department_code
      if (d.academic_year) academicYear.value = d.academic_year
      if (d.semester) semester.value = d.semester

      if (Array.isArray(d.kpis)) kpis.value = d.kpis
      if (d.grade_distribution) gradeDistribution.value = d.grade_distribution
      if (Array.isArray(d.trend)) trendData.value = d.trend
      if (Array.isArray(d.chart_points)) chartPoints.value = d.chart_points
      hasTrendData.value = !!d.has_trend_data
      if (Array.isArray(d.chronological_trend)) chronologicalTrend.value = d.chronological_trend
      if (Array.isArray(d.course_performance)) coursePerformance.value = d.course_performance
      if (Array.isArray(d.exam_results)) examResults.value = d.exam_results
      if (Array.isArray(d.student_performance)) studentPerformance.value = d.student_performance
      if (Array.isArray(d.instructor_summary)) instructorSummary.value = d.instructor_summary
      if (d.filter_options) filterOptions.value = d.filter_options

      if (isManualRefresh) {
        showToast('Department academic reports and analytics refreshed!', 'success')
      }
    }
  } catch (error: any) {
    console.error('Failed to fetch department reports:', error)
    showToast(error?.response?.data?.message || 'Unable to load report data. Please try again.', 'error')
  } finally {
    isLoading.value = false
    isRefreshing.value = false
  }
}

// ── Filter & Search Handlers ─────────────────────────────────────────────────
const onFilterChange = () => {
  fetchReportData()
}

const resetFilters = () => {
  selectedPeriod.value = 'This Semester'
  selectedAcademicYear.value = 'all'
  selectedSemester.value = 'all'
  selectedCourse.value = 'all'
  searchQuery.value = ''
  studentGradeFilter.value = 'all'
  fetchReportData()
}

// ── Computed Filtered Datasets ───────────────────────────────────────────────
const filteredCourses = computed(() => {
  let list = [...coursePerformance.value]
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(c => 
      c.name.toLowerCase().includes(q) || 
      c.code.toLowerCase().includes(q) ||
      (c.instructor && c.instructor.toLowerCase().includes(q))
    )
  }

  // Sorting
  switch (sortCourseBy.value) {
    case 'avgScore_desc':
      return list.sort((a, b) => b.avgScore - a.avgScore)
    case 'avgScore_asc':
      return list.sort((a, b) => a.avgScore - b.avgScore)
    case 'passRate_desc':
      return list.sort((a, b) => b.passRate - a.passRate)
    case 'passRate_asc':
      return list.sort((a, b) => a.passRate - b.passRate)
    case 'name_asc':
      return list.sort((a, b) => a.name.localeCompare(b.name))
    default:
      return list
  }
})

const filteredExams = computed(() => {
  let list = [...examResults.value]
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(e => 
      e.title.toLowerCase().includes(q) || 
      e.code.toLowerCase().includes(q) ||
      e.course_code.toLowerCase().includes(q) ||
      (e.instructor && e.instructor.toLowerCase().includes(q))
    )
  }

  switch (sortExamBy.value) {
    case 'date_desc':
      return list.sort((a, b) => b.id - a.id)
    case 'attempts_desc':
      return list.sort((a, b) => b.attempts_count - a.attempts_count)
    case 'passRate_desc':
      return list.sort((a, b) => b.pass_rate - a.pass_rate)
    case 'avg_desc':
      return list.sort((a, b) => b.avg_score - a.avg_score)
    default:
      return list
  }
})

const filteredStudents = computed(() => {
  let list = [...studentPerformance.value]
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(s => 
      s.student_name.toLowerCase().includes(q) || 
      s.student_id.toLowerCase().includes(q) ||
      s.exam_title.toLowerCase().includes(q) ||
      s.course_code.toLowerCase().includes(q)
    )
  }

  if (studentGradeFilter.value !== 'all') {
    if (studentGradeFilter.value === 'passed') {
      list = list.filter(s => s.percentage !== null && s.percentage >= 50)
    } else if (studentGradeFilter.value === 'failed') {
      list = list.filter(s => s.percentage !== null && s.percentage < 50)
    } else if (studentGradeFilter.value === 'in_progress') {
      list = list.filter(s => s.status === 'in_progress' || s.percentage === null)
    }
  }

  return list
})

const filteredInstructors = computed(() => {
  let list = [...instructorSummary.value]
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(i => 
      i.name.toLowerCase().includes(q) || 
      i.email.toLowerCase().includes(q) ||
      i.role.toLowerCase().includes(q)
    )
  }
  return list
})

// ── Export Handling ──────────────────────────────────────────────────────────
const toggleExportDropdown = () => {
  showExportDropdown.value = !showExportDropdown.value
}

const handlePrint = () => {
  showExportDropdown.value = false
  showExportModal.value = false
  window.print()
}

const handleExport = async (format?: 'pdf' | 'excel' | 'csv' | 'print') => {
  const chosenFormat = format || selectedExportFormat.value
  showExportDropdown.value = false
  showExportModal.value = false

  if (chosenFormat === 'print') {
    handlePrint()
    return
  }

  try {
    isExporting.value = true
    exportFormatLoading.value = chosenFormat
    showToast(`Generating official ${chosenFormat.toUpperCase()} report...`, 'info')

    const res = await apiClient.get('/dept-head/reports/export', {
      params: {
        format: chosenFormat,
        period: selectedPeriod.value,
        academic_year: selectedAcademicYear.value,
        semester: selectedSemester.value
      }
    })

    const { file, filename } = res.data
    if (!file || !filename) {
      throw new Error('Export payload missing file data')
    }

    let mimeType = 'application/pdf'
    if (chosenFormat === 'excel') {
      mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    } else if (chosenFormat === 'csv') {
      mimeType = 'text/csv;charset=utf-8;'
    }

    // Decode base64 and trigger browser file download
    const binary = atob(file)
    const array = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i++) {
      array[i] = binary.charCodeAt(i)
    }

    const blob = new Blob([array], { type: mimeType })
    const link = document.createElement('a')
    const url = URL.createObjectURL(blob)
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()

    setTimeout(() => {
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
    }, 250)

    showToast(`Report downloaded successfully as ${chosenFormat.toUpperCase()}!`, 'success')
  } catch (err: any) {
    console.error('Export error:', err)
    showToast(err?.response?.data?.message || 'Failed to export report. Please try again.', 'error')
  } finally {
    isExporting.value = false
    exportFormatLoading.value = ''
  }
}

// Drilldown Navigation Helper
const navigateTo = (routePath?: string) => {
  if (routePath) {
    router.push(routePath)
  }
}

// Close Dropdowns on Click Outside
const handleOutsideClick = (e: MouseEvent) => {
  const target = e.target as HTMLElement
  if (!target.closest('.export-dropdown-container')) {
    showExportDropdown.value = false
  }
}

onMounted(() => {
  fetchReportData()
  window.addEventListener('click', handleOutsideClick)
})

onUnmounted(() => {
  window.removeEventListener('click', handleOutsideClick)
})
</script>

<template>
  <div class="space-y-6 min-w-0 max-w-full pb-12">

    <!-- ══════════════════════════════════════════════════════════
         OFFICIAL PRINT HEADER (Visible only during window.print)
    ══════════════════════════════════════════════════════════ -->
    <div class="hidden print:block border-b-2 border-[#5138ed] pb-4 mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">WOLLO UNIVERSITY</h1>
          <p class="text-sm font-bold text-[#5138ed]">Department of {{ deptName }} &bull; Academic Analytics &amp; Reports</p>
          <p class="text-xs text-slate-500 mt-1">Academic Term: {{ activeTermBadge }} &bull; Period Filter: {{ selectedPeriod }}</p>
        </div>
        <div class="text-right text-xs text-slate-400">
          <p>Official Academic Document</p>
          <p>Generated: {{ new Date().toLocaleDateString() }}</p>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         TOAST FEEDBACK NOTIFICATION
    ══════════════════════════════════════════════════════════ -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toast.show"
        class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-xl border text-[13px] font-medium transition-all max-w-[90vw] print:hidden"
        :class="{
          'bg-emerald-50 border-emerald-200 text-emerald-800': toast.type === 'success',
          'bg-rose-50 border-rose-200 text-rose-800': toast.type === 'error',
          'bg-indigo-50 border-indigo-200 text-[#5138ed]': toast.type === 'info'
        }"
      >
        <svg v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <svg v-else-if="toast.type === 'error'" class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <svg v-else class="w-5 h-5 text-[#5138ed] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="break-words">{{ toast.message }}</span>
      </div>
    </transition>

    <!-- ══════════════════════════════════════════════════════════
         PAGE HEADER & REPORTING TOOLBAR
    ══════════════════════════════════════════════════════════ -->
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 print:hidden">
      <!-- Title & Context Pills -->
      <div>
        <div class="flex flex-wrap items-center gap-2.5">
          <h1 class="text-[22px] sm:text-[24px] font-black text-slate-900 tracking-tight">Department Reports</h1>
          <!-- Department Pill -->
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-indigo-50 text-[#5138ed] border border-indigo-100">
            <span class="w-1.5 h-1.5 rounded-full bg-[#5138ed]"></span>
            {{ deptName }} ({{ deptCode }})
          </span>
          <!-- Active Term Pill -->
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            {{ activeTermBadge }}
          </span>
        </div>
        <p class="text-[13px] text-slate-500 mt-1">Analyze academic performance, examination results, and department progress.</p>
      </div>

      <!-- Reporting Controls Toolbar -->
      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Academic Year Dropdown -->
        <select
          v-model="selectedAcademicYear"
          @change="onFilterChange"
          class="text-[12px] font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-2 min-h-[42px] focus:outline-none focus:border-[#5138ed] cursor-pointer shadow-2xs hover:border-slate-300 transition-colors"
          title="Filter by Academic Year"
        >
          <option value="all">All Academic Years</option>
          <option v-for="yr in filterOptions.academic_years" :key="yr" :value="yr">Year {{ yr }}</option>
        </select>

        <!-- Semester Dropdown -->
        <select
          v-model="selectedSemester"
          @change="onFilterChange"
          class="text-[12px] font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-2 min-h-[42px] focus:outline-none focus:border-[#5138ed] cursor-pointer shadow-2xs hover:border-slate-300 transition-colors"
          title="Filter by Semester"
        >
          <option value="all">All Semesters</option>
          <option v-for="sem in filterOptions.semesters" :key="sem" :value="sem">{{ sem }}</option>
        </select>

        <!-- Period Selector -->
        <select
          v-model="selectedPeriod"
          @change="onFilterChange"
          class="text-[12px] font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-2 min-h-[42px] focus:outline-none focus:border-[#5138ed] cursor-pointer shadow-2xs hover:border-slate-300 transition-colors"
          title="Filter by Reporting Period"
        >
          <option value="This Semester">This Semester</option>
          <option value="Last 30 Days">Last 30 Days</option>
          <option value="This Academic Year">This Academic Year</option>
          <option value="All Time">All Time</option>
        </select>

        <!-- Refresh Data Button -->
        <button
          @click="fetchReportData(true)"
          :disabled="isRefreshing || isLoading"
          class="min-h-[42px] w-[42px] bg-white border border-slate-200 hover:border-slate-300 text-slate-600 rounded-xl flex items-center justify-center transition-all hover:bg-slate-50 disabled:opacity-50 cursor-pointer shadow-2xs"
          title="Refresh Reports & Analytics"
        >
          <svg :class="['w-4 h-4', isRefreshing && 'animate-spin text-[#5138ed]']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
        </button>

        <!-- Print Shortcut Button -->
        <button
          @click="handlePrint"
          class="min-h-[42px] px-3 py-2 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 hover:text-slate-900 rounded-xl text-[12px] font-bold flex items-center gap-1.5 transition-all shadow-2xs cursor-pointer"
          title="Print official report view"
        >
          <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
          </svg>
          <span class="hidden sm:inline">Print</span>
        </button>

        <!-- Export Dropdown Container -->
        <div class="relative export-dropdown-container">
          <div class="inline-flex rounded-xl shadow-xs">
            <button
              type="button"
              @click="showExportModal = true"
              :disabled="isExporting"
              class="flex items-center gap-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-[13px] font-bold pl-4 pr-3 py-2.5 min-h-[42px] rounded-l-xl transition-all cursor-pointer disabled:opacity-70 border-r border-indigo-400/30"
            >
              <svg v-if="!isExporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              <svg v-else class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ isExporting ? 'Exporting...' : 'Export' }}</span>
            </button>

            <button
              type="button"
              @click.stop="toggleExportDropdown"
              :disabled="isExporting"
              class="bg-[#5138ed] hover:bg-indigo-700 text-white px-2.5 py-2.5 min-h-[42px] rounded-r-xl transition-all cursor-pointer disabled:opacity-70"
              title="Select Export Format"
            >
              <svg class="w-3.5 h-3.5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
          </div>

          <!-- Quick Dropdown Menu -->
          <div
            v-if="showExportDropdown"
            class="absolute right-0 mt-2 w-56 bg-white border border-slate-100 rounded-xl shadow-xl py-1.5 z-50 transition-all text-slate-700 animate-in fade-in zoom-in-95 duration-150"
          >
            <div class="px-3.5 py-2 border-b border-slate-100 mb-1">
              <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Select Export Format</p>
            </div>
            <button
              type="button"
              @click="handleExport('pdf')"
              class="w-full flex items-center gap-3 px-3.5 py-2.5 hover:bg-slate-50 text-left font-medium text-slate-700 hover:text-[#5138ed] transition-colors cursor-pointer"
            >
              <div class="w-7 h-7 rounded-md bg-rose-50 flex items-center justify-center text-rose-500 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <p class="font-bold leading-tight text-[12.5px]">PDF Document</p>
                <p class="text-[10px] text-slate-400">Formal academic report (.pdf)</p>
              </div>
            </button>
            <button
              type="button"
              @click="handleExport('excel')"
              class="w-full flex items-center gap-3 px-3.5 py-2.5 hover:bg-slate-50 text-left font-medium text-slate-700 hover:text-emerald-600 transition-colors cursor-pointer"
            >
              <div class="w-7 h-7 rounded-md bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div>
                <p class="font-bold leading-tight text-[12.5px]">Excel Spreadsheet</p>
                <p class="text-[10px] text-slate-400">Data analysis table (.xlsx)</p>
              </div>
            </button>
            <button
              type="button"
              @click="handleExport('csv')"
              class="w-full flex items-center gap-3 px-3.5 py-2.5 hover:bg-slate-50 text-left font-medium text-slate-700 hover:text-sky-600 transition-colors cursor-pointer"
            >
              <div class="w-7 h-7 rounded-md bg-sky-50 flex items-center justify-center text-sky-600 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
              </div>
              <div>
                <p class="font-bold leading-tight text-[12.5px]">CSV Spreadsheet</p>
                <p class="text-[10px] text-slate-400">Raw dataset (.csv)</p>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         4 EXECUTIVE SUMMARY KPI CARDS (Real Database Scoped)
    ══════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 print:grid-cols-4">
      <template v-if="isLoading">
        <div v-for="i in 4" :key="'kpi-sk-'+i" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs animate-pulse flex items-center gap-4">
          <div class="w-12 h-12 bg-slate-100 rounded-xl"></div>
          <div class="flex-1 space-y-2">
            <div class="h-3 bg-slate-100 rounded w-24"></div>
            <div class="h-6 bg-slate-100 rounded w-16"></div>
          </div>
        </div>
      </template>

      <template v-else>
        <div
          v-for="kpi in kpis"
          :key="kpi.label"
          @click="navigateTo(kpi.route)"
          class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-4 sm:p-5 flex items-center gap-4 hover:shadow-md hover:border-indigo-200 transition-all cursor-pointer relative overflow-hidden group"
          :title="`Click to drill down into ${kpi.label}`"
        >
          <div :class="[kpi.bg, 'w-12 h-12 rounded-xl flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform']">
            <svg class="w-6 h-6" :class="kpi.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="kpi.icon"/>
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">{{ kpi.label }}</p>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[20px] sm:text-[22px] font-black text-slate-900 tracking-tight">{{ kpi.value }}</span>
              <span
                :class="[
                  kpi.trend === 'up' ? 'text-emerald-700 bg-emerald-50 border border-emerald-100' : 'text-rose-700 bg-rose-50 border border-rose-100',
                  'text-[10px] font-bold px-2 py-0.5 rounded-md inline-block whitespace-nowrap'
                ]"
              >
                {{ kpi.change }}
              </span>
            </div>
          </div>
          <!-- Subtle Drilldown Indicator -->
          <div class="opacity-0 group-hover:opacity-100 transition-opacity text-slate-300 pr-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
          </div>
        </div>
      </template>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         REPORT TYPE NAVIGATION TABS (Segmented Control)
    ══════════════════════════════════════════════════════════ -->
    <div class="flex items-center gap-1.5 p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200/70 overflow-x-auto min-w-0 print:hidden">
      <button
        @click="activeTab = 'overview'"
        :class="[
          'px-4 py-2.5 rounded-xl text-[12.5px] font-bold transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer',
          activeTab === 'overview' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
        ]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <span>Department Overview</span>
      </button>

      <button
        @click="activeTab = 'courses'"
        :class="[
          'px-4 py-2.5 rounded-xl text-[12.5px] font-bold transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer',
          activeTab === 'courses' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
        ]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <span>Course Performance</span>
        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-200/80 text-slate-700 font-mono">{{ coursePerformance.length }}</span>
      </button>

      <button
        @click="activeTab = 'exams'"
        :class="[
          'px-4 py-2.5 rounded-xl text-[12.5px] font-bold transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer',
          activeTab === 'exams' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
        ]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
        <span>Examination Results</span>
        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-200/80 text-slate-700 font-mono">{{ examResults.length }}</span>
      </button>

      <button
        @click="activeTab = 'students'"
        :class="[
          'px-4 py-2.5 rounded-xl text-[12.5px] font-bold transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer',
          activeTab === 'students' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
        ]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span>Student Performance</span>
        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-200/80 text-slate-700 font-mono">{{ studentPerformance.length }}</span>
      </button>

      <button
        @click="activeTab = 'instructors'"
        :class="[
          'px-4 py-2.5 rounded-xl text-[12.5px] font-bold transition-all whitespace-nowrap flex items-center gap-2 cursor-pointer',
          activeTab === 'instructors' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
        ]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
        <span>Instructor Summary</span>
        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-200/80 text-slate-700 font-mono">{{ instructorSummary.length }}</span>
      </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         TAB 1: DEPARTMENT OVERVIEW (Analytics & Performance)
    ══════════════════════════════════════════════════════════ -->
    <div v-if="activeTab === 'overview'" class="space-y-6">

      <!-- Performance Trends & Grade Breakdown -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Exam Performance Trend Chart -->
        <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 sm:p-6 flex flex-col justify-between">
          <div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
              <div>
                <h3 class="text-[15px] font-bold text-slate-900">Exam Performance Trend</h3>
                <p class="text-[12px] text-slate-400 mt-0.5">Average score trajectory across all {{ deptName }} evaluations</p>
              </div>

              <!-- Trend Toggle & Legend -->
              <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                  <span class="w-2.5 h-2.5 rounded-full bg-[#5138ed]"></span>
                  <span>Average Score (%)</span>
                </div>
                <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-[11px]">
                  <button
                    @click="trendViewMode = 'monthly'"
                    :class="['px-2.5 py-1 rounded-md font-bold transition-all cursor-pointer', trendViewMode === 'monthly' ? 'bg-white text-[#5138ed] shadow-2xs' : 'text-slate-500 hover:text-slate-800']"
                  >
                    Monthly
                  </button>
                  <button
                    @click="trendViewMode = 'exams'"
                    :class="['px-2.5 py-1 rounded-md font-bold transition-all cursor-pointer', trendViewMode === 'exams' ? 'bg-white text-[#5138ed] shadow-2xs' : 'text-slate-500 hover:text-slate-800']"
                  >
                    By Exam
                  </button>
                </div>
              </div>
            </div>

            <!-- View 1: Monthly Chart View -->
            <div v-if="trendViewMode === 'monthly'" class="relative pt-3 pb-2 overflow-x-auto min-w-0">
              <div class="min-w-[440px]">
                <!-- Interactive Tooltip Popup -->
                <div
                  v-if="activeHoverPoint"
                  class="absolute z-10 pointer-events-none bg-slate-900 text-white text-[11px] font-medium px-3 py-2 rounded-xl shadow-xl -translate-x-1/2 -translate-y-full transition-all animate-in fade-in"
                  :style="{ left: `${(activeHoverPoint.x / 550) * 100}%`, top: `${(activeHoverPoint.y / 200) * 100 - 12}%` }"
                >
                  <div class="font-bold text-center text-white">{{ activeHoverPoint.month }} 2026</div>
                  <div class="text-indigo-300 font-semibold" v-if="activeHoverPoint.has_data">
                    Avg Score: {{ activeHoverPoint.avg_score }}%
                  </div>
                  <div class="text-slate-400 text-[10px]" v-if="activeHoverPoint.attempts_count > 0">
                    {{ activeHoverPoint.attempts_count }} student attempts
                  </div>
                  <div class="text-slate-400 text-[10px] italic" v-else>
                    No department exams in this month
                  </div>
                </div>

                <!-- SVG Area & Line Chart -->
                <svg viewBox="0 0 550 200" class="w-full h-44 sm:h-52 overflow-visible" preserveAspectRatio="none">
                  <defs>
                    <linearGradient id="trendGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#5138ed" stop-opacity="0.22"/>
                      <stop offset="100%" stop-color="#5138ed" stop-opacity="0.01"/>
                    </linearGradient>
                  </defs>

                  <!-- Subtle Grid lines -->
                  <line x1="0" y1="40" x2="550" y2="40" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3 3"/>
                  <line x1="0" y1="90" x2="550" y2="90" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3 3"/>
                  <line x1="0" y1="140" x2="550" y2="140" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3 3"/>

                  <!-- Filled Gradient Area -->
                  <path :d="svgFill" fill="url(#trendGrad)"/>

                  <!-- Smooth Line Path -->
                  <path :d="svgLine" fill="none" stroke="#5138ed" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>

                  <!-- Data Dots -->
                  <g v-for="(p, i) in chartPoints" :key="i">
                    <circle
                      :cx="p[0]"
                      :cy="p[1]"
                      :r="trendData[i]?.has_data ? 5.5 : 3.5"
                      :fill="trendData[i]?.has_data ? '#5138ed' : '#ffffff'"
                      :stroke="trendData[i]?.has_data ? '#ffffff' : '#cbd5e1'"
                      :stroke-width="trendData[i]?.has_data ? 2.5 : 1.5"
                      class="cursor-pointer hover:scale-125 transition-transform"
                      @mouseenter="activeHoverPoint = { month: trendData[i]?.month || '', avg_score: trendData[i]?.avg_score || 0, attempts_count: trendData[i]?.attempts_count || 0, has_data: !!trendData[i]?.has_data, x: p[0], y: p[1] }"
                      @mouseleave="activeHoverPoint = null"
                    />
                  </g>
                </svg>

                <!-- Month Axis Labels -->
                <div class="flex justify-between mt-3 px-1 text-[11px] text-slate-400 font-semibold">
                  <span
                    v-for="item in trendData"
                    :key="item.month"
                    :class="item.has_data ? 'text-[#5138ed] font-bold' : 'text-slate-400'"
                  >
                    {{ item.month }}
                  </span>
                </div>
              </div>
            </div>

            <!-- View 2: Chronological Exams View -->
            <div v-else class="pt-2 pb-1 space-y-3">
              <div v-if="chronologicalTrend.length === 0" class="text-center py-10 text-slate-400 text-[13px]">
                No evaluated examinations found in this period.
              </div>
              <div v-else class="space-y-2.5 max-h-[220px] overflow-y-auto pr-1">
                <div
                  v-for="ex in chronologicalTrend"
                  :key="ex.exam_id"
                  class="flex items-center justify-between p-3 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-white hover:border-indigo-100 transition-all text-[12px]"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <span class="font-mono text-[11px] text-slate-400 bg-white px-2 py-1 rounded-md border border-slate-200 shrink-0">
                      {{ ex.date }}
                    </span>
                    <div class="min-w-0">
                      <p class="font-bold text-slate-800 truncate">{{ ex.title }}</p>
                      <p class="text-[11px] text-slate-400 font-mono">{{ ex.course_code }} &bull; {{ ex.attempts_count }} attempts</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-3 shrink-0">
                    <div class="text-right">
                      <span class="font-bold text-slate-900 block">{{ ex.avg_score }}%</span>
                      <span class="text-[10px] text-emerald-600 font-bold">{{ ex.pass_rate }}% pass</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Bottom Metric Note -->
          <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
            <span class="flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              Verified against {{ gradeDistribution.total_all }} department examination attempts.
            </span>
            <span class="font-mono text-slate-400">Target Threshold: &ge; 50.0% Passing</span>
          </div>
        </div>

        <!-- Right 1 Col: Grade Distribution & Pass/Fail Analysis -->
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 sm:p-6 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-[15px] font-bold text-slate-900">Grade Breakdown</h3>
              <span class="text-[11px] font-semibold text-slate-400">{{ gradeDistribution.total_all }} Total Records</span>
            </div>

            <!-- Pass vs Fail Stacked Visual Proportion -->
            <div class="space-y-1.5 mb-5">
              <div class="flex items-center justify-between text-[11px] font-bold">
                <span class="text-emerald-700">Passed: {{ gradeDistribution.passed_count }} ({{ gradeDistribution.pass_rate }}%)</span>
                <span class="text-rose-700">Failed: {{ gradeDistribution.failed_count }}</span>
              </div>
              <div class="h-3 w-full bg-slate-100 rounded-full flex overflow-hidden">
                <div
                  class="bg-emerald-500 h-full transition-all duration-500"
                  :style="{ width: `${gradeDistribution.total_all > 0 ? (gradeDistribution.passed_count / gradeDistribution.total_all) * 100 : 0}%` }"
                  :title="`Passed: ${gradeDistribution.passed_count}`"
                ></div>
                <div
                  class="bg-rose-500 h-full transition-all duration-500"
                  :style="{ width: `${gradeDistribution.total_all > 0 ? (gradeDistribution.failed_count / gradeDistribution.total_all) * 100 : 0}%` }"
                  :title="`Failed: ${gradeDistribution.failed_count}`"
                ></div>
                <div
                  class="bg-slate-300 h-full transition-all duration-500"
                  :style="{ width: `${gradeDistribution.total_all > 0 ? (gradeDistribution.in_progress / gradeDistribution.total_all) * 100 : 0}%` }"
                  :title="`In Progress: ${gradeDistribution.in_progress}`"
                ></div>
              </div>
              <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1">
                <span>Passing Grade: D (&ge;50%)</span>
                <span v-if="gradeDistribution.in_progress > 0">{{ gradeDistribution.in_progress }} In Progress</span>
              </div>
            </div>

            <!-- Grade Tier Cards (A, B, C, D, F) -->
            <div class="space-y-2">
              <div class="flex items-center justify-between p-2.5 rounded-xl bg-emerald-50/60 border border-emerald-100/80">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-emerald-500 text-white font-bold text-[11px] flex items-center justify-center">A</span>
                  <span class="text-[12px] font-semibold text-slate-700">Excellent (85% &ndash; 100%)</span>
                </div>
                <span class="text-[13px] font-bold text-emerald-800">{{ gradeDistribution.A }}</span>
              </div>

              <div class="flex items-center justify-between p-2.5 rounded-xl bg-sky-50/60 border border-sky-100/80">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-sky-500 text-white font-bold text-[11px] flex items-center justify-center">B</span>
                  <span class="text-[12px] font-semibold text-slate-700">Good (70% &ndash; 84%)</span>
                </div>
                <span class="text-[13px] font-bold text-sky-800">{{ gradeDistribution.B }}</span>
              </div>

              <div class="flex items-center justify-between p-2.5 rounded-xl bg-indigo-50/60 border border-indigo-100/80">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-indigo-500 text-white font-bold text-[11px] flex items-center justify-center">C</span>
                  <span class="text-[12px] font-semibold text-slate-700">Satisfactory (60% &ndash; 69%)</span>
                </div>
                <span class="text-[13px] font-bold text-indigo-800">{{ gradeDistribution.C }}</span>
              </div>

              <div class="flex items-center justify-between p-2.5 rounded-xl bg-amber-50/60 border border-amber-100/80">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-amber-500 text-white font-bold text-[11px] flex items-center justify-center">D</span>
                  <span class="text-[12px] font-semibold text-slate-700">Bare Pass (50% &ndash; 59%)</span>
                </div>
                <span class="text-[13px] font-bold text-amber-800">{{ gradeDistribution.D }}</span>
              </div>

              <div class="flex items-center justify-between p-2.5 rounded-xl bg-rose-50/60 border border-rose-100/80">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-rose-500 text-white font-bold text-[11px] flex items-center justify-center">F</span>
                  <span class="text-[12px] font-semibold text-slate-700">Failing Grade (&lt; 50%)</span>
                </div>
                <span class="text-[13px] font-bold text-rose-800">{{ gradeDistribution.F }}</span>
              </div>
            </div>
          </div>

          <button
            @click="activeTab = 'students'"
            class="w-full mt-4 py-2.5 px-3 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-[12px] font-bold rounded-xl transition-all flex items-center justify-center gap-1.5 cursor-pointer"
          >
            <span>View All Student Result Records</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
        </div>

      </div>

      <!-- Quick Courses Breakdown & Action Summary -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Course Performance Summary Cards -->
        <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-[15px] font-bold text-slate-900">Course Performance Comparison</h3>
              <p class="text-[12px] text-slate-400 mt-0.5">Evaluation outcomes categorized by department course</p>
            </div>
            <button
              @click="activeTab = 'courses'"
              class="text-[12px] font-bold text-[#5138ed] hover:underline flex items-center gap-1 cursor-pointer"
            >
              <span>See Full Report</span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>

          <div v-if="coursePerformance.length === 0" class="text-center py-8 text-slate-400 text-[13px]">
            No courses recorded for this department.
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="c in coursePerformance.slice(0, 4)"
              :key="c.code + c.name"
              class="p-4 rounded-xl border border-slate-100 hover:border-indigo-100 hover:bg-indigo-50/20 transition-all space-y-2"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-[13px] text-slate-800 truncate">{{ c.name }}</span>
                    <span class="text-[10px] font-mono font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">{{ c.code }}</span>
                  </div>
                  <p class="text-[11px] text-slate-400 mt-0.5">
                    Instructor: <span class="font-semibold text-slate-600">{{ c.instructor }}</span> &bull; {{ c.attempts }} attempts &bull; {{ c.credits }} credits
                  </p>
                </div>

                <div class="text-right shrink-0">
                  <span class="text-[15px] font-black text-slate-900">{{ c.avgScore }}%</span>
                  <p class="text-[10px] font-bold" :class="c.passRate >= 50 ? 'text-emerald-600' : 'text-rose-600'">
                    {{ c.passRate }}% pass rate
                  </p>
                </div>
              </div>

              <!-- Visual Progress Bar -->
              <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div
                  :class="[
                    c.passRate >= 70 ? 'bg-emerald-500' : c.passRate >= 50 ? 'bg-[#5138ed]' : 'bg-rose-500',
                    'h-2 rounded-full transition-all duration-500'
                  ]"
                  :style="{ width: `${c.passRate}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right 1 Col: Quick Academic Intelligence Insights -->
        <div class="bg-gradient-to-br from-[#5138ed]/5 to-indigo-50/50 border border-indigo-100 rounded-2xl p-5 sm:p-6 flex flex-col justify-between">
          <div class="space-y-4">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-[#5138ed] text-white flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
              </div>
              <h3 class="text-[14px] font-bold text-slate-900">Academic Intelligence</h3>
            </div>

            <div class="space-y-2.5 text-[12px] text-slate-600">
              <div class="p-3 bg-white rounded-xl border border-indigo-100 shadow-2xs space-y-1">
                <p class="font-bold text-slate-800">Pass Rate Evaluation</p>
                <p class="text-[11px] leading-relaxed text-slate-500">
                  Department pass rate currently stands at <strong class="text-slate-800">{{ gradeDistribution.pass_rate }}%</strong> with {{ gradeDistribution.passed_count }} passed and {{ gradeDistribution.failed_count }} failed attempts.
                </p>
              </div>

              <div class="p-3 bg-white rounded-xl border border-indigo-100 shadow-2xs space-y-1">
                <p class="font-bold text-slate-800">Department Head Actions</p>
                <p class="text-[11px] leading-relaxed text-slate-500">
                  Ensure all instructors submit semester results and verify exam schedules for the active academic term.
                </p>
              </div>
            </div>
          </div>

          <!-- Shortcuts -->
          <div class="pt-4 border-t border-indigo-100/80 space-y-2">
            <button
              @click="router.push('/dept-head/schedule')"
              class="w-full py-2 px-3 text-[12px] font-bold text-[#5138ed] hover:bg-white rounded-xl transition-all text-left flex items-center justify-between cursor-pointer"
            >
              <span>View Academic Calendar &amp; Schedule</span>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button
              @click="router.push('/dept-head/results')"
              class="w-full py-2 px-3 text-[12px] font-bold text-[#5138ed] hover:bg-white rounded-xl transition-all text-left flex items-center justify-between cursor-pointer"
            >
              <span>Examine Results Module</span>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>

      </div>

    </div>

    <!-- ══════════════════════════════════════════════════════════
         TAB 2: COURSE PERFORMANCE DETAILED REPORT
    ══════════════════════════════════════════════════════════ -->
    <div v-else-if="activeTab === 'courses'" class="space-y-4">
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
        
        <!-- Toolbar for Courses -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
          <div>
            <h2 class="text-[16px] font-bold text-slate-900">Course Performance Report</h2>
            <p class="text-[12px] text-slate-400">Detailed examination outcomes for all {{ deptName }} courses</p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <!-- Search Input -->
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search courses or instructor..."
                class="w-56 text-[12px] pl-8 pr-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] text-slate-700 bg-white"
              />
              <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
            </div>

            <!-- Sorting Dropdown -->
            <select
              v-model="sortCourseBy"
              class="text-[12px] font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-[#5138ed] cursor-pointer"
            >
              <option value="avgScore_desc">Highest Average Score</option>
              <option value="avgScore_asc">Lowest Average Score</option>
              <option value="passRate_desc">Highest Pass Rate</option>
              <option value="passRate_asc">Lowest Pass Rate</option>
              <option value="name_asc">Course Name (A-Z)</option>
            </select>
          </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto min-w-0 pt-2">
          <table class="w-full text-left text-[12px]">
            <thead>
              <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                <th class="py-3 px-3">Course Code</th>
                <th class="py-3 px-3">Course Title</th>
                <th class="py-3 px-3">Instructor</th>
                <th class="py-3 px-3 text-center">Credits</th>
                <th class="py-3 px-3 text-center">Attempts</th>
                <th class="py-3 px-3 text-center">Passed</th>
                <th class="py-3 px-3 text-center">Failed</th>
                <th class="py-3 px-3 text-center">Avg Score</th>
                <th class="py-3 px-3 text-center">Pass Rate</th>
                <th class="py-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="filteredCourses.length === 0">
                <td colspan="10" class="py-12 text-center text-slate-400">
                  <p class="font-bold text-slate-600">No courses match your filter</p>
                  <button @click="resetFilters" class="mt-2 text-[#5138ed] font-bold text-[12px] hover:underline cursor-pointer">Reset all filters</button>
                </td>
              </tr>
              <tr
                v-for="c in filteredCourses"
                :key="c.code"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-3.5 px-3 font-mono font-bold text-[#5138ed]">{{ c.code }}</td>
                <td class="py-3.5 px-3 font-bold text-slate-800">{{ c.name }}</td>
                <td class="py-3.5 px-3 text-slate-600">{{ c.instructor }}</td>
                <td class="py-3.5 px-3 text-center font-mono">{{ c.credits }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-700">{{ c.attempts }}</td>
                <td class="py-3.5 px-3 text-center text-emerald-600 font-bold">{{ c.passed }}</td>
                <td class="py-3.5 px-3 text-center text-rose-600 font-bold">{{ c.failed }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-900">{{ c.avgScore }}%</td>
                <td class="py-3.5 px-3 text-center">
                  <span
                    :class="[
                      c.passRate >= 70 ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : c.passRate >= 50 ? 'bg-indigo-50 text-[#5138ed] border-indigo-100' : 'bg-rose-50 text-rose-700 border-rose-100',
                      'px-2 py-0.5 rounded-md text-[11px] font-bold border'
                    ]"
                  >
                    {{ c.passRate }}%
                  </span>
                </td>
                <td class="py-3.5 px-3 text-right">
                  <button
                    @click="router.push('/dept-head/courses')"
                    class="px-2.5 py-1 text-[11px] font-bold text-slate-600 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer"
                  >
                    View Course
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         TAB 3: EXAMINATION RESULTS REPORT
    ══════════════════════════════════════════════════════════ -->
    <div v-else-if="activeTab === 'exams'" class="space-y-4">
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
        
        <!-- Toolbar for Exams -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
          <div>
            <h2 class="text-[16px] font-bold text-slate-900">Department Examination Results</h2>
            <p class="text-[12px] text-slate-400">All examinations evaluated within {{ deptName }}</p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search exam title or code..."
                class="w-56 text-[12px] pl-8 pr-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] text-slate-700 bg-white"
              />
              <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
            </div>

            <select
              v-model="sortExamBy"
              class="text-[12px] font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-[#5138ed] cursor-pointer"
            >
              <option value="date_desc">Latest Scheduled First</option>
              <option value="attempts_desc">Most Attempts</option>
              <option value="passRate_desc">Highest Pass Rate</option>
              <option value="avg_desc">Highest Average Score</option>
            </select>
          </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto min-w-0 pt-2">
          <table class="w-full text-left text-[12px]">
            <thead>
              <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                <th class="py-3 px-3">Exam Code</th>
                <th class="py-3 px-3">Title</th>
                <th class="py-3 px-3">Course</th>
                <th class="py-3 px-3">Scheduled Date</th>
                <th class="py-3 px-3 text-center">Attempts</th>
                <th class="py-3 px-3 text-center">Passed</th>
                <th class="py-3 px-3 text-center">Failed</th>
                <th class="py-3 px-3 text-center">Avg Score</th>
                <th class="py-3 px-3 text-center">Pass Rate</th>
                <th class="py-3 px-3 text-center">Status</th>
                <th class="py-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="filteredExams.length === 0">
                <td colspan="11" class="py-12 text-center text-slate-400">
                  <p class="font-bold text-slate-600">No examination records found</p>
                  <button @click="resetFilters" class="mt-2 text-[#5138ed] font-bold text-[12px] hover:underline cursor-pointer">Reset filters</button>
                </td>
              </tr>
              <tr
                v-for="e in filteredExams"
                :key="e.id"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-3.5 px-3 font-mono font-bold text-[#5138ed]">{{ e.code }}</td>
                <td class="py-3.5 px-3 font-bold text-slate-800">{{ e.title }}</td>
                <td class="py-3.5 px-3 font-mono text-slate-600">{{ e.course_code }}</td>
                <td class="py-3.5 px-3 text-slate-500 whitespace-nowrap">{{ e.scheduled_at }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-800">{{ e.attempts_count }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-emerald-600">{{ e.passed_count }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-rose-600">{{ e.failed_count }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-900">{{ e.avg_score }}%</td>
                <td class="py-3.5 px-3 text-center">
                  <span
                    :class="[
                      e.pass_rate >= 70 ? 'bg-emerald-50 text-emerald-700' : e.pass_rate >= 50 ? 'bg-indigo-50 text-[#5138ed]' : 'bg-rose-50 text-rose-700',
                      'px-2 py-0.5 rounded text-[11px] font-bold'
                    ]"
                  >
                    {{ e.pass_rate }}%
                  </span>
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600">
                    {{ e.status }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-right">
                  <button
                    @click="router.push('/dept-head/results')"
                    class="px-2.5 py-1 text-[11px] font-bold text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer"
                  >
                    Results
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         TAB 4: STUDENT PERFORMANCE ANALYSIS
    ══════════════════════════════════════════════════════════ -->
    <div v-else-if="activeTab === 'students'" class="space-y-4">
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
        
        <!-- Toolbar for Students -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
          <div>
            <h2 class="text-[16px] font-bold text-slate-900">Student Performance Analysis</h2>
            <p class="text-[12px] text-slate-400">Individual student attempt scores and letter grades</p>
          </div>

          <div class="flex flex-wrap items-center gap-2.5">
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search student name or ID..."
                class="w-56 text-[12px] pl-8 pr-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] text-slate-700 bg-white"
              />
              <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
            </div>

            <!-- Grade Filter -->
            <select
              v-model="studentGradeFilter"
              class="text-[12px] font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-[#5138ed] cursor-pointer"
            >
              <option value="all">All Outcomes</option>
              <option value="passed">Passed (&ge;50%)</option>
              <option value="failed">Failed (&lt;50%)</option>
              <option value="in_progress">In Progress</option>
            </select>
          </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto min-w-0 pt-2">
          <table class="w-full text-left text-[12px]">
            <thead>
              <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                <th class="py-3 px-3">Student Name</th>
                <th class="py-3 px-3">Student ID</th>
                <th class="py-3 px-3">Exam Title</th>
                <th class="py-3 px-3">Course</th>
                <th class="py-3 px-3 text-center">Score</th>
                <th class="py-3 px-3 text-center">Percentage</th>
                <th class="py-3 px-3 text-center">Grade</th>
                <th class="py-3 px-3 text-center">Status</th>
                <th class="py-3 px-3 text-right">Submitted</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="filteredStudents.length === 0">
                <td colspan="9" class="py-12 text-center text-slate-400">
                  <p class="font-bold text-slate-600">No student attempts matching filters</p>
                  <button @click="resetFilters" class="mt-2 text-[#5138ed] font-bold text-[12px] hover:underline cursor-pointer">Reset filters</button>
                </td>
              </tr>
              <tr
                v-for="s in filteredStudents"
                :key="s.id"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-3.5 px-3">
                  <p class="font-bold text-slate-800">{{ s.student_name }}</p>
                  <p class="text-[10px] text-slate-400">{{ s.email }}</p>
                </td>
                <td class="py-3.5 px-3 font-mono font-bold text-slate-600">{{ s.student_id }}</td>
                <td class="py-3.5 px-3 font-medium text-slate-700">{{ s.exam_title }}</td>
                <td class="py-3.5 px-3 font-mono text-slate-600">{{ s.course_code }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-800">{{ s.score }} / {{ s.total_marks }}</td>
                <td class="py-3.5 px-3 text-center font-bold" :class="s.percentage !== null && s.percentage >= 50 ? 'text-emerald-600' : 'text-rose-600'">
                  {{ s.percentage !== null ? s.percentage + '%' : 'Pending' }}
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span
                    :class="[
                      s.grade === 'A' ? 'bg-emerald-500 text-white' : s.grade === 'B+' || s.grade === 'B' ? 'bg-sky-500 text-white' : s.grade === 'C' ? 'bg-indigo-500 text-white' : s.grade === 'D' ? 'bg-amber-500 text-white' : s.grade === 'F' ? 'bg-rose-500 text-white' : 'bg-slate-400 text-white',
                      'w-7 h-7 rounded-lg inline-flex items-center justify-center font-bold text-[11px]'
                    ]"
                  >
                    {{ s.grade }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600">
                    {{ s.status }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-right text-slate-400 whitespace-nowrap text-[11px]">
                  {{ s.submitted_at }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         TAB 5: INSTRUCTOR PERFORMANCE SUMMARY
    ══════════════════════════════════════════════════════════ -->
    <div v-else-if="activeTab === 'instructors'" class="space-y-4">
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5">
        
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h2 class="text-[16px] font-bold text-slate-900">Instructor Academic Summary</h2>
            <p class="text-[12px] text-slate-400">Department faculty workload, examinations conducted, and student outcomes</p>
          </div>
          <button
            @click="router.push('/dept-head/instructors')"
            class="px-3 py-1.5 text-[12px] font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors cursor-pointer"
          >
            Manage Instructors
          </button>
        </div>

        <div class="overflow-x-auto min-w-0 pt-2">
          <table class="w-full text-left text-[12px]">
            <thead>
              <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                <th class="py-3 px-3">Instructor</th>
                <th class="py-3 px-3">Role</th>
                <th class="py-3 px-3 text-center">Assigned Courses</th>
                <th class="py-3 px-3 text-center">Exams Conducted</th>
                <th class="py-3 px-3 text-center">Student Attempts</th>
                <th class="py-3 px-3 text-center">Average Score</th>
                <th class="py-3 px-3 text-center">Pass Rate</th>
                <th class="py-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr
                v-for="inst in filteredInstructors"
                :key="inst.id"
                class="hover:bg-slate-50/70 transition-colors"
              >
                <td class="py-3.5 px-3">
                  <p class="font-bold text-slate-800">{{ inst.name }}</p>
                  <p class="text-[10px] text-slate-400">{{ inst.email }}</p>
                </td>
                <td class="py-3.5 px-3 text-slate-600">{{ inst.role }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-700">{{ inst.assigned_courses }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-700">{{ inst.exams_conducted }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-700">{{ inst.attempts_count }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-900">{{ inst.avg_score }}%</td>
                <td class="py-3.5 px-3 text-center">
                  <span
                    :class="[
                      inst.pass_rate >= 50 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700',
                      'px-2 py-0.5 rounded text-[11px] font-bold'
                    ]"
                  >
                    {{ inst.pass_rate }}%
                  </span>
                </td>
                <td class="py-3.5 px-3 text-right">
                  <button
                    @click="router.push('/dept-head/instructors')"
                    class="px-2.5 py-1 text-[11px] font-bold text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer"
                  >
                    Profile
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         EXPORT MODAL DIALOG
    ══════════════════════════════════════════════════════════ -->
    <div
      v-if="showExportModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-3 sm:p-4 overflow-y-auto print:hidden"
    >
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-[95vw] sm:max-w-md w-full max-h-[92vh] overflow-y-auto p-5 sm:p-6 space-y-4 sm:space-y-5 animate-in fade-in zoom-in-95 duration-200 my-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-[16px] font-bold text-slate-900">Export Department Report</h3>
            <p class="text-[12px] text-slate-500 mt-0.5">{{ deptName }} &bull; {{ selectedPeriod }}</p>
          </div>
          <button
            type="button"
            @click="showExportModal = false"
            class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="space-y-3">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Choose File Format:</p>

          <!-- Format Choice 1: PDF -->
          <label
            @click="selectedExportFormat = 'pdf'"
            class="flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition-all min-h-[44px]"
            :class="selectedExportFormat === 'pdf' ? 'border-[#5138ed] bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50'"
          >
            <input type="radio" v-model="selectedExportFormat" value="pdf" class="hidden" />
            <div class="w-9 h-9 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600 shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-bold text-slate-800">PDF Document (.pdf)</p>
              <p class="text-[11px] text-slate-500">Official formatted academic report with KPIs, tables, and trends</p>
            </div>
            <div v-if="selectedExportFormat === 'pdf'" class="w-5 h-5 rounded-full bg-[#5138ed] flex items-center justify-center text-white shrink-0">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
          </label>

          <!-- Format Choice 2: Excel -->
          <label
            @click="selectedExportFormat = 'excel'"
            class="flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition-all min-h-[44px]"
            :class="selectedExportFormat === 'excel' ? 'border-[#5138ed] bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50'"
          >
            <input type="radio" v-model="selectedExportFormat" value="excel" class="hidden" />
            <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-bold text-slate-800">Excel Spreadsheet (.xlsx)</p>
              <p class="text-[11px] text-slate-500">Spreadsheet table for data analysis and filtering in Microsoft Excel</p>
            </div>
            <div v-if="selectedExportFormat === 'excel'" class="w-5 h-5 rounded-full bg-[#5138ed] flex items-center justify-center text-white shrink-0">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
          </label>

          <!-- Format Choice 3: CSV -->
          <label
            @click="selectedExportFormat = 'csv'"
            class="flex items-center gap-3.5 p-3.5 rounded-xl border cursor-pointer transition-all min-h-[44px]"
            :class="selectedExportFormat === 'csv' ? 'border-[#5138ed] bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50'"
          >
            <input type="radio" v-model="selectedExportFormat" value="csv" class="hidden" />
            <div class="w-9 h-9 rounded-lg bg-sky-50 flex items-center justify-center text-sky-600 shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-bold text-slate-800">CSV Spreadsheet (.csv)</p>
              <p class="text-[11px] text-slate-500">Universal comma-separated text values compatible with any tool</p>
            </div>
            <div v-if="selectedExportFormat === 'csv'" class="w-5 h-5 rounded-full bg-[#5138ed] flex items-center justify-center text-white shrink-0">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
          </label>
        </div>

        <!-- Modal Actions -->
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <button
            type="button"
            @click="showExportModal = false"
            class="px-4 py-2.5 min-h-[42px] text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors cursor-pointer text-center"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleExport()"
            :disabled="isExporting"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[42px] text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all cursor-pointer disabled:opacity-60"
          >
            <svg v-if="isExporting" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ isExporting ? 'Generating File...' : 'Download Report' }}</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
@media print {
  /* Hide all interactive shell items */
  header,
  aside,
  nav,
  .print\:hidden {
    display: none !important;
  }

  body {
    background: white !important;
    color: black !important;
  }

  .print\:block {
    display: block !important;
  }

  .print\:grid-cols-4 {
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  }
}
</style>
