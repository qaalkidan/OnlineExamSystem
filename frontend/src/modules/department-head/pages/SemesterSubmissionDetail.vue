<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'

const route = useRoute()
const router = useRouter()

const submissionId = route.params.id as string

const isLoading = ref(true)
const isSyncing = ref(false)
const isSubmittingAction = ref(false)
const isExporting = ref(false)
const searchQuery = ref('')
const selectedDepartment = ref('All Departments')
const selectedStatus = ref('All Statuses')
const selectedSemester = ref('All Academic Terms')
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

const viewDetailsModal = ref<{
  open: boolean
  isLoading: boolean
  activeTab: 'overview' | 'students' | 'checklist'
  searchStudent: string
  statusFilter: string
  data: any | null
}>({
  open: false,
  isLoading: false,
  activeTab: 'overview',
  searchStudent: '',
  statusFilter: 'all',
  data: null,
})

const reviewModal = ref<{
  open: boolean
  instructor: any | null
}>({
  open: false,
  instructor: null,
})

const approveModal = ref<{
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

const closeSemesterModal = ref<{
  open: boolean
  instructor: any | null
}>({
  open: false,
  instructor: null,
})

// Summary Stats
const semesterInfo = ref({
  academicYear: 'All Academic Years',
  semester: 'All Semesters',
  department: 'Computer Science',
  department_code: 'CS',
  pendingReview: 0,
  approved: 0,
  correctionRequired: 0,
  rejected: 0,
  reopened: 0,
  notSubmitted: 0,
  total: 0,
})

// Real Instructors & Submissions
const instructors = ref<any[]>([])
const departmentsList = ref<string[]>([
  'All Departments',
  'computer scince',
  'software engineering',
  'Information technology',
  'Informatics system',
  'electrical engineering',
  'civil'
])

const semestersList = ref<string[]>([
  'All Academic Terms',
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

    if (selectedSemester.value && selectedSemester.value !== 'All Academic Terms') {
      const parts = selectedSemester.value.split('—').map(s => s.trim())
      if (parts[0]) params.academic_year = parts[0]
      if (parts[1]) params.semester = parts[1]
    } else {
      params.academic_year = 'All Academic Terms'
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

// Live Backend Synchronization
const syncRealData = async () => {
  isSyncing.value = true
  try {
    const payload: any = {}
    if (selectedSemester.value && selectedSemester.value !== 'All Academic Terms') {
      const parts = selectedSemester.value.split('—').map(s => s.trim())
      if (parts[0]) payload.academic_year = parts[0]
      if (parts[1]) payload.semester = parts[1]
    } else {
      payload.academic_year = '2025/2026'
      payload.semester = 'Second Semester'
    }

    const res = await apiClient.post('/dept-head/semester-submissions/sync', payload)
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
    showToast('Live semester submission records synchronized!', 'success')
  } catch (error) {
    console.error('Failed to sync semester submissions:', error)
    // Fallback to fetchSubmissions
    await fetchSubmissions()
    showToast('Refreshed semester submission data.', 'info')
  } finally {
    isSyncing.value = false
  }
}

onMounted(async () => {
  await fetchSubmissions()
  window.addEventListener('click', closeAllMenus)

  // If directly navigated to a submission record ID
  if (submissionId && submissionId !== 'all' && !submissionId.includes('year') && !isNaN(Number(submissionId))) {
    const found = instructors.value.find(i => i.submission_id === Number(submissionId) || i.id === Number(submissionId))
    if (found) {
      openViewDetailsModal(found)
    } else {
      openViewDetailsModal({ id: Number(submissionId), submission_id: Number(submissionId) })
    }
  }
})

const closeAllMenus = () => {
  openMenuId.value = null
}

// Watch filters to refresh
watch([selectedDepartment, selectedSemester], () => {
  currentPage.value = 1
  fetchSubmissions()
})

const hasActiveFilters = computed(() => {
  return searchQuery.value.trim() !== '' ||
    selectedDepartment.value !== 'All Departments' ||
    selectedStatus.value !== 'All Statuses' ||
    selectedSemester.value !== 'All Academic Terms'
})

const resetFilters = () => {
  searchQuery.value = ''
  selectedDepartment.value = 'All Departments'
  selectedStatus.value = 'All Statuses'
  selectedSemester.value = 'All Academic Terms'
  currentPage.value = 1
}

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
      matchesStatus = (inst.status?.toLowerCase() === selectedStatus.value.toLowerCase())
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
  if (status === 'Approved') return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (status === 'Pending') return 'bg-amber-50 text-amber-700 border-amber-200'
  if (status === 'Under Review') return 'bg-blue-50 text-blue-700 border-blue-200'
  if (status === 'Correction Required') return 'bg-orange-50 text-orange-700 border-orange-200'
  if (status === 'Rejected') return 'bg-rose-50 text-rose-700 border-rose-200'
  if (status === 'Reopened') return 'bg-cyan-50 text-cyan-800 border-cyan-200'
  if (status === 'Not Submitted') return 'bg-slate-100 text-slate-500 border-slate-200'
  return 'bg-slate-50 text-slate-500 border-slate-200'
}

const toggleMenu = (id: number) => {
  openMenuId.value = openMenuId.value === id ? null : id
}

// Styling Helper for Grade Badge
const getGradeBadge = (grade: string) => {
  if (['A+', 'A', 'A-'].includes(grade)) return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (['B+', 'B', 'B-'].includes(grade)) return 'bg-blue-50 text-blue-700 border-blue-200'
  if (['C+', 'C', 'C-'].includes(grade)) return 'bg-amber-50 text-amber-700 border-amber-200'
  if (grade === 'D') return 'bg-orange-50 text-orange-700 border-orange-200'
  if (grade === 'F') return 'bg-rose-50 text-rose-700 border-rose-200'
  return 'bg-slate-100 text-slate-500 border-slate-200'
}

// Filtered Enrolled Students for View Details Modal
const filteredModalStudents = computed<any[]>(() => {
  if (!viewDetailsModal.value.data?.students) return []
  const list = viewDetailsModal.value.data.students
  const q = viewDetailsModal.value.searchStudent.toLowerCase().trim()
  const filter = viewDetailsModal.value.statusFilter

  return list.filter((st: any) => {
    const matchesSearch = !q 
      || st.name?.toLowerCase().includes(q)
      || st.id_no?.toLowerCase().includes(q)
      || st.email?.toLowerCase().includes(q)

    let matchesStatus = true
    if (filter === 'completed') {
      matchesStatus = st.status === 'Completed'
    } else if (filter === 'in_progress') {
      matchesStatus = st.status === 'Partially Graded' || st.status === 'In Progress'
    } else if (filter === 'not_attempted') {
      matchesStatus = st.status === 'Not Attempted'
    }

    return matchesSearch && matchesStatus
  })
})

// Active computed item for View Details Modal actions
const viewDetailsItem = computed(() => {
  const d = viewDetailsModal.value.data
  if (!d) return null
  return {
    ...(d.instructor || {}),
    ...(d.submission || {}),
    submission_id: d.submission?.id,
    instructor_id: d.instructor?.id,
    id: d.submission?.id || d.instructor?.id,
    name: d.instructor?.name,
    email: d.instructor?.email,
    course: d.course?.title || d.course?.name || d.instructor?.course,
    course_code: d.course?.code || d.instructor?.course_code,
    section: d.course?.section || d.instructor?.section || 'Section A',
    credit: d.course?.credits || d.instructor?.credit,
    academic_year: d.submission?.academic_year || semesterInfo.value.academicYear,
    semester: d.submission?.semester || semesterInfo.value.semester,
    status: d.submission?.status || 'Pending',
    raw_status: d.submission?.raw_status || 'pending',
    is_locked: Boolean(d.submission?.is_locked),
    locked_at: d.submission?.locked_at,
    approved_at: d.submission?.approved_at,
    is_submitted: true,
  }
})

// Open Comprehensive View Details Modal
const openViewDetailsModal = async (inst: any) => {
  openMenuId.value = null
  viewDetailsModal.value = {
    open: true,
    isLoading: true,
    activeTab: 'overview',
    searchStudent: '',
    statusFilter: 'all',
    data: {
      submission: {
        id: inst.submission_id || inst.id,
        instructor_id: inst.instructor_id || inst.id,
        status: inst.status || 'Pending',
        raw_status: inst.raw_status || 'pending',
        is_locked: inst.is_locked,
        academic_year: inst.academic_year || semesterInfo.value.academicYear,
        semester: inst.semester || semesterInfo.value.semester,
        submitted_at: inst.submitted_date ? `${inst.submitted_date} ${inst.submitted_time}` : inst.submitted,
        approved_at: inst.approved_at,
        approved_by: inst.approved_by,
        reopened_at: inst.reopened_at,
        reopened_by: inst.reopened_by,
        reopen_reason: inst.reopen_reason,
        remarks: inst.remarks,
        locked_at: inst.locked_at,
      },
      instructor: {
        id: inst.instructor_id || inst.id,
        name: inst.name,
        email: inst.email,
        initials: inst.initials,
        color: inst.color,
        department: inst.department,
        section: inst.section,
        year_level: inst.year_level,
        phone: inst.phone || '—',
        office: inst.office || 'Staff Office',
        role: inst.role || 'Instructor',
      },
      course: {
        title: inst.course,
        code: inst.course_code,
        credits: inst.credit || inst.courses || 4,
        section: inst.section || 'Section A',
        level: inst.year_level || '3rd Year',
        department: inst.department,
      },
      exams: [],
      students: [],
      kpis: {
        total_enrolled: inst.students || 0,
        total_exams: inst.exams_count || 0,
        total_graded: inst.results_submitted || 0,
        avg_score: inst.avg_score || 0,
        pass_rate: inst.pass_rate || 0,
        highest_score: 0,
        lowest_score: 0,
      },
      checklist: inst.checklist || {},
    }
  }

  try {
    const targetId = inst.submission_id || inst.id || inst.instructor_id
    const res = await apiClient.get(`/dept-head/semester-submissions/${targetId}/record-details`, {
      params: {
        academic_year: inst.academic_year || semesterInfo.value.academicYear,
        semester: inst.semester || semesterInfo.value.semester,
      }
    })
    if (res.data) {
      viewDetailsModal.value.data = res.data
    }
  } catch (err) {
    console.error('Failed to load full submission details:', err)
  } finally {
    viewDetailsModal.value.isLoading = false
  }
}

// Open Review Detail Modal
const openReviewModal = (inst: any) => {
  openMenuId.value = null
  reviewModal.value = {
    open: true,
    instructor: inst,
  }
}

const openViewDetailsFromReview = () => {
  const inst = reviewModal.value.instructor
  reviewModal.value.open = false
  if (inst) {
    openViewDetailsModal(inst)
  }
}

// Open Approve Confirmation Modal
const openApproveModal = (inst: any) => {
  openMenuId.value = null
  approveModal.value = {
    open: true,
    instructor: inst,
  }
}

// Functional Actions (Persisting to backend)
const confirmApprove = async () => {
  const inst = approveModal.value.instructor || viewDetailsModal.value.data?.instructor
  if (!inst) return
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id
    const res = await apiClient.put(`/dept-head/semester-submissions/${targetId}/status`, {
      status: 'approved',
      academic_year: inst.academic_year || semesterInfo.value.academicYear,
      semester: inst.semester || semesterInfo.value.semester,
    })

    const prevStatus = inst.status
    inst.status = 'Approved'
    inst.raw_status = 'approved'
    inst.is_locked = false // Access remains OPEN upon approval
    inst.locked_at = null
    if (res.data?.submission?.approved_at) {
      inst.approved_at = res.data.submission.approved_at
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
      reviewModal.value.instructor.is_locked = false
      reviewModal.value.instructor.locked_at = null
      reviewModal.value.instructor.approved_at = res.data?.submission?.approved_at
    }

    if (viewDetailsModal.value.open && viewDetailsModal.value.data?.submission) {
      viewDetailsModal.value.data.submission.status = 'Approved'
      viewDetailsModal.value.data.submission.raw_status = 'approved'
      viewDetailsModal.value.data.submission.is_locked = false
      viewDetailsModal.value.data.submission.locked_at = null
      viewDetailsModal.value.data.submission.approved_at = res.data?.submission?.approved_at || new Date().toLocaleString()
      viewDetailsModal.value.data.submission.approved_by = { name: res.data?.submission?.approved_by || 'Department Head' }
    }

    // Update list item
    const targetInList = instructors.value.find(i => (i.submission_id && i.submission_id === targetId) || i.id === targetId || i.instructor_id === targetId)
    if (targetInList) {
      targetInList.status = 'Approved'
      targetInList.raw_status = 'approved'
      targetInList.is_locked = false
      targetInList.locked_at = null
      targetInList.approved_at = res.data?.submission?.approved_at
    }

    approveModal.value.open = false
    showToast(`Semester submission for ${inst.name} approved successfully. Workspace remains open.`, 'success')
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
  const inst = correctionModal.value.instructor || viewDetailsModal.value.data?.instructor
  if (!inst) return
  if (!correctionModal.value.remarks.trim()) {
    showToast('Please provide feedback or remarks describing what needs correction.', 'error')
    return
  }
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id
    await apiClient.put(`/dept-head/semester-submissions/${targetId}/status`, {
      status: 'correction_required',
      remarks: correctionModal.value.remarks,
      academic_year: inst.academic_year || semesterInfo.value.academicYear,
      semester: inst.semester || semesterInfo.value.semester,
    })

    const prevStatus = inst.status
    inst.status = 'Correction Required'
    inst.raw_status = 'correction_required'
    inst.is_locked = false
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
      reviewModal.value.instructor.is_locked = false
      reviewModal.value.instructor.remarks = correctionModal.value.remarks
    }

    if (viewDetailsModal.value.open && viewDetailsModal.value.data?.submission) {
      viewDetailsModal.value.data.submission.status = 'Correction Required'
      viewDetailsModal.value.data.submission.raw_status = 'correction_required'
      viewDetailsModal.value.data.submission.is_locked = false
      viewDetailsModal.value.data.submission.remarks = correctionModal.value.remarks
    }

    const targetInList = instructors.value.find(i => (i.submission_id && i.submission_id === targetId) || i.id === targetId || i.instructor_id === targetId)
    if (targetInList) {
      targetInList.status = 'Correction Required'
      targetInList.raw_status = 'correction_required'
      targetInList.is_locked = false
      targetInList.remarks = correctionModal.value.remarks
    }

    correctionModal.value.open = false
    showToast(`Correction requested for ${inst.name}. Instructor can now modify records.`, 'info')
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
  const inst = rejectModal.value.instructor || viewDetailsModal.value.data?.instructor
  if (!inst) return
  if (!rejectModal.value.remarks.trim()) {
    showToast('Please provide a reason for rejecting the submission.', 'error')
    return
  }
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id
    await apiClient.put(`/dept-head/semester-submissions/${targetId}/status`, {
      status: 'rejected',
      remarks: rejectModal.value.remarks,
      academic_year: inst.academic_year || semesterInfo.value.academicYear,
      semester: inst.semester || semesterInfo.value.semester,
    })

    const prevStatus = inst.status
    inst.status = 'Rejected'
    inst.raw_status = 'rejected'
    inst.remarks = rejectModal.value.remarks

    // Refresh summary counts
    if (prevStatus !== 'Rejected') {
      semesterInfo.value.rejected = (semesterInfo.value.rejected || 0) + 1
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

    if (viewDetailsModal.value.open && viewDetailsModal.value.data?.submission) {
      viewDetailsModal.value.data.submission.status = 'Rejected'
      viewDetailsModal.value.data.submission.raw_status = 'rejected'
      viewDetailsModal.value.data.submission.remarks = rejectModal.value.remarks
    }

    const targetInList = instructors.value.find(i => (i.submission_id && i.submission_id === targetId) || i.id === targetId || i.instructor_id === targetId)
    if (targetInList) {
      targetInList.status = 'Rejected'
      targetInList.raw_status = 'rejected'
      targetInList.remarks = rejectModal.value.remarks
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

const canCloseSemester = (inst: any): boolean => {
  if (!inst) return false
  if (inst.is_locked) return false
  const s = String(inst.status || '').toLowerCase()
  const raw = String(inst.raw_status || '').toLowerCase()
  return s === 'approved' || s === 'reopened' || raw === 'approved' || raw === 'reopened' || Boolean(inst.approved_at)
}

const openCloseSemesterModal = (inst: any) => {
  openMenuId.value = null
  if (!canCloseSemester(inst)) {
    showToast('Cannot close semester (lock): the semester submission must be approved first.', 'info')
    return
  }
  closeSemesterModal.value = {
    open: true,
    instructor: inst,
  }
}

const confirmCloseSemester = async () => {
  const inst = closeSemesterModal.value.instructor || viewDetailsModal.value.data?.instructor
  if (!inst) return
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id || inst.instructor_id
    const res = await apiClient.put(`/dept-head/semester-submissions/${targetId}/lock`, {
      academic_year: inst.academic_year || semesterInfo.value.academicYear,
      semester: inst.semester || semesterInfo.value.semester,
    })

    const lockedAt = res.data?.submission?.locked_at || new Date().toLocaleString()
    inst.is_locked = true
    inst.locked_at = lockedAt
    inst.status = 'Approved'
    inst.raw_status = 'approved'

    if (reviewModal.value.open && reviewModal.value.instructor?.id === inst.id) {
      reviewModal.value.instructor.is_locked = true
      reviewModal.value.instructor.locked_at = lockedAt
      reviewModal.value.instructor.status = 'Approved'
      reviewModal.value.instructor.raw_status = 'approved'
    }

    if (viewDetailsModal.value.open && viewDetailsModal.value.data?.submission) {
      viewDetailsModal.value.data.submission.is_locked = true
      viewDetailsModal.value.data.submission.locked_at = lockedAt
      viewDetailsModal.value.data.submission.status = 'Approved'
      viewDetailsModal.value.data.submission.raw_status = 'approved'
    }

    const prevStatus = inst.status
    const targetInList = instructors.value.find(i => (i.submission_id && i.submission_id === targetId) || i.id === targetId || i.instructor_id === targetId)
    if (targetInList) {
      targetInList.is_locked = true
      targetInList.locked_at = lockedAt
      targetInList.status = 'Approved'
      targetInList.raw_status = 'approved'
    }

    if (prevStatus === 'Reopened' && (semesterInfo.value.reopened || 0) > 0) {
      semesterInfo.value.reopened--
      semesterInfo.value.approved = (semesterInfo.value.approved || 0) + 1
    }

    closeSemesterModal.value.open = false
    showToast(`Semester workspace for ${inst.name} closed and locked successfully. Instructor access restricted to read-only.`, 'success')
  } catch (error: any) {
    console.error('Failed to close and lock semester:', error)
    showToast(error?.response?.data?.message || 'Failed to lock semester workspace.', 'error')
  } finally {
    isSubmittingAction.value = false
  }
}

const openReopenModal = (inst: any) => {
  openMenuId.value = null
  if (!inst.is_locked) {
    showToast('Only locked semester workspaces can be reopened.', 'info')
    return
  }
  reopenModal.value = {
    open: true,
    instructor: inst,
    reason: ''
  }
}

const submitReopen = async () => {
  const inst = reopenModal.value.instructor || viewDetailsModal.value.data?.instructor
  if (!inst) return
  isSubmittingAction.value = true
  try {
    const targetId = inst.submission_id || inst.id || inst.instructor_id
    const res = await apiClient.put(`/dept-head/semester-submissions/${targetId}/reopen`, {
      reason: reopenModal.value.reason || 'Semester reopened by department head for modifications.'
    })

    inst.status = 'Approved'
    inst.raw_status = 'approved'
    inst.is_locked = false // Access becomes Open!
    inst.locked_at = null
    inst.reopened_at = res.data?.submission?.reopened_at || new Date().toISOString()
    inst.reopen_reason = reopenModal.value.reason

    if (reviewModal.value.open && reviewModal.value.instructor?.id === inst.id) {
      reviewModal.value.instructor.status = 'Approved'
      reviewModal.value.instructor.raw_status = 'approved'
      reviewModal.value.instructor.is_locked = false
      reviewModal.value.instructor.locked_at = null
      reviewModal.value.instructor.reopened_at = res.data?.submission?.reopened_at
      reviewModal.value.instructor.reopen_reason = reopenModal.value.reason
    }

    if (viewDetailsModal.value.open && viewDetailsModal.value.data?.submission) {
      viewDetailsModal.value.data.submission.status = 'Approved'
      viewDetailsModal.value.data.submission.raw_status = 'approved'
      viewDetailsModal.value.data.submission.is_locked = false
      viewDetailsModal.value.data.submission.locked_at = null
      viewDetailsModal.value.data.submission.reopened_at = res.data?.submission?.reopened_at || new Date().toLocaleString()
      viewDetailsModal.value.data.submission.reopened_by = { name: res.data?.submission?.reopened_by || 'Department Head' }
      viewDetailsModal.value.data.submission.reopen_reason = reopenModal.value.reason
    }

    const targetInList = instructors.value.find(i => (i.submission_id && i.submission_id === targetId) || i.id === targetId || i.instructor_id === targetId)
    if (targetInList) {
      targetInList.status = 'Approved'
      targetInList.raw_status = 'approved'
      targetInList.is_locked = false
      targetInList.locked_at = null
      targetInList.reopened_at = res.data?.submission?.reopened_at
      targetInList.reopen_reason = reopenModal.value.reason
    }

    reopenModal.value.open = false
    showToast(`Semester reopened for ${inst.name}. Instructor workspace access restored.`, 'success')
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

    if (selectedSemester.value && selectedSemester.value !== 'All Academic Terms') {
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
        <button
          @click="router.push({ name: 'DeptHeadSemesterSubmissionsOverview' })"
          class="w-9 h-9 sm:w-10 sm:h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-slate-500 hover:text-[#5138ed] hover:border-[#5138ed] hover:bg-indigo-50 transition-all shadow-xs shrink-0 cursor-pointer min-h-[40px] min-w-[40px]"
          title="Back to Semester Submissions Overview"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <div class="w-9 h-9 sm:w-10 sm:h-10 bg-indigo-50 text-[#5138ed] rounded-xl flex items-center justify-center shrink-0 shadow-xs">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h2 class="text-lg sm:text-2xl font-bold text-slate-800 tracking-tight truncate">Semester Submissions</h2>
            <span class="hidden md:inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-[#5138ed] border border-indigo-100">
              {{ semesterInfo.department }} [{{ semesterInfo.department_code }}]
            </span>
          </div>
          <p class="text-xs sm:text-[13px] text-slate-500 font-medium truncate">Review and approve instructor semester records.</p>
        </div>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <button
          @click="syncRealData"
          :disabled="isLoading || isSyncing"
          class="inline-flex items-center justify-center gap-2 px-3.5 sm:px-4 py-2 sm:py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs sm:text-[12px] font-bold hover:bg-slate-50 hover:text-[#5138ed] hover:border-[#5138ed]/40 transition-all shadow-xs disabled:opacity-50 min-h-[44px] cursor-pointer"
        >
          <svg class="w-4 h-4 text-[#5138ed]" :class="{ 'animate-spin': isLoading || isSyncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
          <span>{{ isSyncing ? 'Syncing...' : 'Sync Real Data' }}</span>
        </button>
      </div>
    </div>

    <!-- Info Banner -->
    <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl px-3.5 sm:px-4 py-2.5 sm:py-3 flex items-center gap-3">
      <div class="w-5 h-5 bg-[#5138ed] text-white rounded-full flex items-center justify-center shrink-0">
        <span class="text-[10px] font-black">i</span>
      </div>
      <div class="flex items-center justify-between flex-1 gap-2 flex-wrap">
        <p class="text-xs sm:text-[12px] font-medium text-indigo-700 leading-snug">
          Approved semesters remain open until explicitly closed &amp; locked by the Department Head.
        </p>
        <span class="text-[11px] font-bold text-indigo-600 bg-white/70 px-2 py-0.5 rounded-md border border-indigo-100 shrink-0">
          Active: {{ semesterInfo.department }} &bull; {{ selectedSemester }}
        </span>
      </div>
    </div>

    <!-- Top Row: Summary Cards + Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 items-stretch">

      <!-- Pending Review Card -->
      <div 
        @click="selectedStatus = selectedStatus === 'Pending' ? 'All Statuses' : 'Pending'"
        class="rounded-xl border p-3.5 sm:p-4 flex items-start justify-between shadow-xs hover:shadow-md transition-all cursor-pointer group"
        :class="selectedStatus === 'Pending' 
          ? 'bg-amber-50/60 border-amber-300 ring-2 ring-amber-400' 
          : 'bg-white border-slate-200 hover:border-amber-200'"
      >
        <div class="min-w-0">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 bg-amber-50 text-amber-500 rounded-lg flex items-center justify-center border border-amber-100 shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide truncate">Pending Review</span>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-slate-800">
            <span v-if="isLoading" class="inline-block w-8 h-8 bg-slate-100 rounded animate-pulse"></span>
            <span v-else>{{ semesterInfo.pendingReview }}</span>
          </div>
          <span class="text-[11px] font-medium text-slate-500 mt-1 block truncate">Awaiting your review</span>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-amber-500 transition-colors mt-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </div>

      <!-- Approved Card -->
      <div 
        @click="selectedStatus = selectedStatus === 'Approved' ? 'All Statuses' : 'Approved'"
        class="rounded-xl border p-3.5 sm:p-4 flex items-start justify-between shadow-xs hover:shadow-md transition-all cursor-pointer group"
        :class="selectedStatus === 'Approved' 
          ? 'bg-emerald-50/60 border-emerald-300 ring-2 ring-emerald-400' 
          : 'bg-white border-slate-200 hover:border-emerald-200'"
      >
        <div class="min-w-0">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center border border-emerald-100 shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide truncate">Approved</span>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-slate-800">
            <span v-if="isLoading" class="inline-block w-8 h-8 bg-slate-100 rounded animate-pulse"></span>
            <span v-else>{{ semesterInfo.approved }}</span>
          </div>
          <span class="text-[11px] font-medium text-slate-500 mt-1 block truncate">This semester</span>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 transition-colors mt-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </div>

      <!-- Correction Required Card -->
      <div 
        @click="selectedStatus = selectedStatus === 'Correction Required' ? 'All Statuses' : 'Correction Required'"
        class="rounded-xl border p-3.5 sm:p-4 flex items-start justify-between shadow-xs hover:shadow-md transition-all cursor-pointer group"
        :class="selectedStatus === 'Correction Required' 
          ? 'bg-orange-50/60 border-orange-300 ring-2 ring-orange-400' 
          : 'bg-white border-slate-200 hover:border-orange-200'"
      >
        <div class="min-w-0">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 bg-orange-50 text-orange-500 rounded-lg flex items-center justify-center border border-orange-100 shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide truncate">Correction Required</span>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-slate-800">
            <span v-if="isLoading" class="inline-block w-8 h-8 bg-slate-100 rounded animate-pulse"></span>
            <span v-else>{{ semesterInfo.correctionRequired }}</span>
          </div>
          <span class="text-[11px] font-medium text-slate-500 mt-1 block truncate">Needs attention</span>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-orange-500 transition-colors mt-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </div>

      <!-- Total Submissions Card -->
      <div 
        @click="selectedStatus = 'All Statuses'"
        class="rounded-xl border p-3.5 sm:p-4 flex items-start justify-between shadow-xs hover:shadow-md transition-all cursor-pointer group"
        :class="selectedStatus === 'All Statuses' 
          ? 'bg-indigo-50/40 border-indigo-200 ring-2 ring-[#5138ed]' 
          : 'bg-white border-slate-200 hover:border-indigo-200'"
      >
        <div class="min-w-0">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-8 h-8 bg-indigo-50 text-[#5138ed] rounded-lg flex items-center justify-center border border-indigo-100 shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
            </div>
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide truncate">Total Submissions</span>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-slate-800">
            <span v-if="isLoading" class="inline-block w-8 h-8 bg-slate-100 rounded animate-pulse"></span>
            <span v-else>{{ semesterInfo.total }}</span>
          </div>
          <span class="text-[11px] font-medium text-slate-500 mt-1 block truncate">For this semester</span>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-[#5138ed] transition-colors mt-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </div>

      <!-- Quick Actions Card -->
      <div class="col-span-1 sm:col-span-2 md:col-span-3 lg:col-span-1 bg-white rounded-xl border border-slate-200 p-3.5 sm:p-4 shadow-xs flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-2.5">
            <div class="w-7 h-7 bg-indigo-50 text-[#5138ed] rounded-lg flex items-center justify-center shrink-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <span class="text-xs font-bold text-slate-700">Quick Actions</span>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-1 gap-2">
            <button 
              @click="isExportModalOpen = true"
              class="w-full py-2 px-2 bg-[#5138ed] text-white text-[11px] font-bold rounded-xl hover:bg-[#4530d1] transition-colors flex items-center justify-center gap-1.5 shadow-xs min-h-[38px] cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              <span>Export Report</span>
            </button>
            <button 
              @click="isGuidelinesModalOpen = true"
              class="w-full py-2 px-2 bg-white border border-slate-200 text-slate-600 text-[11px] font-semibold rounded-xl hover:bg-slate-50 transition-colors flex items-center justify-center gap-1.5 min-h-[38px] cursor-pointer truncate"
            >
              <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <span class="truncate">Guidelines</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main List Container -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs flex flex-col min-w-0">

      <!-- Filter Bar -->
      <div class="p-3.5 sm:p-4 border-b border-slate-100 flex flex-wrap items-center gap-2.5 sm:gap-3 bg-slate-50/50">
        <!-- Search Input -->
        <div class="relative w-full md:w-auto flex-1 min-w-[220px] max-w-md">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search instructor, course, code..."
            class="w-full pl-9 pr-8 py-2 bg-white border border-slate-200 rounded-lg text-xs sm:text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors shadow-xs"
          />
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Department Dropdown -->
        <div class="relative flex-1 sm:flex-none min-w-[140px]">
          <select 
            v-model="selectedDepartment" 
            class="w-full appearance-none pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-lg text-xs sm:text-[13px] text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] cursor-pointer shadow-xs"
          >
            <option v-for="dept in departmentsList" :key="dept" :value="dept">{{ dept }}</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>

        <!-- Status Dropdown -->
        <div class="relative flex-1 sm:flex-none min-w-[150px]">
          <select 
            v-model="selectedStatus" 
            class="w-full appearance-none pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-lg text-xs sm:text-[13px] text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] cursor-pointer shadow-xs"
          >
            <option value="All Statuses">All Submissions ({{ semesterInfo.total }})</option>
            <option value="Pending">Pending Review ({{ semesterInfo.pendingReview }})</option>
            <option value="Approved">Approved ({{ semesterInfo.approved }})</option>
            <option value="Correction Required">Correction Required ({{ semesterInfo.correctionRequired }})</option>
            <option value="Rejected">Rejected ({{ semesterInfo.rejected || 0 }})</option>
            <option value="Reopened">Reopened ({{ semesterInfo.reopened || 0 }})</option>
            <option value="Not Submitted">Not Submitted ({{ semesterInfo.notSubmitted || 0 }})</option>
            <option value="All Instructors">All Instructors ({{ (semesterInfo.total || 0) + (semesterInfo.notSubmitted || 0) }})</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>

        <!-- Academic Term Dropdown -->
        <div class="relative w-full sm:w-auto sm:ml-auto min-w-[200px]">
          <select 
            v-model="selectedSemester" 
            class="w-full appearance-none pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-lg text-xs sm:text-[13px] text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] cursor-pointer shadow-xs"
          >
            <option v-for="sem in semestersList" :key="sem" :value="sem">{{ sem }}</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>

        <!-- Reset Filters Button -->
        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
          title="Reset all filters"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
          <span>Reset</span>
        </button>
      </div>

      <!-- Desktop Table Container -->
      <div class="hidden lg:block overflow-x-auto min-w-0 w-full">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max">
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
                  <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-1">
                    <svg class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                  </div>
                  <span class="text-[14px] font-bold text-slate-700">No Semester Submissions Found</span>
                  <span class="text-[12px] text-slate-500 max-w-md">No instructors matched your active filters for {{ selectedSemester }}.</span>
                  <div class="flex items-center gap-2 mt-3">
                    <button
                      @click="syncRealData"
                      class="px-4 py-2 bg-[#5138ed] text-white rounded-xl text-[12px] font-bold hover:bg-[#432dd4] transition-colors shadow-xs cursor-pointer flex items-center gap-1.5"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                      <span>Sync Real Data</span>
                    </button>
                    <button
                      v-if="selectedSemester !== 'All Academic Terms'"
                      @click="selectedSemester = 'All Academic Terms'"
                      class="px-4 py-2 bg-indigo-50 text-[#5138ed] border border-indigo-100 rounded-xl text-[12px] font-bold hover:bg-indigo-100 transition-colors shadow-xs cursor-pointer"
                    >
                      View All Academic Terms
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
              @click="openViewDetailsModal(inst)"
            >
              <!-- 1: Row Number -->
              <td class="py-3.5 px-4 text-[12px] font-bold text-slate-400">
                {{ (currentPage - 1) * itemsPerPage + index + 1 }}
              </td>

              <!-- 2: Instructor (Avatar, Name, Email) -->
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-3">
                  <div :class="['w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-extrabold shrink-0 shadow-xs', inst.color]">
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
                    <span class="font-mono text-slate-600 font-semibold bg-slate-100 px-1 rounded">{{ inst.course_code }}</span> &bull; {{ inst.department }}
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

              <!-- 7: Status Badge & Access State -->
              <td class="py-3.5 px-4">
                <div class="flex flex-col gap-1 items-start">
                  <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border whitespace-nowrap', getStatusBadge(inst.status)]">
                    <svg v-if="inst.status === 'Approved'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    <svg v-else-if="inst.status === 'Pending'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <svg v-else-if="inst.status === 'Correction Required'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <svg v-else-if="inst.status === 'Rejected'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    {{ inst.status }}
                  </span>
                  <span
                    v-if="inst.status === 'Approved'"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-semibold border"
                    :class="inst.is_locked ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="inst.is_locked ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                    Access: {{ inst.is_locked ? 'Locked' : 'Open' }}
                  </span>
                </div>
              </td>

              <!-- 8: Actions Dropdown Menu -->
              <td class="py-3.5 px-4 text-center" @click.stop>
                <div class="relative inline-block text-left">
                  <button
                    @click.stop="toggleMenu(inst.id)"
                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                    title="Actions"
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
                    class="absolute right-0 top-9 z-50 w-56 bg-white border border-slate-200 rounded-xl shadow-xl py-1 text-left animate-in fade-in zoom-in-95 duration-100"
                  >
                    <!-- 1. View Details -->
                    <button
                      @click="openViewDetailsModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-[#5138ed] transition-colors flex items-center gap-2.5 cursor-pointer"
                    >
                      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                      <span>View Details</span>
                    </button>

                    <!-- 2. Review Submission -->
                    <button
                      v-if="inst.is_submitted"
                      @click="openReviewModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-[#5138ed] transition-colors flex items-center gap-2.5 cursor-pointer"
                    >
                      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                      <span>Review Submission</span>
                    </button>

                    <div v-if="inst.is_submitted" class="border-t border-slate-100 my-1"></div>

                    <!-- 3. Approve -->
                    <button
                      v-if="inst.is_submitted && inst.status !== 'Approved'"
                      @click="openApproveModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                    >
                      <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                      <span>Approve</span>
                    </button>

                    <!-- 4. Request Correction -->
                    <button
                      v-if="inst.is_submitted && inst.status !== 'Correction Required'"
                      @click="openCorrectionModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-orange-600 hover:bg-orange-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                    >
                      <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                      <span>Request Correction</span>
                    </button>

                    <!-- 5. Close Semester (Lock) / Reopen Semester (Unlock) State-Aware Action -->
                    <!-- When locked: display Reopen Semester (Unlock) -->
                    <button
                      v-if="inst.is_locked"
                      @click="openReopenModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-cyan-700 hover:bg-cyan-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                    >
                      <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                      <span>Reopen Semester (Unlock)</span>
                    </button>

                    <!-- When NOT locked: display Close Semester (Lock) ONLY AFTER APPROVAL -->
                    <button
                      v-else-if="canCloseSemester(inst)"
                      @click="openCloseSemesterModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-amber-700 hover:bg-amber-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                    >
                      <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                      <span>Close Semester (Lock)</span>
                    </button>

                    <div v-if="inst.is_submitted" class="border-t border-slate-100 my-1"></div>

                    <!-- 6. Reject -->
                    <button
                      v-if="inst.is_submitted && inst.status !== 'Rejected'"
                      @click="openRejectModal(inst)"
                      class="w-full px-4 py-2.5 text-left text-[12px] font-semibold text-rose-600 hover:bg-rose-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                    >
                      <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                      <span>Reject</span>
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile & Tablet Card List -->
      <div class="lg:hidden divide-y divide-slate-100 bg-white">
        <!-- Loading State -->
        <div v-if="isLoading" class="py-12 text-center text-slate-400">
          <div class="flex flex-col items-center gap-3">
            <div class="animate-spin w-8 h-8 border-2 border-[#5138ed] border-t-transparent rounded-full"></div>
            <span class="text-xs font-semibold text-slate-500">Loading semester submissions...</span>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredInstructors.length === 0" class="py-12 text-center text-slate-400 px-4">
          <div class="flex flex-col items-center gap-2">
            <svg class="w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <span class="text-sm font-bold text-slate-700">No Semester Submissions Found</span>
            <span class="text-xs text-slate-500">No instructors matched your active filters.</span>
          </div>
        </div>

        <!-- Real Cards -->
        <div 
          v-else 
          v-for="inst in paginatedInstructors" 
          :key="inst.id"
          class="p-4 space-y-3 hover:bg-slate-50/50 transition-colors"
        >
          <!-- Instructor Info & Status -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
              <div :class="['w-10 h-10 rounded-full flex items-center justify-center text-xs font-extrabold shrink-0 shadow-xs', inst.color]">
                {{ inst.initials }}
              </div>
              <div class="min-w-0">
                <h4 class="text-sm font-bold text-slate-800 truncate">{{ inst.name }}</h4>
                <p class="text-xs text-slate-400 truncate">{{ inst.email }}</p>
              </div>
            </div>
            <!-- Status Badge & Access State -->
            <div class="flex flex-col items-end gap-1">
              <span :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold border shrink-0', getStatusBadge(inst.status)]">
                {{ inst.status }}
              </span>
              <span
                v-if="inst.status === 'Approved'"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-semibold border"
                :class="inst.is_locked ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="inst.is_locked ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                Access: {{ inst.is_locked ? 'Locked' : 'Open' }}
              </span>
            </div>
          </div>

          <!-- Course & Department Details -->
          <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Course:</span>
              <span class="font-bold text-slate-800 text-right truncate max-w-[200px]">{{ inst.course || inst.department }}</span>
            </div>
            <div v-if="inst.course_code" class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Code:</span>
              <span class="font-mono font-semibold text-slate-700">{{ inst.course_code }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Department:</span>
              <span class="font-medium text-slate-700">{{ inst.department }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Section:</span>
              <span class="font-bold text-[#5138ed]">{{ inst.section || 'Section A' }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Credit Hours:</span>
              <span class="font-semibold text-slate-700">{{ inst.credit || inst.courses || 4 }} Credits</span>
            </div>
            <div class="flex items-center justify-between pt-1 border-t border-slate-200/60">
              <span class="text-slate-500 font-medium">Submitted:</span>
              <span class="font-semibold text-slate-700">
                {{ inst.submitted_date || (inst.submitted?.includes('\n') ? inst.submitted.split('\n')[0] : inst.submitted) }}
                {{ inst.submitted_time ? `• ${inst.submitted_time}` : '' }}
              </span>
            </div>
          </div>

          <!-- Action Buttons (Touch Friendly min-h-[44px]) -->
          <div class="pt-1 flex flex-wrap items-center gap-2">
            <!-- 1. View Details -->
            <button
              @click="openViewDetailsModal(inst)"
              class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-50 text-[#5138ed] border border-indigo-100 rounded-xl text-xs font-bold hover:bg-indigo-100 transition-colors min-h-[44px] cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <span>View Details</span>
            </button>

            <!-- 2. Review Submission -->
            <button
              @click="openReviewModal(inst)"
              class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-50 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold hover:bg-slate-100 transition-colors min-h-[44px] cursor-pointer"
              title="Review Submission"
            >
              <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
              <span>Review</span>
            </button>

            <!-- Lock / Unlock Toggle Action -->
            <button
              v-if="inst.is_locked"
              @click="openReopenModal(inst)"
              class="inline-flex items-center justify-center px-3 py-2 bg-cyan-50 text-cyan-700 border border-cyan-200 rounded-xl text-xs font-bold hover:bg-cyan-100 transition-colors min-h-[44px] cursor-pointer"
              title="Reopen Semester (Unlock)"
            >
              <svg class="w-4 h-4 mr-1 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
              <span>Unlock</span>
            </button>
            <button
              v-else-if="canCloseSemester(inst)"
              @click="openCloseSemesterModal(inst)"
              class="inline-flex items-center justify-center px-3 py-2 bg-amber-50 text-amber-700 border border-amber-200 rounded-xl text-xs font-bold hover:bg-amber-100 transition-colors min-h-[44px] cursor-pointer"
              title="Close Semester (Lock)"
            >
              <svg class="w-4 h-4 mr-1 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              <span>Lock</span>
            </button>

            <template v-if="inst.is_submitted">
              <!-- 3. Approve -->
              <button
                v-if="inst.status !== 'Approved'"
                @click="openApproveModal(inst)"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition-colors min-h-[44px] cursor-pointer shadow-xs"
                title="Approve"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>Approve</span>
              </button>

              <!-- 4. Request Correction -->
              <button
                v-if="inst.status !== 'Correction Required'"
                @click="openCorrectionModal(inst)"
                class="inline-flex items-center justify-center px-3 py-2 bg-orange-50 text-orange-600 border border-orange-200 rounded-xl text-xs font-bold hover:bg-orange-100 transition-colors min-h-[44px] cursor-pointer"
                title="Request Correction"
              >
                Correction
              </button>

              <!-- 6. Reject -->
              <button
                v-if="inst.status !== 'Rejected'"
                @click="openRejectModal(inst)"
                class="inline-flex items-center justify-center px-3 py-2 bg-rose-50 text-rose-600 border border-rose-200 rounded-xl text-xs font-bold hover:bg-rose-100 transition-colors min-h-[44px] cursor-pointer"
                title="Reject Submission"
              >
                Reject
              </button>
            </template>
          </div>
        </div>
      </div>

      <!-- Table Footer / Pagination -->
      <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white">
        <span class="text-xs font-medium text-slate-500 text-center sm:text-left">
          Showing {{ filteredInstructors.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }} to 
          {{ Math.min(currentPage * itemsPerPage, filteredInstructors.length) }} of 
          {{ filteredInstructors.length }} submissions
        </span>

        <div v-if="totalPages > 1" class="flex items-center gap-1.5">
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 bg-white hover:bg-slate-50 hover:text-slate-600 disabled:opacity-40 transition-colors cursor-pointer min-h-[36px] min-w-[36px]"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
          </button>
          
          <button
            v-for="p in totalPages"
            :key="p"
            @click="goToPage(p)"
            :class="[
              'w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-colors cursor-pointer min-h-[36px] min-w-[36px]',
              p === currentPage 
                ? 'bg-[#5138ed] text-white shadow-xs' 
                : 'border border-slate-200 text-slate-600 bg-white hover:bg-slate-50'
            ]"
          >
            {{ p }}
          </button>

          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 bg-white hover:bg-slate-50 hover:text-slate-600 disabled:opacity-40 transition-colors cursor-pointer min-h-[36px] min-w-[36px]"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Semester Submission Details Modal (Comprehensive View Details) -->
    <div
      v-if="viewDetailsModal.open && viewDetailsModal.data"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-3 sm:p-5 overflow-y-auto"
    >
      <div class="bg-white rounded-2xl max-w-5xl w-full p-4 sm:p-6 shadow-2xl border border-slate-100 my-auto max-h-[94vh] flex flex-col">
        
        <!-- Modal Top Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-4 mb-4 shrink-0">
          <div class="flex items-center gap-3 min-w-0">
            <div :class="['w-11 h-11 sm:w-12 sm:h-12 rounded-full flex items-center justify-center text-xs sm:text-sm font-extrabold shrink-0 shadow-xs', viewDetailsModal.data.instructor.color || 'bg-indigo-100 text-indigo-700']">
              {{ viewDetailsModal.data.instructor.initials || 'IN' }}
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-base sm:text-lg font-bold text-slate-800 truncate">{{ viewDetailsModal.data.instructor.name }}</h3>
                <span :class="['px-2.5 py-0.5 rounded-full text-[11px] font-bold border shrink-0', getStatusBadge(viewDetailsModal.data.submission.status)]">
                  {{ viewDetailsModal.data.submission.status }}
                </span>
                <span v-if="viewDetailsModal.data.submission.is_locked" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 shrink-0 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                  Locked (Read-Only)
                </span>
                <span v-else class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                  Editable (Open)
                </span>
              </div>
              <p class="text-xs text-slate-500 font-medium truncate mt-0.5">
                {{ viewDetailsModal.data.instructor.email }} &bull; {{ viewDetailsModal.data.instructor.department }} &bull; {{ viewDetailsModal.data.course.title }} ({{ viewDetailsModal.data.course.code }})
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0 ml-2">
            <button 
              @click="openViewDetailsModal(viewDetailsModal.data.instructor)" 
              class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Refresh Data"
            >
              <svg class="w-4 h-4" :class="viewDetailsModal.isLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
            <button 
              @click="viewDetailsModal.open = false" 
              class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
              title="Close Modal"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-1 border-b border-slate-100 mb-4 shrink-0 overflow-x-auto">
          <button
            @click="viewDetailsModal.activeTab = 'overview'"
            :class="[
              'px-4 py-2.5 text-xs font-bold border-b-2 transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap',
              viewDetailsModal.activeTab === 'overview'
                ? 'border-[#5138ed] text-[#5138ed] bg-indigo-50/50 rounded-t-lg'
                : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span>Overview & Performance</span>
          </button>

          <button
            @click="viewDetailsModal.activeTab = 'students'"
            :class="[
              'px-4 py-2.5 text-xs font-bold border-b-2 transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap',
              viewDetailsModal.activeTab === 'students'
                ? 'border-[#5138ed] text-[#5138ed] bg-indigo-50/50 rounded-t-lg'
                : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <span>Student Examination Records</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
              {{ viewDetailsModal.data.students?.length || 0 }}
            </span>
          </button>

          <button
            @click="viewDetailsModal.activeTab = 'checklist'"
            :class="[
              'px-4 py-2.5 text-xs font-bold border-b-2 transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap',
              viewDetailsModal.activeTab === 'checklist'
                ? 'border-[#5138ed] text-[#5138ed] bg-indigo-50/50 rounded-t-lg'
                : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Verification Checklist & Review History</span>
          </button>
        </div>

        <!-- Modal Body Content -->
        <div class="overflow-y-auto max-h-[calc(94vh-180px)] pr-1">

          <!-- Loading Indicator -->
          <div v-if="viewDetailsModal.isLoading" class="py-12 text-center text-slate-400">
            <div class="flex flex-col items-center gap-3">
              <div class="animate-spin w-8 h-8 border-2 border-[#5138ed] border-t-transparent rounded-full"></div>
              <span class="text-xs font-semibold text-slate-500">Fetching live student exam records and verification details...</span>
            </div>
          </div>

          <template v-else>
            <!-- TAB 1: OVERVIEW & PERFORMANCE -->
            <div v-if="viewDetailsModal.activeTab === 'overview'" class="space-y-4">
              <!-- Academic Metadata Grid -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Course & Code</span>
                  <span class="text-xs sm:text-[13px] font-bold text-slate-800 truncate block">{{ viewDetailsModal.data.course.title }}</span>
                  <span class="text-[11px] font-mono font-semibold text-[#5138ed] bg-indigo-50/60 px-1.5 py-0.5 rounded inline-block mt-0.5">
                    {{ viewDetailsModal.data.course.code || 'N/A' }}
                  </span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Section & Credit</span>
                  <span class="text-xs sm:text-[13px] font-bold text-slate-800 block">{{ viewDetailsModal.data.submission.section || 'Section A' }}</span>
                  <span class="text-[11px] text-slate-500 font-medium">{{ viewDetailsModal.data.course.credits }} Credit Hours &bull; {{ viewDetailsModal.data.course.level }}</span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Academic Term</span>
                  <span class="text-xs sm:text-[13px] font-bold text-slate-800 block">{{ viewDetailsModal.data.submission.academic_year }}</span>
                  <span class="text-[11px] text-slate-500 font-medium">{{ viewDetailsModal.data.submission.semester }}</span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Submission Date</span>
                  <span class="text-xs sm:text-[13px] font-bold text-slate-800 block">{{ viewDetailsModal.data.submission.submitted_at || '—' }}</span>
                  <span class="text-[10px] font-semibold text-slate-400">{{ viewDetailsModal.data.submission.is_locked ? 'Access: Locked' : 'Access: Open' }}</span>
                </div>
              </div>

              <!-- Reviewer & Decision Context Card -->
              <div class="border border-slate-100 rounded-xl p-3.5 bg-slate-50/60 space-y-2.5">
                <div class="flex items-center justify-between flex-wrap gap-2">
                  <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Review & Approval Status</span>
                  <span class="text-xs font-semibold text-slate-500">
                    Workflow Status: <strong class="text-slate-800">{{ viewDetailsModal.data.submission.status }}</strong>
                  </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                  <div class="bg-white p-3 rounded-xl border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-500 block mb-1">Approval & Reviewer Information</span>
                    <p v-if="viewDetailsModal.data.submission.approved_at" class="text-slate-700">
                      Approved on <strong class="text-emerald-700">{{ viewDetailsModal.data.submission.approved_at }}</strong>
                      <span v-if="viewDetailsModal.data.submission.approved_by"> by <strong>{{ viewDetailsModal.data.submission.approved_by.name || viewDetailsModal.data.submission.approved_by }}</strong></span>
                    </p>
                    <p v-else class="text-slate-400 italic">No formal approval recorded yet.</p>
                  </div>

                  <div class="bg-white p-3 rounded-xl border border-slate-100">
                    <span class="text-[11px] font-bold text-slate-500 block mb-1">Locking & Access Status</span>
                    <p v-if="viewDetailsModal.data.submission.is_locked" class="text-slate-700">
                      <strong class="text-amber-700">Locked</strong> &bull; Instructor portal is restricted to READ-ONLY mode for this course workspace.
                    </p>
                    <p v-else class="text-slate-700">
                      <strong class="text-emerald-700">Open</strong> &bull; Instructor has active editing access to this course workspace.
                    </p>
                  </div>
                </div>

                <!-- Correction Remarks if present -->
                <div v-if="viewDetailsModal.data.submission.remarks" class="p-3 bg-orange-50 border border-orange-200 rounded-xl text-xs text-orange-900">
                  <span class="font-bold block text-orange-800 mb-0.5">Department Head Remarks / Instructions:</span>
                  <p>{{ viewDetailsModal.data.submission.remarks }}</p>
                </div>

                <!-- Reopen Reason if present -->
                <div v-if="viewDetailsModal.data.submission.reopen_reason" class="p-3 bg-cyan-50 border border-cyan-200 rounded-xl text-xs text-cyan-900">
                  <span class="font-bold block text-cyan-800 mb-0.5">Reopening Audit Rationale (by {{ viewDetailsModal.data.submission.reopened_by?.name || 'Department Head' }} on {{ viewDetailsModal.data.submission.reopened_at }}):</span>
                  <p>{{ viewDetailsModal.data.submission.reopen_reason }}</p>
                </div>
              </div>

              <!-- Performance KPI Grid -->
              <div class="border border-slate-100 rounded-xl p-3 sm:p-4 bg-gradient-to-r from-indigo-50/30 to-purple-50/20">
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">Class Examination Performance Metrics</h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2.5 sm:gap-3 text-center">
                  <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-xs">
                    <span class="text-base sm:text-lg font-black text-slate-800 block">{{ viewDetailsModal.data.kpis?.total_enrolled || 0 }}</span>
                    <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Enrolled Students</span>
                  </div>
                  <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-xs">
                    <span class="text-base sm:text-lg font-black text-slate-800 block">{{ viewDetailsModal.data.kpis?.total_exams || 0 }}</span>
                    <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Exams Conducted</span>
                  </div>
                  <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-xs">
                    <span class="text-base sm:text-lg font-black text-slate-800 block">{{ viewDetailsModal.data.kpis?.total_graded || 0 }}</span>
                    <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Graded Attempts</span>
                  </div>
                  <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-xs">
                    <span class="text-base sm:text-lg font-black text-emerald-600 block">{{ viewDetailsModal.data.kpis?.avg_score || 0 }}%</span>
                    <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Class Average</span>
                  </div>
                  <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-xs">
                    <span class="text-base sm:text-lg font-black text-[#5138ed] block">{{ viewDetailsModal.data.kpis?.pass_rate || 0 }}%</span>
                    <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Pass Rate</span>
                  </div>
                  <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-xs">
                    <span class="text-base sm:text-lg font-black text-slate-700 block">
                      {{ viewDetailsModal.data.kpis?.highest_score || 0 }}% / {{ viewDetailsModal.data.kpis?.lowest_score || 0 }}%
                    </span>
                    <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">High / Low Score</span>
                  </div>
                </div>
              </div>

              <!-- Conducted Examinations Table -->
              <div class="border border-slate-100 rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-700">Examinations Conducted for Course</span>
                  <span class="text-[11px] text-slate-400">{{ viewDetailsModal.data.exams?.length || 0 }} Exam(s)</span>
                </div>
                <div class="overflow-x-auto">
                  <table class="w-full text-left text-xs whitespace-nowrap">
                    <thead class="bg-slate-50/50 text-[11px] text-slate-400 font-bold border-b border-slate-100">
                      <tr>
                        <th class="py-2.5 px-3">Exam Title</th>
                        <th class="py-2.5 px-3">Duration</th>
                        <th class="py-2.5 px-3 text-center">Total Marks</th>
                        <th class="py-2.5 px-3 text-center">Attempts</th>
                        <th class="py-2.5 px-3 text-center">Class Avg</th>
                        <th class="py-2.5 px-3 text-center">Pass Rate</th>
                        <th class="py-2.5 px-3">Status</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-if="!viewDetailsModal.data.exams || viewDetailsModal.data.exams.length === 0">
                        <td colspan="7" class="py-6 text-center text-slate-400 italic">No examinations recorded for this semester yet.</td>
                      </tr>
                      <tr v-else v-for="ex in viewDetailsModal.data.exams" :key="ex.id" class="hover:bg-slate-50/50">
                        <td class="py-2.5 px-3 font-semibold text-slate-800">{{ ex.title }}</td>
                        <td class="py-2.5 px-3 text-slate-500">{{ ex.duration_minutes }} mins</td>
                        <td class="py-2.5 px-3 text-center font-mono font-semibold">{{ ex.total_marks }}</td>
                        <td class="py-2.5 px-3 text-center font-bold text-slate-700">{{ ex.attempts_count }}</td>
                        <td class="py-2.5 px-3 text-center font-semibold text-emerald-600">{{ ex.avg_score }}%</td>
                        <td class="py-2.5 px-3 text-center font-semibold text-[#5138ed]">{{ ex.pass_rate }}%</td>
                        <td class="py-2.5 px-3">
                          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="ex.status === 'completed' || ex.status === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                            {{ ex.status }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- TAB 2: STUDENT EXAMINATION RECORDS -->
            <div v-else-if="viewDetailsModal.activeTab === 'students'" class="space-y-3.5">
              <!-- Filter & Search Toolbar -->
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                <div class="relative flex-1 min-w-[200px]">
                  <input
                    v-model="viewDetailsModal.searchStudent"
                    type="text"
                    placeholder="Search student by name or ID number..."
                    class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:border-[#5138ed]"
                  />
                  <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>

                <div class="flex items-center gap-2">
                  <select
                    v-model="viewDetailsModal.statusFilter"
                    class="appearance-none pl-3 pr-7 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] cursor-pointer"
                  >
                    <option value="all">All Evaluation States</option>
                    <option value="completed">Completed Grading</option>
                    <option value="in_progress">Partially Graded</option>
                    <option value="not_attempted">Not Attempted</option>
                  </select>

                  <span class="text-xs font-semibold text-slate-500 whitespace-nowrap">
                    Showing {{ filteredModalStudents.length }} of {{ viewDetailsModal.data.students?.length || 0 }} Students
                  </span>
                </div>
              </div>

              <!-- Enrolled Students Table -->
              <div class="border border-slate-100 rounded-xl overflow-hidden bg-white">
                <div class="overflow-x-auto">
                  <table class="w-full text-left text-xs whitespace-nowrap">
                    <thead class="bg-slate-50 text-[11px] text-slate-400 font-bold border-b border-slate-100 uppercase tracking-wider">
                      <tr>
                        <th class="py-2.5 px-3 w-8">#</th>
                        <th class="py-2.5 px-3">Student Name & ID</th>
                        <th class="py-2.5 px-3">Section</th>
                        <th class="py-2.5 px-3 text-center">Score / Max</th>
                        <th class="py-2.5 px-3 text-center">Percentage</th>
                        <th class="py-2.5 px-3 text-center">Letter Grade</th>
                        <th class="py-2.5 px-3">Grading Status</th>
                        <th class="py-2.5 px-3">Publication</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-if="filteredModalStudents.length === 0">
                        <td colspan="8" class="py-10 text-center text-slate-400">
                          <p class="font-semibold text-slate-600">No students matched your search criteria.</p>
                          <span class="text-[11px]">Try clearing your search query or filter.</span>
                        </td>
                      </tr>
                      <tr v-else v-for="(st, sIdx) in filteredModalStudents" :key="st.id" class="hover:bg-slate-50/50">
                        <td class="py-2.5 px-3 font-bold text-slate-400">{{ Number(sIdx) + 1 }}</td>
                        <td class="py-2.5 px-3">
                          <div class="flex flex-col">
                            <span class="font-bold text-slate-800">{{ st.name }}</span>
                            <span class="font-mono text-[11px] text-slate-400">{{ st.id_no }}</span>
                          </div>
                        </td>
                        <td class="py-2.5 px-3">
                          <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">
                            {{ st.section }}
                          </span>
                        </td>
                        <td class="py-2.5 px-3 text-center font-bold font-mono">
                          <span class="text-slate-800">{{ st.total_score }}</span>
                          <span class="text-slate-400 font-normal"> / {{ st.max_marks }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-center font-semibold" :class="st.overall_percentage >= 50 ? 'text-emerald-600' : 'text-rose-600'">
                          {{ st.overall_percentage }}%
                        </td>
                        <td class="py-2.5 px-3 text-center">
                          <span :class="['px-2 py-0.5 rounded-md text-[11px] font-extrabold border', getGradeBadge(st.letter_grade)]">
                            {{ st.letter_grade }}
                          </span>
                        </td>
                        <td class="py-2.5 px-3">
                          <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                            :class="st.status === 'Completed' ? 'bg-emerald-50 text-emerald-700' : (st.status === 'Partially Graded' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500')"
                          >
                            {{ st.status }}
                          </span>
                        </td>
                        <td class="py-2.5 px-3">
                          <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                            :class="st.publication_status === 'Published' ? 'bg-indigo-50 text-[#5138ed]' : 'bg-slate-100 text-slate-500'"
                          >
                            <span class="w-1.5 h-1.5 rounded-full" :class="st.publication_status === 'Published' ? 'bg-[#5138ed]' : 'bg-slate-400'"></span>
                            {{ st.publication_status }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- TAB 3: VERIFICATION CHECKLIST & REVIEW HISTORY -->
            <div v-else-if="viewDetailsModal.activeTab === 'checklist'" class="space-y-4">
              <!-- Checklist Cards -->
              <div>
                <h4 class="text-xs font-bold text-slate-700 mb-2.5">Workflow Verification Checklist</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div
                    v-for="(chk, cKey) in viewDetailsModal.data.checklist"
                    :key="cKey"
                    class="p-3.5 rounded-xl border flex items-start gap-3 bg-white"
                    :class="chk.completed ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200 bg-slate-50/50'"
                  >
                    <div
                      class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                      :class="chk.completed ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-200 text-slate-400'"
                    >
                      <svg v-if="chk.completed" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                      <span v-else class="text-xs font-bold">!</span>
                    </div>
                    <div class="min-w-0">
                      <span class="text-xs font-bold text-slate-800 block">{{ chk.label }}</span>
                      <p class="text-[11px] text-slate-500 mt-0.5">{{ chk.detail }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- History & Audit Trail -->
              <div class="border border-slate-100 rounded-xl p-4 bg-slate-50">
                <h4 class="text-xs font-bold text-slate-700 mb-3">Submission Record Audit Trail</h4>
                <div class="space-y-3 text-xs">
                  <div class="flex items-center justify-between py-1.5 border-b border-slate-200/60">
                    <span class="text-slate-500">Record Created</span>
                    <span class="font-semibold text-slate-700">{{ viewDetailsModal.data.submission.created_at || '—' }}</span>
                  </div>
                  <div class="flex items-center justify-between py-1.5 border-b border-slate-200/60">
                    <span class="text-slate-500">Instructor Submission</span>
                    <span class="font-semibold text-slate-700">{{ viewDetailsModal.data.submission.submitted_at || 'Not submitted' }}</span>
                  </div>
                  <div class="flex items-center justify-between py-1.5 border-b border-slate-200/60">
                    <span class="text-slate-500">Department Approval & Locking</span>
                    <span class="font-semibold" :class="viewDetailsModal.data.submission.approved_at ? 'text-emerald-700' : 'text-slate-400'">
                      {{ viewDetailsModal.data.submission.approved_at || 'Pending Approval' }}
                      <span v-if="viewDetailsModal.data.submission.approved_by"> (by {{ viewDetailsModal.data.submission.approved_by.name || viewDetailsModal.data.submission.approved_by }})</span>
                    </span>
                  </div>
                  <div v-if="viewDetailsModal.data.submission.reopened_at" class="flex items-center justify-between py-1.5 border-b border-slate-200/60">
                    <span class="text-slate-500">Reopened / Unlocked</span>
                    <span class="font-semibold text-cyan-700">
                      {{ viewDetailsModal.data.submission.reopened_at }} (by {{ viewDetailsModal.data.submission.reopened_by?.name || 'Department Head' }})
                    </span>
                  </div>
                  <div class="flex items-center justify-between py-1.5">
                    <span class="text-slate-500">Lock Enforcement Status</span>
                    <span class="font-bold" :class="viewDetailsModal.data.submission.is_locked ? 'text-amber-700' : 'text-emerald-600'">
                      {{ viewDetailsModal.data.submission.is_locked ? 'Active Lock Enforced' : 'Edit Access Active' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Modal Footer Actions -->
        <div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-100 pt-3.5 mt-4 gap-2.5 shrink-0">
          <button
            @click="viewDetailsModal.open = false"
            class="w-full sm:w-auto px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors min-h-[40px] cursor-pointer"
          >
            Close
          </button>

          <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-end">
            <!-- 1. When locked: display Reopen Semester (Unlock) -->
            <button
              v-if="viewDetailsItem?.is_locked"
              @click="openReopenModal(viewDetailsItem)"
              class="px-4 py-2 rounded-xl text-xs font-bold text-cyan-700 bg-cyan-50 hover:bg-cyan-100 transition-colors flex items-center gap-1.5 min-h-[40px] cursor-pointer"
            >
              <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
              <span>Reopen Semester (Unlock)</span>
            </button>

            <!-- 2. When unlocked AND approved: display Close Semester (Lock) ONLY AFTER APPROVAL -->
            <button
              v-else-if="canCloseSemester(viewDetailsItem)"
              @click="openCloseSemesterModal(viewDetailsItem)"
              class="px-4 py-2 rounded-xl text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors flex items-center gap-1.5 min-h-[40px] cursor-pointer"
            >
              <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              <span>Close Semester (Lock)</span>
            </button>

            <!-- 3. If submission not approved, allow Approve & Lock, Request Correction, Reject -->
            <template v-if="!canCloseSemester(viewDetailsItem) && !viewDetailsItem?.is_locked">
              <button
                v-if="viewDetailsItem?.raw_status !== 'rejected'"
                @click="openRejectModal(viewDetailsItem)"
                class="px-3.5 py-2 rounded-xl text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-colors min-h-[40px] cursor-pointer"
              >
                Reject
              </button>
              <button
                v-if="viewDetailsItem?.raw_status !== 'correction_required'"
                @click="openCorrectionModal(viewDetailsItem)"
                class="px-3.5 py-2 rounded-xl text-xs font-bold text-orange-600 bg-orange-50 hover:bg-orange-100 transition-colors min-h-[40px] cursor-pointer"
              >
                Request Correction
              </button>
              <button
                @click="openApproveModal(viewDetailsItem)"
                class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-xs min-h-[40px] cursor-pointer flex items-center gap-1.5"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>Approve</span>
              </button>
            </template>
          </div>
        </div>

      </div>
    </div>

    <!-- Review Submission Detail Modal -->
    <div
      v-if="reviewModal.open && reviewModal.instructor"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4 overflow-y-auto"
    >
      <div class="bg-white rounded-2xl max-w-[95vw] sm:max-w-xl md:max-w-2xl w-full p-4 sm:p-6 shadow-2xl border border-slate-100 my-auto max-h-[92vh] flex flex-col">
        
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-3 sm:pb-4 mb-4 sm:mb-5 shrink-0">
          <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
            <div :class="['w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center text-xs sm:text-sm font-extrabold shrink-0 shadow-xs', reviewModal.instructor.color]">
              {{ reviewModal.instructor.initials }}
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-base sm:text-lg font-bold text-slate-800 truncate">{{ reviewModal.instructor.name }}</h3>
                <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold border shrink-0', getStatusBadge(reviewModal.instructor.status)]">
                  {{ reviewModal.instructor.status }}
                </span>
                <span v-if="reviewModal.instructor.is_locked" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200 shrink-0 flex items-center gap-1">
                  <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                  Locked
                </span>
              </div>
              <p class="text-xs text-slate-500 font-medium truncate">{{ reviewModal.instructor.email }} &bull; {{ reviewModal.instructor.department }}</p>
            </div>
          </div>
          <button 
            @click="reviewModal.open = false" 
            class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer shrink-0 ml-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="overflow-y-auto max-h-[calc(92vh-140px)] pr-1">
          <!-- Academic Submission Overview Cards -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 mb-4 sm:mb-5">
            <div class="bg-slate-50 p-2.5 sm:p-3 rounded-xl border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Course</span>
              <span class="text-xs sm:text-[13px] font-bold text-slate-800 truncate block">{{ reviewModal.instructor.course }}</span>
              <span class="text-[10px] font-mono text-slate-500 font-semibold">{{ reviewModal.instructor.course_code || 'N/A' }}</span>
            </div>
            <div class="bg-slate-50 p-2.5 sm:p-3 rounded-xl border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Section & Credit</span>
              <span class="text-xs sm:text-[13px] font-bold text-slate-800 block">{{ reviewModal.instructor.section }}</span>
              <span class="text-[10px] text-slate-500 font-medium">{{ reviewModal.instructor.credit }} Credit Hours</span>
            </div>
            <div class="bg-slate-50 p-2.5 sm:p-3 rounded-xl border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Submitted Date</span>
              <span class="text-xs sm:text-[13px] font-bold text-slate-800 block">{{ reviewModal.instructor.submitted_date }}</span>
              <span class="text-[10px] text-slate-500 font-medium">{{ reviewModal.instructor.submitted_time }}</span>
            </div>
            <div class="bg-slate-50 p-2.5 sm:p-3 rounded-xl border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide block mb-1">Academic Term</span>
              <span class="text-xs sm:text-[13px] font-bold text-slate-800 block">{{ reviewModal.instructor.academic_year || semesterInfo.academicYear }}</span>
              <span class="text-[10px] text-slate-500 font-medium">{{ reviewModal.instructor.semester || semesterInfo.semester }}</span>
            </div>
          </div>

          <!-- Quick link to full View Details -->
          <div class="mb-4 sm:mb-5">
            <button 
              type="button"
              @click="openViewDetailsFromReview" 
              class="w-full py-2.5 px-3.5 rounded-xl bg-indigo-50/80 hover:bg-indigo-100/90 border border-indigo-200/80 text-[#5138ed] text-xs font-bold flex items-center justify-between transition-all cursor-pointer shadow-xs group"
            >
              <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[#5138ed] group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>View Full Student Examination Scores & Grade Roster</span>
              </div>
              <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-[#5138ed]">
                Open Details
                <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </span>
            </button>
          </div>

          <!-- Performance Metrics Grid -->
          <div class="border border-slate-100 rounded-xl p-3 sm:p-4 mb-4 sm:mb-5 bg-gradient-to-r from-indigo-50/30 to-purple-50/20">
            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5 sm:mb-3">Academic Performance & Records</h4>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4 text-center">
              <div class="bg-white p-2 sm:p-2.5 rounded-lg border border-slate-100 shadow-xs">
                <span class="text-base sm:text-[18px] font-black text-slate-800 block">{{ reviewModal.instructor.exams_count ?? 0 }}</span>
                <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Exams</span>
              </div>
              <div class="bg-white p-2 sm:p-2.5 rounded-lg border border-slate-100 shadow-xs">
                <span class="text-base sm:text-[18px] font-black text-slate-800 block">{{ reviewModal.instructor.results_submitted ?? 0 }}</span>
                <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Graded Attempts</span>
              </div>
              <div class="bg-white p-2 sm:p-2.5 rounded-lg border border-slate-100 shadow-xs">
                <span class="text-base sm:text-[18px] font-black text-emerald-600 block">{{ reviewModal.instructor.avg_score ?? 0 }}%</span>
                <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Class Average</span>
              </div>
              <div class="bg-white p-2 sm:p-2.5 rounded-lg border border-slate-100 shadow-xs">
                <span class="text-base sm:text-[18px] font-black text-[#5138ed] block">{{ reviewModal.instructor.pass_rate ?? 0 }}%</span>
                <span class="text-[10px] sm:text-[11px] font-medium text-slate-500">Pass Rate</span>
              </div>
            </div>
          </div>

          <!-- Checklist -->
          <div class="mb-4 sm:mb-5">
            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">Verification Checklist</h4>
            <div class="space-y-2 text-xs">
              <div class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-50 text-slate-700">
                <div class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                  <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <span class="font-medium">Academic Schedule: Registered for {{ reviewModal.instructor.academic_year || semesterInfo.academicYear }} ({{ reviewModal.instructor.semester || semesterInfo.semester }})</span>
              </div>
              <div class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-50 text-slate-700">
                <div class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                  <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <span class="font-medium">Assessments: {{ reviewModal.instructor.exams_count ?? 0 }} Exams Configured & Evaluated</span>
              </div>
              <div class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-50 text-slate-700">
                <div class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                  <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <span class="font-medium">Grading: {{ reviewModal.instructor.results_submitted ?? 0 }} Student Results Processed for Section {{ reviewModal.instructor.section }}</span>
              </div>
            </div>
          </div>

          <!-- Remarks Section if present -->
          <div v-if="reviewModal.instructor.remarks" class="mb-4 sm:mb-5 p-3 rounded-xl bg-amber-50/70 border border-amber-200">
            <span class="text-[11px] font-bold text-amber-800 uppercase tracking-wide block mb-1">Previous Remarks / Notes:</span>
            <p class="text-xs text-amber-900 font-medium">{{ reviewModal.instructor.remarks }}</p>
          </div>

          <!-- Reopen Reason if present -->
          <div v-if="reviewModal.instructor.reopen_reason" class="mb-4 sm:mb-5 p-3 rounded-xl bg-cyan-50/70 border border-cyan-200">
            <span class="text-[11px] font-bold text-cyan-800 uppercase tracking-wide block mb-1">Reopen Rationale:</span>
            <p class="text-xs text-cyan-900 font-medium">{{ reviewModal.instructor.reopen_reason }}</p>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-100 pt-3 sm:pt-4 gap-2.5 shrink-0">
          <button
            @click="reviewModal.open = false"
            class="w-full sm:w-auto px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors min-h-[40px] cursor-pointer"
          >
            Close
          </button>

          <div v-if="reviewModal.instructor.is_submitted" class="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-end">
            <button
              v-if="reviewModal.instructor.status !== 'Rejected'"
              @click="openRejectModal(reviewModal.instructor)"
              class="px-3.5 py-2 rounded-xl text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-colors min-h-[40px] cursor-pointer"
            >
              Reject
            </button>
            <button
              v-if="reviewModal.instructor.status !== 'Correction Required'"
              @click="openCorrectionModal(reviewModal.instructor)"
              class="px-3.5 py-2 rounded-xl text-xs font-bold text-orange-600 bg-orange-50 hover:bg-orange-100 transition-colors min-h-[40px] cursor-pointer"
            >
              Correction
            </button>
            <!-- Lock / Unlock Toggle Action -->
            <button
              v-if="reviewModal.instructor.is_locked"
              @click="openReopenModal(reviewModal.instructor)"
              class="px-3.5 py-2 rounded-xl text-xs font-bold text-cyan-700 bg-cyan-50 hover:bg-cyan-100 transition-colors flex items-center gap-1.5 min-h-[40px] cursor-pointer"
            >
              <svg class="w-3.5 h-3.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
              <span>Reopen Semester (Unlock)</span>
            </button>
            <button
              v-else-if="canCloseSemester(reviewModal.instructor)"
              @click="openCloseSemesterModal(reviewModal.instructor)"
              class="px-3.5 py-2 rounded-xl text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors flex items-center gap-1.5 min-h-[40px] cursor-pointer"
            >
              <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              <span>Close Semester (Lock)</span>
            </button>

            <!-- Approve -->
            <button
              v-if="reviewModal.instructor.status !== 'Approved'"
              @click="openApproveModal(reviewModal.instructor)"
              class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-xs min-h-[40px] cursor-pointer flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              <span>Approve</span>
            </button>
          </div>
          <div v-else class="text-xs text-slate-400 font-medium italic flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            Instructor has not submitted semester records yet.
          </div>
        </div>

      </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div
      v-if="approveModal.open && approveModal.instructor"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4"
    >
      <div class="bg-white rounded-2xl max-w-[95vw] sm:max-w-md w-full p-4 sm:p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-base font-bold text-slate-800 truncate">Approve Semester Submission</h3>
            <p class="text-xs text-slate-500 truncate">Instructor: {{ approveModal.instructor.name }}</p>
          </div>
        </div>

        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3.5 mb-4 text-xs text-emerald-900 space-y-1.5">
          <span class="font-bold block text-emerald-800">Verification & Access Policy:</span>
          <p>
            Approving this submission will officially mark semester examination and assessment records for 
            <strong>{{ approveModal.instructor.name }}</strong> ({{ approveModal.instructor.course }}) as <strong>Approved</strong>.
          </p>
          <p class="text-[11px] text-emerald-700">
            The instructor's semester workspace remains <strong>Open</strong> until you explicitly choose <strong>Close Semester (Lock)</strong>.
          </p>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            @click="approveModal.open = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors min-h-[40px] cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="confirmApprove"
            :disabled="isSubmittingAction"
            class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-xs disabled:opacity-50 min-h-[40px] cursor-pointer flex items-center gap-1.5"
          >
            <svg v-if="isSubmittingAction" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            Confirm Approval
          </button>
        </div>
      </div>
    </div>

    <!-- Export Report Options Modal -->
    <div
      v-if="isExportModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4"
    >
      <div class="bg-white rounded-2xl max-w-[95vw] sm:max-w-md w-full p-4 sm:p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-800">Export Semester Report</h3>
              <p class="text-[11px] text-slate-400">Download formatted semester report</p>
            </div>
          </div>
          <button @click="isExportModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <p class="text-xs text-slate-500 mb-4 font-medium">
          Choose your preferred export format. Includes instructor names, courses, section assignments, submission timestamps, and current review status.
        </p>

        <div class="space-y-2.5 sm:space-y-3 mb-5">
          <!-- PDF Option -->
          <button
            @click="triggerExport('pdf')"
            :disabled="isExporting"
            class="w-full p-3 rounded-xl border border-slate-200 hover:border-[#5138ed] hover:bg-indigo-50/40 transition-all flex items-center justify-between group text-left disabled:opacity-50 min-h-[44px] cursor-pointer"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs shrink-0 border border-rose-100">
                PDF
              </div>
              <div class="min-w-0">
                <span class="text-xs sm:text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors block truncate">Official Wollo University PDF Report</span>
                <span class="text-[11px] text-slate-400 truncate block">Printable landscape document</span>
              </div>
            </div>
            <svg class="w-4 h-4 text-slate-400 group-hover:text-[#5138ed] transition-colors shrink-0 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>

          <!-- Excel Option -->
          <button
            @click="triggerExport('excel')"
            :disabled="isExporting"
            class="w-full p-3 rounded-xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/40 transition-all flex items-center justify-between group text-left disabled:opacity-50 min-h-[44px] cursor-pointer"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xs shrink-0 border border-emerald-100">
                XLS
              </div>
              <div class="min-w-0">
                <span class="text-xs sm:text-[13px] font-bold text-slate-800 group-hover:text-emerald-700 transition-colors block truncate">Excel Spreadsheet (.xlsx)</span>
                <span class="text-[11px] text-slate-400 truncate block">Structured data spreadsheet</span>
              </div>
            </div>
            <svg class="w-4 h-4 text-slate-400 group-hover:text-emerald-600 transition-colors shrink-0 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>

          <!-- CSV Option -->
          <button
            @click="triggerExport('csv')"
            :disabled="isExporting"
            class="w-full p-3 rounded-xl border border-slate-200 hover:border-sky-500 hover:bg-sky-50/40 transition-all flex items-center justify-between group text-left disabled:opacity-50 min-h-[44px] cursor-pointer"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-xs shrink-0 border border-sky-100">
                CSV
              </div>
              <div class="min-w-0">
                <span class="text-xs sm:text-[13px] font-bold text-slate-800 group-hover:text-sky-700 transition-colors block truncate">Comma Separated Values (.csv)</span>
                <span class="text-[11px] text-slate-400 truncate block">Universal tabular format</span>
              </div>
            </div>
            <svg class="w-4 h-4 text-slate-400 group-hover:text-sky-600 transition-colors shrink-0 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>

        <div v-if="isExporting" class="flex items-center justify-center gap-2 py-2 text-xs font-semibold text-[#5138ed]">
          <div class="w-4 h-4 border-2 border-[#5138ed] border-t-transparent rounded-full animate-spin"></div>
          Generating export document...
        </div>

        <div class="flex justify-end pt-2 border-t border-slate-100">
          <button
            @click="isExportModalOpen = false"
            class="w-full sm:w-auto px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors min-h-[40px] cursor-pointer"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Request Correction Modal -->
    <div
      v-if="correctionModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4"
    >
      <div class="bg-white rounded-2xl max-w-[95vw] sm:max-w-md w-full p-4 sm:p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0 border border-orange-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-base font-bold text-slate-800 truncate">Request Correction</h3>
            <p class="text-xs text-slate-500 truncate">Instructor: {{ correctionModal.instructor?.name }}</p>
          </div>
        </div>

        <p class="text-xs text-slate-600 mb-3 leading-relaxed">
          Requesting corrections will unlock this semester record and allow the instructor to make revisions. Please specify clearly what needs adjustment.
        </p>

        <div class="mb-4">
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Feedback / Correction Remarks <span class="text-rose-500">*</span></label>
          <textarea
            v-model="correctionModal.remarks"
            rows="3"
            placeholder="Specify what needs correction (e.g. Missing practical scores, incorrect grade curve)..."
            class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:bg-white transition-colors"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            @click="correctionModal.open = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors min-h-[40px] cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="submitCorrection"
            :disabled="isSubmittingAction"
            class="px-5 py-2 rounded-xl text-xs font-bold bg-orange-500 text-white hover:bg-orange-600 transition-colors shadow-xs disabled:opacity-50 min-h-[40px] cursor-pointer flex items-center gap-1.5"
          >
            <svg v-if="isSubmittingAction" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Submit Request
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Submission Modal -->
    <div
      v-if="rejectModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4"
    >
      <div class="bg-white rounded-2xl max-w-[95vw] sm:max-w-md w-full p-4 sm:p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 border border-rose-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-base font-bold text-slate-800 truncate">Reject Submission</h3>
            <p class="text-xs text-slate-500 truncate">Instructor: {{ rejectModal.instructor?.name }}</p>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Reason for Rejection <span class="text-rose-500">*</span></label>
          <textarea
            v-model="rejectModal.remarks"
            rows="3"
            placeholder="Explain why this submission is rejected..."
            class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-[13px] text-slate-700 focus:outline-none focus:border-rose-500 focus:bg-white transition-colors"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            @click="rejectModal.open = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors min-h-[40px] cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="submitReject"
            :disabled="isSubmittingAction"
            class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 transition-colors shadow-xs disabled:opacity-50 min-h-[40px] cursor-pointer flex items-center gap-1.5"
          >
            <svg v-if="isSubmittingAction" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Confirm Rejection
          </button>
        </div>
      </div>
    </div>

    <!-- Close Semester (Lock) Modal -->
    <div
      v-if="closeSemesterModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4"
    >
      <div class="bg-white rounded-2xl max-w-[95vw] sm:max-w-md w-full p-4 sm:p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-base font-bold text-slate-800 truncate">Close Semester (Lock)</h3>
            <p class="text-xs text-slate-500 truncate">Restricts instructor workspace access</p>
          </div>
        </div>

        <!-- Target Identification Card -->
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 mb-3.5 space-y-2 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Instructor:</span>
            <span class="font-bold text-slate-800 text-right">{{ closeSemesterModal.instructor?.name }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Course:</span>
            <span class="font-bold text-[#5138ed] text-right truncate max-w-[220px]">
              {{ closeSemesterModal.instructor?.course }} ({{ closeSemesterModal.instructor?.course_code || 'N/A' }})
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Section:</span>
            <span class="font-semibold text-slate-700">{{ closeSemesterModal.instructor?.section || 'Section A' }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Academic Term:</span>
            <span class="font-semibold text-slate-700">
              {{ closeSemesterModal.instructor?.academic_year || semesterInfo.academicYear }} &bull; {{ closeSemesterModal.instructor?.semester || semesterInfo.semester }}
            </span>
          </div>
        </div>

        <!-- Warning Explanation -->
        <div class="p-3 bg-amber-50/80 border border-amber-200 rounded-xl mb-4 text-xs text-amber-900 leading-relaxed">
          <div class="flex items-start gap-2">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
              <span class="font-bold block mb-0.5">Workspace Access Restriction:</span>
              <p>The instructor will immediately lose access to modify exams, change question banks, update grades, or alter student records for this course workspace until the semester is reopened by the Department Head.</p>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            @click="closeSemesterModal.open = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors min-h-[40px] cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="confirmCloseSemester"
            :disabled="isSubmittingAction"
            class="px-5 py-2 rounded-xl text-xs font-bold bg-amber-600 text-white hover:bg-amber-700 transition-colors shadow-xs disabled:opacity-50 flex items-center gap-1.5 min-h-[40px] cursor-pointer"
          >
            <svg v-if="isSubmittingAction" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <span>Confirm & Close Semester</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Reopen Semester Modal -->
    <div
      v-if="reopenModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4"
    >
      <div class="bg-white rounded-2xl max-w-[95vw] sm:max-w-md w-full p-4 sm:p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center shrink-0 border border-cyan-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-base font-bold text-slate-800 truncate">Reopen Semester (Unlock)</h3>
            <p class="text-xs text-slate-500 truncate">Restores instructor workspace edit access</p>
          </div>
        </div>

        <!-- Target Identification Card -->
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 mb-3.5 space-y-2 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Instructor:</span>
            <span class="font-bold text-slate-800 text-right">{{ reopenModal.instructor?.name }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Course:</span>
            <span class="font-bold text-[#5138ed] text-right truncate max-w-[220px]">
              {{ reopenModal.instructor?.course }} ({{ reopenModal.instructor?.course_code || 'N/A' }})
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Section:</span>
            <span class="font-semibold text-slate-700">{{ reopenModal.instructor?.section || 'Section A' }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 font-medium">Academic Term:</span>
            <span class="font-semibold text-slate-700">
              {{ reopenModal.instructor?.academic_year || semesterInfo.academicYear }} &bull; {{ reopenModal.instructor?.semester || semesterInfo.semester }}
            </span>
          </div>
        </div>

        <div class="p-3 bg-cyan-50/80 border border-cyan-200 rounded-xl mb-3.5 text-xs text-cyan-900 leading-relaxed">
          <div class="flex items-start gap-2">
            <svg class="w-4 h-4 text-cyan-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
              <span class="font-bold block mb-0.5">Workspace Access Restoration:</span>
              <p>Reopening will immediately restore the instructor's editing access to the course workspace for this semester. The instructor will regain full permissions to modify exam questions, manage student assessments, and submit updated records.</p>
            </div>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-xs font-bold text-slate-700 mb-1.5">Reason for Reopening (Optional)</label>
          <textarea
            v-model="reopenModal.reason"
            rows="3"
            placeholder="Specify reason for reopening (e.g. Grade revisions requested by department board)..."
            class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-[13px] text-slate-700 focus:outline-none focus:border-cyan-500 focus:bg-white transition-colors"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2.5">
          <button
            @click="reopenModal.open = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors min-h-[40px] cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="submitReopen"
            :disabled="isSubmittingAction"
            class="px-5 py-2 rounded-xl text-xs font-bold bg-cyan-600 text-white hover:bg-cyan-700 transition-colors shadow-xs disabled:opacity-50 flex items-center gap-1.5 min-h-[40px] cursor-pointer"
          >
            <svg v-if="isSubmittingAction" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
            <span>Confirm & Reopen Semester</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Submission Guidelines Modal -->
    <div
      v-if="isGuidelinesModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4"
    >
      <div class="bg-white rounded-2xl max-w-[95vw] sm:max-w-lg w-full p-4 sm:p-6 shadow-2xl border border-slate-100 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800">Semester Submission Guidelines</h3>
          </div>
          <button @click="isGuidelinesModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="space-y-3 sm:space-y-4 text-xs sm:text-[13px] text-slate-600 leading-relaxed">
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
            <p>Once a submission is marked as <strong>Approved</strong>, the semester workspace remains open until the Department Head explicitly closes and locks it. Closing locks course records and restricts instructor editing until reopened.</p>
          </div>

          <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
            <span class="font-bold text-slate-800 block mb-1">4. Rejection Policy</span>
            <p>Submissions should only be <strong>Rejected</strong> if there is a fundamental breach or major discrepancies in exam records. Always supply clear remarks so instructors understand necessary revisions.</p>
          </div>
        </div>

        <div class="mt-5 sm:mt-6 flex justify-end">
          <button
            @click="isGuidelinesModalOpen = false"
            class="w-full sm:w-auto px-5 py-2.5 bg-[#5138ed] text-white rounded-xl text-xs sm:text-[12px] font-bold hover:bg-[#4530d1] transition-colors shadow-xs min-h-[40px] cursor-pointer"
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