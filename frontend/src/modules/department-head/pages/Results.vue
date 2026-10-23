<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'

const router = useRouter()
const authStore = useAuthStore()
const settingsStore = useSettingsStore()

// ── Active View Tab ──
type ViewTab = 'students' | 'exams' | 'courses' | 'submissions'
const activeTab = ref<ViewTab>('students')

// ── Search & Comprehensive Filter State ──
const search = ref('')
const searchDebounced = ref('')
let searchTimeout: any = null

const yearLevelFilter = ref('all')
const semesterFilter = ref('all')
const sectionFilter = ref('all')
const courseFilter = ref('all')
const statusFilter = ref('all')
const gradeFilter = ref('all')

const currentPage = ref(1)
const perPage = ref(10)

// ── Loading & Async States ──
const isLoading = ref(true)
const isExporting = ref(false)
const isPublishing = ref(false)
const showExportMenu = ref(false)
const successToast = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

const showToast = (msg: string) => {
  successToast.value = msg
  setTimeout(() => {
    successToast.value = null
  }, 4000)
}

// ── Department & KPIs Data ──
const departmentInfo = ref({
  name: 'Department',
  code: 'DEPT',
  head_name: 'Department Head',
  academic_year: '2026',
  semester: 'Second Semester',
  total_courses: 0,
  total_students: 0,
})

const statsData = ref({
  total_exams: 0,
  completed_exams: 0,
  total_students_with_results: 0,
  total_attempts: 0,
  published_results: 0,
  pending_results: 0,
  average_score: 0,
  pass_rate: 0,
  passed_count: 0,
  failed_count: 0,
  courses_evaluated: 0,
})

const studentResultsList = ref<any[]>([])
const allStudentsCount = ref(0)

const resultsExamsList = ref<any[]>([])
const allExamsCount = ref(0)
const coursePerformanceList = ref<any[]>([])

const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
  last_page: 1,
})

const filterOptions = ref({
  courses: [] as { code: string; title: string }[],
  sections: [] as string[],
  year_levels: ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],
  assigned_semester: 'Second Semester',
  semesters: ['Second Semester'],
  grades: ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'F'],
  statuses: ['published', 'graded', 'submitted', 'pending', 'in_progress'],
})

// Super Admin assigned semester (static from Settings)
const assignedSemester = computed(() => {
  return departmentInfo.value.semester || filterOptions.value.assigned_semester || settingsStore.semester || 'Second Semester'
})

// ── Modals & Drawers State ──
const showExamDetailModal = ref(false)
const selectedExam = ref<any>(null)
const examStudentResults = ref<any[]>([])
const examStats = ref<any>(null)
const isLoadingExamDetails = ref(false)

const showAttemptDetailModal = ref(false)
const selectedAttempt = ref<any>(null)
const selectedStudent = ref<any>(null)
const selectedExamForAttempt = ref<any>(null)
const attemptQuestions = ref<any[]>([])
const isLoadingAttemptDetails = ref(false)

const showPublishModal = ref(false)
const examToPublish = ref<any>(null)

// ── Debounce Search Input ──
watch(search, (newVal) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    searchDebounced.value = newVal
    currentPage.value = 1
    fetchResults()
  }, 350)
})

// ── Watch Filter Changes ──
watch([yearLevelFilter, semesterFilter, sectionFilter, courseFilter, statusFilter, gradeFilter, perPage], () => {
  currentPage.value = 1
  fetchResults()
})

watch(activeTab, () => {
  currentPage.value = 1
  fetchResults()
})

// ── Fetch Results from Backend ──
const fetchResults = async () => {
  isLoading.value = true
  errorMessage.value = null
  try {
    const params: any = {
      page: currentPage.value,
      per_page: perPage.value,
      view_mode: activeTab.value,
    }

    if (searchDebounced.value.trim()) params.search = searchDebounced.value.trim()
    if (yearLevelFilter.value !== 'all') {
      params.year_level = yearLevelFilter.value
      params.academic_year = yearLevelFilter.value
    }
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (courseFilter.value !== 'all') params.course_code = courseFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (gradeFilter.value !== 'all') params.grade = gradeFilter.value

    const res = await apiClient.get('/dept-head/results', { params })
    if (res.data?.data) {
      const d = res.data.data
      if (d.department) departmentInfo.value = d.department
      if (d.stats) statsData.value = d.stats
      if (d.course_performance) coursePerformanceList.value = d.course_performance
      if (d.filter_options) filterOptions.value = d.filter_options

      studentResultsList.value = d.student_results || []
      allStudentsCount.value = d.all_students_count || 0

      resultsExamsList.value = d.results || []
      allExamsCount.value = d.all_exams_count || 0

      if (d.pagination) pagination.value = d.pagination
    }
  } catch (err: any) {
    console.error('Failed to fetch department results:', err)
    errorMessage.value = err?.response?.data?.message || 'Failed to load department academic results. Please retry.'
  } finally {
    isLoading.value = false
  }
}

// ── Open Student Attempt Breakdown ──
const openAttemptDetails = async (attemptId: number) => {
  showAttemptDetailModal.value = true
  isLoadingAttemptDetails.value = true
  selectedAttempt.value = null
  selectedStudent.value = null
  selectedExamForAttempt.value = null
  attemptQuestions.value = []

  try {
    const res = await apiClient.get(`/dept-head/results/attempt/${attemptId}`)
    if (res.data?.data) {
      selectedAttempt.value = res.data.data.attempt
      selectedStudent.value = res.data.data.student
      selectedExamForAttempt.value = res.data.data.exam
      attemptQuestions.value = res.data.data.questions || []
    }
  } catch (err: any) {
    console.error('Failed to load student attempt details:', err)
    alert(err?.response?.data?.message || 'Unable to load attempt details.')
    showAttemptDetailModal.value = false
  } finally {
    isLoadingAttemptDetails.value = false
  }
}

// ── Open Exam Student Details ──
const openExamResults = async (exam: any) => {
  selectedExam.value = exam
  showExamDetailModal.value = true
  isLoadingExamDetails.value = true
  examStudentResults.value = []
  examStats.value = null

  try {
    const res = await apiClient.get(`/dept-head/results/${exam.id}`)
    if (res.data?.data) {
      examStudentResults.value = res.data.data.students || []
      examStats.value = res.data.data.stats || null
      if (res.data.data.exam) {
        selectedExam.value = { ...selectedExam.value, ...res.data.data.exam }
      }
    }
  } catch (err: any) {
    console.error('Failed to load exam student results:', err)
    alert(err?.response?.data?.message || 'Unable to retrieve students for this exam.')
  } finally {
    isLoadingExamDetails.value = false
  }
}

// ── Publish Exam Results Workflow ──
const promptPublishExam = (exam: any) => {
  examToPublish.value = exam
  showPublishModal.value = true
}

const confirmPublishExam = async () => {
  if (!examToPublish.value) return
  isPublishing.value = true
  try {
    const res = await apiClient.post(`/dept-head/results/${examToPublish.value.id}/publish`)
    showToast(res.data?.message || 'Examination results published successfully.')
    showPublishModal.value = false
    examToPublish.value = null
    fetchResults()
    if (showExamDetailModal.value && selectedExam.value) {
      openExamResults(selectedExam.value)
    }
  } catch (err: any) {
    console.error('Failed to publish results:', err)
    alert(err?.response?.data?.message || 'Failed to publish results. Please try again.')
  } finally {
    isPublishing.value = false
  }
}

// ── Multi-Format Institutional Export ──
const exportResults = async (format: 'pdf' | 'excel' | 'csv') => {
  showExportMenu.value = false
  isExporting.value = true
  try {
    const params: any = { format }
    if (yearLevelFilter.value !== 'all') {
      params.year_level = yearLevelFilter.value
      params.academic_year = yearLevelFilter.value
    }
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (courseFilter.value !== 'all') params.course_code = courseFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (gradeFilter.value !== 'all') params.grade = gradeFilter.value
    if (searchDebounced.value.trim()) params.search = searchDebounced.value.trim()

    const res = await apiClient.get('/dept-head/results/export', { params })
    const { file, filename, mime_type } = res.data

    if (!file || !filename) {
      throw new Error('Export payload missing file data.')
    }

    const binary = atob(file)
    const array = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i++) {
      array[i] = binary.charCodeAt(i)
    }

    const blob = new Blob([array], { type: mime_type || 'application/octet-stream' })
    const blobUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)

    showToast(`Exported department results as ${format.toUpperCase()}.`)
  } catch (err: any) {
    console.error('Export error:', err)
    alert(err?.response?.data?.message || 'Failed to export results. Please try again.')
  } finally {
    isExporting.value = false
  }
}

// ── Print Sheet ──
const printSheet = () => {
  window.print()
}

// ── Reset Filters ──
const resetFilters = () => {
  search.value = ''
  searchDebounced.value = ''
  yearLevelFilter.value = 'all'
  semesterFilter.value = 'all'
  sectionFilter.value = 'all'
  courseFilter.value = 'all'
  statusFilter.value = 'all'
  gradeFilter.value = 'all'
  currentPage.value = 1
  fetchResults()
}

// Remove single filter
const removeFilter = (key: string) => {
  if (key === 'search') { search.value = ''; searchDebounced.value = '' }
  if (key === 'year_level') yearLevelFilter.value = 'all'
  if (key === 'semester') semesterFilter.value = 'all'
  if (key === 'section') sectionFilter.value = 'all'
  if (key === 'course') courseFilter.value = 'all'
  if (key === 'status') statusFilter.value = 'all'
  if (key === 'grade') gradeFilter.value = 'all'
  currentPage.value = 1
  fetchResults()
}

const activeFiltersCount = computed(() => {
  let count = 0
  if (searchDebounced.value.trim()) count++
  if (yearLevelFilter.value !== 'all') count++
  if (semesterFilter.value !== 'all') count++
  if (sectionFilter.value !== 'all') count++
  if (courseFilter.value !== 'all') count++
  if (statusFilter.value !== 'all') count++
  if (gradeFilter.value !== 'all') count++
  return count
})

// ── Pagination Controls ──
const totalPages = computed(() => Math.max(1, pagination.value.last_page))
const displayPages = computed(() => {
  const tp = totalPages.value
  const cp = currentPage.value
  if (tp <= 7) return Array.from({ length: tp }, (_, i) => i + 1)
  if (cp <= 4) return [1, 2, 3, 4, 5, '...', tp]
  if (cp >= tp - 3) return [1, '...', tp - 4, tp - 3, tp - 2, tp - 1, tp]
  return [1, '...', cp - 1, cp, cp + 1, '...', tp]
})

const changePage = (p: number | string) => {
  if (typeof p === 'number' && p >= 1 && p <= totalPages.value && p !== currentPage.value) {
    currentPage.value = p
    fetchResults()
  }
}

// ── Status & Grade Styling Badges ──
const statusBadge = (s: string) => {
  const status = (s || '').toLowerCase()
  if (status === 'published') return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (status.includes('graded') || status.includes('unpublished')) return 'bg-indigo-50 text-[#5138ed] border-indigo-200'
  if (status === 'completed') return 'bg-sky-50 text-sky-700 border-sky-200'
  if (status === 'submitted') return 'bg-blue-50 text-blue-700 border-blue-200'
  if (status === 'pending') return 'bg-amber-50 text-amber-700 border-amber-200'
  if (status === 'in_progress' || status === 'in progress') return 'bg-slate-100 text-slate-600 border-slate-200'
  return 'bg-slate-100 text-slate-600 border-slate-200'
}

const gradeBadge = (g: string) => {
  const grade = (g || '').toUpperCase()
  if (grade.startsWith('A')) return 'bg-emerald-50 text-emerald-700 border-emerald-200 font-black'
  if (grade.startsWith('B')) return 'bg-sky-50 text-sky-700 border-sky-200 font-bold'
  if (grade.startsWith('C')) return 'bg-amber-50 text-amber-700 border-amber-200 font-bold'
  if (grade.startsWith('D')) return 'bg-orange-50 text-orange-700 border-orange-200 font-bold'
  if (grade === 'F') return 'bg-rose-50 text-rose-700 border-rose-200 font-black'
  return 'bg-slate-100 text-slate-500 border-slate-200 font-medium'
}

const getInitials = (name: string) => {
  if (!name) return '?'
  return name
    .split(' ')
    .filter(Boolean)
    .map(n => n[0])
    .join('')
    .substring(0, 2)
    .toUpperCase()
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

// ── Global Click for Dropdowns ──
const handleGlobalClick = (e: MouseEvent) => {
  const target = e.target as HTMLElement
  if (!target.closest('.export-menu-container')) {
    showExportMenu.value = false
  }
}

onMounted(() => {
  fetchResults()
  settingsStore.fetchSettings()
  window.addEventListener('click', handleGlobalClick)
})
</script>

<template>
  <div class="space-y-6 min-w-0 max-w-full pb-10">

    <!-- Toast Notification -->
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
        class="fixed bottom-6 right-6 z-50 bg-slate-900 text-white px-4 py-3 rounded-xl shadow-xl flex items-center gap-3 border border-slate-700 text-xs sm:text-sm font-semibold"
      >
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
        <span>{{ successToast }}</span>
      </div>
    </transition>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- 1. INSTITUTIONAL PAGE HEADER                                         -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Results & Academic Performance</h1>
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-[#5138ed] border border-indigo-200 shrink-0">
            {{ departmentInfo.code || 'DEPT' }}
          </span>
        </div>
        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
          Monitor student marks, grade distribution, and university department academic evaluation.
        </p>
      </div>

      <div class="flex items-center flex-wrap gap-2.5">
        <!-- Print Sheet -->
        <button
          @click="printSheet"
          class="inline-flex items-center justify-center gap-2 border border-slate-200 hover:bg-slate-50 active:bg-slate-100 text-slate-700 text-xs sm:text-sm font-bold px-3.5 py-2.5 rounded-xl transition-all shadow-sm bg-white min-h-[42px]"
          title="Print official department results"
        >
          <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
          <span class="hidden sm:inline">Print Sheet</span>
        </button>

        <!-- Multi-Format Export Dropdown -->
        <div class="relative export-menu-container">
          <button
            @click.stop="showExportMenu = !showExportMenu"
            :disabled="isExporting"
            class="inline-flex items-center justify-center gap-2 border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-bold px-3.5 py-2.5 rounded-xl transition-all shadow-sm bg-white min-h-[42px] disabled:opacity-50"
          >
            <svg v-if="!isExporting" class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            <svg v-else class="w-4 h-4 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            <span>{{ isExporting ? 'Exporting...' : 'Export Results' }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </button>

          <div
            v-if="showExportMenu"
            class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 z-40 text-xs font-semibold animate-in fade-in zoom-in-95 duration-150"
          >
            <button
              @click="exportResults('excel')"
              class="w-full flex items-center gap-2.5 px-4 py-2.5 hover:bg-slate-50 text-slate-700 text-left transition-colors"
            >
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              Export Excel (.xlsx)
            </button>
            <button
              @click="exportResults('pdf')"
              class="w-full flex items-center gap-2.5 px-4 py-2.5 hover:bg-slate-50 text-slate-700 text-left transition-colors"
            >
              <span class="w-2 h-2 rounded-full bg-rose-500"></span>
              Export PDF Document
            </button>
            <button
              @click="exportResults('csv')"
              class="w-full flex items-center gap-2.5 px-4 py-2.5 hover:bg-slate-50 text-slate-700 text-left transition-colors"
            >
              <span class="w-2 h-2 rounded-full bg-sky-500"></span>
              Export CSV Spreadsheet
            </button>
          </div>
        </div>

        <!-- Refresh Button -->
        <button
          @click="fetchResults"
          :disabled="isLoading"
          class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-[#5138ed] border border-indigo-200 rounded-xl text-xs sm:text-sm font-bold transition-colors min-h-[42px] disabled:opacity-50"
          title="Refresh results data"
        >
          <svg :class="['w-4 h-4', isLoading ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          <span class="hidden sm:inline">Refresh</span>
        </button>
      </div>
    </div>

    <!-- Error Banner -->
    <div v-if="errorMessage" class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start justify-between gap-3 text-rose-800 text-xs sm:text-sm">
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span>{{ errorMessage }}</span>
      </div>
      <button @click="fetchResults" class="font-bold underline hover:text-rose-950">Retry</button>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- 2. DEPARTMENT OVERVIEW & ACADEMIC CONTEXT BANNER                     -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-5">
      <div class="flex items-center gap-4 min-w-0">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-indigo-50 text-[#5138ed] border border-indigo-100 flex items-center justify-center shrink-0">
          <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
        </div>
        <div class="min-w-0">
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Department Academic Center</span>
          <h2 class="text-base sm:text-lg font-black text-slate-900 truncate capitalize">
            {{ departmentInfo.name }} ({{ departmentInfo.code }})
          </h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5 truncate">
            Department Head: <span class="font-bold text-slate-700">{{ departmentInfo.head_name }}</span>
          </p>
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-6 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100">
        <div>
          <span class="text-[10px] font-bold uppercase text-slate-400 block">Academic Year</span>
          <span class="text-xs sm:text-sm font-black text-slate-800">{{ departmentInfo.academic_year }}</span>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase text-slate-400 block">Active Semester</span>
          <span class="text-xs sm:text-sm font-black text-[#5138ed]">{{ assignedSemester }}</span>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase text-slate-400 block">Dept Courses</span>
          <span class="text-xs sm:text-sm font-black text-slate-800">{{ departmentInfo.total_courses }}</span>
        </div>
        <div>
          <span class="text-[10px] font-bold uppercase text-slate-400 block">Enrolled Students</span>
          <span class="text-xs sm:text-sm font-black text-slate-800">{{ departmentInfo.total_students }}</span>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- 3. REAL DEPARTMENT ACADEMIC KPI METRICS                              -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
      <!-- Total Students Evaluated -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow">
        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Students Evaluated</span>
        <p class="text-xl sm:text-2xl font-black text-slate-900 mt-1">{{ statsData.total_students_with_results }}</p>
        <p class="text-[11px] font-medium text-slate-500 mt-0.5">With exam submissions</p>
      </div>

      <!-- Total Exam Submissions / Attempts -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow">
        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Submissions</span>
        <p class="text-xl sm:text-2xl font-black text-indigo-600 mt-1">{{ statsData.total_attempts }}</p>
        <p class="text-[11px] font-medium text-slate-500 mt-0.5">Across all examinations</p>
      </div>

      <!-- Published Results -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow">
        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Published Results</span>
        <p class="text-xl sm:text-2xl font-black text-emerald-600 mt-1">{{ statsData.published_results }}</p>
        <p class="text-[11px] font-medium text-emerald-600 mt-0.5">Visible to students</p>
      </div>

      <!-- Pending Results -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow">
        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Pending Grading</span>
        <p class="text-xl sm:text-2xl font-black text-amber-600 mt-1">{{ statsData.pending_results }}</p>
        <p class="text-[11px] font-medium text-amber-600 mt-0.5">Awaiting publication</p>
      </div>

      <!-- Average Score -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow">
        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Department Average</span>
        <p class="text-xl sm:text-2xl font-black text-slate-900 mt-1">{{ statsData.average_score }}%</p>
        <p class="text-[11px] font-medium text-slate-500 mt-0.5">Graded performance</p>
      </div>

      <!-- Overall Pass Rate -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow">
        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Overall Pass Rate</span>
        <p class="text-xl sm:text-2xl font-black text-emerald-600 mt-1">{{ statsData.pass_rate }}%</p>
        <p class="text-[11px] font-medium text-slate-500 mt-0.5">≥ 50% passing criteria</p>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- 4. STUDENT EXAMINATION RESULTS SECTION & COMPREHENSIVE FILTER PANEL  -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden space-y-4 p-4 sm:p-5">
      
      <!-- Top Title & View Tabs -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
          <h2 class="text-base font-black text-slate-900 tracking-tight">Student Examination Results</h2>
          <p class="text-xs text-slate-400">Detailed student marks, grades, and examination attempts breakdown</p>
        </div>

        <div class="flex items-center gap-2 overflow-x-auto">
          <!-- By Student Attempt Tab (Primary) -->
          <button
            @click="activeTab = 'students'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2',
              activeTab === 'students'
                ? 'bg-[#5138ed] text-white shadow-sm'
                : 'text-slate-600 hover:bg-slate-100'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            <span>By Student Attempt ({{ allStudentsCount }})</span>
          </button>

          <!-- By Examination Tab -->
          <button
            @click="activeTab = 'exams'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2',
              activeTab === 'exams'
                ? 'bg-[#5138ed] text-white shadow-sm'
                : 'text-slate-600 hover:bg-slate-100'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
            <span>By Examination ({{ allExamsCount }})</span>
          </button>

          <!-- Course Breakdown Tab -->
          <button
            @click="activeTab = 'courses'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2',
              activeTab === 'courses'
                ? 'bg-[#5138ed] text-white shadow-sm'
                : 'text-slate-600 hover:bg-slate-100'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            <span>Courses ({{ coursePerformanceList.length }})</span>
          </button>
        </div>
      </div>

      <!-- ── Comprehensive Filter Panel ── -->
      <div class="bg-slate-50/70 border border-slate-200/70 rounded-2xl p-3.5 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
          
          <!-- 1. Search Query -->
          <div class="relative lg:col-span-2">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            <input
              v-model="search"
              type="text"
              placeholder="Search by Student ID, Student Name, Course, Exam..."
              class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white text-slate-800 placeholder:text-slate-400 shadow-2xs"
            />
          </div>

          <!-- 2. Year Level Filter (1st Year, 2nd Year, 3rd Year, 4th Year, 5th Year) -->
          <div>
            <select
              v-model="yearLevelFilter"
              class="w-full px-3 py-2 text-xs font-medium border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed] shadow-2xs"
            >
              <option value="all">All Year Levels</option>
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
              <option value="5th Year">5th Year</option>
            </select>
          </div>

          <!-- 3. Semester Filter (Integrated with Super Admin assigned semester) -->
          <div>
            <select
              v-model="semesterFilter"
              class="w-full px-3 py-2 text-xs font-medium border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed] shadow-2xs"
            >
              <option value="all">All Semesters</option>
              <option :value="assignedSemester">{{ assignedSemester }}</option>
            </select>
          </div>

          <!-- 4. Section Filter -->
          <div>
            <select
              v-model="sectionFilter"
              class="w-full px-3 py-2 text-xs font-medium border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed] shadow-2xs"
            >
              <option value="all">All Sections</option>
              <option v-for="sec in filterOptions.sections" :key="sec" :value="sec">{{ sec }}</option>
            </select>
          </div>

          <!-- 5. Course Filter -->
          <div>
            <select
              v-model="courseFilter"
              class="w-full px-3 py-2 text-xs font-medium border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed] shadow-2xs truncate"
            >
              <option value="all">All Courses</option>
              <option v-for="c in filterOptions.courses" :key="c.code" :value="c.code">{{ c.code }} — {{ c.title }}</option>
            </select>
          </div>

          <!-- 6. Result Status Filter -->
          <div>
            <select
              v-model="statusFilter"
              class="w-full px-3 py-2 text-xs font-medium border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed] shadow-2xs"
            >
              <option value="all">All Result Status</option>
              <option value="published">Published</option>
              <option value="graded">Graded (Unpublished)</option>
              <option value="pending">Pending</option>
              <option value="submitted">Submitted</option>
              <option value="in_progress">In Progress</option>
            </select>
          </div>

          <!-- 7. Grade Filter -->
          <div class="flex items-center gap-2">
            <select
              v-model="gradeFilter"
              class="w-full px-3 py-2 text-xs font-medium border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed] shadow-2xs"
            >
              <option value="all">All Letter Grades</option>
              <option v-for="g in filterOptions.grades" :key="g" :value="g">Grade {{ g }}</option>
            </select>

            <!-- Reset Filters -->
            <button
              v-if="activeFiltersCount > 0"
              @click="resetFilters"
              class="px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 border border-rose-200 rounded-xl transition-colors shrink-0 shadow-2xs flex items-center gap-1"
              title="Reset all filters"
            >
              <span>Clear</span>
              <span class="w-4 h-4 rounded-full bg-rose-100 text-rose-700 text-[10px] flex items-center justify-center font-bold">{{ activeFiltersCount }}</span>
            </button>
          </div>

        </div>

        <!-- Active Filter Badges & Count Strip -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-200/50 text-xs">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mr-1">Active:</span>

            <span v-if="activeFiltersCount === 0" class="text-slate-400 italic text-[11px]">No active filters applied</span>

            <span v-if="searchDebounced" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-indigo-50 border border-indigo-200 text-[#5138ed] text-[11px] font-medium">
              Search: "{{ searchDebounced }}"
              <button @click="removeFilter('search')" class="hover:text-indigo-900 ml-0.5">✕</button>
            </span>

            <span v-if="yearLevelFilter !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-indigo-50 border border-indigo-200 text-[#5138ed] text-[11px] font-medium">
              Year Level: {{ yearLevelFilter }}
              <button @click="removeFilter('year_level')" class="hover:text-indigo-900 ml-0.5">✕</button>
            </span>

            <span v-if="semesterFilter !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-indigo-50 border border-indigo-200 text-[#5138ed] text-[11px] font-medium">
              Semester: {{ semesterFilter }}
              <button @click="removeFilter('semester')" class="hover:text-indigo-900 ml-0.5">✕</button>
            </span>

            <span v-if="sectionFilter !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-indigo-50 border border-indigo-200 text-[#5138ed] text-[11px] font-medium">
              Section: {{ sectionFilter }}
              <button @click="removeFilter('section')" class="hover:text-indigo-900 ml-0.5">✕</button>
            </span>

            <span v-if="courseFilter !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-indigo-50 border border-indigo-200 text-[#5138ed] text-[11px] font-medium">
              Course: {{ courseFilter }}
              <button @click="removeFilter('course')" class="hover:text-indigo-900 ml-0.5">✕</button>
            </span>

            <span v-if="statusFilter !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-indigo-50 border border-indigo-200 text-[#5138ed] text-[11px] font-medium capitalize">
              Status: {{ statusFilter }}
              <button @click="removeFilter('status')" class="hover:text-indigo-900 ml-0.5">✕</button>
            </span>

            <span v-if="gradeFilter !== 'all'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-indigo-50 border border-indigo-200 text-[#5138ed] text-[11px] font-medium">
              Grade: {{ gradeFilter }}
              <button @click="removeFilter('grade')" class="hover:text-indigo-900 ml-0.5">✕</button>
            </span>
          </div>

          <!-- Total matching records count badge -->
          <div class="text-right">
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
              {{ pagination.total }} matching {{ activeTab === 'exams' ? 'exams' : 'student records' }}
            </span>
          </div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- TAB CONTENT 1: BY STUDENT ATTEMPT (PRIMARY 14 COLUMNS)               -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'students'">
        <!-- Loading Skeleton -->
        <div v-if="isLoading" class="p-12 text-center text-slate-500">
          <div class="inline-block animate-spin w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full mb-3"></div>
          <p class="text-xs sm:text-sm font-medium">Loading department student examination results...</p>
        </div>

        <template v-else>
          <!-- Desktop Table View (Exact 14 Columns) -->
          <div class="hidden md:block overflow-x-auto min-w-0 w-full border border-slate-100 rounded-2xl">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50/80 border-b border-slate-100 text-slate-400 font-bold uppercase text-[10px]">
                <tr>
                  <th class="px-3.5 py-3.5 text-center" style="width: 35px;">No.</th>
                  <th class="px-3.5 py-3.5" style="width: 95px;">Student ID</th>
                  <th class="px-4 py-3.5">Student Name</th>
                  <th class="px-3 py-3.5" style="width: 75px;">Course Code</th>
                  <th class="px-3.5 py-3.5">Course Name</th>
                  <th class="px-3 py-3.5 text-center" style="width: 75px;">Section</th>
                  <th class="px-3.5 py-3.5 text-center" style="width: 80px;">Year Level</th>
                  <th class="px-3.5 py-3.5 text-center" style="width: 80px;">Semester</th>
                  <th class="px-4 py-3.5">Exam</th>
                  <th class="px-3 py-3.5 text-center" style="width: 60px;">Total</th>
                  <th class="px-3.5 py-3.5 text-center" style="width: 75px;">Obtained</th>
                  <th class="px-3.5 py-3.5 text-center" style="width: 55px;">Grade</th>
                  <th class="px-3.5 py-3.5 text-center" style="width: 95px;">Status</th>
                  <th class="px-4 py-3.5 text-right" style="width: 85px;">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="studentResultsList.length === 0">
                  <td colspan="14" class="p-12 text-center text-slate-400">
                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <p class="text-sm font-bold text-slate-600">No student results found</p>
                    <p class="text-xs text-slate-400 mt-1">Try adjusting your filters or search terms.</p>
                  </td>
                </tr>

                <tr v-for="s in studentResultsList" :key="s.id" class="hover:bg-slate-50/60 transition-colors">
                  <!-- 1. No. -->
                  <td class="px-3.5 py-4 text-center text-slate-400 font-bold">
                    {{ s.no }}
                  </td>

                  <!-- 2. Student ID -->
                  <td class="px-3.5 py-4">
                    <span class="font-mono text-xs font-bold text-slate-700 bg-slate-50 px-2 py-0.5 rounded border border-slate-200 block truncate">
                      {{ s.student_id }}
                    </span>
                  </td>

                  <!-- 3. Student Name -->
                  <td class="px-4 py-4">
                    <div class="flex items-center gap-2.5">
                      <div :class="['w-7 h-7 rounded-full flex items-center justify-center font-bold text-[10px] shrink-0', getAvatarColor(s.student_db_id || 0)]">
                        {{ getInitials(s.student_name) }}
                      </div>
                      <div class="min-w-0">
                        <span class="block font-bold text-slate-900 truncate">{{ s.student_name }}</span>
                        <span class="block text-[10px] text-slate-400 truncate">{{ s.student_email }}</span>
                      </div>
                    </div>
                  </td>

                  <!-- 4. Course Code -->
                  <td class="px-3 py-4">
                    <span class="px-1.5 py-0.5 text-[10px] font-mono font-bold bg-indigo-50 text-[#5138ed] rounded border border-indigo-200">
                      {{ s.course_code }}
                    </span>
                  </td>

                  <!-- 5. Course Name -->
                  <td class="px-3.5 py-4">
                    <span class="font-medium text-slate-800 line-clamp-1" :title="s.course_name">
                      {{ s.course_name }}
                    </span>
                  </td>

                  <!-- 6. Section -->
                  <td class="px-3.5 py-4 text-center">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                      {{ s.section }}
                    </span>
                  </td>

                  <!-- 7. Year Level (1st Year, 2nd Year, etc.) -->
                  <td class="px-3.5 py-4 text-center">
                    <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-[#5138ed] border border-indigo-200 text-[10px] font-bold whitespace-nowrap">
                      {{ s.year_level || s.student_year || '1st Year' }}
                    </span>
                  </td>

                  <!-- 8. Semester -->
                  <td class="px-3.5 py-4 text-center font-medium text-slate-700 text-[11px] truncate">
                    {{ s.semester }}
                  </td>

                  <!-- 9. Exam -->
                  <td class="px-4 py-4">
                    <span class="block font-medium text-slate-900 line-clamp-1" :title="s.exam_title">
                      {{ s.exam_title }}
                    </span>
                    <span class="text-[10px] font-mono text-slate-400">{{ s.exam_code }}</span>
                  </td>

                  <!-- 10. Total Marks -->
                  <td class="px-3.5 py-4 text-center font-bold text-slate-500">
                    {{ s.total_marks }}
                  </td>

                  <!-- 11. Obtained Marks -->
                  <td class="px-3.5 py-4 text-center">
                    <span v-if="s.is_graded && s.obtained_marks !== null" class="font-black text-slate-900 text-xs">
                      {{ s.obtained_marks }} pts
                    </span>
                    <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                      Pending
                    </span>
                  </td>

                  <!-- 12. Grade -->
                  <td class="px-3.5 py-4 text-center">
                    <span v-if="s.is_graded" :class="[gradeBadge(s.grade), 'px-2 py-0.5 rounded text-[11px] border']">
                      {{ s.grade }}
                    </span>
                    <span v-else class="text-slate-400">—</span>
                  </td>

                  <!-- 13. Result Status -->
                  <td class="px-3.5 py-4 text-center">
                    <span :class="[statusBadge(s.status), 'px-2 py-0.5 rounded-full text-[10px] font-bold border capitalize block whitespace-nowrap']">
                      {{ s.status }}
                    </span>
                  </td>

                  <!-- 14. Actions -->
                  <td class="px-4 py-4 text-right">
                    <button
                      @click="openAttemptDetails(s.attempt_id)"
                      class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors inline-flex items-center gap-1 shadow-2xs"
                      title="Inspect student examination submission & answers"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                      <span>View</span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile Cards View for Students -->
          <div class="md:hidden divide-y divide-slate-100 border border-slate-100 rounded-2xl">
            <div v-if="studentResultsList.length === 0" class="p-8 text-center text-slate-400 text-xs">
              No student results found.
            </div>
            <div v-for="s in studentResultsList" :key="s.id" class="p-4 space-y-3">
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5">
                  <div :class="['w-8 h-8 rounded-full flex items-center justify-center font-black text-xs shrink-0', getAvatarColor(s.student_db_id || 0)]">
                    {{ getInitials(s.student_name) }}
                  </div>
                  <div>
                    <h4 class="text-xs font-bold text-slate-900">{{ s.student_name }}</h4>
                    <p class="text-[10px] font-mono text-slate-500">{{ s.student_id }}</p>
                  </div>
                </div>
                <span :class="[gradeBadge(s.grade), 'px-2 py-0.5 rounded text-[11px] border shrink-0']">
                  {{ s.grade }}
                </span>
              </div>

              <div class="bg-slate-50 p-2.5 rounded-xl space-y-1 text-xs text-slate-600">
                <div class="flex justify-between">
                  <span class="text-slate-400">Course:</span>
                  <span class="font-bold text-slate-800">{{ s.course_name }} ({{ s.course_code }})</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-400">Exam:</span>
                  <span class="font-medium text-slate-700">{{ s.exam_title }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-400">Year Level & Section:</span>
                  <span class="font-medium text-slate-700">{{ s.year_level || s.student_year }} &bull; {{ s.section }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-400">Semester:</span>
                  <span class="font-medium text-slate-700">{{ s.semester }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-400">Obtained / Total:</span>
                  <span class="font-bold text-slate-900">
                    {{ s.is_graded && s.obtained_marks !== null ? `${s.obtained_marks} / ${s.total_marks} (${s.percentage}%)` : 'Not Graded' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-400">Status:</span>
                  <span :class="[statusBadge(s.status), 'px-2 py-0.5 rounded-full text-[10px] font-bold border capitalize']">
                    {{ s.status }}
                  </span>
                </div>
              </div>

              <button
                @click="openAttemptDetails(s.attempt_id)"
                class="w-full py-2 text-xs font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors text-center"
              >
                View Full Details & Answers
              </button>
            </div>
          </div>
        </template>
      </div>

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- TAB CONTENT 2: BY EXAMINATION                                        -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div v-else-if="activeTab === 'exams'">
        <div v-if="isLoading" class="p-12 text-center text-slate-500">
          <div class="inline-block animate-spin w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full mb-3"></div>
          <p class="text-xs sm:text-sm font-medium">Loading department examinations...</p>
        </div>

        <template v-else>
          <div class="hidden md:block overflow-x-auto min-w-0 w-full border border-slate-100 rounded-2xl">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50/80 border-b border-slate-100 text-slate-400 font-bold uppercase text-[10px]">
                <tr>
                  <th class="px-5 py-3.5">Exam Title & Code</th>
                  <th class="px-4 py-3.5">Course</th>
                  <th class="px-4 py-3.5">Lead Faculty</th>
                  <th class="px-4 py-3.5 text-center">Submissions</th>
                  <th class="px-4 py-3.5 text-center">Avg Score</th>
                  <th class="px-4 py-3.5 text-center">Pass Rate</th>
                  <th class="px-4 py-3.5 text-center">Status</th>
                  <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="resultsExamsList.length === 0">
                  <td colspan="8" class="p-12 text-center text-slate-400">
                    No examination results found.
                  </td>
                </tr>
                <tr v-for="r in resultsExamsList" :key="r.id" class="hover:bg-slate-50/60 transition-colors">
                  <td class="px-5 py-4">
                    <span class="block text-xs sm:text-sm font-bold text-slate-900">{{ r.title }}</span>
                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-50 text-[#5138ed] border border-indigo-200 mt-1">
                      {{ r.code }}
                    </span>
                  </td>
                  <td class="px-4 py-4">
                    <span class="block font-bold text-slate-800">{{ r.course_name }}</span>
                    <span class="text-[11px] text-slate-400 font-mono">{{ r.course_code }}</span>
                  </td>
                  <td class="px-4 py-4">
                    <div class="flex items-center gap-2">
                      <div :class="['w-7 h-7 rounded-full flex items-center justify-center font-bold text-[10px] shrink-0', getAvatarColor(r.instructor_id || 0)]">
                        {{ getInitials(r.instructor_name) }}
                      </div>
                      <span class="font-medium text-slate-700 truncate max-w-[140px]">{{ r.instructor_name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-4 text-center">
                    <span class="font-bold text-slate-900">{{ r.submitted_count }} / {{ r.total_students }}</span>
                    <span class="block text-[10px] text-slate-400 mt-0.5">{{ r.graded_count }} graded</span>
                  </td>
                  <td class="px-4 py-4 text-center">
                    <span v-if="r.average_pct !== null" class="font-black text-slate-900">{{ r.average_pct }}%</span>
                    <span v-else class="text-slate-400">—</span>
                  </td>
                  <td class="px-4 py-4 text-center">
                    <span v-if="r.pass_rate !== null" class="font-black text-emerald-600">{{ r.pass_rate }}%</span>
                    <span v-else class="text-slate-400">—</span>
                  </td>
                  <td class="px-4 py-4 text-center">
                    <span :class="[statusBadge(r.status), 'px-2.5 py-0.5 rounded-full text-[10px] font-bold border capitalize']">
                      {{ r.status }}
                    </span>
                  </td>
                  <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button
                        @click="openExamResults(r)"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors"
                      >
                        View Scores
                      </button>
                      <button
                        v-if="r.status !== 'Published' && r.submitted_count > 0"
                        @click="promptPublishExam(r)"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition-colors"
                      >
                        Publish
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- TAB CONTENT 3: COURSE BREAKDOWN                                      -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div v-else-if="activeTab === 'courses'">
        <div v-if="coursePerformanceList.length === 0" class="p-12 text-center text-slate-400">
          <p class="text-sm font-bold text-slate-600">No course performance evaluations recorded</p>
          <p class="text-xs text-slate-400 mt-1">Exams and student grades will populate course performance data.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="cp in coursePerformanceList"
            :key="cp.course_code"
            class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4 hover:shadow-md transition-shadow"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-50 text-[#5138ed] border border-indigo-200">
                  {{ cp.course_code }}
                </span>
                <h4 class="text-sm font-bold text-slate-900 mt-1">{{ cp.course_name }}</h4>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ cp.credits }} Credit Hours</p>
              </div>
              <span :class="[statusBadge(cp.status), 'px-2 py-0.5 rounded-full text-[10px] font-bold border capitalize']">
                {{ cp.status }}
              </span>
            </div>

            <div class="grid grid-cols-2 gap-3 text-center pt-2 border-t border-slate-100">
              <div class="bg-slate-50 p-2.5 rounded-xl">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">Average Score</span>
                <span class="text-base font-black text-slate-900">{{ cp.average_score }}%</span>
              </div>
              <div class="bg-emerald-50/60 p-2.5 rounded-xl border border-emerald-100">
                <span class="text-[10px] font-bold text-emerald-600 block uppercase">Pass Rate</span>
                <span class="text-base font-black text-emerald-700">{{ cp.pass_rate }}%</span>
              </div>
            </div>

            <div class="space-y-1.5 text-xs text-slate-600 pt-1">
              <div class="flex justify-between">
                <span class="text-slate-400">Total Submissions:</span>
                <span class="font-bold text-slate-800">{{ cp.total_attempts }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Score Range:</span>
                <span class="font-bold text-slate-800">{{ cp.lowest_score }} to {{ cp.highest_score }} pts</span>
              </div>
            </div>

            <button
              @click="courseFilter = cp.course_code; activeTab = 'students'"
              class="w-full py-2 text-xs font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors text-center"
            >
              Filter Student Results by this Course
            </button>
          </div>
        </div>
      </div>

      <!-- ════════════════════════════════════════════════════════════════════ -->
      <!-- PAGINATION CONTROLS                                                  -->
      <!-- ════════════════════════════════════════════════════════════════════ -->
      <div
        v-if="activeTab === 'exams' || activeTab === 'students'"
        class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs text-slate-500"
      >
        <div class="flex items-center gap-3">
          <p>
            Showing {{ pagination.total === 0 ? 0 : (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, pagination.total) }} of {{ pagination.total }} records
          </p>
          <div class="flex items-center gap-1.5 ml-2">
            <span class="text-[11px] text-slate-400">Rows:</span>
            <select
              v-model="perPage"
              class="border border-slate-200 rounded-lg px-2 py-1 text-xs bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-1.5">
          <button
            @click="changePage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors font-bold"
          >
            Previous
          </button>

          <template v-for="(p, idx) in displayPages" :key="idx">
            <span v-if="p === '...'" class="px-2 py-1 text-slate-400">...</span>
            <button
              v-else
              @click="changePage(p)"
              :class="[
                'w-8 h-8 rounded-lg font-bold transition-colors flex items-center justify-center text-xs',
                currentPage === p
                  ? 'bg-[#5138ed] text-white shadow-xs'
                  : 'border border-slate-200 text-slate-600 hover:bg-slate-50'
              ]"
            >
              {{ p }}
            </button>
          </template>

          <button
            @click="changePage(currentPage + 1)"
            :disabled="currentPage >= totalPages"
            class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors font-bold"
          >
            Next
          </button>
        </div>
      </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL 1: VIEW DETAILS MODAL (STUDENT ATTEMPT AUDIT)                   -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showAttemptDetailModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden my-auto animate-in zoom-in-95 duration-200">
          
          <!-- Header -->
          <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-slate-100 bg-slate-50/70 shrink-0">
            <div>
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-50 text-[#5138ed] border border-indigo-200">
                Examination Result Audit
              </span>
              <h3 class="text-base font-black text-slate-900 mt-1">Student Performance Inspection</h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5">{{ selectedExamForAttempt?.title }}</p>
            </div>
            <button
              @click="showAttemptDetailModal = false"
              class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 text-lg transition-colors"
            >
              ✕
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 sm:p-6 overflow-y-auto flex-1 min-h-0 space-y-5">
            <div v-if="isLoadingAttemptDetails" class="py-12 text-center text-slate-500">
              <div class="inline-block animate-spin w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full mb-3"></div>
              <p class="text-xs font-medium">Retrieving attempt details and answers...</p>
            </div>

            <div v-else class="space-y-5">
              
              <!-- Student Profile & Score Summary Card -->
              <div class="bg-indigo-50/40 border border-indigo-100 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-[#5138ed] flex items-center justify-center font-black text-base shrink-0">
                    {{ getInitials(selectedStudent?.name || '') }}
                  </div>
                  <div>
                    <h4 class="text-sm font-black text-slate-900">{{ selectedStudent?.name }}</h4>
                    <p class="text-xs font-mono text-slate-600 font-bold">
                      Student ID: {{ selectedStudent?.student_id }}
                    </p>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                      {{ selectedStudent?.email }} &bull; {{ selectedStudent?.year_level }} &bull; {{ selectedStudent?.section }} &bull; {{ selectedStudent?.department }}
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-indigo-100 shadow-xs">
                  <div class="text-center px-2">
                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Score</span>
                    <span class="text-base font-black text-slate-900">
                      {{ selectedAttempt?.score !== null ? `${selectedAttempt?.score} / ${selectedAttempt?.total_marks}` : 'Not Graded' }}
                    </span>
                  </div>
                  <div class="text-center px-2 border-l border-slate-100">
                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Grade</span>
                    <span :class="[gradeBadge(selectedAttempt?.grade || ''), 'px-2 py-0.5 rounded text-xs font-black inline-block mt-0.5']">
                      {{ selectedAttempt?.grade || 'Pending' }}
                    </span>
                  </div>
                  <div class="text-center px-2 border-l border-slate-100">
                    <span class="text-[10px] text-slate-400 uppercase font-bold block">GPA (4.0)</span>
                    <span class="text-sm font-black text-[#5138ed]">{{ Number(selectedAttempt?.grade_point || 0).toFixed(2) }}</span>
                  </div>
                </div>
              </div>

              <!-- Examination Metadata Strip -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200/60 text-xs">
                <div>
                  <span class="text-[10px] uppercase font-bold text-slate-400 block">Course</span>
                  <span class="font-bold text-slate-800">{{ selectedExamForAttempt?.course_code }}</span>
                </div>
                <div>
                  <span class="text-[10px] uppercase font-bold text-slate-400 block">Academic Term</span>
                  <span class="font-bold text-slate-800">{{ selectedExamForAttempt?.semester }}</span>
                </div>
                <div>
                  <span class="text-[10px] uppercase font-bold text-slate-400 block">Lead Instructor</span>
                  <span class="font-bold text-slate-800">{{ selectedExamForAttempt?.instructor_name }}</span>
                </div>
                <div>
                  <span class="text-[10px] uppercase font-bold text-slate-400 block">Submission Date</span>
                  <span class="font-bold text-slate-800">{{ selectedAttempt?.submitted_at }}</span>
                </div>
              </div>

              <!-- Question Responses Breakdown -->
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Questions & Student Submission Review ({{ attemptQuestions.length }} Items)
                  </h4>
                  <span class="text-[11px] font-bold text-indigo-600">
                    Total: {{ selectedAttempt?.score }} / {{ selectedAttempt?.total_marks }} marks ({{ selectedAttempt?.percentage }}%)
                  </span>
                </div>

                <div v-if="attemptQuestions.length === 0" class="p-6 text-center text-slate-400 text-xs border border-dashed rounded-xl">
                  No question-by-question breakdown available for this attempt.
                </div>

                <div v-else class="space-y-3">
                  <div
                    v-for="q in attemptQuestions"
                    :key="q.id"
                    class="p-4 rounded-2xl border transition-all"
                    :class="[
                      q.is_correct
                        ? 'bg-emerald-50/40 border-emerald-200/80'
                        : (q.is_partial || (q.awarded_marks > 0 && q.awarded_marks < q.max_marks))
                          ? 'bg-amber-50/40 border-amber-200/80'
                          : 'bg-slate-50 border-slate-200/80'
                    ]"
                  >
                    <div class="flex items-center justify-between text-xs mb-2">
                      <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-600">Question {{ q.number }} &bull; {{ q.type }}</span>
                        <span v-if="q.has_manual_score" class="px-2 py-0.5 rounded text-[9px] font-bold bg-indigo-50 text-[#5138ed] border border-indigo-200 uppercase tracking-wider">
                          Instructor Graded
                        </span>
                      </div>

                      <span
                        class="font-bold px-2 py-0.5 rounded text-[10px]"
                        :class="[
                          q.is_correct
                            ? 'bg-emerald-100 text-emerald-800'
                            : (q.is_partial || (q.awarded_marks > 0 && q.awarded_marks < q.max_marks))
                              ? 'bg-amber-100 text-amber-800'
                              : (q.status_label === 'Pending Review' ? 'bg-slate-100 text-slate-700' : 'bg-rose-100 text-rose-800')
                        ]"
                      >
                        {{ q.awarded_marks }} / {{ q.max_marks }} marks &bull;
                        {{ q.is_correct ? 'Correct' : ((q.is_partial || (q.awarded_marks > 0 && q.awarded_marks < q.max_marks)) ? 'Partial Credit' : (q.status_label || 'Incorrect')) }}
                      </span>
                    </div>

                    <p class="text-xs font-bold text-slate-900 mb-2">{{ q.text }}</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100">
                      <div class="bg-white p-2.5 rounded-xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Student Answer:</span>
                        <span class="font-bold text-slate-800 break-words">{{ q.student_answer !== null ? q.student_answer : 'No answer submitted' }}</span>
                      </div>
                      <div class="bg-white p-2.5 rounded-xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Correct Answer:</span>
                        <span class="font-bold text-emerald-700 break-words">{{ q.correct_answer || 'Pending evaluation' }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/70 shrink-0">
            <button
              @click="printSheet"
              class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition-colors inline-flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
              <span>Print Audit Sheet</span>
            </button>
            <button
              @click="showAttemptDetailModal = false"
              class="px-5 py-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm"
            >
              Close
            </button>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL 2: EXAM STUDENT SCORES LIST MODAL                              -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showExamDetailModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden my-auto animate-in zoom-in-95 duration-200">
          
          <!-- Modal Header -->
          <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-slate-100 bg-slate-50/70 shrink-0">
            <div class="min-w-0 pr-3">
              <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-50 text-[#5138ed] border border-indigo-200">
                {{ selectedExam?.code }}
              </span>
              <h3 class="text-base sm:text-lg font-black text-slate-900 mt-1 truncate">{{ selectedExam?.title }}</h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5 truncate">
                {{ selectedExam?.course_name }} ({{ selectedExam?.course_code }}) &bull; Faculty: {{ selectedExam?.instructor_name }}
              </p>
            </div>
            <button
              @click="showExamDetailModal = false"
              class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 text-lg transition-colors shrink-0"
            >
              ✕
            </button>
          </div>

          <!-- Modal KPI Strip -->
          <div v-if="examStats" class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50/40 border-b border-slate-100 text-xs">
            <div class="bg-white p-2.5 rounded-xl border border-slate-100 text-center">
              <span class="text-[10px] text-slate-400 block uppercase font-bold">Submissions</span>
              <span class="font-black text-slate-900 text-sm">{{ examStats.total_attempts }}</span>
            </div>
            <div class="bg-white p-2.5 rounded-xl border border-slate-100 text-center">
              <span class="text-[10px] text-slate-400 block uppercase font-bold">Average Score</span>
              <span class="font-black text-slate-900 text-sm">{{ examStats.average_score }}%</span>
            </div>
            <div class="bg-white p-2.5 rounded-xl border border-slate-100 text-center">
              <span class="text-[10px] text-slate-400 block uppercase font-bold">Passed</span>
              <span class="font-black text-emerald-600 text-sm">{{ examStats.passed_count }}</span>
            </div>
            <div class="bg-white p-2.5 rounded-xl border border-slate-100 text-center">
              <span class="text-[10px] text-slate-400 block uppercase font-bold">Pass Rate</span>
              <span class="font-black text-emerald-600 text-sm">{{ examStats.pass_rate }}%</span>
            </div>
          </div>

          <!-- Modal Body (Students Table) -->
          <div class="p-5 overflow-y-auto flex-1 min-h-0 space-y-4">
            <div v-if="isLoadingExamDetails" class="py-12 text-center text-slate-500">
              <div class="inline-block animate-spin w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full mb-3"></div>
              <p class="text-xs font-medium">Loading student submissions...</p>
            </div>

            <div v-else class="border border-slate-100 rounded-2xl overflow-x-auto min-w-0">
              <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 font-bold uppercase text-[10px]">
                  <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Student Name</th>
                    <th class="px-4 py-3">Student ID</th>
                    <th class="px-4 py-3 text-center">Score</th>
                    <th class="px-4 py-3 text-center">Percentage</th>
                    <th class="px-4 py-3 text-center">Grade</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-if="examStudentResults.length === 0">
                    <td colspan="8" class="p-8 text-center text-slate-400">
                      No student submissions found for this exam.
                    </td>
                  </tr>
                  <tr v-for="(stu, idx) in examStudentResults" :key="stu.id" class="hover:bg-slate-50/50">
                    <td class="px-4 py-3 text-slate-400 font-bold">{{ idx + 1 }}</td>
                    <td class="px-4 py-3">
                      <span class="block font-bold text-slate-900">{{ stu.name }}</span>
                      <span class="block text-[11px] text-slate-400">{{ stu.email }}</span>
                    </td>
                    <td class="px-4 py-3 font-mono text-slate-700 font-bold">{{ stu.student_id }}</td>
                    <td class="px-4 py-3 text-center font-bold text-slate-900">
                      {{ stu.score !== null ? `${stu.score} / ${stu.total_marks}` : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center font-black text-slate-900">
                      {{ stu.percentage !== null ? `${stu.percentage}%` : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span :class="[gradeBadge(stu.grade), 'px-2 py-0.5 rounded text-[11px] border']">
                        {{ stu.grade }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span :class="[statusBadge(stu.status), 'px-2 py-0.5 rounded-full text-[10px] font-bold border capitalize']">
                        {{ stu.status }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <button
                        v-if="stu.attempt_id"
                        @click="openAttemptDetails(stu.attempt_id)"
                        class="px-2.5 py-1 text-xs font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                      >
                        Inspect
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/70 shrink-0">
            <span class="text-xs text-slate-500 font-medium">
              Total Submissions: {{ examStudentResults.length }}
            </span>
            <div class="flex items-center gap-2.5">
              <button
                v-if="selectedExam?.status !== 'Published' && examStudentResults.length > 0"
                @click="promptPublishExam(selectedExam)"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm"
              >
                Publish All Results
              </button>
              <button
                @click="printSheet"
                class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition-colors"
              >
                Print Sheet
              </button>
              <button
                @click="showExamDetailModal = false"
                class="px-4 py-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors"
              >
                Close
              </button>
            </div>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL 3: PUBLISH RESULTS CONFIRMATION MODAL                          -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showPublishModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl space-y-5 animate-in zoom-in-95 duration-200">
          <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>

          <div class="text-center">
            <h3 class="text-base font-black text-slate-900">Publish Examination Results</h3>
            <p class="text-xs text-slate-500 mt-1">
              Are you sure you want to officially release and publish results for
              <strong class="text-slate-800">{{ examToPublish?.title }}</strong>?
            </p>
            <p class="text-[11px] text-amber-600 bg-amber-50 border border-amber-200 rounded-xl p-2.5 mt-3 text-left">
              <strong>Notice:</strong> Once published, scores and grades will become immediately visible to all participating students in their portals.
            </p>
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button
              @click="showPublishModal = false"
              :disabled="isPublishing"
              class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors"
            >
              Cancel
            </button>
            <button
              @click="confirmPublishExam"
              :disabled="isPublishing"
              class="flex-1 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-colors flex items-center justify-center gap-2 shadow-sm"
            >
              <svg v-if="isPublishing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
              <span>{{ isPublishing ? 'Publishing...' : 'Confirm Publication' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
@media print {
  /* Hide non-table UI when printing official sheet */
  button, nav, aside, header, .export-menu-container {
    display: none !important;
  }
  body {
    background: #ffffff !important;
    color: #000000 !important;
    font-size: 9pt;
  }
  table {
    width: 100% !important;
    border-collapse: collapse !important;
  }
  th, td {
    border: 1px solid #cbd5e1 !important;
    padding: 6px !important;
  }
  th {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
    font-weight: bold !important;
  }
}
</style>
