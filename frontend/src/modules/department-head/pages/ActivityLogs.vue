<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'
import {
  Activity,
  Calendar,
  CheckCircle2,
  XCircle,
  ShieldAlert,
  Clock,
  Filter,
  RefreshCw,
  Search,
  Download,
  Trash2,
  Eye,
  Copy,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  SlidersHorizontal,
  X,
  FileText,
  User,
  Shield,
  Layers,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  ExternalLink,
  Database,
  Info,
  AlertTriangle,
  Home
} from 'lucide-vue-next'

interface LogEntry {
  id: number
  time: string
  date?: string
  time_only?: string
  raw_time?: string
  user: string
  email: string
  profile_picture_url?: string | null
  role: string
  raw_role?: string
  action: string
  actionType: string
  module: string
  description: string
  ipAddress: string
  status: 'Success' | 'Failed' | string
  department?: string | null
  is_read?: boolean
}

interface ActivitySummary {
  all: number
  today: number
  successful: number
  failed: number
  security: number
  logins: number
  dataChanges: number
  systemEvents: number
}

interface FilterOptions {
  modules: string[]
  actions: string[]
  roles: string[]
}

interface TopActiveUser {
  id?: number
  name: string
  email?: string
  role: string
  count: number
}

interface Pagination {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number
  to: number
}

// ── State Variables ──────────────────────────────────────────────────────────
const isLoading = ref(true)
const isRefreshing = ref(false)
const isExporting = ref(false)
const lastUpdatedTime = ref<string>('')

// Filter states
const searchQuery = ref('')
const selectedModule = ref('All Modules')
const selectedAction = ref('All Actions')
const selectedStatus = ref('All Statuses')
const selectedRole = ref('All Roles')
const activeKpiFilter = ref<'All' | 'Today' | 'Success' | 'Failed' | 'Security'>('All')

// Date filters
const datePreset = ref<'all' | 'today' | 'yesterday' | '7days' | '30days' | 'custom'>('all')
const startDate = ref('')
const endDate = ref('')
const showDatePopover = ref(false)

// Sorting & Pagination
const sortBy = ref<'created_at' | 'type' | 'module' | 'log_status'>('created_at')
const sortOrder = ref<'asc' | 'desc'>('desc')
const perPage = ref(10)

// Data from API
const logs = ref<LogEntry[]>([])
const activitySummary = ref<ActivitySummary>({
  all: 0,
  today: 0,
  successful: 0,
  failed: 0,
  security: 0,
  logins: 0,
  dataChanges: 0,
  systemEvents: 0,
})
const backendFilters = ref<FilterOptions>({
  modules: [],
  actions: [],
  roles: [],
})
const topActiveUsers = ref<TopActiveUser[]>([])
const pagination = ref<Pagination>({
  total: 0,
  per_page: 10,
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
})

// Modals & Drawers
const selectedLog = ref<LogEntry | null>(null)
const isDrawerOpen = ref(false)
const isClearModalOpen = ref(false)
const isClearing = ref(false)
const clearDays = ref(30)
const copiedField = ref<string | null>(null)

// Toast Notifications
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'success',
})
let toastTimer: any = null

const showToast = (message: string, type: 'success' | 'error' | 'info' = 'success') => {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

// ── Search Debounce ──────────────────────────────────────────────────────────
let searchDebounceTimer: any = null
const onSearchInput = () => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    fetchLogs(1)
  }, 350)
}

const clearSearch = () => {
  searchQuery.value = ''
  fetchLogs(1)
}

// ── Date Range Presets ───────────────────────────────────────────────────────
const applyDatePreset = (preset: 'all' | 'today' | 'yesterday' | '7days' | '30days' | 'custom') => {
  datePreset.value = preset
  const now = new Date()

  if (preset === 'all') {
    startDate.value = ''
    endDate.value = ''
  } else if (preset === 'today') {
    const todayStr = now.toISOString().split('T')[0]
    startDate.value = todayStr
    endDate.value = todayStr
  } else if (preset === 'yesterday') {
    const yest = new Date(now)
    yest.setDate(yest.getDate() - 1)
    const yestStr = yest.toISOString().split('T')[0]
    startDate.value = yestStr
    endDate.value = yestStr
  } else if (preset === '7days') {
    const past = new Date(now)
    past.setDate(past.getDate() - 7)
    startDate.value = past.toISOString().split('T')[0]
    endDate.value = now.toISOString().split('T')[0]
  } else if (preset === '30days') {
    const past = new Date(now)
    past.setDate(past.getDate() - 30)
    startDate.value = past.toISOString().split('T')[0]
    endDate.value = now.toISOString().split('T')[0]
  }

  if (preset !== 'custom') {
    showDatePopover.value = false
    fetchLogs(1)
  }
}

const applyCustomDates = () => {
  if (startDate.value && endDate.value && startDate.value > endDate.value) {
    showToast('Start date cannot be after end date.', 'error')
    return
  }
  datePreset.value = 'custom'
  showDatePopover.value = false
  fetchLogs(1)
}

const resetDates = () => {
  datePreset.value = 'all'
  startDate.value = ''
  endDate.value = ''
  showDatePopover.value = false
  fetchLogs(1)
}

const dateRangeDisplayLabel = computed(() => {
  if (datePreset.value === 'today') return 'Today'
  if (datePreset.value === 'yesterday') return 'Yesterday'
  if (datePreset.value === '7days') return 'Last 7 Days'
  if (datePreset.value === '30days') return 'Last 30 Days'
  if (startDate.value && endDate.value) {
    if (startDate.value === endDate.value) return startDate.value
    return `${startDate.value} to ${endDate.value}`
  }
  if (startDate.value) return `From ${startDate.value}`
  if (endDate.value) return `Until ${endDate.value}`
  return 'All Time'
})

// ── Active Filters Computation ───────────────────────────────────────────────
const hasActiveFilters = computed(() => {
  return (
    searchQuery.value.trim() !== '' ||
    selectedModule.value !== 'All Modules' ||
    selectedAction.value !== 'All Actions' ||
    selectedStatus.value !== 'All Statuses' ||
    selectedRole.value !== 'All Roles' ||
    startDate.value !== '' ||
    endDate.value !== '' ||
    activeKpiFilter.value !== 'All'
  )
})

const clearAllFilters = () => {
  searchQuery.value = ''
  selectedModule.value = 'All Modules'
  selectedAction.value = 'All Actions'
  selectedStatus.value = 'All Statuses'
  selectedRole.value = 'All Roles'
  activeKpiFilter.value = 'All'
  datePreset.value = 'all'
  startDate.value = ''
  endDate.value = ''
  fetchLogs(1)
}

// ── Sorting Handlers ─────────────────────────────────────────────────────────
const handleSort = (column: 'created_at' | 'type' | 'module' | 'log_status') => {
  if (sortBy.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortOrder.value = 'desc'
  }
  fetchLogs(1)
}

// ── KPI Card Click Handlers ──────────────────────────────────────────────────
const selectKpiFilter = (type: 'All' | 'Today' | 'Success' | 'Failed' | 'Security') => {
  if (activeKpiFilter.value === type) {
    activeKpiFilter.value = 'All'
  } else {
    activeKpiFilter.value = type
  }

  // Synchronize with query parameters
  if (activeKpiFilter.value === 'Today') {
    applyDatePreset('today')
    return
  } else if (datePreset.value === 'today') {
    applyDatePreset('all')
  }

  if (activeKpiFilter.value === 'Success') {
    selectedStatus.value = 'Success'
  } else if (activeKpiFilter.value === 'Failed') {
    selectedStatus.value = 'Failed'
  } else {
    selectedStatus.value = 'All Statuses'
  }

  fetchLogs(1)
}

// ── Fetch Activity Logs ──────────────────────────────────────────────────────
const fetchLogs = async (page = 1, silent = false) => {
  try {
    if (!silent) isLoading.value = true
    isRefreshing.value = true

    const params: Record<string, any> = {
      page,
      per_page: perPage.value,
      sort_by: sortBy.value,
      sort_order: sortOrder.value,
    }

    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }
    if (selectedModule.value !== 'All Modules') {
      params.module = selectedModule.value
    }
    if (selectedAction.value !== 'All Actions') {
      params.action_type = selectedAction.value
    }
    if (selectedStatus.value !== 'All Statuses') {
      params.status = selectedStatus.value
    }
    if (selectedRole.value !== 'All Roles') {
      params.role = selectedRole.value
    }
    if (activeKpiFilter.value === 'Security') {
      params.filter = 'Logins'
    }
    if (startDate.value) {
      params.date_from = startDate.value
    }
    if (endDate.value) {
      params.date_to = endDate.value
    }

    const res = await apiClient.get('/dept-head/activity-logs', { params })

    if (res.data?.status === 'success' || Array.isArray(res.data?.data)) {
      logs.value = res.data.data || []
      if (res.data.pagination) {
        pagination.value = res.data.pagination
      }
      if (res.data.summary) {
        activitySummary.value = res.data.summary
      }
      if (res.data.filters) {
        backendFilters.value = {
          modules: res.data.filters.modules || [],
          actions: res.data.filters.actions || [],
          roles: res.data.filters.roles || [],
        }
      }
      if (res.data.topActiveUsers && Array.isArray(res.data.topActiveUsers)) {
        topActiveUsers.value = res.data.topActiveUsers
      }

      // Update formatted time
      const now = new Date()
      lastUpdatedTime.value = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      })
    }
  } catch (error) {
    console.error('Failed to fetch activity logs:', error)
    showToast('Failed to load activity logs from server.', 'error')
  } finally {
    isLoading.value = false
    isRefreshing.value = false
  }
}

// ── Export CSV ───────────────────────────────────────────────────────────────
const handleExportLogs = async () => {
  try {
    isExporting.value = true
    showToast('Generating Activity Logs CSV export...', 'info')

    const params: Record<string, any> = {}
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    if (selectedModule.value !== 'All Modules') params.module = selectedModule.value
    if (selectedAction.value !== 'All Actions') params.action_type = selectedAction.value
    if (selectedStatus.value !== 'All Statuses') params.status = selectedStatus.value
    if (selectedRole.value !== 'All Roles') params.role = selectedRole.value
    if (activeKpiFilter.value === 'Security') params.filter = 'Logins'
    if (startDate.value) params.date_from = startDate.value
    if (endDate.value) params.date_to = endDate.value

    const res = await apiClient.get('/dept-head/activity-logs/export', { params })

    const { file, filename, count } = res.data || {}
    if (!file) throw new Error('Missing file data in export response')

    const binary = atob(file)
    const array = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i++) {
      array[i] = binary.charCodeAt(i)
    }

    const blob = new Blob([array], { type: 'text/csv;charset=utf-8;' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = filename || `activity_logs_${Date.now()}.csv`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(link.href)

    showToast(`Successfully exported ${count ?? 'all'} activity records to CSV.`, 'success')
  } catch (error) {
    console.error('Export failed:', error)
    showToast('Failed to export activity logs.', 'error')
  } finally {
    isExporting.value = false
  }
}

// ── Clear Old Logs ───────────────────────────────────────────────────────────
const confirmClearOldLogs = async () => {
  try {
    isClearing.value = true
    const res = await apiClient.post('/dept-head/activity-logs/clear', {
      days: clearDays.value,
    })

    isClearModalOpen.value = false
    showToast(res.data?.message || 'Old activity logs cleared successfully!', 'success')
    fetchLogs(1)
  } catch (error) {
    console.error('Failed to clear logs:', error)
    showToast('Failed to clear old activity logs.', 'error')
  } finally {
    isClearing.value = false
  }
}

// ── Drawer & Details ─────────────────────────────────────────────────────────
const openDrawer = (log: LogEntry) => {
  selectedLog.value = log
  isDrawerOpen.value = true
}

const closeDrawer = () => {
  isDrawerOpen.value = false
}

const copyToClipboard = async (text: string, fieldName: string) => {
  try {
    await navigator.clipboard.writeText(text)
    copiedField.value = fieldName
    showToast(`Copied ${fieldName} to clipboard!`, 'success')
    setTimeout(() => {
      if (copiedField.value === fieldName) {
        copiedField.value = null
      }
    }, 2500)
  } catch (err) {
    console.error('Copy failed:', err)
  }
}

// ── Relative Time Helper ─────────────────────────────────────────────────────
const getRelativeTime = (rawTime?: string) => {
  if (!rawTime) return ''
  const date = new Date(rawTime)
  const now = new Date()
  const diffSec = Math.floor((now.getTime() - date.getTime()) / 1000)
  if (diffSec < 45) return 'Just now'
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`
  if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`
  if (diffSec < 604800) return `${Math.floor(diffSec / 86400)}d ago`
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// ── Styling Helpers ──────────────────────────────────────────────────────────
const getRoleBadgeClass = (role: string) => {
  const r = (role || '').toLowerCase()
  if (r.includes('head') || r.includes('dept')) {
    return 'bg-purple-50 text-[#5138ed] border border-purple-200/70'
  }
  if (r.includes('admin')) {
    return 'bg-indigo-50 text-indigo-700 border border-indigo-200/70'
  }
  if (r.includes('instructor')) {
    return 'bg-blue-50 text-blue-700 border border-blue-200/70'
  }
  if (r.includes('student')) {
    return 'bg-emerald-50 text-emerald-700 border border-emerald-200/70'
  }
  return 'bg-slate-100 text-slate-700 border border-slate-200/70'
}

const getAvatarColorClass = (role: string) => {
  const r = (role || '').toLowerCase()
  if (r.includes('head') || r.includes('dept')) return 'bg-purple-100 text-[#5138ed]'
  if (r.includes('admin')) return 'bg-indigo-100 text-indigo-700'
  if (r.includes('instructor')) return 'bg-blue-100 text-blue-700'
  if (r.includes('student')) return 'bg-emerald-100 text-emerald-700'
  return 'bg-slate-100 text-slate-600'
}

const getAvatarInitials = (name: string) => {
  if (!name) return 'SY'
  return name
    .trim()
    .split(/\s+/)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const getActionColor = (type: string) => {
  const t = (type || '').toLowerCase()
  if (t === 'login' || t.includes('auth')) return 'text-emerald-600 bg-emerald-50 border-emerald-100'
  if (t.includes('create') || t.includes('add')) return 'text-emerald-600 bg-emerald-50 border-emerald-100'
  if (t.includes('update') || t.includes('edit')) return 'text-amber-600 bg-amber-50 border-amber-100'
  if (t.includes('delete') || t.includes('remove')) return 'text-rose-600 bg-rose-50 border-rose-100'
  if (t.includes('submit')) return 'text-[#5138ed] bg-purple-50 border-purple-100'
  if (t.includes('export')) return 'text-indigo-600 bg-indigo-50 border-indigo-100'
  return 'text-slate-600 bg-slate-50 border-slate-200'
}

// ── Pagination Calculation ───────────────────────────────────────────────────
const displayedPages = computed(() => {
  const total = pagination.value.last_page
  const current = pagination.value.current_page
  if (total <= 6) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }
  const pages: (number | string)[] = []
  if (current <= 3) {
    pages.push(1, 2, 3, 4, '...', total)
  } else if (current >= total - 2) {
    pages.push(1, '...', total - 3, total - 2, total - 1, total)
  } else {
    pages.push(1, '...', current - 1, current, current + 1, '...', total)
  }
  return pages
})

const goToPage = (page: number) => {
  if (page < 1 || page > pagination.value.last_page || page === pagination.value.current_page) return
  fetchLogs(page)
}

const onPerPageChange = () => {
  fetchLogs(1)
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchLogs(1)
})
</script>

<template>
  <div class="max-w-[1550px] mx-auto pb-12 min-w-0 max-w-full space-y-6">

    <!-- Toast Notification -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform translate-y-4 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform translate-y-4 opacity-0"
    >
      <div
        v-if="toast.show"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-2xl border text-[13px] font-semibold max-w-[90vw] backdrop-blur-md"
        :class="{
          'bg-slate-900/95 text-white border-slate-700': toast.type === 'success',
          'bg-rose-600/95 text-white border-rose-500': toast.type === 'error',
          'bg-indigo-900/95 text-white border-indigo-700': toast.type === 'info',
        }"
      >
        <CheckCircle2 v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-400 shrink-0" />
        <XCircle v-else-if="toast.type === 'error'" class="w-5 h-5 text-white shrink-0" />
        <Info v-else class="w-5 h-5 text-indigo-300 shrink-0" />
        <span class="break-words">{{ toast.message }}</span>
      </div>
    </Transition>

    <!-- SECTION 1: PREMIUM PAGE HEADER -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-xs">
      <div class="flex items-start sm:items-center gap-4">
        <!-- Wollo Purple Icon Container -->
        <div class="w-13 h-13 rounded-2xl bg-indigo-50 border border-indigo-100/80 flex items-center justify-center shrink-0 shadow-xs text-[#5138ed]">
          <Activity class="w-7 h-7" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="text-[22px] sm:text-[24px] font-extrabold text-slate-800 tracking-tight">Active Logs</h1>
            <!-- Live Monitoring Status Badge -->
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80 shadow-2xs">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              Live Monitoring
            </span>
          </div>
          <p class="text-[13px] text-slate-500 mt-0.5 truncate">
            Monitor department activities, important changes, and system events.
          </p>
          <!-- Breadcrumb Navigation -->
          <div class="flex items-center gap-1.5 mt-2 text-[12px] text-slate-400">
            <Home class="w-3.5 h-3.5 shrink-0 text-slate-400" />
            <span>Dashboard</span>
            <span class="text-slate-300">/</span>
            <span class="text-[#5138ed] font-semibold truncate">Active Logs</span>
          </div>
        </div>
      </div>

      <!-- Header Controls: Last Updated + Refresh + Export -->
      <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 self-stretch lg:self-auto justify-end">
        <!-- Last Updated Indicator -->
        <div v-if="lastUpdatedTime" class="hidden sm:flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-50 text-slate-500 text-[12px] font-medium border border-slate-100">
          <Clock class="w-3.5 h-3.5 text-slate-400" />
          <span>Updated at <strong class="text-slate-700 font-bold">{{ lastUpdatedTime }}</strong></span>
        </div>

        <!-- Functional Refresh Button -->
        <button
          @click="fetchLogs(pagination.current_page, false)"
          :disabled="isRefreshing"
          title="Refresh activity logs"
          class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 min-h-[42px] rounded-xl text-[13px] font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 transition-all shadow-xs cursor-pointer active:scale-95 disabled:opacity-60"
        >
          <RefreshCw class="w-4 h-4 text-slate-500" :class="{ 'animate-spin text-[#5138ed]': isRefreshing }" />
          <span class="hidden sm:inline">Refresh</span>
        </button>

        <!-- CSV Export Button -->
        <button
          @click="handleExportLogs"
          :disabled="isExporting"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[42px] rounded-xl text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-600 transition-all shadow-sm shadow-indigo-200 cursor-pointer active:scale-95 disabled:opacity-60"
        >
          <Download class="w-4 h-4" :class="{ 'animate-bounce': isExporting }" />
          <span>{{ isExporting ? 'Exporting...' : 'Export CSV' }}</span>
        </button>
      </div>
    </div>

    <!-- SECTION 2: ACTIVITY SUMMARY KPI CARDS (TOP ROW ABOVE TABLE) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 sm:gap-4">

      <!-- 1. Total Activities -->
      <div
        @click="selectKpiFilter('All')"
        class="bg-white border rounded-2xl p-4 sm:p-5 shadow-xs cursor-pointer transition-all duration-200 hover:shadow-md relative overflow-hidden group select-none"
        :class="activeKpiFilter === 'All' ? 'border-[#5138ed] ring-2 ring-[#5138ed]/20 bg-indigo-50/20' : 'border-slate-100 hover:border-indigo-200'"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center font-bold shadow-2xs group-hover:scale-105 transition-transform">
            <Activity class="w-5 h-5" />
          </div>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider bg-slate-100 text-slate-600">All</span>
        </div>
        <div class="text-[24px] sm:text-[28px] font-black text-slate-900 leading-tight">
          {{ activitySummary.all }}
        </div>
        <div class="text-[12px] font-bold text-slate-700 mt-0.5">Total Activities</div>
        <p class="text-[11px] text-slate-400 mt-1 truncate">All recorded events</p>
      </div>

      <!-- 2. Activities Today -->
      <div
        @click="selectKpiFilter('Today')"
        class="bg-white border rounded-2xl p-4 sm:p-5 shadow-xs cursor-pointer transition-all duration-200 hover:shadow-md relative overflow-hidden group select-none"
        :class="activeKpiFilter === 'Today' ? 'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/20' : 'border-slate-100 hover:border-emerald-200'"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold shadow-2xs group-hover:scale-105 transition-transform">
            <Calendar class="w-5 h-5" />
          </div>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider bg-emerald-100/70 text-emerald-700">Today</span>
        </div>
        <div class="text-[24px] sm:text-[28px] font-black text-slate-900 leading-tight">
          {{ activitySummary.today }}
        </div>
        <div class="text-[12px] font-bold text-slate-700 mt-0.5">Activities Today</div>
        <p class="text-[11px] text-slate-400 mt-1 truncate">Recorded past 24 hours</p>
      </div>

      <!-- 3. Successful Activities -->
      <div
        @click="selectKpiFilter('Success')"
        class="bg-white border rounded-2xl p-4 sm:p-5 shadow-xs cursor-pointer transition-all duration-200 hover:shadow-md relative overflow-hidden group select-none"
        :class="activeKpiFilter === 'Success' ? 'border-teal-500 ring-2 ring-teal-500/20 bg-teal-50/20' : 'border-slate-100 hover:border-teal-200'"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold shadow-2xs group-hover:scale-105 transition-transform">
            <CheckCircle2 class="w-5 h-5" />
          </div>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider bg-teal-100/70 text-teal-700">Success</span>
        </div>
        <div class="text-[24px] sm:text-[28px] font-black text-slate-900 leading-tight">
          {{ activitySummary.successful }}
        </div>
        <div class="text-[12px] font-bold text-slate-700 mt-0.5">Successful</div>
        <p class="text-[11px] text-slate-400 mt-1 truncate">Completed actions</p>
      </div>

      <!-- 4. Failed Activities -->
      <div
        @click="selectKpiFilter('Failed')"
        class="bg-white border rounded-2xl p-4 sm:p-5 shadow-xs cursor-pointer transition-all duration-200 hover:shadow-md relative overflow-hidden group select-none"
        :class="activeKpiFilter === 'Failed' ? 'border-rose-500 ring-2 ring-rose-500/20 bg-rose-50/20' : 'border-slate-100 hover:border-rose-200'"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold shadow-2xs group-hover:scale-105 transition-transform">
            <XCircle class="w-5 h-5" />
          </div>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider bg-rose-100/70 text-rose-700">Issues</span>
        </div>
        <div class="text-[24px] sm:text-[28px] font-black text-slate-900 leading-tight">
          {{ activitySummary.failed }}
        </div>
        <div class="text-[12px] font-bold text-slate-700 mt-0.5">Failed Activities</div>
        <p class="text-[11px] text-slate-400 mt-1 truncate">Errors & failed events</p>
      </div>

      <!-- 5. Security & Login Events -->
      <div
        @click="selectKpiFilter('Security')"
        class="col-span-2 sm:col-span-1 bg-white border rounded-2xl p-4 sm:p-5 shadow-xs cursor-pointer transition-all duration-200 hover:shadow-md relative overflow-hidden group select-none"
        :class="activeKpiFilter === 'Security' ? 'border-purple-500 ring-2 ring-purple-500/20 bg-purple-50/20' : 'border-slate-100 hover:border-purple-200'"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shadow-2xs group-hover:scale-105 transition-transform">
            <ShieldAlert class="w-5 h-5" />
          </div>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider bg-purple-100/70 text-purple-700">Security</span>
        </div>
        <div class="text-[24px] sm:text-[28px] font-black text-slate-900 leading-tight">
          {{ activitySummary.security }}
        </div>
        <div class="text-[12px] font-bold text-slate-700 mt-0.5">Security Events</div>
        <p class="text-[11px] text-slate-400 mt-1 truncate">Auth & security checks</p>
      </div>

    </div>

    <!-- SECTION 3: SEARCH & FILTER TOOLBAR -->
    <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-5 shadow-xs space-y-4">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Debounced Search Input -->
        <div class="relative flex-1 min-w-[260px]">
          <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="searchQuery"
            @input="onSearchInput"
            type="text"
            placeholder="Search by user, email, action, module, description or IP..."
            class="w-full pl-10 pr-9 py-2.5 min-h-[42px] text-[13px] border border-slate-200 rounded-xl bg-slate-50/50 hover:bg-white focus:bg-white focus:outline-none focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/10 shadow-xs font-medium text-slate-800 placeholder-slate-400 transition-all"
          />
          <button
            v-if="searchQuery"
            @click="clearSearch"
            title="Clear search"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5 rounded-full hover:bg-slate-200/50 transition-colors"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Filter Dropdowns Row -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
          <!-- Module Filter -->
          <div class="relative min-w-[130px] flex-1 sm:flex-none">
            <select
              v-model="selectedModule"
              @change="fetchLogs(1)"
              class="w-full appearance-none border border-slate-200 rounded-xl pl-3 pr-8 py-2 min-h-[42px] text-[12px] font-bold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-[#5138ed] shadow-xs cursor-pointer"
            >
              <option value="All Modules">All Modules</option>
              <option v-for="m in backendFilters.modules" :key="m" :value="m">{{ m }}</option>
            </select>
            <ChevronDown class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          </div>

          <!-- Action Type Filter -->
          <div class="relative min-w-[130px] flex-1 sm:flex-none">
            <select
              v-model="selectedAction"
              @change="fetchLogs(1)"
              class="w-full appearance-none border border-slate-200 rounded-xl pl-3 pr-8 py-2 min-h-[42px] text-[12px] font-bold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-[#5138ed] shadow-xs cursor-pointer"
            >
              <option value="All Actions">All Actions</option>
              <option v-for="a in backendFilters.actions" :key="a" :value="a">{{ a }}</option>
            </select>
            <ChevronDown class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          </div>

          <!-- Status Filter -->
          <div class="relative min-w-[120px] flex-1 sm:flex-none">
            <select
              v-model="selectedStatus"
              @change="fetchLogs(1)"
              class="w-full appearance-none border border-slate-200 rounded-xl pl-3 pr-8 py-2 min-h-[42px] text-[12px] font-bold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-[#5138ed] shadow-xs cursor-pointer"
            >
              <option value="All Statuses">All Statuses</option>
              <option value="Success">Success</option>
              <option value="Failed">Failed</option>
            </select>
            <ChevronDown class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          </div>

          <!-- Role Filter -->
          <div class="relative min-w-[120px] flex-1 sm:flex-none">
            <select
              v-model="selectedRole"
              @change="fetchLogs(1)"
              class="w-full appearance-none border border-slate-200 rounded-xl pl-3 pr-8 py-2 min-h-[42px] text-[12px] font-bold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-[#5138ed] shadow-xs cursor-pointer"
            >
              <option value="All Roles">All Roles</option>
              <option value="dept_head">Dept Head</option>
              <option value="instructor">Instructor</option>
              <option value="admin">Super Admin</option>
              <option value="student">Student</option>
            </select>
            <ChevronDown class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          </div>

          <!-- Date Filter Trigger -->
          <div class="relative flex-1 sm:flex-none">
            <button
              @click="showDatePopover = !showDatePopover"
              class="w-full inline-flex items-center justify-between gap-2 border border-slate-200 rounded-xl px-3.5 py-2 min-h-[42px] text-[12px] font-bold text-slate-700 bg-white hover:border-slate-300 shadow-xs cursor-pointer"
              :class="startDate || endDate ? 'border-[#5138ed] text-[#5138ed] bg-indigo-50/20' : ''"
            >
              <div class="flex items-center gap-2 truncate">
                <Calendar class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                <span class="truncate">{{ dateRangeDisplayLabel }}</span>
              </div>
              <ChevronDown class="w-3 h-3 text-slate-400 shrink-0" />
            </button>

            <!-- Date Picker Popover -->
            <div
              v-if="showDatePopover"
              class="absolute right-0 top-full mt-2 z-30 bg-white border border-slate-200 rounded-2xl shadow-xl p-4 w-[320px] animate-in fade-in zoom-in-95 duration-100"
            >
              <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                <span class="text-[13px] font-bold text-slate-800">Filter by Date</span>
                <button @click="showDatePopover = false" class="text-slate-400 hover:text-slate-600 p-1">
                  <X class="w-4 h-4" />
                </button>
              </div>

              <!-- Quick Presets -->
              <div class="grid grid-cols-2 gap-1.5 mb-3">
                <button
                  @click="applyDatePreset('all')"
                  class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors text-left"
                  :class="datePreset === 'all' ? 'bg-[#5138ed] text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                >
                  All Time
                </button>
                <button
                  @click="applyDatePreset('today')"
                  class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors text-left"
                  :class="datePreset === 'today' ? 'bg-[#5138ed] text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                >
                  Today
                </button>
                <button
                  @click="applyDatePreset('yesterday')"
                  class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors text-left"
                  :class="datePreset === 'yesterday' ? 'bg-[#5138ed] text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                >
                  Yesterday
                </button>
                <button
                  @click="applyDatePreset('7days')"
                  class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors text-left"
                  :class="datePreset === '7days' ? 'bg-[#5138ed] text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                >
                  Last 7 Days
                </button>
                <button
                  @click="applyDatePreset('30days')"
                  class="col-span-2 px-2.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors text-left"
                  :class="datePreset === '30days' ? 'bg-[#5138ed] text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                >
                  Last 30 Days
                </button>
              </div>

              <!-- Custom Inputs -->
              <div class="space-y-2 pt-2 border-t border-slate-100">
                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Custom Range</div>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-[10px] text-slate-400 font-medium mb-1">From</label>
                    <input
                      v-model="startDate"
                      type="date"
                      class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-[11px] font-semibold text-slate-700 focus:outline-none focus:border-[#5138ed]"
                    />
                  </div>
                  <div>
                    <label class="block text-[10px] text-slate-400 font-medium mb-1">To</label>
                    <input
                      v-model="endDate"
                      type="date"
                      class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-[11px] font-semibold text-slate-700 focus:outline-none focus:border-[#5138ed]"
                    />
                  </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                  <button
                    @click="resetDates"
                    class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-600 hover:bg-slate-100 transition-colors"
                  >
                    Reset
                  </button>
                  <button
                    @click="applyCustomDates"
                    class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-white bg-[#5138ed] hover:bg-indigo-600 transition-colors shadow-2xs"
                  >
                    Apply
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Active Filters Chips & Counter -->
      <div v-if="hasActiveFilters" class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100">
        <div class="flex flex-wrap items-center gap-1.5">
          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Active Filters:</span>

          <!-- Search Chip -->
          <span
            v-if="searchQuery.trim()"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-indigo-50 text-[#5138ed] border border-indigo-100"
          >
            <span>Search: "{{ searchQuery }}"</span>
            <button @click="clearSearch" class="hover:text-indigo-800"><X class="w-3 h-3" /></button>
          </span>

          <!-- Module Chip -->
          <span
            v-if="selectedModule !== 'All Modules'"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-100"
          >
            <span>Module: {{ selectedModule }}</span>
            <button @click="selectedModule = 'All Modules'; fetchLogs(1);" class="hover:text-purple-900"><X class="w-3 h-3" /></button>
          </span>

          <!-- Action Chip -->
          <span
            v-if="selectedAction !== 'All Actions'"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-100"
          >
            <span>Action: {{ selectedAction }}</span>
            <button @click="selectedAction = 'All Actions'; fetchLogs(1);" class="hover:text-amber-900"><X class="w-3 h-3" /></button>
          </span>

          <!-- Status Chip -->
          <span
            v-if="selectedStatus !== 'All Statuses'"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold"
            :class="selectedStatus === 'Success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'"
          >
            <span>Status: {{ selectedStatus }}</span>
            <button @click="selectedStatus = 'All Statuses'; fetchLogs(1);" class="hover:opacity-75"><X class="w-3 h-3" /></button>
          </span>

          <!-- Role Chip -->
          <span
            v-if="selectedRole !== 'All Roles'"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100"
          >
            <span>Role: {{ selectedRole }}</span>
            <button @click="selectedRole = 'All Roles'; fetchLogs(1);" class="hover:text-blue-900"><X class="w-3 h-3" /></button>
          </span>

          <!-- Date Chip -->
          <span
            v-if="startDate || endDate"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200"
          >
            <span>Date: {{ dateRangeDisplayLabel }}</span>
            <button @click="resetDates" class="hover:text-slate-900"><X class="w-3 h-3" /></button>
          </span>

          <!-- Clear All Button -->
          <button
            @click="clearAllFilters"
            class="text-[11px] font-bold text-rose-600 hover:text-rose-700 underline ml-2 transition-colors cursor-pointer"
          >
            Clear all
          </button>
        </div>

        <!-- Matched count -->
        <span class="text-[11px] font-semibold text-slate-400">
          Found <strong class="text-slate-700">{{ pagination.total }}</strong> matching records
        </span>
      </div>
    </div>

    <!-- SECTION 4: REDESIGNED AUDIT TABLE -->
    <div class="bg-white border border-slate-100 rounded-2xl shadow-xs overflow-hidden flex flex-col min-h-[420px]">

      <!-- Loading State -->
      <div v-if="isLoading" class="p-20 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-3 border-indigo-200 border-t-[#5138ed] rounded-full animate-spin"></div>
        <p class="mt-4 text-[13px] font-bold text-slate-600">Loading department activity logs...</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Fetching audited actions from database</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="logs.length === 0" class="p-16 flex flex-col items-center justify-center text-center">
        <div class="w-16 h-16 rounded-2xl bg-indigo-50/50 border border-indigo-100/60 flex items-center justify-center text-[#5138ed] mb-3">
          <FileText class="w-8 h-8 text-indigo-400" />
        </div>
        <h3 class="text-[16px] font-bold text-slate-800">No activity logs found</h3>
        <p class="text-[12px] text-slate-400 mt-1 max-w-md">
          {{ hasActiveFilters ? 'No activity records match your current filter parameters. Try clearing some filters or searching for different keywords.' : 'No activity records have been logged in this department yet.' }}
        </p>
        <button
          v-if="hasActiveFilters"
          @click="clearAllFilters"
          class="mt-4 px-4 py-2 min-h-[38px] bg-indigo-50 text-[#5138ed] rounded-xl text-[12px] font-bold hover:bg-indigo-100 transition-colors cursor-pointer"
        >
          Reset All Filters
        </button>
      </div>

      <!-- Desktop & Tablet Table Display -->
      <div v-else class="hidden md:block flex-1 overflow-x-auto min-w-0 w-full">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/80 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider select-none">
              <th class="px-5 py-3.5 text-center w-12">#</th>
              
              <!-- Sortable Date & Time -->
              <th
                @click="handleSort('created_at')"
                class="px-4 py-3.5 cursor-pointer hover:bg-slate-100/60 transition-colors"
              >
                <div class="flex items-center gap-1.5">
                  <span>Date & Time</span>
                  <ArrowUp v-if="sortBy === 'created_at' && sortOrder === 'asc'" class="w-3.5 h-3.5 text-[#5138ed]" />
                  <ArrowDown v-else-if="sortBy === 'created_at' && sortOrder === 'desc'" class="w-3.5 h-3.5 text-[#5138ed]" />
                  <ArrowUpDown v-else class="w-3 h-3 text-slate-300" />
                </div>
              </th>

              <th class="px-4 py-3.5">User</th>
              <th class="px-4 py-3.5">Role</th>

              <!-- Sortable Action -->
              <th
                @click="handleSort('type')"
                class="px-4 py-3.5 cursor-pointer hover:bg-slate-100/60 transition-colors"
              >
                <div class="flex items-center gap-1.5">
                  <span>Action</span>
                  <ArrowUp v-if="sortBy === 'type' && sortOrder === 'asc'" class="w-3.5 h-3.5 text-[#5138ed]" />
                  <ArrowDown v-else-if="sortBy === 'type' && sortOrder === 'desc'" class="w-3.5 h-3.5 text-[#5138ed]" />
                  <ArrowUpDown v-else class="w-3 h-3 text-slate-300" />
                </div>
              </th>

              <!-- Sortable Module -->
              <th
                @click="handleSort('module')"
                class="px-4 py-3.5 cursor-pointer hover:bg-slate-100/60 transition-colors"
              >
                <div class="flex items-center gap-1.5">
                  <span>Module</span>
                  <ArrowUp v-if="sortBy === 'module' && sortOrder === 'asc'" class="w-3.5 h-3.5 text-[#5138ed]" />
                  <ArrowDown v-else-if="sortBy === 'module' && sortOrder === 'desc'" class="w-3.5 h-3.5 text-[#5138ed]" />
                  <ArrowUpDown v-else class="w-3 h-3 text-slate-300" />
                </div>
              </th>

              <th class="px-4 py-3.5">Description</th>
              <th class="px-4 py-3.5">IP Address</th>

              <!-- Sortable Status -->
              <th
                @click="handleSort('log_status')"
                class="px-4 py-3.5 cursor-pointer hover:bg-slate-100/60 transition-colors"
              >
                <div class="flex items-center gap-1.5">
                  <span>Status</span>
                  <ArrowUp v-if="sortBy === 'log_status' && sortOrder === 'asc'" class="w-3.5 h-3.5 text-[#5138ed]" />
                  <ArrowDown v-else-if="sortBy === 'log_status' && sortOrder === 'desc'" class="w-3.5 h-3.5 text-[#5138ed]" />
                  <ArrowUpDown v-else class="w-3 h-3 text-slate-300" />
                </div>
              </th>

              <th class="px-4 py-3.5 text-center">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100/70">
            <tr
              v-for="(log, idx) in logs"
              :key="log.id"
              class="hover:bg-slate-50/70 transition-colors group"
            >
              <!-- Index / Counter -->
              <td class="px-5 py-3.5 text-center text-[11px] font-bold text-slate-400">
                {{ (pagination.from || 1) + idx }}
              </td>

              <!-- Date & Time -->
              <td class="px-4 py-3.5 whitespace-nowrap">
                <div class="flex flex-col">
                  <span class="text-[12px] font-bold text-slate-800">{{ log.date || log.time.split('\n')[0] }}</span>
                  <div class="flex items-center gap-1.5 mt-0.5">
                    <Clock class="w-3 h-3 text-slate-400" />
                    <span class="text-[11px] font-semibold text-slate-500">{{ log.time_only || log.time.split('\n')[1] }}</span>
                    <span class="text-[10px] text-slate-400 font-medium ml-1">({{ getRelativeTime(log.raw_time) }})</span>
                  </div>
                </div>
              </td>

              <!-- User -->
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-2.5">
                  <div
                    :class="getAvatarColorClass(log.role)"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-extrabold shrink-0 shadow-2xs border border-white"
                  >
                    {{ getAvatarInitials(log.user) }}
                  </div>
                  <div class="min-w-0 max-w-[170px]">
                    <p class="text-[12px] font-bold text-slate-800 truncate" :title="log.user">{{ log.user }}</p>
                    <p class="text-[11px] text-slate-400 truncate" :title="log.email">{{ log.email || '—' }}</p>
                  </div>
                </div>
              </td>

              <!-- Role -->
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span
                  :class="getRoleBadgeClass(log.role)"
                  class="px-2.5 py-1 rounded-md text-[10px] font-bold inline-block shadow-2xs"
                >
                  {{ log.role }}
                </span>
              </td>

              <!-- Action -->
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span
                  :class="getActionColor(log.actionType)"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold border shadow-2xs"
                >
                  <Activity class="w-3 h-3" />
                  <span>{{ log.action }}</span>
                </span>
              </td>

              <!-- Module -->
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                  {{ log.module }}
                </span>
              </td>

              <!-- Description -->
              <td class="px-4 py-3.5 text-[12px] text-slate-600 max-w-[260px] truncate" :title="log.description">
                {{ log.description }}
              </td>

              <!-- IP Address -->
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span class="font-mono text-[11px] font-bold text-slate-600 bg-slate-50 px-2 py-0.5 rounded border border-slate-200">
                  {{ log.ipAddress }}
                </span>
              </td>

              <!-- Status -->
              <td class="px-4 py-3.5 whitespace-nowrap">
                <span
                  v-if="log.status === 'Success'"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/70"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  Success
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full bg-rose-50 text-rose-700 border border-rose-200/70"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                  Failed
                </span>
              </td>

              <!-- Action: Details Button -->
              <td class="px-4 py-3.5 text-center whitespace-nowrap">
                <button
                  @click="openDrawer(log)"
                  title="View complete activity details"
                  class="inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-bold text-[#5138ed] bg-indigo-50 hover:bg-[#5138ed] hover:text-white transition-all shadow-2xs cursor-pointer group-hover:scale-105"
                >
                  <Eye class="w-3.5 h-3.5" />
                  <span>View</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Cards Display -->
      <div v-if="!isLoading && logs.length > 0" class="md:hidden divide-y divide-slate-100">
        <div v-for="log in logs" :key="log.id" class="p-4 space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
              <div :class="getAvatarColorClass(log.role)" class="w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-extrabold shrink-0 shadow-2xs">
                {{ getAvatarInitials(log.user) }}
              </div>
              <div class="min-w-0">
                <p class="text-[13px] font-bold text-slate-800 truncate">{{ log.user }}</p>
                <p class="text-[11px] text-slate-400 truncate">{{ log.email }}</p>
              </div>
            </div>
            <span
              v-if="log.status === 'Success'"
              class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              Success
            </span>
            <span
              v-else
              class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-rose-50 text-rose-700 border border-rose-200 shrink-0"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
              Failed
            </span>
          </div>

          <div class="bg-slate-50 p-3 rounded-xl space-y-2 text-[12px]">
            <div class="flex items-center justify-between">
              <span class="text-slate-400 font-medium">Action:</span>
              <span :class="getActionColor(log.actionType)" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                {{ log.action }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400 font-medium">Module:</span>
              <span class="font-bold text-slate-700">{{ log.module }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400 font-medium">Role:</span>
              <span :class="getRoleBadgeClass(log.role)" class="px-2 py-0.5 rounded text-[10px] font-bold">
                {{ log.role }}
              </span>
            </div>
            <div class="pt-1 text-slate-700 text-[12px] leading-relaxed break-words border-t border-slate-200/50">
              {{ log.description }}
            </div>
          </div>

          <div class="flex items-center justify-between text-[11px] text-slate-400">
            <span>{{ log.date }} • {{ log.time_only }}</span>
            <span class="font-mono">{{ log.ipAddress }}</span>
          </div>

          <button
            @click="openDrawer(log)"
            class="w-full min-h-[38px] inline-flex items-center justify-center gap-2 text-[12px] font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors cursor-pointer"
          >
            <Eye class="w-4 h-4" />
            <span>View Full Details</span>
          </button>
        </div>
      </div>

      <!-- SECTION 5: PAGINATION & PAGE SIZE TOOLBAR -->
      <div v-if="pagination.total > 0" class="px-4 sm:px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 mt-auto bg-slate-50/40">
        <!-- Range Counter & Page Size Selector -->
        <div class="flex items-center gap-3 text-[12px] text-slate-500">
          <span>
            Showing <strong class="text-slate-800">{{ pagination.from || 1 }}</strong> to
            <strong class="text-slate-800">{{ pagination.to || pagination.total }}</strong> of
            <strong class="text-slate-800">{{ pagination.total }}</strong> activities
          </span>
          <div class="hidden sm:flex items-center gap-1.5 ml-2">
            <span class="text-slate-400 text-[11px]">Per page:</span>
            <select
              v-model="perPage"
              @change="onPerPageChange"
              class="border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-700 bg-white focus:outline-none focus:border-[#5138ed] cursor-pointer"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
        </div>

        <!-- Page Navigation Buttons -->
        <div class="flex items-center gap-1.5">
          <!-- Previous Button -->
          <button
            @click="goToPage(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            :class="pagination.current_page <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 cursor-pointer'"
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors bg-white shadow-2xs"
            title="Previous Page"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>

          <!-- Numbered Page Buttons -->
          <template v-for="(p, idx) in displayedPages" :key="idx">
            <span v-if="p === '...'" class="w-7 h-8 flex items-center justify-center text-slate-400 text-[12px]">...</span>
            <button
              v-else
              @click="goToPage(Number(p))"
              :class="pagination.current_page === p ? 'bg-[#5138ed] text-white shadow-xs font-black' : 'text-slate-600 hover:bg-slate-100 bg-white border border-slate-200 font-semibold'"
              class="w-8 h-8 flex items-center justify-center rounded-lg text-[12px] transition-colors shadow-2xs cursor-pointer"
            >
              {{ p }}
            </button>
          </template>

          <!-- Next Button -->
          <button
            @click="goToPage(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            :class="pagination.current_page >= pagination.last_page ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 cursor-pointer'"
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors bg-white shadow-2xs"
            title="Next Page"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>

    </div>

    <!-- SECTION 6: ANALYTICS & QUICK ACTIONS (BOTTOM PANELS) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- 1. Top Active Users Card -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-xs p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <User class="w-4 h-4 text-[#5138ed]" />
            <h3 class="text-[14px] font-bold text-slate-800">Top Active Department Users</h3>
          </div>
          <span class="text-[11px] font-bold text-slate-400">By Log Count</span>
        </div>
        <div class="space-y-3">
          <div
            v-for="user in topActiveUsers"
            :key="user.name"
            @click="searchQuery = user.name; fetchLogs(1);"
            class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors border border-transparent hover:border-slate-100 group"
            title="Click to filter logs by this user"
          >
            <div :class="getAvatarColorClass(user.role)" class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 shadow-2xs">
              {{ getAvatarInitials(user.name) }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors truncate">{{ user.name }}</p>
              <p class="text-[10px] text-slate-400">{{ user.role }}</p>
            </div>
            <span class="text-[11px] font-bold text-slate-700 bg-slate-50 px-2.5 py-0.5 rounded-md border border-slate-200/70 shrink-0">
              {{ user.count }} actions
            </span>
          </div>

          <div v-if="topActiveUsers.length === 0" class="text-center py-6 text-[12px] text-slate-400">
            No active user records logged yet.
          </div>
        </div>
      </div>

      <!-- 2. Activity Type Breakdown Card -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-xs p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <Layers class="w-4 h-4 text-[#5138ed]" />
            <h3 class="text-[14px] font-bold text-slate-800">Activity Breakdown</h3>
          </div>
          <span class="text-[11px] font-bold text-slate-400">Total: {{ activitySummary.all }}</span>
        </div>

        <div class="space-y-3">
          <!-- Successful -->
          <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
              <span class="text-[12px] font-bold text-slate-700">Successful Operations</span>
            </div>
            <span class="text-[13px] font-black text-emerald-600">{{ activitySummary.successful }}</span>
          </div>

          <!-- Failed -->
          <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div>
              <span class="text-[12px] font-bold text-slate-700">Failed / Errors</span>
            </div>
            <span class="text-[13px] font-black text-rose-600">{{ activitySummary.failed }}</span>
          </div>

          <!-- Data Changes -->
          <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div>
              <span class="text-[12px] font-bold text-slate-700">Data Modifications</span>
            </div>
            <span class="text-[13px] font-black text-amber-600">{{ activitySummary.dataChanges }}</span>
          </div>

          <!-- Security / Logins -->
          <div class="p-2.5 rounded-xl bg-slate-50/60 border border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="w-2.5 h-2.5 rounded-full bg-purple-500"></div>
              <span class="text-[12px] font-bold text-slate-700">Auth & Security Audits</span>
            </div>
            <span class="text-[13px] font-black text-purple-600">{{ activitySummary.security }}</span>
          </div>
        </div>
      </div>

      <!-- 3. Quick Management Actions Card -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-xs p-5 sm:p-6">
        <div class="flex items-center gap-2 mb-4">
          <SlidersHorizontal class="w-4 h-4 text-[#5138ed]" />
          <h3 class="text-[14px] font-bold text-slate-800">Quick Actions</h3>
        </div>

        <div class="space-y-2.5">
          <!-- Export Logs Button -->
          <button
            @click="handleExportLogs"
            :disabled="isExporting"
            class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-200/80 hover:border-indigo-300 hover:bg-indigo-50/30 transition-all group text-left cursor-pointer"
          >
            <div class="flex items-center gap-3">
              <Download class="w-4 h-4 text-[#5138ed]" />
              <div>
                <p class="text-[12px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Export Filtered Logs</p>
                <p class="text-[10px] text-slate-400">Download active dataset as CSV</p>
              </div>
            </div>
            <ChevronRight class="w-4 h-4 text-slate-300 group-hover:text-[#5138ed] transition-colors" />
          </button>

          <!-- Clear Old Logs Button -->
          <button
            @click="isClearModalOpen = true"
            class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-200/80 hover:border-rose-300 hover:bg-rose-50/30 transition-all group text-left cursor-pointer"
          >
            <div class="flex items-center gap-3">
              <Trash2 class="w-4 h-4 text-rose-500" />
              <div>
                <p class="text-[12px] font-bold text-slate-800 group-hover:text-rose-600 transition-colors">Clear Archived Logs</p>
                <p class="text-[10px] text-slate-400">Purge old records past threshold</p>
              </div>
            </div>
            <ChevronRight class="w-4 h-4 text-slate-300 group-hover:text-rose-500 transition-colors" />
          </button>

          <!-- Filter by Security Events -->
          <button
            @click="selectKpiFilter('Security')"
            class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-200/80 hover:border-purple-300 hover:bg-purple-50/30 transition-all group text-left cursor-pointer"
          >
            <div class="flex items-center gap-3">
              <ShieldAlert class="w-4 h-4 text-purple-600" />
              <div>
                <p class="text-[12px] font-bold text-slate-800 group-hover:text-purple-600 transition-colors">Security Audit View</p>
                <p class="text-[10px] text-slate-400">Inspect authentication and login logs</p>
              </div>
            </div>
            <ChevronRight class="w-4 h-4 text-slate-300 group-hover:text-purple-600 transition-colors" />
          </button>
        </div>
      </div>

    </div>

    <!-- SECTION 7: ACTIVITY DETAILS SLIDE-OUT DRAWER / MODAL -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isDrawerOpen && selectedLog"
        class="fixed inset-0 z-50 flex items-center justify-end bg-slate-900/40 backdrop-blur-xs p-0"
        @click.self="closeDrawer"
      >
        <div class="bg-white w-full max-w-xl h-full shadow-2xl flex flex-col overflow-hidden animate-in slide-in-from-right duration-250">

          <!-- Drawer Header -->
          <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70 shrink-0">
            <div class="flex items-center gap-3 min-w-0 pr-2">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#5138ed] shrink-0">
                <Activity class="w-5 h-5" />
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <h3 class="text-[16px] font-bold text-slate-800 truncate">Activity Log Details</h3>
                  <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-200 text-slate-700 font-bold">
                    #{{ selectedLog.id }}
                  </span>
                </div>
                <p class="text-[12px] text-slate-400">Department Audit Record</p>
              </div>
            </div>
            <button
              @click="closeDrawer"
              class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200/60 transition-colors cursor-pointer"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Drawer Body with 3 Structured Sections -->
          <div class="p-6 space-y-6 overflow-y-auto flex-1 min-h-0">

            <!-- 1. Activity Information -->
            <div class="space-y-3">
              <div class="flex items-center gap-2 pb-1 border-b border-slate-100">
                <Activity class="w-4 h-4 text-[#5138ed]" />
                <h4 class="text-[13px] font-bold text-slate-800 uppercase tracking-wider">Activity Information</h4>
              </div>

              <div class="grid grid-cols-2 gap-3 text-[12px]">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100/70">
                  <span class="text-slate-400 font-semibold block text-[11px]">Action Type</span>
                  <span class="text-slate-800 font-bold mt-0.5 block">{{ selectedLog.action }}</span>
                </div>

                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100/70">
                  <span class="text-slate-400 font-semibold block text-[11px]">Module / Area</span>
                  <span class="text-slate-800 font-bold mt-0.5 block">{{ selectedLog.module }}</span>
                </div>

                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100/70">
                  <span class="text-slate-400 font-semibold block text-[11px]">Execution Status</span>
                  <span
                    v-if="selectedLog.status === 'Success'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 mt-1"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Success
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-rose-50 text-rose-700 border border-rose-200 mt-1"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                    Failed
                  </span>
                </div>

                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100/70">
                  <span class="text-slate-400 font-semibold block text-[11px]">Timestamp</span>
                  <span class="text-slate-800 font-bold mt-0.5 block truncate">{{ selectedLog.time.replace('\n', ' ') }}</span>
                </div>
              </div>

              <!-- Full Description / Change Summary -->
              <div>
                <span class="text-slate-400 font-semibold block text-[11px] mb-1">Full Description & Action Payload</span>
                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 text-[12px] text-slate-700 font-medium whitespace-pre-wrap leading-relaxed break-words font-mono">
                  {{ selectedLog.description }}
                </div>
              </div>
            </div>

            <!-- 2. User Information -->
            <div class="space-y-3">
              <div class="flex items-center gap-2 pb-1 border-b border-slate-100">
                <User class="w-4 h-4 text-[#5138ed]" />
                <h4 class="text-[13px] font-bold text-slate-800 uppercase tracking-wider">User Information</h4>
              </div>

              <div class="flex items-center gap-3.5 p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <div :class="getAvatarColorClass(selectedLog.role)" class="w-11 h-11 rounded-full flex items-center justify-center text-[13px] font-black shrink-0 shadow-2xs">
                  {{ getAvatarInitials(selectedLog.user) }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <h5 class="text-[14px] font-bold text-slate-800 truncate">{{ selectedLog.user }}</h5>
                    <span :class="getRoleBadgeClass(selectedLog.role)" class="px-2 py-0.5 rounded text-[10px] font-bold">
                      {{ selectedLog.role }}
                    </span>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-0.5 truncate">{{ selectedLog.email || 'No email associated' }}</p>
                  <p class="text-[11px] text-slate-400 mt-0.5">
                    Department: <strong class="text-slate-600">{{ selectedLog.department || 'Computer Science' }}</strong>
                  </p>
                </div>
              </div>
            </div>

            <!-- 3. Technical Details (with Copy Buttons) -->
            <div class="space-y-3">
              <div class="flex items-center gap-2 pb-1 border-b border-slate-100">
                <Database class="w-4 h-4 text-[#5138ed]" />
                <h4 class="text-[13px] font-bold text-slate-800 uppercase tracking-wider">Technical Details</h4>
              </div>

              <div class="space-y-2.5">
                <!-- Record ID Row with Copy -->
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 text-[12px]">
                  <div>
                    <span class="text-slate-400 font-semibold block text-[11px]">Database Record ID</span>
                    <span class="font-mono font-bold text-slate-800">#{{ selectedLog.id }}</span>
                  </div>
                  <button
                    @click="copyToClipboard(String(selectedLog.id), 'Record ID')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 transition-colors shadow-2xs cursor-pointer"
                  >
                    <Check v-if="copiedField === 'Record ID'" class="w-3.5 h-3.5 text-emerald-600" />
                    <Copy v-else class="w-3.5 h-3.5 text-slate-500" />
                    <span>{{ copiedField === 'Record ID' ? 'Copied!' : 'Copy ID' }}</span>
                  </button>
                </div>

                <!-- IP Address Row with Copy -->
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 text-[12px]">
                  <div>
                    <span class="text-slate-400 font-semibold block text-[11px]">Client IP Address</span>
                    <span class="font-mono font-bold text-slate-800">{{ selectedLog.ipAddress }}</span>
                  </div>
                  <button
                    @click="copyToClipboard(selectedLog.ipAddress, 'IP Address')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 transition-colors shadow-2xs cursor-pointer"
                  >
                    <Check v-if="copiedField === 'IP Address'" class="w-3.5 h-3.5 text-emerald-600" />
                    <Copy v-else class="w-3.5 h-3.5 text-slate-500" />
                    <span>{{ copiedField === 'IP Address' ? 'Copied!' : 'Copy IP' }}</span>
                  </button>
                </div>

                <!-- ISO Timestamp -->
                <div v-if="selectedLog.raw_time" class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-[12px]">
                  <span class="text-slate-400 font-semibold block text-[11px]">ISO 8601 Timestamp</span>
                  <span class="font-mono text-[11px] font-bold text-slate-700">{{ selectedLog.raw_time }}</span>
                </div>
              </div>
            </div>

          </div>

          <!-- Drawer Footer -->
          <div class="px-6 py-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-end shrink-0">
            <button
              @click="closeDrawer"
              class="px-5 py-2.5 rounded-xl text-[12px] font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 transition-colors shadow-2xs cursor-pointer"
            >
              Close
            </button>
          </div>

        </div>
      </div>
    </Transition>

    <!-- SECTION 8: CLEAR OLD LOGS CONFIRMATION MODAL -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isClearModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        @click.self="isClearModalOpen = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div class="p-6">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mb-4 border border-rose-100">
              <AlertTriangle class="w-6 h-6" />
            </div>
            <h3 class="text-[17px] font-bold text-slate-800 mb-1">Clear Archived Activity Logs</h3>
            <p class="text-[13px] text-slate-500 leading-relaxed mb-4">
              Are you sure you want to clear department activity logs older than <span class="font-bold text-slate-800">{{ clearDays }} days</span>? This permanently purges historical records and cannot be undone.
            </p>

            <div class="mb-2">
              <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                Select Retention Threshold
              </label>
              <select
                v-model="clearDays"
                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 min-h-[42px] text-[13px] font-semibold text-slate-700 bg-white focus:outline-none focus:border-[#5138ed] shadow-2xs cursor-pointer"
              >
                <option :value="7">Older than 7 days</option>
                <option :value="14">Older than 14 days</option>
                <option :value="30">Older than 30 days (Recommended)</option>
                <option :value="60">Older than 60 days</option>
                <option :value="90">Older than 90 days</option>
              </select>
            </div>
          </div>

          <div class="px-6 py-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-end gap-2.5">
            <button
              @click="isClearModalOpen = false"
              :disabled="isClearing"
              class="px-4 py-2.5 rounded-xl text-[12px] font-bold text-slate-600 hover:bg-slate-200/60 transition-colors cursor-pointer"
            >
              Cancel
            </button>
            <button
              @click="confirmClearOldLogs"
              :disabled="isClearing"
              class="px-5 py-2.5 rounded-xl text-[12px] font-bold text-white bg-rose-600 hover:bg-rose-700 transition-colors shadow-sm shadow-rose-200 inline-flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60"
            >
              <RefreshCw v-if="isClearing" class="w-4 h-4 animate-spin" />
              <span>{{ isClearing ? 'Clearing...' : 'Yes, Clear Logs' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>
