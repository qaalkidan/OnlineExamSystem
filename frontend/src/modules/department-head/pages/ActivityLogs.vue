<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '../../../core/api/apiClient'

interface LogEntry {
  id: number
  time: string
  raw_time?: string
  user: string
  email: string
  role: string
  action: string
  actionType: string
  module: string
  description: string
  ipAddress: string
  status: 'Success' | 'Failed'
  department?: string | null
  is_read?: boolean
}

interface ActivitySummary {
  all: number
  successful: number
  failed: number
  logins: number
  dataChanges: number
  systemEvents: number
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

// State
const isLoading = ref(true)
const isExporting = ref(false)
const filterType = ref('All Activities')
const searchQuery = ref('')
const startDate = ref('')
const endDate = ref('')
const showDateFilterModal = ref(false)

const logs = ref<LogEntry[]>([])
const activitySummary = ref<ActivitySummary>({
  all: 0,
  successful: 0,
  failed: 0,
  logins: 0,
  dataChanges: 0,
  systemEvents: 0,
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

// Modals
const selectedLog = ref<LogEntry | null>(null)
const isViewModalOpen = ref(false)
const isClearModalOpen = ref(false)
const isClearing = ref(false)
const clearDays = ref(30)

// Toast
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' }>({
  show: false,
  message: '',
  type: 'success',
})

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

const filterTypes = [
  { label: 'All Activities' },
  { label: 'Successful' },
  { label: 'Failed' },
  { label: 'Logins' },
  { label: 'Data Changes' },
  { label: 'System Events' },
]

// Computed Date Display label
const dateRangeLabel = computed(() => {
  if (startDate.value && endDate.value) {
    const s = new Date(startDate.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    const e = new Date(endDate.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    return `${s} - ${e}`
  } else if (startDate.value) {
    const s = new Date(startDate.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    return `From ${s}`
  } else if (endDate.value) {
    const e = new Date(endDate.value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    return `Until ${e}`
  }
  return 'May 18, 2025 - May 23, 2025' // default matching design
})

// Fetch logs from backend
const fetchLogs = async (page = 1) => {
  try {
    isLoading.value = true
    const params: Record<string, any> = {
      page,
      per_page: 10,
    }

    if (filterType.value && filterType.value !== 'All Activities') {
      params.filter = filterType.value
    }
    if (startDate.value) {
      params.date_from = startDate.value
    }
    if (endDate.value) {
      params.date_to = endDate.value
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
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
      if (res.data.topActiveUsers && Array.isArray(res.data.topActiveUsers)) {
        topActiveUsers.value = res.data.topActiveUsers
      }
    }
  } catch (error) {
    console.error('Failed to fetch activity logs:', error)
    showToast('Failed to load activity logs from server.', 'error')
  } finally {
    isLoading.value = false
  }
}

// Handlers
const onFilterTypeChange = () => {
  fetchLogs(1)
}

const applyFilters = () => {
  fetchLogs(1)
  showDateFilterModal.value = false
}

const resetDateFilters = () => {
  startDate.value = ''
  endDate.value = ''
  fetchLogs(1)
  showDateFilterModal.value = false
}

const goToPage = (page: number) => {
  if (page < 1 || page > pagination.value.last_page || page === pagination.value.current_page) return
  fetchLogs(page)
}

const openViewModal = (log: LogEntry) => {
  selectedLog.value = log
  isViewModalOpen.value = true
}

// Quick Actions
const filterByCategory = (category: string) => {
  filterType.value = category
  searchQuery.value = ''
  fetchLogs(1)
}

const filterByUser = (userName: string) => {
  searchQuery.value = userName
  fetchLogs(1)
}

const handleExportLogs = async () => {
  try {
    isExporting.value = true
    showToast('Generating Activity Logs CSV export...', 'success')

    const params: Record<string, any> = {}
    if (filterType.value && filterType.value !== 'All Activities') {
      params.filter = filterType.value
    }
    if (startDate.value) params.date_from = startDate.value
    if (endDate.value) params.date_to = endDate.value

    const res = await apiClient.get('/dept-head/activity-logs/export', { params })

    const { file, filename } = res.data || {}
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

    showToast('Activity logs exported successfully!', 'success')
  } catch (error) {
    console.error('Export failed:', error)
    showToast('Failed to export activity logs.', 'error')
  } finally {
    isExporting.value = false
  }
}

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

// Styling helpers
const getRoleBadge = (role: string) => {
  const r = (role || '').toLowerCase()
  if (r.includes('admin')) return 'bg-indigo-50 text-[#5138ed]'
  if (r.includes('head') || r.includes('dept')) return 'bg-purple-50 text-[#5138ed]'
  if (r.includes('instructor')) return 'bg-blue-50 text-blue-500'
  if (r.includes('student')) return 'bg-emerald-50 text-emerald-500'
  return 'bg-slate-50 text-slate-500'
}

const getActionIcon = (type: string) => {
  const t = (type || '').toLowerCase()
  if (t === 'login') return { icon: 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1', color: 'text-emerald-500' }
  if (t.includes('create') || t.includes('add')) return { icon: 'M12 4v16m8-8H4', color: 'text-emerald-500' }
  if (t.includes('update') || t.includes('edit')) return { icon: 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z', color: 'text-amber-500' }
  if (t.includes('delete') || t.includes('remove')) return { icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', color: 'text-rose-500' }
  if (t.includes('submit')) return { icon: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8', color: 'text-[#5138ed]' }
  if (t.includes('export')) return { icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4', color: 'text-[#5138ed]' }
  if (t.includes('backup')) return { icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4', color: 'text-[#5138ed]' }
  if (t.includes('system') || t.includes('event')) return { icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', color: 'text-[#5138ed]' }
  if (t.includes('fail')) return { icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', color: 'text-rose-500' }
  return { icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-slate-400' }
}

const getAvatarColor = (role: string) => {
  const r = (role || '').toLowerCase()
  if (r.includes('admin')) return 'bg-indigo-100 text-[#5138ed]'
  if (r.includes('head') || r.includes('dept')) return 'bg-purple-100 text-[#5138ed]'
  if (r.includes('instructor')) return 'bg-blue-100 text-blue-600'
  if (r.includes('student')) return 'bg-emerald-100 text-emerald-600'
  return 'bg-slate-100 text-slate-500'
}

const getAvatarInitials = (name: string) => {
  if (!name) return 'SY'
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
}

// Pages array for pagination numbers
const displayedPages = computed(() => {
  const total = pagination.value.last_page
  const current = pagination.value.current_page
  if (total <= 5) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }
  const pages: (number | string)[] = []
  if (current <= 3) {
    pages.push(1, 2, 3, '...', total)
  } else if (current >= total - 2) {
    pages.push(1, '...', total - 2, total - 1, total)
  } else {
    pages.push(1, '...', current - 1, current, current + 1, '...', total)
  }
  return pages
})

onMounted(() => {
  fetchLogs(1)
})
</script>

<template>
  <div class="max-w-[1500px] mx-auto pb-10 min-w-0 max-w-full space-y-6">

    <!-- Toast Notification -->
    <div
      v-if="toast.show"
      class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-xl border text-[13px] font-bold transition-all transform animate-bounce-short max-w-[90vw]"
      :class="toast.type === 'success' ? 'bg-slate-900 text-white border-slate-700' : 'bg-rose-600 text-white border-rose-500'"
    >
      <svg v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
      <svg v-else class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
      <span class="break-words">{{ toast.message }}</span>
    </div>

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center shrink-0 shadow-sm border border-indigo-100/50">
          <svg class="w-6 h-6 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <h1 class="text-[20px] sm:text-[22px] font-bold text-slate-800">Active Logs</h1>
          <p class="text-[12px] sm:text-[13px] text-slate-500 truncate">Monitor all recent activities and system events in real-time.</p>
          <div class="flex items-center gap-1.5 mt-1 text-[11px] sm:text-[12px] text-slate-400">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Dashboard</span>
            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-800 font-bold truncate">Active Logs</span>
          </div>
        </div>
      </div>

      <!-- Filters Right -->
      <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 w-full lg:w-auto">
        <!-- Search bar -->
        <div class="relative flex-1 sm:flex-none min-w-[200px]">
          <input
            v-model="searchQuery"
            @keyup.enter="fetchLogs(1)"
            type="text"
            placeholder="Search logs..."
            class="w-full pl-9 pr-4 py-2.5 min-h-[44px] text-[13px] border border-slate-200 rounded-xl bg-white focus:outline-none focus:border-[#5138ed] shadow-sm font-medium text-slate-700 placeholder-slate-400"
          />
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Activity Dropdown -->
        <div class="relative flex-1 sm:flex-none min-w-[150px]">
          <select
            v-model="filterType"
            @change="onFilterTypeChange"
            class="w-full appearance-none border border-slate-200 rounded-xl px-4 py-2.5 pr-10 min-h-[44px] text-[13px] text-slate-700 font-bold bg-white focus:outline-none focus:border-[#5138ed] shadow-sm cursor-pointer"
          >
            <option v-for="f in filterTypes" :key="f.label" :value="f.label">⚙️ {{ f.label }}</option>
          </select>
          <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>

        <!-- Date Range Filter Trigger -->
        <button
          @click="showDateFilterModal = !showDateFilterModal"
          class="inline-flex items-center justify-center gap-2 border border-slate-200 rounded-xl px-4 py-2.5 min-h-[44px] text-[13px] text-slate-700 font-bold bg-white shadow-sm hover:border-[#5138ed] transition-colors flex-1 sm:flex-none"
        >
          <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span class="truncate">{{ dateRangeLabel }}</span>
        </button>

        <!-- Filter Action Button -->
        <button
          @click="applyFilters"
          class="inline-flex items-center justify-center gap-2 px-5 sm:px-6 py-2.5 min-h-[44px] rounded-xl text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-600 transition-colors shadow-sm shadow-indigo-200 active:scale-95 shrink-0"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
          Filter
        </button>
      </div>
    </div>

    <!-- Date Range Picker Modal / Popover -->
    <div v-if="showDateFilterModal" class="relative z-40">
      <div class="bg-white border border-slate-200 rounded-2xl shadow-xl p-4 sm:p-5 max-w-md ml-auto">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
          <h4 class="text-[14px] font-bold text-slate-800">Filter by Date Range</h4>
          <button @click="showDateFilterModal = false" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">From Date</label>
            <input v-model="startDate" type="date" class="w-full border border-slate-200 rounded-xl px-3 py-2 min-h-[44px] text-[12px] text-slate-700 font-medium focus:outline-none focus:border-[#5138ed]" />
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">To Date</label>
            <input v-model="endDate" type="date" class="w-full border border-slate-200 rounded-xl px-3 py-2 min-h-[44px] text-[12px] text-slate-700 font-medium focus:outline-none focus:border-[#5138ed]" />
          </div>
        </div>
        <div class="flex items-center justify-end gap-2">
          <button @click="resetDateFilters" class="px-4 py-2.5 min-h-[44px] rounded-xl text-[12px] font-bold text-slate-600 hover:bg-slate-100 transition-colors">
            Reset
          </button>
          <button @click="applyFilters" class="px-5 py-2.5 min-h-[44px] rounded-xl text-[12px] font-bold text-white bg-[#5138ed] hover:bg-indigo-600 transition-colors">
            Apply Date Filter
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="flex flex-col gap-6 min-w-0 max-w-full">

      <!-- Top: Log Table -->
      <div class="w-full bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden flex flex-col min-h-[350px]">
        
        <!-- Loading State -->
        <div v-if="isLoading" class="p-16 flex flex-col items-center justify-center">
          <div class="w-10 h-10 border-4 border-indigo-200 border-t-[#5138ed] rounded-full animate-spin"></div>
          <p class="mt-4 text-[13px] font-bold text-slate-500">Loading activity logs from database...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="logs.length === 0" class="p-12 sm:p-16 flex flex-col items-center justify-center text-center">
          <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 mb-3">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </div>
          <h3 class="text-[15px] font-bold text-slate-800">No activity logs found</h3>
          <p class="text-[12px] text-slate-400 mt-1 max-w-sm">No activity logs match the selected filter or date range.</p>
          <button @click="filterType = 'All Activities'; searchQuery = ''; startDate = ''; endDate = ''; fetchLogs(1);" class="mt-4 px-4 py-2.5 min-h-[44px] bg-indigo-50 text-[#5138ed] rounded-xl text-[12px] font-bold hover:bg-indigo-100 transition-colors">
            Reset Filters
          </button>
        </div>

        <!-- Desktop / Tablet Table Display -->
        <div v-else class="hidden md:block flex-1 overflow-x-auto min-w-0 w-full">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/70 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-5 py-3.5">Time</th>
                <th class="px-4 py-3.5">User</th>
                <th class="px-4 py-3.5">Role</th>
                <th class="px-4 py-3.5">Action</th>
                <th class="px-4 py-3.5">Module</th>
                <th class="px-4 py-3.5">Description</th>
                <th class="px-4 py-3.5">IP Address</th>
                <th class="px-4 py-3.5">Status</th>
                <th class="px-4 py-3.5 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50/60 transition-colors">
                <!-- Time -->
                <td class="px-5 py-3 whitespace-nowrap">
                  <span class="text-[11px] font-bold text-slate-500">{{ log.time.split('\n')[0] }}</span><br>
                  <span class="text-[11px] font-bold text-slate-800">{{ log.time.split('\n')[1] }}</span>
                </td>

                <!-- User -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div :class="getAvatarColor(log.role)" class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 shadow-xs">
                      {{ getAvatarInitials(log.user) }}
                    </div>
                    <div>
                      <p class="text-[12px] font-bold text-slate-800 whitespace-nowrap">{{ log.user }}</p>
                      <p class="text-[11px] text-slate-400">{{ log.email }}</p>
                    </div>
                  </div>
                </td>

                <!-- Role -->
                <td class="px-4 py-3">
                  <span :class="getRoleBadge(log.role)" class="px-2.5 py-1 rounded-md text-[10px] font-bold whitespace-nowrap">
                    {{ log.role }}
                  </span>
                </td>

                <!-- Action -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <svg :class="getActionIcon(log.actionType).color" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(log.actionType).icon"/>
                    </svg>
                    <span class="text-[12px] font-bold text-slate-800 whitespace-nowrap">{{ log.action }}</span>
                  </div>
                </td>

                <!-- Module -->
                <td class="px-4 py-3 text-[12px] font-bold text-slate-800 whitespace-nowrap">{{ log.module }}</td>

                <!-- Description -->
                <td class="px-4 py-3 text-[12px] text-slate-500 max-w-[220px] truncate" :title="log.description">{{ log.description }}</td>

                <!-- IP Address -->
                <td class="px-4 py-3 text-[11px] font-bold text-slate-500 whitespace-nowrap">{{ log.ipAddress }}</td>

                <!-- Status -->
                <td class="px-4 py-3">
                  <span v-if="log.status === 'Success'" class="px-2.5 py-1 text-[10px] font-bold rounded-md bg-emerald-50 text-emerald-500 capitalize">
                    {{ log.status }}
                  </span>
                  <span v-else class="px-2.5 py-1 text-[10px] font-bold rounded-md bg-rose-50 text-rose-500 capitalize">
                    {{ log.status }}
                  </span>
                </td>

                <!-- Actions: View Button -->
                <td class="px-4 py-3 text-center">
                  <button
                    @click="openViewModal(log)"
                    title="View Log Details"
                    class="w-8 h-8 rounded-full bg-indigo-50 text-[#5138ed] flex items-center justify-center hover:bg-[#5138ed] hover:text-white transition-colors mx-auto"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
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
              <div class="flex items-center gap-3 min-w-0">
                <div :class="getAvatarColor(log.role)" class="w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 shadow-xs">
                  {{ getAvatarInitials(log.user) }}
                </div>
                <div class="min-w-0">
                  <p class="text-[13px] font-bold text-slate-800 truncate">{{ log.user }}</p>
                  <p class="text-[11px] text-slate-400 truncate">{{ log.email }}</p>
                </div>
              </div>
              <span v-if="log.status === 'Success'" class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-50 text-emerald-600 capitalize shrink-0">
                {{ log.status }}
              </span>
              <span v-else class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-rose-50 text-rose-600 capitalize shrink-0">
                {{ log.status }}
              </span>
            </div>

            <div class="bg-slate-50 p-2.5 rounded-xl space-y-1.5 text-[12px]">
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Action:</span>
                <div class="flex items-center gap-1.5 font-bold text-slate-700">
                  <svg :class="getActionIcon(log.actionType).color" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(log.actionType).icon"/>
                  </svg>
                  <span>{{ log.action }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Module:</span>
                <span class="font-bold text-slate-700">{{ log.module }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Role:</span>
                <span :class="getRoleBadge(log.role)" class="px-2 py-0.5 rounded text-[10px] font-bold">
                  {{ log.role }}
                </span>
              </div>
              <div class="pt-1 text-slate-600 text-[11px] leading-relaxed break-words">
                {{ log.description }}
              </div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
              <span>{{ log.time.replace('\n', ' ') }}</span>
              <span class="font-mono">{{ log.ipAddress }}</span>
            </div>

            <button
              @click="openViewModal(log)"
              class="w-full min-h-[44px] inline-flex items-center justify-center gap-1.5 text-[12px] font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              View Full Details
            </button>
          </div>
        </div>

        <!-- Dynamic Pagination -->
        <div v-if="pagination.total > 0" class="px-4 sm:px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 mt-auto">
          <span class="text-[12px] font-bold text-slate-400 text-center sm:text-left">
            Showing {{ pagination.from || 1 }} to {{ pagination.to || pagination.total }} of {{ pagination.total }} activities
          </span>
          <div class="flex items-center gap-1.5">
            <!-- Previous Button -->
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              :class="pagination.current_page <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-50 cursor-pointer'"
              class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <!-- Page Numbers -->
            <template v-for="(p, idx) in displayedPages" :key="idx">
              <span v-if="p === '...'" class="w-7 sm:w-8 h-9 sm:h-8 flex items-center justify-center text-slate-400 text-[12px]">...</span>
              <button
                v-else
                @click="goToPage(Number(p))"
                :class="pagination.current_page === p ? 'bg-[#5138ed] text-white shadow-sm shadow-indigo-100' : 'text-slate-500 hover:bg-slate-50'"
                class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg font-bold text-[12px] transition-colors"
              >
                {{ p }}
              </button>
            </template>

            <!-- Next Button -->
            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              :class="pagination.current_page >= pagination.last_page ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-50 cursor-pointer'"
              class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Activity Summary Card -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
          <div class="flex items-center justify-between mb-4 sm:mb-5">
            <h3 class="text-[14px] font-bold text-slate-800">Activity Summary</h3>
            <span class="text-[11px] font-bold text-slate-400">Total: {{ activitySummary.all }}</span>
          </div>
          <div class="space-y-2.5 sm:space-y-3.5">
            <!-- All Activities -->
            <div
              @click="filterByCategory('All Activities')"
              class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors min-h-[44px]"
              :class="filterType === 'All Activities' ? 'bg-indigo-50/60' : ''"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">All Activities</span>
              </div>
              <span class="text-[13px] font-black text-[#5138ed]">{{ activitySummary.all }}</span>
            </div>
            
            <!-- Successful -->
            <div
              @click="filterByCategory('Successful')"
              class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors min-h-[44px]"
              :class="filterType === 'Successful' ? 'bg-emerald-50/60' : ''"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">Successful</span>
              </div>
              <span class="text-[13px] font-black text-emerald-500">{{ activitySummary.successful }}</span>
            </div>

            <!-- Failed -->
            <div
              @click="filterByCategory('Failed')"
              class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors min-h-[44px]"
              :class="filterType === 'Failed' ? 'bg-rose-50/60' : ''"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-rose-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">Failed</span>
              </div>
              <span class="text-[13px] font-black text-rose-500">{{ activitySummary.failed }}</span>
            </div>

            <!-- Logins -->
            <div
              @click="filterByCategory('Logins')"
              class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors min-h-[44px]"
              :class="filterType === 'Logins' ? 'bg-indigo-50/60' : ''"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">Logins</span>
              </div>
              <span class="text-[13px] font-black text-[#5138ed]">{{ activitySummary.logins }}</span>
            </div>

            <!-- Data Changes -->
            <div
              @click="filterByCategory('Data Changes')"
              class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors min-h-[44px]"
              :class="filterType === 'Data Changes' ? 'bg-amber-50/60' : ''"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">Data Changes</span>
              </div>
              <span class="text-[13px] font-black text-amber-500">{{ activitySummary.dataChanges }}</span>
            </div>

            <!-- System Events -->
            <div
              @click="filterByCategory('System Events')"
              class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors min-h-[44px]"
              :class="filterType === 'System Events' ? 'bg-purple-50/60' : ''"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-purple-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">System Events</span>
              </div>
              <span class="text-[13px] font-black text-purple-500">{{ activitySummary.systemEvents }}</span>
            </div>

          </div>
        </div>

        <!-- Top Active Users Card -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
          <div class="flex items-center justify-between mb-4 sm:mb-5">
            <h3 class="text-[14px] font-bold text-slate-800">Top Active Users</h3>
            <span class="text-[11px] font-bold text-slate-400">By Log Count</span>
          </div>
          <div class="space-y-3 sm:space-y-4">
            <div
              v-for="user in topActiveUsers"
              :key="user.name"
              @click="filterByUser(user.name)"
              class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 cursor-pointer transition-colors min-h-[44px]"
              title="Click to filter activities by this user"
            >
              <div :class="getAvatarColor(user.role)" class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 shadow-xs">
                {{ getAvatarInitials(user.name) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-[12px] font-bold text-slate-800 truncate">{{ user.name }}</p>
                <p class="text-[10px] font-medium text-slate-400">{{ user.role }}</p>
              </div>
              <span class="text-[11px] font-bold text-slate-600 whitespace-nowrap bg-slate-50 px-2 py-0.5 rounded-md border border-slate-100 shrink-0">{{ user.count }} actions</span>
            </div>
            <div v-if="topActiveUsers.length === 0" class="text-center py-6 text-[12px] text-slate-400">
              No active users recorded yet.
            </div>
          </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
          <h3 class="text-[14px] font-bold text-slate-800 mb-4">Quick Actions</h3>
          <div class="space-y-3">
            <!-- Export Logs -->
            <button
              @click="handleExportLogs"
              :disabled="isExporting"
              class="flex items-center justify-between w-full p-3 min-h-[44px] text-left border border-slate-100 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/30 transition-all group cursor-pointer"
            >
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span class="font-bold text-[12px] text-slate-700 group-hover:text-[#5138ed] transition-colors">
                  {{ isExporting ? 'Exporting Logs...' : 'Export Logs' }}
                </span>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- Clear Old Logs -->
            <button
              @click="isClearModalOpen = true"
              class="flex items-center justify-between w-full p-3 min-h-[44px] text-left border border-slate-100 rounded-xl hover:border-rose-200 hover:bg-rose-50/30 transition-all group cursor-pointer"
            >
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span class="font-bold text-[12px] text-slate-700 group-hover:text-rose-500 transition-colors">Clear Old Logs</span>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-rose-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- System Audit Report -->
            <button
              @click="filterByCategory('System Events')"
              class="flex items-center justify-between w-full p-3 min-h-[44px] text-left border border-slate-100 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/30 transition-all group cursor-pointer"
            >
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="font-bold text-[12px] text-slate-700 group-hover:text-[#5138ed] transition-colors">System Audit Report</span>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- Security Logs -->
            <button
              @click="filterByCategory('Logins')"
              class="flex items-center justify-between w-full p-3 min-h-[44px] text-left border border-slate-100 rounded-xl hover:border-emerald-200 hover:bg-emerald-50/30 transition-all group cursor-pointer"
            >
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span class="font-bold text-[12px] text-slate-700 group-hover:text-emerald-500 transition-colors">Security Logs</span>
              </div>
              <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>

      </div>

    </div>

    <!-- View Log Details Modal -->
    <div v-if="isViewModalOpen && selectedLog" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-[95vw] sm:max-w-lg w-full max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150 my-auto">
        
        <!-- Modal Header -->
        <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
          <div class="flex items-center gap-3 min-w-0 pr-2">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="min-w-0">
              <h3 class="text-[15px] font-bold text-slate-800 truncate">Activity Log Details</h3>
              <p class="text-[11px] text-slate-400">Log Record #{{ selectedLog.id }}</p>
            </div>
          </div>
          <button @click="isViewModalOpen = false" class="w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-4 sm:p-6 space-y-4 overflow-y-auto flex-1 min-h-0">
          <!-- User info -->
          <div class="flex items-center gap-3 p-3 bg-slate-50/80 rounded-xl border border-slate-100">
            <div :class="getAvatarColor(selectedLog.role)" class="w-10 h-10 rounded-full flex items-center justify-center text-[12px] font-bold shrink-0">
              {{ getAvatarInitials(selectedLog.user) }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h4 class="text-[13px] font-bold text-slate-800 truncate">{{ selectedLog.user }}</h4>
                <span :class="getRoleBadge(selectedLog.role)" class="px-2 py-0.5 rounded text-[10px] font-bold">
                  {{ selectedLog.role }}
                </span>
              </div>
              <p class="text-[11px] text-slate-500 truncate">{{ selectedLog.email || 'No email provided' }}</p>
            </div>
          </div>

          <!-- Key Details Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[12px]">
            <div class="bg-slate-50 p-3 rounded-xl">
              <span class="text-slate-400 font-semibold block text-[11px]">Timestamp</span>
              <span class="text-slate-700 font-bold">{{ selectedLog.time.replace('\n', ' ') }}</span>
            </div>
            <div class="bg-slate-50 p-3 rounded-xl">
              <span class="text-slate-400 font-semibold block text-[11px]">Module</span>
              <span class="text-slate-700 font-bold">{{ selectedLog.module }}</span>
            </div>
            <div class="bg-slate-50 p-3 rounded-xl">
              <span class="text-slate-400 font-semibold block text-[11px]">Action Type</span>
              <span class="text-slate-700 font-bold flex items-center gap-1.5 mt-0.5">
                <svg :class="getActionIcon(selectedLog.actionType).color" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(selectedLog.actionType).icon"/>
                </svg>
                {{ selectedLog.action }}
              </span>
            </div>
            <div class="bg-slate-50 p-3 rounded-xl">
              <span class="text-slate-400 font-semibold block text-[11px]">Status</span>
              <span v-if="selectedLog.status === 'Success'" class="inline-block mt-0.5 px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-50 text-emerald-600">
                Success
              </span>
              <span v-else class="inline-block mt-0.5 px-2 py-0.5 text-[10px] font-bold rounded bg-rose-50 text-rose-600">
                Failed
              </span>
            </div>
            <div class="bg-slate-50 p-3 rounded-xl sm:col-span-2">
              <span class="text-slate-400 font-semibold block text-[11px]">IP Address</span>
              <span class="text-slate-700 font-mono text-[11px] font-bold">{{ selectedLog.ipAddress }}</span>
            </div>
          </div>

          <!-- Description / Details -->
          <div>
            <span class="text-slate-500 font-bold block text-[12px] mb-1.5">Description & Payload:</span>
            <div class="bg-slate-50 border border-slate-100 rounded-xl p-3.5 text-[12px] text-slate-700 font-medium whitespace-pre-wrap leading-relaxed break-words">
              {{ selectedLog.description }}
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-4 sm:px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex justify-end shrink-0">
          <button
            @click="isViewModalOpen = false"
            class="w-full sm:w-auto px-5 py-2.5 min-h-[44px] rounded-xl text-[12px] font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
          >
            Close
          </button>
        </div>

      </div>
    </div>

    <!-- Clear Old Logs Confirmation Modal -->
    <div v-if="isClearModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-[95vw] sm:max-w-md w-full max-h-[92vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-150 my-auto">
        
        <div class="p-4 sm:p-6">
          <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
          </div>
          <h3 class="text-[16px] font-bold text-slate-800 mb-1">Clear Old Activity Logs</h3>
          <p class="text-[13px] text-slate-500 mb-4">
            Are you sure you want to clear activity logs older than <span class="font-bold text-slate-700">{{ clearDays }} days</span>? This action permanently purges archived entries and cannot be undone.
          </p>

          <div class="mb-4">
            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Select Retention Threshold</label>
            <select v-model="clearDays" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 min-h-[44px] text-[13px] font-medium text-slate-700 bg-white focus:outline-none focus:border-[#5138ed]">
              <option :value="7">Older than 7 days</option>
              <option :value="14">Older than 14 days</option>
              <option :value="30">Older than 30 days (Recommended)</option>
              <option :value="60">Older than 60 days</option>
              <option :value="90">Older than 90 days</option>
            </select>
          </div>
        </div>

        <div class="px-4 sm:px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
          <button
            @click="isClearModalOpen = false"
            :disabled="isClearing"
            class="px-4 py-2.5 min-h-[44px] rounded-xl text-[12px] font-bold text-slate-600 hover:bg-slate-100 transition-colors text-center"
          >
            Cancel
          </button>
          <button
            @click="confirmClearOldLogs"
            :disabled="isClearing"
            class="px-5 py-2.5 min-h-[44px] rounded-xl text-[12px] font-bold text-white bg-rose-600 hover:bg-rose-700 transition-colors shadow-sm shadow-rose-200 inline-flex items-center justify-center gap-2"
          >
            <svg v-if="isClearing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ isClearing ? 'Clearing...' : 'Yes, Clear Logs' }}</span>
          </button>
        </div>

      </div>
    </div>

  </div>
</template>

<style scoped>
@keyframes bounceShort {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
.animate-bounce-short {
  animation: bounceShort 0.4s ease;
}
</style>
