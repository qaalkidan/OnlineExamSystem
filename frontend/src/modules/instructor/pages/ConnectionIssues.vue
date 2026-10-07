<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '../../../core/api/apiClient'
import InstructorRecoveryModal from '../components/recovery/InstructorRecoveryModal.vue'

const requests = ref<any[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)
const searchQuery = ref('')
const statusFilter = ref<string>('all')
const examFilter = ref<string>('all')
const currentPage = ref(1)
const perPage = 15

const showModal = ref(false)
const selectedRequest = ref<any | null>(null)

// ── Fetch ─────────────────────────────────────────────────────────────────────
const fetchRequests = async () => {
  isLoading.value = true
  error.value = null
  try {
    const res = await apiClient.get('/instructor/recovery-requests')
    requests.value = res.data.data ?? []
  } catch (err: any) {
    error.value = err.response?.data?.message ?? 'Failed to load recovery requests.'
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchRequests)

// ── Derived lists ──────────────────────────────────────────────────────────────
const allExams = computed(() => {
  const seen = new Set<number>()
  return requests.value
    .filter(r => r.exam && !seen.has(r.exam.id) && seen.add(r.exam.id))
    .map(r => ({ id: r.exam.id, title: r.exam.title }))
})

const filtered = computed(() =>
  requests.value.filter(r => {
    const matchSearch =
      !searchQuery.value ||
      (r.student?.name ?? '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (r.student?.username ?? '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (r.exam?.title ?? '').toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchStatus = statusFilter.value === 'all' || r.status === statusFilter.value
    const matchExam = examFilter.value === 'all' || String(r.exam?.id) === examFilter.value
    return matchSearch && matchStatus && matchExam
  })
)

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated = computed(() =>
  filtered.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage)
)

const statusConfig: Record<string, { badge: string; label: string }> = {
  pending_approval: { badge: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Pending Review' },
  approved: { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'Approved' },
  rejected: { badge: 'bg-red-50 text-red-700 border-red-200', label: 'Rejected' },
  extended_interruption: { badge: 'bg-slate-100 text-slate-600 border-slate-200', label: 'Extended Interruption' },
}

const formatDuration = (seconds: number | null) => {
  if (!seconds && seconds !== 0) return '—'
  if (seconds === 0) return '0s'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  if (m === 0) return `${s}s`
  if (s === 0) return `${m}m`
  return `${m}m ${s}s`
}

const formatDateTime = (dt: string | null) => {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('en-US', {
    month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

// ── Modal ──────────────────────────────────────────────────────────────────────
const openReview = (request: any) => {
  selectedRequest.value = request
  showModal.value = true
}

const handleReviewed = () => {
  fetchRequests()
}
</script>

<template>
  <div class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
          <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M2 2l20 20M8.464 8.464A5 5 0 006.11 12"/>
          </svg>
          Connection Issues
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Review and approve student connection recovery requests during exams.</p>
      </div>
      <button
        @click="fetchRequests"
        class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Refresh
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col sm:flex-row gap-3">
      <div class="flex-1 relative">
        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search student or exam…"
          class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          @input="currentPage = 1"
        />
      </div>
      <select
        v-model="statusFilter"
        @change="currentPage = 1"
        class="px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700"
      >
        <option value="all">All Statuses</option>
        <option value="pending_approval">Pending Review</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="extended_interruption">Extended Interruption</option>
      </select>
      <select
        v-model="examFilter"
        @change="currentPage = 1"
        class="px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700"
      >
        <option value="all">All Exams</option>
        <option v-for="exam in allExams" :key="exam.id" :value="String(exam.id)">
          {{ exam.title }}
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center py-20">
      <div class="flex flex-col items-center gap-3 text-slate-400">
        <svg class="w-8 h-8 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <span class="text-sm">Loading recovery requests…</span>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center text-red-700">
      <svg class="w-8 h-8 mx-auto mb-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <p class="font-medium">{{ error }}</p>
      <button @click="fetchRequests" class="mt-3 text-sm underline hover:no-underline">Try again</button>
    </div>

    <!-- Empty -->
    <div v-else-if="filtered.length === 0" class="bg-white rounded-2xl border border-slate-100 shadow-sm py-16 text-center text-slate-400">
      <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <p class="font-semibold text-slate-600">No recovery requests found</p>
      <p class="text-sm mt-1">Connection recovery requests from your exams will appear here.</p>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Student</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Exam</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Disconnected</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Reconnected</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Interruption</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Suggested</th>
              <th class="text-left px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Approved</th>
              <th class="text-right px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="req in paginated" :key="req.id" class="hover:bg-slate-50/60 transition-colors">
              <!-- Student -->
              <td class="px-5 py-3.5">
                <div class="font-semibold text-slate-800">{{ req.student?.name ?? '—' }}</div>
                <div class="text-xs text-slate-400">{{ req.student?.username ?? req.student?.email ?? '' }}</div>
              </td>
              <!-- Exam -->
              <td class="px-4 py-3.5">
                <div class="font-medium text-slate-700 max-w-[180px] truncate">{{ req.exam?.title ?? '—' }}</div>
                <div class="text-xs text-slate-400">{{ req.exam?.course?.name ?? '' }}</div>
              </td>
              <!-- Status -->
              <td class="px-4 py-3.5">
                <span
                  :class="(statusConfig[req.status] ?? statusConfig['pending_approval']).badge"
                  class="inline-flex items-center px-2.5 py-1 rounded-lg border text-xs font-semibold"
                >
                  {{ (statusConfig[req.status] ?? statusConfig['pending_approval']).label }}
                </span>
              </td>
              <!-- Disconnected At -->
              <td class="px-4 py-3.5 text-slate-600 text-xs">{{ formatDateTime(req.disconnected_at) }}</td>
              <!-- Reconnected At -->
              <td class="px-4 py-3.5 text-slate-600 text-xs">{{ formatDateTime(req.reconnected_at) }}</td>
              <!-- Interruption -->
              <td class="px-4 py-3.5 font-semibold text-amber-700">{{ formatDuration(req.interruption_seconds) }}</td>
              <!-- Suggested -->
              <td class="px-4 py-3.5 text-slate-600">{{ formatDuration(req.suggested_seconds) }}</td>
              <!-- Approved -->
              <td class="px-4 py-3.5">
                <span :class="req.approved_seconds ? 'text-emerald-700 font-semibold' : 'text-slate-400'">
                  {{ req.approved_seconds ? formatDuration(req.approved_seconds) : '—' }}
                </span>
              </td>
              <!-- Action -->
              <td class="px-5 py-3.5 text-right">
                <button
                  v-if="req.status === 'pending_approval'"
                  @click="openReview(req)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition-colors"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                  Review
                </button>
                <span v-else class="text-xs text-slate-400">Reviewed</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 bg-slate-50">
        <p class="text-xs text-slate-500">
          Showing {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, filtered.length) }} of {{ filtered.length }}
        </p>
        <div class="flex gap-1">
          <button
            v-for="page in totalPages" :key="page"
            @click="currentPage = page"
            :class="currentPage === page ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
            class="w-8 h-8 rounded-lg text-xs font-semibold transition-colors"
          >{{ page }}</button>
        </div>
      </div>
    </div>

    <!-- Review Modal -->
    <InstructorRecoveryModal
      :show="showModal"
      :request="selectedRequest"
      @close="showModal = false"
      @reviewed="handleReviewed"
    />
  </div>
</template>
