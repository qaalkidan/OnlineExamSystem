<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'

const isLoading = ref(true)
const isExporting = ref(false)
const exportLoadingId = ref<string | number | null>(null)
const showExportModal = ref(false)
const selectedExportType = ref('student_performance')
const selectedExportFormat = ref<'pdf' | 'csv'>('pdf')

const now = new Date()
const monthYearStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
const dateRange = ref(`Academic Term — ${monthYearStr}`)
const deptFilter = ref('All Departments')
const trendFilter = ref('Last 6 Months')

// ── KPI Cards ──
const kpis = ref([
  { label: 'Total Students', value: '...', change: 'Active', sub: 'enrolled in system', bg: 'bg-indigo-50', ic: 'text-indigo-500', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { label: 'Total Instructors', value: '...', change: 'Active', sub: 'faculty members', bg: 'bg-emerald-50', ic: 'text-emerald-500', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { label: 'Total Courses', value: '...', change: 'Active', sub: 'registered courses', bg: 'bg-sky-50', ic: 'text-sky-500', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { label: 'Total Exams', value: '...', change: 'Published', sub: 'exams created', bg: 'bg-amber-50', ic: 'text-amber-500', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  { label: 'Total Results Processed', value: '...', change: 'Graded', sub: 'student submissions', bg: 'bg-rose-50', ic: 'text-rose-500', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
])

// ── Bar Chart: Student Performance Overview ──
const availableDepartments = ref<string[]>(['All Departments'])
const rawDeptNames = ref<string[]>([])
const rawDeptGradeData = ref<number[][]>([])
const rawDeptMap = ref<Record<string, number[]>>({})
const gradeColors = ['#6366f1', '#22c55e', '#f59e0b', '#fb923c', '#f43f5e']
const gradeLabels = ['Excellent (A)', 'Good (B)', 'Average (C)', 'Pass (D)', 'Fail (F)']

const barGroupWidth = 95
const barWidth = 12
const barGap = 2
const barPaddingLeft = 36
const barChartHeight = 200

const displayedDepartments = computed(() => {
  if (deptFilter.value === 'All Departments') {
    return rawDeptNames.value.length > 0 ? rawDeptNames.value : ['All Departments']
  }
  return [deptFilter.value]
})

const gradeData = computed(() => {
  if (deptFilter.value === 'All Departments') {
    return rawDeptGradeData.value.length > 0 ? rawDeptGradeData.value : [[0, 0, 0, 0, 0]]
  }
  const match = rawDeptMap.value[deptFilter.value]
  return match ? [match] : [[0, 0, 0, 0, 0]]
})

const maxBarValue = computed(() => {
  const flatVals = gradeData.value.flat()
  const maxVal = Math.max(0, ...flatVals)
  if (maxVal <= 4) return 5
  if (maxVal <= 8) return 10
  if (maxVal <= 20) return 20
  if (maxVal <= 50) return 50
  if (maxVal <= 100) return 100
  return Math.ceil((maxVal * 1.25) / 50) * 50
})

const barChartWidth = computed(() => {
  return Math.max(460, barPaddingLeft + displayedDepartments.value.length * barGroupWidth + 20)
})

const yLabels = computed(() => {
  const max = maxBarValue.value
  const step = max / 4
  return [0, Math.round(step), Math.round(step * 2), Math.round(step * 3), max]
})

function getBarHeight(val: number) {
  if (maxBarValue.value <= 0) return 0
  return Math.min(barChartHeight, (val / maxBarValue.value) * barChartHeight)
}
function getBarX(deptIdx: number, gradeIdx: number) {
  return barPaddingLeft + deptIdx * barGroupWidth + gradeIdx * (barWidth + barGap)
}
function getBarY(val: number) {
  return barChartHeight - getBarHeight(val)
}

// ── Line Chart: Exam Results Trend ──
const months = ref<string[]>(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'])
const passedData = ref<number[]>([0, 0, 0, 0, 0, 0])
const failedData = ref<number[]>([0, 0, 0, 0, 0, 0])
const lineChartWidth = 480
const lineChartHeight = 200
const linePaddingLeft = 40
const linePaddingRight = 20

const maxLineValue = computed(() => {
  const allVals = [...passedData.value, ...failedData.value]
  const maxVal = Math.max(0, ...allVals)
  if (maxVal <= 4) return 5
  if (maxVal <= 8) return 10
  if (maxVal <= 20) return 20
  if (maxVal <= 50) return 50
  if (maxVal <= 100) return 100
  return Math.ceil((maxVal * 1.25) / 50) * 50
})

const lineYLabels = computed(() => {
  const max = maxLineValue.value
  const step = max / 4
  return [0, Math.round(step), Math.round(step * 2), Math.round(step * 3), max]
})

function getLineX(idx: number) {
  const usable = lineChartWidth - linePaddingLeft - linePaddingRight
  const total = months.value.length
  if (total <= 1) return linePaddingLeft + usable / 2
  return linePaddingLeft + (idx / (total - 1)) * usable
}
function getLineY(val: number) {
  if (maxLineValue.value <= 0) return lineChartHeight
  return Math.max(0, Math.min(lineChartHeight, lineChartHeight - (val / maxLineValue.value) * lineChartHeight))
}
function buildLinePath(data: number[]) {
  if (!data || data.length === 0) return ''
  return data.map((v, i) => `${i === 0 ? 'M' : 'L'}${getLineX(i)},${getLineY(v)}`).join(' ')
}
function buildFillPath(data: number[]) {
  if (!data || data.length === 0) return ''
  return buildLinePath(data) + ` L${getLineX(data.length - 1)},${lineChartHeight} L${getLineX(0)},${lineChartHeight} Z`
}

const passedPath = computed(() => buildLinePath(passedData.value))
const failedPath = computed(() => buildLinePath(failedData.value))
const passedFill = computed(() => buildFillPath(passedData.value))
const failedFill = computed(() => buildFillPath(failedData.value))

// ── Recent Reports ──
const recentReports = ref([
  { id: 1, name: 'Student Performance Report', type: 'Academic', generatedBy: 'Super Admin', date: 'Oct 01, 2026', download_type: 'student_performance' },
  { id: 2, name: 'Exam Results Summary Report', type: 'Examination', generatedBy: 'Super Admin', date: 'Oct 01, 2026', download_type: 'exam_results' },
  { id: 3, name: 'Course Enrollment & Catalog', type: 'Academic', generatedBy: 'Super Admin', date: 'Sep 30, 2026', download_type: 'courses' },
  { id: 4, name: 'Department Summary Report', type: 'Academic', generatedBy: 'Super Admin', date: 'Sep 29, 2026', download_type: 'departments' },
  { id: 5, name: 'Faculty & Instructor Directory', type: 'Academic', generatedBy: 'Super Admin', date: 'Sep 28, 2026', download_type: 'instructors' },
])

// ── Report Categories ──
const reportCategories = [
  { title: 'Academic Reports', type: 'student_performance', desc: 'Student performance, marks, grades, and passing rates', bg: 'bg-indigo-50', ic: 'text-indigo-500', icon: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z' },
  { title: 'Examination Reports', type: 'exam_results', desc: 'Exam schedules, scores, attempts, and completion status', bg: 'bg-rose-50', ic: 'text-rose-500', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
  { title: 'Student Reports', type: 'students', desc: 'Student directory, registration, IDs, and section distribution', bg: 'bg-emerald-50', ic: 'text-emerald-500', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { title: 'Instructor Reports', type: 'instructors', desc: 'Faculty directory, course assignments, and department roles', bg: 'bg-sky-50', ic: 'text-sky-500', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { title: 'Course Reports', type: 'courses', desc: 'Course statistics, credit hours, curricula, and semesters', bg: 'bg-amber-50', ic: 'text-amber-500', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { title: 'Department Reports', type: 'departments', desc: 'Department-wise analytics, colleges, and summary statistics', bg: 'bg-purple-50', ic: 'text-purple-500', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
]

// ── Fetch Real Data from Backend ──
async function fetchReportStats(monthsCount = 6) {
  try {
    const res = await apiClient.get('/admin/reports/stats', {
      params: { months: monthsCount }
    })
    if (res.data?.status === 'success' && res.data?.data) {
      const d = res.data.data

      // KPIs
      if (d.kpis && Array.isArray(d.kpis)) {
        kpis.value = d.kpis
      }

      // Performance
      if (d.performance) {
        rawDeptNames.value = d.performance.departments || []
        rawDeptGradeData.value = d.performance.grade_data || []
        rawDeptMap.value = d.performance.department_map || {}
      }

      // Available Departments
      if (d.available_departments && Array.isArray(d.available_departments)) {
        availableDepartments.value = d.available_departments
      }

      // Trend
      if (d.trend) {
        months.value = d.trend.months || []
        passedData.value = d.trend.passed || []
        failedData.value = d.trend.failed || []
      }

      // Recent Reports
      if (d.recent_reports && Array.isArray(d.recent_reports)) {
        recentReports.value = d.recent_reports
      }
    }
  } catch (err) {
    console.error('Failed to load report stats:', err)
  } finally {
    isLoading.value = false
  }
}

// Watch trendFilter to dynamically fetch corresponding months
watch(trendFilter, (newVal) => {
  let m = 6
  if (newVal === 'Last 3 Months') m = 3
  else if (newVal === 'Last Year') m = 12
  fetchReportStats(m)
})

// ── Export / Download Function ──
async function downloadReport(type: string, format: 'pdf' | 'csv' = 'pdf', reportId?: number | string) {
  if (reportId) {
    exportLoadingId.value = reportId
  } else {
    isExporting.value = true
  }

  try {
    const res = await apiClient.get('/admin/reports/export', {
      params: { type, format }
    })

    if (res.data?.status === 'success' && res.data?.file) {
      const base64 = res.data.file
      const byteCharacters = atob(base64)
      const byteNumbers = new Array(byteCharacters.length)
      for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i)
      }
      const byteArray = new Uint8Array(byteNumbers)
      const mimeType = format === 'pdf' ? 'application/pdf' : 'text/csv;charset=utf-8;'
      const blob = new Blob([byteArray], { type: mimeType })

      const link = document.createElement('a')
      link.href = URL.createObjectURL(blob)
      link.setAttribute('download', res.data.filename || `Report_${new Date().toISOString().slice(0, 10)}.${format}`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(link.href)
    }
  } catch (err) {
    console.error('Failed to export report:', err)
  } finally {
    exportLoadingId.value = null
    isExporting.value = false
    showExportModal.value = false
  }
}

function openExportModal(type = 'student_performance') {
  selectedExportType.value = type
  showExportModal.value = true
}

onMounted(() => {
  fetchReportStats(6)
})
</script>

<template>
  <div class="space-y-6">

    <!-- Page Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-end gap-3">
      <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
        <div class="flex items-center justify-center sm:justify-start gap-2 border border-slate-200 rounded-xl px-4 py-2.5 bg-white shadow-sm hover:border-indigo-300 transition-colors flex-1 sm:flex-initial">
          <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          <span class="text-[12px] sm:text-[13px] font-semibold text-slate-600 truncate">{{ dateRange }}</span>
        </div>
        <button 
          @click="openExportModal('student_performance')"
          class="flex items-center justify-center gap-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-[13px] font-bold px-5 py-2.5 rounded-xl shadow-sm shadow-indigo-200 transition-colors active:scale-95 w-full sm:w-auto">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Generate Report
        </button>
      </div>
    </div>

    <!-- KPI Cards (5 columns) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
      <div v-for="kpi in kpis" :key="kpi.label" class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 flex items-start gap-3 hover:shadow-md transition-shadow">
        <div :class="[kpi.bg, 'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5']">
          <svg class="w-5 h-5" :class="kpi.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="kpi.icon"/>
          </svg>
        </div>
        <div class="min-w-0">
          <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide leading-tight">{{ kpi.label }}</p>
          <p class="text-[18px] sm:text-[20px] font-black text-slate-800 leading-tight mt-0.5">{{ kpi.value }}</p>
          <div class="flex items-center gap-1 mt-1 flex-wrap">
            <span class="text-[11px] font-bold text-emerald-600">{{ kpi.change }}</span>
            <span class="text-[10px] text-slate-400">{{ kpi.sub }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Student Performance Overview (Grouped Bar Chart) -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <h3 class="text-[14px] font-bold text-slate-800">Student Performance Overview</h3>
          </div>
          <div class="relative self-start sm:self-auto">
            <select v-model="deptFilter" class="text-[11px] border border-slate-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none text-slate-600 pr-7 appearance-none cursor-pointer hover:border-indigo-300 transition-colors">
              <option v-for="d in availableDepartments" :key="d" :value="d">{{ d }}</option>
            </select>
            <svg class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>
        </div>

        <!-- Grade Legend -->
        <div class="flex items-center gap-3 flex-wrap mb-3">
          <div v-for="(label, i) in gradeLabels" :key="label" class="flex items-center gap-1">
            <div class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ backgroundColor: gradeColors[i] }"></div>
            <span class="text-[10px] text-slate-500 whitespace-nowrap">{{ label }}</span>
          </div>
        </div>

        <!-- Bar SVG -->
        <div class="overflow-x-auto min-w-0 w-full">
          <svg :viewBox="`0 0 ${barChartWidth} ${barChartHeight + 30}`" class="w-full" style="min-width:440px">
            <!-- Grid lines + Y labels -->
            <g v-for="y in yLabels" :key="y">
              <text :x="barPaddingLeft - 5" :y="barChartHeight - (y / maxBarValue) * barChartHeight + 4" font-size="8" fill="#94a3b8" text-anchor="end">{{ y }}</text>
              <line :x1="barPaddingLeft" :y1="barChartHeight - (y / maxBarValue) * barChartHeight" :x2="barChartWidth - 5" :y2="barChartHeight - (y / maxBarValue) * barChartHeight" stroke="#f1f5f9" stroke-width="1"/>
            </g>
            <!-- Bars -->
            <g v-for="(deptData, deptIdx) in gradeData" :key="deptIdx">
              <rect v-for="(val, gradeIdx) in deptData" :key="gradeIdx"
                :x="getBarX(deptIdx, gradeIdx)"
                :y="getBarY(val)"
                :width="barWidth"
                :height="getBarHeight(val)"
                :fill="gradeColors[gradeIdx]"
                rx="2"
              >
                <title>{{ gradeLabels[gradeIdx] }}: {{ val }} students</title>
              </rect>
            </g>
            <!-- X-axis labels -->
            <text v-for="(dept, idx) in displayedDepartments" :key="dept"
              :x="barPaddingLeft + idx * barGroupWidth + (5 * (barWidth + barGap)) / 2"
              :y="barChartHeight + 18"
              font-size="8" fill="#64748b" text-anchor="middle" font-weight="600"
            >{{ dept.length > 18 ? dept.substring(0, 16) + '…' : dept }}</text>
          </svg>
        </div>
      </div>

      <!-- Exam Results Trend (Line Chart) -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            <h3 class="text-[14px] font-bold text-slate-800">Exam Results Trend</h3>
          </div>
          <div class="relative self-start sm:self-auto">
            <select v-model="trendFilter" class="text-[11px] border border-slate-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none text-slate-600 pr-7 appearance-none cursor-pointer hover:border-indigo-300 transition-colors">
              <option>Last 6 Months</option>
              <option>Last 3 Months</option>
              <option>Last Year</option>
            </select>
            <svg class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-5 mb-3">
          <div class="flex items-center gap-1.5">
            <div class="w-4 h-1.5 bg-emerald-500 rounded-full"></div>
            <span class="text-[11px] text-slate-500">Passed</span>
          </div>
          <div class="flex items-center gap-1.5">
            <div class="w-4 h-1.5 bg-rose-500 rounded-full"></div>
            <span class="text-[11px] text-slate-500">Failed</span>
          </div>
        </div>

        <!-- Line SVG -->
        <div class="overflow-x-auto min-w-0 w-full">
          <svg :viewBox="`0 0 ${lineChartWidth} ${lineChartHeight + 25}`" class="w-full" style="min-width:440px">
            <defs>
              <linearGradient id="passGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#22c55e" stop-opacity="0.25"/>
                <stop offset="100%" stop-color="#22c55e" stop-opacity="0.02"/>
              </linearGradient>
              <linearGradient id="failGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.15"/>
                <stop offset="100%" stop-color="#f43f5e" stop-opacity="0.02"/>
              </linearGradient>
            </defs>
            <!-- Grid lines + Y labels -->
            <g v-for="y in lineYLabels" :key="y">
              <text :x="linePaddingLeft - 5" :y="getLineY(y) + 4" font-size="8" fill="#94a3b8" text-anchor="end">{{ y }}</text>
              <line :x1="linePaddingLeft" :y1="getLineY(y)" :x2="lineChartWidth - linePaddingRight" :y2="getLineY(y)" stroke="#f1f5f9" stroke-width="1"/>
            </g>
            <!-- Fill areas -->
            <path :d="passedFill" fill="url(#passGrad)"/>
            <path :d="failedFill" fill="url(#failGrad)"/>
            <!-- Lines -->
            <path :d="passedPath" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path :d="failedPath" fill="none" stroke="#f43f5e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <!-- Data points -->
            <circle v-for="(v, i) in passedData" :key="`p${i}`" :cx="getLineX(i)" :cy="getLineY(v)" r="4" fill="white" stroke="#22c55e" stroke-width="2">
              <title>{{ months[i] }}: {{ v }} Passed</title>
            </circle>
            <circle v-for="(v, i) in failedData" :key="`f${i}`" :cx="getLineX(i)" :cy="getLineY(v)" r="4" fill="white" stroke="#f43f5e" stroke-width="2">
              <title>{{ months[i] }}: {{ v }} Failed</title>
            </circle>
            <!-- X-axis month labels -->
            <text v-for="(m, i) in months" :key="m" :x="getLineX(i)" :y="lineChartHeight + 18" font-size="9" fill="#94a3b8" text-anchor="middle">{{ m }}</text>
          </svg>
        </div>
      </div>
    </div>


    <!-- Bottom Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Recent Reports Table -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4 sm:mb-5">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-[14px] font-bold text-slate-800">Recent Reports</h3>
          </div>
          <button @click="openExportModal('student_performance')" class="text-[12px] font-bold text-[#5138ed] hover:text-indigo-800 transition-colors">Export All</button>
        </div>
        <div class="overflow-x-auto min-w-0 w-full">
          <table class="w-full whitespace-nowrap">
            <thead>
              <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                <th class="pb-2.5 text-left">#</th>
                <th class="pb-2.5 text-left">Report Name</th>
                <th class="pb-2.5 text-left">Type</th>
                <th class="pb-2.5 text-left">Generated By</th>
                <th class="pb-2.5 text-left">Date</th>
                <th class="pb-2.5 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="r in recentReports" :key="r.id" class="hover:bg-slate-50/60 transition-colors">
                <td class="py-2.5 text-[12px] text-slate-400 font-semibold">{{ r.id }}</td>
                <td class="py-2.5 text-[12px] font-semibold text-slate-700 max-w-[130px] truncate pr-2">{{ r.name }}</td>
                <td class="py-2.5">
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-md whitespace-nowrap"
                    :class="r.type === 'Academic' ? 'bg-indigo-50 text-indigo-600' : 'bg-rose-50 text-rose-600'">
                    {{ r.type }}
                  </span>
                </td>
                <td class="py-2.5 text-[11px] text-slate-500 whitespace-nowrap">{{ r.generatedBy }}</td>
                <td class="py-2.5 text-[10px] text-slate-400 whitespace-nowrap">{{ r.date }}</td>
                <td class="py-2.5 text-center">
                  <button 
                    @click="downloadReport(r.download_type, 'pdf', r.id)"
                    :disabled="exportLoadingId === r.id"
                    class="flex items-center gap-1 text-[10px] font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition-colors mx-auto whitespace-nowrap disabled:opacity-50">
                    <svg v-if="exportLoadingId === r.id" class="w-3 h-3 animate-spin text-[#5138ed]" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    {{ exportLoadingId === r.id ? 'Exporting...' : 'Download' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Report Categories -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex items-center gap-2 mb-4 sm:mb-5">
          <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
          </svg>
          <h3 class="text-[14px] font-bold text-slate-800">Report Categories</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div v-for="cat in reportCategories" :key="cat.title"
            @click="openExportModal(cat.type)"
            class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/30 cursor-pointer transition-all group shadow-sm hover:shadow"
            title="Click to generate and download report">
            <div :class="[cat.bg, 'w-9 h-9 rounded-xl flex items-center justify-center shrink-0']">
              <svg class="w-4 h-4" :class="cat.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="cat.icon"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[12px] font-bold text-slate-800 leading-tight group-hover:text-[#5138ed] transition-colors">{{ cat.title }}</p>
              <p class="text-[10px] text-slate-400 mt-0.5 leading-snug">{{ cat.desc }}</p>
            </div>
            <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-500 shrink-0 mt-1 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
          </div>
        </div>
      </div>

    </div>

    <!-- Generate / Export Report Modal -->
    <Teleport to="body">
      <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 w-full max-w-[95vw] sm:max-w-md max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-150">
          <!-- Modal Header -->
          <div class="px-4 sm:px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-[14px] sm:text-[15px] font-bold text-slate-800">Generate Report</h3>
                <p class="text-[11px] text-slate-400">Download system data in PDF or CSV format</p>
              </div>
            </div>
            <button @click="showExportModal = false" class="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-4 sm:p-6 space-y-4 overflow-y-auto">
            <!-- Report Selection -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Select Report Type</label>
              <select v-model="selectedExportType" class="w-full text-[13px] border border-slate-200 rounded-xl px-3.5 py-2.5 bg-white focus:outline-none focus:border-[#5138ed] text-slate-700">
                <option value="student_performance">Student Performance & Academic Results</option>
                <option value="exam_results">Examination Results & Attempts Summary</option>
                <option value="courses">Course Catalog & Enrollment</option>
                <option value="departments">Department Analytics & Statistics</option>
                <option value="instructors">Faculty & Instructor Directory</option>
                <option value="students">Student Roster & Directory</option>
              </select>
            </div>

            <!-- Format Selection -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Select File Format</label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button 
                  type="button"
                  @click="selectedExportFormat = 'pdf'"
                  :class="selectedExportFormat === 'pdf' ? 'border-[#5138ed] bg-indigo-50/50 text-[#5138ed]' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                  class="flex items-center gap-2.5 p-3 rounded-xl border text-[12px] font-bold transition-all text-left">
                  <div class="w-7 h-7 rounded-lg bg-rose-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div>
                    <div>PDF Document</div>
                    <div class="text-[10px] text-slate-400 font-normal">Formatted & ready to print</div>
                  </div>
                </button>

                <button 
                  type="button"
                  @click="selectedExportFormat = 'csv'"
                  :class="selectedExportFormat === 'csv' ? 'border-[#5138ed] bg-indigo-50/50 text-[#5138ed]' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                  class="flex items-center gap-2.5 p-3 rounded-xl border text-[12px] font-bold transition-all text-left">
                  <div class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div>
                    <div>CSV Spreadsheet</div>
                    <div class="text-[10px] text-slate-400 font-normal">Raw data for Excel</div>
                  </div>
                </button>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-4 sm:px-6 py-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-end gap-3 shrink-0">
            <button 
              type="button"
              @click="showExportModal = false"
              class="w-full sm:w-auto px-4 py-2 text-[12px] font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
              Cancel
            </button>
            <button 
              type="button"
              @click="downloadReport(selectedExportType, selectedExportFormat)"
              :disabled="isExporting"
              class="w-full sm:w-auto flex items-center justify-center gap-2 px-5 py-2 text-[12px] font-bold bg-[#5138ed] hover:bg-indigo-700 text-white rounded-xl shadow-sm shadow-indigo-200 transition-colors disabled:opacity-50">
              <svg v-if="isExporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              {{ isExporting ? 'Generating...' : 'Download Report' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
