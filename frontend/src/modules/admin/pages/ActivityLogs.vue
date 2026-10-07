<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'

// ── Types ───────────────────────────────────────────────────────────────────
interface LogEntry {
  id: number
  time: string
  raw_time: string | null
  user: string
  user_id: number | null
  email: string
  role: string
  raw_role: string
  by_who: string
  action: string
  actionType: string
  module: string
  description: string
  resource: string | null
  ip_address: string
  status: string
  severity: 'INFO' | 'SUCCESS' | 'WARNING' | 'ERROR' | 'SECURITY'
  is_read: boolean
  department: string | null
}

interface FilterOptions {
  modules: string[]
  actions: string[]
  roles: { value: string; label: string }[]
  users: { id: number; name: string; email: string; role: string }[]
}

interface BackendStats {
  total: number
  today: number
  successful: number
  failed: number
  security_events: number
  auth_events: number
  data_changes: number
}

// ── State Management ────────────────────────────────────────────────────────
const viewMode = ref<'table' | 'timeline'>('table')
const isLoading = ref(true)
const isExporting = ref(false)
const exportDropdownOpen = ref(false)
const toastMessage = ref<{ text: string; type: 'success' | 'error' | 'info' } | null>(null)
let toastTimer: any = null

// Server-side logs & pagination
const logs = ref<LogEntry[]>([])
const pagination = ref({
  total: 0,
  per_page: 10,
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
})

// Real stats from backend (Source of Truth)
const stats = ref<BackendStats>({
  total: 0,
  today: 0,
  successful: 0,
  failed: 0,
  security_events: 0,
  auth_events: 0,
  data_changes: 0,
})

// Dynamic options from backend
const filterOptions = ref<FilterOptions>({
  modules: [],
  actions: [],
  roles: [],
  users: [],
})

// ── Search & Filter State ───────────────────────────────────────────────────
const searchInput = ref('')
const debouncedSearch = ref('')
let searchDebounceTimeout: any = null

watch(searchInput, (val) => {
  clearTimeout(searchDebounceTimeout)
  searchDebounceTimeout = setTimeout(() => {
    debouncedSearch.value = val.trim()
    pagination.value.current_page = 1
    fetchLogs()
  }, 250)
})

const selectedModule = ref('all')
const selectedAction = ref('all')
const selectedRole = ref('all')
const selectedUser = ref('all')
const selectedStatus = ref('all')
const selectedQuickDate = ref('all')
const customDateFrom = ref('')
const customDateTo = ref('')
const activeCategory = ref<'all' | 'today' | 'successful' | 'failed' | 'security' | 'data_changes'>('all')

const sortBy = ref('created_at')
const sortOrder = ref<'desc' | 'asc'>('desc')
const perPage = ref(10)

// Log Details Modal
const selectedLog = ref<LogEntry | null>(null)
const isViewModalOpen = ref(false)

// ── Toast Helper ────────────────────────────────────────────────────────────
const showToast = (text: string, type: 'success' | 'error' | 'info' = 'success') => {
  clearTimeout(toastTimer)
  toastMessage.value = { text, type }
  toastTimer = setTimeout(() => {
    toastMessage.value = null
  }, 3500)
}

// ── Fetch Activity Logs (Server-Side) ───────────────────────────────────────
const fetchLogs = async (preservePage = false) => {
  try {
    isLoading.value = true
    const params: Record<string, any> = {
      page: preservePage ? pagination.value.current_page : 1,
      per_page: perPage.value,
      sort_by: sortBy.value,
      sort_order: sortOrder.value,
    }

    if (debouncedSearch.value) params.search = debouncedSearch.value
    if (selectedModule.value !== 'all') params.module = selectedModule.value
    if (selectedAction.value !== 'all') params.action = selectedAction.value
    if (selectedRole.value !== 'all') params.role = selectedRole.value
    if (selectedUser.value !== 'all') params.user_id = selectedUser.value
    if (selectedStatus.value !== 'all') params.status = selectedStatus.value

    if (activeCategory.value !== 'all') {
      params.category = activeCategory.value
    }

    if (selectedQuickDate.value !== 'all' && selectedQuickDate.value !== 'custom') {
      params.quick_date = selectedQuickDate.value
    } else if (selectedQuickDate.value === 'custom') {
      if (customDateFrom.value) params.date_from = customDateFrom.value
      if (customDateTo.value) params.date_to = customDateTo.value
    }

    const response = await apiClient.get('/admin/activity-logs', { params })

    if (response.data?.status === 'success' || response.data?.data) {
      logs.value = response.data.data || []
      if (response.data.pagination) {
        pagination.value = response.data.pagination
      }
      if (response.data.stats) {
        stats.value = response.data.stats
      }
      if (response.data.filter_options) {
        filterOptions.value = response.data.filter_options
      }
    }
  } catch (err: any) {
    console.error('Error fetching activity logs:', err)
    showToast('Failed to load activity logs from server.', 'error')
  } finally {
    isLoading.value = false
  }
}

// ── Filter Controls ─────────────────────────────────────────────────────────
const onFilterChange = () => {
  pagination.value.current_page = 1
  fetchLogs()
}

const selectCategoryCard = (cat: 'all' | 'today' | 'successful' | 'failed' | 'security' | 'data_changes') => {
  if (activeCategory.value === cat) {
    activeCategory.value = 'all'
  } else {
    activeCategory.value = cat
  }
  pagination.value.current_page = 1
  fetchLogs()
}

const clearAllFilters = () => {
  searchInput.value = ''
  debouncedSearch.value = ''
  selectedModule.value = 'all'
  selectedAction.value = 'all'
  selectedRole.value = 'all'
  selectedUser.value = 'all'
  selectedStatus.value = 'all'
  selectedQuickDate.value = 'all'
  customDateFrom.value = ''
  customDateTo.value = ''
  activeCategory.value = 'all'
  pagination.value.current_page = 1
  fetchLogs()
}

const activeFilterCount = computed(() => {
  let count = 0
  if (debouncedSearch.value) count++
  if (selectedModule.value !== 'all') count++
  if (selectedAction.value !== 'all') count++
  if (selectedRole.value !== 'all') count++
  if (selectedUser.value !== 'all') count++
  if (selectedStatus.value !== 'all') count++
  if (selectedQuickDate.value !== 'all') count++
  if (activeCategory.value !== 'all') count++
  return count
})

// ── Pagination Controls ─────────────────────────────────────────────────────
const goToPage = (page: number) => {
  if (page < 1 || page > pagination.value.last_page || page === pagination.value.current_page) return
  pagination.value.current_page = page
  fetchLogs(true)
}

const visiblePages = computed(() => {
  const pages: (number | string)[] = []
  const total = pagination.value.last_page
  const current = pagination.value.current_page
  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    if (current <= 4) {
      for (let i = 1; i <= 5; i++) pages.push(i)
      pages.push('...')
      pages.push(total)
    } else if (current >= total - 3) {
      pages.push(1)
      pages.push('...')
      for (let i = total - 4; i <= total; i++) pages.push(i)
    } else {
      pages.push(1)
      pages.push('...')
      for (let i = current - 1; i <= current + 1; i++) pages.push(i)
      pages.push('...')
      pages.push(total)
    }
  }
  return pages
})

// ── Log Inspection Modal ────────────────────────────────────────────────────
const openLogModal = async (log: LogEntry) => {
  selectedLog.value = log
  isViewModalOpen.value = true

  // If unread, mark it as read on the backend
  if (!log.is_read) {
    try {
      await apiClient.post(`/admin/activity-logs/${log.id}/read`)
      log.is_read = true
      window.dispatchEvent(new Event('log-read'))
    } catch (err) {
      console.error('Failed to mark log as read:', err)
    }
  }
}

const closeLogModal = () => {
  isViewModalOpen.value = false
  setTimeout(() => {
    selectedLog.value = null
  }, 200)
}

const copyLogJson = () => {
  if (!selectedLog.value) return
  const data = JSON.stringify(selectedLog.value, null, 2)
  navigator.clipboard.writeText(data).then(() => {
    showToast('Audit record copied to clipboard.', 'success')
  })
}

// ── Export Functionality ────────────────────────────────────────────────────
const handleExport = async (format: 'csv' | 'pdf') => {
  exportDropdownOpen.value = false
  try {
    isExporting.value = true
    const params: Record<string, any> = {
      format,
    }

    if (debouncedSearch.value) params.search = debouncedSearch.value
    if (selectedModule.value !== 'all') params.module = selectedModule.value
    if (selectedAction.value !== 'all') params.action = selectedAction.value
    if (selectedRole.value !== 'all') params.role = selectedRole.value
    if (selectedStatus.value !== 'all') params.status = selectedStatus.value
    if (selectedQuickDate.value === 'custom') {
      if (customDateFrom.value) params.date_from = customDateFrom.value
      if (customDateTo.value) params.date_to = customDateTo.value
    } else if (selectedQuickDate.value !== 'all') {
      params.quick_date = selectedQuickDate.value
    }

    if (format === 'csv') {
      const response = await apiClient.get('/admin/activity-logs/export', {
        params,
        responseType: 'blob',
      })
      const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `wollo_university_audit_logs_${new Date().toISOString().slice(0, 10)}.csv`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
      showToast('Activity logs exported successfully.', 'success')
    } else {
      // HTML / PDF Printable Report
      const queryStr = new URLSearchParams(params).toString()
      const url = `${apiClient.defaults.baseURL || 'http://localhost:8000/api/v1'}/admin/activity-logs/export?${queryStr}`
      window.open(url, '_blank')
    }
  } catch (err) {
    console.error('Export failed:', err)
    showToast('Failed to export activity logs.', 'error')
  } finally {
    isExporting.value = false
  }
}

// ── Timeline Grouping ───────────────────────────────────────────────────────
const timelineGroups = computed(() => {
  const groups: { date: string; isToday: boolean; isYesterday: boolean; items: LogEntry[] }[] = []
  const todayStr = new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
  const yest = new Date()
  yest.setDate(yest.getDate() - 1)
  const yestStr = yest.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })

  const map = new Map<string, LogEntry[]>()
  logs.value.forEach((log) => {
    const dStr = log.time.split('\n')[0] || 'Unknown Date'
    if (!map.has(dStr)) map.set(dStr, [])
    map.get(dStr)!.push(log)
  })

  map.forEach((items, date) => {
    groups.push({
      date,
      isToday: date === todayStr,
      isYesterday: date === yestStr,
      items,
    })
  })

  return groups
})

// ── Visual Badges & Styling ─────────────────────────────────────────────────
const getRoleBadge = (role: string) => {
  if (role.includes('Super Admin')) return 'bg-indigo-50 text-[#4338ca] border-indigo-200'
  if (role.includes('Department Head')) return 'bg-purple-50 text-purple-700 border-purple-200'
  if (role.includes('Instructor')) return 'bg-blue-50 text-blue-700 border-blue-200'
  if (role.includes('Student')) return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  return 'bg-slate-50 text-slate-600 border-slate-200'
}

const getAvatarColor = (role: string) => {
  if (role.includes('Super Admin')) return 'bg-indigo-100 text-[#4338ca] border-indigo-200'
  if (role.includes('Department Head')) return 'bg-purple-100 text-purple-700 border-purple-200'
  if (role.includes('Instructor')) return 'bg-blue-100 text-blue-700 border-blue-200'
  if (role.includes('Student')) return 'bg-emerald-100 text-emerald-700 border-emerald-200'
  return 'bg-slate-100 text-slate-600 border-slate-200'
}

const getAvatarInitials = (name: string) => {
  if (!name) return 'WU'
  return name
    .split(' ')
    .filter(Boolean)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const getActionIcon = (actionType: string) => {
  switch (actionType) {
    case 'Login':
    case 'Login Failed':
      return {
        icon: 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',
        color: actionType === 'Login' ? 'text-emerald-600' : 'text-rose-600',
        bg: actionType === 'Login' ? 'bg-emerald-50' : 'bg-rose-50',
      }
    case 'Created':
      return {
        icon: 'M12 4v16m8-8H4',
        color: 'text-emerald-600',
        bg: 'bg-emerald-50',
      }
    case 'Updated':
      return {
        icon: 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
        color: 'text-amber-600',
        bg: 'bg-amber-50',
      }
    case 'Deleted':
      return {
        icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
        color: 'text-rose-600',
        bg: 'bg-rose-50',
      }
    case 'Approved Recovery':
      return {
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        color: 'text-indigo-600',
        bg: 'bg-indigo-50',
      }
    case 'Connection Restored':
      return {
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        color: 'text-blue-600',
        bg: 'bg-blue-50',
      }
    case 'Exam Attempt Started':
      return {
        icon: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        color: 'text-purple-600',
        bg: 'bg-purple-50',
      }
    case 'Exported':
      return {
        icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
        color: 'text-teal-600',
        bg: 'bg-teal-50',
      }
    default:
      return {
        icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        color: 'text-slate-600',
        bg: 'bg-slate-100',
      }
  }
}

const getModuleBadge = (mod: string) => {
  switch (mod) {
    case 'Examinations':
    case 'Exam Control':
      return 'bg-purple-50 text-purple-700 border-purple-200'
    case 'Exam Recovery':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'Departments':
      return 'bg-indigo-50 text-indigo-700 border-indigo-200'
    case 'Courses':
      return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'Instructors':
      return 'bg-teal-50 text-teal-700 border-teal-200'
    case 'Students':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'Authentication':
      return 'bg-rose-50 text-rose-700 border-rose-200'
    case 'Semester Submissions':
      return 'bg-cyan-50 text-cyan-700 border-cyan-200'
    default:
      return 'bg-slate-50 text-slate-700 border-slate-200'
  }
}

// Close export dropdown when clicking outside
const handleClickOutside = (e: MouseEvent) => {
  const target = e.target as HTMLElement
  if (!target.closest('.export-dropdown-container')) {
    exportDropdownOpen.value = false
  }
}

onMounted(() => {
  fetchLogs()
  document.addEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="max-w-[1540px] mx-auto space-y-6">

    <!-- Toast Notification -->
    <Teleport to="body">
      <div
        v-if="toastMessage"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-[13px] font-semibold transition-all animate-in fade-in slide-in-from-bottom-5"
        :class="toastMessage.type === 'success' ? 'bg-emerald-900/90 text-white border-emerald-700' : toastMessage.type === 'error' ? 'bg-rose-900/90 text-white border-rose-700' : 'bg-slate-900/90 text-white border-slate-700'"
      >
        <span v-if="toastMessage.type === 'success'">✓</span>
        <span v-else-if="toastMessage.type === 'error'">✕</span>
        <span>{{ toastMessage.text }}</span>
      </div>
    </Teleport>

    <!-- ── Institutional Header & Breadcrumbs ───────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        
        <!-- Left: Titles & Breadcrumb -->
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[12px] font-semibold text-slate-400">
            <span>Administration</span>
            <span>/</span>
            <span>Analytics</span>
            <span>/</span>
            <span class="text-[#4338ca] font-bold">Active Logs</span>
          </div>

          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">System Activity & Audit Log Center</h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-[#4338ca] border border-indigo-200/60">
              <span class="w-1.5 h-1.5 rounded-full bg-[#4338ca] animate-pulse"></span>
              {{ stats.total }} Immutable Logs
            </span>
          </div>
          <p class="text-[13px] text-slate-500 font-medium">
            Monitor real-time institutional operations, authentication events, exam recovery actions, and data changes across Wollo University.
          </p>
        </div>

        <!-- Right: Actions Toolbar -->
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
          
          <!-- View Mode Toggle -->
          <div class="inline-flex p-1 rounded-xl bg-slate-100 border border-slate-200 text-[12px] font-bold">
            <button
              @click="viewMode = 'table'"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all"
              :class="viewMode === 'table' ? 'bg-white text-[#4338ca] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              Table View
            </button>
            <button
              @click="viewMode = 'timeline'"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all"
              :class="viewMode === 'timeline' ? 'bg-white text-[#4338ca] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Timeline View
            </button>
          </div>

          <!-- Refresh Button -->
          <button
            @click="fetchLogs(true)"
            :disabled="isLoading"
            class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-[13px] font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-xs disabled:opacity-50"
            title="Refresh logs from server"
          >
            <svg
              class="w-4 h-4 text-slate-500"
              :class="{ 'animate-spin': isLoading }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span>Refresh</span>
          </button>

          <!-- Export Dropdown -->
          <div class="relative export-dropdown-container">
            <button
              @click="exportDropdownOpen = !exportDropdownOpen"
              :disabled="isExporting"
              class="flex items-center gap-2 px-4 py-2 rounded-xl text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200 disabled:opacity-50"
            >
              <svg v-if="!isExporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>Export Audit</span>
              <svg class="w-3.5 h-3.5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="exportDropdownOpen"
              class="absolute right-0 mt-2 w-56 rounded-2xl bg-white border border-slate-200 shadow-xl py-2 z-50 text-[13px] font-medium animate-in fade-in zoom-in-95"
            >
              <button
                @click="handleExport('csv')"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-left text-slate-700 hover:bg-slate-50 hover:text-[#4338ca] transition-colors"
              >
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-[11px]">CSV</div>
                <div>
                  <p class="font-bold text-slate-900">Export as CSV</p>
                  <p class="text-[11px] text-slate-400">Standard spreadsheet data</p>
                </div>
              </button>

              <button
                @click="handleExport('pdf')"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-left text-slate-700 hover:bg-slate-50 hover:text-[#4338ca] transition-colors"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center font-bold text-[11px]">PDF</div>
                <div>
                  <p class="font-bold text-slate-900">Official Audit Report</p>
                  <p class="text-[11px] text-slate-400">Institutional printable report</p>
                </div>
              </button>
            </div>
          </div>

        </div>

      </div>
    </div>

    <!-- ── Security Overview & Interactive KPI Summary Cards (100% Real Data) ── -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
      
      <!-- Card 1: Total Activities -->
      <button
        @click="selectCategoryCard('all')"
        class="text-left p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer relative overflow-hidden"
        :class="activeCategory === 'all' ? 'bg-indigo-50/60 border-[#4338ca] ring-2 ring-[#4338ca]/20 shadow-sm' : 'bg-white border-slate-200/80 hover:border-slate-300 shadow-xs'"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Total Activities</span>
          <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#4338ca] flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{{ stats.total }}</div>
        <p class="text-[11px] text-slate-400 font-medium mt-1">Full institutional history</p>
      </button>

      <!-- Card 2: Today's Activities -->
      <button
        @click="selectCategoryCard('today')"
        class="text-left p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer relative overflow-hidden"
        :class="activeCategory === 'today' ? 'bg-blue-50/60 border-blue-600 ring-2 ring-blue-600/20 shadow-sm' : 'bg-white border-slate-200/80 hover:border-slate-300 shadow-xs'"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Today's Logs</span>
          <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{{ stats.today }}</div>
        <p class="text-[11px] text-slate-400 font-medium mt-1">Past 24 hours</p>
      </button>

      <!-- Card 3: Successful Actions -->
      <button
        @click="selectCategoryCard('successful')"
        class="text-left p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer relative overflow-hidden"
        :class="activeCategory === 'successful' ? 'bg-emerald-50/60 border-emerald-600 ring-2 ring-emerald-600/20 shadow-sm' : 'bg-white border-slate-200/80 hover:border-slate-300 shadow-xs'"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Successful</span>
          <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-emerald-600 tracking-tight">{{ stats.successful }}</div>
        <p class="text-[11px] text-slate-400 font-medium mt-1">Verified operations</p>
      </button>

      <!-- Card 4: Security & Recovery -->
      <button
        @click="selectCategoryCard('security')"
        class="text-left p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer relative overflow-hidden"
        :class="activeCategory === 'security' ? 'bg-purple-50/60 border-purple-600 ring-2 ring-purple-600/20 shadow-sm' : 'bg-white border-slate-200/80 hover:border-slate-300 shadow-xs'"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Security Events</span>
          <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-purple-700 tracking-tight">{{ stats.security_events }}</div>
        <p class="text-[11px] text-slate-400 font-medium mt-1">Auth & recovery audit</p>
      </button>

      <!-- Card 5: Failed Actions -->
      <button
        @click="selectCategoryCard('failed')"
        class="col-span-2 sm:col-span-1 text-left p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer relative overflow-hidden"
        :class="activeCategory === 'failed' ? 'bg-rose-50/60 border-rose-600 ring-2 ring-rose-600/20 shadow-sm' : 'bg-white border-slate-200/80 hover:border-slate-300 shadow-xs'"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Failed Actions</span>
          <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-rose-600 tracking-tight">{{ stats.failed }}</div>
        <p class="text-[11px] text-slate-400 font-medium mt-1">Requiring investigation</p>
      </button>

    </div>

    <!-- ── Advanced Search & Filter Toolbar ─────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-4 sm:p-5 space-y-4">
      
      <!-- Top Row: Search + Primary Filters -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        
        <!-- Search Input (Span 2) -->
        <div class="lg:col-span-2 relative">
          <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input
            v-model="searchInput"
            type="text"
            placeholder="Search by user, email, action, module, or IP..."
            class="w-full pl-10 pr-9 py-2.5 rounded-xl border border-slate-200 text-[13px] text-slate-800 placeholder-slate-400 bg-slate-50/50 focus:bg-white focus:outline-none focus:border-[#4338ca] focus:ring-2 focus:ring-[#4338ca]/10 transition-all font-medium"
          />
          <button
            v-if="searchInput"
            @click="searchInput = ''"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        <!-- Role Filter -->
        <div>
          <select
            v-model="selectedRole"
            @change="onFilterChange"
            class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-[13px] text-slate-700 bg-white font-medium focus:outline-none focus:border-[#4338ca] transition-colors"
          >
            <option value="all">All Roles</option>
            <option v-for="r in filterOptions.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>

        <!-- Module Filter -->
        <div>
          <select
            v-model="selectedModule"
            @change="onFilterChange"
            class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-[13px] text-slate-700 bg-white font-medium focus:outline-none focus:border-[#4338ca] transition-colors"
          >
            <option value="all">All Modules</option>
            <option v-for="m in filterOptions.modules" :key="m" :value="m">{{ m }}</option>
          </select>
        </div>

        <!-- Action Type Filter -->
        <div>
          <select
            v-model="selectedAction"
            @change="onFilterChange"
            class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-[13px] text-slate-700 bg-white font-medium focus:outline-none focus:border-[#4338ca] transition-colors"
          >
            <option value="all">All Actions</option>
            <option v-for="a in filterOptions.actions" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>

        <!-- Date Range Presets -->
        <div>
          <select
            v-model="selectedQuickDate"
            @change="onFilterChange"
            class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-[13px] text-slate-700 bg-white font-medium focus:outline-none focus:border-[#4338ca] transition-colors"
          >
            <option value="all">All Dates</option>
            <option value="today">Today</option>
            <option value="yesterday">Yesterday</option>
            <option value="7days">Last 7 Days</option>
            <option value="30days">Last 30 Days</option>
            <option value="custom">Custom Range...</option>
          </select>
        </div>

      </div>

      <!-- Secondary Row: User Filter, Status, Sorting, Custom Dates -->
      <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100">
        
        <!-- Left Sub-filters -->
        <div class="flex flex-wrap items-center gap-2.5">
          
          <!-- Specific User Filter -->
          <div class="min-w-[160px]">
            <select
              v-model="selectedUser"
              @change="onFilterChange"
              class="w-full px-3 py-2 rounded-lg border border-slate-200 text-[12px] text-slate-700 bg-white font-medium focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">Filter by Actor (All)</option>
              <option v-for="u in filterOptions.users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role }})</option>
            </select>
          </div>

          <!-- Status Filter -->
          <div class="min-w-[130px]">
            <select
              v-model="selectedStatus"
              @change="onFilterChange"
              class="w-full px-3 py-2 rounded-lg border border-slate-200 text-[12px] text-slate-700 bg-white font-medium focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Statuses</option>
              <option value="Success">Success</option>
              <option value="Failed">Failed</option>
            </select>
          </div>

          <!-- Custom Date Range Inputs (when custom selected) -->
          <div v-if="selectedQuickDate === 'custom'" class="flex items-center gap-2 bg-slate-50 p-1.5 rounded-lg border border-slate-200 text-[12px]">
            <input
              v-model="customDateFrom"
              type="date"
              class="px-2 py-1 rounded bg-white border border-slate-200 text-slate-700 font-medium"
              placeholder="From"
              @change="onFilterChange"
            />
            <span class="text-slate-400">to</span>
            <input
              v-model="customDateTo"
              type="date"
              class="px-2 py-1 rounded bg-white border border-slate-200 text-slate-700 font-medium"
              placeholder="To"
              @change="onFilterChange"
            />
          </div>

        </div>

        <!-- Right: Sort & Page Size -->
        <div class="flex items-center gap-3 ml-auto">
          
          <!-- Sort Field -->
          <div class="flex items-center gap-1.5 text-[12px] text-slate-500 font-medium">
            <span>Sort:</span>
            <select
              v-model="sortBy"
              @change="onFilterChange"
              class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 font-semibold focus:outline-none focus:border-[#4338ca]"
            >
              <option value="created_at">Timestamp</option>
              <option value="user">Actor Name</option>
              <option value="module">Module</option>
              <option value="type">Action</option>
              <option value="log_status">Status</option>
            </select>

            <button
              @click="sortOrder = sortOrder === 'desc' ? 'asc' : 'desc'; onFilterChange()"
              class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:text-[#4338ca] transition-colors"
              :title="sortOrder === 'desc' ? 'Sort Ascending' : 'Sort Descending'"
            >
              <svg v-if="sortOrder === 'desc'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/></svg>
            </button>
          </div>

          <!-- Page Size -->
          <div class="flex items-center gap-1.5 text-[12px] text-slate-500 font-medium">
            <span>Show:</span>
            <select
              v-model="perPage"
              @change="onFilterChange"
              class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 font-semibold focus:outline-none focus:border-[#4338ca]"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>

        </div>

      </div>

      <!-- Active Filter Chips -->
      <div v-if="activeFilterCount > 0" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Active Filters:</span>
        
        <span v-if="debouncedSearch" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 text-slate-700">
          Query: "{{ debouncedSearch }}"
          <button @click="searchInput = ''; debouncedSearch = ''; onFilterChange()" class="hover:text-rose-500">✕</button>
        </span>

        <span v-if="selectedRole !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-indigo-50 text-[#4338ca]">
          Role: {{ selectedRole }}
          <button @click="selectedRole = 'all'; onFilterChange()" class="hover:text-rose-500">✕</button>
        </span>

        <span v-if="selectedModule !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-purple-50 text-purple-700">
          Module: {{ selectedModule }}
          <button @click="selectedModule = 'all'; onFilterChange()" class="hover:text-rose-500">✕</button>
        </span>

        <span v-if="selectedAction !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-50 text-emerald-700">
          Action: {{ selectedAction }}
          <button @click="selectedAction = 'all'; onFilterChange()" class="hover:text-rose-500">✕</button>
        </span>

        <span v-if="selectedStatus !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-50 text-amber-700">
          Status: {{ selectedStatus }}
          <button @click="selectedStatus = 'all'; onFilterChange()" class="hover:text-rose-500">✕</button>
        </span>

        <span v-if="selectedQuickDate !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-blue-50 text-blue-700">
          Date: {{ selectedQuickDate }}
          <button @click="selectedQuickDate = 'all'; onFilterChange()" class="hover:text-rose-500">✕</button>
        </span>

        <span v-if="activeCategory !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-50 text-rose-700">
          KPI Category: {{ activeCategory }}
          <button @click="activeCategory = 'all'; onFilterChange()" class="hover:text-rose-500">✕</button>
        </span>

        <button
          @click="clearAllFilters"
          class="text-[11px] font-bold text-rose-600 hover:text-rose-800 underline underline-offset-2 ml-2"
        >
          Clear All
        </button>
      </div>

    </div>

    <!-- ── Main Content Area (Table View vs Timeline View) ─────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
      
      <!-- Loading Skeleton State -->
      <div v-if="isLoading" class="p-8 space-y-4">
        <div v-for="i in 5" :key="i" class="h-14 bg-slate-50 rounded-xl animate-pulse flex items-center justify-between px-4">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-200"></div>
            <div class="space-y-1.5">
              <div class="w-32 h-3.5 bg-slate-200 rounded"></div>
              <div class="w-20 h-2.5 bg-slate-100 rounded"></div>
            </div>
          </div>
          <div class="w-24 h-5 bg-slate-200 rounded-md"></div>
          <div class="w-48 h-3.5 bg-slate-100 rounded"></div>
          <div class="w-16 h-5 bg-slate-200 rounded-md"></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="logs.length === 0" class="py-16 text-center px-4">
        <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-[#4338ca] mx-auto flex items-center justify-center mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 class="text-base font-bold text-slate-800">No activity logs recorded</h3>
        <p class="text-[13px] text-slate-500 max-w-md mx-auto mt-1">
          No institutional operations matched your selected filter parameters or search queries.
        </p>
        <button
          @click="clearAllFilters"
          class="mt-4 px-4 py-2 rounded-xl text-[12px] font-bold text-[#4338ca] bg-indigo-50 hover:bg-indigo-100 transition-colors"
        >
          Reset All Filters
        </button>
      </div>

      <!-- TABLE VIEW -->
      <div v-else-if="viewMode === 'table'" class="overflow-x-auto min-w-0">
        <table class="w-full text-left border-collapse whitespace-nowrap">
          <thead>
            <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
              <th class="px-5 py-3.5">Time</th>
              <th class="px-4 py-3.5">Actor / User</th>
              <th class="px-4 py-3.5">By Who (Role)</th>
              <th class="px-4 py-3.5">Action</th>
              <th class="px-4 py-3.5">Module</th>
              <th class="px-4 py-3.5">Description</th>
              <th class="px-4 py-3.5">IP Address</th>
              <th class="px-4 py-3.5">Status</th>
              <th class="px-4 py-3.5 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="log in logs"
              :key="log.id"
              class="transition-colors hover:bg-slate-50/80 cursor-pointer"
              :class="!log.is_read ? 'bg-indigo-50/15' : ''"
              @click="openLogModal(log)"
            >
              
              <!-- Time -->
              <td class="px-5 py-3.5 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <span
                    v-if="!log.is_read"
                    class="w-1.5 h-1.5 rounded-full bg-[#4338ca] shrink-0"
                    title="Unread log"
                  ></span>
                  <div>
                    <span class="text-[11px] font-bold text-slate-500 block">{{ log.time.split('\n')[0] }}</span>
                    <span class="text-[11px] font-black text-slate-800">{{ log.time.split('\n')[1] }}</span>
                  </div>
                </div>
              </td>

              <!-- Actor / User -->
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-3">
                  <div
                    :class="getAvatarColor(log.by_who || log.role)"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 border"
                  >
                    {{ getAvatarInitials(log.user) }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-[12px] font-bold text-slate-900 truncate">{{ log.user }}</p>
                    <p class="text-[11px] text-slate-400 truncate">{{ log.email || 'N/A' }}</p>
                  </div>
                </div>
              </td>

              <!-- By Who (Role) -->
              <td class="px-4 py-3.5">
                <span
                  :class="getRoleBadge(log.by_who || log.role)"
                  class="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold border whitespace-nowrap"
                >
                  {{ log.by_who || log.role }}
                </span>
              </td>

              <!-- Action -->
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-2">
                  <div
                    :class="[getActionIcon(log.actionType).color, getActionIcon(log.actionType).bg]"
                    class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(log.actionType).icon"/>
                    </svg>
                  </div>
                  <span class="text-[12px] font-bold text-slate-900 whitespace-nowrap">{{ log.action }}</span>
                </div>
              </td>

              <!-- Module -->
              <td class="px-4 py-3.5">
                <span
                  :class="getModuleBadge(log.module)"
                  class="inline-block px-2 py-0.5 rounded text-[11px] font-bold border"
                >
                  {{ log.module }}
                </span>
              </td>

              <!-- Description -->
              <td class="px-4 py-3.5 text-[12px] text-slate-600 max-w-[280px] truncate" :title="log.description">
                <span v-if="log.resource" class="font-bold text-slate-900 mr-1">"{{ log.resource }}"</span>
                {{ log.description }}
              </td>

              <!-- IP Address -->
              <td class="px-4 py-3.5">
                <span class="font-mono text-[11px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                  {{ log.ip_address }}
                </span>
              </td>

              <!-- Status -->
              <td class="px-4 py-3.5">
                <span
                  v-if="log.status === 'Success'"
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200"
                >
                  <span>✓</span> {{ log.status }}
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold rounded-md bg-rose-50 text-rose-700 border border-rose-200"
                >
                  <span>✕</span> {{ log.status }}
                </span>
              </td>

              <!-- Actions (Details Eye) -->
              <td class="px-4 py-3.5 text-center" @click.stop="openLogModal(log)">
                <button
                  class="w-7 h-7 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center hover:bg-[#4338ca] hover:text-white transition-colors mx-auto shadow-xs"
                  title="View full audit details"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
              </td>

            </tr>
          </tbody>
        </table>
      </div>

      <!-- TIMELINE VIEW -->
      <div v-else class="p-6">
        <div class="space-y-8 max-w-4xl mx-auto">
          <div v-for="group in timelineGroups" :key="group.date" class="relative">
            
            <!-- Date Section Header -->
            <div class="flex items-center gap-3 mb-4">
              <span class="px-3 py-1 rounded-xl bg-slate-100 text-slate-800 text-[12px] font-black border border-slate-200">
                <span v-if="group.isToday" class="text-[#4338ca] mr-1">● Today &bull;</span>
                <span v-else-if="group.isYesterday" class="text-blue-600 mr-1">● Yesterday &bull;</span>
                {{ group.date }}
              </span>
              <div class="flex-1 h-px bg-slate-200"></div>
              <span class="text-[11px] font-bold text-slate-400">{{ group.items.length }} events</span>
            </div>

            <!-- Vertical Timeline Track -->
            <div class="border-l-2 border-slate-200 ml-4 pl-6 space-y-4">
              <div
                v-for="item in group.items"
                :key="'tl-' + item.id"
                class="relative p-4 rounded-xl border border-slate-200/80 bg-white hover:border-indigo-300 hover:shadow-xs transition-all cursor-pointer"
                @click="openLogModal(item)"
              >
                <!-- Timeline Dot Indicator -->
                <div
                  :class="[getActionIcon(item.actionType).bg, getActionIcon(item.actionType).color]"
                  class="absolute -left-[35px] top-4 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center text-[10px] shadow-xs"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(item.actionType).icon"/>
                  </svg>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span :class="getRoleBadge(item.by_who || item.role)" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                      {{ item.by_who || item.role }}
                    </span>
                    <span class="font-bold text-[13px] text-slate-900">{{ item.user }}</span>
                    <span class="text-slate-300">&bull;</span>
                    <span :class="getModuleBadge(item.module)" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                      {{ item.module }}
                    </span>
                  </div>

                  <div class="flex items-center gap-2 text-[11px] text-slate-400 font-medium">
                    <span class="font-mono">{{ item.ip_address }}</span>
                    <span>&bull;</span>
                    <span class="font-bold text-slate-700">{{ item.time.split('\n')[1] }}</span>
                  </div>
                </div>

                <p class="text-[13px] text-slate-700 leading-relaxed font-medium">
                  {{ item.description }}
                </p>

                <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100 text-[11px]">
                  <span
                    v-if="item.status === 'Success'"
                    class="font-bold text-emerald-600 inline-flex items-center gap-1"
                  >
                    ✓ Success
                  </span>
                  <span v-else class="font-bold text-rose-600 inline-flex items-center gap-1">
                    ✕ Failed
                  </span>

                  <button
                    @click.stop="openLogModal(item)"
                    class="text-[#4338ca] font-bold hover:underline"
                  >
                    View Details &rarr;
                  </button>
                </div>

              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ── Pagination Bar ─────────────────────────────────────────────────── -->
      <div class="px-5 py-4 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
        <span class="text-[12px] font-bold text-slate-500">
          Showing <span class="text-slate-900 font-black">{{ pagination.total === 0 ? 0 : pagination.from }}</span> to
          <span class="text-slate-900 font-black">{{ pagination.to }}</span> of
          <span class="text-slate-900 font-black">{{ stats.total }}</span> registered activities
        </span>

        <div class="flex items-center gap-1.5">
          <!-- Previous Button -->
          <button
            @click="goToPage(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-[12px] font-bold transition-colors"
          >
            Previous
          </button>

          <!-- Numbered Page Buttons -->
          <template v-for="(p, idx) in visiblePages" :key="idx">
            <span v-if="p === '...'" class="w-8 text-center text-slate-400 font-bold select-none text-[12px]">...</span>
            <button
              v-else
              @click="goToPage(Number(p))"
              :class="pagination.current_page === p ? 'bg-[#4338ca] text-white border-[#4338ca]' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
              class="w-8 h-8 rounded-lg border text-[12px] font-bold transition-colors"
            >
              {{ p }}
            </button>
          </template>

          <!-- Next Button -->
          <button
            @click="goToPage(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-[12px] font-bold transition-colors"
          >
            Next
          </button>
        </div>
      </div>

    </div>

    <!-- ── Audit Log Details Modal ─────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="isViewModalOpen && selectedLog" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" @click="closeLogModal"></div>

        <!-- Modal Card -->
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200 border border-slate-200">
          
          <!-- Modal Header -->
          <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100 bg-slate-50/70 shrink-0">
            <div class="flex items-center gap-3.5">
              <div
                :class="[getActionIcon(selectedLog.actionType).bg, getActionIcon(selectedLog.actionType).color]"
                class="w-11 h-11 rounded-2xl flex items-center justify-center font-bold text-lg shadow-xs"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(selectedLog.actionType).icon"/>
                </svg>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-base font-black text-slate-900">{{ selectedLog.action }}</h3>
                  <span :class="getModuleBadge(selectedLog.module)" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                    {{ selectedLog.module }}
                  </span>
                </div>
                <p class="text-[12px] font-semibold text-slate-400">
                  Log ID #{{ selectedLog.id }} &bull; {{ selectedLog.time.replace('\n', ' at ') }}
                </p>
              </div>
            </div>

            <button
              @click="closeLogModal"
              class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors"
            >
              ✕
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 space-y-5 overflow-y-auto">
            
            <!-- Actor Card -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="flex items-center gap-3.5">
                <div
                  :class="getAvatarColor(selectedLog.by_who || selectedLog.role)"
                  class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-black border"
                >
                  {{ getAvatarInitials(selectedLog.user) }}
                </div>
                <div>
                  <h4 class="text-[14px] font-black text-slate-900">{{ selectedLog.user }}</h4>
                  <p class="text-[12px] text-slate-500 font-medium">{{ selectedLog.email || 'Email unavailable' }}</p>
                  <p v-if="selectedLog.department" class="text-[11px] text-slate-400 font-semibold mt-0.5">
                    Department: {{ selectedLog.department }}
                  </p>
                </div>
              </div>

              <div class="self-start sm:self-center">
                <span :class="getRoleBadge(selectedLog.by_who || selectedLog.role)" class="px-3 py-1 rounded-lg text-[11px] font-bold border">
                  {{ selectedLog.by_who || selectedLog.role }}
                </span>
              </div>
            </div>

            <!-- Key Metadata Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              
              <!-- Timestamp -->
              <div class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/50 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Timestamp</span>
                <span class="text-[12px] font-bold text-slate-800 block">{{ selectedLog.time.replace('\n', ' ') }}</span>
              </div>

              <!-- IP Address -->
              <div class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/50 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">IP Address</span>
                <span class="font-mono text-[12px] font-bold text-slate-800 block">{{ selectedLog.ip_address }}</span>
              </div>

              <!-- Status -->
              <div class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/50 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Operation Status</span>
                <span
                  v-if="selectedLog.status === 'Success'"
                  class="text-[12px] font-bold text-emerald-600 block"
                >
                  ✓ Success
                </span>
                <span v-else class="text-[12px] font-bold text-rose-600 block">
                  ✕ Failed
                </span>
              </div>

            </div>

            <!-- Affected Resource (if identified) -->
            <div v-if="selectedLog.resource" class="p-3.5 rounded-xl border border-indigo-100 bg-indigo-50/40">
              <span class="text-[10px] font-bold text-[#4338ca] uppercase tracking-wider block mb-1">Affected Academic Resource</span>
              <p class="text-[13px] font-bold text-slate-900">
                {{ selectedLog.resource }}
              </p>
            </div>

            <!-- Event Description -->
            <div class="p-4 rounded-xl border border-slate-200/80 space-y-1.5">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Event Description</span>
              <p class="text-[13px] font-medium text-slate-800 leading-relaxed">
                {{ selectedLog.description }}
              </p>
            </div>

            <!-- Immutability Security Banner -->
            <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/60 flex items-start gap-3">
              <div class="w-6 h-6 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </div>
              <div class="text-[11px] text-slate-500 leading-normal">
                <span class="font-bold text-slate-700">Audit Record Immutability:</span>
                This event is a cryptographically secured and non-repudiable audit entry. In accordance with university policy, individual audit records cannot be altered or removed from the management portal.
              </div>
            </div>

          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100 bg-slate-50/70 shrink-0">
            <button
              @click="copyLogJson"
              class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-[12px] font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-xs"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              Copy Record JSON
            </button>

            <button
              @click="closeLogModal"
              class="px-5 py-2 rounded-xl text-[13px] font-bold text-white bg-slate-900 hover:bg-slate-800 transition-colors"
            >
              Close
            </button>
          </div>

        </div>
      </div>
    </Teleport>

  </div>
</template>
