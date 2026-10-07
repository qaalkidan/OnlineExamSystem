<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'

const router = useRouter()

// ── State ─────────────────────────────────────────────────────────────────────
const selectedPeriod = ref<'7_days' | '30_days' | '90_days' | 'semester' | 'year'>('30_days')
const isPeriodDropdownOpen = ref(false)
const isLoading = ref(true)
const isRefreshingHealth = ref(false)
const hasError = ref(false)
const errorMessage = ref('')
const hoveredPointIndex = ref<number | null>(null)

const periodOptions = [
  { value: '7_days',   label: 'Last 7 Days' },
  { value: '30_days',  label: 'Last 30 Days' },
  { value: '90_days',  label: 'Last 90 Days' },
  { value: 'semester', label: 'This Semester' },
  { value: 'year',     label: 'This Academic Year' },
] as const

const currentPeriodLabel = computed(() => {
  return periodOptions.find(p => p.value === selectedPeriod.value)?.label ?? 'Last 30 Days'
})

// Current formatted date
const formattedCurrentDate = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
})

// ── Data Contract ─────────────────────────────────────────────────────────────
interface GrowthInfo {
  rate: number
  formatted: string
  trend: 'up' | 'down'
  count: number
}

interface DashboardData {
  status: string
  period: string
  kpi: {
    totalUsers: { value: number; label: string; sub: string; growth: GrowthInfo | null; in_period: number }
    instructors: { value: number; active: number; label: string; sub: string; growth: GrowthInfo | null; in_period: number }
    students: { value: number; active: number; label: string; sub: string; growth: GrowthInfo | null; in_period: number }
    courses: { value: number; label: string; sub: string; growth: GrowthInfo | null; in_period: number }
    exams: { value: number; active: number; label: string; sub: string; growth: GrowthInfo | null; in_period: number }
  }
  academicOverview: {
    examsConducted: { value: number; growth: GrowthInfo | null }
    totalAttempts: { value: number; growth: GrowthInfo | null }
    passRate: { value: number }
    averageScore: { value: number }
  }
  chart: {
    labels: string[]
    fullDates: string[]
    data: number[]
    attempts: number[]
    exams: number[]
  }
  academicPeriod: {
    academicYear: string
    semester: string
    formattedTerm: string
    eventTitle: string
    startDate: string | null
    endDate: string | null
    status: string
  }
  userDistribution: Array<{
    label: string
    count: number
    pct: string
    color: string
  }>
  recentActivities: Array<{
    id: number
    action: string
    type: string
    module: string
    details: string
    actor_name: string
    actor_role: string
    time_ago: string
    timestamp: string | null
    date: string
  }>
  recentExams: Array<{
    id: number
    title: string
    course: string
    date: string
    status: string
    attempts_count: number
    duration: string
  }>
  systemHealth: {
    server: { name: string; status: string; details: string; icon: string }
    database: { name: string; status: string; details: string; icon: string }
    storage: { name: string; status: string; details: string; icon: string }
    email: { name: string; status: string; details: string; icon: string }
    last_checked: string
    last_checked_human: string
  }
  lastUpdated: string
}

const dashboard = ref<DashboardData | null>(null)

// ── Fetch Dashboard ───────────────────────────────────────────────────────────
const fetchDashboardStats = async (period = selectedPeriod.value) => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''
  try {
    const res = await apiClient.get<DashboardData>('/admin/dashboard-stats', {
      params: { period }
    })
    dashboard.value = res.data
  } catch (err: any) {
    hasError.value = true
    errorMessage.value = err.response?.data?.message || 'Unable to load administration dashboard data.'
  } finally {
    isLoading.value = false
  }
}

const selectPeriod = (period: '7_days' | '30_days' | '90_days' | 'semester' | 'year') => {
  selectedPeriod.value = period
  isPeriodDropdownOpen.value = false
  fetchDashboardStats(period)
}

// ── Refresh System Health ─────────────────────────────────────────────────────
const refreshSystemHealth = async () => {
  if (isRefreshingHealth.value) return
  isRefreshingHealth.value = true
  try {
    const res = await apiClient.get('/admin/dashboard/system-status')
    if (dashboard.value && res.data?.health) {
      dashboard.value.systemHealth = res.data.health
    }
  } catch (err) {
    console.error('Failed to probe system status:', err)
  } finally {
    setTimeout(() => {
      isRefreshingHealth.value = false
    }, 400)
  }
}

onMounted(() => {
  fetchDashboardStats()
})

// ── SVG Chart Calculations ────────────────────────────────────────────────────
const chartCoords = computed(() => {
  if (!dashboard.value?.chart?.attempts?.length) return []
  const data = dashboard.value.chart.attempts
  const maxVal = Math.max(...data, 5) // Minimum ceiling of 5 to avoid flat 0-div
  const count = data.length

  return data.map((val, idx) => {
    const x = count > 1 ? (idx / (count - 1)) * 600 : 300
    const y = 175 - (val / maxVal) * 145
    return { x, y, value: val, label: dashboard.value?.chart.labels[idx] ?? '', fullDate: dashboard.value?.chart.fullDates[idx] ?? '' }
  })
})

const svgPath = computed(() => {
  const points = chartCoords.value
  if (!points.length) return ''
  return 'M ' + points.map(p => `${p.x},${p.y}`).join(' L ')
})

const svgFill = computed(() => {
  const points = chartCoords.value
  if (!points.length) return ''
  return `M ${points[0].x},185 ` + points.map(p => `L ${p.x},${p.y}`).join(' ') + ` L ${points[points.length - 1].x},185 Z`
})

// ── Donut Calculations ────────────────────────────────────────────────────────
const donutSegments = computed(() => {
  if (!dashboard.value?.userDistribution?.length) return []
  const C = 2 * Math.PI * 44
  let currentOffset = 0
  return dashboard.value.userDistribution.map(d => {
    const pctVal = parseFloat(d.pct) / 100 || 0
    const length = pctVal * C
    const dasharray = `${length} ${C - length}`
    const dashoffset = -currentOffset
    currentOffset += length
    return {
      label: d.label,
      count: d.count,
      pct: d.pct,
      color: d.color,
      dasharray,
      dashoffset,
    }
  })
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const getHealthStatusBadge = (status: string) => {
  switch (status.toLowerCase()) {
    case 'operational':
      return { text: 'text-emerald-700 bg-emerald-50 border-emerald-200', dot: 'bg-emerald-500' }
    case 'degraded':
      return { text: 'text-amber-700 bg-amber-50 border-amber-200', dot: 'bg-amber-500' }
    case 'unavailable':
      return { text: 'text-red-700 bg-red-50 border-red-200', dot: 'bg-red-500' }
    default:
      return { text: 'text-slate-700 bg-slate-50 border-slate-200', dot: 'bg-slate-400' }
  }
}
</script>

<template>
  <div class="space-y-6 max-w-[1600px] mx-auto pb-10">

    <!-- ── Header Bar & Date Filters ────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span>System Administration Command Center</span>
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Real-time university overview, performance indicators, and operational metrics.
        </p>
      </div>

      <!-- Action Controls -->
      <div class="flex items-center gap-2.5 flex-wrap">
        
        <!-- Live Date Indicator -->
        <div class="hidden md:flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white border border-slate-200 shadow-xs text-xs font-semibold text-slate-600">
          <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          <span>{{ formattedCurrentDate }}</span>
        </div>

        <!-- Period Selector Dropdown -->
        <div class="relative">
          <button
            @click="isPeriodDropdownOpen = !isPeriodDropdownOpen"
            type="button"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 hover:border-indigo-400 rounded-xl text-xs font-semibold text-slate-700 shadow-xs transition-colors focus:outline-none"
          >
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span>{{ currentPeriodLabel }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': isPeriodDropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="isPeriodDropdownOpen"
            class="absolute right-0 mt-1.5 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-40"
          >
            <button
              v-for="p in periodOptions"
              :key="p.value"
              @click="selectPeriod(p.value)"
              type="button"
              class="w-full text-left px-3.5 py-2 text-xs font-medium transition-colors flex items-center justify-between"
              :class="selectedPeriod === p.value ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
            >
              <span>{{ p.label }}</span>
              <svg v-if="selectedPeriod === p.value" class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Reload / Refresh Button -->
        <button
          @click="fetchDashboardStats()"
          :disabled="isLoading"
          title="Refresh dashboard metrics"
          type="button"
          class="p-2 bg-white border border-slate-200 hover:border-slate-300 text-slate-600 hover:text-slate-900 rounded-xl shadow-xs transition-colors disabled:opacity-50"
        >
          <svg class="w-4 h-4" :class="{ 'animate-spin text-indigo-600': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- ── Error State ──────────────────────────────────────────────────────── -->
    <div v-if="hasError" class="p-6 rounded-2xl bg-red-50 border border-red-200 text-center">
      <div class="w-12 h-12 mx-auto rounded-full bg-red-100 flex items-center justify-center text-red-600 mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
      </div>
      <h3 class="text-sm font-bold text-red-900">{{ errorMessage || 'Unable to load dashboard metrics' }}</h3>
      <p class="text-xs text-red-600 mt-1 max-w-md mx-auto">An error occurred while communicating with the backend. Please verify your connection and permissions.</p>
      <button
        @click="fetchDashboardStats()"
        type="button"
        class="mt-4 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors inline-flex items-center gap-2"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Retry
      </button>
    </div>

    <!-- ── 1. Main KPI Cards ────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
      
      <!-- Total Users -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden group">
        <div class="flex items-start justify-between gap-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </div>
          <span v-if="!isLoading && dashboard?.kpi.totalUsers.growth" class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md" :class="dashboard.kpi.totalUsers.growth.trend === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
            {{ dashboard.kpi.totalUsers.growth.formatted }}
          </span>
        </div>
        <div class="mt-4">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Users</p>
          <div v-if="isLoading" class="h-8 w-20 bg-slate-100 animate-pulse rounded mt-1"></div>
          <p v-else class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">
            {{ dashboard?.kpi.totalUsers.value.toLocaleString() ?? '—' }}
          </p>
          <p class="text-[11px] text-slate-500 font-medium mt-1 truncate">All registered university accounts</p>
        </div>
      </div>

      <!-- Instructors -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden group">
        <div class="flex items-start justify-between gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
          </div>
          <span v-if="!isLoading && dashboard?.kpi.instructors.growth" class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md" :class="dashboard.kpi.instructors.growth.trend === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
            {{ dashboard.kpi.instructors.growth.formatted }}
          </span>
        </div>
        <div class="mt-4">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Instructors</p>
          <div v-if="isLoading" class="h-8 w-20 bg-slate-100 animate-pulse rounded mt-1"></div>
          <p v-else class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">
            {{ dashboard?.kpi.instructors.value.toLocaleString() ?? '—' }}
          </p>
          <p class="text-[11px] text-slate-500 font-medium mt-1 truncate">
            {{ dashboard ? `${dashboard.kpi.instructors.active} active instructors` : 'Academic staff' }}
          </p>
        </div>
      </div>

      <!-- Students -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden group">
        <div class="flex items-start justify-between gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
          </div>
          <span v-if="!isLoading && dashboard?.kpi.students.growth" class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md" :class="dashboard.kpi.students.growth.trend === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
            {{ dashboard.kpi.students.growth.formatted }}
          </span>
        </div>
        <div class="mt-4">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Students</p>
          <div v-if="isLoading" class="h-8 w-20 bg-slate-100 animate-pulse rounded mt-1"></div>
          <p v-else class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">
            {{ dashboard?.kpi.students.value.toLocaleString() ?? '—' }}
          </p>
          <p class="text-[11px] text-slate-500 font-medium mt-1 truncate">
            {{ dashboard ? `${dashboard.kpi.students.active} enrolled test takers` : 'Enrolled students' }}
          </p>
        </div>
      </div>

      <!-- Courses -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden group">
        <div class="flex items-start justify-between gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
          </div>
          <span v-if="!isLoading && dashboard?.kpi.courses.growth" class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md" :class="dashboard.kpi.courses.growth.trend === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
            {{ dashboard.kpi.courses.growth.formatted }}
          </span>
        </div>
        <div class="mt-4">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Courses</p>
          <div v-if="isLoading" class="h-8 w-20 bg-slate-100 animate-pulse rounded mt-1"></div>
          <p v-else class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">
            {{ dashboard?.kpi.courses.value.toLocaleString() ?? '—' }}
          </p>
          <p class="text-[11px] text-slate-500 font-medium mt-1 truncate">Offered academic curriculum</p>
        </div>
      </div>

      <!-- Exams -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden group">
        <div class="flex items-start justify-between gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
          </div>
          <span v-if="!isLoading && dashboard?.kpi.exams.growth" class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md" :class="dashboard.kpi.exams.growth.trend === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
            {{ dashboard.kpi.exams.growth.formatted }}
          </span>
        </div>
        <div class="mt-4">
          <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Exams</p>
          <div v-if="isLoading" class="h-8 w-20 bg-slate-100 animate-pulse rounded mt-1"></div>
          <p v-else class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">
            {{ dashboard?.kpi.exams.value.toLocaleString() ?? '—' }}
          </p>
          <p class="text-[11px] text-slate-500 font-medium mt-1 truncate">
            {{ dashboard ? `${dashboard.kpi.exams.active} published & scheduled` : 'Created examinations' }}
          </p>
        </div>
      </div>

    </div>

    <!-- ── 2. Academic Overview & System Status ─────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Academic Overview Chart Card -->
      <div class="col-span-1 lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-xs p-6 flex flex-col justify-between">
        
        <!-- Header & Range Filter -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
          <div>
            <h2 class="text-base font-bold text-slate-900 tracking-tight">Academic Activity Overview</h2>
            <p class="text-xs text-slate-500 mt-0.5">Student exam attempts and testing volume over time</p>
          </div>
          
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              {{ currentPeriodLabel }}
            </span>
          </div>
        </div>

        <!-- 4 Key Academic Sub-Metrics -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-slate-50 border border-slate-100 mb-6">
          
          <div>
            <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Exams Conducted</p>
            <div class="flex items-baseline gap-1.5 mt-1">
              <span class="text-lg font-bold text-slate-900">
                {{ dashboard?.academicOverview.examsConducted.value.toLocaleString() ?? '0' }}
              </span>
              <span v-if="dashboard?.academicOverview.examsConducted.growth" class="text-[10px] font-bold text-emerald-600">
                {{ dashboard.academicOverview.examsConducted.growth.formatted }}
              </span>
            </div>
          </div>

          <div>
            <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Attempts</p>
            <div class="flex items-baseline gap-1.5 mt-1">
              <span class="text-lg font-bold text-slate-900">
                {{ dashboard?.academicOverview.totalAttempts.value.toLocaleString() ?? '0' }}
              </span>
              <span v-if="dashboard?.academicOverview.totalAttempts.growth" class="text-[10px] font-bold text-emerald-600">
                {{ dashboard.academicOverview.totalAttempts.growth.formatted }}
              </span>
            </div>
          </div>

          <div>
            <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Pass Rate</p>
            <div class="flex items-baseline gap-1.5 mt-1">
              <span class="text-lg font-bold" :class="(dashboard?.academicOverview.passRate.value ?? 0) >= 50 ? 'text-emerald-700' : 'text-amber-700'">
                {{ dashboard ? `${dashboard.academicOverview.passRate.value}%` : '0%' }}
              </span>
              <span class="text-[10px] text-slate-400 font-normal">≥ 50%</span>
            </div>
          </div>

          <div>
            <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Average Score</p>
            <div class="flex items-baseline gap-1.5 mt-1">
              <span class="text-lg font-bold text-indigo-700">
                {{ dashboard ? `${dashboard.academicOverview.averageScore.value}%` : '0%' }}
              </span>
              <span class="text-[10px] text-slate-400 font-normal">Grade scale</span>
            </div>
          </div>

        </div>

        <!-- SVG Line Chart with Tooltip -->
        <div class="relative min-w-0">
          
          <div v-if="isLoading" class="h-44 w-full bg-slate-50 animate-pulse rounded-xl flex items-center justify-center text-xs text-slate-400">
            Loading examination activity timeline...
          </div>

          <div v-else-if="chartCoords.length === 0" class="h-44 w-full bg-slate-50 rounded-xl flex flex-col items-center justify-center text-slate-400">
            <svg class="w-8 h-8 text-slate-300 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <p class="text-xs font-semibold text-slate-500">No exam activity recorded for this period</p>
            <p class="text-[11px] text-slate-400">Attempts and submissions will appear on this timeline.</p>
          </div>

          <div v-else class="relative">
            
            <!-- Tooltip Overlay -->
            <div
              v-if="hoveredPointIndex !== null && chartCoords[hoveredPointIndex]"
              class="absolute z-20 pointer-events-none -translate-x-1/2 -top-12 bg-slate-900 text-white text-[11px] font-medium py-1.5 px-3 rounded-lg shadow-xl flex items-center gap-2 whitespace-nowrap"
              :style="{ left: `${(chartCoords[hoveredPointIndex].x / 600) * 100}%` }"
            >
              <span>{{ chartCoords[hoveredPointIndex].fullDate }}:</span>
              <span class="font-bold text-indigo-300">{{ chartCoords[hoveredPointIndex].value }} Attempts</span>
            </div>

            <!-- SVG Container -->
            <svg viewBox="0 0 600 190" class="w-full h-44 overflow-visible" preserveAspectRatio="none">
              <defs>
                <linearGradient id="primaryAreaGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#6366f1" stop-opacity="0.22"/>
                  <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0"/>
                </linearGradient>
              </defs>

              <!-- Subtle horizontal grid lines -->
              <line x1="0" y1="30" x2="600" y2="30" stroke="#f1f5f9" stroke-dasharray="4" stroke-width="1"/>
              <line x1="0" y1="90" x2="600" y2="90" stroke="#f1f5f9" stroke-dasharray="4" stroke-width="1"/>
              <line x1="0" y1="150" x2="600" y2="150" stroke="#f1f5f9" stroke-dasharray="4" stroke-width="1"/>
              <line x1="0" y1="185" x2="600" y2="185" stroke="#e2e8f0" stroke-width="1.5"/>

              <!-- Gradient Fill Area -->
              <path :d="svgFill" fill="url(#primaryAreaGrad)" />

              <!-- Polyline -->
              <path :d="svgPath" fill="none" stroke="#6366f1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

              <!-- Interactive Points -->
              <g v-for="(p, idx) in chartCoords" :key="idx">
                <circle
                  :cx="p.x"
                  :cy="p.y"
                  :r="hoveredPointIndex === idx ? 6 : 4"
                  :fill="hoveredPointIndex === idx ? '#4f46e5' : '#ffffff'"
                  stroke="#6366f1"
                  :stroke-width="hoveredPointIndex === idx ? 3 : 2"
                  class="cursor-pointer transition-all"
                  @mouseenter="hoveredPointIndex = idx"
                  @mouseleave="hoveredPointIndex = null"
                />
              </g>
            </svg>

            <!-- X-Axis Labels -->
            <div class="flex justify-between items-center mt-2 px-1 text-[11px] font-medium text-slate-400">
              <span v-for="p in chartCoords" :key="p.label">{{ p.label }}</span>
            </div>
          </div>
        </div>

      </div>

      <!-- System Status / Real Health Card -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-6 flex flex-col justify-between">
        
        <div>
          <div class="flex items-center justify-between mb-5">
            <div>
              <h2 class="text-base font-bold text-slate-900 tracking-tight">System Health & Services</h2>
              <p class="text-xs text-slate-500 mt-0.5">Live operational status of core infrastructure</p>
            </div>
            
            <button
              @click="refreshSystemHealth()"
              :disabled="isRefreshingHealth"
              type="button"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 hover:border-indigo-300 text-xs font-semibold text-slate-600 hover:text-indigo-600 transition-colors shadow-2xs"
            >
              <svg class="w-3.5 h-3.5" :class="{ 'animate-spin text-indigo-600': isRefreshingHealth }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              <span>Refresh</span>
            </button>
          </div>

          <!-- Services List -->
          <div class="space-y-4">
            
            <!-- Application Server -->
            <div class="p-3.5 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 leading-tight truncate">Application Server</p>
                  <p class="text-[11px] text-slate-400 font-medium truncate mt-0.5">
                    {{ dashboard?.systemHealth.server.details || 'PHP Engine & Memory' }}
                  </p>
                </div>
              </div>
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold border"
                :class="getHealthStatusBadge(dashboard?.systemHealth.server.status ?? 'Operational').text"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="getHealthStatusBadge(dashboard?.systemHealth.server.status ?? 'Operational').dot"></span>
                {{ dashboard?.systemHealth.server.status ?? 'Operational' }}
              </span>
            </div>

            <!-- Database System -->
            <div class="p-3.5 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 leading-tight truncate">Database System</p>
                  <p class="text-[11px] text-slate-400 font-medium truncate mt-0.5">
                    {{ dashboard?.systemHealth.database.details || 'MySQL Connection' }}
                  </p>
                </div>
              </div>
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold border"
                :class="getHealthStatusBadge(dashboard?.systemHealth.database.status ?? 'Operational').text"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="getHealthStatusBadge(dashboard?.systemHealth.database.status ?? 'Operational').dot"></span>
                {{ dashboard?.systemHealth.database.status ?? 'Operational' }}
              </span>
            </div>

            <!-- File Storage -->
            <div class="p-3.5 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 leading-tight truncate">File & Asset Storage</p>
                  <p class="text-[11px] text-slate-400 font-medium truncate mt-0.5">
                    {{ dashboard?.systemHealth.storage.details || 'Disk Storage' }}
                  </p>
                </div>
              </div>
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold border"
                :class="getHealthStatusBadge(dashboard?.systemHealth.storage.status ?? 'Operational').text"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="getHealthStatusBadge(dashboard?.systemHealth.storage.status ?? 'Operational').dot"></span>
                {{ dashboard?.systemHealth.storage.status ?? 'Operational' }}
              </span>
            </div>

            <!-- Email Notification Service -->
            <div class="p-3.5 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 leading-tight truncate">Email Notification Service</p>
                  <p class="text-[11px] text-slate-400 font-medium truncate mt-0.5">
                    {{ dashboard?.systemHealth.email.details || 'Mail Driver' }}
                  </p>
                </div>
              </div>
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold border"
                :class="getHealthStatusBadge(dashboard?.systemHealth.email.status ?? 'Operational').text"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="getHealthStatusBadge(dashboard?.systemHealth.email.status ?? 'Operational').dot"></span>
                {{ dashboard?.systemHealth.email.status ?? 'Operational' }}
              </span>
            </div>

          </div>
        </div>

        <!-- Footer Timestamp -->
        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 mt-4">
          <span>Active Health Probing</span>
          <span>Checked: {{ dashboard?.systemHealth.last_checked_human ?? 'Just now' }}</span>
        </div>

      </div>

    </div>

    <!-- ── 3. Academic Semester & User Distribution & Quick Actions ────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">

      <!-- Current Academic Semester Card -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-6 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-900 tracking-tight">Current Academic Period</h3>
            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
              <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span>
              {{ dashboard?.academicPeriod.status ?? 'Active' }}
            </span>
          </div>

          <div class="p-4 rounded-xl bg-gradient-to-br from-indigo-50/70 to-blue-50/50 border border-indigo-100 mb-4">
            <p class="text-[11px] font-bold text-indigo-500 uppercase tracking-wider">Active Term</p>
            <p class="text-xl font-black text-slate-900 tracking-tight mt-0.5">
              {{ dashboard?.academicPeriod.formattedTerm || '2026 Second Semester' }}
            </p>
            <p class="text-xs text-slate-600 mt-1 font-medium">
              Academic Year {{ dashboard?.academicPeriod.academicYear }} • {{ dashboard?.academicPeriod.semester }}
            </p>
          </div>

          <div class="space-y-2.5 text-xs text-slate-600">
            <div class="flex items-center justify-between py-1 border-b border-slate-50">
              <span class="text-slate-400">Term Schedule</span>
              <span class="font-semibold text-slate-800">{{ dashboard?.academicPeriod.eventTitle ?? 'Regular Academic Calendar' }}</span>
            </div>
            <div class="flex items-center justify-between py-1 border-b border-slate-50">
              <span class="text-slate-400">Start Date</span>
              <span class="font-semibold text-slate-800">{{ dashboard?.academicPeriod.startDate ?? 'Configured via Settings' }}</span>
            </div>
            <div class="flex items-center justify-between py-1">
              <span class="text-slate-400">Lock Status</span>
              <span class="font-semibold text-emerald-700">Open for Submissions</span>
            </div>
          </div>
        </div>

        <router-link
          to="/admin/academic-calendar"
          class="mt-5 w-full py-2.5 px-4 rounded-xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/50 text-indigo-700 text-xs font-bold text-center transition-colors flex items-center justify-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Manage Academic Calendar
        </router-link>
      </div>

      <!-- User Distribution Donut -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-6 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-900 tracking-tight">User Account Distribution</h3>
            <span class="text-xs text-slate-400 font-medium">By active role</span>
          </div>

          <div class="flex flex-col items-center my-3">
            <div class="relative w-36 h-36">
              <svg viewBox="0 0 120 120" class="w-full h-full">
                <!-- Background track -->
                <circle cx="60" cy="60" r="44" fill="none" stroke="#f1f5f9" stroke-width="16"/>
                <!-- Colored segments -->
                <circle
                  v-for="seg in donutSegments"
                  :key="seg.label"
                  cx="60"
                  cy="60"
                  r="44"
                  fill="none"
                  :stroke="seg.color"
                  stroke-width="16"
                  :stroke-dasharray="seg.dasharray"
                  :stroke-dashoffset="seg.dashoffset"
                  stroke-linecap="butt"
                  transform="rotate(-90 60 60)"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <span class="text-lg font-black text-slate-900 leading-tight">
                  {{ dashboard?.kpi.totalUsers.value.toLocaleString() ?? '0' }}
                </span>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Total</span>
              </div>
            </div>
          </div>

          <!-- Legend -->
          <div class="space-y-2 mt-4">
            <div
              v-for="d in donutSegments"
              :key="d.label"
              class="flex items-center justify-between text-xs py-1 border-b border-slate-50 last:border-none"
            >
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: d.color }"></span>
                <span class="font-medium text-slate-600">{{ d.label }}</span>
              </div>
              <span class="font-bold text-slate-800">{{ d.count.toLocaleString() }} <span class="text-[11px] text-slate-400 font-normal">({{ d.pct }})</span></span>
            </div>
          </div>
        </div>

        <router-link
          to="/admin/users"
          class="mt-4 text-[11px] font-bold text-indigo-600 hover:text-indigo-800 text-center block transition-colors"
        >
          View all registered accounts →
        </router-link>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-6 flex flex-col justify-between">
        <div>
          <h3 class="text-sm font-bold text-slate-900 tracking-tight mb-1">Administrative Actions</h3>
          <p class="text-xs text-slate-500 mb-4">Direct operational shortcuts</p>

          <div class="space-y-2.5">
            
            <router-link
              to="/admin/instructors"
              class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/40 transition-all group"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-700 truncate">Manage Instructors</p>
                  <p class="text-[10px] text-slate-400 truncate">Add, edit, and assign teachers</p>
                </div>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </router-link>

            <router-link
              to="/admin/students"
              class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/40 transition-all group"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-700 truncate">Enrolled Students</p>
                  <p class="text-[10px] text-slate-400 truncate">Inspect student enrollments</p>
                </div>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </router-link>

            <router-link
              to="/admin/exam-control"
              class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/40 transition-all group"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-700 truncate">Exam Control Centre</p>
                  <p class="text-[10px] text-slate-400 truncate">Manage live pauses, overrides & cancellations</p>
                </div>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </router-link>

            <router-link
              to="/admin/reports"
              class="flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/40 transition-all group"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-700 truncate">Analytics & Reports</p>
                  <p class="text-[10px] text-slate-400 truncate">Export academic performance summaries</p>
                </div>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </router-link>

          </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 mt-4">
          <span>All modules authenticated</span>
          <router-link to="/admin/settings" class="hover:text-indigo-600 transition-colors">Settings →</router-link>
        </div>
      </div>

    </div>

    <!-- ── 4. Recent Activities & Recent Exams ──────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Recent System Activity Logs -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-6 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900 tracking-tight">Recent System Activity</h3>
              <p class="text-xs text-slate-500 mt-0.5">Real-time audit log of administrative operations</p>
            </div>
            <router-link to="/admin/activity-logs" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
              View All Logs →
            </router-link>
          </div>

          <div v-if="!dashboard?.recentActivities.length" class="py-12 text-center text-slate-400">
            <p class="text-xs font-semibold text-slate-500">No activity logs recorded yet</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Operations will appear here as users interact with the system.</p>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="act in dashboard.recentActivities"
              :key="act.id"
              class="flex items-start gap-3 p-3 rounded-xl border border-slate-50 hover:bg-slate-50/70 transition-colors"
            >
              <div class="w-2 h-2 rounded-full bg-indigo-600 mt-1.5 shrink-0"></div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-xs font-bold text-slate-800 truncate">{{ act.action }}</p>
                  <span class="text-[10px] text-slate-400 whitespace-nowrap shrink-0">{{ act.time_ago }}</span>
                </div>
                <div class="flex items-center gap-2 mt-1">
                  <span class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                    {{ act.module }}
                  </span>
                  <span class="text-[10px] text-slate-400">
                    By {{ act.actor_name }} ({{ act.actor_role }})
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 mt-4">
          <span>Audit trail active</span>
          <router-link to="/admin/activity-logs" class="text-indigo-600 font-semibold hover:underline">
            Inspect all audit events
          </router-link>
        </div>
      </div>

      <!-- Recent Exams -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-6 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900 tracking-tight">Recent Examinations</h3>
              <p class="text-xs text-slate-500 mt-0.5">Recently created and active tests</p>
            </div>
            <router-link to="/admin/exams" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
              Manage Exams →
            </router-link>
          </div>

          <div v-if="!dashboard?.recentExams.length" class="py-12 text-center text-slate-400">
            <p class="text-xs font-semibold text-slate-500">No exams created yet</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Created exams will be listed here.</p>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="exam in dashboard.recentExams"
              :key="exam.id"
              class="flex items-center justify-between p-3.5 rounded-xl border border-slate-50 hover:bg-slate-50/70 transition-colors gap-3"
            >
              <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-slate-800 truncate">{{ exam.title }}</p>
                <p class="text-[11px] text-slate-400 font-medium truncate mt-0.5">{{ exam.course }} • {{ exam.duration }}</p>
              </div>
              <div class="text-right shrink-0">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                  {{ exam.status }}
                </span>
                <p class="text-[10px] text-slate-400 font-medium mt-1">
                  {{ exam.attempts_count }} {{ exam.attempts_count === 1 ? 'attempt' : 'attempts' }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 mt-4">
          <span>Exam Delivery Platform</span>
          <router-link to="/admin/exams" class="text-indigo-600 font-semibold hover:underline">
            View full exams catalogue
          </router-link>
        </div>
      </div>

    </div>

  </div>
</template>
