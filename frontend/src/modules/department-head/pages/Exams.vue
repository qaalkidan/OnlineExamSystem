<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'

const router = useRouter()
const authStore = useAuthStore()
const settingsStore = useSettingsStore()

// ── View States ──
const currentView = ref<'list' | 'detail' | 'questions'>('list')
const showScheduleModal = ref(false)
const showEditModal = ref(false)
const showCancelModal = ref(false)
const cancelConfirmationStep = ref<1 | 2>(1)
const showAssignModal = ref(false)
const showDeleteModal = ref(false)
const showExportMenu = ref(false)

// ── Data & Loading ──
const isLoading = ref(true)
const isLoadingDetail = ref(false)
const isSubmitting = ref(false)
const isExporting = ref(false)
const errorMessage = ref<string | null>(null)
const successToast = ref<string | null>(null)

const showToast = (msg: string) => {
  successToast.value = msg
  setTimeout(() => {
    successToast.value = null
  }, 4000)
}

// ── Active Academic Term & Header Data ──
const activeTerm = computed(() => {
  const y = academicYearFilter.value !== 'all' ? academicYearFilter.value : (settingsStore.academicYear || '2026')
  const s = semesterFilter.value !== 'all' ? semesterFilter.value : (settingsStore.semester || 'Second Semester')
  if (academicYearFilter.value === 'all' && semesterFilter.value === 'all') {
    return 'All Academic Years & Semesters'
  }
  if (academicYearFilter.value === 'all') {
    return `All Academic Years • ${s}`
  }
  if (semesterFilter.value === 'all') {
    return `${y} • All Semesters`
  }
  return `${y} • ${s}`
})

const todayFormatted = computed(() => {
  return new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
})

const dayName = computed(() => {
  return new Date().toLocaleDateString('en-US', { weekday: 'long' })
})

// ── Department & Stats Data ──
const department = ref({
  id: null as number | null,
  name: 'Department',
  code: 'DEPT',
  college: 'College of Computing and Informatics'
})

const statsData = ref({
  total: 0,
  upcoming: 0,
  active: 0,
  completed: 0,
  cancelled: 0,
  draft: 0,
  total_submissions: 0,
  courses_with_exams: 0,
})

const filterOptions = ref({
  academic_years: [] as string[],
  semesters: [] as string[],
  sections: [] as string[],
  year_levels: [] as string[],
  courses: [] as any[],
  instructors: [] as any[],
  exam_types: [] as string[],
  statuses: [] as any[],
})

// ── Search & Filter State ──
const search = ref('')
const searchDebounceTimer = ref<any>(null)
const academicYearFilter = ref('all')
const semesterFilter = ref('all')
const sectionFilter = ref('all')
const courseFilter = ref('all')
const examTypeFilter = ref('all')
const statusFilter = ref('all')
const instructorFilter = ref('all')

// ── Sorting ──
const sortBy = ref('scheduled_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

// ── Pagination State ──
const currentPage = ref(1)
const perPage = ref(10)
const totalItems = ref(0)
const lastPage = ref(1)
const fromItem = ref(0)
const toItem = ref(0)

// ── Datasets ──
const exams = ref<any[]>([])
const selectedExam = ref<any>(null)

// ── Active 3-Dot Dropdown Row ──
const openDropdownId = ref<number | null>(null)

const toggleDropdown = (id: number, e: Event) => {
  e.stopPropagation()
  openDropdownId.value = openDropdownId.value === id ? null : id
}

// ── Form State (Schedule / Edit) ──
const examForm = ref({
  id: null as number | null,
  title: '',
  course_code: '',
  academic_year: '2026',
  semester: 'Second Semester',
  scheduled_at: '',
  duration_minutes: 90,
  total_marks: 100,
  exam_type: 'Midterm',
  instructor_id: '',
  room: '',
  section: '',
  description: '',
  status: 'published',
})

const cancelReason = ref('')
const assignInstructorId = ref('')
const formErrors = ref<Record<string, string>>({})
const conflictWarning = ref<string | null>(null)

// ── Active Filters Count ──
const activeFilterCount = computed(() => {
  let count = 0
  if (search.value.trim()) count++
  if (academicYearFilter.value !== 'all') count++
  if (semesterFilter.value !== 'all') count++
  if (sectionFilter.value !== 'all') count++
  if (courseFilter.value !== 'all') count++
  if (examTypeFilter.value !== 'all') count++
  if (statusFilter.value !== 'all') count++
  if (instructorFilter.value !== 'all') count++
  return count
})

const resetFilters = () => {
  search.value = ''
  academicYearFilter.value = 'all'
  semesterFilter.value = 'all'
  sectionFilter.value = 'all'
  courseFilter.value = 'all'
  examTypeFilter.value = 'all'
  statusFilter.value = 'all'
  instructorFilter.value = 'all'
  currentPage.value = 1
  fetchExams()
}

// ── Fetch Exams from Backend ──
const fetchExams = async () => {
  isLoading.value = true
  errorMessage.value = null
  openDropdownId.value = null

  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
      sort_by: sortBy.value,
      sort_order: sortOrder.value,
    }

    if (search.value.trim()) params.search = search.value.trim()
    if (academicYearFilter.value !== 'all') params.academic_year = academicYearFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (courseFilter.value !== 'all') params.course_code = courseFilter.value
    if (examTypeFilter.value !== 'all') params.exam_type = examTypeFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (instructorFilter.value !== 'all') params.instructor_id = instructorFilter.value

    const res = await apiClient.get('/dept-head/exams', { params })
    const resData = res.data

    exams.value = resData.data || []

    if (resData.stats) {
      statsData.value = resData.stats
    }
    if (resData.department) {
      department.value = resData.department
    }
    if (resData.filter_options) {
      filterOptions.value = resData.filter_options
    }
    if (resData.meta) {
      currentPage.value = resData.meta.current_page
      lastPage.value = resData.meta.last_page
      totalItems.value = resData.meta.total
      fromItem.value = resData.meta.from || 0
      toItem.value = resData.meta.to || 0
    }
  } catch (err: any) {
    console.error('Failed to load exams:', err)
    errorMessage.value = err?.response?.data?.message || 'Failed to load department examinations. Please try again.'
  } finally {
    isLoading.value = false
  }
}

// Debounced search watcher
const onSearchInput = () => {
  clearTimeout(searchDebounceTimer.value)
  searchDebounceTimer.value = setTimeout(() => {
    currentPage.value = 1
    fetchExams()
  }, 350)
}

// Watch filters
watch([academicYearFilter, semesterFilter, sectionFilter, courseFilter, examTypeFilter, statusFilter, instructorFilter], () => {
  currentPage.value = 1
  fetchExams()
})

// ── Sorting ──
const setSort = (column: string) => {
  if (sortBy.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortOrder.value = 'asc'
  }
  fetchExams()
}

// ── Pagination Navigation ──
const goToPage = (page: number) => {
  if (page >= 1 && page <= lastPage.value) {
    currentPage.value = page
    fetchExams()
  }
}

const nextPage = () => {
  if (currentPage.value < lastPage.value) {
    currentPage.value++
    fetchExams()
  }
}

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
    fetchExams()
  }
}

const onPerPageChange = () => {
  currentPage.value = 1
  fetchExams()
}

// ── Questions Filter & Search within Detail View ──
const questionTypeFilter = ref('all')
const questionSearch = ref('')

// ── View Exam Details ──
const openDetail = async (exam: any) => {
  openDropdownId.value = null
  selectedExam.value = exam
  currentView.value = 'detail'
  isLoadingDetail.value = true
  questionTypeFilter.value = 'all'
  questionSearch.value = ''

  try {
    const res = await apiClient.get(`/dept-head/exams/${exam.id}`)
    if (res.data?.data) {
      selectedExam.value = res.data.data
    }
  } catch (err: any) {
    console.error('Failed to load exam details:', err)
  } finally {
    isLoadingDetail.value = false
  }
}

const filteredQuestions = computed<any[]>(() => {
  let list: any[] = selectedExam.value?.questionsList || []
  if (questionTypeFilter.value !== 'all') {
    const filter = questionTypeFilter.value.toLowerCase()
    list = list.filter((q: any) =>
      (q.type || '').toLowerCase() === filter ||
      (q.raw_type || '').toLowerCase() === filter ||
      (q.full_type || '').toLowerCase() === filter
    )
  }
  if (questionSearch.value.trim()) {
    const term = questionSearch.value.toLowerCase()
    list = list.filter((q: any) =>
      (q.text || '').toLowerCase().includes(term) ||
      (q.title || '').toLowerCase().includes(term) ||
      (q.type || '').toLowerCase().includes(term) ||
      (q.instruction || '').toLowerCase().includes(term) ||
      (q.difficulty || '').toLowerCase().includes(term) ||
      (q.chapter || '').toLowerCase().includes(term) ||
      (q.topic || '').toLowerCase().includes(term)
    )
  }
  return list
})

const questionTypeCounts = computed(() => {
  const list = selectedExam.value?.questionsList || []
  const counts: Record<string, number> = { all: list.length }
  for (const q of list) {
    const t = q.type || 'Other'
    counts[t] = (counts[t] || 0) + 1
  }
  return counts
})

const printExamPaper = () => {
  window.print()
}

const backToList = () => {
  currentView.value = 'list'
  selectedExam.value = null
}

const backToDetail = () => {
  currentView.value = 'detail'
}

// ── Open Schedule Exam Modal ──
const openScheduleExam = () => {
  conflictWarning.value = null
  formErrors.value = {}
  examForm.value = {
    id: null,
    title: '',
    course_code: filterOptions.value.courses[0]?.code || '',
    academic_year: academicYearFilter.value !== 'all' ? academicYearFilter.value : (settingsStore.academicYear || '2026'),
    semester: semesterFilter.value !== 'all' ? semesterFilter.value : (settingsStore.semester || 'Second Semester'),
    scheduled_at: '',
    duration_minutes: 90,
    total_marks: 100,
    exam_type: 'Midterm',
    instructor_id: '',
    room: '',
    section: sectionFilter.value !== 'all' ? sectionFilter.value : '',
    description: '',
    status: 'published',
  }
  showScheduleModal.value = true
}

// ── Open Edit Exam Modal ──
const openEditExam = (exam: any) => {
  openDropdownId.value = null
  selectedExam.value = exam
  conflictWarning.value = null
  formErrors.value = {}

  let formattedDateInput = ''
  if (exam.scheduled_at) {
    const d = new Date(exam.scheduled_at)
    formattedDateInput = d.toISOString().slice(0, 16)
  }

  examForm.value = {
    id: exam.id,
    title: exam.title || '',
    course_code: exam.course_code || exam.courseCode || '',
    academic_year: exam.academic_year || exam.academicYear || (settingsStore.academicYear || '2026'),
    semester: exam.semester || (settingsStore.semester || 'Second Semester'),
    scheduled_at: formattedDateInput,
    duration_minutes: exam.duration_minutes || 60,
    total_marks: exam.total_marks || exam.marks || 100,
    exam_type: exam.type || 'Midterm',
    instructor_id: exam.instructor_id || (exam.instructor ? exam.instructor.id : ''),
    room: exam.settings?.room || '',
    section: exam.section || '',
    description: exam.description || '',
    status: exam.raw_status || 'published',
  }
  showEditModal.value = true
}

// ── Submit Schedule Exam ──
const submitScheduleExam = async () => {
  formErrors.value = {}
  conflictWarning.value = null

  if (!examForm.value.title.trim()) {
    formErrors.value.title = 'Exam title is required'
    return
  }
  if (!examForm.value.course_code) {
    formErrors.value.course_code = 'Course selection is required'
    return
  }
  if (!examForm.value.scheduled_at) {
    formErrors.value.scheduled_at = 'Scheduled date and time is required'
    return
  }

  isSubmitting.value = true
  try {
    await apiClient.post('/dept-head/exams', {
      title: examForm.value.title.trim(),
      course_code: examForm.value.course_code,
      academic_year: examForm.value.academic_year,
      semester: examForm.value.semester,
      scheduled_at: examForm.value.scheduled_at,
      duration_minutes: parseInt(String(examForm.value.duration_minutes)),
      total_marks: parseInt(String(examForm.value.total_marks)),
      exam_type: examForm.value.exam_type,
      instructor_id: examForm.value.instructor_id || null,
      room: examForm.value.room || null,
      section: examForm.value.section || null,
      description: examForm.value.description || null,
    })

    showScheduleModal.value = false
    showToast(`Exam "${examForm.value.title}" scheduled successfully.`)
    await fetchExams()
  } catch (err: any) {
    console.error('Failed to schedule exam:', err)
    const msg = err.response?.data?.message || 'Failed to schedule examination.'
    if (msg.includes('Conflict')) {
      conflictWarning.value = msg
    } else {
      alert(msg)
    }
  } finally {
    isSubmitting.value = false
  }
}

// ── Submit Edit Exam ──
const submitEditExam = async () => {
  if (!selectedExam.value) return
  formErrors.value = {}
  conflictWarning.value = null

  isSubmitting.value = true
  try {
    await apiClient.put(`/dept-head/exams/${selectedExam.value.id}`, {
      title: examForm.value.title.trim(),
      academic_year: examForm.value.academic_year,
      semester: examForm.value.semester,
      scheduled_at: examForm.value.scheduled_at,
      duration_minutes: parseInt(String(examForm.value.duration_minutes)),
      total_marks: parseInt(String(examForm.value.total_marks)),
      exam_type: examForm.value.exam_type,
      instructor_id: examForm.value.instructor_id || null,
      room: examForm.value.room || null,
      section: examForm.value.section || null,
    })

    showEditModal.value = false
    showToast(`Exam "${examForm.value.title}" updated successfully.`)
    await fetchExams()
    if (currentView.value === 'detail') {
      await openDetail(selectedExam.value)
    }
  } catch (err: any) {
    console.error('Failed to update exam:', err)
    const msg = err.response?.data?.message || 'Failed to update examination.'
    if (msg.includes('Conflict')) {
      conflictWarning.value = msg
    } else {
      alert(msg)
    }
  } finally {
    isSubmitting.value = false
  }
}

// ── Check if Exam Can Be Cancelled (Never if Completed or Cancelled) ──
const canCancelExam = (exam: any): boolean => {
  if (!exam) return false
  const status = (exam.status || exam.raw_status || '').toLowerCase()
  if (status === 'completed' || status === 'cancelled') return false
  if (exam.can_cancel === false) return false
  return true
}

// ── Open Cancel Exam Modal (2-Step Confirmation Flow) ──
const openCancelExam = (exam: any) => {
  if (!canCancelExam(exam)) {
    alert('Completed or already cancelled examinations cannot be cancelled.')
    return
  }
  openDropdownId.value = null
  selectedExam.value = exam
  cancelReason.value = ''
  cancelConfirmationStep.value = 1
  showCancelModal.value = true
}

const proceedToCancelStep2 = () => {
  if (!canCancelExam(selectedExam.value)) {
    showCancelModal.value = false
    alert('This examination is completed or cancelled and cannot be modified.')
    return
  }
  cancelConfirmationStep.value = 2
}

const backToCancelStep1 = () => {
  cancelConfirmationStep.value = 1
}

const submitCancelExam = async () => {
  if (!selectedExam.value || !canCancelExam(selectedExam.value)) return
  isSubmitting.value = true
  try {
    await apiClient.post(`/dept-head/exams/${selectedExam.value.id}/cancel`, {
      reason: cancelReason.value.trim() || 'Cancelled by Department Head',
    })
    showCancelModal.value = false
    cancelConfirmationStep.value = 1
    showToast(`Exam "${selectedExam.value.title}" has been cancelled.`)
    await fetchExams()
    if (currentView.value === 'detail') {
      await openDetail(selectedExam.value)
    }
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to cancel examination.')
  } finally {
    isSubmitting.value = false
  }
}

// ── Open Assign Faculty Modal ──
const openAssignFaculty = (exam: any) => {
  openDropdownId.value = null
  selectedExam.value = exam
  assignInstructorId.value = exam.instructor_id || (exam.instructor ? String(exam.instructor.id) : '')
  showAssignModal.value = true
}

const submitAssignFaculty = async () => {
  if (!selectedExam.value || !assignInstructorId.value) return
  isSubmitting.value = true
  try {
    await apiClient.post(`/dept-head/exams/${selectedExam.value.id}/assign-instructor`, {
      instructor_id: assignInstructorId.value,
    })
    showAssignModal.value = false
    showToast(`Faculty assigned to exam successfully.`)
    await fetchExams()
    if (currentView.value === 'detail') {
      await openDetail(selectedExam.value)
    }
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to assign faculty.')
  } finally {
    isSubmitting.value = false
  }
}

// ── Open Delete Exam Modal ──
const confirmDeleteExam = (exam: any) => {
  openDropdownId.value = null
  selectedExam.value = exam
  showDeleteModal.value = true
}

const executeDeleteExam = async () => {
  if (!selectedExam.value) return
  isSubmitting.value = true
  try {
    await apiClient.delete(`/dept-head/exams/${selectedExam.value.id}`)
    showDeleteModal.value = false
    showToast(`Exam deleted successfully.`)
    if (currentView.value === 'detail') {
      currentView.value = 'list'
    }
    await fetchExams()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to delete examination.')
  } finally {
    isSubmitting.value = false
  }
}

// ── Export Handling ──
const handleExport = async (format: 'pdf' | 'excel' | 'csv') => {
  showExportMenu.value = false
  isExporting.value = true

  try {
    const params: Record<string, any> = { format }
    if (search.value.trim()) params.search = search.value.trim()
    if (academicYearFilter.value !== 'all') params.academic_year = academicYearFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (courseFilter.value !== 'all') params.course_code = courseFilter.value
    if (examTypeFilter.value !== 'all') params.exam_type = examTypeFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value

    const res = await apiClient.get('/dept-head/exams/export', { params })
    const { file, filename } = res.data

    if (!file || !filename) {
      throw new Error('Export payload missing file data')
    }

    const binary = atob(file)
    const array = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i++) {
      array[i] = binary.charCodeAt(i)
    }

    let mimeType = 'application/octet-stream'
    if (format === 'pdf') mimeType = 'application/pdf'
    else if (format === 'excel') mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    else if (format === 'csv') mimeType = 'text/csv;charset=utf-8;'

    const blob = new Blob([array], { type: mimeType })
    const blobUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)

    showToast(`Exported department exams as ${format.toUpperCase()}.`)
  } catch (err: any) {
    console.error('Export error:', err)
    alert(err?.response?.data?.message || 'Failed to export exams. Please try again.')
  } finally {
    isExporting.value = false
  }
}

// ── Global Click for Dropdowns ──
const handleGlobalClick = (e: MouseEvent) => {
  const target = e.target as HTMLElement
  if (!target.closest('.dropdown-container')) {
    openDropdownId.value = null
  }
  if (!target.closest('.export-menu-container')) {
    showExportMenu.value = false
  }
}

onMounted(() => {
  fetchExams()
  settingsStore.fetchSettings()
  window.addEventListener('click', handleGlobalClick)
})

// ── Badges & Helpers ──
const typeBadge = (t: string) => {
  const type = (t || '').toLowerCase()
  if (type === 'midterm') return 'bg-indigo-50 text-[#5138ed] border border-indigo-200'
  if (type === 'final') return 'bg-amber-50 text-amber-700 border border-amber-200'
  if (type === 'quiz') return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
  if (type === 'practical') return 'bg-purple-50 text-purple-700 border border-purple-200'
  return 'bg-slate-100 text-slate-700 border border-slate-200'
}

const statusBadge = (s: string) => {
  const status = (s || '').toLowerCase()
  if (status === 'active') return 'bg-emerald-100 text-emerald-800 border-emerald-300 font-black animate-pulse'
  if (status === 'scheduled') return 'bg-sky-50 text-sky-700 border-sky-200'
  if (status === 'published') return 'bg-teal-50 text-teal-700 border-teal-200'
  if (status === 'completed') return 'bg-slate-100 text-slate-600 border-slate-200'
  if (status === 'cancelled') return 'bg-rose-50 text-rose-700 border-rose-200'
  if (status === 'draft') return 'bg-amber-50 text-amber-700 border-amber-200'
  return 'bg-slate-100 text-slate-600 border-slate-200'
}

const qTypeBadge = (t: string) => {
  const type = (t || '').toLowerCase()
  if (type.includes('choice') || type === 'mcq' || type === 'multiple_choice') return 'text-indigo-700 bg-indigo-50 border-indigo-200'
  if (type.includes('true') || type === 'true_false') return 'text-emerald-700 bg-emerald-50 border-emerald-200'
  if (type.includes('short') || type === 'short_answer') return 'text-amber-700 bg-amber-50 border-amber-200'
  if (type.includes('matching')) return 'text-purple-700 bg-purple-50 border-purple-200'
  if (type.includes('blank') || type.includes('fill')) return 'text-sky-700 bg-sky-50 border-sky-200'
  if (type.includes('essay')) return 'text-rose-700 bg-rose-50 border-rose-200'
  return 'text-slate-700 bg-slate-100 border-slate-200'
}

const getInitials = (name: string) => {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

const getAvatarColor = (id: number) => {
  const colors = [
    'bg-indigo-100 text-[#5138ed]',
    'bg-emerald-100 text-emerald-700',
    'bg-sky-100 text-sky-700',
    'bg-purple-100 text-purple-700',
    'bg-amber-100 text-amber-700',
    'bg-rose-100 text-rose-700',
  ]
  return colors[id % colors.length]
}
</script>

<template>
  <div class="space-y-6 min-w-0 max-w-full pb-10">

    <!-- Success Toast Notification -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="successToast"
        class="fixed bottom-6 right-6 z-50 bg-slate-900 text-white px-4 py-3 rounded-xl shadow-xl flex items-center gap-3 border border-slate-700"
      >
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
        <span class="text-xs sm:text-sm font-semibold">{{ successToast }}</span>
      </div>
    </transition>

    <!-- Error Alert Banner -->
    <div
      v-if="errorMessage"
      class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start justify-between gap-3 text-rose-800"
    >
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="text-sm font-bold">Examination Load Error</p>
          <p class="text-xs text-rose-600 mt-0.5">{{ errorMessage }}</p>
        </div>
      </div>
      <button @click="fetchExams" class="text-xs font-bold text-rose-700 hover:underline shrink-0">Try again</button>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW 1: EXAM DETAILS VIEW (COMPREHENSIVE EXAM & INSTRUCTOR DETAILS,    -->
    <!--         FOLLOWED BY FULL QUESTION PAPER STRICTLY WITHOUT ANSWERS)    -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <template v-if="currentView === 'detail'">
      <!-- Breadcrumb & Top Bar -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1.5">
            <button @click="backToList" class="text-[#5138ed] hover:underline flex items-center gap-1 font-bold">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
              Department Examinations
            </button>
            <span>/</span>
            <span class="font-mono text-slate-500">{{ selectedExam?.courseCode }}</span>
            <span>/</span>
            <span class="text-slate-700 font-medium truncate max-w-[200px] sm:max-w-xs">{{ selectedExam?.title }}</span>
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
              {{ selectedExam?.title }}
            </h1>
            <span class="px-2.5 py-0.5 text-xs font-mono font-bold rounded-lg bg-indigo-50 text-[#5138ed] border border-indigo-200">
              {{ selectedExam?.code }}
            </span>
            <span :class="[statusBadge(selectedExam?.status), 'px-2.5 py-0.5 text-xs font-bold rounded-lg border capitalize']">
              {{ selectedExam?.status }}
            </span>
            <span :class="[typeBadge(selectedExam?.type), 'px-2.5 py-0.5 text-xs font-bold rounded-lg']">
              {{ selectedExam?.type }}
            </span>
            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-700 border border-slate-200">
              Section: {{ selectedExam?.section || 'All Sections' }}
            </span>
          </div>

          <p class="text-xs sm:text-sm text-slate-500 mt-1 flex flex-wrap items-center gap-1.5">
            <span>Department of <strong class="text-slate-700">{{ selectedExam?.departmentName || department.name }}</strong> ({{ selectedExam?.departmentCode || department.code }})</span>
            <span class="text-slate-300">•</span>
            <span>Course: <strong class="text-slate-700">{{ selectedExam?.courseName }}</strong> ({{ selectedExam?.courseCode }})</span>
            <span v-if="selectedExam?.courseCredits" class="text-slate-300">•</span>
            <span v-if="selectedExam?.courseCredits">{{ selectedExam.courseCredits }} Credit Hours</span>
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Print Paper -->
          <button
            @click="printExamPaper"
            class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm cursor-pointer"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>Print Paper</span>
          </button>

          <!-- Edit Schedule -->
          <button
            v-if="selectedExam?.can_edit"
            @click="openEditExam(selectedExam)"
            class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm cursor-pointer"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            <span>Edit Schedule</span>
          </button>

          <!-- Results Link -->
          <button
            @click="router.push(`/dept-head/results`)"
            class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-[#5138ed] bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition-colors shadow-sm cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            <span>Results & Grades</span>
          </button>

          <!-- Cancel Exam -->
          <button
            v-if="canCancelExam(selectedExam)"
            @click="openCancelExam(selectedExam)"
            class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition-colors shadow-sm cursor-pointer"
          >
            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>Cancel Exam</span>
          </button>

          <!-- Back Button -->
          <button
            @click="backToList"
            class="flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            <span>Back to Exams</span>
          </button>
        </div>
      </div>

      <!-- Highlights Metric Row (5 Modern Cards) -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
        <!-- Card 1: Time & Venue -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between text-slate-400 mb-1">
              <span class="text-[10px] font-bold uppercase tracking-wider">Exam Window</span>
              <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <p class="text-sm sm:text-base font-black text-slate-900 leading-tight">{{ selectedExam?.time }}</p>
            <p class="text-[11px] text-slate-500 font-medium mt-0.5">{{ selectedExam?.date }}</p>
          </div>
          <div class="mt-2 pt-2 border-t border-slate-100 flex items-center gap-1 text-[11px] text-slate-600 font-semibold truncate">
            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
            <span class="truncate">{{ selectedExam?.room || 'Exam Hall' }}</span>
          </div>
        </div>

        <!-- Card 2: Duration & Total Marks -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between text-slate-400 mb-1">
              <span class="text-[10px] font-bold uppercase tracking-wider">Duration & Marks</span>
              <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>
            </div>
            <p class="text-sm sm:text-base font-black text-[#5138ed] leading-tight">{{ selectedExam?.duration }} • {{ selectedExam?.marks || selectedExam?.total_marks }} Pts</p>
            <p class="text-[11px] text-slate-500 font-medium mt-0.5">Passing: {{ selectedExam?.passing_marks || 50 }} Pts ({{ selectedExam?.passing_percentage || 50 }}%)</p>
          </div>
          <div class="mt-2 pt-2 border-t border-slate-100 flex items-center gap-1 text-[11px] text-indigo-700 font-bold">
            <span>{{ selectedExam?.questionsList?.length || selectedExam?.questions || 0 }} total questions</span>
          </div>
        </div>

        <!-- Card 3: Assigned Faculty -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between text-slate-400 mb-1">
              <span class="text-[10px] font-bold uppercase tracking-wider">Assigned Faculty</span>
              <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
            </div>
            <p class="text-sm sm:text-base font-black text-slate-900 leading-tight truncate">{{ selectedExam?.instructor?.name || selectedExam?.instructorName || 'Unassigned Faculty' }}</p>
            <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ selectedExam?.instructor?.email || selectedExam?.instructorEmail || 'No email' }}</p>
          </div>
          <div class="mt-2 pt-2 border-t border-slate-100 flex items-center gap-1 text-[11px] text-emerald-700 font-semibold truncate">
            <span class="truncate">ID: {{ selectedExam?.instructor?.id_no || 'FAC-DEPT' }}</span>
          </div>
        </div>

        <!-- Card 4: Academic Cohort -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between text-slate-400 mb-1">
              <span class="text-[10px] font-bold uppercase tracking-wider">Cohort & Term</span>
              <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            </div>
            <p class="text-sm sm:text-base font-black text-slate-900 leading-tight">{{ selectedExam?.academicYear || '2026' }}</p>
            <p class="text-[11px] text-slate-500 font-medium truncate mt-0.5">{{ selectedExam?.semester || 'Second Semester' }}</p>
          </div>
          <div class="mt-2 pt-2 border-t border-slate-100 flex items-center gap-1 text-[11px] text-purple-700 font-bold truncate">
            <span class="truncate">{{ selectedExam?.section || 'All Sections' }}</span>
          </div>
        </div>

        <!-- Card 5: Participation & Pass Rate -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex flex-col justify-between col-span-2 sm:col-span-1">
          <div>
            <div class="flex items-center justify-between text-slate-400 mb-1">
              <span class="text-[10px] font-bold uppercase tracking-wider">Participation</span>
              <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <p class="text-sm sm:text-base font-black text-slate-900 leading-tight">{{ selectedExam?.totalAttempts ?? 0 }} Submissions</p>
            <p class="text-[11px] text-slate-500 font-medium mt-0.5">Pass: {{ selectedExam?.passRate ?? 0 }}% • Avg: {{ selectedExam?.averageScore ?? 0 }}%</p>
          </div>
          <div class="mt-2 pt-2 border-t border-slate-100 flex items-center gap-1 text-[11px] text-amber-700 font-semibold truncate">
            <span>{{ selectedExam?.eligibleStudents ?? 0 }} eligible candidates</span>
          </div>
        </div>
      </div>

      <!-- Main Detail Grid: 2 Comprehensive Panels -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1 & 2: Detailed Examination Specifications & Settings -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Exam Specifications Card -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
                <div>
                  <h3 class="text-sm font-bold text-slate-900">Examination Profile & Curriculum Context</h3>
                  <p class="text-xs text-slate-500">Official academic specifications recorded in the department registry.</p>
                </div>
              </div>
              <span class="px-2.5 py-1 text-xs font-mono font-bold rounded-lg bg-indigo-50 text-[#5138ed] border border-indigo-200">
                {{ selectedExam?.code }}
              </span>
            </div>

            <!-- Two-column specifications grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-xs">
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Course Title:</span>
                <span class="font-bold text-slate-800 text-right truncate max-w-[200px]">{{ selectedExam?.courseName }}</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Course Code:</span>
                <span class="font-mono font-bold text-slate-800">{{ selectedExam?.courseCode }}</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Department:</span>
                <span class="font-bold text-slate-800 text-right">{{ selectedExam?.departmentName || department.name }} ({{ selectedExam?.departmentCode || department.code }})</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Course Credits:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.courseCredits || 3 }} Credit Hours</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Academic Year:</span>
                <span class="font-bold text-[#5138ed]">{{ selectedExam?.academicYear || selectedExam?.academic_year || '2026' }}</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Semester:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.semester || 'Second Semester' }}</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Target Section:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.section || 'All Cohort Sections' }}</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Examination Format:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.type }}</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Scheduled Timing:</span>
                <span class="font-bold text-slate-800 text-right">{{ selectedExam?.date }} • {{ selectedExam?.time }} – {{ selectedExam?.endTime }}</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Exam Venue / Hall:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.room || 'Designated Exam Hall / TBA' }}</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Passing Threshold:</span>
                <span class="font-bold text-emerald-700">{{ selectedExam?.passing_marks || 50 }} Pts ({{ selectedExam?.passing_percentage || 50 }}%)</span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-400 font-medium">Created On:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.createdAtFormatted || '—' }}</span>
              </div>
            </div>

            <!-- Description & Special Instructions Box -->
            <div class="pt-2">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Candidate Instructions & Exam Description</label>
              <div class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-700 leading-relaxed font-normal">
                {{ selectedExam?.description || 'No special exam instructions recorded. Candidates must follow standard Wollo University examination guidelines.' }}
              </div>
            </div>

            <!-- Behavioral & Security Settings Grid -->
            <div class="pt-2">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2.5">Examination Security & Delivery Rules</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 flex flex-col justify-between">
                  <span class="text-slate-500 font-medium text-[11px]">Shuffle Questions</span>
                  <span :class="selectedExam?.settings?.shuffleQuestions ? 'text-emerald-700 font-bold' : 'text-slate-400 font-medium'">
                    {{ selectedExam?.settings?.shuffleQuestions ? '✓ Enabled' : '✕ Disabled' }}
                  </span>
                </div>
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 flex flex-col justify-between">
                  <span class="text-slate-500 font-medium text-[11px]">Shuffle Options</span>
                  <span :class="selectedExam?.settings?.shuffleAnswers ? 'text-emerald-700 font-bold' : 'text-slate-400 font-medium'">
                    {{ selectedExam?.settings?.shuffleAnswers ? '✓ Enabled' : '✕ Disabled' }}
                  </span>
                </div>
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 flex flex-col justify-between">
                  <span class="text-slate-500 font-medium text-[11px]">Allow Backtracking</span>
                  <span :class="selectedExam?.settings?.allowBacktracking ? 'text-emerald-700 font-bold' : 'text-slate-400 font-medium'">
                    {{ selectedExam?.settings?.allowBacktracking ? '✓ Allowed' : '✕ Locked' }}
                  </span>
                </div>
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 flex flex-col justify-between">
                  <span class="text-slate-500 font-medium text-[11px]">Fullscreen Mode</span>
                  <span :class="selectedExam?.settings?.enableFullscreenMode ? 'text-emerald-700 font-bold' : 'text-slate-400 font-medium'">
                    {{ selectedExam?.settings?.enableFullscreenMode ? '✓ Enforced' : '✕ Optional' }}
                  </span>
                </div>
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 flex flex-col justify-between">
                  <span class="text-slate-500 font-medium text-[11px]">Tab Monitoring</span>
                  <span :class="selectedExam?.settings?.enableBrowserTabMonitoring ? 'text-emerald-700 font-bold' : 'text-slate-400 font-medium'">
                    {{ selectedExam?.settings?.enableBrowserTabMonitoring ? '✓ Active' : '✕ Off' }}
                  </span>
                </div>
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 flex flex-col justify-between">
                  <span class="text-slate-500 font-medium text-[11px]">Right Click Block</span>
                  <span :class="selectedExam?.settings?.disableRightClick ? 'text-emerald-700 font-bold' : 'text-slate-400 font-medium'">
                    {{ selectedExam?.settings?.disableRightClick ? '✓ Blocked' : '✕ Allowed' }}
                  </span>
                </div>
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 flex flex-col justify-between">
                  <span class="text-slate-500 font-medium text-[11px]">Auto Submit</span>
                  <span :class="selectedExam?.settings?.autoSubmit ? 'text-emerald-700 font-bold' : 'text-slate-400 font-medium'">
                    {{ selectedExam?.settings?.autoSubmit ? '✓ On Timer' : '✕ Manual' }}
                  </span>
                </div>
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 flex flex-col justify-between">
                  <span class="text-slate-500 font-medium text-[11px]">Calculator Access</span>
                  <span :class="selectedExam?.settings?.allowCalculator ? 'text-emerald-700 font-bold' : 'text-slate-400 font-medium'">
                    {{ selectedExam?.settings?.allowCalculator ? '✓ Permitted' : '✕ Prohibited' }}
                  </span>
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- Column 3: Assigned Faculty Member Profile & Sign-off Card -->
        <div class="lg:col-span-1 space-y-6">

          <!-- Faculty Profile Card -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                <h3 class="text-sm font-bold text-slate-900">Assigned Faculty Member</h3>
              </div>
              <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                Lead Examiner
              </span>
            </div>

            <!-- Avatar & Profile Details -->
            <div class="flex items-start gap-3.5">
              <div
                :class="getAvatarColor(selectedExam?.instructorId || selectedExam?.instructor?.id || selectedExam?.id)"
                class="w-14 h-14 rounded-2xl flex items-center justify-center font-black text-lg shadow-sm shrink-0"
              >
                <span>{{ getInitials(selectedExam?.instructor?.name || selectedExam?.instructorName || 'Faculty') }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-base font-black text-slate-900 truncate leading-snug">
                  {{ selectedExam?.instructor?.name || selectedExam?.instructorName || 'Unassigned Faculty' }}
                </p>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                  {{ selectedExam?.instructor?.role || 'Course Instructor & Examiner' }}
                </p>
                <p class="text-xs text-[#5138ed] font-semibold mt-0.5 truncate">
                  {{ selectedExam?.instructor?.email || selectedExam?.instructorEmail || 'No email registered' }}
                </p>
              </div>
            </div>

            <!-- Instructor Metadata List -->
            <div class="space-y-2.5 text-xs pt-1 border-t border-slate-100">
              <div class="flex justify-between py-1 border-b border-slate-100/60">
                <span class="text-slate-400">Institutional Staff ID:</span>
                <span class="font-mono font-bold text-slate-800">{{ selectedExam?.instructor?.id_no || ('FAC-' + (selectedExam?.instructorId || '0114')) }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-100/60">
                <span class="text-slate-400">Department Affiliation:</span>
                <span class="font-bold text-slate-800 text-right">{{ selectedExam?.instructor?.department || selectedExam?.departmentName || department.name }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-100/60">
                <span class="text-slate-400">Campus Contact:</span>
                <span class="font-medium text-slate-700">{{ selectedExam?.instructor?.phone || 'Campus Extension' }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-100/60">
                <span class="text-slate-400">Exam Authoring Status:</span>
                <span class="font-bold text-emerald-700">Completed & Verified</span>
              </div>
              <div class="flex justify-between py-1">
                <span class="text-slate-400">Total Items Authored:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.questionsList?.length || selectedExam?.questions || 0 }} Items ({{ selectedExam?.marks || 100 }} Pts)</span>
              </div>
            </div>

            <!-- Department Authorization Pill -->
            <div class="p-3 rounded-xl bg-emerald-50/70 border border-emerald-200/80 flex items-center gap-2.5 text-xs text-emerald-800">
              <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <span class="font-semibold text-[11px]">Authorized examiner for Department of {{ selectedExam?.departmentName || department.name }}</span>
            </div>
          </div>

          <!-- Quick Attempt Performance Card -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-3">
            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Candidate Participation</h4>
            <div class="grid grid-cols-2 gap-2 text-center">
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                <p class="text-lg font-black text-slate-900">{{ selectedExam?.totalAttempts || 0 }}</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase mt-0.5">Submissions</p>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                <p class="text-lg font-black text-emerald-600">{{ selectedExam?.passRate || 0 }}%</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase mt-0.5">Pass Rate</p>
              </div>
            </div>
            <button
              @click="router.push(`/dept-head/results`)"
              class="w-full py-2 text-xs font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer border border-indigo-100"
            >
              <span>View Candidate Results</span>
              <span>→</span>
            </button>
          </div>

        </div>

      </div>

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- SECTION: COMPLETE EXAMINATION QUESTION PAPER (WITHOUT ANSWERS)       -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div id="questions-paper" class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-7 shadow-sm space-y-6">

        <!-- Question Paper Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              </div>
              <div>
                <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Complete Examination Question Paper</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                  Official examination paper questions as authored by the assigned faculty. Correct answer keys are strictly hidden for department review.
                </p>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <span class="px-3 py-1.5 rounded-xl bg-indigo-50 text-[#5138ed] border border-indigo-200 text-xs font-bold">
              {{ selectedExam?.questionsList?.length || selectedExam?.questions || 0 }} Assessment Items
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold">
              {{ selectedExam?.marks || 100 }} Total Marks
            </span>
          </div>
        </div>

        <!-- Filter Pills & Search Input Toolbar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <!-- Question Type Pills -->
          <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <button
              @click="questionTypeFilter = 'all'"
              :class="questionTypeFilter === 'all' ? 'bg-[#5138ed] text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
              class="px-3 py-1.5 rounded-xl font-bold transition cursor-pointer"
            >
              All Items ({{ questionTypeCounts['all'] || 0 }})
            </button>
            <button
              v-for="(count, typeName) in questionTypeCounts"
              :key="typeName"
              v-show="typeName !== 'all' && count > 0"
              @click="questionTypeFilter = typeName"
              :class="questionTypeFilter === typeName ? 'bg-[#5138ed] text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
              class="px-3 py-1.5 rounded-xl font-bold transition cursor-pointer"
            >
              {{ typeName }} ({{ count }})
            </button>
          </div>

          <!-- Question Prompt Search -->
          <div class="relative w-full sm:w-64">
            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            <input
              v-model="questionSearch"
              type="text"
              placeholder="Search question prompt..."
              class="w-full pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
            />
            <button
              v-if="questionSearch"
              @click="questionSearch = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- Empty State for Questions -->
        <div
          v-if="filteredQuestions.length === 0"
          class="py-12 text-center text-slate-400 border border-dashed border-slate-200 rounded-2xl text-xs space-y-1.5"
        >
          <svg class="w-8 h-8 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          <p class="font-bold text-slate-600">No questions match the active filter</p>
          <p class="text-slate-400">Clear your search term or select "All Items" to view the complete paper.</p>
        </div>

        <!-- Question Items Stream (WITHOUT ANSWERS) -->
        <div v-else class="space-y-5">
          <div
            v-for="(q, idx) in filteredQuestions"
            :key="q.id || idx"
            class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-2xs space-y-3.5 hover:border-slate-300 transition-colors"
          >
            <!-- Question Item Header -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
              <div class="flex flex-wrap items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-indigo-50 text-[#5138ed] font-black text-xs flex items-center justify-center">
                  Q{{ q.number || (Number(idx) + 1) }}
                </span>
                <span :class="[qTypeBadge(q.type), 'px-2.5 py-0.5 rounded-lg text-xs font-bold border']">
                  {{ q.type }}
                </span>
                <span v-if="q.difficulty" class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600">
                  {{ q.difficulty }}
                </span>
                <span v-if="q.chapter || q.topic" class="text-[11px] text-slate-400 font-medium hidden sm:inline">
                  {{ q.chapter ? q.chapter + ' • ' : '' }}{{ q.topic }}
                </span>
              </div>
              <span class="text-xs font-bold text-[#5138ed] bg-indigo-50 border border-indigo-100 px-3 py-1 rounded-xl">
                {{ q.marks }} Marks
              </span>
            </div>

            <!-- Question Prompt / Text -->
            <p class="text-sm sm:text-base font-semibold text-slate-900 leading-relaxed whitespace-pre-line">
              {{ q.text }}
            </p>

            <!-- Instructions (if any) -->
            <p v-if="q.instruction" class="text-xs text-slate-500 italic">
              {{ q.instruction }}
            </p>

            <!-- Question Image (if any) -->
            <div v-if="q.image_url" class="pt-1">
              <img :src="q.image_url" alt="Question illustration" class="max-h-64 rounded-xl border border-slate-200 object-contain bg-slate-50 p-1" />
            </div>

            <!-- ── OPTIONS & ITEMS PRESENTATION (STRICTLY WITHOUT ANSWERS) ── -->

            <!-- Multiple Choice Options (Neutral, No Correct Indicators) -->
            <template v-if="q.options && q.options.length && (q.raw_type === 'multiple_choice' || (q.type || '').toLowerCase().includes('choice') || (q.type || '').toLowerCase().includes('mcq'))">
              <div class="space-y-2 pt-1">
                <div
                  v-for="(opt, optIdx) in q.options"
                  :key="optIdx"
                  class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/60 text-xs sm:text-sm text-slate-800"
                >
                  <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-white text-slate-700 border border-slate-300 shrink-0">
                    {{ String.fromCharCode(65 + Number(optIdx)) }}
                  </span>
                  <span class="font-medium">{{ typeof opt === 'string' ? opt : (opt.text || opt.title || opt.desc || JSON.stringify(opt)) }}</span>
                </div>
              </div>
            </template>

            <!-- True / False Options (Neutral, No Answer Revealed) -->
            <template v-else-if="q.raw_type === 'true_false' || (q.type || '').toLowerCase().includes('true')">
              <div class="grid grid-cols-2 gap-3 pt-1">
                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/60 text-xs sm:text-sm text-slate-800">
                  <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-white text-slate-700 border border-slate-300 shrink-0">A</span>
                  <span class="font-medium">True</span>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/60 text-xs sm:text-sm text-slate-800">
                  <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-white text-slate-700 border border-slate-300 shrink-0">B</span>
                  <span class="font-medium">False</span>
                </div>
              </div>
            </template>

            <!-- Matching Items (Column A & Column B Neutral Display) -->
            <template v-else-if="q.matching_pairs && q.matching_pairs.length">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                <div class="space-y-2">
                  <p class="text-[11px] font-bold uppercase text-slate-400">Column A (Premises)</p>
                  <div v-for="(p, pIdx) in q.matching_pairs" :key="'prem-' + pIdx" class="p-2.5 rounded-lg border border-slate-200 bg-slate-50/50 text-xs font-medium text-slate-800 flex items-center gap-2">
                    <span class="font-bold text-slate-500">{{ Number(pIdx) + 1 }}.</span>
                    <span>{{ p.premise }}</span>
                  </div>
                </div>
                <div class="space-y-2">
                  <p class="text-[11px] font-bold uppercase text-slate-400">Column B (Options)</p>
                  <div v-for="(p, pIdx) in q.matching_pairs" :key="'resp-' + pIdx" class="p-2.5 rounded-lg border border-slate-200 bg-slate-50/50 text-xs font-medium text-slate-800 flex items-center gap-2">
                    <span class="font-bold text-slate-500">{{ String.fromCharCode(65 + Number(pIdx)) }}.</span>
                    <span>{{ p.response }}</span>
                  </div>
                </div>
              </div>
            </template>

            <!-- Short Answer / Essay / Blank Space (Neutral Candidate Answer Space) -->
            <template v-else>
              <div class="pt-1">
                <div class="border-2 border-dashed border-slate-200 bg-slate-50/40 rounded-xl p-4 text-xs text-slate-400 flex items-center gap-2">
                  <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                  <span>Candidate Response Area — Written answer space provided on examination paper</span>
                </div>
              </div>
            </template>

          </div>
        </div>

        <!-- Bottom Return to Top Bar -->
        <div class="flex items-center justify-between pt-4 border-t border-slate-100 text-xs">
          <span class="text-slate-400 font-medium">Viewing full examination paper for {{ selectedExam?.courseCode }}</span>
          <button
            @click="backToList"
            class="text-[#5138ed] font-bold hover:underline flex items-center gap-1 cursor-pointer"
          >
            <span>Back to Examinations List</span>
            <span>↑</span>
          </button>
        </div>

      </div>

    </template>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW 3: EXAMS LIST (DEFAULT MAIN VIEW)                               -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <template v-else>

      <!-- Institutional Header -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-1.5">
            <span class="px-2.5 py-0.5 text-xs font-black tracking-wider uppercase rounded-md bg-[#5138ed]/10 text-[#5138ed] border border-[#5138ed]/20">
              Department of {{ department.name }}
            </span>
            <span class="px-2.5 py-0.5 text-xs font-bold rounded-md bg-slate-100 text-slate-600 border border-slate-200">
              Code: {{ department.code }}
            </span>
            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
              {{ activeTerm }}
            </span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Department Exam Management Center
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            Schedule, monitor, and administer university examinations for Wollo University.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <!-- Multi-format Export Dropdown -->
          <div class="relative export-menu-container">
            <button
              @click="showExportMenu = !showExportMenu"
              :disabled="isExporting"
              class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm disabled:opacity-50"
            >
              <svg v-if="isExporting" class="w-4 h-4 animate-spin text-[#5138ed]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <svg v-else class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              <span>{{ isExporting ? 'Exporting...' : 'Export Exams' }}</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="showExportMenu"
              class="absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-40 text-xs font-semibold animate-in fade-in zoom-in-95 duration-150"
            >
              <button
                @click="handleExport('pdf')"
                class="w-full px-4 py-2 text-left hover:bg-slate-50 flex items-center gap-2.5 text-slate-700"
              >
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                Export as PDF Document
              </button>
              <button
                @click="handleExport('excel')"
                class="w-full px-4 py-2 text-left hover:bg-slate-50 flex items-center gap-2.5 text-slate-700"
              >
                <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                Export as Excel (.xlsx)
              </button>
              <button
                @click="handleExport('csv')"
                class="w-full px-4 py-2 text-left hover:bg-slate-50 flex items-center gap-2.5 text-slate-700"
              >
                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                Export as CSV Spreadsheet
              </button>
            </div>
          </div>

          <!-- Schedule Exam Button -->
          <button
            @click="openScheduleExam"
            class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all cursor-pointer whitespace-nowrap"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>Schedule Exam</span>
          </button>
        </div>
      </div>

      <!-- Real KPI Cards (Zero Fake Trends) -->
      <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Total Exams -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Exams</p>
            <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ statsData.total }}</p>
            <p class="text-[11px] text-slate-500 font-medium truncate mt-0.5">{{ statsData.courses_with_exams }} course(s) with exams</p>
          </div>
        </div>

        <!-- Upcoming Exams -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Upcoming Exams</p>
            <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ statsData.upcoming }}</p>
            <p class="text-[11px] text-sky-600 font-semibold truncate mt-0.5">Scheduled timeline</p>
          </div>
        </div>

        <!-- Active / In Progress Exams -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Active Exams</p>
            <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ statsData.active }}</p>
            <p class="text-[11px] text-emerald-600 font-semibold truncate mt-0.5">
              {{ statsData.active > 0 ? 'Live in exam halls' : 'No active exams currently' }}
            </p>
          </div>
        </div>

        <!-- Student Submissions -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Submissions</p>
            <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ statsData.total_submissions }}</p>
            <p class="text-[11px] text-slate-500 font-medium truncate mt-0.5">{{ statsData.completed }} concluded exams</p>
          </div>
        </div>
      </div>

      <!-- Filters & Search Toolbar -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
          <!-- Search Input -->
          <div class="relative flex-1">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="search"
              @input="onSearchInput"
              type="text"
              placeholder="Search exams by title, course name, code, or instructor..."
              class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all bg-white placeholder:text-slate-400"
            />
            <button
              v-if="search"
              @click="search = ''; fetchExams()"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs"
            >
              ✕
            </button>
          </div>

          <!-- Filters Row -->
          <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2.5">
            <!-- Academic Year Filter -->
            <select
              v-model="academicYearFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">All Academic Years</option>
              <option v-for="ay in filterOptions.academic_years" :key="ay" :value="ay">
                {{ ay }}
              </option>
            </select>

            <!-- Semester Filter -->
            <select
              v-model="semesterFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">All Semesters</option>
              <option v-for="sem in filterOptions.semesters" :key="sem" :value="sem">{{ sem }}</option>
            </select>

            <!-- Course Filter -->
            <select
              v-model="courseFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">All Department Courses</option>
              <option v-for="c in filterOptions.courses" :key="c.code" :value="c.code">
                {{ c.code }} — {{ c.title }}
              </option>
            </select>

            <!-- Section Filter -->
            <select
              v-model="sectionFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">All Sections</option>
              <option v-for="sec in filterOptions.sections" :key="sec" :value="sec">{{ sec }}</option>
            </select>

            <!-- Exam Type Filter -->
            <select
              v-model="examTypeFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">All Exam Types</option>
              <option v-for="t in filterOptions.exam_types" :key="t" :value="t">{{ t }}</option>
            </select>

            <!-- Status Filter -->
            <select
              v-model="statusFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option v-for="st in filterOptions.statuses" :key="st.value" :value="st.value">{{ st.label }}</option>
            </select>
          </div>
        </div>

        <!-- Active Filter Indicator / Reset -->
        <div v-if="activeFilterCount > 0" class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100 text-xs">
          <div class="flex flex-wrap items-center gap-1.5 text-slate-500 font-medium">
            <span class="text-slate-400 font-semibold mr-1">Active filters ({{ activeFilterCount }}):</span>
            <span v-if="academicYearFilter !== 'all'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 text-[#5138ed] font-bold text-[11px] border border-indigo-200">
              AY: {{ academicYearFilter }}
              <button @click="academicYearFilter = 'all'" class="hover:text-indigo-900 ml-0.5 cursor-pointer">✕</button>
            </span>
            <span v-if="semesterFilter !== 'all'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 text-[#5138ed] font-bold text-[11px] border border-indigo-200">
              Sem: {{ semesterFilter }}
              <button @click="semesterFilter = 'all'" class="hover:text-indigo-900 ml-0.5 cursor-pointer">✕</button>
            </span>
            <span v-if="sectionFilter !== 'all'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 text-[#5138ed] font-bold text-[11px] border border-indigo-200">
              Sec: {{ sectionFilter }}
              <button @click="sectionFilter = 'all'" class="hover:text-indigo-900 ml-0.5 cursor-pointer">✕</button>
            </span>
            <span v-if="courseFilter !== 'all'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 text-[#5138ed] font-bold text-[11px] border border-indigo-200">
              Course: {{ courseFilter }}
              <button @click="courseFilter = 'all'" class="hover:text-indigo-900 ml-0.5 cursor-pointer">✕</button>
            </span>
            <span v-if="examTypeFilter !== 'all'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 text-[#5138ed] font-bold text-[11px] border border-indigo-200">
              Type: {{ examTypeFilter }}
              <button @click="examTypeFilter = 'all'" class="hover:text-indigo-900 ml-0.5 cursor-pointer">✕</button>
            </span>
            <span v-if="statusFilter !== 'all'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 text-[#5138ed] font-bold text-[11px] border border-indigo-200">
              Status: {{ statusFilter }}
              <button @click="statusFilter = 'all'" class="hover:text-indigo-900 ml-0.5 cursor-pointer">✕</button>
            </span>
            <span v-if="search.trim()" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 text-[#5138ed] font-bold text-[11px] border border-indigo-200">
              "{{ search.trim() }}"
              <button @click="search = ''; fetchExams()" class="hover:text-indigo-900 ml-0.5 cursor-pointer">✕</button>
            </span>
          </div>
          <button
            @click="resetFilters"
            class="text-[#5138ed] font-bold hover:underline cursor-pointer ml-auto"
          >
            Clear all filters
          </button>
        </div>
      </div>

      <!-- Desktop Exams Table & Mobile Cards -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">

        <!-- Loading State -->
        <div v-if="isLoading" class="p-12 text-center text-slate-400">
          <svg class="w-8 h-8 animate-spin mx-auto text-[#5138ed] mb-3" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <p class="text-xs font-bold text-slate-600">Loading department examinations...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="exams.length === 0" class="p-12 text-center text-slate-400">
          <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          <p class="text-sm font-bold text-slate-700">No examinations found</p>
          <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
            {{ search || activeFilterCount > 0 ? 'No exams match the active filters or search parameters.' : 'Your department does not have any examinations registered yet. Click "Schedule Exam" to create one.' }}
          </p>
          <button
            v-if="activeFilterCount > 0"
            @click="resetFilters"
            class="mt-4 px-4 py-2 text-xs font-bold text-[#5138ed] bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition-colors"
          >
            Reset Filters
          </button>
        </div>

        <!-- Table View (Desktop & Tablet) -->
        <div v-else class="hidden lg:block overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                <th class="py-3.5 px-3 w-10 text-center">#</th>
                <th @click="setSort('title')" class="py-3.5 px-4 cursor-pointer hover:text-slate-800">
                  <div class="flex items-center gap-1.5">
                    <span>Exam Title</span>
                    <span v-if="sortBy === 'title'" class="text-[#5138ed]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th @click="setSort('course_code')" class="py-3.5 px-4 cursor-pointer hover:text-slate-800">
                  <span>Course</span>
                </th>
                <th class="py-3.5 px-3">Exam Type</th>
                <th class="py-3.5 px-3">Section</th>
                <th class="py-3.5 px-4">Academic Year & Semester</th>
                <th @click="setSort('scheduled_at')" class="py-3.5 px-4 cursor-pointer hover:text-slate-800">
                  <div class="flex items-center gap-1.5">
                    <span>Date & Time Window</span>
                    <span v-if="sortBy === 'scheduled_at'" class="text-[#5138ed]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-3.5 px-4">Duration & Marks</th>
                <th class="py-3.5 px-4">Assigned Faculty</th>
                <th class="py-3.5 px-3 text-center">Submissions</th>
                <th class="py-3.5 px-3 text-center">Status</th>
                <th class="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="(exam, idx) in exams"
                :key="exam.id"
                class="hover:bg-slate-50/80 transition-colors group"
              >
                <!-- Row Number -->
                <td class="py-3.5 px-3 text-center text-slate-400 font-bold text-xs">
                  {{ exam.no || (Number(idx) + 1) }}
                </td>

                <!-- Title & Code -->
                <td class="py-3.5 px-4">
                  <div class="flex items-start gap-2.5">
                    <span class="px-2 py-0.5 text-[11px] font-mono font-bold uppercase rounded bg-indigo-50 text-[#5138ed] border border-indigo-200 shrink-0">
                      {{ exam.code }}
                    </span>
                    <div class="min-w-0">
                      <p class="font-bold text-slate-900 group-hover:text-[#5138ed] transition-colors leading-snug">
                        {{ exam.title }}
                      </p>
                      <p class="text-[11px] text-slate-400 mt-0.5">{{ exam.questions }} Questions</p>
                    </div>
                  </div>
                </td>

                <!-- Course -->
                <td class="py-3.5 px-4">
                  <span class="font-bold text-slate-800 block truncate max-w-[160px]">{{ exam.courseName }}</span>
                  <span class="text-[11px] text-slate-500 font-mono">{{ exam.courseCode }}</span>
                </td>

                <!-- Exam Type -->
                <td class="py-3.5 px-3">
                  <span :class="[typeBadge(exam.type), 'px-2 py-0.5 text-[11px] font-bold rounded-md']">
                    {{ exam.type }}
                  </span>
                </td>

                <!-- Section -->
                <td class="py-3.5 px-3">
                  <span class="px-2 py-0.5 text-[11px] font-semibold rounded bg-slate-100 text-slate-700 whitespace-nowrap">
                    {{ exam.section || 'All Sections' }}
                  </span>
                </td>

                <!-- Academic Year & Semester -->
                <td class="py-3.5 px-4">
                  <p class="font-bold text-slate-800 leading-snug">{{ exam.academic_year || '2026' }}</p>
                  <p class="text-[11px] text-slate-500 font-medium">{{ exam.semester || 'Second Semester' }}</p>
                </td>

                <!-- Date & Time -->
                <td class="py-3.5 px-4">
                  <p class="font-bold text-slate-800">{{ exam.date }}</p>
                  <p class="text-[11px] text-slate-500">{{ exam.time }}</p>
                </td>

                <!-- Duration & Marks -->
                <td class="py-3.5 px-4">
                  <p class="font-bold text-slate-800">{{ exam.duration }}</p>
                  <p class="text-[11px] text-slate-500 font-semibold">{{ exam.marks }} Total Points</p>
                </td>

                <!-- Assigned Faculty -->
                <td class="py-3.5 px-4">
                  <div class="flex items-center gap-2">
                    <div
                      :class="getAvatarColor(exam.instructor_id || exam.id)"
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-[10px] shrink-0"
                    >
                      <span>{{ getInitials(exam.instructor_name) }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="font-bold text-slate-800 truncate leading-tight">{{ exam.instructor_name }}</p>
                      <p class="text-[10px] text-slate-400 truncate">{{ exam.instructor_email }}</p>
                    </div>
                  </div>
                </td>

                <!-- Student Submissions -->
                <td class="py-3.5 px-3 text-center">
                  <span class="font-bold text-slate-800">{{ exam.attempts_count }}</span>
                  <span class="text-slate-400 text-[11px] ml-1">subm.</span>
                </td>

                <!-- Status Badge -->
                <td class="py-3.5 px-3 text-center">
                  <span :class="[statusBadge(exam.status), 'px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border whitespace-nowrap']">
                    {{ exam.status }}
                  </span>
                </td>

                <!-- Actions Menu -->
                <td class="py-3.5 px-4 text-right">
                  <div class="relative inline-block text-left dropdown-container">
                    <button
                      @click="toggleDropdown(exam.id, $event)"
                      class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                      </svg>
                    </button>

                    <!-- Dropdown Content -->
                    <div
                      v-if="openDropdownId === exam.id"
                      class="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-40 text-xs font-semibold animate-in fade-in zoom-in-95 duration-100"
                    >
                      <button
                        @click="openDetail(exam)"
                        class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700"
                      >
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.264 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        <span>View Details</span>
                      </button>

                      <button
                        v-if="exam.can_edit"
                        @click="openEditExam(exam)"
                        class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700"
                      >
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        <span>Edit Schedule</span>
                      </button>

                      <button
                        @click="router.push(`/dept-head/results`)"
                        class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-[#5138ed]"
                      >
                        <svg class="w-3.5 h-3.5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        <span>View Results</span>
                      </button>

                      <button
                        @click="router.push(`/dept-head/courses`)"
                        class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700"
                      >
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        <span>View Course</span>
                      </button>

                      <div v-if="canCancelExam(exam)" class="border-t border-slate-100 my-1"></div>

                      <button
                        v-if="canCancelExam(exam)"
                        @click="openCancelExam(exam)"
                        class="w-full px-3.5 py-2 text-left hover:bg-rose-50 flex items-center gap-2 text-rose-600 font-bold"
                      >
                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Cancel Exam</span>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards View (< lg screens) -->
        <div v-if="exams.length > 0" class="block lg:hidden divide-y divide-slate-100">
          <div
            v-for="exam in exams"
            :key="'mobile-' + exam.id"
            class="p-4 space-y-3"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                  <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-indigo-50 text-[#5138ed] border border-indigo-200">
                    {{ exam.code }}
                  </span>
                  <span :class="[statusBadge(exam.status), 'px-2 py-0.5 rounded text-[10px] font-bold border capitalize']">
                    {{ exam.status }}
                  </span>
                  <span :class="[typeBadge(exam.type), 'px-2 py-0.5 rounded text-[10px] font-bold']">
                    {{ exam.type }}
                  </span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                    {{ exam.section || 'All Sections' }}
                  </span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm leading-snug">{{ exam.title }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ exam.courseName }} ({{ exam.courseCode }})</p>
              </div>

              <!-- Quick Dropdown -->
              <div class="relative dropdown-container">
                <button
                  @click="toggleDropdown(exam.id, $event)"
                  class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                </button>
                <div
                  v-if="openDropdownId === exam.id"
                  class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-40 text-xs font-semibold"
                >
                  <button @click="openDetail(exam)" class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700">View Details</button>
                  <button v-if="exam.can_edit" @click="openEditExam(exam)" class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700">Edit Schedule</button>
                  <button v-if="canCancelExam(exam)" @click="openCancelExam(exam)" class="w-full px-3.5 py-2 text-left hover:bg-rose-50 flex items-center gap-2 text-rose-600 font-bold">Cancel Exam</button>
                </div>
              </div>
            </div>

            <!-- Mobile Meta Grid -->
            <div class="grid grid-cols-2 gap-2 text-xs text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Schedule</span>
                <span class="font-bold text-slate-800">{{ exam.date }}</span>
                <span class="text-slate-400 text-[11px] block">{{ exam.time }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Term & Section</span>
                <span class="font-bold text-slate-800 truncate block">{{ exam.academic_year || '2026' }} • {{ exam.semester || 'Sem 2' }}</span>
                <span class="text-slate-500 text-[11px] block">{{ exam.section || 'All Sections' }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Duration</span>
                <span class="font-bold text-slate-800">{{ exam.duration }} ({{ exam.marks }} pts)</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Faculty</span>
                <span class="font-bold text-slate-800 truncate block">{{ exam.instructor_name }}</span>
              </div>
            </div>

            <!-- Card Action -->
            <button
              @click="openDetail(exam)"
              class="w-full py-2.5 text-xs font-bold text-[#5138ed] bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition-colors flex items-center justify-center gap-1.5"
            >
              <span>View Full Exam Details</span>
              <span>→</span>
            </button>
          </div>
        </div>

        <!-- Pagination Controls -->
        <div class="p-4 border-t border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-600">
          <div class="flex items-center gap-2">
            <span>Showing</span>
            <span class="font-bold text-slate-900">{{ fromItem }}</span>
            <span>to</span>
            <span class="font-bold text-slate-900">{{ toItem }}</span>
            <span>of</span>
            <span class="font-bold text-slate-900">{{ totalItems }}</span>
            <span>exams</span>

            <div class="ml-3 hidden sm:flex items-center gap-1.5">
              <span>Per page:</span>
              <select
                v-model="perPage"
                @change="onPerPageChange"
                class="border border-slate-200 rounded-lg px-2 py-1 text-xs font-semibold bg-white"
              >
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
              </select>
            </div>
          </div>

          <!-- Page Buttons -->
          <div class="flex items-center gap-1 self-center sm:self-auto">
            <button
              @click="prevPage"
              :disabled="currentPage <= 1"
              class="px-2.5 py-1.5 border border-slate-200 rounded-lg font-bold hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            <span class="px-3 py-1 font-bold text-slate-800">
              Page {{ currentPage }} of {{ lastPage }}
            </span>
            <button
              @click="nextPage"
              :disabled="currentPage >= lastPage"
              class="px-2.5 py-1.5 border border-slate-200 rounded-lg font-bold hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
        </div>

      </div>

    </template>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: SCHEDULE NEW EXAM                                             -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showScheduleModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-xl w-full shadow-2xl space-y-5 animate-in zoom-in-95 duration-200 max-h-[90vh] overflow-y-auto">
          <div class="flex items-start justify-between border-b border-slate-100 pb-3">
            <div>
              <h2 class="text-base font-bold text-slate-900">Schedule Department Examination</h2>
              <p class="text-xs text-slate-500 mt-0.5">Define exam timetable and assign faculty</p>
            </div>
            <button @click="showScheduleModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
          </div>

          <!-- Schedule Conflict Warning Banner -->
          <div v-if="conflictWarning" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex items-start gap-2.5">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <div>
              <p class="font-bold">Schedule Conflict Detected</p>
              <p class="mt-0.5">{{ conflictWarning }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Exam Title -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Exam Title *</label>
              <input
                v-model="examForm.title"
                type="text"
                placeholder="e.g. Midterm Examination - Biology"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
              <p v-if="formErrors.title" class="text-[11px] text-rose-500 mt-1">{{ formErrors.title }}</p>
            </div>

            <!-- Course Selection (Locked to Department Courses) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Course *</label>
              <select
                v-model="examForm.course_code"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="">Select Department Course</option>
                <option v-for="c in filterOptions.courses" :key="c.code" :value="c.code">
                  {{ c.code }} — {{ c.title }}
                </option>
              </select>
              <p v-if="formErrors.course_code" class="text-[11px] text-rose-500 mt-1">{{ formErrors.course_code }}</p>
            </div>

            <!-- Exam Type -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Exam Type</label>
              <select
                v-model="examForm.exam_type"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="Midterm">Midterm</option>
                <option value="Final">Final Examination</option>
                <option value="Quiz">Quiz</option>
                <option value="Practical">Practical / Lab Exam</option>
              </select>
            </div>

            <!-- Date & Time -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Scheduled Date & Time *</label>
              <input
                v-model="examForm.scheduled_at"
                type="datetime-local"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
              <p v-if="formErrors.scheduled_at" class="text-[11px] text-rose-500 mt-1">{{ formErrors.scheduled_at }}</p>
            </div>

            <!-- Duration -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Duration (Minutes) *</label>
              <input
                v-model="examForm.duration_minutes"
                type="number"
                min="5"
                max="360"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
            </div>

            <!-- Total Marks -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Total Marks *</label>
              <input
                v-model="examForm.total_marks"
                type="number"
                min="1"
                max="500"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
            </div>

            <!-- Instructor Assignment -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Lead Instructor</label>
              <select
                v-model="examForm.instructor_id"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="">Course Default / Unassigned</option>
                <option v-for="inst in filterOptions.instructors" :key="inst.id" :value="inst.id">
                  {{ inst.name }} ({{ inst.id_no || 'Faculty' }})
                </option>
              </select>
            </div>

            <!-- Room / Venue -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Hall / Room (Optional)</label>
              <input
                v-model="examForm.room"
                type="text"
                placeholder="e.g. Lab 204 or Hall B"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
            </div>

            <!-- Section -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Cohort Section (Optional)</label>
              <select
                v-model="examForm.section"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="">All Cohort Sections</option>
                <option v-for="sec in filterOptions.sections" :key="sec" :value="sec">{{ sec }}</option>
              </select>
            </div>

            <!-- Academic Year -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Academic Year *</label>
              <select
                v-model="examForm.academic_year"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option v-for="ay in filterOptions.academic_years" :key="ay" :value="ay">{{ ay }}</option>
              </select>
            </div>

            <!-- Semester -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Semester *</label>
              <select
                v-model="examForm.semester"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option v-for="sem in filterOptions.semesters" :key="sem" :value="sem">{{ sem }}</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
            <button
              @click="showScheduleModal = false"
              class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              @click="submitScheduleExam"
              :disabled="isSubmitting"
              class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm transition-all disabled:opacity-50"
            >
              <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSubmitting ? 'Scheduling...' : 'Save & Schedule' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: EDIT EXAM                                                     -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showEditModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-xl w-full shadow-2xl space-y-5 animate-in zoom-in-95 duration-200 max-h-[90vh] overflow-y-auto">
          <div class="flex items-start justify-between border-b border-slate-100 pb-3">
            <div>
              <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded bg-indigo-50 text-[#5138ed] border border-indigo-200">
                {{ selectedExam?.code }}
              </span>
              <h2 class="text-base font-bold text-slate-900 mt-1">Modify Examination Schedule</h2>
              <p class="text-xs text-slate-500 mt-0.5">{{ selectedExam?.courseName }}</p>
            </div>
            <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
          </div>

          <!-- Schedule Conflict Warning Banner -->
          <div v-if="conflictWarning" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex items-start gap-2.5">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <div>
              <p class="font-bold">Schedule Conflict Detected</p>
              <p class="mt-0.5">{{ conflictWarning }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Exam Title *</label>
              <input
                v-model="examForm.title"
                type="text"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Scheduled Date & Time *</label>
              <input
                v-model="examForm.scheduled_at"
                type="datetime-local"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Duration (Minutes)</label>
              <input
                v-model="examForm.duration_minutes"
                type="number"
                min="5"
                max="360"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Total Marks</label>
              <input
                v-model="examForm.total_marks"
                type="number"
                min="1"
                max="500"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Assigned Faculty</label>
              <select
                v-model="examForm.instructor_id"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="">Unassigned</option>
                <option v-for="inst in filterOptions.instructors" :key="inst.id" :value="inst.id">
                  {{ inst.name }} ({{ inst.id_no || 'Faculty' }})
                </option>
              </select>
            </div>

            <!-- Section -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Cohort Section (Optional)</label>
              <select
                v-model="examForm.section"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="">All Cohort Sections</option>
                <option v-for="sec in filterOptions.sections" :key="sec" :value="sec">{{ sec }}</option>
              </select>
            </div>

            <!-- Academic Year -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Academic Year *</label>
              <select
                v-model="examForm.academic_year"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option v-for="ay in filterOptions.academic_years" :key="ay" :value="ay">{{ ay }}</option>
              </select>
            </div>

            <!-- Semester -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Semester *</label>
              <select
                v-model="examForm.semester"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option v-for="sem in filterOptions.semesters" :key="sem" :value="sem">{{ sem }}</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
            <button
              @click="showEditModal = false"
              class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              @click="submitEditExam"
              :disabled="isSubmitting"
              class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm transition-all disabled:opacity-50"
            >
              <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSubmitting ? 'Saving...' : 'Update Schedule' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: CANCEL EXAM 2-STEP SECURITY CONFIRMATION                       -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showCancelModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-lg w-full shadow-2xl space-y-5 animate-in zoom-in-95 duration-200">

          <!-- ════ STEP 1 OF 2: VERIFICATION & REASON INPUT ════ -->
          <div v-if="cancelConfirmationStep === 1" class="space-y-4">
            <!-- Step Badge & Close -->
            <div class="flex items-center justify-between">
              <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                Confirmation 1 of 2: Security Verification
              </span>
              <button
                @click="showCancelModal = false"
                class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition cursor-pointer"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>

            <div class="flex items-start gap-3.5">
              <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div class="text-left">
                <h3 class="text-base font-black text-slate-900">Initiate Examination Cancellation?</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                  You are initiating cancellation for <strong class="text-slate-900">"{{ selectedExam?.title }}"</strong>. Cancelling will revoke student examination access and notify all invigilators.
                </p>
              </div>
            </div>

            <!-- Exam Details Snapshot Card -->
            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3.5 text-xs text-left space-y-2">
              <div class="flex justify-between">
                <span class="text-slate-400 font-medium">Course:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.courseName }} ({{ selectedExam?.courseCode }})</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400 font-medium">Scheduled Window:</span>
                <span class="font-bold text-slate-800">{{ selectedExam?.date }} • {{ selectedExam?.time }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400 font-medium">Target Cohort:</span>
                <span class="font-bold text-slate-700">{{ selectedExam?.academic_year || selectedExam?.academicYear }} • {{ selectedExam?.semester }} ({{ selectedExam?.section }})</span>
              </div>
            </div>

            <!-- Cancellation Reason Input -->
            <div class="text-left space-y-1.5">
              <label class="block text-xs font-bold text-slate-700">
                Reason for Cancellation <span class="text-rose-500">*</span>
              </label>
              <textarea
                v-model="cancelReason"
                rows="3"
                placeholder="e.g. Schedule postponed by Department Council decision..."
                class="w-full px-3.5 py-2.5 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition placeholder:text-slate-400"
              ></textarea>
              <p class="text-[11px] text-slate-400">This cancellation note will be permanently archived in the institutional audit log.</p>
            </div>

            <!-- Step 1 Actions -->
            <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
              <button
                @click="showCancelModal = false"
                class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 cursor-pointer transition"
              >
                Keep Exam
              </button>
              <button
                @click="proceedToCancelStep2"
                class="flex items-center gap-1.5 px-5 py-2.5 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-sm transition cursor-pointer"
              >
                <span>Proceed to Final Confirmation</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
              </button>
            </div>
          </div>

          <!-- ════ STEP 2 OF 2: IRREVERSIBLE ACTION CONFIRMATION ════ -->
          <div v-else class="space-y-4">
            <!-- Step Badge & Close -->
            <div class="flex items-center justify-between">
              <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-rose-600 animate-ping"></span>
                Confirmation 2 of 2: Irreversible Security Warning
              </span>
              <button
                @click="showCancelModal = false"
                class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition cursor-pointer"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>

            <!-- Danger Alert Card -->
            <div class="p-4 rounded-2xl bg-rose-50/90 border border-rose-200 text-left space-y-3">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </div>
                <div>
                  <h4 class="text-sm font-black text-rose-950">Are you absolutely certain?</h4>
                  <p class="text-xs text-rose-700 mt-0.5 leading-relaxed">
                    This action is <strong class="underline decoration-rose-400">permanent and cannot be undone</strong>.
                  </p>
                </div>
              </div>

              <div class="text-xs text-rose-900 bg-white/80 p-3 rounded-xl border border-rose-200/70 space-y-1">
                <p>• <strong>Exam Title:</strong> {{ selectedExam?.title }}</p>
                <p>• <strong>Course Code:</strong> {{ selectedExam?.courseCode }}</p>
                <p>• <strong>Logged Reason:</strong> <span class="italic text-rose-800">"{{ cancelReason || 'Cancelled by Department Head' }}"</span></p>
              </div>

              <p class="text-[11px] text-rose-600 font-medium">
                Students will immediately lose exam access. This examination will be marked as <strong class="text-rose-700 font-bold">CANCELLED</strong> across all university portals.
              </p>
            </div>

            <!-- Step 2 Actions -->
            <div class="flex items-center justify-between gap-2.5 pt-2 border-t border-slate-100">
              <button
                @click="backToCancelStep1"
                :disabled="isSubmitting"
                class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 cursor-pointer transition flex items-center gap-1.5"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                <span>Back to Step 1</span>
              </button>
              <button
                @click="submitCancelExam"
                :disabled="isSubmitting"
                class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-md transition disabled:opacity-50 cursor-pointer"
              >
                <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <svg v-else class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <span>{{ isSubmitting ? 'Cancelling...' : 'Permanently Cancel Exam' }}</span>
              </button>
            </div>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: ASSIGN FACULTY TO EXAM                                        -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showAssignModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl space-y-5 animate-in zoom-in-95 duration-200">
          <div class="flex items-start justify-between border-b border-slate-100 pb-3">
            <div>
              <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded bg-indigo-50 text-[#5138ed] border border-indigo-200">
                {{ selectedExam?.code }}
              </span>
              <h2 class="text-base font-bold text-slate-900 mt-1">Assign Lead Faculty Member</h2>
              <p class="text-xs text-slate-500 mt-0.5">{{ selectedExam?.title }}</p>
            </div>
            <button @click="showAssignModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Select Faculty Member</label>
            <select
              v-model="assignInstructorId"
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
            >
              <option value="">Select Department Instructor</option>
              <option v-for="inst in filterOptions.instructors" :key="inst.id" :value="String(inst.id)">
                {{ inst.name }} — {{ inst.id_no || 'Faculty' }} ({{ inst.email }})
              </option>
            </select>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
            <button
              @click="showAssignModal = false"
              class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              @click="submitAssignFaculty"
              :disabled="isSubmitting || !assignInstructorId"
              class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-all disabled:opacity-50"
            >
              <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSubmitting ? 'Saving...' : 'Confirm Assignment' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: DELETE EXAM CONFIRMATION                                      -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showDeleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl space-y-4 animate-in zoom-in-95 duration-200 text-center">
          <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center mx-auto">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">Delete Examination</h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
              Are you sure you want to delete <strong class="text-slate-800">"{{ selectedExam?.title }}"</strong>?
            </p>
            <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-2.5 mt-2 font-medium">
              Note: Examinations with recorded student attempts cannot be deleted to preserve academic integrity.
            </p>
          </div>
          <div class="flex items-center justify-center gap-2 pt-2">
            <button
              @click="showDeleteModal = false"
              class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              @click="executeDeleteExam"
              :disabled="isSubmitting"
              class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-sm transition-all disabled:opacity-50"
            >
              <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSubmitting ? 'Deleting...' : 'Confirm Delete' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
