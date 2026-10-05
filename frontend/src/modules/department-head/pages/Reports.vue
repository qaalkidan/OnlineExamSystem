<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../../modules/auth/store/authStore'

const authStore = useAuthStore()

// State
const isLoading = ref(true)
const isExporting = ref(false)
const exportFormatLoading = ref<string>('')
const showExportDropdown = ref(false)
const showExportModal = ref(false)
const selectedExportFormat = ref<'pdf' | 'excel' | 'csv'>('pdf')
const deptName = ref(authStore.user?.department?.name || 'Computer Science')
const selectedPeriod = ref('This Semester')
const activeHoverPoint = ref<{ month: string; avg_score: number; attempts_count: number; x: number; y: number } | null>(null)

// Toast notification
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' }>({
  show: false,
  message: '',
  type: 'success'
})

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 3500)
}

// KPI State
interface KpiItem {
  label: string
  value: string
  change: string
  trend: 'up' | 'down'
  bg: string
  ic: string
  icon: string
}

const kpis = ref<KpiItem[]>([
  {
    label: 'Total Exams Conducted',
    value: '0',
    change: '0 active',
    trend: 'up',
    bg: 'bg-indigo-50',
    ic: 'text-[#5138ed]',
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'
  },
  {
    label: 'Total Student Attempts',
    value: '0',
    change: '0 total',
    trend: 'up',
    bg: 'bg-sky-50',
    ic: 'text-sky-500',
    icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'
  },
  {
    label: 'Department Pass Rate',
    value: '0%',
    change: '0 passed',
    trend: 'up',
    bg: 'bg-emerald-50',
    ic: 'text-emerald-500',
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
  },
  {
    label: 'Average Score',
    value: '0%',
    change: 'N/A',
    trend: 'up',
    bg: 'bg-amber-50',
    ic: 'text-amber-500',
    icon: 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z'
  },
])

// Course Performance State
interface CoursePerfItem {
  id?: number
  name: string
  code: string
  instructor: string
  passRate: number
  avgScore: number
  attempts?: number
}

const coursePerformance = ref<CoursePerfItem[]>([])

// Trend Chart State
interface TrendPoint {
  month: string
  avg_score: number
  attempts_count: number
}

const trendData = ref<TrendPoint[]>([
  { month: 'Jan', avg_score: 68, attempts_count: 0 },
  { month: 'Feb', avg_score: 74, attempts_count: 0 },
  { month: 'Mar', avg_score: 72, attempts_count: 0 },
  { month: 'Apr', avg_score: 76, attempts_count: 0 },
  { month: 'May', avg_score: 80, attempts_count: 0 },
  { month: 'Jun', avg_score: 83, attempts_count: 0 },
  { month: 'Jul', avg_score: 85, attempts_count: 0 },
  { month: 'Aug', avg_score: 78, attempts_count: 0 },
  { month: 'Sep', avg_score: 82, attempts_count: 0 },
  { month: 'Oct', avg_score: 84, attempts_count: 0 },
  { month: 'Nov', avg_score: 80, attempts_count: 0 },
  { month: 'Dec', avg_score: 86, attempts_count: 0 }
])

const chartPoints = ref<[number, number][]>([
  [0, 160], [50, 140], [100, 150], [150, 120], [200, 130], [250, 90],
  [300, 100], [350, 80], [400, 90], [450, 70], [500, 85], [550, 60]
])

const svgLine = computed(() => {
  if (!chartPoints.value || chartPoints.value.length === 0) return ''
  return chartPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'}${p[0]},${p[1]}`).join(' ')
})

const svgFill = computed(() => {
  if (!chartPoints.value || chartPoints.value.length === 0) return ''
  const last = chartPoints.value[chartPoints.value.length - 1]
  return `${svgLine.value} L${last[0]},200 L0,200 Z`
})

// Fetch report data from backend
const fetchReportData = async () => {
  try {
    isLoading.value = true
    const res = await apiClient.get('/dept-head/reports', {
      params: { period: selectedPeriod.value }
    })

    if (res.data?.status === 'success' && res.data?.data) {
      const data = res.data.data
      if (data.department_name) {
        deptName.value = data.department_name
      }
      if (data.kpis && Array.isArray(data.kpis)) {
        kpis.value = data.kpis
      }
      if (data.course_performance && Array.isArray(data.course_performance)) {
        coursePerformance.value = data.course_performance
      }
      if (data.trend && Array.isArray(data.trend)) {
        trendData.value = data.trend
      }
      if (data.chart_points && Array.isArray(data.chart_points)) {
        chartPoints.value = data.chart_points
      }
    }
  } catch (error) {
    console.error('Failed to fetch department reports:', error)
  } finally {
    isLoading.value = false
  }
}

// Period Change Handler
const onPeriodChange = () => {
  fetchReportData()
}

// Toggle Dropdown
const toggleExportDropdown = () => {
  showExportDropdown.value = !showExportDropdown.value
}

// Export handler
const handleExport = async (format?: 'pdf' | 'excel' | 'csv') => {
  const chosenFormat = format || selectedExportFormat.value
  showExportDropdown.value = false
  showExportModal.value = false

  try {
    isExporting.value = true
    exportFormatLoading.value = chosenFormat
    showToast(`Generating ${chosenFormat.toUpperCase()} report...`, 'success')

    const res = await apiClient.get('/dept-head/reports/export', {
      params: {
        format: chosenFormat,
        period: selectedPeriod.value
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

    // Decode and download file
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

// Close dropdown on outside click
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
  <div class="space-y-6 min-w-0 max-w-full">

    <!-- Toast Notification -->
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
        class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium transition-all max-w-[90vw]"
        :class="toast.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'"
      >
        <svg v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <svg v-else class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="break-words">{{ toast.message }}</span>
      </div>
    </transition>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-[20px] sm:text-[22px] font-bold text-slate-800">Department Analytics</h1>
        <p class="text-[12px] sm:text-[13px] text-slate-500 mt-1">Detailed performance metrics for {{ deptName }}.</p>
      </div>

      <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
        <!-- Period Selector -->
        <select
          v-model="selectedPeriod"
          @change="onPeriodChange"
          class="flex-1 sm:flex-initial text-[13px] border border-slate-200 rounded-xl px-4 py-2.5 min-h-[44px] focus:outline-none focus:border-[#5138ed] text-slate-600 bg-white font-medium cursor-pointer shadow-xs"
        >
          <option value="This Semester">This Semester</option>
          <option value="Last Semester">Last Semester</option>
          <option value="Last 30 Days">Last 30 Days</option>
          <option value="This Year">This Year</option>
          <option value="All Time">All Time</option>
        </select>

        <!-- Export Dropdown / Action Button Container -->
        <div class="relative export-dropdown-container">
          <div class="inline-flex rounded-xl shadow-sm">
            <!-- Main Export Button (Triggers Modal / Quick Download) -->
            <button
              type="button"
              @click="showExportModal = true"
              :disabled="isExporting"
              class="flex items-center gap-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-[13px] font-bold pl-4 pr-3 py-2.5 min-h-[44px] rounded-l-xl transition-all cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed border-r border-indigo-500/50"
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

            <!-- Chevron Toggle Button for Quick Options -->
            <button
              type="button"
              @click.stop="toggleExportDropdown"
              :disabled="isExporting"
              class="bg-[#5138ed] hover:bg-indigo-700 text-white px-3 py-2.5 min-h-[44px] rounded-r-xl transition-all cursor-pointer disabled:opacity-70"
              title="Export Formats"
            >
              <svg class="w-3.5 h-3.5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
          </div>

          <!-- Quick Dropdown Menu -->
          <div
            v-if="showExportDropdown"
            class="absolute right-0 mt-2 w-52 bg-white border border-slate-100 rounded-xl shadow-xl py-1.5 z-50 transition-all text-slate-700"
          >
            <div class="px-3 py-1.5 border-b border-slate-100 mb-1">
              <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Select Export Format</p>
            </div>
            <button
              type="button"
              @click="handleExport('pdf')"
              class="w-full flex items-center gap-2.5 px-3.5 py-2.5 min-h-[44px] hover:bg-slate-50 text-left font-medium text-slate-700 hover:text-[#5138ed] transition-colors cursor-pointer"
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
              class="w-full flex items-center gap-2.5 px-3.5 py-2.5 min-h-[44px] hover:bg-slate-50 text-left font-medium text-slate-700 hover:text-emerald-600 transition-colors cursor-pointer"
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
              class="w-full flex items-center gap-2.5 px-3.5 py-2.5 min-h-[44px] hover:bg-slate-50 text-left font-medium text-slate-700 hover:text-sky-600 transition-colors cursor-pointer"
            >
              <div class="w-7 h-7 rounded-md bg-sky-50 flex items-center justify-center text-sky-500 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
              </div>
              <div>
                <p class="font-bold leading-tight text-[12.5px]">CSV Spreadsheet</p>
                <p class="text-[10px] text-slate-400">Raw data values (.csv)</p>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Export Modal Dialog -->
    <div
      v-if="showExportModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-3 sm:p-4 overflow-y-auto"
    >
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-[95vw] sm:max-w-md w-full max-h-[92vh] overflow-y-auto p-5 sm:p-6 space-y-4 sm:space-y-5 animate-in fade-in zoom-in-95 duration-200 my-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-[16px] font-bold text-slate-800">Export Department Report</h3>
            <p class="text-[12px] text-slate-500 mt-0.5">{{ deptName }} &bull; {{ selectedPeriod }}</p>
          </div>
          <button
            type="button"
            @click="showExportModal = false"
            class="w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="space-y-3">
          <p class="text-[12px] font-semibold text-slate-600 uppercase tracking-wider">Choose File Format:</p>

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
            class="px-4 py-2.5 min-h-[44px] text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors cursor-pointer text-center"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleExport()"
            :disabled="isExporting"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all cursor-pointer disabled:opacity-60"
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

    <!-- KPIs Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="kpi in kpis"
        :key="kpi.label"
        class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-5 flex items-center gap-4 hover:shadow-md transition-shadow relative overflow-hidden"
      >
        <div :class="[kpi.bg, 'w-11 h-11 rounded-xl flex items-center justify-center shrink-0']">
          <svg class="w-5 h-5" :class="kpi.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="kpi.icon"/>
          </svg>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider truncate">{{ kpi.label }}</p>
          <div class="flex items-center gap-2 mt-0.5">
            <span class="text-[18px] sm:text-[20px] font-bold text-slate-800">{{ kpi.value }}</span>
            <span
              :class="[
                kpi.trend === 'up' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50',
                'text-[10px] font-bold px-1.5 py-0.5 rounded-md inline-block whitespace-nowrap'
              ]"
            >
              {{ kpi.change }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Chart & Course Breakdown Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Exam Performance Trend Chart -->
      <div class="lg:col-span-2 bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6 flex flex-col justify-between overflow-hidden">
        <div>
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div>
              <h3 class="text-[15px] font-bold text-slate-800">Exam Performance Trend</h3>
              <p class="text-[12px] text-slate-400 mt-0.5">Average scores across all {{ deptName }} courses</p>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-[#5138ed]"></span>
              <span class="text-[11px] text-slate-500 font-medium">Average Score (%)</span>
            </div>
          </div>

          <!-- SVG Visual Chart with scroll on narrow screens -->
          <div class="relative pt-4 pb-2 overflow-x-auto min-w-0">
            <div class="min-w-[420px]">
              <!-- Tooltip Popup -->
              <div
                v-if="activeHoverPoint"
                class="absolute z-10 pointer-events-none bg-slate-900 text-white text-[11px] font-medium px-2.5 py-1.5 rounded-lg shadow-md -translate-x-1/2 -translate-y-full transition-transform"
                :style="{ left: `${(activeHoverPoint.x / 550) * 100}%`, top: `${(activeHoverPoint.y / 200) * 100 - 8}%` }"
              >
                <div class="font-bold text-center">{{ activeHoverPoint.month }}</div>
                <div class="text-indigo-200">Avg Score: {{ activeHoverPoint.avg_score }}%</div>
                <div class="text-slate-400 text-[10px]" v-if="activeHoverPoint.attempts_count > 0">
                  {{ activeHoverPoint.attempts_count }} attempts
                </div>
              </div>

              <svg viewBox="0 0 550 200" class="w-full h-44 sm:h-48 overflow-visible" preserveAspectRatio="none">
                <defs>
                  <linearGradient id="rGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#5138ed" stop-opacity="0.18"/>
                    <stop offset="100%" stop-color="#5138ed" stop-opacity="0.01"/>
                  </linearGradient>
                </defs>

                <!-- Horizontal Grid Lines -->
                <line x1="0" y1="40" x2="550" y2="40" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3 3"/>
                <line x1="0" y1="90" x2="550" y2="90" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3 3"/>
                <line x1="0" y1="140" x2="550" y2="140" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3 3"/>

                <!-- Filled Area -->
                <path :d="svgFill" fill="url(#rGrad)"/>

                <!-- Trend Line -->
                <path :d="svgLine" fill="none" stroke="#5138ed" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>

                <!-- Interactive Data Points -->
                <g v-for="(p, i) in chartPoints" :key="i">
                  <circle
                    :cx="p[0]"
                    :cy="p[1]"
                    r="4"
                    fill="white"
                    stroke="#5138ed"
                    stroke-width="2.5"
                    class="cursor-pointer hover:r-6 transition-all"
                    @mouseenter="activeHoverPoint = { month: trendData[i]?.month || '', avg_score: trendData[i]?.avg_score || 0, attempts_count: trendData[i]?.attempts_count || 0, x: p[0], y: p[1] }"
                    @mouseleave="activeHoverPoint = null"
                  />
                </g>
              </svg>

              <!-- Month Axis Labels -->
              <div class="flex justify-between mt-3 px-1">
                <span
                  v-for="item in trendData"
                  :key="item.month"
                  class="text-[10px] sm:text-[11px] text-slate-400 font-medium"
                >
                  {{ item.month }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Course Performance Breakdown -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-4 sm:mb-5">
            <h3 class="text-[15px] font-bold text-slate-800">Course Performance</h3>
            <span class="text-[11px] text-slate-400 font-medium">{{ coursePerformance.length }} Courses</span>
          </div>

          <!-- List of Courses -->
          <div class="space-y-4 max-h-[310px] overflow-y-auto pr-1">
            <div
              v-for="c in coursePerformance"
              :key="c.code + c.name"
              class="group"
            >
              <div class="flex items-center justify-between mb-1.5 gap-2">
                <span class="text-[12px] font-bold text-slate-700 truncate flex-1 min-w-0" :title="c.name">
                  {{ c.name }}
                </span>
                <span class="text-[12px] font-bold text-slate-800 shrink-0">{{ c.passRate }}%</span>
              </div>

              <!-- Progress Bar -->
              <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div
                  :class="[
                    c.passRate >= 80 ? 'bg-emerald-500' : c.passRate >= 70 ? 'bg-[#5138ed]' : 'bg-amber-500',
                    'h-2 rounded-full transition-all duration-500'
                  ]"
                  :style="{ width: `${c.passRate}%` }"
                ></div>
              </div>

              <!-- Course Info Subtitle -->
              <div class="flex items-center justify-between mt-1 text-[10px] gap-2">
                <span class="font-mono font-bold text-slate-400 shrink-0">{{ c.code }}</span>
                <span class="text-slate-500 truncate min-w-0 text-right">
                  Instructor: <span class="font-bold text-slate-700">{{ c.instructor }}</span>
                </span>
              </div>
            </div>

            <!-- Empty State if no courses -->
            <div v-if="coursePerformance.length === 0 && !isLoading" class="text-center py-8">
              <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
              </svg>
              <p class="text-xs text-slate-400 font-medium">No courses available for this department.</p>
            </div>
          </div>
        </div>
      </div>

    </div>

  </div>
</template>
