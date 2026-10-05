<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'

interface LogEntry {
  id: number
  time: string
  user: string
  email: string
  role: string
  by_who: string
  action: string
  actionType: string
  module: string
  description: string
  ipAddress: string
  status: string
  is_read: boolean
}

const filterType = ref('All Activities')
const currentPage = ref(1)
const perPage = 10

const filterTypes = [
  { label: 'All Activities' },
  { label: 'Successful' },
  { label: 'Failed' },
  { label: 'Logins' },
  { label: 'Data Changes' },
  { label: 'System Events' },
]

const logs = ref<LogEntry[]>([])
const isLoading = ref(true)
const unreadCount = ref(0)

const isViewModalOpen = ref(false)
const selectedLog = ref<LogEntry | null>(null)

const markAllRead = async () => {
  try {
    await apiClient.post('/admin/activity-logs/mark-all-read')
    // Mark all locally
    logs.value.forEach(l => { l.is_read = true })
    unreadCount.value = 0
    // Notify sidebar to clear
    window.dispatchEvent(new CustomEvent('log-count-update', { detail: { count: 0 } }))
  } catch (err) {
    console.error('Failed to mark all as read', err)
  }
}

const openViewModal = async (log: LogEntry) => {
  selectedLog.value = log
  isViewModalOpen.value = true

  // If unread, mark it as read on the backend
  if (!log.is_read) {
    try {
      await apiClient.post(`/admin/activity-logs/${log.id}/read`)
      log.is_read = true
      if (unreadCount.value > 0) unreadCount.value--
      // Dispatch event to update the sidebar badge
      window.dispatchEvent(new Event('log-read'))
    } catch (err) {
      console.error('Failed to mark log as read', err)
    }
  }
}

const closeViewModal = () => {
  isViewModalOpen.value = false
  setTimeout(() => { selectedLog.value = null }, 200)
}

const fetchLogs = async () => {
  try {
    isLoading.value = true
    const response = await apiClient.get('/admin/activity-logs')
    
    // Format the time slightly
    logs.value = response.data.data.map((log: any) => {
      const date = new Date(log.time)
      const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
      const formattedTime = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
      return {
        ...log,
        actionType: log.action, // mapping from backend to match frontend
        ipAddress: log.ip_address,
        is_read: log.is_read,
        time: `${formattedDate}\n${formattedTime}`
      }
    })

    // Check how many are actually unread from the server
    const serverUnreadCount = logs.value.filter(l => !l.is_read).length

    // Automatically mark all as read when the admin views the page
    if (serverUnreadCount > 0) {
      apiClient.post('/admin/activity-logs/mark-all-read').catch(console.error)
      // Tell the sidebar to clear its badge
      window.dispatchEvent(new CustomEvent('log-count-update', { detail: { count: 0 } }))
    }
    
    // Hide the top unread count badge since they've now opened the page,
    // but the red dots on the rows will remain for this session so they can see which are new.
    unreadCount.value = 0
  } catch (error) {
    console.error('Error fetching logs:', error)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchLogs()
})

const activitySummary = computed(() => {
  const all = logs.value.length
  const successful = logs.value.filter(l => l.status === 'Success').length
  const failed = logs.value.filter(l => l.status === 'Failed').length
  const logins = logs.value.filter(l => l.actionType === 'Login' || l.actionType === 'Login Failed').length
  const dataChanges = logs.value.filter(l => ['Created','Updated','Deleted','Exported'].includes(l.actionType)).length
  const systemEvents = logs.value.filter(l => l.actionType === 'System Event' || l.actionType === 'Backup').length

  return { all, successful, failed, logins, dataChanges, systemEvents }
})

const topActiveUsers = computed(() => {
  const userCounts: Record<string, { role: string, count: number }> = {}
  logs.value.forEach(log => {
    if (log.user === 'Unknown') return
    if (!userCounts[log.user]) {
      userCounts[log.user] = { role: log.role, count: 0 }
    }
    userCounts[log.user].count++
  })

  return Object.entries(userCounts)
    .map(([name, data]) => ({ name, role: data.role, count: data.count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})

const filteredLogs = computed(() => {
  if (filterType.value === 'All Activities') return logs.value
  if (filterType.value === 'Successful') return logs.value.filter(l => l.status === 'Success')
  if (filterType.value === 'Failed') return logs.value.filter(l => l.status === 'Failed')
  if (filterType.value === 'Logins') return logs.value.filter(l => l.actionType === 'Login' || l.actionType === 'Login Failed')
  if (filterType.value === 'Data Changes') return logs.value.filter(l => ['Created','Updated','Deleted','Exported'].includes(l.actionType))
  if (filterType.value === 'System Events') return logs.value.filter(l => l.actionType === 'System Event' || l.actionType === 'Backup')
  return logs.value
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredLogs.value.length / perPage)))

const paginatedLogs = computed(() => {
  const start = (currentPage.value - 1) * perPage
  const end = start + perPage
  return filteredLogs.value.slice(start, end)
})

const visiblePages = computed(() => {
  const pages: (number | string)[] = []
  const total = totalPages.value
  const current = currentPage.value
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

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const goToPage = (page: number | string) => {
  if (typeof page === 'number' && page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Reset to first page when filter changes
watch(filterType, () => {
  currentPage.value = 1
})

// Keep currentPage within bounds if filtered length changes
watch(filteredLogs, (newVal) => {
  const max = Math.max(1, Math.ceil(newVal.length / perPage))
  if (currentPage.value > max) {
    currentPage.value = max
  }
})

const getRoleBadge = (role: string) => {
  if (role === 'Super Admin') return 'bg-indigo-50 text-[#5138ed]'
  if (role.includes('Department Head')) return 'bg-purple-50 text-purple-600'
  if (role === 'Instructor') return 'bg-blue-50 text-blue-500'
  if (role === 'Student') return 'bg-emerald-50 text-emerald-500'
  return 'bg-slate-50 text-slate-500'
}

// "By Who" badge — same color mapping as role badge
const getByWhoBadge = (byWho: string) => {
  if (byWho === 'Super Admin') return 'bg-indigo-50 text-[#5138ed]'
  if (byWho.includes('Department Head')) return 'bg-purple-50 text-purple-600'
  if (byWho === 'Instructor') return 'bg-blue-50 text-blue-500'
  if (byWho === 'Student') return 'bg-emerald-50 text-emerald-500'
  return 'bg-slate-50 text-slate-500'
}

const getAvatarColor = (role: string) => {
  if (role === 'Super Admin') return 'bg-indigo-100 text-[#5138ed]'
  if (role.includes('Department Head')) return 'bg-purple-100 text-purple-600'
  if (role === 'Instructor') return 'bg-blue-100 text-blue-600'
  if (role === 'Student') return 'bg-emerald-100 text-emerald-600'
  return 'bg-slate-100 text-slate-500'
}

const getActionIcon = (type: string) => {
  if (type === 'Login') return { icon: 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1', color: 'text-emerald-500' }
  if (type === 'Created') return { icon: 'M12 4v16m8-8H4', color: 'text-emerald-500' }
  if (type === 'Updated') return { icon: 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z', color: 'text-amber-500' }
  if (type === 'Deleted') return { icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', color: 'text-rose-500' }
  if (type === 'Submitted') return { icon: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8', color: 'text-[#5138ed]' }
  if (type === 'Exported') return { icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4', color: 'text-[#5138ed]' }
  if (type === 'Backup') return { icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4', color: 'text-[#5138ed]' }
  if (type === 'System Event') return { icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', color: 'text-[#5138ed]' }
  if (type === 'Login Failed') return { icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', color: 'text-rose-500' }
  return { icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-slate-400' }
}

const getAvatarInitials = (name: string) => {
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
}
</script>

<template>
  <div class="max-w-[1500px] mx-auto">

    <!-- Page Actions -->
    <div class="flex flex-wrap items-center justify-end gap-3 mb-6">
      <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
        <!-- Unread Count Badge -->
        <div v-if="unreadCount > 0" class="flex items-center gap-2 px-3 py-2 rounded-xl bg-rose-50 border border-rose-200">
          <div class="w-2 h-2 bg-rose-500 rounded-full animate-pulse"></div>
          <span class="text-[12px] font-bold text-rose-600">{{ unreadCount }} unread</span>
          <button @click="markAllRead" class="text-[11px] font-bold text-rose-500 hover:text-rose-700 underline underline-offset-2 ml-1">Mark all read</button>
        </div>
        <div class="relative flex-1 sm:flex-initial min-w-[140px]">
          <select v-model="filterType" class="w-full appearance-none border border-slate-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-slate-700 font-bold bg-white focus:outline-none focus:border-[#5138ed] shadow-sm">
            <option v-for="f in filterTypes" :key="f.label" :value="f.label">⚙️ {{ f.label }}</option>
          </select>
          <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
        <div class="hidden md:flex items-center gap-2 border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 font-bold bg-white shadow-sm">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span>May 18, 2025 - May 23, 2025</span>
        </div>
        <button class="flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-600 transition-colors shadow-sm shadow-indigo-200 w-full sm:w-auto">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
          Filter
        </button>
      </div>
    </div>

    <!-- Main Content + Sidebar Grid -->
    <div class="flex flex-col gap-6">

      <!-- Top: Log Table -->
      <div class="w-full bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden flex flex-col h-[fit-content]">
        <!-- Desktop / Tablet Table View -->
        <div class="flex-1 overflow-x-auto min-w-0 w-full hidden md:block">
          <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead>
              <tr class="bg-slate-50/60 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-5 py-3.5">Time</th>
                <th class="px-4 py-3.5">User</th>
                <th class="px-4 py-3.5">By Who</th>
                <th class="px-4 py-3.5">Action</th>
                <th class="px-4 py-3.5">Module</th>
                <th class="px-4 py-3.5">Description</th>
                <th class="px-4 py-3.5">IP Address</th>
                <th class="px-4 py-3.5">Status</th>
                <th class="px-4 py-3.5 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="filteredLogs.length === 0">
                <td colspan="9" class="px-5 py-12 text-center text-slate-400 text-[13px] font-medium">
                  No activity logs found for the selected filter.
                </td>
              </tr>
              <tr v-else v-for="log in paginatedLogs" :key="log.id" class="transition-colors hover:bg-slate-50/80" :class="!log.is_read ? 'bg-rose-50/20' : ''">
                <!-- Time -->
                <td class="px-5 py-3 whitespace-nowrap relative">
                  <div v-if="!log.is_read" class="absolute left-2 top-1/2 -translate-y-1/2 w-1.5 h-1.5 bg-rose-500 rounded-full"></div>
                  <span class="text-[11px] font-bold text-slate-500">{{ log.time.split('\n')[0] }}</span><br>
                  <span class="text-[11px] font-bold" :class="!log.is_read ? 'text-rose-600' : 'text-slate-800'">{{ log.time.split('\n')[1] }}</span>
                </td>

                <!-- User -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div :class="getAvatarColor(log.role)" class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0">
                      {{ getAvatarInitials(log.user) }}
                    </div>
                    <div>
                      <p class="text-[12px] font-bold text-slate-800 whitespace-nowrap">{{ log.user }}</p>
                      <p class="text-[11px] text-slate-400">{{ log.email }}</p>
                    </div>
                  </div>
                </td>

                <!-- By Who -->
                <td class="px-4 py-3">
                  <span :class="getByWhoBadge(log.by_who || log.role)" class="px-2.5 py-1 rounded-md text-[10px] font-bold whitespace-nowrap">
                    {{ log.by_who || log.role }}
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
                <td class="px-4 py-3 text-[12px] text-slate-500 max-w-[200px] truncate" :title="log.description">{{ log.description }}</td>

                <!-- IP -->
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

                <!-- View -->
                <td class="px-4 py-3 text-center">
                  <button @click="openViewModal(log)" class="w-7 h-7 rounded-full bg-indigo-50 text-[#5138ed] flex items-center justify-center hover:bg-[#5138ed] hover:text-white transition-colors mx-auto">
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

        <!-- Mobile Card List View (Small Screens) -->
        <div class="block md:hidden divide-y divide-slate-100">
          <div v-if="filteredLogs.length === 0" class="p-6 text-center text-slate-400 text-[13px] font-medium">
            No activity logs found for the selected filter.
          </div>
          <div v-else v-for="log in paginatedLogs" :key="'mob-' + log.id" class="p-4 transition-colors hover:bg-slate-50/80" :class="!log.is_read ? 'bg-rose-50/20' : ''">
            <div class="flex items-start justify-between gap-3 mb-2.5">
              <div class="flex items-center gap-2.5 min-w-0">
                <div :class="getAvatarColor(log.role)" class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0">
                  {{ getAvatarInitials(log.user) }}
                </div>
                <div class="min-w-0">
                  <p class="text-[13px] font-bold text-slate-800 truncate">{{ log.user }}</p>
                  <p class="text-[11px] text-slate-400 truncate">{{ log.email }}</p>
                </div>
              </div>
              <span v-if="log.status === 'Success'" class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-50 text-emerald-500 capitalize shrink-0">
                {{ log.status }}
              </span>
              <span v-else class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-rose-50 text-rose-500 capitalize shrink-0">
                {{ log.status }}
              </span>
            </div>
            
            <div class="flex flex-wrap items-center gap-2 mb-2 text-[11px]">
              <span :class="getByWhoBadge(log.by_who || log.role)" class="px-2 py-0.5 rounded text-[10px] font-bold">
                {{ log.by_who || log.role }}
              </span>
              <div class="flex items-center gap-1 font-bold text-slate-700">
                <svg :class="getActionIcon(log.actionType).color" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(log.actionType).icon"/>
                </svg>
                <span>{{ log.action }}</span>
              </div>
              <span class="text-slate-400">·</span>
              <span class="font-semibold text-slate-600">{{ log.module }}</span>
            </div>

            <p class="text-[12px] text-slate-500 line-clamp-2 mb-3 leading-relaxed">{{ log.description }}</p>

            <div class="flex items-center justify-between pt-2 border-t border-slate-50 text-[11px]">
              <span class="text-slate-400 font-medium">{{ log.time.replace('\n', ' • ') }}</span>
              <button @click="openViewModal(log)" class="flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 text-[#5138ed] font-bold hover:bg-[#5138ed] hover:text-white transition-colors text-[11px]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Details
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 mt-auto bg-slate-50/30">
          <span class="text-[12px] font-bold text-slate-500">
            Showing <span class="font-bold text-slate-700">{{ filteredLogs.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }}</span> to <span class="font-bold text-slate-700">{{ Math.min(currentPage * perPage, filteredLogs.length) }}</span> of <span class="font-bold text-slate-700">{{ filteredLogs.length }}</span> activities
          </span>
          <div class="flex items-center gap-1.5">
            <!-- Previous Button -->
            <button 
              @click="prevPage"
              :disabled="currentPage <= 1"
              title="Previous page"
              class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-white hover:text-[#5138ed] hover:border-[#5138ed] disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-500 disabled:hover:border-slate-200 disabled:cursor-not-allowed transition-colors cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <!-- Page Number Buttons -->
            <template v-for="(page, idx) in visiblePages" :key="idx">
              <span v-if="page === '...'" class="w-8 h-8 flex items-center justify-center text-slate-400 text-[12px] font-bold select-none">...</span>
              <button
                v-else
                @click="goToPage(page)"
                :class="currentPage === page ? 'bg-[#5138ed] text-white border-[#5138ed] shadow-sm' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                class="w-8 h-8 flex items-center justify-center rounded-lg border text-[12px] font-bold transition-colors cursor-pointer"
              >
                {{ page }}
              </button>
            </template>

            <!-- Next Button -->
            <button 
              @click="nextPage"
              :disabled="currentPage >= totalPages"
              title="Next page"
              class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-white hover:text-[#5138ed] hover:border-[#5138ed] disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-500 disabled:hover:border-slate-200 disabled:cursor-not-allowed transition-colors cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>
      </div>

                <!-- Bottom Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Activity Summary Card -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6">
          <h3 class="text-[14px] font-bold text-slate-800 mb-5">Activity Summary</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">All Activities</span>
              </div>
              <span class="text-[13px] font-black text-[#5138ed]">{{ activitySummary.all }}</span>
            </div>
            
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">Successful</span>
              </div>
              <span class="text-[13px] font-black text-emerald-500">{{ activitySummary.successful }}</span>
            </div>

            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-rose-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">Failed</span>
              </div>
              <span class="text-[13px] font-black text-rose-500">{{ activitySummary.failed }}</span>
            </div>

            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">Logins</span>
              </div>
              <span class="text-[13px] font-black text-[#5138ed]">{{ activitySummary.logins }}</span>
            </div>

            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </div>
                <span class="text-[12px] font-bold text-slate-800">Data Changes</span>
              </div>
              <span class="text-[13px] font-black text-amber-500">{{ activitySummary.dataChanges }}</span>
            </div>

            <div class="flex items-center justify-between">
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

        <!-- Top Active Users -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6">
          <h3 class="text-[14px] font-bold text-slate-800 mb-5">Top Active Users</h3>
          <div class="space-y-4">
            <div v-for="user in topActiveUsers" :key="user.name" class="flex items-center gap-3">
              <div :class="getAvatarColor(user.role)" class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0">
                {{ getAvatarInitials(user.name) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-[12px] font-bold text-slate-800 truncate">{{ user.name }}</p>
                <p class="text-[10px] font-medium text-slate-400">{{ user.role }}</p>
              </div>
              <span class="text-[11px] font-bold text-slate-600 whitespace-nowrap">{{ user.count }} actions</span>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6">
          <h3 class="text-[14px] font-bold text-slate-800 mb-4">Quick Actions</h3>
          <div class="space-y-3">
            <button class="flex items-center justify-between w-full p-2.5 text-left border border-slate-100 rounded-xl hover:border-slate-200 transition-colors group">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span class="font-bold text-[12px] text-slate-700 group-hover:text-[#5138ed] transition-colors">Export Logs</span>
              </div>
            </button>
            <button class="flex items-center justify-between w-full p-2.5 text-left border border-slate-100 rounded-xl hover:border-slate-200 transition-colors group">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span class="font-bold text-[12px] text-slate-700 group-hover:text-rose-500 transition-colors">Clear Old Logs</span>
              </div>
            </button>
            <button class="flex items-center justify-between w-full p-2.5 text-left border border-slate-100 rounded-xl hover:border-slate-200 transition-colors group">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="font-bold text-[12px] text-slate-700 group-hover:text-[#5138ed] transition-colors">System Audit Report</span>
              </div>
            </button>
            <button class="flex items-center justify-between w-full p-2.5 text-left border border-slate-100 rounded-xl hover:border-slate-200 transition-colors group">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span class="font-bold text-[12px] text-slate-700 group-hover:text-emerald-500 transition-colors">Security Logs</span>
              </div>
            </button>
          </div>
        </div>
      </div>

              </div>
  </div>

  <!-- View Modal -->
  <Teleport to="body">
    <div v-if="isViewModalOpen && selectedLog" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeViewModal"></div>
      <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-[95vw] sm:max-w-lg max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-slate-100 bg-slate-50/50 shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 class="text-[15px] sm:text-[16px] font-bold text-slate-800">Log Details</h3>
              <p class="text-[11px] sm:text-[12px] font-medium text-slate-500">View complete activity information</p>
            </div>
          </div>
          <button @click="closeViewModal" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-4 sm:p-6 space-y-4 sm:space-y-5 overflow-y-auto">
          
          <!-- User Info -->
          <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 p-3.5 sm:p-4 bg-slate-50 rounded-xl border border-slate-100">
            <div class="flex items-center gap-3 sm:gap-4 min-w-0">
              <div :class="getAvatarColor(selectedLog.by_who || selectedLog.role)" class="w-11 h-11 sm:w-12 sm:h-12 rounded-full flex items-center justify-center text-[15px] sm:text-[16px] font-bold shrink-0 shadow-sm border-2 border-white">
                {{ getAvatarInitials(selectedLog.user) }}
              </div>
              <div class="min-w-0">
                <p class="text-[14px] sm:text-[15px] font-bold text-slate-800 truncate">{{ selectedLog.user }}</p>
                <p class="text-[12px] sm:text-[13px] text-slate-500 truncate">{{ selectedLog.email }}</p>
              </div>
            </div>
            <span :class="getByWhoBadge(selectedLog.by_who || selectedLog.role)" class="px-3 py-1 rounded-md text-[11px] font-bold shadow-sm self-start sm:self-auto shrink-0">
              {{ selectedLog.by_who || selectedLog.role }}
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <!-- Time -->
            <div class="p-3.5 sm:p-4 border border-slate-100 rounded-xl space-y-1">
              <p class="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider">Date & Time</p>
              <p class="text-[12px] sm:text-[13px] font-bold text-slate-800">{{ selectedLog.time.replace('\n', ' ') }}</p>
            </div>

            <!-- IP Address -->
            <div class="p-3.5 sm:p-4 border border-slate-100 rounded-xl space-y-1">
              <p class="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider">IP Address</p>
              <p class="text-[12px] sm:text-[13px] font-bold text-slate-800">{{ selectedLog.ipAddress }}</p>
            </div>

            <!-- Action -->
            <div class="p-3.5 sm:p-4 border border-slate-100 rounded-xl space-y-1">
              <p class="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider">Action Type</p>
              <div class="flex items-center gap-2">
                <svg :class="getActionIcon(selectedLog.actionType).color" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(selectedLog.actionType).icon"/>
                </svg>
                <p class="text-[12px] sm:text-[13px] font-bold text-slate-800">{{ selectedLog.action }}</p>
              </div>
            </div>

            <!-- Module -->
            <div class="p-3.5 sm:p-4 border border-slate-100 rounded-xl space-y-1">
              <p class="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider">Module</p>
              <p class="text-[12px] sm:text-[13px] font-bold text-slate-800">{{ selectedLog.module }}</p>
            </div>
          </div>

          <!-- Description -->
          <div class="p-3.5 sm:p-4 border border-slate-100 rounded-xl space-y-1.5 sm:space-y-2">
            <p class="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider">Description</p>
            <p class="text-[13px] sm:text-[14px] font-medium text-slate-700 leading-relaxed">{{ selectedLog.description }}</p>
          </div>

          <!-- Status -->
          <div class="flex items-center justify-between p-3.5 sm:p-4 border border-slate-100 rounded-xl">
            <p class="text-[12px] font-bold text-slate-500">Operation Status</p>
            <span v-if="selectedLog.status === 'Success'" class="px-3 py-1.5 text-[11px] font-bold rounded-md bg-emerald-50 text-emerald-500 capitalize flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              {{ selectedLog.status }}
            </span>
            <span v-else class="px-3 py-1.5 text-[11px] font-bold rounded-md bg-rose-50 text-rose-500 capitalize flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              {{ selectedLog.status }}
            </span>
          </div>

        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-end px-4 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/50 shrink-0">
          <button @click="closeViewModal" class="w-full sm:w-auto px-6 py-2.5 rounded-xl text-[13px] font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 transition-colors shadow-sm">
            Close
          </button>
        </div>

      </div>
    </div>
  </Teleport>

</template>
