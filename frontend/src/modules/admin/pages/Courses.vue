<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useSettingsStore } from '../../../store/settingsStore'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Doughnut } from 'vue-chartjs'

ChartJS.register(ArcElement, Tooltip, Legend)

const router = useRouter()
const settingsStore = useSettingsStore()

// ── Search & Filter State ─────────────────────────────────────────────────────
const search = ref('')
const debouncedSearch = ref('')
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

const handleSearchInput = () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    debouncedSearch.value = search.value.trim().toLowerCase()
  }, 250)
}

const clearSearch = () => {
  search.value = ''
  debouncedSearch.value = ''
}

const deptFilter = ref('all')
const statusFilter = ref('all')
const levelFilter = ref('all')
const semesterFilter = ref('all')
const assignmentFilter = ref<'all' | 'assigned' | 'unassigned'>('all')

const academicYearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year']
const semesterOptions = ['First Semester', 'Second Semester', 'Summer Semester']
const sectionOptions = ['Section A', 'Section B', 'Both Sections']

// ── Sorting State ─────────────────────────────────────────────────────────────
type SortField = 'code' | 'title' | 'department' | 'level' | 'credits' | 'status' | 'instructors' | 'created'
const sortField = ref<SortField>('code')
const sortDirection = ref<'asc' | 'desc'>('asc')

const handleSort = (field: SortField) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

// ── Column Visibility Management ──────────────────────────────────────────────
const showColumnDropdown = ref(false)
const visibleColumns = ref({
  department: true,
  level: true,
  instructors: true,
  credits: true,
  semester: false,
  status: true,
  created_by: true,
})

// ── Pagination ────────────────────────────────────────────────────────────────
const currentPage = ref(1)
const perPage = ref(10)

// ── View Management ───────────────────────────────────────────────────────────
const showAddPage = ref(false)
const showDetailsPage = ref(false)
const isEditing = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const showAssignModal = ref(false)
const selectedCourse = ref<any>(null)
const courseToAssign = ref<any>(null)
const assignSection = ref('')
const assignInstructorId = ref('')
const assignCoInstructorId = ref('')
const isLoading = ref(true)
const isRefreshing = ref(false)
const lastRefreshedAt = ref<Date>(new Date())

// ── Raw Data ──────────────────────────────────────────────────────────────────
const allCourses = ref<any[]>([])
const allDepartments = ref<any[]>([])
const allInstructors = ref<any[]>([])
const backendStats = ref<{
  total: number
  active: number
  inactive: number
  this_semester: number
  assigned: number
  unassigned: number
  total_credits: number
  new_courses: number
  growth?: { rate: number; formatted: string; trend: 'up' | 'down' } | null
} | null>(null)

// ── Course Form State ─────────────────────────────────────────────────────────
const newCourseForm = ref({
  code: '',
  title: '',
  type: '',
  department_id: '',
  program: '',
  level: '1st Year',
  semester: 'Second Semester',
  credits: '3',
  language: 'English',
  short_description: '',
  full_description: '',
  instructor_id: '',
  co_instructors: '',
  capacity: '',
  enrollment_status: 'Open for Enrollment',
  visibility: 'Visible to Students',
  start_date: '',
  end_date: '',
  status: 'active',
})

const courseFormErrors = ref<Record<string, string>>({})
const courseFormTouched = ref<Record<string, boolean>>({})

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatDate = (dateStr: string | null | undefined) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const formatDateTime = (dateStr: string | null | undefined) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getCourseInstructors = (course: any) => {
  if (!course) return []
  const map = new Map<number | string, any>()

  const addInstructor = (user: any, fallbackSection?: string) => {
    if (!user || !user.id) return
    const id = user.id
    const existing = map.get(id)
    const section = user.section || fallbackSection || existing?.section || 'Section A'
    map.set(id, {
      id: user.id,
      name: user.name || 'Unknown Instructor',
      email: user.email || '',
      role: user.role || 'instructor',
      section: section,
      avatar: user.name ? user.name.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2) : 'IN',
      profilePicture: user.profile_picture_url || null,
    })
  }

  const fromRelation = course.assigned_instructors || course.assignedInstructors || []
  if (Array.isArray(fromRelation)) {
    fromRelation.forEach((u: any) => addInstructor(u, u.section || 'Section A'))
  }

  if (course.instructor && typeof course.instructor === 'object' && course.instructor.id) {
    const defaultSec = course.section === 'Section B' ? 'Section B' : 'Section A'
    addInstructor(course.instructor, course.instructor.section || defaultSec)
  } else if (course.instructor_id) {
    const inst = allInstructors.value.find(i => i.id === course.instructor_id)
    if (inst) addInstructor(inst, inst.section || 'Section A')
  }

  const co = course.co_instructor || course.coInstructor
  if (co && typeof co === 'object' && co.id) {
    addInstructor(co, co.section || 'Section B')
  } else if (course.co_instructor_id) {
    const coInst = allInstructors.value.find(i => i.id === course.co_instructor_id)
    if (coInst) addInstructor(coInst, coInst.section || 'Section B')
  }

  if (course.code && allInstructors.value?.length) {
    const cCode = String(course.code).trim().toUpperCase()
    allInstructors.value.forEach((inst: any) => {
      if (inst.course_code && String(inst.course_code).trim().toUpperCase() === cCode) {
        addInstructor(inst, inst.section || 'Section A')
      }
    })
  }

  const list = Array.from(map.values())
  if (list.length === 2 && list[0].section === list[1].section) {
    list[1].section = 'Section B'
  }

  list.sort((a, b) => (a.section || '').localeCompare(b.section || '') || (a.name || '').localeCompare(b.name || ''))
  return list
}

const selectedCourseInstructors = computed(() => {
  return getCourseInstructors(selectedCourse.value)
})

const currentInstructorInfo = computed(() => {
  if (!courseToAssign.value?.instructor) return null
  const name = courseToAssign.value.instructor?.name || (typeof courseToAssign.value.instructor === 'string' ? courseToAssign.value.instructor : null)
  if (!name || name === 'Unassigned') return null
  return `${name} is currently assigned (Section A)`
})

const currentCoInstructorInfo = computed(() => {
  const coId = courseToAssign.value?.co_instructor_id || courseToAssign.value?.coInstructor?.id || courseToAssign.value?.co_instructor?.id
  if (!coId) return null
  const coInst = allInstructors.value.find(i => i.id === coId) || courseToAssign.value?.coInstructor || courseToAssign.value?.co_instructor
  if (!coInst) return null
  return `${coInst.name} is currently assigned (Section B)`
})

// ── Fetch Data ────────────────────────────────────────────────────────────────
const fetchCourses = async (silent = false) => {
  if (!silent) isLoading.value = true
  try {
    const res = await apiClient.get('/admin/courses')
    backendStats.value = res.data.stats || null
    allCourses.value = (res.data.data || []).map((c: any) => {
      const instructors = getCourseInstructors(c)
      const primaryInstructor = instructors.length > 0 ? instructors[0].name : (c.instructor?.name || 'Unassigned')
      return {
        ...c,
        name: c.title,
        title: c.title,
        code: c.code,
        dept: c.department?.name || 'Unassigned',
        departmentName: c.department?.name || 'Unassigned',
        department_id: c.department_id,
        instructor: primaryInstructor,
        instructorsCount: instructors.length,
        status: c.status || 'active',
        semester: c.semester || settingsStore.semester || 'Second Semester',
        credits: c.credits || 3,
        level: c.level || '1st Year',
        section: c.section || 'Both Sections',
        created_by: c.creator?.role === 'dept_head' || c.creator?.role === 'department_head' ? 'Dept. Head' : (c.creator?.role === 'admin' ? 'Admin' : (c.creator?.name || 'Super Admin')),
        created_on: c.created_at ? formatDate(c.created_at) : '—',
      }
    })
    lastRefreshedAt.value = new Date()
  } catch (err) {
    console.error('Failed to fetch courses:', err)
    showToast('Failed to load courses from the database.', 'error')
  } finally {
    isLoading.value = false
    isRefreshing.value = false
  }
}

const fetchDepartments = async () => {
  try {
    const res = await apiClient.get('/admin/departments')
    allDepartments.value = res.data.data || []
  } catch (err) {
    console.error('Failed to fetch departments:', err)
  }
}

const fetchInstructors = async () => {
  try {
    const res = await apiClient.get('/admin/instructors')
    allInstructors.value = res.data.data || []
  } catch (err) {
    console.error('Failed to fetch instructors:', err)
  }
}

const refreshData = async () => {
  isRefreshing.value = true
  await Promise.all([fetchDepartments(), fetchInstructors()])
  await fetchCourses(true)
  showToast('Course directory and statistics synchronized.', 'success')
}

// ── Action Dropdown Management ────────────────────────────────────────────────
const activeActionDropdown = ref<number | string | null>(null)

const toggleActionDropdown = (id: number | string) => {
  activeActionDropdown.value = activeActionDropdown.value === id ? null : id
}

const handleActionClick = (actionFn: () => void) => {
  activeActionDropdown.value = null
  actionFn()
}

const closeAllDropdowns = () => {
  activeActionDropdown.value = null
  showExportDropdown.value = false
  showColumnDropdown.value = false
}

onMounted(async () => {
  window.addEventListener('click', closeAllDropdowns)
  await Promise.all([fetchDepartments(), fetchInstructors()])
  await fetchCourses()
})

onUnmounted(() => {
  window.removeEventListener('click', closeAllDropdowns)
})

// ── Computed Stats (Strict Real Database Values) ──────────────────────────────
const stats = computed(() => {
  const total = backendStats.value?.total ?? allCourses.value.length
  const active = backendStats.value?.active ?? allCourses.value.filter(c => c.status === 'active').length
  const inactive = backendStats.value?.inactive ?? (total - active)
  const assigned = backendStats.value?.assigned ?? allCourses.value.filter(c => getCourseInstructors(c).length > 0).length
  const unassigned = backendStats.value?.unassigned ?? (total - assigned)
  const currentSemester = settingsStore.semester || 'Second Semester'
  const thisSemester = backendStats.value?.this_semester ?? allCourses.value.filter(c => (c.semester || '').toLowerCase().includes('second') || c.semester === currentSemester).length
  const totalCredits = backendStats.value?.total_credits ?? allCourses.value.reduce((sum, c) => sum + (Number(c.credits) || 0), 0)
  const growth = backendStats.value?.growth ?? null

  return {
    total,
    active,
    inactive,
    assigned,
    unassigned,
    thisSemester,
    totalCredits,
    growth,
  }
})

// ── Interactive KPI Click Handler ─────────────────────────────────────────────
const handleKpiClick = (type: 'total' | 'active' | 'inactive' | 'assigned' | 'this_semester') => {
  if (type === 'total') {
    statusFilter.value = 'all'
    semesterFilter.value = 'all'
    assignmentFilter.value = 'all'
  } else if (type === 'active') {
    statusFilter.value = 'active'
    assignmentFilter.value = 'all'
  } else if (type === 'inactive') {
    statusFilter.value = 'inactive'
    assignmentFilter.value = 'all'
  } else if (type === 'assigned') {
    assignmentFilter.value = 'assigned'
  } else if (type === 'this_semester') {
    semesterFilter.value = settingsStore.semester || 'Second Semester'
    assignmentFilter.value = 'all'
  }
  currentPage.value = 1
}

// ── Filtering Logic ───────────────────────────────────────────────────────────
const filtered = computed(() => {
  return allCourses.value.filter(course => {
    // 1. Debounced Search
    if (debouncedSearch.value) {
      const q = debouncedSearch.value
      const matchName = course.name?.toLowerCase().includes(q)
      const matchCode = course.code?.toLowerCase().includes(q)
      const matchDept = course.departmentName?.toLowerCase().includes(q)
      const matchInst = course.instructor?.toLowerCase().includes(q)
      if (!matchName && !matchCode && !matchDept && !matchInst) return false
    }

    // 2. Department
    if (deptFilter.value !== 'all') {
      if (course.departmentName !== deptFilter.value && String(course.department_id) !== String(deptFilter.value)) {
        return false
      }
    }

    // 3. Status
    if (statusFilter.value !== 'all') {
      if (course.status !== statusFilter.value) return false
    }

    // 4. Academic Year Level
    if (levelFilter.value !== 'all') {
      if (course.level !== levelFilter.value) return false
    }

    // 5. Semester
    if (semesterFilter.value !== 'all') {
      if (course.semester !== semesterFilter.value) return false
    }

    // 6. Assignment Status
    if (assignmentFilter.value !== 'all') {
      const hasFaculty = getCourseInstructors(course).length > 0
      if (assignmentFilter.value === 'assigned' && !hasFaculty) return false
      if (assignmentFilter.value === 'unassigned' && hasFaculty) return false
    }

    return true
  })
})

// ── Sorting Logic ─────────────────────────────────────────────────────────────
const sorted = computed(() => {
  const list = [...filtered.value]
  const dir = sortDirection.value === 'asc' ? 1 : -1

  return list.sort((a, b) => {
    switch (sortField.value) {
      case 'code':
        return dir * (a.code || '').localeCompare(b.code || '')
      case 'title':
        return dir * (a.name || a.title || '').localeCompare(b.name || b.title || '')
      case 'department':
        return dir * (a.departmentName || '').localeCompare(b.departmentName || '')
      case 'level':
        return dir * (a.level || '').localeCompare(b.level || '')
      case 'credits':
        return dir * ((Number(a.credits) || 0) - (Number(b.credits) || 0))
      case 'status':
        return dir * (a.status || '').localeCompare(b.status || '')
      case 'instructors':
        return dir * (getCourseInstructors(a).length - getCourseInstructors(b).length)
      case 'created':
        return dir * (new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime())
      default:
        return 0
    }
  })
})

// ── Pagination Calculation ────────────────────────────────────────────────────
const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / perPage.value)))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return sorted.value.slice(start, start + perPage.value)
})

const visiblePages = computed(() => {
  const pages: (number | string)[] = []
  const total = totalPages.value
  const current = currentPage.value

  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    pages.push(1)
    if (current > 3) pages.push('...')
    const start = Math.max(2, current - 1)
    const end = Math.min(total - 1, current + 1)
    for (let i = start; i <= end; i++) pages.push(i)
    if (current < total - 2) pages.push('...')
    pages.push(total)
  }
  return pages
})

const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }
const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
const goToPage = (page: number | string) => {
  if (typeof page === 'number' && page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

watch([debouncedSearch, deptFilter, statusFilter, levelFilter, semesterFilter, assignmentFilter], () => {
  currentPage.value = 1
})

watch(sorted, (newVal) => {
  const max = Math.max(1, Math.ceil(newVal.length / perPage.value))
  if (currentPage.value > max) currentPage.value = max
})

// ── Active Filter Chips ───────────────────────────────────────────────────────
const activeFilterChips = computed(() => {
  const chips: { id: string; label: string; clear: () => void }[] = []

  if (debouncedSearch.value) {
    chips.push({
      id: 'search',
      label: `Search: "${debouncedSearch.value}"`,
      clear: () => clearSearch(),
    })
  }
  if (deptFilter.value !== 'all') {
    chips.push({
      id: 'dept',
      label: `Dept: ${deptFilter.value}`,
      clear: () => { deptFilter.value = 'all' },
    })
  }
  if (statusFilter.value !== 'all') {
    chips.push({
      id: 'status',
      label: `Status: ${statusFilter.value}`,
      clear: () => { statusFilter.value = 'all' },
    })
  }
  if (levelFilter.value !== 'all') {
    chips.push({
      id: 'level',
      label: `Level: ${levelFilter.value}`,
      clear: () => { levelFilter.value = 'all' },
    })
  }
  if (semesterFilter.value !== 'all') {
    chips.push({
      id: 'semester',
      label: `Semester: ${semesterFilter.value}`,
      clear: () => { semesterFilter.value = 'all' },
    })
  }
  if (assignmentFilter.value !== 'all') {
    chips.push({
      id: 'assignment',
      label: assignmentFilter.value === 'assigned' ? 'Assigned Faculty' : 'Unassigned Courses',
      clear: () => { assignmentFilter.value = 'all' },
    })
  }

  return chips
})

const clearAllFilters = () => {
  clearSearch()
  deptFilter.value = 'all'
  statusFilter.value = 'all'
  levelFilter.value = 'all'
  semesterFilter.value = 'all'
  assignmentFilter.value = 'all'
  currentPage.value = 1
}

// ── Form Validation ───────────────────────────────────────────────────────────
const resetCourseValidation = () => {
  courseFormErrors.value = {}
  courseFormTouched.value = {}
}

const validateCourseField = (field: string) => {
  const f = newCourseForm.value
  const e = { ...courseFormErrors.value }
  const clear = () => { delete e[field] }

  switch (field) {
    case 'code': {
      const val = (f.code || '').trim()
      if (!val) {
        e.code = 'Course code is required.'
      } else if (val.length < 2 || val.length > 30) {
        e.code = 'Course code must be between 2 and 30 characters.'
      } else if (!/^[a-zA-Z0-9_\-\s/]+$/.test(val)) {
        e.code = 'Course code can only contain letters, numbers, hyphens, and slashes.'
      } else {
        clear()
      }
      break
    }
    case 'title': {
      const val = (f.title || '').trim()
      if (!val) {
        e.title = 'Course title is required.'
      } else if (val.length < 3) {
        e.title = 'Course title must be at least 3 characters.'
      } else if (val.length > 200) {
        e.title = 'Course title cannot exceed 200 characters.'
      } else {
        clear()
      }
      break
    }
    case 'department_id':
      if (!f.department_id) {
        e.department_id = 'Academic department is required.'
      } else {
        clear()
      }
      break
    case 'level':
      if (!f.level) {
        e.level = 'Academic year level is required.'
      } else {
        clear()
      }
      break
    case 'credits': {
      const raw = String(f.credits ?? '').trim()
      const n = Number(raw)
      if (!raw) {
        e.credits = 'Credit hours are required.'
      } else if (isNaN(n) || !Number.isInteger(n) || n < 1 || n > 30) {
        e.credits = 'Credits must be an integer between 1 and 30.'
      } else {
        clear()
      }
      break
    }
  }

  courseFormErrors.value = e
}

const touchCourseField = (field: string) => {
  courseFormTouched.value[field] = true
  validateCourseField(field)
}

const validateAddCourseForm = (): boolean => {
  const required = ['code', 'title', 'department_id', 'level', 'credits']
  required.forEach(f => {
    courseFormTouched.value[f] = true
    validateCourseField(f)
  })
  return Object.keys(courseFormErrors.value).every(k => !courseFormErrors.value[k])
}

const courseFieldCls = (field: string, extra = '') => {
  const base = `w-full border rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:outline-none transition-colors ${extra}`
  if (courseFormTouched.value[field]) {
    if (courseFormErrors.value[field]) return base + ' border-rose-400 bg-rose-50/20 focus:border-rose-500'
    return base + ' border-emerald-400 bg-emerald-50/10 focus:border-emerald-500'
  }
  return base + ' border-slate-200 focus:border-indigo-600 bg-white'
}

// ── Form Navigation & Actions ─────────────────────────────────────────────────
const openAddPage = () => {
  isEditing.value = false
  resetCourseValidation()
  newCourseForm.value = {
    code: '',
    title: '',
    type: '',
    department_id: allDepartments.value[0]?.id ? String(allDepartments.value[0].id) : '',
    program: '',
    level: '1st Year',
    semester: settingsStore.semester || 'Second Semester',
    credits: '3',
    language: 'English',
    short_description: '',
    full_description: '',
    instructor_id: '',
    co_instructors: '',
    capacity: '',
    enrollment_status: 'Open for Enrollment',
    visibility: 'Visible to Students',
    start_date: '',
    end_date: '',
    status: 'active',
  }
  showAddPage.value = true
  showDetailsPage.value = false
}

const openEditPage = (c: any) => {
  isEditing.value = true
  resetCourseValidation()
  selectedCourse.value = c
  newCourseForm.value = {
    code: c.code || '',
    title: c.name || c.title || '',
    type: c.type || '',
    department_id: c.department_id ? String(c.department_id) : '',
    program: c.program || '',
    level: c.level || '1st Year',
    semester: c.semester || settingsStore.semester || 'Second Semester',
    credits: String(c.credits || '3'),
    language: c.language || 'English',
    short_description: c.short_description || c.description || '',
    full_description: c.full_description || '',
    instructor_id: c.instructor_id ? String(c.instructor_id) : '',
    co_instructors: c.co_instructor_id ? String(c.co_instructor_id) : '',
    capacity: c.capacity || '',
    enrollment_status: c.enrollment_status || 'Open for Enrollment',
    visibility: c.visibility || 'Visible to Students',
    start_date: c.start_date ? c.start_date.substring(0, 10) : '',
    end_date: c.end_date ? c.end_date.substring(0, 10) : '',
    status: c.status || 'active',
  }
  showEditModal.value = true
}

const openDetailsPage = async (c: any) => {
  selectedCourse.value = c
  showDetailsPage.value = true
  showAddPage.value = false
  if (c?.id) {
    try {
      const res = await apiClient.get(`/admin/courses/${c.id}`)
      if (res.data?.data) {
        const item = res.data.data
        const instructors = getCourseInstructors(item)
        selectedCourse.value = {
          ...c,
          ...item,
          name: item.title || c.name,
          dept: item.department?.name || c.dept,
          departmentName: item.department?.name || c.departmentName,
          instructorsCount: instructors.length,
          instructor: instructors.length > 0 ? instructors[0].name : (item.instructor?.name || 'Unassigned'),
        }
      }
    } catch (e) {
      console.error('Failed to load fresh course details:', e)
    }
  }
}

const closeDetails = () => {
  showDetailsPage.value = false
}

const saveCourse = async () => {
  delete courseFormErrors.value._server
  if (!validateAddCourseForm()) return

  isLoading.value = true
  try {
    const payload = {
      title: newCourseForm.value.title.trim(),
      code: newCourseForm.value.code.trim().toUpperCase(),
      credits: Number(newCourseForm.value.credits) || 3,
      department_id: newCourseForm.value.department_id,
      instructor_id: newCourseForm.value.instructor_id || null,
      semester: newCourseForm.value.semester || settingsStore.semester || 'Second Semester',
      level: newCourseForm.value.level,
      start_date: newCourseForm.value.start_date || null,
      end_date: newCourseForm.value.end_date || null,
      visibility: newCourseForm.value.visibility || 'Visible to Students',
      enrollment_status: newCourseForm.value.enrollment_status || 'Open for Enrollment',
      status: newCourseForm.value.status || 'active',
    }

    if (isEditing.value && selectedCourse.value) {
      await apiClient.put(`/admin/courses/${selectedCourse.value.id}`, payload)
      showToast(`Course "${payload.title}" updated successfully.`, 'success')
    } else {
      await apiClient.post('/admin/courses', payload)
      showToast(`Course "${payload.title}" created successfully.`, 'success')
    }

    await fetchCourses(true)
    showAddPage.value = false
    showEditModal.value = false
    resetCourseValidation()

    if (selectedCourse.value && isEditing.value) {
      const updated = allCourses.value.find(c => c.id === selectedCourse.value.id)
      if (updated) selectedCourse.value = { ...updated }
    }
  } catch (err: any) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      const backendErrors = err.response.data.errors
      Object.keys(backendErrors).forEach(key => {
        courseFormTouched.value[key] = true
        courseFormErrors.value[key] = Array.isArray(backendErrors[key]) ? backendErrors[key][0] : backendErrors[key]
      })
    } else {
      const msg = err.response?.data?.message || (isEditing.value ? 'Failed to update course.' : 'Failed to create course.')
      courseFormErrors.value._server = msg
      showToast(msg, 'error')
    }
  } finally {
    isLoading.value = false
  }
}

// ── Delete Course ─────────────────────────────────────────────────────────────
const confirmDelete = (c: any) => {
  selectedCourse.value = c
  showDeleteModal.value = true
}

const deleteCourse = async () => {
  if (!selectedCourse.value) return
  isLoading.value = true
  try {
    await apiClient.delete(`/admin/courses/${selectedCourse.value.id}`)
    await fetchCourses(true)
    showDeleteModal.value = false
    if (showDetailsPage.value && selectedCourse.value?.id) {
      showDetailsPage.value = false
    }
    showToast(`Course "${selectedCourse.value.name}" deleted successfully.`, 'success')
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to delete course. It may be associated with exams or submissions.'
    showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

// ── Assign Instructors Modal ──────────────────────────────────────────────────
const formAvailableInstructors = computed(() => {
  if (!newCourseForm.value.department_id) return []
  return allInstructors.value.filter(i => String(i.department_id) === String(newCourseForm.value.department_id))
})

const instructorsForAssign = computed(() => {
  if (!courseToAssign.value?.department_id) return allInstructors.value
  return allInstructors.value.filter(i => String(i.department_id) === String(courseToAssign.value.department_id))
})

const availableInstructors = computed(() => {
  return instructorsForAssign.value.filter(inst => String(inst.id) !== String(assignCoInstructorId.value))
})

const availableCoInstructors = computed(() => {
  return instructorsForAssign.value.filter(inst => String(inst.id) !== String(assignInstructorId.value))
})

const openAssign = (course: any) => {
  courseToAssign.value = course
  assignSection.value = course.section || 'Both Sections'
  assignInstructorId.value = course.instructor_id ? String(course.instructor_id) : (course.instructor?.id ? String(course.instructor.id) : '')
  assignCoInstructorId.value = course.co_instructor_id ? String(course.co_instructor_id) : (course.coInstructor?.id ? String(course.coInstructor.id) : '')
  showAssignModal.value = true
}

const assignInstructor = async () => {
  if (!courseToAssign.value) return
  isLoading.value = true
  try {
    const res = await apiClient.put(`/admin/courses/${courseToAssign.value.id}`, {
      instructor_id: assignInstructorId.value || null,
      co_instructor_id: assignCoInstructorId.value || null,
      section: assignSection.value || null,
    })
    await fetchInstructors()
    await fetchCourses(true)

    if (selectedCourse.value && selectedCourse.value.id === courseToAssign.value.id) {
      const refreshed = allCourses.value.find(c => c.id === selectedCourse.value.id)
      if (refreshed) {
        selectedCourse.value = refreshed
      } else if (res.data?.data) {
        selectedCourse.value = {
          ...selectedCourse.value,
          ...res.data.data,
        }
      }
    }
    showAssignModal.value = false
    showToast('Course instructor assignments saved successfully.', 'success')
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to assign instructors.'
    showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

// ── Export Handling ───────────────────────────────────────────────────────────
const showExportDropdown = ref(false)
const isExporting = ref(false)

const handleExport = async (format: 'csv' | 'pdf') => {
  showExportDropdown.value = false
  isExporting.value = true
  try {
    const token = localStorage.getItem('auth_token')
    const params = new URLSearchParams()
    params.set('format', format)
    if (deptFilter.value !== 'all') params.set('department', deptFilter.value)
    if (statusFilter.value !== 'all') params.set('status', statusFilter.value)
    if (levelFilter.value !== 'all') params.set('level', levelFilter.value)
    if (semesterFilter.value !== 'all') params.set('semester', semesterFilter.value)
    if (debouncedSearch.value) params.set('search', debouncedSearch.value)

    const res = await fetch(`http://localhost:8000/api/v1/admin/courses-export?${params.toString()}`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })
    if (!res.ok) throw new Error('Export failed')
    const data = await res.json()
    const byteChars = atob(data.file)
    const byteNums = new Array(byteChars.length)
    for (let i = 0; i < byteChars.length; i++) byteNums[i] = byteChars.charCodeAt(i)
    const blob = new Blob([new Uint8Array(byteNums)], {
      type: format === 'pdf' ? 'application/pdf' : 'text/csv',
    })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = data.filename || `courses_export_${new Date().toISOString().slice(0, 10)}.${format}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)

    showToast(`Courses exported successfully as ${format.toUpperCase()}.`, 'success')
  } catch (err) {
    showToast('Failed to export courses. Please try again.', 'error')
  } finally {
    isExporting.value = false
  }
}

// ── Import Handling ───────────────────────────────────────────────────────────
const showImportModal = ref(false)
const selectedImportFile = ref<File | null>(null)
const isImporting = ref(false)
const importDragOver = ref(false)
const modalFileInput = ref<HTMLInputElement | null>(null)

const showImportErrorModal = ref(false)
const importErrorMessage = ref('')
const importErrorList = ref<string[]>([])

const showImportSuccessModal = ref(false)
const importSuccessMessage = ref('')
const importedCoursesList = ref<any[]>([])

const triggerImport = () => {
  selectedImportFile.value = null
  showImportModal.value = true
}

const onModalFileSelect = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) selectedImportFile.value = file
}

const onFileDrop = (event: DragEvent) => {
  importDragOver.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    const ext = file.name.split('.').pop()?.toLowerCase()
    if (ext === 'csv' || ext === 'pdf') {
      selectedImportFile.value = file
    } else {
      importErrorMessage.value = 'Invalid file format. Please upload a CSV (.csv) or PDF (.pdf) file.'
      importErrorList.value = ['Only .csv and .pdf file formats are supported for course import.']
      showImportErrorModal.value = true
    }
  }
}

const executeImport = async () => {
  if (!selectedImportFile.value) return
  isImporting.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedImportFile.value)

    const res = await apiClient.post('/admin/courses-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    showImportModal.value = false
    selectedImportFile.value = null
    importSuccessMessage.value = res.data.message || `Successfully imported courses.`
    importedCoursesList.value = res.data.courses || []
    showImportSuccessModal.value = true
    await fetchCourses(true)
  } catch (err: any) {
    showImportModal.value = false
    const data = err.response?.data
    importErrorMessage.value = data?.message || 'Failed to import courses.'
    importErrorList.value = Array.isArray(data?.errors)
      ? data.errors
      : (data?.message ? [data.message] : ['An unexpected error occurred during course import.'])
    showImportErrorModal.value = true
  } finally {
    isImporting.value = false
    if (modalFileInput.value) modalFileInput.value.value = ''
  }
}

const downloadSampleCsv = () => {
  const dept1 = allDepartments.value[0]?.name || 'Software Engineering'
  const dept2 = allDepartments.value[1]?.name || 'Computer Science'
  const headers = ['Course Code', 'Course Title', 'Department', 'Academic Year Level', 'Credits', 'Semester', 'Section', 'Status', 'Description']
  const row1 = ['CS-301', 'Compiler Design', dept1, '3rd Year', '4', 'Second Semester', 'Section A', 'Active', 'Study of compiler principles and construction techniques']
  const row2 = ['SE-204', 'Software Architecture', dept2, '2nd Year', '3', 'Second Semester', 'Section B', 'Active', 'Fundamental software architecture styles and patterns']

  const csvContent = [headers.join(','), row1.join(','), row2.join(',')].join('\n')
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'wollo_university_course_template.csv'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

// ── Toasts ────────────────────────────────────────────────────────────────────
interface ToastItem {
  id: number
  message: string
  type: 'success' | 'error' | 'warning'
}
const toasts = ref<ToastItem[]>([])

const showToast = (message: string, type: 'success' | 'error' | 'warning' = 'success') => {
  const id = Date.now()
  toasts.value.push({ id, message, type })
  setTimeout(() => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }, 4500)
}

// ── Bottom Charts & Analytics ─────────────────────────────────────────────────
const topDepartments = computed(() => {
  if (allDepartments.value && allDepartments.value.length > 0) {
    const list = allDepartments.value.map(d => {
      const count = allCourses.value.filter(c => c.department_id === d.id || c.departmentName === d.name || c.dept === d.name).length
      return { id: d.id, name: d.name, count }
    })
    return list.sort((a, b) => b.count - a.count).slice(0, 5)
  }
  const deptCounts: Record<string, number> = {}
  allCourses.value.forEach(c => {
    const dName = c.departmentName && c.departmentName !== '—' ? c.departmentName : 'General'
    deptCounts[dName] = (deptCounts[dName] || 0) + 1
  })
  return Object.entries(deptCounts)
    .map(([name, count]) => ({ id: name, name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})

const chartPalette = ['#4338ca', '#10b981', '#f59e0b', '#06b6d4', '#ec4899']

const chartData = computed(() => {
  const deptsWithCourses = topDepartments.value.filter(d => d.count > 0)
  if (deptsWithCourses.length === 0) {
    return {
      labels: ['No Courses'],
      datasets: [{
        backgroundColor: ['#e2e8f0'],
        data: [1],
        borderWidth: 0,
      }],
    }
  }
  return {
    labels: deptsWithCourses.map(d => d.name),
    datasets: [{
      backgroundColor: chartPalette.slice(0, deptsWithCourses.length),
      data: deptsWithCourses.map(d => d.count),
      borderWidth: 0,
      hoverOffset: 4,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '74%',
  plugins: { legend: { display: false }, tooltip: { enabled: true } },
}

const deptIcons = ['💻', '⚙️', '📊', '🗄️', '🌐']

const recentCourses = computed(() => {
  return [...allCourses.value]
    .sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())
    .slice(0, 5)
})
</script>

<template>
  <div class="space-y-6">

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- FLOATING TOAST NOTIFICATIONS                                           -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="fixed top-5 right-5 z-[150] space-y-2 pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-xs font-semibold max-w-sm"
          :class="{
            'bg-emerald-50 text-emerald-900 border-emerald-200': toast.type === 'success',
            'bg-rose-50 text-rose-900 border-rose-200': toast.type === 'error',
            'bg-amber-50 text-amber-900 border-amber-200': toast.type === 'warning',
          }"
        >
          <span class="w-2 h-2 rounded-full shrink-0" :class="{
            'bg-emerald-500': toast.type === 'success',
            'bg-rose-500': toast.type === 'error',
            'bg-amber-500': toast.type === 'warning',
          }"></span>
          <span class="flex-1">{{ toast.message }}</span>
        </div>
      </TransitionGroup>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 1. MAIN COURSE DIRECTORY VIEW                                           -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="!showAddPage && !showDetailsPage" class="space-y-6 pb-16 min-w-0 w-full">

      <!-- Institutional Command Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span>Administration</span>
            <span class="text-slate-300">/</span>
            <span>Academic</span>
            <span class="text-slate-300">/</span>
            <span class="text-indigo-600 font-bold">Courses</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
            Course Management
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
              {{ stats.total }} Courses
            </span>
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Manage academic curriculum, departments, faculty assignments, credits, and semester offerings.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 flex-wrap sm:flex-nowrap">
          <!-- Refresh -->
          <button
            @click="refreshData"
            :disabled="isRefreshing"
            class="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs border border-slate-200 transition-colors shadow-xs disabled:opacity-50"
            title="Refresh course listings and statistics"
          >
            <svg class="w-4 h-4 text-slate-500" :class="{ 'animate-spin': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span class="hidden sm:inline">Refresh</span>
          </button>

          <!-- Import -->
          <button
            @click="triggerImport"
            class="flex items-center gap-2 px-3.5 py-2.5 bg-white hover:bg-slate-50 text-indigo-700 font-bold rounded-xl text-xs border border-indigo-200 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <span>Import</span>
          </button>

          <!-- Export Dropdown -->
          <div class="relative">
            <button
              @click="showExportDropdown = !showExportDropdown"
              class="flex items-center gap-2 px-3.5 py-2.5 bg-white hover:bg-slate-50 text-indigo-700 font-bold rounded-xl text-xs border border-indigo-200 transition-colors shadow-xs"
            >
              <svg v-if="!isExporting" class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              <svg v-else class="w-4 h-4 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              <span>Export</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div
              v-if="showExportDropdown"
              class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-40 select-none animate-in fade-in zoom-in-95 duration-100"
            >
              <button
                @click="handleExport('csv')"
                class="w-full text-left px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2"
              >
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                Export as CSV (Excel)
              </button>
              <button
                @click="handleExport('pdf')"
                class="w-full text-left px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2"
              >
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                Export as PDF Document
              </button>
            </div>
          </div>

          <!-- Add Course (Primary CTA) -->
          <button
            @click="openAddPage"
            class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-colors shadow-xs shadow-indigo-200"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add New Course</span>
          </button>
        </div>
      </div>

      <!-- ── KPI Cards (100% Real Database Values & Interactive Filtering) ── -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- 1. Total Courses -->
        <div
          @click="handleKpiClick('total')"
          class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs hover:border-indigo-300 hover:shadow-md transition-all cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Courses</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.total }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">All registered courses</span>
            <span class="text-indigo-600 font-bold group-hover:underline text-[11px]">Show all →</span>
          </div>
        </div>

        <!-- 2. Active Courses -->
        <div
          @click="handleKpiClick('active')"
          class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs hover:border-emerald-300 hover:shadow-md transition-all cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Active Courses</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.active }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-emerald-600 font-bold flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              {{ stats.total > 0 ? Math.round((stats.active / stats.total) * 100) : 0 }}% of curriculum
            </span>
            <span class="text-emerald-700 font-bold group-hover:underline text-[11px]">Filter Active →</span>
          </div>
        </div>

        <!-- 3. Inactive Courses -->
        <div
          @click="handleKpiClick('inactive')"
          class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs hover:border-rose-300 hover:shadow-md transition-all cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
              </svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Inactive Courses</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.inactive }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">{{ stats.inactive === 0 ? 'No inactive offerings' : 'Requires review' }}</span>
            <span class="text-rose-600 font-bold group-hover:underline text-[11px]">Filter Inactive →</span>
          </div>
        </div>

        <!-- 4. Assigned Faculty -->
        <div
          @click="handleKpiClick('assigned')"
          class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs hover:border-sky-300 hover:shadow-md transition-all cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-11 h-11 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Assigned Courses</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.assigned }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-sky-600 font-bold">
              {{ stats.unassigned }} unassigned
            </span>
            <span class="text-sky-700 font-bold group-hover:underline text-[11px]">Filter Assigned →</span>
          </div>
        </div>

        <!-- 5. Courses This Semester -->
        <div
          @click="handleKpiClick('this_semester')"
          class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs hover:border-amber-300 hover:shadow-md transition-all cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Current Semester</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.thisSemester }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium truncate max-w-[110px]">{{ settingsStore.semester || 'Second Semester' }}</span>
            <span class="text-amber-700 font-bold group-hover:underline text-[11px]">Filter Semester →</span>
          </div>
        </div>
      </div>

      <!-- ── Search & Filter Toolbar ── -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-4 sm:p-5 space-y-3.5">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
          <!-- Search Input with Debounce -->
          <div class="relative flex-1 min-w-[240px] max-w-full lg:max-w-md">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="search"
              @input="handleSearchInput"
              type="text"
              placeholder="Search by course code, title, department, instructor..."
              class="w-full bg-slate-50/70 border border-slate-200 rounded-xl pl-10 pr-9 py-2 text-xs font-medium text-slate-800 focus:outline-none focus:border-indigo-500 focus:bg-white transition-all placeholder:text-slate-400"
            />
            <button
              v-if="search"
              @click="clearSearch"
              class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 transition-colors"
              title="Clear search"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Dropdown Filter Toolbar -->
          <div class="flex flex-wrap items-center gap-2">
            <!-- Department -->
            <select
              v-model="deptFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Departments</option>
              <option v-for="d in allDepartments" :key="d.id" :value="d.name">{{ d.name }}</option>
            </select>

            <!-- Academic Year Level -->
            <select
              v-model="levelFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Year Levels</option>
              <option v-for="lvl in academicYearLevels" :key="lvl" :value="lvl">{{ lvl }}</option>
            </select>

            <!-- Semester -->
            <select
              v-model="semesterFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Semesters</option>
              <option v-for="sem in semesterOptions" :key="sem" :value="sem">{{ sem }}</option>
            </select>

            <!-- Status -->
            <select
              v-model="statusFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Status</option>
              <option value="active">Active Only</option>
              <option value="inactive">Inactive Only</option>
            </select>

            <!-- Assignment Status -->
            <select
              v-model="assignmentFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Assignments</option>
              <option value="assigned">Assigned Faculty</option>
              <option value="unassigned">Unassigned Only</option>
            </select>

            <!-- Columns Management Dropdown -->
            <div class="relative">
              <button
                @click="showColumnDropdown = !showColumnDropdown"
                class="p-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-colors"
                title="Customize table columns"
              >
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
              </button>

              <div
                v-if="showColumnDropdown"
                class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 p-2.5 z-40 space-y-1.5 animate-in fade-in"
              >
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-2 py-1">Visible Columns</p>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.department" class="rounded text-indigo-600" />
                  Department
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.level" class="rounded text-indigo-600" />
                  Academic Level
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.instructors" class="rounded text-indigo-600" />
                  Instructors
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.credits" class="rounded text-indigo-600" />
                  Credits
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.semester" class="rounded text-indigo-600" />
                  Semester
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.status" class="rounded text-indigo-600" />
                  Status
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.created_by" class="rounded text-indigo-600" />
                  Created By
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Active Filter Chips Row -->
        <div v-if="activeFilterChips.length > 0" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Active Filters:</span>
          <div
            v-for="chip in activeFilterChips"
            :key="chip.id"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-lg text-xs font-semibold"
          >
            <span>{{ chip.label }}</span>
            <button @click="chip.clear" class="hover:text-indigo-900 transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <button
            @click="clearAllFilters"
            class="text-xs font-bold text-slate-500 hover:text-rose-600 ml-1 transition-colors underline"
          >
            Clear All
          </button>
        </div>
      </div>

      <!-- ── Modern Enterprise Course Table ── -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs flex flex-col min-w-0 w-full overflow-hidden">
        
        <!-- Table Scroll Container -->
        <div class="overflow-x-auto min-w-0 flex-1 min-h-[360px]">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/70 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider select-none">
                <th @click="handleSort('code')" class="px-5 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Course Code</span>
                    <span v-if="sortField === 'code'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th @click="handleSort('title')" class="px-5 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Course Title</span>
                    <span v-if="sortField === 'title'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.department" @click="handleSort('department')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Department</span>
                    <span v-if="sortField === 'department'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.level" @click="handleSort('level')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Year Level</span>
                    <span v-if="sortField === 'level'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.instructors" @click="handleSort('instructors')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Assigned Faculty</span>
                    <span v-if="sortField === 'instructors'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.credits" @click="handleSort('credits')" class="px-4 py-3.5 text-center whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center justify-center gap-1.5">
                    <span>Credits</span>
                    <span v-if="sortField === 'credits'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.semester" class="px-4 py-3.5 whitespace-nowrap">Semester</th>
                <th v-if="visibleColumns.status" @click="handleSort('status')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Status</span>
                    <span v-if="sortField === 'status'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.created_by" class="px-4 py-3.5 text-center whitespace-nowrap">Created By</th>
                <th class="px-5 py-3.5 text-right whitespace-nowrap">Actions</th>
              </tr>
            </thead>

            <!-- Skeleton Loading State -->
            <tbody v-if="isLoading" class="divide-y divide-slate-50">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="px-5 py-3.5"><div class="h-6 w-16 bg-slate-200 rounded-md"></div></td>
                <td class="px-5 py-3.5"><div class="h-4 w-36 bg-slate-200 rounded"></div></td>
                <td v-if="visibleColumns.department" class="px-4 py-3.5"><div class="h-3 w-28 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.level" class="px-4 py-3.5"><div class="h-3 w-16 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.instructors" class="px-4 py-3.5"><div class="h-6 w-24 bg-slate-100 rounded-full"></div></td>
                <td v-if="visibleColumns.credits" class="px-4 py-3.5 text-center"><div class="h-4 w-10 bg-slate-100 rounded mx-auto"></div></td>
                <td v-if="visibleColumns.semester" class="px-4 py-3.5"><div class="h-3 w-20 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.status" class="px-4 py-3.5"><div class="h-5 w-16 bg-slate-100 rounded-full"></div></td>
                <td v-if="visibleColumns.created_by" class="px-4 py-3.5 text-center"><div class="h-4 w-14 bg-slate-100 rounded mx-auto"></div></td>
                <td class="px-5 py-3.5 text-right"><div class="h-6 w-20 bg-slate-100 rounded ml-auto"></div></td>
              </tr>
            </tbody>

            <!-- Real Course Rows -->
            <tbody v-else class="divide-y divide-slate-50">
              <tr
                v-for="(course, index) in paginated"
                :key="course.id"
                class="hover:bg-indigo-50/30 transition-colors group"
              >
                <!-- Course Code (Prominent Mono Badge) -->
                <td class="px-5 py-3.5 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-100 text-[#4338ca] font-mono font-black text-xs tracking-wide">
                    {{ course.code }}
                  </span>
                </td>

                <!-- Course Title (Clickable) -->
                <td class="px-5 py-3.5">
                  <div class="min-w-0">
                    <p
                      @click="openDetailsPage(course)"
                      class="text-xs font-bold text-slate-900 group-hover:text-indigo-700 transition-colors cursor-pointer hover:underline truncate max-w-[240px]"
                    >
                      {{ course.name }}
                    </p>
                    <p class="text-[10px] text-slate-400 font-medium truncate">
                      {{ course.section || 'All Sections' }}
                    </p>
                  </div>
                </td>

                <!-- Department -->
                <td v-if="visibleColumns.department" class="px-4 py-3.5 whitespace-nowrap">
                  <span class="text-xs font-semibold text-slate-700">
                    {{ course.departmentName }}
                  </span>
                </td>

                <!-- Academic Level -->
                <td v-if="visibleColumns.level" class="px-4 py-3.5 text-xs text-slate-600 font-medium whitespace-nowrap">
                  {{ course.level || '—' }}
                </td>

                <!-- Assigned Instructors (Dynamic Faculty Chip) -->
                <td v-if="visibleColumns.instructors" class="px-4 py-3.5 whitespace-nowrap">
                  <div v-if="getCourseInstructors(course).length > 0" class="flex items-center gap-1.5">
                    <div
                      @click="openAssign(course)"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200 transition-colors cursor-pointer group/inst"
                      :title="getCourseInstructors(course).map(i => `${i.name} (${i.section || 'Instructor'})`).join(', ')"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                      <span class="text-xs font-semibold text-slate-800 group-hover/inst:text-indigo-700">
                        {{ getCourseInstructors(course)[0].name }}
                      </span>
                      <span v-if="getCourseInstructors(course).length > 1" class="text-[10px] font-bold text-indigo-600 bg-indigo-100/70 px-1.5 py-0.2 rounded-full">
                        +{{ getCourseInstructors(course).length - 1 }}
                      </span>
                    </div>
                  </div>
                  <div v-else>
                    <button
                      @click="openAssign(course)"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-dashed border-amber-300 bg-amber-50/60 text-amber-700 text-[11px] font-semibold hover:bg-amber-100 transition-colors"
                      title="Click to assign faculty"
                    >
                      <span>Unassigned</span>
                      <span class="text-[10px]">+</span>
                    </button>
                  </div>
                </td>

                <!-- Credits -->
                <td v-if="visibleColumns.credits" class="px-4 py-3.5 text-center whitespace-nowrap">
                  <span class="text-xs font-bold text-slate-700">
                    {{ course.credits }} CR
                  </span>
                </td>

                <!-- Semester -->
                <td v-if="visibleColumns.semester" class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                  {{ course.semester }}
                </td>

                <!-- Status Badge -->
                <td v-if="visibleColumns.status" class="px-4 py-3.5 whitespace-nowrap">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-full capitalize border"
                    :class="{
                      'bg-emerald-50 text-emerald-700 border-emerald-200': course.status === 'active',
                      'bg-slate-100 text-slate-600 border-slate-200': course.status === 'inactive',
                    }"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full"
                      :class="{
                        'bg-emerald-500': course.status === 'active',
                        'bg-slate-400': course.status === 'inactive',
                      }"
                    ></span>
                    {{ course.status }}
                  </span>
                </td>

                <!-- Created By -->
                <td v-if="visibleColumns.created_by" class="px-4 py-3.5 text-center whitespace-nowrap">
                  <span class="text-[11px] font-bold text-sky-700 bg-sky-50 border border-sky-100 px-2 py-0.5 rounded-md">
                    {{ course.created_by }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                  <div class="relative inline-block text-left">
                    <button
                      @click.stop="toggleActionDropdown(course.id)"
                      type="button"
                      class="w-8 h-8 rounded-xl inline-flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-100 transition-all focus:outline-none"
                      :class="{ 'bg-indigo-50 text-indigo-700 border-indigo-200': activeActionDropdown === course.id }"
                      title="Course Actions"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                      </svg>
                    </button>

                    <!-- Action Dropdown Menu -->
                    <div
                      v-if="activeActionDropdown === course.id"
                      @click.stop
                      class="absolute right-0 w-44 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-50 select-none animate-in fade-in zoom-in-95 duration-100"
                      :class="index >= paginated.length - 2 && paginated.length > 2 ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
                    >
                      <!-- View Details -->
                      <button
                        @click="handleActionClick(() => openDetailsPage(course))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>View Details</span>
                      </button>

                      <!-- Assign Faculty -->
                      <button
                        @click="handleActionClick(() => openAssign(course))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span>Assign Faculty</span>
                      </button>

                      <!-- Edit Course -->
                      <button
                        @click="handleActionClick(() => openEditPage(course))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span>Edit Course</span>
                      </button>

                      <div class="h-px bg-slate-100 my-1"></div>

                      <!-- Delete Course -->
                      <button
                        @click="handleActionClick(() => confirmDelete(course))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-rose-500 group-hover/item:text-rose-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Delete Course</span>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>

              <!-- Empty State: Filters Match 0 -->
              <tr v-if="paginated.length === 0">
                <td :colspan="10" class="px-6 py-14 text-center">
                  <div class="max-w-xs mx-auto flex flex-col items-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    <p class="text-sm font-bold text-slate-800">No courses found</p>
                    <p class="text-xs text-slate-500 mt-1">There are no courses matching your current search or filter criteria.</p>
                    <button
                      @click="clearAllFilters"
                      class="mt-4 px-4 py-2 bg-indigo-50 text-indigo-700 font-bold rounded-xl text-xs hover:bg-indigo-100 transition-colors"
                    >
                      Clear All Filters
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Professional Pagination Footer -->
        <div class="px-5 py-3.5 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
          <div class="flex items-center gap-3 text-xs text-slate-500">
            <span>
              Showing <strong class="text-slate-800">{{ filtered.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }}</strong> to <strong class="text-slate-800">{{ Math.min(currentPage * perPage, filtered.length) }}</strong> of <strong class="text-slate-800">{{ filtered.length }}</strong> courses
            </span>
            <div class="flex items-center gap-1.5 ml-2">
              <span class="text-slate-400 font-medium">Per page:</span>
              <select
                v-model="perPage"
                class="border border-slate-200 rounded-lg px-2 py-0.5 text-xs font-semibold text-slate-700 bg-white focus:outline-none"
              >
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
              </select>
            </div>
          </div>

          <!-- Page Controls -->
          <div class="flex items-center gap-1.5">
            <button
              @click="prevPage"
              :disabled="currentPage <= 1"
              class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-white hover:border-indigo-400 hover:text-indigo-700 disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:border-slate-200 disabled:cursor-not-allowed text-xs font-bold transition-all"
            >
              ‹ Previous
            </button>

            <template v-for="(page, idx) in visiblePages" :key="idx">
              <span v-if="page === '...'" class="w-7 h-7 flex items-center justify-center text-xs text-slate-400">…</span>
              <button
                v-else
                @click="goToPage(page)"
                class="w-7 h-7 flex items-center justify-center rounded-lg border text-xs font-bold transition-all"
                :class="currentPage === page ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300'"
              >
                {{ page }}
              </button>
            </template>

            <button
              @click="nextPage"
              :disabled="currentPage >= totalPages"
              class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-white hover:border-indigo-400 hover:text-indigo-700 disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:border-slate-200 disabled:cursor-not-allowed text-xs font-bold transition-all"
            >
              Next ›
            </button>
          </div>
        </div>

      </div>

      <!-- ── Bottom Overview & Real Academic Insights ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Course Overview Donut -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs">
          <h3 class="text-sm font-bold text-slate-900 mb-4">Department Distribution</h3>
          <div class="relative w-40 h-40 mx-auto mb-4">
            <Doughnut :data="chartData" :options="chartOptions" />
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <span class="text-2xl font-black text-slate-800 leading-none">{{ stats.total }}</span>
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Courses</span>
            </div>
          </div>
          <div class="space-y-1.5 text-xs">
            <div v-for="(label, i) in chartData.labels.slice(0, 4)" :key="i" class="flex items-center justify-between">
              <div class="flex items-center gap-2 min-w-0">
                <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: chartPalette[i % chartPalette.length] }"></div>
                <span class="text-slate-600 truncate">{{ label }}</span>
              </div>
              <span class="font-bold text-slate-800 shrink-0">
                {{ chartData.datasets[0].data[i] }}
              </span>
            </div>
          </div>
        </div>

        <!-- Top Departments by Course Count -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Top Departments</h3>
            <div class="space-y-2.5">
              <div
                v-for="(dept, i) in topDepartments"
                :key="dept.name"
                class="flex items-center justify-between text-xs p-1.5 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
                @click="deptFilter = dept.name"
                :title="'Filter courses by ' + dept.name"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span class="text-sm">{{ deptIcons[i] || '📁' }}</span>
                  <span class="font-semibold text-slate-700 truncate">{{ dept.name }}</span>
                </div>
                <span class="font-bold text-indigo-700 shrink-0">{{ dept.count }}</span>
              </div>
              <div v-if="topDepartments.length === 0" class="text-xs text-slate-400 text-center py-4">No course offerings recorded</div>
            </div>
          </div>
          <button @click="router.push('/admin/departments')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 text-left mt-3">
            Manage All Departments →
          </button>
        </div>

        <!-- Recent Courses -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Recently Added Courses</h3>
            <div class="space-y-3">
              <div
                v-for="c in recentCourses"
                :key="c.id"
                class="flex items-center gap-3 p-1 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors group"
                @click="openDetailsPage(c)"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 font-mono font-bold text-[10px] flex items-center justify-center shrink-0 border border-indigo-100">
                  {{ (c.code || 'CR').slice(0, 3) }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-bold text-slate-800 truncate group-hover:text-indigo-700">{{ c.name }}</p>
                  <p class="text-[10px] text-slate-400 truncate">{{ c.departmentName }} • {{ c.credits }} CR</p>
                </div>
                <span class="text-[10px] text-slate-400 whitespace-nowrap shrink-0">{{ c.created_on }}</span>
              </div>
              <div v-if="recentCourses.length === 0" class="text-xs text-slate-400 text-center py-4">No recent courses added</div>
            </div>
          </div>
          <p class="text-[11px] text-slate-400 mt-3">Sorted by registration date</p>
        </div>

        <!-- Quick Shortcuts -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Course Actions</h3>
            <div class="grid grid-cols-2 gap-2.5">
              <button @click="openAddPage" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 hover:border-indigo-300 hover:bg-indigo-50/40 group transition-all text-center">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-1.5 group-hover:scale-105 transition-transform">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                </div>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-indigo-700">Add Course</span>
              </button>
              <button @click="triggerImport" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 hover:border-indigo-300 hover:bg-indigo-50/40 group transition-all text-center">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-1.5 group-hover:scale-105 transition-transform">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                </div>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-indigo-700">Import CSV/PDF</span>
              </button>
              <button @click="handleExport('csv')" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 hover:border-indigo-300 hover:bg-indigo-50/40 group transition-all text-center">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-1.5 group-hover:scale-105 transition-transform">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                </div>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-indigo-700">Export CSV</span>
              </button>
              <button @click="router.push('/admin/departments')" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 hover:border-indigo-300 hover:bg-indigo-50/40 group transition-all text-center">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-1.5 group-hover:scale-105 transition-transform">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                </div>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-indigo-700">Departments</span>
              </button>
            </div>
          </div>
          <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
            <span>Last synced: {{ lastRefreshedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
            <span class="font-bold text-indigo-600 cursor-pointer" @click="refreshData">Sync</span>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 2. COURSE DETAIL VIEW                                                  -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="showDetailsPage" class="space-y-6 pb-16 min-w-0 w-full">
      <!-- Breadcrumb Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <button
            @click="closeDetails"
            class="w-10 h-10 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-100 transition-colors text-slate-600"
            title="Back to Courses Directory"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          </button>
          <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
              <span @click="closeDetails" class="hover:text-indigo-600 cursor-pointer">Courses</span>
              <span>/</span>
              <span class="text-indigo-600 font-bold">Curriculum Details</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
              {{ selectedCourse?.name }}
              <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100">
                {{ selectedCourse?.code }}
              </span>
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold capitalize border"
                :class="{
                  'bg-emerald-50 text-emerald-700 border-emerald-200': selectedCourse?.status === 'active',
                  'bg-slate-100 text-slate-700 border-slate-200': selectedCourse?.status === 'inactive',
                }"
              >
                {{ selectedCourse?.status }}
              </span>
            </h1>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="openAssign(selectedCourse)"
            class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold rounded-xl text-xs hover:bg-emerald-100 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
            Assign Faculty
          </button>
          <button
            @click="openEditPage(selectedCourse)"
            class="flex items-center gap-2 px-4 py-2.5 bg-white border border-indigo-300 text-indigo-700 font-bold rounded-xl text-xs hover:bg-indigo-50 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            Edit Course
          </button>
          <button
            @click="confirmDelete(selectedCourse)"
            class="flex items-center gap-2 px-4 py-2.5 bg-white border border-rose-200 text-rose-600 font-bold rounded-xl text-xs hover:bg-rose-50 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Remove Course
          </button>
        </div>
      </div>

      <!-- Detail Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
        <div class="space-y-6 min-w-0">
          
          <!-- Academic Placement -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-6 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Academic Placement & Curriculum
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5 text-xs">
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Department</p>
                <p class="text-sm font-black text-slate-800">{{ selectedCourse?.departmentName }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Credit Hours</p>
                <p class="text-sm font-black text-slate-800">{{ selectedCourse?.credits }} Credits</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Academic Level</p>
                <p class="text-sm font-black text-slate-800">{{ selectedCourse?.level }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Semester</p>
                <p class="text-xs font-bold text-slate-700">{{ selectedCourse?.semester }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Assigned Section</p>
                <p class="text-xs font-bold text-slate-700">{{ selectedCourse?.section || 'Both Sections' }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Enrollment Status</p>
                <p class="text-xs font-bold text-emerald-700">{{ selectedCourse?.enrollment_status || 'Open for Enrollment' }}</p>
              </div>
            </div>
          </div>

          <!-- Faculty Assignment Card -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                Assigned Faculty Members
              </h3>
              <button
                @click="openAssign(selectedCourse)"
                class="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1"
              >
                <span>Manage Assignments</span>
                <span>→</span>
              </button>
            </div>

            <div v-if="selectedCourseInstructors.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div
                v-for="inst in selectedCourseInstructors"
                :key="inst.id"
                class="flex items-center gap-3.5 p-4 rounded-xl border border-slate-100 bg-slate-50/70"
              >
                <div class="w-11 h-11 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 uppercase shadow-2xs">
                  {{ inst.avatar }}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-bold text-slate-800 truncate">{{ inst.name }}</p>
                    <span
                      class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase"
                      :class="inst.section === 'Section B' ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-blue-100 text-blue-700 border border-blue-200'"
                    >
                      {{ inst.section || 'Section A' }}
                    </span>
                  </div>
                  <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ inst.email || 'No institutional email' }}</p>
                  <p class="text-[10px] text-slate-400 capitalize mt-0.5">{{ inst.role ? inst.role.replace('_', ' ') : 'Faculty' }}</p>
                </div>
              </div>
            </div>

            <div v-else class="p-6 rounded-xl border border-dashed border-slate-200 text-center bg-slate-50/50">
              <p class="text-xs text-slate-500">No instructors are currently assigned to this course.</p>
              <button @click="openAssign(selectedCourse)" class="mt-2 text-xs font-bold text-indigo-600 hover:underline inline-flex items-center gap-1">
                + Assign instructors for Section A and Section B
              </button>
            </div>
          </div>

          <!-- Description -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-3">Course Description & Syllabus Summary</h3>
            <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-line">
              {{ selectedCourse?.description || selectedCourse?.short_description || selectedCourse?.full_description || 'No detailed syllabus or course description recorded in system.' }}
            </p>
          </div>

        </div>

        <!-- Sidebar Details -->
        <div class="space-y-6">
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-xs">
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-4">Record Metadata</h4>
            <div class="space-y-3.5 text-xs">
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Created By</span>
                <span class="font-bold text-slate-700">{{ selectedCourse?.created_by }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Created Date</span>
                <span class="font-semibold text-slate-700">{{ formatDateTime(selectedCourse?.created_at) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Last Updated</span>
                <span class="font-semibold text-slate-700">{{ formatDateTime(selectedCourse?.updated_at) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Visibility</span>
                <span class="font-bold text-slate-700">{{ selectedCourse?.visibility || 'Visible to Students' }}</span>
              </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 space-y-2">
              <button
                @click="openEditPage(selectedCourse)"
                class="w-full py-2.5 px-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl text-xs transition-colors flex items-center justify-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                Edit Course
              </button>
              <button
                @click="closeDetails"
                class="w-full py-2.5 px-4 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-xl text-xs transition-colors"
              >
                Back to Courses
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 3. ADD COURSE VIEW                                                     -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="showAddPage" class="space-y-6 pb-16 min-w-0 w-full">
      <!-- Breadcrumb Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span class="hover:text-indigo-600 cursor-pointer" @click="showAddPage = false">Courses</span>
            <span>/</span>
            <span class="text-indigo-600 font-bold">Add New Course</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Add New Course</h1>
        </div>
        <button
          @click="showAddPage = false"
          class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          Back to Courses
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
        <!-- Form Columns -->
        <div class="space-y-6 min-w-0">
          
          <!-- Course Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs space-y-5">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Course Information
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Course Code <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.code"
                  type="text"
                  placeholder="e.g. CS-301 or SE204"
                  :class="courseFieldCls('code')"
                  @input="touchCourseField('code')"
                />
                <p v-if="courseFormErrors.code" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ courseFormErrors.code }}
                </p>
              </div>

              <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Course Title <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.title"
                  type="text"
                  placeholder="e.g. Advanced Operating Systems"
                  :class="courseFieldCls('title')"
                  @input="touchCourseField('title')"
                />
                <p v-if="courseFormErrors.title" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ courseFormErrors.title }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 pt-2">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Department <span class="text-rose-500">*</span></label>
                <select
                  v-model="newCourseForm.department_id"
                  :class="courseFieldCls('department_id')"
                  @change="touchCourseField('department_id')"
                >
                  <option value="">Select Department</option>
                  <option v-for="d in allDepartments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
                <p v-if="courseFormErrors.department_id" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ courseFormErrors.department_id }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Academic Year Level <span class="text-rose-500">*</span></label>
                <select
                  v-model="newCourseForm.level"
                  :class="courseFieldCls('level')"
                  @change="touchCourseField('level')"
                >
                  <option v-for="lvl in academicYearLevels" :key="lvl" :value="lvl">{{ lvl }}</option>
                </select>
                <p v-if="courseFormErrors.level" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ courseFormErrors.level }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Credit Hours <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.credits"
                  type="number"
                  min="1"
                  max="30"
                  :class="courseFieldCls('credits')"
                  @input="touchCourseField('credits')"
                />
                <p v-if="courseFormErrors.credits" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ courseFormErrors.credits }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Semester</label>
                <select
                  v-model="newCourseForm.semester"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-600 bg-white"
                >
                  <option v-for="sem in semesterOptions" :key="sem" :value="sem">{{ sem }}</option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Current academic semester: {{ settingsStore.semester }}</p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Primary Instructor (Optional)</label>
                <select
                  v-model="newCourseForm.instructor_id"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-600 bg-white"
                >
                  <option value="">Unassigned (Can assign later)</option>
                  <option v-for="inst in formAvailableInstructors" :key="inst.id" :value="String(inst.id)">{{ inst.name }}</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Course Settings (Optional) -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs space-y-5">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center justify-between">
              <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                Course Settings & Visibility
              </span>
              <span class="text-[10px] font-bold text-slate-400 uppercase bg-slate-100 px-2 py-0.5 rounded">Optional</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Enrollment Status</label>
                <select
                  v-model="newCourseForm.enrollment_status"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-600 bg-white"
                >
                  <option value="Open for Enrollment">Open for Enrollment</option>
                  <option value="Closed">Closed</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Visibility</label>
                <select
                  v-model="newCourseForm.visibility"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-600 bg-white"
                >
                  <option value="Visible to Students">Visible to Students</option>
                  <option value="Hidden">Hidden from Students</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Course Description</label>
              <textarea
                v-model="newCourseForm.short_description"
                rows="3"
                placeholder="Brief course objectives and syllabus summary..."
                class="w-full border border-slate-200 rounded-xl p-3 text-xs focus:outline-none focus:border-indigo-600"
              ></textarea>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              @click="showAddPage = false"
              class="px-6 py-2.5 border border-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="saveCourse"
              :disabled="isLoading"
              class="flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-colors shadow-xs shadow-indigo-200 disabled:opacity-50"
            >
              <svg v-if="isLoading" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              <span>{{ isLoading ? 'Creating Course...' : 'Create Course' }}</span>
            </button>
          </div>

        </div>

        <!-- Right Summary Preview -->
        <div class="space-y-6">
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-xs">
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-4">Course Summary</h4>
            <div class="flex items-start gap-4 mb-6 pb-6 border-b border-slate-50">
              <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center shrink-0 text-indigo-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
              </div>
              <div class="min-w-0 flex-1">
                <h5 class="text-xs font-bold text-slate-900 truncate">{{ newCourseForm.title || 'New Course' }}</h5>
                <p class="text-[11px] font-mono text-indigo-600 mt-0.5">{{ newCourseForm.code || 'CODE' }}</p>
              </div>
            </div>

            <div class="space-y-3 text-xs">
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Department</span>
                <span class="font-bold text-slate-700 truncate max-w-[150px]">
                  {{ allDepartments.find(d => String(d.id) === String(newCourseForm.department_id))?.name || 'Not Set' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Credits</span>
                <span class="font-bold text-slate-700">{{ newCourseForm.credits }} CR</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Level</span>
                <span class="font-bold text-slate-700">{{ newCourseForm.level }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Semester</span>
                <span class="font-bold text-slate-700">{{ newCourseForm.semester }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Status</span>
                <span class="font-bold text-emerald-700 capitalize">{{ newCourseForm.status }}</span>
              </div>
            </div>
          </div>

          <div class="bg-indigo-50/70 border border-indigo-100 rounded-2xl p-5 text-xs text-indigo-900 space-y-2">
            <h5 class="font-bold uppercase tracking-wider text-[11px] text-indigo-700">Course Features Enabled</h5>
            <div class="space-y-1.5 pt-1 text-slate-700">
              <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Online Examination Ready</div>
              <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Question Bank Management</div>
              <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Student Grade Submissions</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 4. MODALS (Edit, Delete, Assign, Import, Export)                        -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <!-- Edit Course Modal -->
      <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 border border-slate-100">
          <div class="flex items-center justify-between px-6 py-4.5 border-b border-slate-100 shrink-0">
            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Edit Course: {{ selectedCourse?.name }}</h3>
            <button @click="showEditModal = false" class="p-1 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="p-6 overflow-y-auto space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Course Code <span class="text-rose-500">*</span></label>
                <input v-model="newCourseForm.code" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Course Title <span class="text-rose-500">*</span></label>
                <input v-model="newCourseForm.title" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Department <span class="text-rose-500">*</span></label>
                <select v-model="newCourseForm.department_id" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option v-for="d in allDepartments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Credit Hours <span class="text-rose-500">*</span></label>
                <input v-model="newCourseForm.credits" type="number" min="1" max="30" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Year Level</label>
                <select v-model="newCourseForm.level" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option v-for="lvl in academicYearLevels" :key="lvl" :value="lvl">{{ lvl }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Semester</label>
                <select v-model="newCourseForm.semester" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option v-for="sem in semesterOptions" :key="sem" :value="sem">{{ sem }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                <select v-model="newCourseForm.status" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Enrollment Status</label>
                <select v-model="newCourseForm.enrollment_status" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="Open for Enrollment">Open for Enrollment</option>
                  <option value="Closed">Closed</option>
                </select>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50 shrink-0">
            <button @click="showEditModal = false" class="px-5 py-2 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="saveCourse" :disabled="isLoading" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-xs disabled:opacity-50">
              {{ isLoading ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Assign Instructors Modal -->
      <div v-if="showAssignModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 border border-slate-100">
          <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900">Assign Course Faculty</h3>
                <p class="text-xs text-slate-500">{{ courseToAssign?.name }} ({{ courseToAssign?.code }})</p>
              </div>
            </div>

            <div class="space-y-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Section Cohort</label>
                <select v-model="assignSection" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 focus:outline-none focus:border-indigo-600 bg-white">
                  <option v-for="sec in sectionOptions" :key="sec" :value="sec">{{ sec }}</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Primary Course Instructor</label>
                <select v-model="assignInstructorId" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="">Unassigned</option>
                  <option v-for="inst in availableInstructors" :key="inst.id" :value="String(inst.id)">{{ inst.name }} ({{ inst.department?.name || 'Faculty' }})</option>
                </select>
                <p v-if="currentInstructorInfo" class="mt-1 text-[11px] text-slate-400 italic">{{ currentInstructorInfo }}</p>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Co-Instructor (Section B / Optional)</label>
                <select v-model="assignCoInstructorId" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="">None / Single Instructor</option>
                  <option v-for="inst in availableCoInstructors" :key="inst.id" :value="String(inst.id)">{{ inst.name }}</option>
                </select>
                <p v-if="currentCoInstructorInfo" class="mt-1 text-[11px] text-slate-400 italic">{{ currentCoInstructorInfo }}</p>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50">
            <button @click="showAssignModal = false" class="px-5 py-2 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="assignInstructor" :disabled="isLoading" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-xs disabled:opacity-50">
              {{ isLoading ? 'Assigning...' : 'Save Assignments' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Delete Course Modal -->
      <div v-if="showDeleteModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 border border-slate-100">
          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-rose-100">
              <svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 mb-2">Remove Course?</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
              Are you sure you want to delete <strong class="text-slate-800">{{ selectedCourse?.name }}</strong> (<span class="font-mono text-slate-700">{{ selectedCourse?.code }}</span>)? This action will remove course allocations.
            </p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="deleteCourse" :disabled="isLoading" class="flex-1 py-2.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors shadow-xs disabled:opacity-50">
              {{ isLoading ? 'Removing...' : 'Confirm Delete' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Import Modal -->
      <div v-if="showImportModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col animate-in fade-in zoom-in-95 border border-slate-100">
          <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
              </div>
              <div>
                <h3 class="text-base font-bold text-slate-800">Batch Import Courses</h3>
                <p class="text-xs text-slate-500">Upload a CSV or PDF file to register curriculum courses.</p>
              </div>
            </div>
            <button @click="showImportModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="p-6 space-y-4">
            <div class="p-4 bg-indigo-50/70 border border-indigo-100 rounded-xl space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">Template & Required Columns</span>
                <button @click="downloadSampleCsv" class="px-2.5 py-1 bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 font-bold text-xs rounded-lg transition-colors shadow-2xs">
                  Download Sample CSV
                </button>
              </div>
              <p class="text-xs text-slate-600">
                Required columns: <strong class="text-slate-900">Course Code, Course Title, Department, Academic Year Level, Credits</strong>.
              </p>
            </div>

            <div
              class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-2"
              :class="importDragOver ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:border-indigo-600 hover:bg-slate-50/50'"
              @dragover.prevent="importDragOver = true"
              @dragleave.prevent="importDragOver = false"
              @drop.prevent="onFileDrop"
              @click="modalFileInput?.click()"
            >
              <input type="file" ref="modalFileInput" class="hidden" accept=".csv,.pdf,text/csv,application/pdf" @change="onModalFileSelect" />
              <template v-if="selectedImportFile">
                <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-black text-xs uppercase">
                  {{ selectedImportFile.name.split('.').pop() }}
                </div>
                <div>
                  <p class="text-xs font-bold text-slate-800">{{ selectedImportFile.name }}</p>
                  <p class="text-[11px] text-slate-400">{{ (selectedImportFile.size / 1024).toFixed(1) }} KB</p>
                </div>
                <button @click.stop="selectedImportFile = null" class="text-[11px] font-bold text-rose-600 hover:underline">
                  Choose a different file
                </button>
              </template>
              <template v-else>
                <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center mb-1">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                </div>
                <p class="text-xs font-bold text-slate-800">Click to browse or drag and drop file here</p>
                <p class="text-[11px] text-slate-400">Supported formats: CSV (.csv) or PDF (.pdf)</p>
              </template>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50">
            <button @click="showImportModal = false" class="px-5 py-2 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button
              @click="executeImport"
              :disabled="!selectedImportFile || isImporting"
              class="flex items-center gap-2 px-6 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-xs disabled:opacity-50"
            >
              <svg v-if="isImporting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              <span>{{ isImporting ? 'Importing Courses...' : 'Upload & Import' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Import Error Modal -->
      <div v-if="showImportErrorModal" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-rose-200 animate-in fade-in zoom-in-95">
          <div class="bg-rose-50 px-6 py-5 border-b border-rose-100 flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </div>
            <div class="flex-1">
              <h3 class="text-sm font-bold text-rose-900">Import Format Issues Detected</h3>
              <p class="text-xs text-rose-700 mt-0.5">{{ importErrorMessage }}</p>
            </div>
            <button @click="showImportErrorModal = false" class="text-rose-400 hover:text-rose-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="p-6 space-y-3 max-h-[50vh] overflow-y-auto">
            <p class="text-xs font-bold text-slate-700 uppercase tracking-wide">Detected Issues:</p>
            <div class="space-y-1.5 bg-slate-50 border border-slate-200 rounded-xl p-3.5">
              <div v-for="(err, idx) in importErrorList" :key="idx" class="flex items-start gap-2 text-xs text-slate-700">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0 mt-1.5"></span>
                <span>{{ err }}</span>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
            <button @click="downloadSampleCsv" class="text-xs font-bold text-indigo-700 hover:underline">
              Download Valid Template (.CSV)
            </button>
            <button @click="showImportErrorModal = false; showImportModal = true" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors">
              Try Again
            </button>
          </div>
        </div>
      </div>

      <!-- Import Success Modal -->
      <div v-if="showImportSuccessModal" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-emerald-200 animate-in fade-in zoom-in-95">
          <div class="p-6 text-center space-y-4">
            <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto shadow-2xs">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-800">Courses Imported Successfully!</h3>
              <p class="text-xs text-slate-500 mt-1">{{ importSuccessMessage }}</p>
            </div>
            <div v-if="importedCoursesList.length > 0" class="max-h-40 overflow-y-auto border border-slate-100 rounded-xl divide-y divide-slate-100 text-left">
              <div v-for="c in importedCoursesList" :key="c.id" class="p-2.5 flex items-center justify-between bg-slate-50/50 text-xs">
                <div>
                  <p class="font-bold text-slate-800">{{ c.title || c.name }}</p>
                  <p class="text-[10px] text-slate-400 font-mono">{{ c.code }}</p>
                </div>
                <span class="px-2 py-0.5 font-bold rounded bg-indigo-50 text-indigo-700 text-[10px]">
                  {{ c.credits }} CR
                </span>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end bg-slate-50/50">
            <button @click="showImportSuccessModal = false" class="w-full sm:w-auto px-6 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors">
              Done & View Courses
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(-12px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}
</style>
