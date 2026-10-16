<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import apiClient from '../../../core/api/apiClient'

// ── State Management ──────────────────────────────────────────────────────────
const activeTab = ref<'recovery' | 'control'>('recovery')

// Recovery Requests State
const recoveryRequests = ref<any[]>([])
const isLoadingRecovery = ref(true)
const recoveryError = ref<string | null>(null)
const searchQuery = ref('')
const debouncedSearch = ref('')
let searchDebounceTimer: any = null

const statusFilter = ref('all')
const currentPage = ref(1)
const perPage = ref(15)
const pagination = ref({
  total: 0,
  current_page: 1,
  last_page: 1,
  per_page: 15,
})

const stats = ref({
  total: 0,
  pending_approval: 0,
  approved: 0,
  admin_override: 0,
  rejected: 0,
  total_interruption_seconds: 0,
})

// Expanded row IDs for viewing recovery audit details
const expandedRowIds = ref<Set<number>>(new Set())
const toggleRowExpand = (id: number) => {
  if (expandedRowIds.value.has(id)) {
    expandedRowIds.value.delete(id)
  } else {
    expandedRowIds.value.add(id)
  }
}

// Exam Sessions & Controls State
const allExams = ref<any[]>([])
const isLoadingExams = ref(false)
const examsError = ref<string | null>(null)
const examSearchQuery = ref('')
const examStatusFilter = ref('all')
const examStats = ref<any>({})

// Last refreshed indicator
const lastRefreshedAt = ref<Date>(new Date())
const lastRefreshedText = computed(() => {
  return lastRefreshedAt.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
})

// ── Modals State ───────────────────────────────────────────────────────────────
// Override Modal
const showOverrideModal = ref(false)
const overrideRequest = ref<any | null>(null)
const overrideSeconds = ref<number>(0)
const overrideReason = ref('')
const isOverriding = ref(false)
const overrideError = ref<string | null>(null)

// Cancel Exam Modal
const showCancelModal = ref(false)
const cancelExam = ref<any | null>(null)
const cancelReason = ref('')
const isCancelling = ref(false)
const cancelError = ref<string | null>(null)

// Pause Exam Modal
const showPauseModal = ref(false)
const pauseExam = ref<any | null>(null)
const isPausing = ref(false)
const pauseError = ref<string | null>(null)

// Toast State
const toast = ref<{ message: string; type: 'success' | 'error' | 'info' } | null>(null)
let toastTimer: any = null
const showToast = (message: string, type: 'success' | 'error' | 'info' = 'success') => {
  clearTimeout(toastTimer)
  toast.value = { message, type }
  toastTimer = setTimeout(() => { toast.value = null }, 4000)
}

// ── Search Debounce ────────────────────────────────────────────────────────────
watch(searchQuery, (newVal) => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    debouncedSearch.value = newVal.trim()
    currentPage.value = 1
    fetchRecoveryRequests()
  }, 300)
})

watch(statusFilter, () => {
  currentPage.value = 1
  fetchRecoveryRequests()
})

watch(perPage, () => {
  currentPage.value = 1
  fetchRecoveryRequests()
})

// ── API Fetching ───────────────────────────────────────────────────────────────
const fetchRecoveryRequests = async () => {
  isLoadingRecovery.value = true
  recoveryError.value = null
  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (statusFilter.value !== 'all') {
      params.status = statusFilter.value
    }
    if (debouncedSearch.value) {
      params.search = debouncedSearch.value
    }

    const res = await apiClient.get('/admin/recovery-requests', { params })
    recoveryRequests.value = res.data.data ?? []
    if (res.data.pagination) {
      pagination.value = res.data.pagination
    }
    if (res.data.stats) {
      stats.value = res.data.stats
    }
    lastRefreshedAt.value = new Date()
  } catch (err: any) {
    recoveryError.value = err.response?.data?.message ?? 'Failed to load recovery requests from the server.'
  } finally {
    isLoadingRecovery.value = false
  }
}

const fetchExams = async () => {
  isLoadingExams.value = true
  examsError.value = null
  try {
    const res = await apiClient.get('/admin/exams')
    allExams.value = res.data.data?.exams ?? []
    examStats.value = res.data.data?.stats ?? {}
    lastRefreshedAt.value = new Date()
  } catch (err: any) {
    examsError.value = err.response?.data?.message ?? 'Failed to load examination sessions.'
  } finally {
    isLoadingExams.value = false
  }
}

const handleRefresh = async () => {
  if (activeTab.value === 'recovery') {
    await fetchRecoveryRequests()
  } else {
    await fetchExams()
  }
}

const switchTab = (tab: 'recovery' | 'control') => {
  activeTab.value = tab
  if (tab === 'control' && allExams.value.length === 0) {
    fetchExams()
  }
}

const resetFilters = () => {
  searchQuery.value = ''
  debouncedSearch.value = ''
  statusFilter.value = 'all'
  currentPage.value = 1
  fetchRecoveryRequests()
}

onMounted(() => {
  fetchRecoveryRequests()
  fetchExams()
})

// ── Computed Properties ────────────────────────────────────────────────────────
const filteredExams = computed(() => {
  return allExams.value.filter(exam => {
    const q = examSearchQuery.value.toLowerCase().trim()
    const matchesSearch = !q ||
      (exam.title ?? '').toLowerCase().includes(q) ||
      (exam.course ?? '').toLowerCase().includes(q) ||
      (exam.course_code ?? '').toLowerCase().includes(q) ||
      (exam.instructor ?? '').toLowerCase().includes(q) ||
      (exam.department ?? '').toLowerCase().includes(q) ||
      (exam.examCode ?? '').toLowerCase().includes(q)

    let matchesStatus = true
    if (examStatusFilter.value === 'published') {
      matchesStatus = exam.status === 'published' && !exam.is_paused && !exam.is_cancelled
    } else if (examStatusFilter.value === 'paused') {
      matchesStatus = exam.status === 'paused' || exam.is_paused === true
    } else if (examStatusFilter.value === 'cancelled') {
      matchesStatus = exam.status === 'cancelled' || exam.is_cancelled === true
    } else if (examStatusFilter.value !== 'all') {
      matchesStatus = exam.status === examStatusFilter.value
    }

    return matchesSearch && matchesStatus
  })
})

const liveExamsCount = computed(() => {
  return allExams.value.filter(e => e.status === 'published' && !e.is_paused && !e.is_cancelled).length
})

const pausedExamsCount = computed(() => {
  return allExams.value.filter(e => e.status === 'paused' || e.is_paused).length
})

const cancelledExamsCount = computed(() => {
  return allExams.value.filter(e => e.status === 'cancelled' || e.is_cancelled).length
})

// ── Helpers & Formatters ───────────────────────────────────────────────────────
const statusConfig: Record<string, { badge: string; dot: string; label: string }> = {
  pending_approval: {
    badge: 'bg-amber-50 text-amber-700 border-amber-200/80',
    dot: 'bg-amber-500',
    label: 'Pending Review',
  },
  approved: {
    badge: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
    dot: 'bg-emerald-500',
    label: 'Approved',
  },
  admin_override: {
    badge: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
    dot: 'bg-indigo-500',
    label: 'Admin Override',
  },
  rejected: {
    badge: 'bg-rose-50 text-rose-700 border-rose-200/80',
    dot: 'bg-rose-500',
    label: 'Rejected',
  },
  extended_interruption: {
    badge: 'bg-slate-100 text-slate-700 border-slate-200/80',
    dot: 'bg-slate-500',
    label: 'Extended Interruption',
  },
}

const getStatusBadge = (req: any) => {
  if (req.is_admin_override || req.status === 'admin_override') {
    return statusConfig.admin_override
  }
  return statusConfig[req.status] ?? statusConfig.pending_approval
}

const formatDuration = (seconds: number | null | undefined): string => {
  if (seconds === null || seconds === undefined) return '—'
  if (seconds === 0) return '0 seconds'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60

  if (m === 0) {
    return `${s} ${s === 1 ? 'second' : 'seconds'}`
  }
  if (s === 0) {
    return `${m} ${m === 1 ? 'min' : 'mins'}`
  }
  return `${m}m ${s}s`
}

const formatClock = (seconds: number | null | undefined): string => {
  if (seconds === null || seconds === undefined) return '00:00'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

const formatDateTime = (dt: string | null | undefined): string => {
  if (!dt) return '—'
  try {
    const d = new Date(dt)
    return d.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return dt
  }
}

const getInitials = (name?: string): string => {
  if (!name) return 'ST'
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

const getAvatarBg = (name?: string): string => {
  const colors = [
    'bg-indigo-100 text-indigo-700 border-indigo-200',
    'bg-sky-100 text-sky-700 border-sky-200',
    'bg-violet-100 text-violet-700 border-violet-200',
    'bg-emerald-100 text-emerald-700 border-emerald-200',
    'bg-amber-100 text-amber-700 border-amber-200',
    'bg-teal-100 text-teal-700 border-teal-200',
  ]
  if (!name) return colors[0]
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

// ── Override Handlers ──────────────────────────────────────────────────────────
const openOverride = (req: any) => {
  if (req.is_submitted || (req.can_override === false && req.attempt_status !== 'in_progress')) {
    showToast('Cannot override: This exam attempt has already been submitted and concluded.', 'info')
    return
  }
  overrideRequest.value = req
  overrideSeconds.value = req.suggested_seconds ?? req.interruption_seconds ?? 0
  overrideReason.value = ''
  overrideError.value = null
  showOverrideModal.value = true
}

const setPresetSeconds = (extra: number) => {
  overrideSeconds.value = Math.max(0, extra)
}

const matchInterruptionSeconds = () => {
  if (overrideRequest.value?.interruption_seconds !== undefined) {
    overrideSeconds.value = overrideRequest.value.interruption_seconds
  }
}

const submitOverride = async () => {
  if (!overrideReason.value.trim() || overrideReason.value.trim().length < 4) {
    overrideError.value = 'Please provide a clear administrative override reason (at least 4 characters).'
    return
  }
  if (overrideSeconds.value < 0) {
    overrideError.value = 'Approved extra time seconds cannot be negative.'
    return
  }

  isOverriding.value = true
  overrideError.value = null
  try {
    await apiClient.post(`/admin/recovery-requests/${overrideRequest.value.id}/override`, {
      approved_seconds: overrideSeconds.value,
      override_reason: overrideReason.value.trim(),
    })
    showOverrideModal.value = false
    showToast('Extra time override applied successfully. Attempt deadline updated.', 'success')
    fetchRecoveryRequests()
  } catch (err: any) {
    overrideError.value = err.response?.data?.message ?? 'Failed to apply administrator override.'
  } finally {
    isOverriding.value = false
  }
}

// ── Exam Control Actions ───────────────────────────────────────────────────────
const openCancelExam = (exam: any) => {
  cancelExam.value = exam
  cancelReason.value = ''
  cancelError.value = null
  showCancelModal.value = true
}

const submitCancelExam = async () => {
  if (!cancelReason.value.trim() || cancelReason.value.trim().length < 4) {
    cancelError.value = 'A valid cancellation reason is required (at least 4 characters).'
    return
  }
  isCancelling.value = true
  cancelError.value = null
  try {
    const examId = cancelExam.value.id ?? cancelExam.value.exam_id
    await apiClient.post(`/admin/exams/${examId}/cancel`, {
      reason: cancelReason.value.trim(),
    })
    showCancelModal.value = false
    showToast(`Exam "${cancelExam.value.title}" has been cancelled. In-progress students notified.`, 'success')
    fetchExams()
    fetchRecoveryRequests()
  } catch (err: any) {
    cancelError.value = err.response?.data?.message ?? 'Failed to cancel examination.'
  } finally {
    isCancelling.value = false
  }
}

const openPauseExam = (exam: any) => {
  pauseExam.value = exam
  pauseError.value = null
  showPauseModal.value = true
}

const submitPauseExam = async () => {
  isPausing.value = true
  pauseError.value = null
  try {
    const examId = pauseExam.value.id ?? pauseExam.value.exam_id
    await apiClient.post(`/admin/exams/${examId}/pause`)
    showPauseModal.value = false
    showToast(`Exam "${pauseExam.value.title}" paused. Active student timers are frozen.`, 'info')
    fetchExams()
  } catch (err: any) {
    pauseError.value = err.response?.data?.message ?? 'Failed to pause examination.'
  } finally {
    isPausing.value = false
  }
}

const resumeExam = async (exam: any) => {
  try {
    const examId = exam.id ?? exam.exam_id
    const res = await apiClient.post(`/admin/exams/${examId}/resume`)
    const pauseSecs = res.data?.data?.pause_duration ?? 0
    showToast(`Exam "${exam.title}" resumed. In-progress attempts compensated (+${pauseSecs}s).`, 'success')
    fetchExams()
  } catch (err: any) {
    showToast(err.response?.data?.message ?? 'Failed to resume examination.', 'error')
  }
}

const reinstateExam = async (exam: any) => {
  if (!confirm(`Are you sure you want to reinstate "${exam.title}"? Students will be permitted to resume taking the exam.`)) return
  try {
    const examId = exam.id ?? exam.exam_id
    await apiClient.post(`/admin/exams/${examId}/reinstate`)
    showToast(`Exam "${exam.title}" has been successfully reinstated.`, 'success')
    fetchExams()
  } catch (err: any) {
    showToast(err.response?.data?.message ?? 'Failed to reinstate examination.', 'error')
  }
}

const filterRecoveryByExam = (examTitle: string) => {
  searchQuery.value = examTitle
  switchTab('recovery')
}
</script>

<template>
  <div class="min-h-full space-y-6 pb-12">

    <!-- ── Floating Toast Notification ──────────────────────────────────────── -->
    <Transition name="toast-slide">
      <div
        v-if="toast"
        :class="{
          'bg-emerald-600 text-white shadow-emerald-500/20': toast.type === 'success',
          'bg-rose-600 text-white shadow-rose-500/20': toast.type === 'error',
          'bg-slate-900 text-white shadow-slate-900/20': toast.type === 'info',
        }"
        class="fixed top-6 right-6 z-[600] px-5 py-3.5 rounded-2xl font-medium text-sm shadow-2xl flex items-center gap-3 border border-white/10 backdrop-blur-md"
      >
        <span class="p-1 rounded-full bg-white/20">
          <svg v-if="toast.type === 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          <svg v-else-if="toast.type === 'error'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <span>{{ toast.message }}</span>
        <button @click="toast = null" class="ml-2 text-white/70 hover:text-white transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </Transition>

    <!-- ── Modern Page Header ────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 transition-all">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-2.5 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Exam Control Centre</h1>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                Wollo University Command
              </span>
            </div>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl leading-relaxed">
              Monitor live examination sessions, review connection interruptions, and manage approved time overrides.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3 self-end sm:self-center shrink-0">
          <!-- Real-Time Sync Indicator -->
          <div class="hidden md:inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 text-xs font-medium">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span>Live Sync</span>
            <span class="text-slate-400">|</span>
            <span class="text-slate-500 font-mono text-[11px]">{{ lastRefreshedText }}</span>
          </div>

          <!-- Refresh Button -->
          <button
            @click="handleRefresh"
            :disabled="isLoadingRecovery || isLoadingExams"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300 shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:opacity-60 cursor-pointer"
          >
            <svg
              class="w-4 h-4 text-slate-500"
              :class="{ 'animate-spin text-indigo-600': isLoadingRecovery || isLoadingExams }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span>Refresh</span>
          </button>
        </div>

      </div>

      <!-- ── Segmented Navigation Tabs ──────────────────────────────────────── -->
      <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
        <button
          @click="switchTab('recovery')"
          :class="activeTab === 'recovery'
            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900'"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all focus:outline-none cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span>Recovery Requests</span>
          <span
            v-if="stats.pending_approval > 0"
            class="ml-1 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-bold"
            :class="activeTab === 'recovery' ? 'bg-amber-400 text-slate-900' : 'bg-amber-500 text-white'"
          >
            {{ stats.pending_approval }}
          </span>
        </button>

        <button
          @click="switchTab('control')"
          :class="activeTab === 'control'
            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900'"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all focus:outline-none cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          <span>Exam Sessions & Controls</span>
          <span
            v-if="allExams.length > 0"
            class="ml-1 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-semibold"
            :class="activeTab === 'control' ? 'bg-indigo-700 text-indigo-100' : 'bg-slate-200 text-slate-700'"
          >
            {{ allExams.length }}
          </span>
        </button>
      </div>
    </div>

    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <!-- ── TAB 1: RECOVERY REQUESTS ─────────────────────────────────────────── -->
    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'recovery'">

      <!-- ── KPI Summary Metric Row ─────────────────────────────────────────── -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total Requests -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Requests</span>
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
          </div>
          <div class="mt-3 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-slate-900">{{ stats.total }}</span>
            <span class="text-xs text-slate-400 font-medium">logged incidents</span>
          </div>
          <div class="mt-2 text-xs text-slate-500">Platform connection events</div>
        </div>

        <!-- Pending Review -->
        <div class="bg-white rounded-2xl border border-amber-200/80 p-5 shadow-sm hover:shadow-md transition-shadow bg-gradient-to-br from-white to-amber-50/30">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Pending Review</span>
            <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
          </div>
          <div class="mt-3 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-amber-600">{{ stats.pending_approval }}</span>
            <span
              v-if="stats.pending_approval > 0"
              class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white"
            >
              Action Required
            </span>
          </div>
          <div class="mt-2 text-xs text-slate-500">Awaiting instructor/admin action</div>
        </div>

        <!-- Approved Extra Time -->
        <div class="bg-white rounded-2xl border border-emerald-200/80 p-5 shadow-sm hover:shadow-md transition-shadow bg-gradient-to-br from-white to-emerald-50/20">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Approved Requests</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
          </div>
          <div class="mt-3 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-emerald-600">{{ stats.approved }}</span>
            <span class="text-xs text-slate-400 font-medium">compensated</span>
          </div>
          <div class="mt-2 text-xs text-slate-500">Extra time granted to student attempts</div>
        </div>

        <!-- Admin Overrides -->
        <div class="bg-white rounded-2xl border border-indigo-200/80 p-5 shadow-sm hover:shadow-md transition-shadow bg-gradient-to-br from-white to-indigo-50/20">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-indigo-700 uppercase tracking-wider">Admin Overrides</span>
            <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
          </div>
          <div class="mt-3 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-indigo-600">{{ stats.admin_override }}</span>
            <span class="text-xs text-slate-400 font-medium">super administrator</span>
          </div>
          <div class="mt-2 text-xs text-slate-500">Direct executive interventions</div>
        </div>

      </div>

      <!-- ── Filter Toolbar ─────────────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 flex flex-col md:flex-row items-center justify-between gap-4">
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto flex-1">
          <!-- Search Input -->
          <div class="relative w-full sm:w-80">
            <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search student, ID, exam..."
              class="w-full pl-10 pr-9 py-2 text-sm bg-slate-50/70 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-slate-800 placeholder-slate-400"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Status Dropdown -->
          <select
            v-model="statusFilter"
            class="w-full sm:w-auto px-3.5 py-2 text-sm bg-slate-50/70 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 text-slate-700 transition-all"
          >
            <option value="all">All Request Statuses</option>
            <option value="pending_approval">Pending Review</option>
            <option value="approved">Approved</option>
            <option value="admin_override">Admin Override</option>
            <option value="rejected">Rejected</option>
            <option value="extended_interruption">Extended Interruption</option>
          </select>

          <!-- Reset Filter Button -->
          <button
            v-if="searchQuery || statusFilter !== 'all'"
            @click="resetFilters"
            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 px-2 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors flex items-center gap-1 shrink-0"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Clear Filters
          </button>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-end">
          <span class="text-xs text-slate-500 font-medium">
            Showing <span class="font-bold text-slate-700">{{ recoveryRequests.length }}</span> of {{ pagination.total }} records
          </span>

          <select
            v-model="perPage"
            class="px-2.5 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          >
            <option :value="10">10 / page</option>
            <option :value="15">15 / page</option>
            <option :value="25">25 / page</option>
            <option :value="50">50 / page</option>
          </select>
        </div>

      </div>

      <!-- ── Loading Skeleton / State ───────────────────────────────────────── -->
      <div v-if="isLoadingRecovery" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-12 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 mb-4 animate-spin">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
        </div>
        <p class="text-base font-semibold text-slate-800">Loading Connection Recovery Requests</p>
        <p class="text-xs text-slate-400 mt-1">Retrieving verified examination interruption logs...</p>
      </div>

      <!-- ── Error State ────────────────────────────────────────────────────── -->
      <div v-else-if="recoveryError" class="bg-rose-50/60 border border-rose-200 rounded-2xl p-8 text-center text-rose-800">
        <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <p class="font-bold text-base">{{ recoveryError }}</p>
        <p class="text-xs text-rose-600 mt-1">An error occurred while communicating with the Wollo University backend API.</p>
        <button
          @click="fetchRecoveryRequests"
          class="mt-4 px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 text-white hover:bg-rose-700 transition-colors shadow-sm"
        >
          Try Again
        </button>
      </div>

      <!-- ── Empty State ────────────────────────────────────────────────────── -->
      <div v-else-if="recoveryRequests.length === 0" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 px-6 text-center">
        <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <h3 class="text-base font-bold text-slate-800">No Recovery Requests Found</h3>
        <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">
          {{ searchQuery || statusFilter !== 'all' ? 'No connection recovery requests matched your active search query or filter.' : 'There are currently no connection interruptions or extra time recovery requests registered.' }}
        </p>
        <button
          v-if="searchQuery || statusFilter !== 'all'"
          @click="resetFilters"
          class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors"
        >
          Reset Filters
        </button>
      </div>

      <!-- ── Recovery Requests Data Table ───────────────────────────────────── -->
      <div v-else class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-slate-200/80 bg-slate-50/70 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                <th class="py-3.5 pl-6 pr-4">Student</th>
                <th class="py-3.5 px-4">Exam Session</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4">Interruption Duration</th>
                <th class="py-3.5 px-4">Suggested Extra</th>
                <th class="py-3.5 px-4">Approved Extra</th>
                <th class="py-3.5 px-4">Instructor / Reviewer</th>
                <th class="py-3.5 pr-6 pl-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
              <template v-for="req in recoveryRequests" :key="req.id">
                <tr class="hover:bg-slate-50/70 transition-colors group">
                  
                  <!-- Student Column with Initials Avatar -->
                  <td class="py-4 pl-6 pr-4">
                    <div class="flex items-center gap-3">
                      <div
                        :class="getAvatarBg(req.student?.name || req.student_name)"
                        class="w-9 h-9 rounded-xl border flex items-center justify-center font-bold text-xs shrink-0 shadow-xs"
                      >
                        {{ getInitials(req.student?.name || req.student_name) }}
                      </div>
                      <div class="min-w-0">
                        <div class="font-semibold text-slate-900 group-hover:text-indigo-600 transition-colors truncate">
                          {{ req.student?.name || req.student_name || 'Unknown Student' }}
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-slate-400 mt-0.5">
                          <span class="font-mono text-slate-500">{{ req.student?.id_no || req.student_id || ('STU-' + (req.student?.id || req.user_id)) }}</span>
                          <span v-if="req.student?.email" class="truncate">• {{ req.student.email }}</span>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Exam Column -->
                  <td class="py-4 px-4">
                    <div class="font-medium text-slate-900 max-w-[220px] truncate" :title="req.exam?.title || req.exam_title">
                      {{ req.exam?.title || req.exam_title || 'Untitled Exam' }}
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                      <span v-if="req.exam?.course?.code" class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-100 text-slate-600">
                        {{ req.exam.course.code }}
                      </span>
                      <span class="text-xs text-slate-400">
                        Attempt #{{ req.attempt_id }}
                      </span>
                    </div>
                  </td>

                  <!-- Status Column -->
                  <td class="py-4 px-4 whitespace-nowrap">
                    <span
                      :class="getStatusBadge(req).badge"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-semibold"
                    >
                      <span :class="getStatusBadge(req).dot" class="w-1.5 h-1.5 rounded-full shrink-0"></span>
                      {{ getStatusBadge(req).label }}
                    </span>
                  </td>

                  <!-- Interruption Duration -->
                  <td class="py-4 px-4 whitespace-nowrap">
                    <div class="flex items-center gap-1.5 text-amber-700 font-bold font-mono">
                      <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      <span>{{ formatClock(req.interruption_seconds) }}</span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                      {{ formatDuration(req.interruption_seconds) }}
                    </div>
                  </td>

                  <!-- Suggested Extra -->
                  <td class="py-4 px-4 whitespace-nowrap">
                    <div class="text-slate-700 font-semibold font-mono">
                      +{{ formatClock(req.suggested_seconds) }}
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                      {{ formatDuration(req.suggested_seconds) }}
                    </div>
                  </td>

                  <!-- Approved Extra -->
                  <td class="py-4 px-4 whitespace-nowrap">
                    <div v-if="req.approved_seconds !== null && req.approved_seconds !== undefined">
                      <div class="text-emerald-700 font-bold font-mono flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        +{{ formatClock(req.approved_seconds) }}
                      </div>
                      <div class="text-[11px] text-emerald-600 mt-0.5">
                        {{ formatDuration(req.approved_seconds) }}
                      </div>
                    </div>
                    <span v-else class="text-xs text-slate-400 italic font-normal">
                      Awaiting review
                    </span>
                  </td>

                  <!-- Reviewer / Instructor Column -->
                  <td class="py-4 px-4">
                    <div class="text-xs text-slate-800 font-medium truncate max-w-[150px]">
                      {{ req.reviewer?.name || req.reviewed_by_name || req.instructor_name || '—' }}
                    </div>
                    <div v-if="req.override_by_name || req.overrider?.name" class="text-[11px] text-indigo-600 font-semibold mt-0.5">
                      Override: {{ req.override_by_name || req.overrider?.name }}
                    </div>
                  </td>

                  <!-- Actions Column -->
                  <td class="py-4 pr-6 pl-4 text-right whitespace-nowrap">
                    <div class="inline-flex items-center gap-1.5">
                      <!-- Details Toggle Button -->
                      <button
                        @click="toggleRowExpand(req.id)"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                        :title="expandedRowIds.has(req.id) ? 'Collapse Audit Details' : 'Expand Audit Details'"
                      >
                        <svg
                          class="w-4 h-4 transition-transform duration-200"
                          :class="{ 'rotate-180 text-indigo-600': expandedRowIds.has(req.id) }"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                      </button>

                      <!-- Override Action Button (Available only when attempt is eligible / in-progress) -->
                      <button
                        v-if="req.can_override || (!req.is_submitted && req.attempt_status === 'in_progress')"
                        @click="openOverride(req)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs hover:shadow transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>Override</span>
                      </button>

                      <!-- Attempt Finalized Indicator (When student already submitted/finished) -->
                      <span
                        v-else
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-500 border border-slate-200/80 text-[11px] font-semibold cursor-default"
                        :title="'Attempt was already submitted on ' + (req.submitted_at_formatted || 'an earlier date') + '. Extra time overrides can only be applied to active, unsubmitted attempts.'"
                      >
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Finalized</span>
                      </span>
                    </div>
                  </td>

                </tr>

                <!-- ── Expandable Audit Details Row ──────────────────────────── -->
                <tr v-if="expandedRowIds.has(req.id)" class="bg-indigo-50/30 border-y border-indigo-100/60">
                  <td colspan="8" class="p-5">
                    <div class="bg-white rounded-xl border border-indigo-100 p-4 shadow-xs space-y-4">
                      
                      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                          <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                          <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                            Incident Timeline & Attempt Synchronization Audit
                          </span>
                        </div>
                        <span class="text-xs font-mono text-slate-400">Request Record #{{ req.id }}</span>
                      </div>

                      <!-- Attempt Finalized Notice if already submitted -->
                      <div v-if="req.is_submitted || req.submitted_at_formatted" class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-700 flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-slate-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                          <span class="font-bold text-slate-800">Attempt Already Concluded</span>: This student attempt was submitted and finalized on <span class="font-semibold text-slate-900">{{ req.submitted_at_formatted || 'an earlier date' }}</span>. In online examination management, extra time compensation applies strictly to active, in-progress attempts experiencing real-time network interruptions.
                        </div>
                      </div>

                      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                        
                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                          <span class="text-slate-400 uppercase font-semibold text-[10px] block">Disconnected At</span>
                          <span class="font-semibold text-slate-800 mt-1 block font-mono">
                            {{ req.disconnected_at_formatted || formatDateTime(req.disconnected_at) }}
                          </span>
                        </div>

                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                          <span class="text-slate-400 uppercase font-semibold text-[10px] block">Reconnected At</span>
                          <span class="font-semibold text-slate-800 mt-1 block font-mono">
                            {{ req.reconnected_at_formatted || formatDateTime(req.reconnected_at) || 'In-Progress' }}
                          </span>
                        </div>

                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                          <span class="text-slate-400 uppercase font-semibold text-[10px] block">Original Deadline</span>
                          <span class="font-semibold text-slate-800 mt-1 block font-mono">
                            {{ req.original_deadline || '—' }}
                          </span>
                        </div>

                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                          <span class="text-slate-400 uppercase font-semibold text-[10px] block">Adjusted Deadline</span>
                          <span class="font-semibold text-emerald-700 mt-1 block font-mono">
                            {{ req.adjusted_deadline_formatted || formatDateTime(req.adjusted_deadline) }}
                          </span>
                        </div>

                      </div>

                      <!-- Reviewer / Override Notes Row -->
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs pt-1">
                        
                        <div class="p-3 bg-amber-50/50 rounded-lg border border-amber-200/50">
                          <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-amber-800 text-[11px]">Instructor Review</span>
                            <span class="text-[10px] text-amber-700">{{ req.reviewed_at || 'Pending review' }}</span>
                          </div>
                          <p class="text-slate-600">
                            {{ req.review_notes || 'No instructor notes recorded for this request.' }}
                          </p>
                          <div class="mt-2 text-[10px] text-slate-400 font-medium">
                            Reviewer: {{ req.reviewer?.name || req.reviewed_by_name || 'N/A' }}
                          </div>
                        </div>

                        <div class="p-3 bg-indigo-50/50 rounded-lg border border-indigo-200/50">
                          <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-indigo-800 text-[11px]">Super Admin Override</span>
                            <span class="text-[10px] text-indigo-700 font-semibold">{{ req.is_admin_override ? 'Active Override' : 'Standard' }}</span>
                          </div>
                          <p class="text-slate-600">
                            {{ req.override_reason || 'No executive override reason specified.' }}
                          </p>
                          <div class="mt-2 text-[10px] text-slate-400 font-medium">
                            Overridden by: {{ req.overrider?.name || req.override_by_name || 'None' }}
                          </div>
                        </div>

                      </div>

                    </div>
                  </td>
                </tr>

              </template>
            </tbody>
          </table>
        </div>

        <!-- ── Modern Pagination ──────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-slate-200/80 bg-slate-50/60">
          <div class="text-xs text-slate-500 font-medium">
            Page <span class="font-bold text-slate-700">{{ pagination.current_page }}</span> of {{ pagination.last_page }}
            ({{ pagination.total }} total recovery requests)
          </div>

          <div class="flex items-center gap-1.5">
            <button
              @click="currentPage = Math.max(1, currentPage - 1); fetchRecoveryRequests()"
              :disabled="currentPage <= 1 || isLoadingRecovery"
              class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              Previous
            </button>

            <button
              v-for="p in pagination.last_page"
              :key="p"
              @click="currentPage = p; fetchRecoveryRequests()"
              :class="currentPage === p
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
              class="w-8 h-8 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center cursor-pointer"
            >
              {{ p }}
            </button>

            <button
              @click="currentPage = Math.min(pagination.last_page, currentPage + 1); fetchRecoveryRequests()"
              :disabled="currentPage >= pagination.last_page || isLoadingRecovery"
              class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              Next
            </button>
          </div>
        </div>

      </div>

    </template>

    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <!-- ── TAB 2: EXAM SESSIONS & CONTROLS ───────────────────────────────────── -->
    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <template v-if="activeTab === 'control'">

      <!-- ── Exam Control KPI Cards ─────────────────────────────────────────── -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Managed Exams</span>
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
          </div>
          <div class="mt-3 text-2xl font-bold text-slate-900">{{ allExams.length }}</div>
          <div class="mt-2 text-xs text-slate-500">Exams on university catalog</div>
        </div>

        <div class="bg-white rounded-2xl border border-emerald-200/80 p-5 shadow-sm bg-gradient-to-br from-white to-emerald-50/20">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Live & Published</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
              <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-600"></span>
              </span>
            </div>
          </div>
          <div class="mt-3 text-2xl font-bold text-emerald-600">{{ liveExamsCount }}</div>
          <div class="mt-2 text-xs text-slate-500">Currently active for students</div>
        </div>

        <div class="bg-white rounded-2xl border border-amber-200/80 p-5 shadow-sm bg-gradient-to-br from-white to-amber-50/20">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Paused Sessions</span>
            <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
          </div>
          <div class="mt-3 text-2xl font-bold text-amber-600">{{ pausedExamsCount }}</div>
          <div class="mt-2 text-xs text-slate-500">Timers frozen platform-wide</div>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200/80 p-5 shadow-sm bg-gradient-to-br from-white to-rose-50/20">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Cancelled Exams</span>
            <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
          </div>
          <div class="mt-3 text-2xl font-bold text-rose-600">{{ cancelledExamsCount }}</div>
          <div class="mt-2 text-xs text-slate-500">Terminated by administration</div>
        </div>

      </div>

      <!-- ── Filter Toolbar ─────────────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 flex flex-col md:flex-row items-center justify-between gap-4">
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto flex-1">
          <div class="relative w-full sm:w-80">
            <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              v-model="examSearchQuery"
              type="text"
              placeholder="Search exam title, course, instructor..."
              class="w-full pl-10 pr-9 py-2 text-sm bg-slate-50/70 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-slate-800 placeholder-slate-400"
            />
            <button
              v-if="examSearchQuery"
              @click="examSearchQuery = ''"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <select
            v-model="examStatusFilter"
            class="w-full sm:w-auto px-3.5 py-2 text-sm bg-slate-50/70 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 text-slate-700 transition-all"
          >
            <option value="all">All Exam Statuses</option>
            <option value="published">Live / Published</option>
            <option value="paused">Paused</option>
            <option value="cancelled">Cancelled</option>
            <option value="completed">Completed</option>
            <option value="draft">Draft</option>
          </select>
        </div>

        <div class="text-xs text-slate-500 font-medium">
          Showing <span class="font-bold text-slate-700">{{ filteredExams.length }}</span> of {{ allExams.length }} exams
        </div>

      </div>

      <!-- ── Loading / Empty States ─────────────────────────────────────────── -->
      <div v-if="isLoadingExams" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-12 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 mb-4 animate-spin">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
        </div>
        <p class="text-base font-semibold text-slate-800">Loading Exam Sessions</p>
        <p class="text-xs text-slate-400 mt-1">Retrieving platform examinations for control...</p>
      </div>

      <div v-else-if="filteredExams.length === 0" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 px-6 text-center">
        <div class="w-16 h-16 rounded-2xl bg-slate-50 border border-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <h3 class="text-base font-bold text-slate-800">No Examinations Match Filter</h3>
        <p class="text-sm text-slate-500 mt-1">Try modifying your search query or status filter.</p>
      </div>

      <!-- ── Modern Exam Cards Grid ─────────────────────────────────────────── -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        
        <div
          v-for="exam in filteredExams"
          :key="exam.id"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition-all p-5 flex flex-col justify-between"
        >
          <div>
            <!-- Card Header: Status & Exam Code -->
            <div class="flex items-center justify-between gap-2 mb-3">
              <span
                :class="{
                  'bg-emerald-50 text-emerald-700 border-emerald-200/80': exam.status === 'published' && !exam.is_paused && !exam.is_cancelled,
                  'bg-amber-50 text-amber-700 border-amber-200/80': exam.status === 'paused' || exam.is_paused,
                  'bg-rose-50 text-rose-700 border-rose-200/80': exam.status === 'cancelled' || exam.is_cancelled,
                  'bg-slate-100 text-slate-700 border-slate-200': !['published','paused','cancelled'].includes(exam.status),
                }"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-bold"
              >
                <span
                  :class="{
                    'bg-emerald-500': exam.status === 'published' && !exam.is_paused && !exam.is_cancelled,
                    'bg-amber-500': exam.status === 'paused' || exam.is_paused,
                    'bg-rose-500': exam.status === 'cancelled' || exam.is_cancelled,
                    'bg-slate-500': !['published','paused','cancelled'].includes(exam.status),
                  }"
                  class="w-1.5 h-1.5 rounded-full"
                ></span>
                <span class="capitalize">
                  {{ exam.is_cancelled ? 'Cancelled' : exam.is_paused ? 'Paused' : exam.status }}
                </span>
              </span>

              <span class="font-mono text-xs font-semibold text-slate-500 bg-slate-50 px-2 py-0.5 rounded-md border border-slate-200/70">
                {{ exam.examCode || ('EXM-' + exam.id) }}
              </span>
            </div>

            <!-- Title & Course Details -->
            <h3 class="text-base font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">
              {{ exam.title }}
            </h3>
            
            <p class="text-xs text-slate-500 mt-1 flex items-center gap-1.5 flex-wrap">
              <span class="font-semibold text-slate-700">{{ exam.course || exam.course_name || 'Course' }}</span>
              <span v-if="exam.course_code || exam.courseCode" class="font-mono text-slate-400">({{ exam.course_code || exam.courseCode }})</span>
              <span class="text-slate-300">•</span>
              <span>{{ exam.department || 'General' }}</span>
            </p>

            <!-- Metadata Pills -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-100 text-xs">
              <div class="bg-slate-50/80 rounded-xl p-2.5 border border-slate-100">
                <span class="text-slate-400 text-[10px] uppercase font-bold block">Instructor</span>
                <span class="font-semibold text-slate-800 truncate block mt-0.5">
                  {{ exam.instructor || 'Unassigned' }}
                </span>
              </div>

              <div class="bg-slate-50/80 rounded-xl p-2.5 border border-slate-100">
                <span class="text-slate-400 text-[10px] uppercase font-bold block">Duration</span>
                <span class="font-semibold text-slate-800 font-mono block mt-0.5">
                  {{ exam.duration || (exam.duration_minutes + 'm') }}
                </span>
              </div>

              <div class="bg-slate-50/80 rounded-xl p-2.5 border border-slate-100 col-span-2 sm:col-span-1">
                <span class="text-slate-400 text-[10px] uppercase font-bold block">Logged Attempts</span>
                <span class="font-semibold text-slate-800 font-mono block mt-0.5">
                  {{ exam.attempts_count ?? 0 }} attempts
                </span>
              </div>
            </div>

            <!-- Cancellation Info Banner (if cancelled) -->
            <div v-if="exam.is_cancelled || exam.status === 'cancelled'" class="mt-3 p-3 bg-rose-50 border border-rose-200/80 rounded-xl text-xs text-rose-800">
              <div class="font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Cancelled by Administrator
              </div>
              <p class="text-rose-700 mt-1 italic">"{{ exam.cancellation_reason || 'No cancellation reason specified.' }}"</p>
            </div>
          </div>

          <!-- Card Actions Footer -->
          <div class="mt-5 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
            
            <button
              @click="filterRecoveryByExam(exam.title)"
              class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 cursor-pointer"
            >
              <span>View Related Requests</span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <div class="flex items-center gap-2">
              <!-- Pause / Resume Controls -->
              <button
                v-if="exam.status === 'published' && !exam.is_paused && !exam.is_cancelled"
                @click="openPauseExam(exam)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Pause Exam</span>
              </button>

              <button
                v-if="exam.status === 'paused' || exam.is_paused"
                @click="resumeExam(exam)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Resume Exam</span>
              </button>

              <!-- Cancel / Reinstate Controls -->
              <button
                v-if="!exam.is_cancelled && exam.status !== 'cancelled'"
                @click="openCancelExam(exam)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-rose-200 bg-white hover:bg-rose-50 text-rose-600 text-xs font-semibold transition-colors cursor-pointer"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                <span>Cancel</span>
              </button>

              <button
                v-if="exam.is_cancelled || exam.status === 'cancelled'"
                @click="reinstateExam(exam)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Reinstate</span>
              </button>
            </div>

          </div>

        </div>

      </div>

    </template>

    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <!-- ── MODAL 1: ADMINISTRATIVE OVERRIDE EXTRA TIME ───────────────────────── -->
    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showOverrideModal && overrideRequest"
          class="fixed inset-0 z-[500] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
          @click.self="showOverrideModal = false"
        >
          <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="text-base font-bold text-slate-900">Admin Extra Time Override</h3>
                  <p class="text-xs text-slate-500">Executive intervention for student examination recovery</p>
                </div>
              </div>

              <button
                @click="showOverrideModal = false"
                class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition-colors cursor-pointer"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-5">

              <!-- Context Card -->
              <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 space-y-3">
                <div class="flex items-center justify-between">
                  <div>
                    <div class="font-bold text-slate-900 text-sm">
                      {{ overrideRequest.student?.name || overrideRequest.student_name }}
                    </div>
                    <div class="text-xs text-slate-400 font-mono mt-0.5">
                      ID: {{ overrideRequest.student?.id_no || overrideRequest.student_id || 'N/A' }}
                    </div>
                  </div>
                  <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100 font-mono">
                    Attempt #{{ overrideRequest.attempt_id }}
                  </span>
                </div>

                <div class="text-xs text-slate-600 font-medium">
                  Exam: <span class="text-slate-900 font-bold">{{ overrideRequest.exam?.title || overrideRequest.exam_title }}</span>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/60 text-xs">
                  <div>
                    <span class="text-slate-400 text-[10px] uppercase font-semibold block">Logged Interruption</span>
                    <span class="font-bold text-amber-700 font-mono">
                      {{ formatClock(overrideRequest.interruption_seconds) }} ({{ formatDuration(overrideRequest.interruption_seconds) }})
                    </span>
                  </div>
                  <div>
                    <span class="text-slate-400 text-[10px] uppercase font-semibold block">Suggested Time</span>
                    <span class="font-bold text-slate-700 font-mono">
                      +{{ formatClock(overrideRequest.suggested_seconds) }} ({{ formatDuration(overrideRequest.suggested_seconds) }})
                    </span>
                  </div>
                </div>
              </div>

              <!-- Extra Time Configuration -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Extra Compensation Time (Seconds)
                  </label>
                  <span class="text-xs font-bold text-indigo-600 font-mono">
                    = {{ formatClock(overrideSeconds) }} ({{ formatDuration(overrideSeconds) }})
                  </span>
                </div>

                <div class="relative">
                  <input
                    v-model.number="overrideSeconds"
                    type="number"
                    min="0"
                    step="15"
                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-center text-lg font-bold font-mono text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                  />
                </div>

                <!-- Quick Preset Chips -->
                <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                  <span class="text-[11px] text-slate-400 mr-1">Presets:</span>
                  <button
                    type="button"
                    @click="setPresetSeconds(60)"
                    class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 font-medium transition-colors cursor-pointer"
                  >
                    +1 min
                  </button>
                  <button
                    type="button"
                    @click="setPresetSeconds(120)"
                    class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 font-medium transition-colors cursor-pointer"
                  >
                    +2 mins
                  </button>
                  <button
                    type="button"
                    @click="setPresetSeconds(300)"
                    class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 font-medium transition-colors cursor-pointer"
                  >
                    +5 mins
                  </button>
                  <button
                    type="button"
                    @click="matchInterruptionSeconds"
                    class="px-2.5 py-1 text-xs rounded-lg border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium transition-colors cursor-pointer"
                  >
                    Match Interruption
                  </button>
                  <button
                    type="button"
                    @click="setPresetSeconds(0)"
                    class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-400 hover:text-slate-600 font-medium transition-colors cursor-pointer ml-auto"
                  >
                    Reset (0s)
                  </button>
                </div>
              </div>

              <!-- Override Reason -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Administrative Reason <span class="text-rose-500">*</span>
                  </label>
                  <span class="text-[11px]" :class="overrideReason.length < 4 ? 'text-amber-600' : 'text-slate-400'">
                    {{ overrideReason.length }} / 500
                  </span>
                </div>
                <textarea
                  v-model="overrideReason"
                  rows="3"
                  maxlength="500"
                  placeholder="Required: Provide the official justification for this executive time override..."
                  class="w-full px-3.5 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-slate-800 placeholder-slate-400 resize-none"
                ></textarea>
              </div>

              <!-- Consequential Explanation Notice -->
              <div class="p-3 bg-indigo-50/70 border border-indigo-100 rounded-xl text-xs text-indigo-900 flex items-start gap-2.5">
                <svg class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="leading-relaxed">
                  This override sets extra compensation time absolutely on Attempt #{{ overrideRequest.attempt_id }}, extends the student's active deadline, and writes an immutable audit record to the university log.
                </div>
              </div>

              <!-- Error Alert -->
              <div v-if="overrideError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-medium">
                {{ overrideError }}
              </div>

            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3">
              <button
                @click="showOverrideModal = false"
                class="px-4 py-2 text-xs font-semibold rounded-xl text-slate-600 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer"
              >
                Cancel
              </button>

              <button
                @click="submitOverride"
                :disabled="isOverriding || overrideReason.trim().length < 4"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm hover:shadow transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
              >
                <svg v-if="isOverriding" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span>{{ isOverriding ? 'Applying Override...' : 'Confirm & Apply Override' }}</span>
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <!-- ── MODAL 2: CANCEL EXAM ──────────────────────────────────────────────── -->
    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showCancelModal && cancelExam"
          class="fixed inset-0 z-[500] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
          @click.self="showCancelModal = false"
        >
          <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
            
            <div class="px-6 py-5 border-b border-rose-100 flex items-center justify-between bg-rose-50/50">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
                <div>
                  <h3 class="text-base font-bold text-rose-900">Cancel Examination</h3>
                  <p class="text-xs text-rose-600">Immediate platform-wide examination cancellation</p>
                </div>
              </div>

              <button
                @click="showCancelModal = false"
                class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition-colors cursor-pointer"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <div class="p-6 space-y-4">
              <div class="p-4 bg-rose-50 border border-rose-200/80 rounded-2xl text-xs text-rose-800 leading-relaxed">
                <div class="font-bold flex items-center gap-2 text-rose-900 mb-1">
                  <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                  High Impact Administrative Action
                </div>
                Cancelling this examination will immediately halt all in-progress student attempts. A cancellation screen will be shown to students, but all submitted answers will be preserved.
              </div>

              <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 text-xs">
                <div class="text-slate-400 font-semibold uppercase text-[10px]">Target Examination</div>
                <div class="text-sm font-bold text-slate-800 mt-0.5">{{ cancelExam.title }}</div>
                <div class="text-slate-500 mt-0.5">{{ cancelExam.course }} • Instructor: {{ cancelExam.instructor }}</div>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                  Cancellation Reason <span class="text-rose-500">*</span>
                </label>
                <textarea
                  v-model="cancelReason"
                  rows="3"
                  placeholder="Required: Provide the official justification for cancelling this examination..."
                  class="w-full px-3.5 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all text-slate-800 placeholder-slate-400 resize-none"
                ></textarea>
              </div>

              <div v-if="cancelError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-medium">
                {{ cancelError }}
              </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3">
              <button
                @click="showCancelModal = false"
                class="px-4 py-2 text-xs font-semibold rounded-xl text-slate-600 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer"
              >
                Go Back
              </button>

              <button
                @click="submitCancelExam"
                :disabled="isCancelling || cancelReason.trim().length < 4"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
              >
                <svg v-if="isCancelling" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span>{{ isCancelling ? 'Cancelling...' : 'Confirm Cancellation' }}</span>
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <!-- ── MODAL 3: PAUSE EXAM ───────────────────────────────────────────────── -->
    <!-- ═════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showPauseModal && pauseExam"
          class="fixed inset-0 z-[500] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
          @click.self="showPauseModal = false"
        >
          <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
            
            <div class="px-6 py-5 border-b border-amber-100 flex items-center justify-between bg-amber-50/50">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                  <h3 class="text-base font-bold text-amber-900">Pause Examination</h3>
                  <p class="text-xs text-amber-700">Freeze student timers during active session</p>
                </div>
              </div>

              <button
                @click="showPauseModal = false"
                class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition-colors cursor-pointer"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <div class="p-6 space-y-4">
              <div class="p-4 bg-amber-50 border border-amber-200/80 rounded-2xl text-xs text-amber-800 leading-relaxed">
                <div class="font-bold flex items-center gap-2 text-amber-900 mb-1">
                  <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  Timer Compensation Notice
                </div>
                Pausing this examination freezes all student timers immediately. When you resume the exam later, the system will automatically compensate in-progress students with equivalent extra time.
              </div>

              <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 text-xs">
                <div class="text-slate-400 font-semibold uppercase text-[10px]">Exam Session</div>
                <div class="text-sm font-bold text-slate-800 mt-0.5">{{ pauseExam.title }}</div>
                <div class="text-slate-500 mt-0.5">{{ pauseExam.course }} • Instructor: {{ pauseExam.instructor }}</div>
              </div>

              <div v-if="pauseError" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-medium">
                {{ pauseError }}
              </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3">
              <button
                @click="showPauseModal = false"
                class="px-4 py-2 text-xs font-semibold rounded-xl text-slate-600 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer"
              >
                Go Back
              </button>

              <button
                @click="submitPauseExam"
                :disabled="isPausing"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-semibold rounded-xl bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
              >
                <svg v-if="isPausing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span>{{ isPausing ? 'Pausing...' : 'Confirm Pause' }}</span>
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateY(-12px) scale(0.96);
}
</style>
