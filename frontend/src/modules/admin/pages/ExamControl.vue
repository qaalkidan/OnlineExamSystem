<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '../../../core/api/apiClient'

// ── State ──────────────────────────────────────────────────────────────────────
const recoveryRequests = ref<any[]>([])
const isLoadingRecovery = ref(true)
const recoveryError = ref<string | null>(null)

const activeTab = ref<'recovery' | 'control'>('recovery')
const searchQuery = ref('')
const statusFilter = ref('all')
const currentPage = ref(1)
const perPage = 15

// Cancel modal state
const showCancelModal = ref(false)
const cancelExam = ref<any | null>(null)
const cancelReason = ref('')
const isCancelling = ref(false)
const cancelError = ref<string | null>(null)

// Pause modal state
const showPauseModal = ref(false)
const pauseExam = ref<any | null>(null)
const isPausing = ref(false)
const pauseError = ref<string | null>(null)

// Override modal state
const showOverrideModal = ref(false)
const overrideRequest = ref<any | null>(null)
const overrideSeconds = ref<number>(0)
const overrideReason = ref('')
const isOverriding = ref(false)
const overrideError = ref<string | null>(null)

// Toast
const toast = ref<{ message: string; type: 'success' | 'error' } | null>(null)
const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  toast.value = { message, type }
  setTimeout(() => { toast.value = null }, 4000)
}

// ── Fetch ──────────────────────────────────────────────────────────────────────
const fetchRecoveryRequests = async () => {
  isLoadingRecovery.value = true
  recoveryError.value = null
  try {
    const res = await apiClient.get('/admin/recovery-requests')
    recoveryRequests.value = res.data.data ?? []
  } catch (err: any) {
    recoveryError.value = err.response?.data?.message ?? 'Failed to load recovery requests.'
  } finally {
    isLoadingRecovery.value = false
  }
}

onMounted(fetchRecoveryRequests)

// ── Computed ───────────────────────────────────────────────────────────────────
const filteredRecovery = computed(() =>
  recoveryRequests.value.filter(r => {
    const q = searchQuery.value.toLowerCase()
    const matchSearch = !q ||
      (r.student?.name ?? '').toLowerCase().includes(q) ||
      (r.exam?.title ?? '').toLowerCase().includes(q)
    const matchStatus = statusFilter.value === 'all' || r.status === statusFilter.value
    return matchSearch && matchStatus
  })
)

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecovery.value.length / perPage)))
const paginated = computed(() =>
  filteredRecovery.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage)
)

const statusConfig: Record<string, { badge: string; label: string }> = {
  pending_approval: { badge: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Pending' },
  approved: { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Approved' },
  rejected: { badge: 'bg-red-50 text-red-700 border-red-200', label: 'Rejected' },
  admin_override: { badge: 'bg-indigo-50 text-indigo-700 border-indigo-200', label: 'Admin Override' },
  extended_interruption: { badge: 'bg-slate-100 text-slate-600 border-slate-200', label: 'Extended' },
}

const formatDuration = (seconds: number | null) => {
  if (!seconds && seconds !== 0) return '—'
  if (seconds === 0) return '0s'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return m === 0 ? `${s}s` : s === 0 ? `${m}m` : `${m}m ${s}s`
}

const formatDateTime = (dt: string | null) => {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ── Override ───────────────────────────────────────────────────────────────────
const openOverride = (req: any) => {
  overrideRequest.value = req
  overrideSeconds.value = req.suggested_seconds ?? 0
  overrideReason.value = ''
  overrideError.value = null
  showOverrideModal.value = true
}

const submitOverride = async () => {
  if (!overrideReason.value.trim()) {
    overrideError.value = 'Override reason is required.'
    return
  }
  isOverriding.value = true
  overrideError.value = null
  try {
    await apiClient.post(`/admin/recovery-requests/${overrideRequest.value.id}/override`, {
      approved_seconds: overrideSeconds.value,
      override_reason: overrideReason.value,
    })
    showOverrideModal.value = false
    showToast('Override applied successfully.')
    fetchRecoveryRequests()
  } catch (err: any) {
    overrideError.value = err.response?.data?.message ?? 'Failed to apply override.'
  } finally {
    isOverriding.value = false
  }
}

// ── Cancel ─────────────────────────────────────────────────────────────────────
const openCancelExam = (exam: any) => {
  cancelExam.value = exam
  cancelReason.value = ''
  cancelError.value = null
  showCancelModal.value = true
}

const submitCancelExam = async () => {
  if (!cancelReason.value.trim()) {
    cancelError.value = 'Cancellation reason is required.'
    return
  }
  isCancelling.value = true
  cancelError.value = null
  try {
    await apiClient.post(`/admin/exams/${cancelExam.value.exam_id ?? cancelExam.value.exam?.id}/cancel`, {
      reason: cancelReason.value,
    })
    showCancelModal.value = false
    showToast('Exam cancelled. All in-progress students have been notified.')
    fetchRecoveryRequests()
  } catch (err: any) {
    cancelError.value = err.response?.data?.message ?? 'Failed to cancel exam.'
  } finally {
    isCancelling.value = false
  }
}

// ── Pause / Resume ─────────────────────────────────────────────────────────────
const openPauseExam = (exam: any) => {
  pauseExam.value = exam
  pauseError.value = null
  showPauseModal.value = true
}

const submitPauseExam = async () => {
  isPausing.value = true
  pauseError.value = null
  try {
    await apiClient.post(`/admin/exams/${pauseExam.value.exam_id ?? pauseExam.value.exam?.id}/pause`)
    showPauseModal.value = false
    showToast('Exam paused. Students will see a pause screen.')
    fetchRecoveryRequests()
  } catch (err: any) {
    pauseError.value = err.response?.data?.message ?? 'Failed to pause exam.'
  } finally {
    isPausing.value = false
  }
}

const resumeExam = async (examId: number) => {
  try {
    await apiClient.post(`/admin/exams/${examId}/resume`)
    showToast('Exam resumed. Student timers have been adjusted.')
    fetchRecoveryRequests()
  } catch (err: any) {
    showToast(err.response?.data?.message ?? 'Failed to resume exam.', 'error')
  }
}

const reinstateExam = async (examId: number) => {
  if (!confirm('Reinstate this cancelled exam? Students will be able to continue.')) return
  try {
    await apiClient.post(`/admin/exams/${examId}/reinstate`)
    showToast('Exam reinstated.')
    fetchRecoveryRequests()
  } catch (err: any) {
    showToast(err.response?.data?.message ?? 'Failed to reinstate exam.', 'error')
  }
}

// ── Unique exams from recovery requests (for control panel) ────────────────────
const uniqueExams = computed(() => {
  const seen = new Set<number>()
  return recoveryRequests.value
    .filter(r => r.exam && !seen.has(r.exam.id) && seen.add(r.exam.id))
    .map(r => r.exam)
})
</script>

<template>
  <div class="space-y-6">

    <!-- Toast -->
    <Transition name="toast-slide">
      <div
        v-if="toast"
        :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'"
        class="fixed top-6 right-6 z-[500] px-5 py-3 rounded-xl text-white text-sm font-medium shadow-xl flex items-center gap-2"
      >
        <svg v-if="toast.type === 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ toast.message }}
      </div>
    </Transition>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
          <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
          Exam Control Centre
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Monitor connection recovery requests and control live exams.</p>
      </div>
      <button @click="fetchRecoveryRequests" class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Refresh
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-slate-100 rounded-xl p-1 w-fit">
      <button
        v-for="tab in [{ key: 'recovery', label: 'Recovery Requests' }, { key: 'control', label: 'Exam Control' }]"
        :key="tab.key"
        @click="activeTab = tab.key as any"
        :class="activeTab === tab.key ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
        class="px-5 py-2 rounded-lg text-sm font-semibold transition-all"
      >
        {{ tab.label }}
        <span v-if="tab.key === 'recovery' && recoveryRequests.filter(r => r.status === 'pending_approval').length > 0"
          class="ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-white text-[10px] font-bold">
          {{ recoveryRequests.filter(r => r.status === 'pending_approval').length }}
        </span>
      </button>
    </div>

    <!-- ── Recovery Requests Tab ─────────────────────────────────────────────── -->
    <template v-if="activeTab === 'recovery'">

      <!-- Filters -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="searchQuery" type="text" placeholder="Search student or exam…"
            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @input="currentPage = 1" />
        </div>
        <select v-model="statusFilter" @change="currentPage = 1"
          class="px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700">
          <option value="all">All Statuses</option>
          <option value="pending_approval">Pending Review</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="admin_override">Admin Override</option>
          <option value="extended_interruption">Extended Interruption</option>
        </select>
      </div>

      <!-- Loading -->
      <div v-if="isLoadingRecovery" class="flex items-center justify-center py-16 text-slate-400">
        <svg class="w-7 h-7 animate-spin text-indigo-500 mr-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        Loading…
      </div>

      <div v-else-if="recoveryError" class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center text-red-700">
        <p class="font-medium">{{ recoveryError }}</p>
        <button @click="fetchRecoveryRequests" class="mt-2 text-sm underline">Retry</button>
      </div>

      <div v-else-if="filteredRecovery.length === 0" class="bg-white rounded-2xl border border-slate-100 shadow-sm py-16 text-center text-slate-400">
        <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p class="font-semibold text-slate-600">No recovery requests</p>
      </div>

      <div v-else class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50">
                <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Student</th>
                <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Exam</th>
                <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Interruption</th>
                <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Suggested</th>
                <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Approved</th>
                <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Instructor</th>
                <th class="text-right px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Admin Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="req in paginated" :key="req.id" class="hover:bg-slate-50/60 transition-colors">
                <td class="px-5 py-3.5">
                  <div class="font-semibold text-slate-800">{{ req.student?.name || req.student_name || '—' }}</div>
                  <div class="text-xs text-slate-400">{{ req.student?.username || req.student_id || '' }}</div>
                </td>
                <td class="px-4 py-3.5">
                  <div class="font-medium text-slate-700 max-w-[160px] truncate">{{ req.exam?.title || req.exam_title || '—' }}</div>
                </td>
                <td class="px-4 py-3.5">
                  <span :class="(statusConfig[req.status] ?? statusConfig.pending_approval).badge"
                    class="inline-flex items-center px-2.5 py-1 rounded-lg border text-xs font-semibold">
                    {{ (statusConfig[req.status] ?? statusConfig.pending_approval).label }}
                  </span>
                </td>
                <td class="px-4 py-3.5 font-semibold text-amber-700">{{ formatDuration(req.interruption_seconds) }}</td>
                <td class="px-4 py-3.5 text-slate-600">{{ formatDuration(req.suggested_seconds) }}</td>
                <td class="px-4 py-3.5">
                  <span :class="req.approved_seconds ? 'text-emerald-700 font-semibold' : 'text-slate-400'">
                    {{ req.approved_seconds ? formatDuration(req.approved_seconds) : '—' }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-slate-500 text-xs">{{ req.reviewer?.name ?? '—' }}</td>
                <td class="px-5 py-3.5 text-right">
                  <button @click="openOverride(req)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Override
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 bg-slate-50">
          <p class="text-xs text-slate-500">
            Showing {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, filteredRecovery.length) }} of {{ filteredRecovery.length }}
          </p>
          <div class="flex gap-1">
            <button v-for="p in totalPages" :key="p" @click="currentPage = p"
              :class="currentPage === p ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
              class="w-8 h-8 rounded-lg text-xs font-semibold transition-colors">{{ p }}</button>
          </div>
        </div>
      </div>
    </template>

    <!-- ── Exam Control Tab ──────────────────────────────────────────────────── -->
    <template v-if="activeTab === 'control'">
      <div v-if="uniqueExams.length === 0" class="bg-white rounded-2xl border border-slate-100 shadow-sm py-16 text-center text-slate-400">
        <p class="font-semibold text-slate-600">No active exams</p>
        <p class="text-sm mt-1">Exams with connection recovery requests will appear here for control.</p>
      </div>
      <div v-else class="grid gap-4">
        <div
          v-for="exam in uniqueExams"
          :key="exam.id"
          class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
        >
          <div>
            <p class="font-bold text-slate-800">{{ exam.title }}</p>
            <p class="text-sm text-slate-500">{{ exam.course?.name ?? '' }} — {{ exam.department ?? '' }}</p>
            <div class="flex items-center gap-2 mt-2">
              <span :class="{
                  'bg-emerald-50 text-emerald-700 border-emerald-200': exam.status === 'published',
                  'bg-amber-50 text-amber-700 border-amber-200': exam.status === 'paused',
                  'bg-red-50 text-red-700 border-red-200': exam.status === 'cancelled',
                  'bg-slate-100 text-slate-600 border-slate-200': !['published','paused','cancelled'].includes(exam.status),
                }"
                class="inline-flex px-2.5 py-0.5 rounded-lg border text-xs font-semibold capitalize">
                {{ exam.status }}
              </span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <!-- Pause / Resume -->
            <button
              v-if="exam.status === 'published'"
              @click="openPauseExam({ exam_id: exam.id, exam })"
              class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-xl bg-amber-500 text-white hover:bg-amber-600 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Pause Exam
            </button>
            <button
              v-if="exam.status === 'paused'"
              @click="resumeExam(exam.id)"
              class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Resume Exam
            </button>
            <!-- Cancel -->
            <button
              v-if="exam.status !== 'cancelled'"
              @click="openCancelExam({ exam_id: exam.id, exam })"
              class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
              Cancel Exam
            </button>
            <!-- Reinstate -->
            <button
              v-if="exam.status === 'cancelled'"
              @click="reinstateExam(exam.id)"
              class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              Reinstate
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- ── Override Modal ──────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showOverrideModal && overrideRequest" class="fixed inset-0 z-[300] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
          <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50">
              <h2 class="text-base font-bold text-slate-800">Admin Override — Extra Time</h2>
              <button @click="showOverrideModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="p-6 space-y-4">
              <div class="bg-slate-50 rounded-xl p-4 text-sm">
                <p class="font-semibold text-slate-700">{{ overrideRequest.student?.name }}</p>
                <p class="text-slate-500">{{ overrideRequest.exam?.title }}</p>
                <div class="flex gap-4 mt-2 text-xs text-slate-500">
                  <span>Interruption: <span class="font-semibold text-amber-700">{{ formatDuration(overrideRequest.interruption_seconds) }}</span></span>
                  <span>Suggested: <span class="font-semibold">{{ formatDuration(overrideRequest.suggested_seconds) }}</span></span>
                </div>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Override Extra Time (seconds)</label>
                <input v-model.number="overrideSeconds" type="number" min="0" step="30"
                  class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 text-center" />
                <p class="text-xs text-slate-400 mt-1 text-center">= {{ formatDuration(overrideSeconds) }} — sets extra time absolutely (not additive)</p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Override Reason <span class="text-red-500">*</span></label>
                <textarea v-model="overrideReason" rows="2"
                  class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-slate-800 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder-slate-400"
                  placeholder="Required: explain the reason for admin override…"></textarea>
              </div>
              <div v-if="overrideError" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">{{ overrideError }}</div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50">
              <button @click="showOverrideModal = false" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">Cancel</button>
              <button @click="submitOverride" :disabled="isOverriding"
                class="px-5 py-2 text-sm font-semibold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 transition-colors disabled:opacity-50 flex items-center gap-2">
                <svg v-if="isOverriding" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Apply Override
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Cancel Modal ─────────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showCancelModal && cancelExam" class="fixed inset-0 z-[300] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
          <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-red-50">
              <h2 class="text-base font-bold text-red-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                Cancel Exam
              </h2>
              <button @click="showCancelModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-red-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="p-6 space-y-4">
              <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm">
                <p class="font-semibold text-red-700">⚠ This action will immediately cancel the exam for all students.</p>
                <p class="text-red-600 mt-1 text-xs">Students who are currently taking the exam will see a cancellation screen. Their submitted answers are preserved.</p>
              </div>
              <p class="text-sm text-slate-700 font-medium">Exam: <span class="font-bold">{{ cancelExam.exam?.title }}</span></p>
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Cancellation Reason <span class="text-red-500">*</span></label>
                <textarea v-model="cancelReason" rows="3"
                  class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-slate-800 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-red-400 placeholder-slate-400"
                  placeholder="Required: why is this exam being cancelled?"></textarea>
              </div>
              <div v-if="cancelError" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">{{ cancelError }}</div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50">
              <button @click="showCancelModal = false" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">Go Back</button>
              <button @click="submitCancelExam" :disabled="isCancelling"
                class="px-5 py-2 text-sm font-semibold rounded-xl bg-red-600 text-white hover:bg-red-700 transition-colors disabled:opacity-50 flex items-center gap-2">
                <svg v-if="isCancelling" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Confirm Cancel Exam
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Pause Modal ─────────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showPauseModal && pauseExam" class="fixed inset-0 z-[300] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
          <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-amber-50">
              <h2 class="text-base font-bold text-amber-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Pause Exam
              </h2>
              <button @click="showPauseModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-amber-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="p-6 space-y-4">
              <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm">
                <p class="font-semibold text-amber-700">This will pause the exam for all in-progress students.</p>
                <p class="text-amber-600 mt-1 text-xs">Students will see a pause screen. Their timers will be extended by the pause duration when you resume.</p>
              </div>
              <p class="text-sm text-slate-700 font-medium">Exam: <span class="font-bold">{{ pauseExam.exam?.title }}</span></p>
              <div v-if="pauseError" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">{{ pauseError }}</div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50">
              <button @click="showPauseModal = false" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition-colors">Go Back</button>
              <button @click="submitPauseExam" :disabled="isPausing"
                class="px-5 py-2 text-sm font-semibold rounded-xl bg-amber-500 text-white hover:bg-amber-600 transition-colors disabled:opacity-50 flex items-center gap-2">
                <svg v-if="isPausing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Confirm Pause
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.toast-slide-enter-active, .toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from, .toast-slide-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
