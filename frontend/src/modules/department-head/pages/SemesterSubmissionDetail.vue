<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'

const route = useRoute()
const router = useRouter()

const submissionId = route.params.id as string

const isLoading = ref(true)
const isSubmittingAction = ref(false)
const isExporting = ref(false)
const searchQuery = ref('')
const selectedDepartment = ref('All Departments')
const selectedStatus = ref('All Statuses')
const selectedSemester = ref('2025/2026 — Second Semester')
const openMenuId = ref<number | null>(null)

// Toast notification state
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'success'
})

const showToast = (message: string, type: 'success' | 'error' | 'info' = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

// Modal States
const isGuidelinesModalOpen = ref(false)
const isExportModalOpen = ref(false)

const reviewModal = ref<{
  open: boolean
  instructor: any | null
}>({
  open: false,
  instructor: null,
})

const correctionModal = ref<{
  open: boolean
  instructor: any | null
  remarks: string
}>({
  open: false,
  instructor: null,
  remarks: ''
})

const rejectModal = ref<{
  open: boolean
  instructor: any | null
  remarks: string
}>({
  open: false,
  instructor: null,
  remarks: ''
})

const reopenModal = ref<{
  open: boolean
  instructor: any | null
  reason: string
}>({
  open: false,
  instructor: null,
  reason: '',
})

// Summary Stats
const semesterInfo = ref({
  academicYear: '2025/2026',
  semester: 'Second Semester',
  department: 'Computer Science',
  pendingReview: 0,
  approved: 0,
  correctionRequired: 0,
  rejected: 0,
  notSubmitted: 0,
  total: 0,
})

// Real Instructors & Submissions
const instructors = ref<any[]>([])
const departmentsList = ref<string[]>([
  'All Departments',
  'Computer Science',
  'Software Engineering',
  'Information Technology',
  'Information Systems'
])

const semestersList = ref<string[]>([
  '2025/2026 — Second Semester',
  '2025/2026 — First Semester',
  '2024/2025 — Second Semester',
  '2024/2025 — First Semester',
])

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(10)

// Fetch Data from Backend
const fetchSubmissions = async () => {
  isLoading.value = true
  try {
    const params: any = {
      status: 'All Instructors'
    }

    if (submissionId && submissionId !== 'all') {
      params.year_level = submissionId
    }

    if (selectedDepartment.value && selectedDepartment.value !== 'All Departments') {
      params.department = selectedDepartment.value
    } else {
      params.department = 'All Departments'
    }

    if (selectedSemester.value) {
      const parts = selectedSemester.value.split('—').map(s => s.trim())
      if (parts[0]) params.academic_year = parts[0]
      if (parts[1]) params.semester = parts[1]
    }

    const res = await apiClient.get('/dept-head/semester-submissions/details', { params })
    if (res.data) {
      instructors.value = res.data.instructors || []
      if (res.data.semester_info) {
        semesterInfo.value = {
          ...semesterInfo.value,
          ...res.data.semester_info
        }
      }
      if (res.data.departments && res.data.departments.length > 0) {
        departmentsList.value = ['All Departments', ...res.data.departments]
      }
      if (res.data.semesters && res.data.semesters.length > 0) {
        semestersList.value = res.data.semesters
      }
    }
  } catch (error) {
    console.error('Failed to fetch semester submissions:', error)
    showToast('Failed to load semester submissions from server.', 'error')
  } finally {
    isLoading.value = false
  }
}

const syncRealData = async () => {
  await fetchSubmissions()
  showToast('Live semester submission records synchronized!', 'success')
}

onMounted(() => {
  fetchSubmissions()
  // Global click listener to close dropdowns
  window.addEventListener('click', closeAllMenus)
})

const closeAllMenus = () => {
  openMenuId.value = null
}

// Watch filters to refresh
watch([selectedDepartment, selectedSemester], () => {
  currentPage.value = 1
  fetchSubmissions()
})

const filteredInstructors = computed(() => {
  return instructors.value.filter(inst => {
    const q = searchQuery.value.toLowerCase().trim()
    const matchesSearch = !q
      || inst.name?.toLowerCase().includes(q)
      || inst.email?.toLowerCase().includes(q)
      || inst.department?.toLowerCase().includes(q)
      || inst.course?.toLowerCase().includes(q)
      || inst.course_code?.toLowerCase().includes(q)
      || inst.section?.toLowerCase().includes(q)

    const matchesDept = selectedDepartment.value === 'All Departments'
      || inst.department?.toLowerCase() === selectedDepartment.value.toLowerCase()

    let matchesStatus = true
    if (selectedStatus.value === 'All Statuses' || selectedStatus.value === 'All Submissions') {
      matchesStatus = !!inst.is_submitted
    } else if (selectedStatus.value === 'All Instructors') {
      matchesStatus = true
    } else if (selectedStatus.value === 'Not Submitted') {
      matchesStatus = !inst.is_submitted
    } else {
      matchesStatus = inst.status === selectedStatus.value
    }

    return matchesSearch && matchesDept && matchesStatus
  })
})

const totalPages = computed(() => {
  return Math.ceil(filteredInstructors.value.length / itemsPerPage.value) || 1
})

const paginatedInstructors = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return filteredInstructors.value.slice(start, start + itemsPerPage.value)
})

const goToPage = (p: number) => {
  if (p >= 1 && p <= totalPages.value) {
    currentPage.value = p
  }
}

// Styling Helpers
const getStatusBadge = (status: string) => {
  if (status === 'Approved') return 'bg-emerald-50 text-emerald-600 border-emerald-200'
  if (status === 'Pending') return 'bg-amber-50 text-amber-600 border-amber-200'
  if (status === 'Under Review') return 'bg-blue-50 text-blue-600 border-blue-200'
  if (status === 'Correction Required') return 'bg-orange-50 text-orange-600 border-orange-200'
  if (status === 'Rejected') return 'bg-rose-50 text-rose-600 border-rose-200'
  if (status === 'Reopened') return 'bg-cyan-50 text-cyan-700 border-cyan-200'
  if (status === 'Not Submitted') return 'bg-slate-100 text-slate-500 border-slate-200'
  return 'bg-slate-50 text-slate-500 border-slate-200'
}

const toggleMenu = (id: number) => {
  openMenuId.value = openMenuId.value === id ? null : id
}

// Open Review Detail Modal
const openReviewModal = (inst: any) => {
  openMenuId.value = null
  reviewModal.value = {
    open: true,
    instructor: inst,
  }
}

// Functional Actions (Persisting to backend)
const approveSubmission = async (inst: any) => {
  openMenuId.value = null
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id
    const res = await apiClient.put(`/dept-head/semester-submissions/${targetId}/status`, {
      status: 'approved',
      academic_year: semesterInfo.value.academicYear,
      semester: semesterInfo.value.semester,
    })

    const prevStatus = inst.status
    inst.status = 'Approved'
    inst.raw_status = 'approved'
    if (res.data?.submission?.approved_at) {
      inst.submitted = res.data.submission.approved_at.replace(' ', '\n')
    }

    // Refresh summary counts
    if (prevStatus !== 'Approved') {
      semesterInfo.value.approved++
      if (prevStatus === 'Pending' && semesterInfo.value.pendingReview > 0) {
        semesterInfo.value.pendingReview--
      } else if (prevStatus === 'Correction Required' && semesterInfo.value.correctionRequired > 0) {
        semesterInfo.value.correctionRequired--
      }
    }

    if (reviewModal.value.open && reviewModal.value.instructor?.id === inst.id) {
      reviewModal.value.instructor.status = 'Approved'
      reviewModal.value.instructor.raw_status = 'approved'
    }

    showToast(`Semester submission for ${inst.name} approved successfully!`, 'success')
  } catch (error) {
    console.error('Failed to approve submission:', error)
    showToast('Failed to approve submission. Please try again.', 'error')
  } finally {
    isSubmittingAction.value = false
  }
}

const openCorrectionModal = (inst: any) => {
  openMenuId.value = null
  correctionModal.value = {
    open: true,
    instructor: inst,
    remarks: inst.remarks || ''
  }
}

const submitCorrection = async () => {
  if (!correctionModal.value.instructor) return
  const inst = correctionModal.value.instructor
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id
    await apiClient.put(`/dept-head/semester-submissions/${targetId}/status`, {
      status: 'correction_required',
      remarks: correctionModal.value.remarks || 'Please check and revise semester examination records.',
      academic_year: semesterInfo.value.academicYear,
      semester: semesterInfo.value.semester,
    })

    const prevStatus = inst.status
    inst.status = 'Correction Required'
    inst.raw_status = 'correction_required'
    inst.remarks = correctionModal.value.remarks

    // Refresh summary counts
    if (prevStatus !== 'Correction Required') {
      semesterInfo.value.correctionRequired++
      if (prevStatus === 'Pending' && semesterInfo.value.pendingReview > 0) {
        semesterInfo.value.pendingReview--
      } else if (prevStatus === 'Approved' && semesterInfo.value.approved > 0) {
        semesterInfo.value.approved--
      }
    }

    if (reviewModal.value.open && reviewModal.value.instructor?.id === inst.id) {
      reviewModal.value.instructor.status = 'Correction Required'
      reviewModal.value.instructor.raw_status = 'correction_required'
      reviewModal.value.instructor.remarks = correctionModal.value.remarks
    }

    correctionModal.value.open = false
    showToast(`Correction requested for ${inst.name}.`, 'info')
  } catch (error) {
    console.error('Failed to request correction:', error)
    showToast('Failed to request correction.', 'error')
  } finally {
    isSubmittingAction.value = false
  }
}

const openRejectModal = (inst: any) => {
  openMenuId.value = null
  rejectModal.value = {
    open: true,
    instructor: inst,
    remarks: inst.remarks || ''
  }
}

const submitReject = async () => {
  if (!rejectModal.value.instructor) return
  const inst = rejectModal.value.instructor
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id
    await apiClient.put(`/dept-head/semester-submissions/${targetId}/status`, {
      status: 'rejected',
      remarks: rejectModal.value.remarks || 'Semester submission rejected by department head.',
      academic_year: semesterInfo.value.academicYear,
      semester: semesterInfo.value.semester,
    })

    const prevStatus = inst.status
    inst.status = 'Rejected'
    inst.raw_status = 'rejected'
    inst.remarks = rejectModal.value.remarks

    // Refresh summary counts
    if (prevStatus !== 'Rejected') {
      if (prevStatus === 'Pending' && semesterInfo.value.pendingReview > 0) {
        semesterInfo.value.pendingReview--
      } else if (prevStatus === 'Approved' && semesterInfo.value.approved > 0) {
        semesterInfo.value.approved--
      } else if (prevStatus === 'Correction Required' && semesterInfo.value.correctionRequired > 0) {
        semesterInfo.value.correctionRequired--
      }
    }

    if (reviewModal.value.open && reviewModal.value.instructor?.id === inst.id) {
      reviewModal.value.instructor.status = 'Rejected'
      reviewModal.value.instructor.raw_status = 'rejected'
      reviewModal.value.instructor.remarks = rejectModal.value.remarks
    }

    rejectModal.value.open = false
    showToast(`Submission for ${inst.name} has been rejected.`, 'info')
  } catch (error) {
    console.error('Failed to reject submission:', error)
    showToast('Failed to reject submission.', 'error')
  } finally {
    isSubmittingAction.value = false
  }
}

const openReopenModal = (inst: any) => {
  openMenuId.value = null
  reopenModal.value = {
    open: true,
    instructor: inst,
    reason: ''
  }
}

const submitReopen = async () => {
  if (!reopenModal.value.instructor) return
  const inst = reopenModal.value.instructor
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id
    const res = await apiClient.put(`/dept-head/semester-submissions/${targetId}/reopen`, {
      reopen_reason: reopenModal.value.reason || 'Semester reopened by department head for corrections.'
    })

    const prevStatus = inst.status
    inst.status = 'Reopened'
    inst.raw_status = 'reopened'
    inst.is_locked = false
    inst.reopened_at = res.data?.submission?.reopened_at || new Date().toISOString()
    inst.reopen_reason = reopenModal.value.reason

    if (reviewModal.value.open && reviewModal.value.instructor?.id === inst.id) {
      reviewModal.value.instructor.status = 'Reopened'
      reviewModal.value.instructor.raw_status = 'reopened'
      reviewModal.value.instructor.is_locked = false
    }

    reopenModal.value.open = false
    showToast(`Semester reopened for ${inst.name}. Instructor can now make modifications.`, 'success')
  } catch (error: any) {
    console.error('Failed to reopen semester:', error)
    showToast(error?.response?.data?.message || 'Failed to reopen semester submission.', 'error')
  } finally {
    isSubmittingAction.value = false
  }
}

// Integrated Backend Export Functionality
const triggerExport = async (format: 'pdf' | 'excel' | 'csv') => {
  isExporting.value = true
  try {
    const params: any = { format }

    if (selectedDepartment.value && selectedDepartment.value !== 'All Departments') {
      params.department = selectedDepartment.value
    } else {
      params.department = 'All Departments'
    }

    if (selectedStatus.value && selectedStatus.value !== 'All Statuses') {
      params.status = selectedStatus.value
    }

    if (selectedSemester.value) {
      const parts = selectedSemester.value.split('—').map(s => s.trim())
      if (parts[0]) params.academic_year = parts[0]
      if (parts[1]) params.semester = parts[1]
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    const res = await apiClient.get('/dept-head/semester-submissions/export', { params })
    if (res.data?.file) {
      const byteCharacters = atob(res.data.file)
      const byteNumbers = new Array(byteCharacters.length)
      for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i)
      }
      const byteArray = new Uint8Array(byteNumbers)
      const mimeType = format === 'pdf' 
        ? 'application/pdf' 
        : (format === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv;charset=utf-8;')
      
      const blob = new Blob([byteArray], { type: mimeType })
      const link = document.createElement('a')
      link.href = URL.createObjectURL(blob)
      link.download = res.data.filename || `Semester_Submissions_Report.${format}`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(link.href)

      showToast(`Exported ${format.toUpperCase()} report successfully!`, 'success')
      isExportModalOpen.value = false
    } else {
      showToast('Export failed: file data not received from server.', 'error')
    }
  } catch (err) {
    console.error('Export error:', err)
    showToast('Failed to export report from server.', 'error')
  } finally {
    isExporting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">

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
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-xl border bg-white"
        :class="toast.type === 'success' ? 'border-emerald-200 text-emerald-800' : (toast.type === 'error' ? 'border-rose-200 text-rose-800' : 'border-indigo-200 text-indigo-800')"
      >
        <div class="w-2.5 h-2.5 rounded-full" :class="toast.type === 'success' ? 'bg-emerald-500' : (toast.type === 'error' ? 'bg-rose-500' : 'bg-indigo-500')"></div>
        <span class="text-[13px] font-semibold">{{ toast.message }}</span>
      </div>
    </transition>

    <!-- Page Header -->
    <div class="flex items-start justify-between">
      <div class="flex items-center gap-3">
        <button
          @click="router.push({ name: 'DeptHeadSemesterSubmissionsOverview' })"
          class="w-9 h-9 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-slate-500 hover:text-[#5138ed] hover:border-[#5138ed] hover:bg-indigo-50 transition-all shadow-sm"
          title="Back to Semester Submissions Overview"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <div class="w-10 h-10 bg-indigo-50 text-[#5138ed] rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
        </div>
        <div>
          <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Semester Submissions</h2>
          <p class="text-[13px] text-slate-500 font-medium">Review and approve instructor semester records for the current academic year and semester.</p>
        </div>
      </div>

      <button
        @click="syncRealData"
        :disabled="isLoading"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-[12px] font-bold hover:bg-slate-50 hover:text-[#5138ed] hover:border-[#5138ed]/40 transition-all shadow-sm disabled:opacity-50"
      >
        <svg class="w-4 h-4 text-[#5138ed]" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        Sync Real Data
      </button>
    </div>

    <!-- Info Banner -->
    <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl px-4 py-3 flex items-center gap-3">
      <div class="w-5 h-5 bg-[#5138ed] text-white rounded-full flex items-center justify-center flex-shrink-0">
        <span class="text-[10px] font-black">i</span>
      </div>
      <p class="text-[12px] font-medium text-indigo-700">
        Once approved, the semester records will be locked and cannot be modified by the instructor.
      </p>
    </div>

    <!-- Top Row: Summary Cards + Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-stretch">

      <!-- Pending Review Card -->
      <div 
        @click="selectedStatus = selectedStatus === 'Pending' ? 'All Statuses' : 'Pending'"
        class="rounded-xl border p-4 flex items-start justify-between shadow-sm hover:shadow-md transition-all cursor-pointer group"
        :class="selectedStatus === 'Pending' 
          ? 'bg-amber-50/60 border-amber-300 ring-2 ring-amber-400' 
          : 'bg-white border-slate-200 hover:border-amber-200'"
      >
        <div>
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 bg-amber-50 text-amber-500 rounded-lg flex items-center justify-center border border-amber-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wide">Pending Review</span>
          </div>
          <div class="text-3xl font-black text-slate-800">
            <span v-if="isLoading" class="inline-block w-8 h-8 bg-slate-100 rounded animate-pulse"></span>
            <span v-else>{{ semesterInfo.pendingReview }}</span>
          </div>
          <span class="text-[11px] font-medium text-slate-500 mt-1">Awaiting your review</span>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-amber-500 transition-colors mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </div>

      <!-- Approved Card -->
      <div 
        @click="selectedStatus = selectedStatus === 'Approved' ? 'All Statuses' : 'Approved'"
        class="rounded-xl border p-4 flex items-start justify-between shadow-sm hover:shadow-md transition-all cursor-pointer group"
        :class="selectedStatus === 'Approved' 
          ? 'bg-emerald-50/60 border-emerald-300 ring-2 ring-emerald-400' 
          : 'bg-white border-slate-200 hover:border-emerald-200'"
      >
        <div>
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center border border-emerald-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wide">Approved</span>
          </div>
          <div class="text-3xl font-black text-slate-800">
            <span v-if="isLoading" class="inline-block w-8 h-8 bg-slate-100 rounded animate-pulse"></span>
            <span v-else>{{ semesterInfo.approved }}</span>
          </div>
          <span class="text-[11px] font-medium text-slate-500 mt-1">This semester</span>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 transition-colors mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </div>

      <!-- Correction Required Card -->
      <div 
        @click="selectedStatus = selectedStatus === 'Correction Required' ? 'All Statuses' : 'Correction Required'"
        class="rounded-xl border p-4 flex items-start justify-between shadow-sm hover:shadow-md transition-all cursor-pointer group"
        :class="selectedStatus === 'Correction Required' 
          ? 'bg-orange-50/60 border-orange-300 ring-2 ring-orange-400' 
          : 'bg-white border-slate-200 hover:border-orange-200'"
      >
        <div>
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 bg-orange-50 text-orange-500 rounded-lg flex items-center justify-center border border-orange-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wide">Correction Required</span>
          </div>
          <div class="text-3xl font-black text-slate-800">
            <span v-if="isLoading" class="inline-block w-8 h-8 bg-slate-100 rounded animate-pulse"></span>
            <span v-else>{{ semesterInfo.correctionRequired }}</span>
          </div>
          <span class="text-[11px] font-medium text-slate-500 mt-1">Needs attention</span>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-orange-500 transition-colors mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </div>

      <!-- Total Submissions Card -->
      <div 
        @click="selectedStatus = 'All Statuses'"
        class="rounded-xl border p-4 flex items-start justify-between shadow-sm hover:shadow-md transition-all cursor-pointer group"
        :class="selectedStatus === 'All Statuses' 
          ? 'bg-indigo-50/40 border-indigo-200 ring-2 ring-[#5138ed]' 
          : 'bg-white border-slate-200 hover:border-indigo-200'"
      >
        <div>
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 bg-indigo-50 text-[#5138ed] rounded-lg flex items-center justify-center border border-indigo-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
            </div>
            <span class="text-[12px] font-bold text-slate-500 uppercase tracking-wide">Total Submissions</span>
          </div>
          <div class="text-3xl font-black text-slate-800">
            <span v-if="isLoading" class="inline-block w-8 h-8 bg-slate-100 rounded animate-pulse"></span>
            <span v-else>{{ semesterInfo.total }}</span>
          </div>
          <span class="text-[11px] font-medium text-slate-500 mt-1">For this semester</span>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-[#5138ed] transition-colors mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </div>

      <!-- Quick Actions Card -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-3">
            <div class="w-7 h-7 bg-indigo-50 text-[#5138ed] rounded-lg flex items-center justify-center">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <span class="text-[12px] font-bold text-slate-700">Quick Actions</span>
          </div>
          <div class="space-y-2">
            <button 
              @click="isExportModalOpen = true"
              class="w-full py-2 bg-[#5138ed] text-white text-[11px] font-bold rounded-xl hover:bg-[#4530d1] transition-colors flex items-center justify-center gap-2 shadow-sm"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              Export Report
            </button>
            <button 
              @click="isGuidelinesModalOpen = true"
              class="w-full py-2 bg-white border border-slate-200 text-slate-600 text-[11px] font-semibold rounded-xl hover:bg-slate-50 transition-colors flex items-center justify-center gap-2"
            >
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              View Submission Guidelines
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main List Container -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col">

      <!-- Filter Bar -->
      <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3 bg-slate-50/50">
        <!-- Search Input -->
        <div class="relative flex-1 min-w-[260px] max-w-md">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by instructor, course, code, or department..."
            class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors shadow-sm"
          />
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>

        <!-- Department Dropdown -->
        <div class="relative">
          <select 
            v-model="selectedDepartment" 
            class="appearance-none pl-4 pr-9 py-2 bg-white border border-slate-200 rounded-lg text-[13px] text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] cursor-pointer min-w-[160px] shadow-sm"
          >
            <option v-for="dept in departmentsList" :key="dept" :value="dept">{{ dept }}</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>

        <!-- Status Dropdown -->
        <div class="relative">
          <select 
            v-model="selectedStatus" 
            class="appearance-none pl-4 pr-9 py-2 bg-white border border-slate-200 rounded-lg text-[13px] text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] cursor-pointer min-w-[175px] shadow-sm"
          >
            <option value="All Statuses">All Submissions ({{ semesterInfo.total }})</option>
            <option value="Pending">Pending Review ({{ semesterInfo.pendingReview }})</option>
            <option value="Approved">Approved ({{ semesterInfo.approved }})</option>
            <option value="Correction Required">Correction Required ({{ semesterInfo.correctionRequired }})</option>
            <option value="Rejected">Rejected ({{ semesterInfo.rejected }})</option>
            <option value="Reopened">Reopened</option>
            <option value="Not Submitted">Not Submitted ({{ semesterInfo.notSubmitted || 0 }})</option>
            <option value="All Instructors">All Instructors ({{ (semesterInfo.total || 0) + (semesterInfo.notSubmitted || 0) }})</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>

        <!-- Academic Term Dropdown -->
        <div class="relative ml-auto">
          <select 
            v-model="selectedSemester" 
            class="appearance-none pl-4 pr-9 py-2 bg-white border border-slate-200 rounded-lg text-[13px] text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] cursor-pointer min-w-[220px] shadow-sm"
          >
            <option v-for="sem in semestersList" :key="sem" :value="sem">{{ sem }}</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
      </div>

      <!-- Table Container -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 bg-slate-50/60">
              <th class="py-3.5 px-4 font-semibold w-10">#</th>
              <th class="py-3.5 px-4 font-semibold">Instructor</th>
              <th class="py-3.5 px-4 font-semibold">Course</th>
              <th class="py-3.5 px-4 font-semibold">Section</th>
              <th class="py-3.5 px-4 font-semibold text-center">Credit</th>
              <th class="py-3.5 px-4 font-semibold">Submitted</th>
              <th class="py-3.5 px-4 font-semibold">Status</th>
              <th class="py-3.5 px-4 font-semibold text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">

            <!-- Loading State -->
            <tr v-if="isLoading">
              <td colspan="8" class="py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                  <div class="animate-spin w-8 h-8 border-2 border-[#5138ed] border-t-transparent rounded-full"></div>
                  <span class="text-[13px] font-semibold text-slate-500">Loading semester submissions...</span>
                </div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="filteredInstructors.length === 0">
              <td colspan="8" class="py-16 text-center">
                <div class="flex flex-col items-center gap-2.5 text-slate-400">
                  <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-1">
                    <svg class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                  </div>
                  <span class="text-[14px] font-bold text-slate-700">No Semester Submissions Found</span>
                  <span class="text-[12px] text-slate-500 max-w-md">No instructors have submitted semester records for {{ semesterInfo.academicYear }} ({{ semesterInfo.semester }}) yet.</span>
                  <div v-if="selectedStatus === 'All Statuses' && (semesterInfo.notSubmitted || 0) > 0" class="mt-2">
                    <button
                      @click="selectedStatus = 'Not Submitted'"
                      class="px-4 py-2 bg-indigo-50 text-[#5138ed] border border-indigo-100 rounded-xl text-[12px] font-bold hover:bg-indigo-100 transition-colors shadow-xs"
                    >
                      View Department Instructors ({{ semesterInfo.notSubmitted }} Unsubmitted)
                    </button>
                  </div>
                </div>
              </td>
            </tr>

            <!-- Real Data Rows -->
            <tr
              v-else
              v-for="(inst, index) in paginatedInstructors"
              :key="inst.id"
              class="hover:bg-slate-50/70 transition-colors group cursor-pointer"
              @click="openReviewModal(inst)"
            >
              <!-- 1: Row Number -->
              <td class="py-3.5 px-4 text-[12px] font-bold text-slate-400">
                {{ (currentPage - 1) * itemsPerPage + index + 1 }}
              </td>

              <!-- 2: Instructor (Avatar, Name, Email) -->
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-3">
                  <div :class="['w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-extrabold flex-shrink-0 shadow-sm', inst.color]">
                    {{ inst.initials }}
                  </div>
                  <div class="flex flex-col min-w-0">
                    <span class="text-[13px] font-bold text-slate-800 truncate group-hover:text-[#5138ed] transition-colors">{{ inst.name }}</span>
                    <span class="text-[11px] text-slate-400 font-medium truncate">{{ inst.email }}</span>
                  </div>
                </div>
              </td>

              <!-- 3: Course Title & Code -->
              <td class="py-3.5 px-4">
                <div class="flex flex-col">
                  <span class="text-[13px] font-bold text-slate-800 truncate">{{ inst.course || inst.department }}</span>
                  <span v-if="inst.course_code" class="text-[11px] text-slate-400 font-medium">
                    <span class="font-mono text-slate-500 font-semibold">{{ inst.course_code }}</span> • {{ inst.department }}
                  </span>
                  <span v-else class="text-[11px] text-slate-400 font-medium">{{ inst.department }}</span>
                </div>
              </td>

              <!-- 4: Section Badge -->
              <td class="py-3.5 px-4">
                <span
                  class="px-2.5 py-1 text-[11px] font-bold rounded-md whitespace-nowrap"
                  :class="inst.section?.toLowerCase().includes('b') ? 'bg-sky-50 text-sky-600 border border-sky-100' : 'bg-indigo-50 text-[#5138ed] border border-indigo-100'"
                >
                  {{ inst.section || 'Section A' }}
                </span>
              </td>

              <!-- 5: Credit Hours -->
              <td class="py-3.5 px-4 text-[13px] font-bold text-slate-700 text-center">
                {{ inst.credit || inst.courses || 4 }}
              </td>

              <!-- 6: Submitted Date and Time -->
              <td class="py-3.5 px-4">
                <div class="flex flex-col">
                  <span class="text-[12px] font-semibold text-slate-700">
                    {{ inst.submitted_date || (inst.submitted?.includes('\n') ? inst.submitted.split('\n')[0] : inst.submitted) }}
                  </span>
                  <span class="text-[11px] text-slate-400 font-medium">
                    {{ inst.submitted_time || (inst.submitted?.includes('\n') ? inst.submitted.split('\n')[1] : '') }}
                  </span>
                </div>
              </td>

              <!-- 7: Status Badge -->
              <td class="py-3.5 px-4">
                <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border whitespace-nowrap', getStatusBadge(inst.status)]">
                  <svg v-if="inst.status === 'Approved'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                  <svg v-else-if="inst.status === 'Pending'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  <svg v-else-if="inst.status === 'Correction Required'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                  <svg v-else-if="inst.status === 'Rejected'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                  {{ inst.status }}
                </span>
              </td>

              <!-- 8: Actions Dropdown Menu -->
              <td class="py-3.5 px-4 text-center" @click.stop>
                <div v-if="inst.is_submitted" class="relative inline-block text-left">
                  <button
                    @click.stop="toggleMenu(inst.id)"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                  >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                      <circle cx="12" cy="5" r="1.5"/>
                      <circle cx="12" cy="12" r="1.5"/>
                      <circle cx="12" cy="19" r="1.5"/>
                    </svg>
                  </button>

                  <!-- 3-Dot Dropdown -->
                  <div
                    v-if="openMenuId === inst.id"
                    @click.stop
                    class="absolute right-0 top-9 z-50 w-52 bg-white border border-slate-200 rounded-xl shadow-xl py-1 text-left animate-in fade-in zoom-in-95 duration-100"
                  >
                    <button
                      @click="openReviewModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-[#5138ed] transition-colors flex items-center gap-2.5"
                    >
                      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                      Review Submission
                    </button>
                    <div class="border-t border-slate-100 my-1"></div>
                    <button
                      @click="approveSubmission(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center gap-2.5"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                      Approve
                    </button>
                    <button
                      @click="openCorrectionModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-orange-600 hover:bg-orange-50 transition-colors flex items-center gap-2.5"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                      Request Correction
                    </button>
                    <button
                      v-if="inst.status === 'Approved' || inst.status === 'Pending'"
                      @click="openReopenModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-cyan-700 hover:bg-cyan-50 transition-colors flex items-center gap-2.5"
                    >
                      <svg class="w-3.5 h-3.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                      Reopen Semester (Unlock)
                    </button>
                    <div class="border-t border-slate-100 my-1"></div>
                    <button
                      @click="openRejectModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-rose-600 hover:bg-rose-50 transition-colors flex items-center gap-2.5"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                      Reject
                    </button>
                  </div>
                </div>
                <div v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200 text-slate-400 text-[11px] font-medium">
                  <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                  <span>Awaiting Submission</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer / Pagination -->
      <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-white">
        <span class="text-[12px] font-medium text-slate-500">
          Showing {{ filteredInstructors.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }} to 
          {{ Math.min(currentPage * itemsPerPage, filteredInstructors.length) }} of 
          {{ filteredInstructors.length }} submissions
        </span>

        <div v-if="totalPages > 1" class="flex items-center gap-1.5">
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 bg-white hover:bg-slate-50 hover:text-slate-600 disabled:opacity-40 transition-colors"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
          </button>
          
          <button
            v-for="p in totalPages"
            :key="p"
            @click="goToPage(p)"
            :class="[
              'w-8 h-8 flex items-center justify-center rounded-lg text-[12px] font-bold transition-colors',
              p === currentPage 
                ? 'bg-[#5138ed] text-white shadow-sm' 
                : 'border border-slate-200 text-slate-600 bg-white hover:bg-slate-50'
            ]"
          >
            {{ p }}
          </button>

          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 bg-white hover:bg-slate-50 hover:text-slate-600 disabled:opacity-40 transition-colors"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Review Submission Detail Modal -->
    <div
      v-if="reviewModal.open && reviewModal.instructor"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 overflow-y-auto"
    >
      <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-100 my-8">
        
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-4 mb-5">
          <div class="flex items-center gap-3">
            <div :class="['w-12 h-12 rounded-full flex items-center justify-center text-sm font-extrabold flex-shrink-0 shadow-sm', reviewModal.instructor.color]">
              {{ reviewModal.instructor.initials }}
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-lg font-bold text-slate-800">{{ reviewModal.instructor.name }}</h3>
                <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold border', getStatusBadge(reviewModal.instructor.status)]">
                  {{ reviewModal.instructor.status }}
                </span>
              </div>
              <p class="text-[12px] text-slate-500 font-medium">{{ reviewModal.instructor.email }} • {{ reviewModal.instructor.department }}</p>
            </div>
          </div>
          <button 
            @click="reviewModal.open = false" 
            class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Academic Submission Overview Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Course</span>
            <span class="text-[13px] font-bold text-slate-800 truncate block">{{ reviewModal.instructor.course }}</span>
            <span class="text-[10px] font-mono text-slate-500 font-semibold">{{ reviewModal.instructor.course_code || 'N/A' }}</span>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Section & Credit</span>
            <span class="text-[13px] font-bold text-slate-800 block">{{ reviewModal.instructor.section }}</span>
            <span class="text-[10px] text-slate-500 font-medium">{{ reviewModal.instructor.credit }} Credit Hours</span>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Submitted Date</span>
            <span class="text-[13px] font-bold text-slate-800 block">{{ reviewModal.instructor.submitted_date }}</span>
            <span class="text-[10px] text-slate-500 font-medium">{{ reviewModal.instructor.submitted_time }}</span>
          </div>
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Academic Term</span>
            <span class="text-[13px] font-bold text-slate-800 block">{{ semesterInfo.academicYear }}</span>
            <span class="text-[10px] text-slate-500 font-medium">{{ semesterInfo.semester }}</span>
          </div>
        </div>

        <!-- Performance Metrics Grid -->
        <div class="border border-slate-100 rounded-xl p-4 mb-5 bg-gradient-to-r from-indigo-50/30 to-purple-50/20">
          <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3">Academic Performance & Records</h4>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div class="bg-white p-2.5 rounded-lg border border-slate-100 shadow-xs">
              <span class="text-[18px] font-black text-slate-800 block">{{ reviewModal.instructor.exams_count ?? 1 }}</span>
              <span class="text-[11px] font-medium text-slate-500">Exams Conducted</span>
            </div>
            <div class="bg-white p-2.5 rounded-lg border border-slate-100 shadow-xs">
              <span class="text-[18px] font-black text-slate-800 block">{{ reviewModal.instructor.results_submitted ?? 0 }}</span>
              <span class="text-[11px] font-medium text-slate-500">Graded Attempts</span>
            </div>
            <div class="bg-white p-2.5 rounded-lg border border-slate-100 shadow-xs">
              <span class="text-[18px] font-black text-emerald-600 block">{{ reviewModal.instructor.avg_score ?? 0 }}%</span>
              <span class="text-[11px] font-medium text-slate-500">Class Average</span>
            </div>
            <div class="bg-white p-2.5 rounded-lg border border-slate-100 shadow-xs">
              <span class="text-[18px] font-black text-[#5138ed] block">{{ reviewModal.instructor.pass_rate ?? 0 }}%</span>
              <span class="text-[11px] font-medium text-slate-500">Pass Rate</span>
            </div>
          </div>
        </div>

        <!-- Checklist -->
        <div class="mb-5">
          <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">Verification Checklist</h4>
          <div class="space-y-2 text-[12px]">
            <div class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-50 text-slate-700">
              <div class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
              </div>
              <span class="font-medium">Academic Schedule Verified: Registered for {{ semesterInfo.academicYear }} ({{ semesterInfo.semester }})</span>
            </div>
            <div class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-50 text-slate-700">
              <div class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
              </div>
              <span class="font-medium">Examinations & Assessments: {{ reviewModal.instructor.exams_count ?? 1 }} Exams Configured & Evaluated</span>
            </div>
            <div class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-50 text-slate-700">
              <div class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
              </div>
              <span class="font-medium">Continuous Assessment & Grading: Complete for Section {{ reviewModal.instructor.section }}</span>
            </div>
          </div>
        </div>

        <!-- Remarks Section if present -->
        <div v-if="reviewModal.instructor.remarks" class="mb-5 p-3 rounded-xl bg-amber-50/70 border border-amber-200">
          <span class="text-[11px] font-bold text-amber-800 uppercase tracking-wide block mb-1">Previous Remarks / Notes:</span>
          <p class="text-[12px] text-amber-900 font-medium">{{ reviewModal.instructor.remarks }}</p>
        </div>

        <!-- Modal Footer Actions -->
        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
          <button
            @click="reviewModal.open = false"
            class="px-4 py-2 rounded-xl text-[12px] font-semibold text-slate-600 hover:bg-slate-100 transition-colors"
          >
            Close
          </button>

          <div v-if="reviewModal.instructor.is_submitted" class="flex items-center gap-2">
            <button
              v-if="reviewModal.instructor.status !== 'Rejected'"
              @click="openRejectModal(reviewModal.instructor)"
              class="px-4 py-2 rounded-xl text-[12px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-colors"
            >
              Reject
            </button>
            <button
              v-if="reviewModal.instructor.status !== 'Correction Required'"
              @click="openCorrectionModal(reviewModal.instructor)"
              class="px-4 py-2 rounded-xl text-[12px] font-bold text-orange-600 bg-orange-50 hover:bg-orange-100 transition-colors"
            >
              Request Correction
            </button>
            <button
              v-if="reviewModal.instructor.status === 'Approved' || reviewModal.instructor.status === 'Pending'"
              @click="openReopenModal(reviewModal.instructor)"
              class="px-4 py-2 rounded-xl text-[12px] font-bold text-cyan-700 bg-cyan-50 hover:bg-cyan-100 transition-colors flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
              Reopen (Unlock)
            </button>
            <button
              v-if="reviewModal.instructor.status !== 'Approved'"
              @click="approveSubmission(reviewModal.instructor)"
              :disabled="isSubmittingAction"
              class="px-5 py-2 rounded-xl text-[12px] font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-sm disabled:opacity-50"
            >
              Approve Submission
            </button>
          </div>
          <div v-else class="text-[12px] text-slate-400 font-medium italic flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            Instructor has not submitted semester records yet.
          </div>
        </div>

      </div>
    </div>

    <!-- Export Report Options Modal -->
    <div
      v-if="isExportModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-800">Export Semester Report</h3>
              <p class="text-[11px] text-slate-400">Download formatted semester submissions report</p>
            </div>
          </div>
          <button @click="isExportModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <p class="text-[12px] text-slate-500 mb-4 font-medium">
          Choose your preferred export format. Both options include instructor names, courses, section assignments, submission timestamps, and current review status.
        </p>

        <div class="space-y-3 mb-5">
          <!-- PDF Option -->
          <button
            @click="triggerExport('pdf')"
            :disabled="isExporting"
            class="w-full p-3.5 rounded-xl border border-slate-200 hover:border-[#5138ed] hover:bg-indigo-50/40 transition-all flex items-center justify-between group text-left disabled:opacity-50"
          >
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs flex-shrink-0 border border-rose-100">
                PDF
              </div>
              <div>
                <span class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors block">Official Wollo University PDF Report</span>
                <span class="text-[11px] text-slate-400">Printable landscape document with university header & KPI summary</span>
              </div>
            </div>
            <svg class="w-4 h-4 text-slate-400 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>

          <!-- Excel Option -->
          <button
            @click="triggerExport('excel')"
            :disabled="isExporting"
            class="w-full p-3.5 rounded-xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/40 transition-all flex items-center justify-between group text-left disabled:opacity-50"
          >
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xs flex-shrink-0 border border-emerald-100">
                XLS
              </div>
              <div>
                <span class="text-[13px] font-bold text-slate-800 group-hover:text-emerald-700 transition-colors block">Excel Spreadsheet (.xlsx)</span>
                <span class="text-[11px] text-slate-400">Structured data spreadsheet for departmental records and archiving</span>
              </div>
            </div>
            <svg class="w-4 h-4 text-slate-400 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>

          <!-- CSV Option -->
          <button
            @click="triggerExport('csv')"
            :disabled="isExporting"
            class="w-full p-3.5 rounded-xl border border-slate-200 hover:border-sky-500 hover:bg-sky-50/40 transition-all flex items-center justify-between group text-left disabled:opacity-50"
          >
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-xs flex-shrink-0 border border-sky-100">
                CSV
              </div>
              <div>
                <span class="text-[13px] font-bold text-slate-800 group-hover:text-sky-700 transition-colors block">Comma Separated Values (.csv)</span>
                <span class="text-[11px] text-slate-400">Universal tabular format compatible with all spreadsheet tools</span>
              </div>
            </div>
            <svg class="w-4 h-4 text-slate-400 group-hover:text-sky-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>

        <div v-if="isExporting" class="flex items-center justify-center gap-2 py-2 text-[12px] font-semibold text-[#5138ed]">
          <div class="w-4 h-4 border-2 border-[#5138ed] border-t-transparent rounded-full animate-spin"></div>
          Generating export document from live database...
        </div>

        <div class="flex justify-end pt-2 border-t border-slate-100">
          <button
            @click="isExportModalOpen = false"
            class="px-4 py-2 rounded-xl text-[12px] font-semibold text-slate-600 hover:bg-slate-100 transition-colors"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Request Correction Modal -->
    <div
      v-if="correctionModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center flex-shrink-0 border border-orange-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-800">Request Correction</h3>
            <p class="text-[12px] text-slate-500">Instructor: {{ correctionModal.instructor?.name }}</p>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Feedback / Correction Remarks</label>
          <textarea
            v-model="correctionModal.remarks"
            rows="3"
            placeholder="Specify what needs correction (e.g. missing continuous assessment scores, grade recalculation)..."
            class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:bg-white transition-colors"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            @click="correctionModal.open = false"
            class="px-4 py-2 rounded-xl text-[12px] font-semibold text-slate-600 hover:bg-slate-100 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="submitCorrection"
            :disabled="isSubmittingAction"
            class="px-5 py-2 rounded-xl text-[12px] font-bold bg-orange-500 text-white hover:bg-orange-600 transition-colors shadow-sm disabled:opacity-50"
          >
            Submit Request
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Submission Modal -->
    <div
      v-if="rejectModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center flex-shrink-0 border border-rose-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-800">Reject Submission</h3>
            <p class="text-[12px] text-slate-500">Instructor: {{ rejectModal.instructor?.name }}</p>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Reason for Rejection</label>
          <textarea
            v-model="rejectModal.remarks"
            rows="3"
            placeholder="Explain why this submission is rejected..."
            class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:border-rose-500 focus:bg-white transition-colors"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            @click="rejectModal.open = false"
            class="px-4 py-2 rounded-xl text-[12px] font-semibold text-slate-600 hover:bg-slate-100 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="submitReject"
            :disabled="isSubmittingAction"
            class="px-5 py-2 rounded-xl text-[12px] font-bold bg-rose-600 text-white hover:bg-rose-700 transition-colors shadow-sm disabled:opacity-50"
          >
            Confirm Rejection
          </button>
        </div>
      </div>
    </div>

    <!-- Reopen Semester Modal -->
    <div
      v-if="reopenModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center flex-shrink-0 border border-cyan-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-800">Reopen Semester</h3>
            <p class="text-[12px] text-slate-500">Instructor: {{ reopenModal.instructor?.name }}</p>
          </div>
        </div>

        <p class="text-[12px] text-slate-600 mb-4 leading-relaxed">
          Reopening will <strong class="text-slate-800">unlock the instructor's academic records</strong>. The instructor will regain full edit access to create and modify questions, exams, and grades.
        </p>

        <div class="mb-4">
          <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Reason for Reopening</label>
          <textarea
            v-model="reopenModal.reason"
            rows="3"
            placeholder="Specify reason for reopening (e.g., Grade adjustment approved, missing assessment to be added)..."
            class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:border-cyan-500 focus:bg-white transition-colors"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            @click="reopenModal.open = false"
            class="px-4 py-2 rounded-xl text-[12px] font-semibold text-slate-600 hover:bg-slate-100 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="submitReopen"
            :disabled="isSubmittingAction"
            class="px-5 py-2 rounded-xl text-[12px] font-bold bg-cyan-600 text-white hover:bg-cyan-700 transition-colors shadow-sm disabled:opacity-50 flex items-center gap-1.5"
          >
            <svg v-if="isSubmittingAction" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Unlock & Reopen
          </button>
        </div>
      </div>
    </div>

    <!-- Submission Guidelines Modal -->
    <div
      v-if="isGuidelinesModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
    >
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800">Semester Submission Guidelines</h3>
          </div>
          <button @click="isGuidelinesModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="space-y-4 text-[13px] text-slate-600 leading-relaxed">
          <div class="p-3 bg-indigo-50/60 rounded-xl border border-indigo-100">
            <span class="font-bold text-indigo-900 block mb-1">1. Completeness of Continuous Assessment</span>
            <p>Instructors must ensure all quizzes, assignments, midterm exams, and practical labs have been properly scored and published before finalizing semester submissions.</p>
          </div>

          <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
            <span class="font-bold text-slate-800 block mb-1">2. Grade Verification Protocol</span>
            <p>Verify that total scores conform to university grading scales (A+, A, B, C, D, F, NG) and that student attendances satisfy the minimum requirement.</p>
          </div>

          <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
            <span class="font-bold text-slate-800 block mb-1">3. Approval & Locking Mechanism</span>
            <p>Once a submission is marked as <strong>Approved</strong>, all course grades for that section will be locked from further edits by the instructor. To reopen editing, the department head must issue a <strong>Correction Request</strong>.</p>
          </div>

          <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
            <span class="font-bold text-slate-800 block mb-1">4. Rejection Policy</span>
            <p>Submissions should only be <strong>Rejected</strong> if there is a fundamental breach or major discrepancies in exam records. Always supply clear remarks so instructors understand necessary revisions.</p>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <button
            @click="isGuidelinesModalOpen = false"
            class="px-5 py-2.5 bg-[#5138ed] text-white rounded-xl text-[12px] font-bold hover:bg-[#4530d1] transition-colors shadow-sm"
          >
            I Understand
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* Scoped styles */
</style>