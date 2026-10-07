<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Line, Doughnut, Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement
} from 'chart.js'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement
)

const router = useRouter()
const authStore = useAuthStore()
const settingsStore = useSettingsStore()

const isLoading = ref(true)
const isRefreshing = ref(false)
const errorMessage = ref<string | null>(null)
const activeChartTab = ref<'trend' | 'distribution'>('trend')

// Filter state
const selectedAcademicYear = ref<string>('all')
const selectedSemester = ref<string>('all')

const dashboardData = ref<any>({
  department: {
    id: null,
    name: '',
    raw_name: '',
    code: 'DEPT',
    college: ''
  },
  active_term: {
    academic_year: '2026',
    semester: 'Second Semester'
  },
  available_periods: {
    years: [],
    semesters: []
  },
  stats: [],
  requires_attention: [],
  upcoming_exams: [],
  lineChart: { labels: [], students: [], courses: [] },
  doughnutChart: { totalExams: 0, data: [0, 0, 0, 0], stats: [] },
  performances: [],
  activities: [],
  announcements: []
})

// Fetch real dashboard stats from backend
const fetchDashboardStats = async (isManualRefresh = false) => {
  if (isManualRefresh) {
    isRefreshing.value = true
  } else {
    isLoading.value = true
  }
  errorMessage.value = null

  try {
    const params: Record<string, string> = {}
    if (selectedAcademicYear.value !== 'all') {
      params.academic_year = selectedAcademicYear.value
    }
    if (selectedSemester.value !== 'all') {
      params.semester = selectedSemester.value
    }

    const res = await apiClient.get('/dept-head/dashboard-stats', { params })
    if (res.data?.data) {
      dashboardData.value = res.data.data
    }
  } catch (err: any) {
    console.error('Failed to load department dashboard stats:', err)
    errorMessage.value = err?.response?.data?.message || 'Failed to communicate with university administration server. Please verify network connection and try again.'
  } finally {
    isLoading.value = false
    isRefreshing.value = false
  }
}

onMounted(() => {
  fetchDashboardStats()
  settingsStore.fetchSettings()
})

// Re-fetch when filter period changes
watch([selectedAcademicYear, selectedSemester], () => {
  fetchDashboardStats(true)
})

// Department meta computed
const department = computed(() => dashboardData.value.department || { name: 'Department', code: 'DEPT', college: 'Wollo University' })
const activeTerm = computed(() => dashboardData.value.active_term || { academic_year: '2026', semester: 'Second Semester' })
const attentionItems = computed(() => dashboardData.value.requires_attention || [])

// KPIs computed
const kpiStats = computed(() => {
  if (dashboardData.value.stats && dashboardData.value.stats.length > 0) {
    return dashboardData.value.stats
  }
  return [
    { id: 'students',     label: 'Total Students',           value: '0', change: 'Enrolled in department', bg: 'bg-indigo-50',  ic: 'text-indigo-600',  icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', color: 'text-slate-500' },
    { id: 'instructors',  label: 'Department Faculty',       value: '0', change: 'Active instructors & head', bg: 'bg-emerald-50', ic: 'text-emerald-600', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', color: 'text-slate-500' },
    { id: 'courses',      label: 'Curriculum Courses',       value: '0', change: 'Offered curriculum units', bg: 'bg-sky-50',     ic: 'text-sky-600',     icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', color: 'text-slate-500' },
    { id: 'active_exams', label: 'Active & Scheduled Exams', value: '0', change: 'Department assessments',   bg: 'bg-amber-50',   ic: 'text-amber-600',   icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', color: 'text-slate-500' },
    { id: 'submissions',  label: 'Pending Submissions',      value: '0', change: 'Awaiting sign-off',        bg: 'bg-purple-50',  ic: 'text-purple-600',  icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', color: 'text-slate-500' },
    { id: 'attempts',     label: 'Exam Submissions',         value: '0', change: 'Student attempts recorded', bg: 'bg-rose-50',    ic: 'text-rose-600',    icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', color: 'text-slate-500' },
  ]
})

// KPI Route Navigator
const navigateKpi = (id: string) => {
  switch (id) {
    case 'students':
      router.push('/dept-head/students')
      break
    case 'instructors':
      router.push('/dept-head/instructors')
      break
    case 'courses':
      router.push('/dept-head/courses')
      break
    case 'active_exams':
      router.push('/dept-head/exams')
      break
    case 'submissions':
      router.push('/dept-head/semester-submissions')
      break
    case 'attempts':
      router.push('/dept-head/results')
      break
    default:
      break
  }
}

// Line Chart Data (Trend)
const lineChartData = computed(() => {
  const labels = dashboardData.value.lineChart?.labels?.length
    ? dashboardData.value.lineChart.labels
    : ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct']
  const students = dashboardData.value.lineChart?.students?.length
    ? dashboardData.value.lineChart.students
    : [0, 0, 0, 0, 0, 0, 0]
  const courses = dashboardData.value.lineChart?.courses?.length
    ? dashboardData.value.lineChart.courses
    : [0, 0, 0, 0, 0, 0, 0]

  return {
    labels,
    datasets: [
      {
        label: 'Enrolled Students',
        backgroundColor: 'rgba(81, 56, 237, 0.12)',
        borderColor: '#5138ed',
        borderWidth: 2.5,
        fill: true,
        data: students,
        tension: 0.35,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: '#5138ed',
        pointBorderWidth: 2
      },
      {
        label: 'Curriculum Courses',
        backgroundColor: 'rgba(2, 132, 199, 0.08)',
        borderColor: '#0284c7',
        borderWidth: 2.5,
        fill: true,
        data: courses,
        tension: 0.35,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: '#0284c7',
        pointBorderWidth: 2
      }
    ]
  }
})

const lineChartOptions = computed<any>(() => {
  const students = dashboardData.value.lineChart?.students || [0]
  const courses = dashboardData.value.lineChart?.courses || [0]
  const maxVal = Math.max(...students, ...courses, 5)
  const step = Math.max(1, Math.ceil(maxVal / 4))

  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1e293b',
        titleFont: { size: 12, weight: 'bold' as const },
        bodyFont: { size: 11 },
        padding: 10,
        cornerRadius: 8,
        usePointStyle: true
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        suggestedMax: maxVal + step,
        ticks: { stepSize: step, color: '#94a3b8', font: { size: 11 } },
        border: { display: false },
        grid: { color: '#f1f5f9' }
      },
      x: {
        grid: { display: false },
        ticks: { color: '#64748b', font: { size: 11, weight: 'bold' as const } },
        border: { display: false }
      }
    }
  }
})

// Bar Chart Data (Department Resource Distribution)
const barChartData = computed(() => {
  const students = Number(kpiStats.value.find((k: any) => k.id === 'students')?.value || 0)
  const faculty = Number(kpiStats.value.find((k: any) => k.id === 'instructors')?.value || 0)
  const courses = Number(kpiStats.value.find((k: any) => k.id === 'courses')?.value || 0)
  const exams = Number(kpiStats.value.find((k: any) => k.id === 'active_exams')?.value || 0)
  const attempts = Number(kpiStats.value.find((k: any) => k.id === 'attempts')?.value || 0)

  return {
    labels: ['Students', 'Faculty', 'Courses', 'Exams', 'Attempts'],
    datasets: [
      {
        label: 'Current Academic Unit Counts',
        data: [students, faculty, courses, exams, attempts],
        backgroundColor: [
          '#6366f1', // Indigo
          '#10b981', // Emerald
          '#0284c7', // Sky
          '#f59e0b', // Amber
          '#ec4899'  // Pink/Rose
        ],
        borderRadius: 8,
        borderSkipped: false
      }
    ]
  }
})

const barChartOptions = computed<any>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1e293b',
      padding: 10,
      cornerRadius: 8
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: { precision: 0, color: '#94a3b8', font: { size: 11 } },
      grid: { color: '#f1f5f9' },
      border: { display: false }
    },
    x: {
      grid: { display: false },
      ticks: { color: '#64748b', font: { size: 11, weight: 'bold' as const } },
      border: { display: false }
    }
  }
}))

// Doughnut Chart Data (Exams Status)
const doughnutChartData = computed(() => ({
  labels: ['Upcoming', 'Ongoing', 'Completed', 'Drafts'],
  datasets: [{
    data: dashboardData.value.doughnutChart?.data || [0, 0, 0, 0],
    backgroundColor: ['#6366f1', '#0284c7', '#10b981', '#cbd5e1'],
    borderWidth: 2,
    borderColor: '#ffffff',
    cutout: '72%'
  }]
}))

const doughnutChartOptions: any = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1e293b',
      padding: 10,
      cornerRadius: 8
    }
  }
}

const totalExamsCount = computed(() => dashboardData.value.doughnutChart?.totalExams ?? 0)

const examStats = computed(() => {
  if (dashboardData.value.doughnutChart?.stats?.length) {
    return dashboardData.value.doughnutChart.stats
  }
  return [
    { label: 'Upcoming', val: 0, color: 'bg-indigo-600' },
    { label: 'Ongoing', val: 0, color: 'bg-sky-500' },
    { label: 'Completed', val: 0, color: 'bg-emerald-500' },
    { label: 'Drafts', val: 0, color: 'bg-slate-400' }
  ]
})

// Department Performance Indicators
const performances = computed(() => dashboardData.value.performances || [])

// Upcoming Exams
const upcomingExams = computed(() => dashboardData.value.upcoming_exams || [])

// Recent Announcements / Academic Milestones
const announcements = computed(() => dashboardData.value.announcements || [])

// Recent Department Activities
const activities = computed(() => dashboardData.value.activities || [])

// Quick Actions
const quickActions = [
  { label: 'Manage Instructors', desc: 'Faculty profiles & academic assignments', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', color: 'text-indigo-600', bg: 'bg-indigo-50 border-indigo-100', route: '/dept-head/instructors' },
  { label: 'Manage Students', desc: 'Enrolled students roster & profiles', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', color: 'text-emerald-600', bg: 'bg-emerald-50 border-emerald-100', route: '/dept-head/students' },
  { label: 'Curriculum Courses', desc: 'Department courses & curriculum units', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', color: 'text-sky-600', bg: 'bg-sky-50 border-sky-100', route: '/dept-head/courses' },
  { label: 'Department Exams', desc: 'Schedules, live status & questions', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', color: 'text-amber-600', bg: 'bg-amber-50 border-amber-100', route: '/dept-head/exams' },
  { label: 'Semester Submissions', desc: 'Instructor grade & curriculum sign-off', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', color: 'text-purple-600', bg: 'bg-purple-50 border-purple-100', route: '/dept-head/semester-submissions' },
  { label: 'Department Reports', desc: 'Academic analytics, results & exports', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', color: 'text-rose-600', bg: 'bg-rose-50 border-rose-100', route: '/dept-head/reports' },
]

// Current Date Display
const currentDate = new Date().toLocaleDateString('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric'
})
const currentDay = new Date().toLocaleDateString('en-US', {
  weekday: 'long'
})
</script>

<template>
  <div class="space-y-6 min-w-0 max-w-full pb-10">

    <!-- Error Alert Banner -->
    <div
      v-if="errorMessage"
      class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start justify-between gap-3 text-rose-800"
    >
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div>
          <p class="text-sm font-bold text-rose-900">Communication Error</p>
          <p class="text-xs text-rose-700 mt-0.5">{{ errorMessage }}</p>
        </div>
      </div>
      <button
        @click="fetchDashboardStats()"
        class="px-3 py-1.5 bg-rose-600 text-white text-xs font-semibold rounded-lg hover:bg-rose-700 transition cursor-pointer shrink-0"
      >
        Retry
      </button>
    </div>

    <!-- Header Section -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        
        <!-- Left: Branding & Department Title -->
        <div class="min-w-0 space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-indigo-50 text-[#5138ed] border border-indigo-100">
              <span class="w-1.5 h-1.5 rounded-full bg-[#5138ed] animate-pulse"></span>
              Wollo University • Academic Operations
            </span>
            <span
              v-if="department.code"
              class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200"
            >
              Code: {{ department.code }}
            </span>
            <span
              v-if="activeTerm.academic_year"
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100"
            >
              <svg class="w-3 h-3 mr-1 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              {{ activeTerm.academic_year }} • {{ activeTerm.semester }}
            </span>
          </div>

          <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex flex-wrap items-center gap-2">
              <span>Department Management Center</span>
              <span class="text-slate-400 font-light hidden sm:inline">|</span>
              <span class="text-[#5138ed] font-bold">{{ department.name }}</span>
            </h1>
            <p class="text-xs sm:text-[13px] text-slate-500 mt-1 flex flex-wrap items-center gap-2">
              <span>Department Head: <strong class="text-slate-800 font-semibold">{{ authStore.user?.name || 'Department Head' }}</strong></span>
              <span class="text-slate-300">•</span>
              <span class="text-slate-500">{{ department.college || 'College of Computing and Informatics' }}</span>
            </p>
          </div>
        </div>

        <!-- Right: Filter Toolbar & Refresh Controls -->
        <div class="flex flex-wrap items-center gap-2.5 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100">
          
          <!-- Period Selector -->
          <div class="relative">
            <select
              v-model="selectedAcademicYear"
              class="pl-3 pr-8 py-2 text-xs font-semibold border border-slate-200 rounded-xl text-slate-700 bg-slate-50 hover:bg-white focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition cursor-pointer appearance-none shadow-2xs"
            >
              <option value="all">All Academic Years</option>
              <option
                v-for="year in dashboardData.available_periods?.years"
                :key="year"
                :value="year"
              >
                Year: {{ year }}
              </option>
            </select>
            <svg class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- Refresh Button -->
          <button
            @click="fetchDashboardStats(true)"
            :disabled="isRefreshing || isLoading"
            class="px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs cursor-pointer disabled:opacity-50"
            title="Refresh department metrics"
          >
            <svg
              class="w-3.5 h-3.5 text-slate-500"
              :class="{ 'animate-spin text-[#5138ed]': isRefreshing }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span class="hidden sm:inline">Refresh</span>
          </button>

          <!-- Live Date Badge -->
          <div class="hidden sm:flex flex-col items-end px-3 py-1 bg-slate-50 border border-slate-200 rounded-xl text-right">
            <span class="text-[11px] font-bold text-slate-800 leading-tight">{{ currentDate }}</span>
            <span class="text-[10px] font-medium text-slate-400 leading-tight">{{ currentDay }}</span>
          </div>

        </div>

      </div>
    </div>

    <!-- SKELETON LOADING STATE -->
    <div v-if="isLoading" class="space-y-6 animate-pulse">
      <div class="h-28 bg-slate-200/60 rounded-2xl"></div>
      <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        <div v-for="i in 6" :key="i" class="h-28 bg-slate-200/60 rounded-2xl"></div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 h-80 bg-slate-200/60 rounded-2xl"></div>
        <div class="lg:col-span-4 h-80 bg-slate-200/60 rounded-2xl"></div>
      </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <div v-else class="space-y-6">

      <!-- SECTION: "REQUIRES YOUR ATTENTION" PRIORITY ACTION CENTER -->
      <div v-if="attentionItems.length > 0" class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="flex h-2.5 w-2.5 relative">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
            </span>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Requires Your Attention</h2>
          </div>
          <span class="text-xs text-slate-400 font-medium">{{ attentionItems.length }} action item{{ attentionItems.length > 1 ? 's' : '' }} pending</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="item in attentionItems"
            :key="item.id"
            class="bg-white border rounded-2xl p-4 sm:p-5 shadow-xs transition-all hover:shadow-md flex flex-col justify-between"
            :class="item.severity === 'warning' ? 'border-amber-200 bg-amber-50/20' : 'border-indigo-100 bg-indigo-50/20'"
          >
            <div>
              <div class="flex items-center justify-between mb-2">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                  :class="item.severity === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'"
                >
                  Action Required
                </span>
                <span class="text-sm font-black text-slate-900">{{ item.count }} Pending</span>
              </div>
              <h3 class="text-sm font-bold text-slate-900 leading-snug">{{ item.title }}</h3>
              <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ item.desc }}</p>
            </div>
            
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-end">
              <button
                @click="router.push(item.route)"
                class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                :class="item.severity === 'warning' ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-[#5138ed] hover:bg-indigo-700 text-white'"
              >
                <span>{{ item.action }}</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION: 6 CORE DEPARTMENT KPIS -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
        <div
          v-for="s in kpiStats"
          :key="s.id || s.label"
          @click="navigateKpi(s.id)"
          class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between mb-3">
              <div :class="[s.bg, 'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border border-slate-100']">
                <svg class="w-5 h-5" :class="s.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="s.icon" />
                </svg>
              </div>
              <span
                v-if="s.id === 'submissions' && Number(s.value) > 0"
                class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 animate-pulse"
              >
                Action
              </span>
            </div>
            
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider truncate">{{ s.label }}</p>
            <p class="text-2xl sm:text-[26px] font-black text-slate-900 mt-1 leading-none tracking-tight">{{ s.value }}</p>
          </div>

          <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[11px] text-slate-400 font-medium truncate">{{ s.change }}</span>
            <svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-[#5138ed] group-hover:translate-x-0.5 transition-all shrink-0 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </div>
        </div>
      </div>

      <!-- SECTION: ANALYTICS & VISUAL DIAGNOSTICS (2/3 + 1/3) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">
        
        <!-- Left 8 cols: Department Growth & Resource Distribution -->
        <div class="lg:col-span-8 bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div>
              <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <span>Department Analytics & Trajectory</span>
              </h3>
              <p class="text-xs text-slate-500 mt-0.5">Enrolled students and curriculum delivery trends</p>
            </div>

            <!-- View Tab Switcher -->
            <div class="inline-flex p-1 bg-slate-100 rounded-xl self-start sm:self-auto">
              <button
                @click="activeChartTab = 'trend'"
                class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer"
                :class="activeChartTab === 'trend' ? 'bg-white text-[#5138ed] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
              >
                Monthly Trend
              </button>
              <button
                @click="activeChartTab = 'distribution'"
                class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer"
                :class="activeChartTab === 'distribution' ? 'bg-white text-[#5138ed] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
              >
                Resource Distribution
              </button>
            </div>
          </div>

          <!-- Trend Chart Legend -->
          <div v-if="activeChartTab === 'trend'" class="flex flex-wrap items-center gap-6 mb-4 pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <div class="w-3.5 h-1.5 bg-[#5138ed] rounded-full"></div>
              <span class="text-xs font-bold text-slate-700">Students Enrolled</span>
              <span class="text-xs font-bold text-[#5138ed] ml-1">({{ kpiStats.find((k: any) => k.id === 'students')?.value || 0 }})</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-3.5 h-1.5 bg-[#0284c7] rounded-full"></div>
              <span class="text-xs font-bold text-slate-700">Curriculum Courses</span>
              <span class="text-xs font-bold text-[#0284c7] ml-1">({{ kpiStats.find((k: any) => k.id === 'courses')?.value || 0 }})</span>
            </div>
          </div>

          <!-- Main Chart Container -->
          <div class="h-[250px] sm:h-[280px] w-full min-w-0 flex-1">
            <Line
              v-if="activeChartTab === 'trend'"
              :data="lineChartData"
              :options="lineChartOptions"
            />
            <Bar
              v-else
              :data="barChartData"
              :options="barChartOptions"
            />
          </div>

          <!-- Chart Footer Summary -->
          <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between text-xs text-slate-500 gap-2">
            <span>Academic unit: <strong>{{ department.name }} ({{ department.code }})</strong></span>
            <div class="flex items-center gap-4">
              <button
                @click="router.push('/dept-head/courses')"
                class="text-xs font-bold text-[#5138ed] hover:underline cursor-pointer"
              >
                Manage Courses →
              </button>
              <button
                @click="router.push('/dept-head/students')"
                class="text-xs font-bold text-slate-600 hover:text-slate-900 cursor-pointer"
              >
                View Roster →
              </button>
            </div>
          </div>
        </div>

        <!-- Right 4 cols: Exams Delivery & Status -->
        <div class="lg:col-span-4 bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-bold text-slate-900">Exams Delivery</h3>
                <p class="text-xs text-slate-500 mt-0.5">Status of department examinations</p>
              </div>
              <button
                @click="router.push('/dept-head/exams')"
                class="text-xs font-bold text-[#5138ed] hover:underline cursor-pointer"
              >
                View All
              </button>
            </div>

            <!-- Doughnut Chart Container -->
            <div class="py-4 flex flex-col items-center justify-center">
              <div class="relative w-[150px] h-[150px] shrink-0 flex items-center justify-center">
                <Doughnut
                  v-if="doughnutChartData.datasets[0].data.some((v: number) => v > 0)"
                  :data="doughnutChartData"
                  :options="doughnutChartOptions"
                />
                <div
                  v-else
                  class="w-full h-full rounded-full border-[10px] border-slate-100 flex items-center justify-center"
                ></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                  <span class="text-2xl sm:text-3xl font-black text-slate-900 leading-none">{{ totalExamsCount }}</span>
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1">Exams Total</span>
                </div>
              </div>
            </div>

            <!-- Doughnut Legend Breakdown -->
            <div class="grid grid-cols-2 gap-2.5 mt-2">
              <div
                v-for="stat in examStats"
                :key="stat.label"
                class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/50 flex items-center justify-between"
              >
                <div class="flex items-center gap-2">
                  <div :class="[stat.color, 'w-2.5 h-2.5 rounded-full shrink-0']"></div>
                  <span class="text-xs text-slate-600 font-medium">{{ stat.label }}</span>
                </div>
                <span class="text-xs font-black text-slate-900">{{ stat.val }}</span>
              </div>
            </div>
          </div>

          <button
            @click="router.push('/dept-head/exams')"
            class="w-full mt-6 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-[#5138ed] text-xs font-bold rounded-xl transition flex items-center justify-center gap-2 cursor-pointer border border-indigo-100 shadow-2xs"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span>Open Exam Administration</span>
          </button>
        </div>

      </div>

      <!-- SECTION: EXAMINATIONS SCHEDULE & ACADEMIC MILESTONES (7/12 + 5/12) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">

        <!-- Left 7 cols: Upcoming & Scheduled Exams Table -->
        <div class="lg:col-span-7 bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-bold text-slate-900">Department Examinations</h3>
                <p class="text-xs text-slate-500 mt-0.5">Scheduled and active department exams</p>
              </div>
              <button
                @click="router.push('/dept-head/exams')"
                class="text-xs font-bold text-[#5138ed] hover:underline cursor-pointer"
              >
                View All ({{ totalExamsCount }})
              </button>
            </div>

            <!-- Exams List / Table -->
            <div v-if="upcomingExams.length === 0" class="py-10 text-center text-slate-400">
              <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
              <p class="text-xs font-semibold text-slate-600">No Scheduled Exams</p>
              <p class="text-[11px] text-slate-400 mt-0.5">Exam schedules for this department will appear here.</p>
            </div>

            <div v-else class="overflow-x-auto -mx-5 sm:mx-0">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-2.5 px-3">Course / Exam</th>
                    <th class="py-2.5 px-3">Instructor</th>
                    <th class="py-2.5 px-3">Schedule</th>
                    <th class="py-2.5 px-3">Status</th>
                    <th class="py-2.5 px-3 text-right">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                  <tr
                    v-for="exam in upcomingExams"
                    :key="exam.id"
                    class="hover:bg-slate-50/70 transition-colors group"
                  >
                    <td class="py-3 px-3">
                      <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                          {{ exam.course_code }}
                        </span>
                        <div class="min-w-0">
                          <p class="font-bold text-slate-800 truncate max-w-[150px] sm:max-w-[180px]">{{ exam.title }}</p>
                          <p class="text-[11px] text-slate-400 truncate max-w-[150px] sm:max-w-[180px]">{{ exam.course_title }}</p>
                        </div>
                      </div>
                    </td>
                    <td class="py-3 px-3 text-slate-600 font-medium whitespace-nowrap">
                      {{ exam.instructor_name }}
                    </td>
                    <td class="py-3 px-3 whitespace-nowrap">
                      <p class="font-semibold text-slate-700">{{ exam.scheduled_human }}</p>
                      <p class="text-[11px] text-slate-400">{{ exam.duration_minutes }} mins</p>
                    </td>
                    <td class="py-3 px-3 whitespace-nowrap">
                      <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold capitalize"
                        :class="{
                          'bg-emerald-50 text-emerald-700 border border-emerald-200': exam.status === 'published' || exam.status === 'completed',
                          'bg-indigo-50 text-indigo-700 border border-indigo-200': exam.status === 'scheduled',
                          'bg-slate-100 text-slate-600 border border-slate-200': exam.status === 'draft'
                        }"
                      >
                        {{ exam.status }}
                      </span>
                    </td>
                    <td class="py-3 px-3 text-right whitespace-nowrap">
                      <button
                        @click="router.push('/dept-head/exams')"
                        class="px-2.5 py-1 text-[11px] font-bold text-[#5138ed] hover:bg-indigo-50 rounded-lg transition cursor-pointer"
                      >
                        Inspect
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-400">Total department assessments registered: <strong>{{ totalExamsCount }}</strong></span>
            <button
              @click="router.push('/dept-head/schedule')"
              class="font-bold text-[#5138ed] hover:underline cursor-pointer"
            >
              Exams Calendar →
            </button>
          </div>
        </div>

        <!-- Right 5 cols: Academic Milestones & University Calendar -->
        <div class="lg:col-span-5 bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-bold text-slate-900">Academic Milestones</h3>
                <p class="text-xs text-slate-500 mt-0.5">Key university calendar deadlines</p>
              </div>
              <button
                @click="router.push('/dept-head/schedule')"
                class="text-xs font-bold text-[#5138ed] hover:underline cursor-pointer"
              >
                Calendar
              </button>
            </div>

            <div v-if="announcements.length === 0" class="py-10 text-center text-slate-400">
              <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <p class="text-xs font-semibold text-slate-600">No Scheduled Events</p>
              <p class="text-[11px] text-slate-400 mt-0.5">Academic dates and holidays will appear here.</p>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="event in announcements"
                :key="event.id"
                class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-white hover:border-indigo-100 transition-all flex items-start gap-3"
              >
                <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 text-[#5138ed] flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2 mb-1">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                      :class="event.badge_bg"
                    >
                      {{ event.category_name }}
                    </span>
                    <span class="text-[11px] font-bold text-slate-500 whitespace-nowrap">{{ event.date_formatted }}</span>
                  </div>
                  <h4 class="text-xs font-bold text-slate-800 leading-snug">{{ event.title }}</h4>
                  <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2 leading-relaxed">{{ event.desc }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-400">Institutional Term: <strong>{{ activeTerm.semester }}</strong></span>
            <button
              @click="router.push('/dept-head/schedule')"
              class="font-bold text-[#5138ed] hover:underline cursor-pointer"
            >
              Academic Calendar →
            </button>
          </div>
        </div>

      </div>

      <!-- SECTION: DEPARTMENT PERFORMANCE & AUDIT TRAIL (6/12 + 6/12) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">

        <!-- Left 6 cols: Department Academic Performance -->
        <div class="lg:col-span-6 bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-bold text-slate-900">Academic Indicators</h3>
                <p class="text-xs text-slate-500 mt-0.5">Calculated from department exams & grade records</p>
              </div>
              <button
                @click="router.push('/dept-head/reports')"
                class="text-xs font-bold text-[#5138ed] hover:underline cursor-pointer"
              >
                Detailed Reports
              </button>
            </div>

            <div class="space-y-4">
              <div
                v-for="perf in performances"
                :key="perf.label"
                class="p-3 rounded-xl border border-slate-100 bg-slate-50/50"
              >
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs font-bold text-slate-700">{{ perf.label }}</span>
                  <span class="text-xs font-black text-slate-900">{{ perf.val }}</span>
                </div>
                <div class="w-full h-2 bg-slate-200/70 rounded-full overflow-hidden">
                  <div
                    :class="[perf.color, 'h-full rounded-full transition-all duration-700']"
                    :style="{ width: `${perf.pct}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-400">Source: Official Department Records</span>
            <button
              @click="router.push('/dept-head/results')"
              class="font-bold text-[#5138ed] hover:underline cursor-pointer"
            >
              View Exam Results →
            </button>
          </div>
        </div>

        <!-- Right 6 cols: Department Activity Audit Trail -->
        <div class="lg:col-span-6 bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-bold text-slate-900">Recent Department Activity</h3>
                <p class="text-xs text-slate-500 mt-0.5">Real-time audit log of department actions</p>
              </div>
              <button
                @click="router.push('/dept-head/activity-logs')"
                class="text-xs font-bold text-[#5138ed] hover:underline cursor-pointer"
              >
                Audit Logs
              </button>
            </div>

            <div v-if="activities.length === 0" class="py-10 text-center text-slate-400">
              <p class="text-xs font-semibold text-slate-600">No Recent Activity</p>
              <p class="text-[11px] text-slate-400 mt-0.5">Logs for this department will appear here.</p>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="(act, idx) in activities"
                :key="act.id || idx"
                class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-slate-50/70 transition"
              >
                <div :class="[act.bg, act.color, 'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-xs font-bold']">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-bold text-slate-800 truncate">{{ act.title }}</span>
                    <span class="text-[10px] text-slate-400 font-medium whitespace-nowrap">{{ act.time }}</span>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-0.5 truncate">{{ act.desc }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-400">Security & compliance tracking active</span>
            <button
              @click="router.push('/dept-head/activity-logs')"
              class="font-bold text-[#5138ed] hover:underline cursor-pointer"
            >
              Full Audit Trail →
            </button>
          </div>
        </div>

      </div>

      <!-- SECTION: QUICK ACTIONS COMMAND GRID -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
        <div class="mb-4">
          <h3 class="text-base font-bold text-slate-900">Department Administration Quick Operations</h3>
          <p class="text-xs text-slate-500 mt-0.5">Direct shortcuts to frequent departmental administrative workflows</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3.5">
          <div
            v-for="act in quickActions"
            :key="act.label"
            @click="router.push(act.route)"
            class="p-4 rounded-xl border border-slate-200/80 hover:border-indigo-300 hover:shadow-md transition-all cursor-pointer group flex flex-col justify-between bg-white"
          >
            <div>
              <div :class="[act.bg, act.color, 'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mb-3 border']">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="act.icon" />
                </svg>
              </div>
              <h4 class="text-xs font-bold text-slate-900 group-hover:text-[#5138ed] transition leading-snug">{{ act.label }}</h4>
              <p class="text-[11px] text-slate-500 mt-1 line-clamp-2 leading-relaxed">{{ act.desc }}</p>
            </div>
            
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between">
              <span class="text-[10px] font-bold text-slate-400 group-hover:text-[#5138ed] transition">Access</span>
              <svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-[#5138ed] group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </div>
        </div>
      </div>

    </div>

  </div>
</template>
