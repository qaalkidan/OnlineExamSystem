<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()

// ── View States ──
const currentView = ref<'list' | 'add' | 'detail'>('list')
const showAssignModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const showUnauthorizedModal = ref(false)

// ── Data & Loading ──
const isLoading = ref(true)
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

// ── Active Academic Term ──
const activeTerm = computed(() => {
  const y = settingsStore.academicYear || '2026'
  const s = settingsStore.semester || 'Second Semester'
  return `${y} • ${s}`
})

// ── Department & Stats Data ──
const department = ref({
  id: null as number | null,
  name: 'Department',
  code: 'DEPT',
  college: 'College of Computing and Informatics'
})

const stats = ref({
  total: 0,
  active: 0,
  inactive: 0,
  total_students: 0,
  total_exams: 0,
  unassigned_courses: 0,
})

const filterOptions = ref({
  year_levels: [] as string[],
  semesters: [] as string[],
  statuses: ['active', 'inactive'],
  created_by: [
    { value: 'all', label: 'All Creators' },
    { value: 'dept_head', label: 'Department Head' },
    { value: 'admin', label: 'Super Admin' },
  ],
  instructors: [] as any[],
})

// ── Search & Filter State ──
const search = ref('')
const searchDebounceTimer = ref<any>(null)
const createdByFilter = ref('all')
const yearLevelFilter = ref('all')
const semesterFilter = ref('all')
const statusFilter = ref('all')
const instructorFilter = ref('all')

// ── Sorting ──
const sortBy = ref('code')
const sortOrder = ref<'asc' | 'desc'>('asc')

// ── Pagination State ──
const currentPage = ref(1)
const perPage = ref(10)
const totalItems = ref(0)
const lastPage = ref(1)
const fromItem = ref(0)
const toItem = ref(0)

// ── Courses Dataset ──
const courses = ref<any[]>([])
const selectedCourse = ref<any>(null)
const courseDetail = ref<any>(null)
const isLoadingDetail = ref(false)

// ── Active 3-Dot Dropdown Row ──
const openDropdownId = ref<number | null>(null)

const toggleDropdown = (id: number, e: Event) => {
  e.stopPropagation()
  openDropdownId.value = openDropdownId.value === id ? null : id
}

// ── Export Menu State ──
const showExportMenu = ref(false)

// ── Form State (Add / Edit) ──
const courseForm = ref({
  id: null as number | null,
  code: '',
  title: '',
  credits: 3 as number | string,
  level: '',
  semester: '',
  section: '',
  status: 'active',
  instructor_id: '',
  co_instructor_id: '',
})

const formErrors = ref<Record<string, string>>({})
const formTouched = ref<Record<string, boolean>>({})

// ── Assign Instructor State ──
const courseToAssign = ref<any>(null)
const assignInstructorId = ref('')
const assignCoInstructorId = ref('')
const assignSection = ref('')

// ── Active Filters Count ──
const activeFilterCount = computed(() => {
  let count = 0
  if (search.value.trim()) count++
  if (createdByFilter.value !== 'all') count++
  if (yearLevelFilter.value !== 'all') count++
  if (semesterFilter.value !== 'all') count++
  if (statusFilter.value !== 'all') count++
  if (instructorFilter.value !== 'all') count++
  return count
})

const resetFilters = () => {
  search.value = ''
  createdByFilter.value = 'all'
  yearLevelFilter.value = 'all'
  semesterFilter.value = 'all'
  statusFilter.value = 'all'
  instructorFilter.value = 'all'
  currentPage.value = 1
  fetchCourses()
}

// ── Fetch Courses from Backend ──
const fetchCourses = async () => {
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
    if (createdByFilter.value !== 'all') params.created_by = createdByFilter.value
    if (yearLevelFilter.value !== 'all') params.level = yearLevelFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (instructorFilter.value !== 'all') params.instructor_id = instructorFilter.value

    const res = await apiClient.get('/dept-head/courses', { params })
    const resData = res.data

    courses.value = resData.data || []

    if (resData.stats) {
      stats.value = resData.stats
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
    console.error('Failed to load courses:', err)
    errorMessage.value = err?.response?.data?.message || 'Failed to load department courses. Please try again.'
  } finally {
    isLoading.value = false
  }
}

// Watch search input with debounce
const onSearchInput = () => {
  clearTimeout(searchDebounceTimer.value)
  searchDebounceTimer.value = setTimeout(() => {
    currentPage.value = 1
    fetchCourses()
  }, 350)
}

// Watch filters
watch([createdByFilter, yearLevelFilter, semesterFilter, statusFilter, instructorFilter], () => {
  currentPage.value = 1
  fetchCourses()
})

// ── Sorting ──
const setSort = (column: string) => {
  if (sortBy.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortOrder.value = 'asc'
  }
  fetchCourses()
}

// ── Pagination Navigation ──
const goToPage = (page: number) => {
  if (page >= 1 && page <= lastPage.value) {
    currentPage.value = page
    fetchCourses()
  }
}

const nextPage = () => {
  if (currentPage.value < lastPage.value) {
    currentPage.value++
    fetchCourses()
  }
}

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
    fetchCourses()
  }
}

const onPerPageChange = () => {
  currentPage.value = 1
  fetchCourses()
}

// ── View Course Detail ──
const openDetail = async (course: any) => {
  selectedCourse.value = course
  currentView.value = 'detail'
  isLoadingDetail.value = true
  courseDetail.value = null

  try {
    const res = await apiClient.get(`/dept-head/courses/${course.id}`)
    courseDetail.value = res.data?.data || course
  } catch (err: any) {
    console.error('Failed to load course details:', err)
    courseDetail.value = course
  } finally {
    isLoadingDetail.value = false
  }
}

const backToList = () => {
  currentView.value = 'list'
  selectedCourse.value = null
  courseDetail.value = null
}

// ── Form Validation ──
const validateField = (field: string) => {
  formTouched.value[field] = true

  if (field === 'code') {
    const val = (courseForm.value.code || '').trim()
    if (!val) {
      formErrors.value.code = 'Course code is required'
    } else if (val.length < 2) {
      formErrors.value.code = 'Course code must be at least 2 characters'
    } else if (val.length > 50) {
      formErrors.value.code = 'Course code cannot exceed 50 characters'
    } else {
      delete formErrors.value.code
    }
  } else if (field === 'title') {
    const val = (courseForm.value.title || '').trim()
    if (!val) {
      formErrors.value.title = 'Course title is required'
    } else if (val.length < 2) {
      formErrors.value.title = 'Course title must be at least 2 characters'
    } else if (val.length > 255) {
      formErrors.value.title = 'Course title cannot exceed 255 characters'
    } else {
      delete formErrors.value.title
    }
  } else if (field === 'level') {
    if (!courseForm.value.level) {
      formErrors.value.level = 'Academic year level is required'
    } else {
      delete formErrors.value.level
    }
  } else if (field === 'semester') {
    if (!courseForm.value.semester) {
      formErrors.value.semester = 'Semester is required'
    } else {
      delete formErrors.value.semester
    }
  } else if (field === 'credits') {
    const num = Number(courseForm.value.credits)
    if (!courseForm.value.credits) {
      formErrors.value.credits = 'Credit hours are required'
    } else if (isNaN(num) || !Number.isInteger(num) || num <= 0) {
      formErrors.value.credits = 'Credits must be a positive integer (e.g. 1 to 10)'
    } else if (num > 30) {
      formErrors.value.credits = 'Credits cannot exceed 30'
    } else {
      delete formErrors.value.credits
    }
  }
}

const validateForm = (): boolean => {
  validateField('code')
  validateField('title')
  validateField('level')
  validateField('semester')
  validateField('credits')
  return Object.keys(formErrors.value).length === 0
}

// ── Open Add Course ──
const openAddCourse = () => {
  courseForm.value = {
    id: null,
    code: '',
    title: '',
    credits: 3,
    level: filterOptions.value.year_levels[0] || '1st Year',
    semester: settingsStore.semester || 'Second Semester',
    section: '',
    status: 'active',
    instructor_id: '',
    co_instructor_id: '',
  }
  formErrors.value = {}
  formTouched.value = {}
  currentView.value = 'add'
}

// ── Save Add Course ──
const submitAddCourse = async () => {
  if (!validateForm()) return

  isSubmitting.value = true
  try {
    const payload = {
      code: courseForm.value.code.trim().toUpperCase(),
      title: courseForm.value.title.trim(),
      credits: parseInt(String(courseForm.value.credits)),
      level: courseForm.value.level,
      semester: courseForm.value.semester,
      section: courseForm.value.section || null,
      instructor_id: courseForm.value.instructor_id || null,
      co_instructor_id: courseForm.value.co_instructor_id || null,
    }

    await apiClient.post('/dept-head/courses', payload)
    showToast(`Course "${payload.title}" (${payload.code}) created successfully.`)
    currentView.value = 'list'
    await fetchCourses()
  } catch (err: any) {
    console.error('Failed to create course:', err)
    if (err.response?.data?.errors) {
      const beErrors = err.response.data.errors
      if (beErrors.code) formErrors.value.code = beErrors.code[0]
      if (beErrors.title) formErrors.value.title = beErrors.title[0]
      if (beErrors.level) formErrors.value.level = beErrors.level[0]
      if (beErrors.credits) formErrors.value.credits = beErrors.credits[0]
      if (beErrors.semester) formErrors.value.semester = beErrors.semester[0]
    }
    alert(err?.response?.data?.message || 'Failed to create course.')
  } finally {
    isSubmitting.value = false
  }
}

// ── Open Edit Course ──
const openEditCourse = (course: any) => {
  openDropdownId.value = null

  if (!course.can_edit) {
    selectedCourse.value = course
    showUnauthorizedModal.value = true
    return
  }

  selectedCourse.value = course
  courseForm.value = {
    id: course.id,
    code: course.code,
    title: course.title,
    credits: course.credits,
    level: course.level || '',
    semester: course.semester || '',
    section: course.section || '',
    status: course.status || 'active',
    instructor_id: course.instructor_id || '',
    co_instructor_id: course.co_instructor_id || '',
  }
  formErrors.value = {}
  formTouched.value = {}
  showEditModal.value = true
}

// ── Save Edit Course ──
const submitEditCourse = async () => {
  if (!selectedCourse.value || !validateForm()) return

  isSubmitting.value = true
  try {
    const payload = {
      code: courseForm.value.code.trim().toUpperCase(),
      title: courseForm.value.title.trim(),
      credits: parseInt(String(courseForm.value.credits)),
      level: courseForm.value.level,
      semester: courseForm.value.semester,
      section: courseForm.value.section || null,
      status: courseForm.value.status,
      instructor_id: courseForm.value.instructor_id || null,
      co_instructor_id: courseForm.value.co_instructor_id || null,
    }

    await apiClient.put(`/dept-head/courses/${selectedCourse.value.id}`, payload)
    showEditModal.value = false
    showToast(`Course "${payload.title}" updated successfully.`)
    await fetchCourses()
    if (currentView.value === 'detail' && selectedCourse.value?.id) {
      await openDetail(selectedCourse.value)
    }
  } catch (err: any) {
    console.error('Failed to update course:', err)
    if (err.response?.data?.errors) {
      const beErrors = err.response.data.errors
      if (beErrors.code) formErrors.value.code = beErrors.code[0]
      if (beErrors.title) formErrors.value.title = beErrors.title[0]
      if (beErrors.level) formErrors.value.level = beErrors.level[0]
      if (beErrors.credits) formErrors.value.credits = beErrors.credits[0]
    }
    alert(err?.response?.data?.message || 'Failed to update course.')
  } finally {
    isSubmitting.value = false
  }
}

// ── Assign Instructor Modal ──
const openAssignInstructor = (course: any) => {
  openDropdownId.value = null
  courseToAssign.value = course
  assignInstructorId.value = course.instructor_id || ''
  assignCoInstructorId.value = course.co_instructor_id || ''
  assignSection.value = course.section || ''
  showAssignModal.value = true
}

const submitAssignInstructor = async () => {
  if (!courseToAssign.value) return

  isSubmitting.value = true
  try {
    await apiClient.put(`/dept-head/courses/${courseToAssign.value.id}`, {
      instructor_id: assignInstructorId.value || null,
      co_instructor_id: assignCoInstructorId.value || null,
      section: assignSection.value || null,
    })
    showAssignModal.value = false
    showToast(`Faculty assigned to "${courseToAssign.value.title}" successfully.`)
    await fetchCourses()
    if (currentView.value === 'detail' && selectedCourse.value?.id === courseToAssign.value.id) {
      await openDetail(courseToAssign.value)
    }
  } catch (err: any) {
    console.error('Failed to assign instructor:', err)
    alert(err?.response?.data?.message || 'Failed to assign instructor.')
  } finally {
    isSubmitting.value = false
  }
}

// ── Delete Handling ──
const confirmDeleteCourse = (course: any) => {
  openDropdownId.value = null
  selectedCourse.value = course
  showDeleteModal.value = true
}

const executeDeleteCourse = async () => {
  if (!selectedCourse.value) return

  isSubmitting.value = true
  try {
    await apiClient.delete(`/dept-head/courses/${selectedCourse.value.id}`)
    showDeleteModal.value = false
    showToast(`Course "${selectedCourse.value.title}" deleted successfully.`)
    if (currentView.value === 'detail') {
      currentView.value = 'list'
    }
    await fetchCourses()
  } catch (err: any) {
    console.error('Failed to delete course:', err)
    alert(err?.response?.data?.message || 'Failed to delete course.')
  } finally {
    isSubmitting.value = false
  }
}

// ── Quick Toggle Status ──
const toggleCourseStatus = async (course: any) => {
  openDropdownId.value = null
  const newStatus = course.status === 'active' ? 'inactive' : 'active'
  try {
    await apiClient.put(`/dept-head/courses/${course.id}`, { status: newStatus })
    showToast(`Course status changed to ${newStatus}.`)
    await fetchCourses()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to update status.')
  }
}

// ── Export Handling ──
const handleExport = async (format: 'pdf' | 'excel' | 'csv') => {
  showExportMenu.value = false
  isExporting.value = true

  try {
    const params: Record<string, any> = { format }
    if (search.value.trim()) params.search = search.value.trim()
    if (createdByFilter.value !== 'all') params.created_by = createdByFilter.value
    if (yearLevelFilter.value !== 'all') params.level = yearLevelFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (instructorFilter.value !== 'all') params.instructor_id = instructorFilter.value

    const res = await apiClient.get('/dept-head/courses/export', { params })
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

    showToast(`Exported courses as ${format.toUpperCase()}.`)
  } catch (err: any) {
    console.error('Export error:', err)
    alert(err?.response?.data?.message || 'Failed to export courses. Please try again.')
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
  fetchCourses()
  settingsStore.fetchSettings()
  window.addEventListener('click', handleGlobalClick)
})

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
          <p class="text-sm font-bold">Failed to load courses</p>
          <p class="text-xs text-rose-600 mt-0.5">{{ errorMessage }}</p>
        </div>
      </div>
      <button @click="fetchCourses" class="text-xs font-bold text-rose-700 hover:underline shrink-0">Try again</button>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW 1: ADD COURSE                                                   -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <template v-if="currentView === 'add'">
      <!-- Breadcrumb & Top Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <button @click="backToList" class="text-[#5138ed] hover:underline flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
              Department Courses
            </button>
            <span>/</span>
            <span class="text-slate-600">Add New Course</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Create Curriculum Course</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            Add a course to Department of {{ department.name }} ({{ department.code }}).
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="backToList"
            class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm"
          >
            Cancel
          </button>
          <button
            @click="submitAddCourse"
            :disabled="isSubmitting"
            class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all disabled:opacity-50"
          >
            <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ isSubmitting ? 'Creating Course...' : 'Save & Publish Course' }}</span>
          </button>
        </div>
      </div>

      <!-- Course Creation Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-7 shadow-sm space-y-6">
        <div class="border-b border-slate-100 pb-4">
          <h2 class="text-base font-bold text-slate-900">Academic & Curriculum Information</h2>
          <p class="text-xs text-slate-500 mt-0.5">Define core course specifications, credit weighting, and cohort level.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <!-- Course Code -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">
              Course Code <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="courseForm.code"
              type="text"
              placeholder="e.g. CS-201 or BIO-101"
              @input="validateField('code')"
              @blur="validateField('code')"
              :class="formErrors.code ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]/20'"
              class="w-full uppercase px-3.5 py-2.5 text-xs sm:text-sm font-semibold border rounded-xl focus:outline-none focus:ring-2 transition-all placeholder:text-slate-400"
            />
            <p v-if="formErrors.code" class="text-[11px] text-rose-500 mt-1 font-medium">{{ formErrors.code }}</p>
          </div>

          <!-- Course Title -->
          <div class="sm:col-span-2">
            <label class="block text-xs font-bold text-slate-700 mb-1.5">
              Course Title <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="courseForm.title"
              type="text"
              placeholder="e.g. Data Structures & Algorithms"
              @input="validateField('title')"
              @blur="validateField('title')"
              :class="formErrors.title ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]/20'"
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm border rounded-xl focus:outline-none focus:ring-2 transition-all placeholder:text-slate-400"
            />
            <p v-if="formErrors.title" class="text-[11px] text-rose-500 mt-1 font-medium">{{ formErrors.title }}</p>
          </div>

          <!-- Department (Locked) -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">
              Department <span class="text-slate-400">(Locked)</span>
            </label>
            <input
              type="text"
              :value="department.name"
              disabled
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm bg-slate-50 border border-slate-200 rounded-xl text-slate-500 cursor-not-allowed select-none font-medium"
            />
          </div>

          <!-- Year Level -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">
              Academic Year Level <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="courseForm.level"
              @change="validateField('level')"
              :class="formErrors.level ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-[#5138ed]'"
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 bg-white"
            >
              <option value="">Select Year Level</option>
              <option v-for="lvl in filterOptions.year_levels" :key="lvl" :value="lvl">{{ lvl }}</option>
            </select>
            <p v-if="formErrors.level" class="text-[11px] text-rose-500 mt-1 font-medium">{{ formErrors.level }}</p>
          </div>

          <!-- Semester -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">
              Semester <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="courseForm.semester"
              @change="validateField('semester')"
              :class="formErrors.semester ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-[#5138ed]'"
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 bg-white"
            >
              <option value="">Select Semester</option>
              <option v-for="sem in filterOptions.semesters" :key="sem" :value="sem">{{ sem }}</option>
            </select>
            <p v-if="formErrors.semester" class="text-[11px] text-rose-500 mt-1 font-medium">{{ formErrors.semester }}</p>
          </div>

          <!-- Credits -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">
              Credit Hours (ECTS/Credits) <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="courseForm.credits"
              type="number"
              min="1"
              max="30"
              @input="validateField('credits')"
              @blur="validateField('credits')"
              :class="formErrors.credits ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 focus:border-[#5138ed]'"
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
            />
            <p v-if="formErrors.credits" class="text-[11px] text-rose-500 mt-1 font-medium">{{ formErrors.credits }}</p>
          </div>

          <!-- Section Assignment -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Section (Cohort Delivery)</label>
            <select
              v-model="courseForm.section"
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 bg-white"
            >
              <option value="">All Department Sections</option>
              <option value="Section A">Section A</option>
              <option value="Section B">Section B</option>
              <option value="Section C">Section C</option>
              <option value="Both Sections">Both Sections (A & B)</option>
            </select>
          </div>

          <!-- Primary Faculty Member -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Primary Instructor (Optional)</label>
            <select
              v-model="courseForm.instructor_id"
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 bg-white"
            >
              <option value="">Unassigned (Assign later)</option>
              <option v-for="inst in filterOptions.instructors" :key="inst.id" :value="inst.id">
                {{ inst.name }} ({{ inst.id_no || 'Faculty' }})
              </option>
            </select>
          </div>

          <!-- Co-Instructor -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Co-Instructor (Optional)</label>
            <select
              v-model="courseForm.co_instructor_id"
              class="w-full px-3.5 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 bg-white"
            >
              <option value="">None</option>
              <option
                v-for="inst in filterOptions.instructors.filter(i => String(i.id) !== String(courseForm.instructor_id))"
                :key="inst.id"
                :value="inst.id"
              >
                {{ inst.name }}
              </option>
            </select>
          </div>
        </div>
      </div>
    </template>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW 2: COURSE DETAILS                                               -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <template v-else-if="currentView === 'detail'">
      <!-- Breadcrumb & Top Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <button @click="backToList" class="text-[#5138ed] hover:underline flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
              Department Courses
            </button>
            <span>/</span>
            <span class="text-slate-600">{{ courseDetail?.code || selectedCourse?.code }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
              {{ courseDetail?.title || selectedCourse?.title }}
            </h1>
            <span class="px-2.5 py-0.5 text-xs font-black tracking-wider uppercase rounded-lg bg-indigo-50 text-[#5138ed] border border-indigo-200">
              {{ courseDetail?.code || selectedCourse?.code }}
            </span>
            <span
              :class="courseDetail?.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
              class="px-2.5 py-0.5 text-xs font-bold rounded-lg border capitalize"
            >
              {{ courseDetail?.status || selectedCourse?.status || 'Active' }}
            </span>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">
            Department of {{ department.name }} • {{ courseDetail?.level || selectedCourse?.level }} • {{ courseDetail?.semester || selectedCourse?.semester }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            @click="openAssignInstructor(courseDetail || selectedCourse)"
            class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-all"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
            <span>Assign Faculty</span>
          </button>
          <button
            v-if="courseDetail?.can_edit"
            @click="openEditCourse(courseDetail || selectedCourse)"
            class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            <span>Edit Course</span>
          </button>
          <button
            @click="backToList"
            class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm"
          >
            Back
          </button>
        </div>
      </div>

      <!-- Course Highlights Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Credit Weight</p>
          <p class="text-xl font-black text-slate-900 mt-1">{{ courseDetail?.credits || selectedCourse?.credits || 3 }} ECTS/Credits</p>
          <p class="text-[11px] text-slate-500 mt-0.5">Academic load</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Enrolled Cohort</p>
          <p class="text-xl font-black text-indigo-600 mt-1">{{ courseDetail?.students_count || selectedCourse?.students_count || 0 }} Students</p>
          <p class="text-[11px] text-slate-500 mt-0.5">Matching year level</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Scheduled Exams</p>
          <p class="text-xl font-black text-amber-600 mt-1">{{ courseDetail?.exams_list?.length ?? (selectedCourse?.exams || 0) }} Exams</p>
          <p class="text-[11px] text-slate-500 mt-0.5">Course assessments</p>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm">
          <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Created By</p>
          <p class="text-sm font-bold text-slate-800 mt-1 truncate">
            {{ courseDetail?.creator_name || (courseDetail?.is_admin_created ? 'Super Admin' : 'Department Head') }}
          </p>
          <span
            :class="courseDetail?.is_admin_created ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-indigo-50 text-[#5138ed] border-indigo-200'"
            class="inline-block mt-1 text-[10px] font-bold px-2 py-0.5 rounded border"
          >
            {{ courseDetail?.is_admin_created ? 'Admin Locked' : 'Dept Head Owned' }}
          </span>
        </div>
      </div>

      <!-- Main Detail Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1: Academic & Faculty Assignment Details -->
        <div class="lg:col-span-1 space-y-6">
          <!-- Faculty Card -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 class="text-sm font-bold text-slate-900">Teaching Faculty</h3>
              <button
                @click="openAssignInstructor(courseDetail || selectedCourse)"
                class="text-xs font-bold text-[#5138ed] hover:underline"
              >
                Change
              </button>
            </div>

            <div v-if="courseDetail?.instructor" class="flex items-start gap-3">
              <div
                :class="getAvatarColor(courseDetail.instructor.id)"
                class="w-12 h-12 rounded-xl flex items-center justify-center font-bold text-sm shrink-0"
              >
                <img
                  v-if="courseDetail.instructor.profile_picture_url"
                  :src="courseDetail.instructor.profile_picture_url"
                  :alt="courseDetail.instructor.name"
                  class="w-12 h-12 rounded-xl object-cover"
                />
                <span v-else>{{ getInitials(courseDetail.instructor.name) }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-black text-slate-900 truncate">{{ courseDetail.instructor.name }}</p>
                <p class="text-xs text-slate-500 truncate">{{ courseDetail.instructor.email }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Faculty ID: {{ courseDetail.instructor.id_no || 'N/A' }}</p>
                <div class="mt-2 flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Primary Instructor
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-6 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
              <p class="text-xs font-bold text-slate-600">No Primary Instructor Assigned</p>
              <p class="text-[11px] text-slate-400 mt-1">Assign faculty to manage exams and grades for this course.</p>
              <button
                @click="openAssignInstructor(courseDetail || selectedCourse)"
                class="mt-3 px-3 py-1.5 text-xs font-bold text-[#5138ed] bg-white border border-[#5138ed]/30 rounded-lg shadow-sm hover:bg-indigo-50"
              >
                Assign Faculty
              </button>
            </div>

            <!-- Co-Instructor if any -->
            <div v-if="courseDetail?.co_instructor" class="border-t border-slate-100 pt-3">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-2">Co-Instructor</p>
              <div class="flex items-center gap-2.5">
                <div
                  :class="getAvatarColor(courseDetail.co_instructor.id)"
                  class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs shrink-0"
                >
                  {{ getInitials(courseDetail.co_instructor.name) }}
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-800 truncate">{{ courseDetail.co_instructor.name }}</p>
                  <p class="text-[11px] text-slate-400 truncate">{{ courseDetail.co_instructor.email }}</p>
                </div>
              </div>
            </div>

            <!-- Instructor Workload Stats -->
            <div v-if="courseDetail?.instructor_workload" class="border-t border-slate-100 pt-3">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-2">Instructor Workload</p>
              <div class="grid grid-cols-2 gap-2 text-center">
                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                  <span class="block text-sm font-black text-slate-800">{{ courseDetail.instructor_workload.courses_count }}</span>
                  <span class="text-[10px] text-slate-500">Dept Courses</span>
                </div>
                <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                  <span class="block text-sm font-black text-slate-800">{{ courseDetail.instructor_workload.total_credits }}</span>
                  <span class="text-[10px] text-slate-500">Credit Hours</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Academic Specifications -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-3">
            <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Curriculum Metadata</h3>
            <div class="space-y-2.5 text-xs">
              <div class="flex justify-between">
                <span class="text-slate-400">Department:</span>
                <span class="font-bold text-slate-800">{{ department.name }} ({{ department.code }})</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">College:</span>
                <span class="font-bold text-slate-800">{{ department.college }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Year Level:</span>
                <span class="font-bold text-slate-800">{{ courseDetail?.level || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Semester:</span>
                <span class="font-bold text-slate-800">{{ courseDetail?.semester || '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Assigned Section:</span>
                <span class="font-bold text-slate-800">{{ courseDetail?.section || 'All Sections' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Date Added:</span>
                <span class="font-bold text-slate-800">{{ courseDetail?.created_at_human || '—' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Column 2 & 3: Associated Exams & Enrolled Cohort Students -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Associated Exams Table -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 class="text-sm font-bold text-slate-900">Associated Examinations</h3>
                <p class="text-xs text-slate-500">Exams published or scheduled under course code {{ courseDetail?.code }}.</p>
              </div>
              <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                {{ courseDetail?.exams_list?.length || 0 }} Exams
              </span>
            </div>

            <div v-if="courseDetail?.exams_list && courseDetail.exams_list.length > 0" class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <th class="pb-2.5">Exam Title</th>
                    <th class="pb-2.5">Schedule</th>
                    <th class="pb-2.5">Duration</th>
                    <th class="pb-2.5">Marks</th>
                    <th class="pb-2.5">Questions</th>
                    <th class="pb-2.5">Submissions</th>
                    <th class="pb-2.5">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="exam in courseDetail.exams_list" :key="exam.id" class="hover:bg-slate-50/60">
                    <td class="py-3 font-bold text-slate-800">{{ exam.title }}</td>
                    <td class="py-3 text-slate-600">{{ exam.scheduled_at }}</td>
                    <td class="py-3 text-slate-600">{{ exam.duration_minutes }} min</td>
                    <td class="py-3 font-semibold text-slate-700">{{ exam.total_marks }} pts</td>
                    <td class="py-3 text-slate-600">{{ exam.questions_count }} Qs</td>
                    <td class="py-3 text-slate-600">{{ exam.attempts_count }}</td>
                    <td class="py-3">
                      <span
                        :class="exam.status === 'published' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                        class="px-2 py-0.5 rounded text-[10px] font-bold border capitalize"
                      >
                        {{ exam.status }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="text-center py-8 text-slate-400 border border-dashed border-slate-100 rounded-xl">
              <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              <p class="text-xs font-semibold text-slate-600">No exams scheduled for this course code yet.</p>
              <p class="text-[11px] text-slate-400 mt-0.5">Faculty members will create and publish exams linked to this course.</p>
            </div>
          </div>

          <!-- Enrolled Cohort Students Preview -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 class="text-sm font-bold text-slate-900">Enrolled Cohort Students</h3>
                <p class="text-xs text-slate-500">Department students registered in {{ courseDetail?.level || selectedCourse?.level }}.</p>
              </div>
              <span class="px-2 py-0.5 rounded text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                {{ courseDetail?.students_count || 0 }} Registered
              </span>
            </div>

            <div v-if="courseDetail?.enrolled_students && courseDetail.enrolled_students.length > 0" class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <th class="pb-2.5">Student Name</th>
                    <th class="pb-2.5">ID No</th>
                    <th class="pb-2.5">Email</th>
                    <th class="pb-2.5">Section</th>
                    <th class="pb-2.5">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="student in courseDetail.enrolled_students" :key="student.id" class="hover:bg-slate-50/60">
                    <td class="py-2.5 font-bold text-slate-800">{{ student.name }}</td>
                    <td class="py-2.5 font-mono text-slate-600">{{ student.id_no || '—' }}</td>
                    <td class="py-2.5 text-slate-500">{{ student.email }}</td>
                    <td class="py-2.5 text-slate-700">{{ student.section || 'General' }}</td>
                    <td class="py-2.5">
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 capitalize">
                        {{ student.status || 'Active' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="text-center py-8 text-slate-400 border border-dashed border-slate-100 rounded-xl">
              <p class="text-xs font-semibold text-slate-600">No students currently enrolled in this year level.</p>
              <p class="text-[11px] text-slate-400 mt-0.5">Students registered in {{ courseDetail?.level || 'this level' }} will appear here.</p>
            </div>
          </div>

        </div>
      </div>
    </template>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW 3: COURSES LIST (DEFAULT)                                       -->
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
            Department Course Management Center
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            Administer curriculum courses, monitor enrollment, and assign faculty for Wollo University.
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
              <span>{{ isExporting ? 'Exporting...' : 'Export Courses' }}</span>
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

          <!-- Add Course Primary Button -->
          <button
            @click="openAddCourse"
            class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all cursor-pointer whitespace-nowrap"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>Add Course</span>
          </button>
        </div>
      </div>

      <!-- Real Department KPI Cards (Zero Fake Trends) -->
      <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Total Courses -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Courses</p>
            <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ stats.total }}</p>
            <p class="text-[11px] text-slate-500 font-medium truncate mt-0.5">Department catalog</p>
          </div>
        </div>

        <!-- Active Courses -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Active Courses</p>
            <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ stats.active }}</p>
            <p class="text-[11px] text-emerald-600 font-semibold truncate mt-0.5">{{ stats.inactive }} inactive/archived</p>
          </div>
        </div>

        <!-- Enrolled Cohort Students -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Students</p>
            <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ stats.total_students.toLocaleString() }}</p>
            <p class="text-[11px] text-slate-500 font-medium truncate mt-0.5">Enrolled in department</p>
          </div>
        </div>

        <!-- Associated Exams -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Exams</p>
            <p class="text-xl sm:text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ stats.total_exams }}</p>
            <p class="text-[11px] text-purple-600 font-semibold truncate mt-0.5">{{ stats.unassigned_courses }} unassigned courses</p>
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
              placeholder="Search courses by title, code, or instructor..."
              class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all bg-white placeholder:text-slate-400"
            />
            <button
              v-if="search"
              @click="search = ''; fetchCourses()"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs"
            >
              ✕
            </button>
          </div>

          <!-- Filters Row -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <!-- Created By Filter -->
            <select
              v-model="createdByFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">Created By (All)</option>
              <option value="dept_head">Department Head</option>
              <option value="admin">Super Admin</option>
            </select>

            <!-- Year Level Filter -->
            <select
              v-model="yearLevelFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">All Year Levels</option>
              <option v-for="lvl in filterOptions.year_levels" :key="lvl" :value="lvl">{{ lvl }}</option>
            </select>

            <!-- Semester Filter -->
            <select
              v-model="semesterFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">All Semesters</option>
              <option v-for="sem in filterOptions.semesters" :key="sem" :value="sem">{{ sem }}</option>
            </select>

            <!-- Status Filter -->
            <select
              v-model="statusFilter"
              class="text-xs font-semibold px-3 py-2.5 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:border-[#5138ed]"
            >
              <option value="all">All Status</option>
              <option value="active">Active Only</option>
              <option value="inactive">Inactive Only</option>
            </select>
          </div>
        </div>

        <!-- Active Filter Indicator / Reset -->
        <div v-if="activeFilterCount > 0" class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
          <div class="flex items-center gap-2 text-slate-500 font-medium">
            <span>Filtered by {{ activeFilterCount }} condition(s)</span>
          </div>
          <button
            @click="resetFilters"
            class="text-[#5138ed] font-bold hover:underline cursor-pointer"
          >
            Clear all filters
          </button>
        </div>
      </div>

      <!-- Desktop Courses Table & Mobile Cards -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">

        <!-- Loading State -->
        <div v-if="isLoading" class="p-12 text-center text-slate-400">
          <svg class="w-8 h-8 animate-spin mx-auto text-[#5138ed] mb-3" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <p class="text-xs font-bold text-slate-600">Loading department courses...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="courses.length === 0" class="p-12 text-center text-slate-400">
          <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <p class="text-sm font-bold text-slate-700">No courses found</p>
          <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
            {{ search || activeFilterCount > 0 ? 'No courses match the active search or filters. Try adjusting your parameters.' : 'Your department does not have any registered courses yet. Click "Add Course" above to create one.' }}
          </p>
          <button
            v-if="activeFilterCount > 0"
            @click="resetFilters"
            class="mt-4 px-4 py-2 text-xs font-bold text-[#5138ed] bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition-colors"
          >
            Reset Filters
          </button>
        </div>

        <!-- Table View (Desktop) -->
        <div v-else class="hidden lg:block overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                <th @click="setSort('code')" class="py-3.5 px-4 cursor-pointer hover:text-slate-800">
                  <div class="flex items-center gap-1.5">
                    <span>Course Code & Title</span>
                    <span v-if="sortBy === 'code'" class="text-[#5138ed]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th @click="setSort('level')" class="py-3.5 px-4 cursor-pointer hover:text-slate-800">
                  <span>Year Level & Semester</span>
                </th>
                <th @click="setSort('credits')" class="py-3.5 px-4 cursor-pointer hover:text-slate-800">
                  <span>Credits</span>
                </th>
                <th class="py-3.5 px-4">Primary Faculty</th>
                <th class="py-3.5 px-4">Enrolled Students</th>
                <th class="py-3.5 px-4">Exams</th>
                <th class="py-3.5 px-4">Created By</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="course in courses"
                :key="course.id"
                class="hover:bg-slate-50/80 transition-colors group"
              >
                <!-- Course Code & Title -->
                <td class="py-3.5 px-4">
                  <div class="flex items-start gap-2.5">
                    <span class="px-2 py-0.5 text-[11px] font-black font-mono tracking-wider uppercase rounded-md bg-indigo-50 text-[#5138ed] border border-indigo-200 shrink-0">
                      {{ course.code }}
                    </span>
                    <div class="min-w-0">
                      <p class="font-bold text-slate-900 group-hover:text-[#5138ed] transition-colors leading-snug">
                        {{ course.title }}
                      </p>
                      <p class="text-[11px] text-slate-400 mt-0.5">
                        {{ course.section ? course.section : 'All Sections' }}
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Year Level & Semester -->
                <td class="py-3.5 px-4">
                  <p class="font-bold text-slate-800">{{ course.level || '—' }}</p>
                  <p class="text-[11px] text-slate-500">{{ course.semester || '—' }}</p>
                </td>

                <!-- Credits -->
                <td class="py-3.5 px-4">
                  <span class="px-2 py-0.5 text-xs font-bold rounded bg-slate-100 text-slate-700">
                    {{ course.credits }} Credits
                  </span>
                </td>

                <!-- Primary Faculty -->
                <td class="py-3.5 px-4">
                  <div v-if="course.instructor" class="flex items-center gap-2">
                    <div
                      :class="getAvatarColor(course.instructor.id)"
                      class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-[10px] shrink-0"
                    >
                      <img
                        v-if="course.instructor.profile_picture_url"
                        :src="course.instructor.profile_picture_url"
                        :alt="course.instructor.name"
                        class="w-7 h-7 rounded-lg object-cover"
                      />
                      <span v-else>{{ getInitials(course.instructor.name) }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="font-bold text-slate-800 truncate leading-tight">{{ course.instructor.name }}</p>
                      <p class="text-[10px] text-slate-400 truncate">{{ course.instructor.email }}</p>
                    </div>
                  </div>
                  <div v-else class="flex items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                      Unassigned
                    </span>
                    <button
                      @click="openAssignInstructor(course)"
                      class="text-[11px] font-bold text-[#5138ed] hover:underline"
                    >
                      Assign
                    </button>
                  </div>
                </td>

                <!-- Enrolled Students -->
                <td class="py-3.5 px-4">
                  <span class="font-bold text-slate-800">{{ course.students_count || 0 }}</span>
                  <span class="text-slate-400 text-[11px] ml-1">students</span>
                </td>

                <!-- Exams -->
                <td class="py-3.5 px-4">
                  <span
                    :class="(course.exams_count ?? 0) > 0 ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-slate-50 text-slate-400 border-slate-200'"
                    class="px-2 py-0.5 rounded text-[11px] font-bold border"
                  >
                    {{ course.exams_count ?? 0 }} Exams
                  </span>
                </td>

                <!-- Created By -->
                <td class="py-3.5 px-4">
                  <span
                    :class="course.is_admin_created ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-teal-50 text-teal-700 border-teal-200'"
                    class="px-2 py-0.5 rounded text-[10px] font-bold border"
                  >
                    {{ course.is_admin_created ? 'Super Admin' : 'Dept Head' }}
                  </span>
                </td>

                <!-- Status -->
                <td class="py-3.5 px-4">
                  <span
                    :class="course.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
                  >
                    {{ course.status }}
                  </span>
                </td>

                <!-- Actions Menu -->
                <td class="py-3.5 px-4 text-right">
                  <div class="relative inline-block text-left dropdown-container">
                    <button
                      @click="toggleDropdown(course.id, $event)"
                      class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                      </svg>
                    </button>

                    <!-- Dropdown Content -->
                    <div
                      v-if="openDropdownId === course.id"
                      class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-40 text-xs font-semibold animate-in fade-in zoom-in-95 duration-100"
                    >
                      <button
                        @click="openDetail(course)"
                        class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700"
                      >
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.264 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        <span>View Details</span>
                      </button>

                      <button
                        @click="openAssignInstructor(course)"
                        class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-emerald-700"
                      >
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                        <span>Assign Faculty</span>
                      </button>

                      <button
                        @click="openEditCourse(course)"
                        class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700"
                      >
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        <span>Edit Course</span>
                      </button>

                      <button
                        @click="toggleCourseStatus(course)"
                        class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700"
                      >
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                        <span>Toggle Status</span>
                      </button>

                      <div v-if="course.can_delete" class="border-t border-slate-100 my-1"></div>

                      <button
                        v-if="course.can_delete"
                        @click="confirmDeleteCourse(course)"
                        class="w-full px-3.5 py-2 text-left hover:bg-rose-50 flex items-center gap-2 text-rose-600 font-bold"
                      >
                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        <span>Delete Course</span>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards View (< lg screens) -->
        <div v-if="courses.length > 0" class="block lg:hidden divide-y divide-slate-100">
          <div
            v-for="course in courses"
            :key="'mobile-' + course.id"
            class="p-4 space-y-3"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="px-2 py-0.5 text-xs font-black font-mono uppercase rounded bg-indigo-50 text-[#5138ed] border border-indigo-200">
                    {{ course.code }}
                  </span>
                  <span
                    :class="course.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                    class="px-2 py-0.5 rounded text-[10px] font-bold border capitalize"
                  >
                    {{ course.status }}
                  </span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm leading-snug">{{ course.title }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ course.level }} • {{ course.semester }}</p>
              </div>

              <!-- Quick Action Dropdown -->
              <div class="relative dropdown-container">
                <button
                  @click="toggleDropdown(course.id, $event)"
                  class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                </button>
                <div
                  v-if="openDropdownId === course.id"
                  class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-40 text-xs font-semibold"
                >
                  <button @click="openDetail(course)" class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700">View Details</button>
                  <button @click="openAssignInstructor(course)" class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-emerald-700">Assign Faculty</button>
                  <button @click="openEditCourse(course)" class="w-full px-3.5 py-2 text-left hover:bg-slate-50 flex items-center gap-2 text-slate-700">Edit Course</button>
                  <button v-if="course.can_delete" @click="confirmDeleteCourse(course)" class="w-full px-3.5 py-2 text-left hover:bg-rose-50 flex items-center gap-2 text-rose-600 font-bold">Delete Course</button>
                </div>
              </div>
            </div>

            <!-- Mobile Card Meta Row -->
            <div class="grid grid-cols-2 gap-2 text-xs text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Instructor</span>
                <span class="font-bold text-slate-800 truncate block">
                  {{ course.instructor ? course.instructor.name : 'Unassigned' }}
                </span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Credits</span>
                <span class="font-bold text-slate-800">{{ course.credits }} Credits</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Students</span>
                <span class="font-bold text-slate-800">{{ course.students_count || 0 }} registered</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Exams</span>
                <span class="font-bold text-slate-800">{{ course.exams_count || 0 }} exams</span>
              </div>
            </div>

            <!-- Card Bottom Action -->
            <div class="flex items-center justify-between pt-1">
              <button
                @click="openAssignInstructor(course)"
                class="text-xs font-bold text-emerald-700 hover:underline flex items-center gap-1"
              >
                <span>{{ course.instructor ? 'Change Faculty' : 'Assign Faculty' }}</span>
                <span>→</span>
              </button>
              <button
                @click="openDetail(course)"
                class="text-xs font-bold text-[#5138ed] hover:underline"
              >
                View Full Details →
              </button>
            </div>
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
            <span>courses</span>

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
    <!-- MODAL: ASSIGN INSTRUCTOR                                             -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showAssignModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-lg w-full shadow-2xl space-y-5 animate-in zoom-in-95 duration-200">
          <div class="flex items-start justify-between border-b border-slate-100 pb-3">
            <div>
              <span class="px-2 py-0.5 text-[10px] font-black uppercase rounded bg-indigo-50 text-[#5138ed] border border-indigo-200 font-mono">
                {{ courseToAssign?.code }}
              </span>
              <h2 class="text-base font-bold text-slate-900 mt-1">Assign Teaching Faculty</h2>
              <p class="text-xs text-slate-500 mt-0.5">{{ courseToAssign?.title }}</p>
            </div>
            <button @click="showAssignModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
          </div>

          <div class="space-y-4">
            <!-- Primary Instructor -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Primary Instructor</label>
              <select
                v-model="assignInstructorId"
                class="w-full px-3.5 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="">Unassigned (None)</option>
                <option
                  v-for="inst in filterOptions.instructors"
                  :key="inst.id"
                  :value="inst.id"
                >
                  {{ inst.name }} — {{ inst.id_no || 'Faculty' }} ({{ inst.email }})
                </option>
              </select>
            </div>

            <!-- Co-Instructor -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Co-Instructor (Optional)</label>
              <select
                v-model="assignCoInstructorId"
                class="w-full px-3.5 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="">None</option>
                <option
                  v-for="inst in filterOptions.instructors.filter(i => String(i.id) !== String(assignInstructorId))"
                  :key="inst.id"
                  :value="inst.id"
                >
                  {{ inst.name }} ({{ inst.email }})
                </option>
              </select>
            </div>

            <!-- Section -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Assigned Section / Delivery Group</label>
              <select
                v-model="assignSection"
                class="w-full px-3.5 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="">All Department Sections</option>
                <option value="Section A">Section A</option>
                <option value="Section B">Section B</option>
                <option value="Section C">Section C</option>
                <option value="Both Sections">Both Sections (A & B)</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
            <button
              @click="showAssignModal = false"
              class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              @click="submitAssignInstructor"
              :disabled="isSubmitting"
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
    <!-- MODAL: EDIT COURSE                                                   -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showEditModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-xl w-full shadow-2xl space-y-5 animate-in zoom-in-95 duration-200 max-h-[90vh] overflow-y-auto">
          <div class="flex items-start justify-between border-b border-slate-100 pb-3">
            <div>
              <span class="px-2 py-0.5 text-[10px] font-black uppercase rounded bg-indigo-50 text-[#5138ed] border border-indigo-200 font-mono">
                {{ selectedCourse?.code }}
              </span>
              <h2 class="text-base font-bold text-slate-900 mt-1">Edit Course Specifications</h2>
              <p class="text-xs text-slate-500 mt-0.5">Department Head course modification</p>
            </div>
            <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Course Code -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Course Code *</label>
              <input
                v-model="courseForm.code"
                type="text"
                @input="validateField('code')"
                class="w-full uppercase px-3 py-2 text-xs sm:text-sm font-semibold border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
              <p v-if="formErrors.code" class="text-[11px] text-rose-500 mt-1">{{ formErrors.code }}</p>
            </div>

            <!-- Course Title -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Course Title *</label>
              <input
                v-model="courseForm.title"
                type="text"
                @input="validateField('title')"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
              <p v-if="formErrors.title" class="text-[11px] text-rose-500 mt-1">{{ formErrors.title }}</p>
            </div>

            <!-- Year Level -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Year Level *</label>
              <select
                v-model="courseForm.level"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option v-for="lvl in filterOptions.year_levels" :key="lvl" :value="lvl">{{ lvl }}</option>
              </select>
            </div>

            <!-- Semester -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Semester *</label>
              <select
                v-model="courseForm.semester"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option v-for="sem in filterOptions.semesters" :key="sem" :value="sem">{{ sem }}</option>
              </select>
            </div>

            <!-- Credits -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Credit Hours *</label>
              <input
                v-model="courseForm.credits"
                type="number"
                min="1"
                max="30"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed]"
              />
            </div>

            <!-- Status -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Status</label>
              <select
                v-model="courseForm.status"
                class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
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
              @click="submitEditCourse"
              :disabled="isSubmitting"
              class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm transition-all disabled:opacity-50"
            >
              <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSubmitting ? 'Saving...' : 'Update Course' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: UNAUTHORIZED / ADMIN CREATED NOTICE                          -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showUnauthorizedModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl text-center space-y-4 animate-in zoom-in-95 duration-200">
          <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center mx-auto">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">Protected Institutional Course</h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
              "{{ selectedCourse?.title }}" ({{ selectedCourse?.code }}) was created by <strong class="text-slate-800">Super Admin</strong>.
              Core course parameters (Title, Code, Credits, Level) are locked at the institutional level.
            </p>
            <p class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-xl p-3 mt-3 font-semibold">
              You are authorized to assign or reassign teaching faculty and delivery sections to this course.
            </p>
          </div>
          <div class="flex items-center justify-center gap-2 pt-2">
            <button
              @click="showUnauthorizedModal = false"
              class="px-4 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50"
            >
              Close
            </button>
            <button
              @click="showUnauthorizedModal = false; openAssignInstructor(selectedCourse)"
              class="px-4 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm"
            >
              Assign Faculty Now
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: DELETE COURSE CONFIRMATION                                    -->
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
            <h3 class="text-base font-bold text-slate-900">Delete Course</h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
              Are you sure you want to permanently delete
              <strong class="text-slate-800">"{{ selectedCourse?.title }}" ({{ selectedCourse?.code }})</strong>?
            </p>
            <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-2.5 mt-2 font-medium">
              Note: Courses with associated examinations or student attempts cannot be deleted to protect academic records.
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
              @click="executeDeleteCourse"
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
