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
const sectionFilter = ref('all')
const yearFilter = ref('all')
const semesterFilter = ref('all')
const dateFilter = ref<'all' | '7_days' | '30_days' | '90_days'>('all')

const academicYearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year']
const sections = ['Section A', 'Section B', 'Section C', 'Section D']

// ── Sorting State ─────────────────────────────────────────────────────────────
type SortField = 'name' | 'id_no' | 'department' | 'status' | 'year' | 'joined' | 'email'
const sortField = ref<SortField>('name')
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
  email: true,
  department: true,
  yearSection: true,
  status: true,
  joined: true,
  lastLogin: true,
  phone: false,
})

// ── Pagination ────────────────────────────────────────────────────────────────
const currentPage = ref(1)
const perPage = ref(10)

// ── View Management ───────────────────────────────────────────────────────────
const showAddPage = ref(false)
const showDeleteModal = ref(false)
const showEditModal = ref(false)
const editStudentForm = ref<any>({})
const viewingStudent = ref<any>(null)
const selectedStudent = ref<any>(null)
const isLoading = ref(true)
const isRefreshing = ref(false)
const lastRefreshedAt = ref<Date>(new Date())

// ── Raw Data ──────────────────────────────────────────────────────────────────
const allStudents = ref<any[]>([])
const allDepartments = ref<any[]>([])
const backendStats = ref<{
  total: number
  active: number
  inactive: number
  new_students?: number
  new_instructors?: number
  growth?: { rate: number; formatted: string; trend: 'up' | 'down' } | null
} | null>(null)

// ── Add Student Form State ────────────────────────────────────────────────────
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const newStudent = ref({
  name: '',
  email: '',
  phone: '',
  gender: '',
  dob: '',
  department_id: '',
  year_level: '1st Year',
  section: 'Section A',
  semester: '',
  studentId: '',
  admissionNumber: '',
  username: '',
  password: '',
  confirmPassword: '',
  profilePicture: null as File | null,
})

const formErrors = ref({
  name: '',
  email: '',
  phone: '',
  gender: '',
  department_id: '',
  year_level: '',
  section: '',
  studentId: '',
  username: '',
  password: '',
  confirmPassword: '',
  dob: '',
})

const isSubmitted = ref(false)

const passwordCriteria = computed(() => {
  const pwd = newStudent.value.password || ''
  return {
    length: pwd.length >= 8,
    uppercase: /[A-Z]/.test(pwd),
    lowercase: /[a-z]/.test(pwd),
    number: /[0-9]/.test(pwd),
    special: /[!@#$%^&*(),.?":{}|<>_\-]/.test(pwd),
  }
})

const isPasswordValid = computed(() => {
  const c = passwordCriteria.value
  return c.length && c.uppercase && c.lowercase && c.number && c.special
})

const validateField = (field: keyof typeof formErrors.value) => {
  switch (field) {
    case 'name':
      if (!newStudent.value.name.trim()) {
        formErrors.value.name = 'Full Name is required.'
      } else if (newStudent.value.name.trim().length < 2) {
        formErrors.value.name = 'Full Name must be at least 2 characters.'
      } else {
        formErrors.value.name = ''
      }
      break

    case 'email':
      if (!newStudent.value.email.trim()) {
        formErrors.value.email = 'Email address is required.'
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newStudent.value.email.trim())) {
        formErrors.value.email = 'Please provide a valid institutional email address.'
      } else {
        formErrors.value.email = ''
      }
      break

    case 'phone':
      if (newStudent.value.phone.trim()) {
        const cleaned = newStudent.value.phone.replace(/[\s\-().+]/g, '')
        if (!/^\d{9,15}$/.test(cleaned)) {
          formErrors.value.phone = 'Phone number must be between 9 and 15 digits.'
        } else {
          formErrors.value.phone = ''
        }
      } else {
        formErrors.value.phone = ''
      }
      break

    case 'gender':
      formErrors.value.gender = newStudent.value.gender ? '' : 'Please select gender.'
      break

    case 'department_id':
      formErrors.value.department_id = newStudent.value.department_id ? '' : 'Please select an academic department.'
      break

    case 'year_level':
      formErrors.value.year_level = newStudent.value.year_level ? '' : 'Please select academic year level.'
      break

    case 'section':
      formErrors.value.section = newStudent.value.section ? '' : 'Please select section.'
      break

    case 'studentId':
      if (newStudent.value.studentId.trim() && newStudent.value.studentId.trim().length > 30) {
        formErrors.value.studentId = 'Student ID cannot exceed 30 characters.'
      } else {
        formErrors.value.studentId = ''
      }
      break

    case 'dob':
      if (newStudent.value.dob) {
        const d = new Date(newStudent.value.dob)
        const now = new Date()
        if (d >= now) {
          formErrors.value.dob = 'Date of birth must be in the past.'
        } else {
          formErrors.value.dob = ''
        }
      } else {
        formErrors.value.dob = ''
      }
      break

    case 'username':
      if (newStudent.value.username.trim() && !/^[a-zA-Z0-9_.-]+$/.test(newStudent.value.username.trim())) {
        formErrors.value.username = 'Username may only contain letters, numbers, underscores, and hyphens.'
      } else {
        formErrors.value.username = ''
      }
      break

    case 'password':
      if (!newStudent.value.password) {
        formErrors.value.password = 'Password is required.'
      } else if (!isPasswordValid.value) {
        formErrors.value.password = 'Password does not satisfy all institutional security criteria.'
      } else {
        formErrors.value.password = ''
      }
      if (newStudent.value.confirmPassword) validateField('confirmPassword')
      break

    case 'confirmPassword':
      if (!newStudent.value.confirmPassword) {
        formErrors.value.confirmPassword = 'Confirmation password is required.'
      } else if (newStudent.value.confirmPassword !== newStudent.value.password) {
        formErrors.value.confirmPassword = 'Passwords do not match.'
      } else {
        formErrors.value.confirmPassword = ''
      }
      break
  }
}

const validateAll = () => {
  isSubmitted.value = true
  const fields: (keyof typeof formErrors.value)[] = [
    'name', 'email', 'phone', 'gender', 'department_id',
    'year_level', 'section', 'password', 'confirmPassword'
  ]
  fields.forEach(f => validateField(f))
  return Object.values(formErrors.value).every(e => e === '')
}

const previewUrl = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleProfilePicture = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    showToast('Please upload a valid image file (PNG, JPG, WEBP).', 'error')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    showToast('Profile image must not exceed 2MB in size.', 'error')
    return
  }

  newStudent.value.profilePicture = file
  previewUrl.value = URL.createObjectURL(file)
}

// ── Edit Profile Picture ──────────────────────────────────────────────────────
const editPreviewUrl = ref<string | null>(null)
const editFileInput = ref<HTMLInputElement | null>(null)

const triggerEditFileInput = () => {
  editFileInput.value?.click()
}

const handleEditProfilePicture = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    showToast('Please upload a valid image file (PNG, JPG, WEBP).', 'error')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    showToast('Profile image must not exceed 2MB in size.', 'error')
    return
  }

  editStudentForm.value.profilePicture = file
  editPreviewUrl.value = URL.createObjectURL(file)
}

// ── Fetch Students & Departments ──────────────────────────────────────────────
const fetchStudents = async (silent = false) => {
  if (!silent) isLoading.value = true
  try {
    const res = await apiClient.get('/admin/users?role=student')
    backendStats.value = res.data.stats || null
    allStudents.value = (res.data.data || []).map((u: any) => ({
      ...u,
      id: u.id,
      name: u.name,
      email: u.email,
      username: u.username || '—',
      phone: u.phone || '—',
      gender: u.gender || '—',
      dob: u.dob || '',
      avatar: u.name ? u.name.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2) : 'ST',
      profilePicture: u.profile_picture_url || (u.profile_picture ? `http://localhost:8000/storage/${u.profile_picture}` : null),
      status: u.status || 'active',
      departmentName: u.department?.name || 'Unassigned',
      department_id: u.department_id,
      year: u.year_level || '1st Year',
      year_level: u.year_level || '1st Year',
      academic_year: u.academic_year || '2025/2026',
      semester: u.semester || settingsStore.semester || '1st Semester',
      section: u.section ? (u.section.startsWith('Section ') ? u.section : `Section ${u.section}`) : 'Section A',
      id_no: u.id_no || `WU/${u.id.toString().padStart(4, '0')}/26`,
      admission_number: u.admission_number || `ADM-${u.id.toString().padStart(5, '0')}`,
      joined: u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : '—',
      lastLogin: u.updated_at ? new Date(u.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—',
    }))
    lastRefreshedAt.value = new Date()
  } catch (err) {
    console.error('Failed to load students:', err)
    showToast('Failed to load student records from the database.', 'error')
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
    console.error('Failed to load departments:', err)
  }
}

const refreshData = async () => {
  isRefreshing.value = true
  await Promise.all([fetchStudents(true), fetchDepartments()])
  showToast('Student directory and metrics refreshed successfully.', 'success')
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
  await Promise.all([fetchStudents(), fetchDepartments()])
})

onUnmounted(() => {
  window.removeEventListener('click', closeAllDropdowns)
})

// ── Computed Stats (Strict Real Database Source) ──────────────────────────────
const stats = computed(() => {
  const total = backendStats.value?.total ?? allStudents.value.length
  const active = backendStats.value?.active ?? allStudents.value.filter(s => s.status === 'active').length
  const inactive = backendStats.value?.inactive ?? allStudents.value.filter(s => s.status === 'inactive' || s.status === 'suspended').length
  const newStudents = backendStats.value?.new_students ?? backendStats.value?.new_instructors ?? allStudents.value.filter(s => {
    if (!s.created_at) return false
    const d = new Date(s.created_at)
    const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)
    return d >= thirtyDaysAgo
  }).length
  const growth = backendStats.value?.growth ?? null
  return { total, active, inactive, newStudents, growth }
})

// ── Interactive KPI Click Handler ─────────────────────────────────────────────
const handleKpiClick = (type: 'total' | 'active' | 'inactive' | 'new') => {
  if (type === 'total') {
    statusFilter.value = 'all'
    dateFilter.value = 'all'
  } else if (type === 'active') {
    statusFilter.value = 'active'
    dateFilter.value = 'all'
  } else if (type === 'inactive') {
    statusFilter.value = 'inactive'
    dateFilter.value = 'all'
  } else if (type === 'new') {
    statusFilter.value = 'all'
    dateFilter.value = '30_days'
  }
  currentPage.value = 1
}

// ── Filtering Logic ───────────────────────────────────────────────────────────
const filtered = computed(() => {
  return allStudents.value.filter(student => {
    // 1. Search Debounced
    if (debouncedSearch.value) {
      const q = debouncedSearch.value
      const matchName = student.name?.toLowerCase().includes(q)
      const matchEmail = student.email?.toLowerCase().includes(q)
      const matchId = student.id_no?.toLowerCase().includes(q)
      const matchUsername = student.username?.toLowerCase().includes(q)
      const matchDept = student.departmentName?.toLowerCase().includes(q)
      if (!matchName && !matchEmail && !matchId && !matchUsername && !matchDept) {
        return false
      }
    }

    // 2. Department Filter
    if (deptFilter.value !== 'all') {
      if (student.departmentName !== deptFilter.value && String(student.department_id) !== String(deptFilter.value)) {
        return false
      }
    }

    // 3. Status Filter
    if (statusFilter.value !== 'all') {
      if (student.status !== statusFilter.value) {
        return false
      }
    }

    // 4. Section Filter
    if (sectionFilter.value !== 'all') {
      const secNorm = sectionFilter.value.replace('Section ', '').trim()
      const studentSecNorm = (student.section || '').replace('Section ', '').trim()
      if (studentSecNorm !== secNorm) {
        return false
      }
    }

    // 5. Academic Year Level Filter
    if (yearFilter.value !== 'all') {
      if (student.year_level !== yearFilter.value && student.year !== yearFilter.value) {
        return false
      }
    }

    // 6. Semester Filter
    if (semesterFilter.value !== 'all') {
      if (student.semester !== semesterFilter.value) {
        return false
      }
    }

    // 7. Date Filter
    if (dateFilter.value !== 'all' && student.created_at) {
      const created = new Date(student.created_at).getTime()
      const now = Date.now()
      const daysDiff = (now - created) / (1000 * 60 * 60 * 24)
      if (dateFilter.value === '7_days' && daysDiff > 7) return false
      if (dateFilter.value === '30_days' && daysDiff > 30) return false
      if (dateFilter.value === '90_days' && daysDiff > 90) return false
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
      case 'name':
        return dir * (a.name || '').localeCompare(b.name || '')
      case 'id_no':
        return dir * (a.id_no || '').localeCompare(b.id_no || '')
      case 'email':
        return dir * (a.email || '').localeCompare(b.email || '')
      case 'department':
        return dir * (a.departmentName || '').localeCompare(b.departmentName || '')
      case 'status':
        return dir * (a.status || '').localeCompare(b.status || '')
      case 'year':
        return dir * (a.year_level || '').localeCompare(b.year_level || '')
      case 'joined':
        return dir * ((new Date(a.created_at || 0).getTime()) - (new Date(b.created_at || 0).getTime()))
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

watch([debouncedSearch, deptFilter, statusFilter, sectionFilter, yearFilter, semesterFilter, dateFilter], () => {
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
  if (sectionFilter.value !== 'all') {
    chips.push({
      id: 'section',
      label: `Section: ${sectionFilter.value}`,
      clear: () => { sectionFilter.value = 'all' },
    })
  }
  if (yearFilter.value !== 'all') {
    chips.push({
      id: 'year',
      label: `Year: ${yearFilter.value}`,
      clear: () => { yearFilter.value = 'all' },
    })
  }
  if (semesterFilter.value !== 'all') {
    chips.push({
      id: 'semester',
      label: `Semester: ${semesterFilter.value}`,
      clear: () => { semesterFilter.value = 'all' },
    })
  }
  if (dateFilter.value !== 'all') {
    const labels: Record<string, string> = {
      '7_days': 'Last 7 Days',
      '30_days': 'Last 30 Days',
      '90_days': 'Last 90 Days',
    }
    chips.push({
      id: 'date',
      label: `Joined: ${labels[dateFilter.value] || dateFilter.value}`,
      clear: () => { dateFilter.value = 'all' },
    })
  }
  return chips
})

const clearAllFilters = () => {
  clearSearch()
  deptFilter.value = 'all'
  statusFilter.value = 'all'
  sectionFilter.value = 'all'
  yearFilter.value = 'all'
  semesterFilter.value = 'all'
  dateFilter.value = 'all'
  currentPage.value = 1
}

// ── Views & Dialogs Handlers ──────────────────────────────────────────────────
const openAdd = () => {
  showAddPage.value = true
  viewingStudent.value = null
  previewUrl.value = null
  isSubmitted.value = false
  newStudent.value = {
    name: '',
    email: '',
    phone: '',
    gender: '',
    dob: '',
    department_id: allDepartments.value[0]?.id ? String(allDepartments.value[0].id) : '',
    year_level: '1st Year',
    section: 'Section A',
    semester: settingsStore.semester || '1st Semester',
    studentId: '',
    admissionNumber: '',
    username: '',
    password: '',
    confirmPassword: '',
    profilePicture: null,
  }
  Object.keys(formErrors.value).forEach(k => {
    (formErrors.value as any)[k] = ''
  })
}

const closeAdd = () => {
  showAddPage.value = false
}

const viewStudent = (student: any) => {
  viewingStudent.value = student
  showAddPage.value = false
}

const closeView = () => {
  viewingStudent.value = null
}

const openEdit = (student: any) => {
  editStudentForm.value = {
    id: student.id,
    name: student.name || '',
    email: student.email || '',
    phone: student.phone === '—' ? '' : (student.phone || ''),
    gender: student.gender === '—' ? '' : (student.gender || ''),
    dob: student.dob || '',
    department_id: student.department_id ? String(student.department_id) : '',
    year_level: student.year_level || student.year || '1st Year',
    section: student.section || 'Section A',
    semester: student.semester || settingsStore.semester || '1st Semester',
    studentId: student.id_no || '',
    admissionNumber: student.admission_number || '',
    username: student.username === '—' ? '' : (student.username || ''),
    status: student.status || 'active',
    password: '',
    profilePicture: null as File | null,
  }
  editPreviewUrl.value = student.profilePicture || null
  showEditModal.value = true
}

const saveEdit = async () => {
  if (!editStudentForm.value.name.trim() || !editStudentForm.value.email.trim()) {
    showToast('Name and Email are required.', 'error')
    return
  }

  isLoading.value = true
  try {
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('name', editStudentForm.value.name.trim())
    formData.append('email', editStudentForm.value.email.trim().toLowerCase())
    formData.append('role', 'student')

    if (editStudentForm.value.username) formData.append('username', editStudentForm.value.username.trim())
    if (editStudentForm.value.phone) formData.append('phone', editStudentForm.value.phone.trim())
    if (editStudentForm.value.gender) formData.append('gender', editStudentForm.value.gender)
    if (editStudentForm.value.dob) formData.append('dob', editStudentForm.value.dob)
    if (editStudentForm.value.department_id) formData.append('department_id', editStudentForm.value.department_id)
    if (editStudentForm.value.year_level) formData.append('year_level', editStudentForm.value.year_level)
    if (editStudentForm.value.section) formData.append('section', editStudentForm.value.section)
    if (editStudentForm.value.semester) formData.append('semester', editStudentForm.value.semester)
    if (editStudentForm.value.studentId) formData.append('id_no', editStudentForm.value.studentId.trim())
    if (editStudentForm.value.status) formData.append('status', editStudentForm.value.status)
    if (editStudentForm.value.password) formData.append('password', editStudentForm.value.password)

    if (editStudentForm.value.profilePicture instanceof File) {
      formData.append('profile_picture', editStudentForm.value.profilePicture)
    }

    await apiClient.post(`/admin/users/${editStudentForm.value.id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    await fetchStudents(true)
    showEditModal.value = false
    showToast('Student record updated successfully.', 'success')

    if (viewingStudent.value?.id === editStudentForm.value.id) {
      const updated = allStudents.value.find(s => s.id === editStudentForm.value.id)
      if (updated) viewingStudent.value = { ...updated }
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to update student record.'
    showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

// ── Add Student Action ────────────────────────────────────────────────────────
const addStudent = async () => {
  if (!validateAll()) {
    const firstErrorKey = Object.keys(formErrors.value).find(k => (formErrors.value as any)[k] !== '')
    if (firstErrorKey) {
      const el = document.getElementById(`field-${firstErrorKey}`)
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
    return
  }

  isLoading.value = true
  try {
    const formData = new FormData()
    const studentName = newStudent.value.name.trim()
    formData.append('name', studentName)
    formData.append('email', newStudent.value.email.trim().toLowerCase())
    formData.append('role', 'student')
    formData.append('password', newStudent.value.password)

    if (newStudent.value.username) formData.append('username', newStudent.value.username.trim())
    if (newStudent.value.phone) formData.append('phone', newStudent.value.phone.trim())
    if (newStudent.value.gender) formData.append('gender', newStudent.value.gender)
    if (newStudent.value.dob) formData.append('dob', newStudent.value.dob)
    if (newStudent.value.department_id) formData.append('department_id', newStudent.value.department_id)
    if (newStudent.value.year_level) formData.append('year_level', newStudent.value.year_level)
    if (newStudent.value.section) formData.append('section', newStudent.value.section)
    if (newStudent.value.semester) formData.append('semester', newStudent.value.semester)
    if (newStudent.value.studentId) formData.append('id_no', newStudent.value.studentId.trim())
    if (newStudent.value.profilePicture) {
      formData.append('profile_picture', newStudent.value.profilePicture)
    }

    await apiClient.post('/admin/users', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    await fetchStudents(true)
    showAddPage.value = false
    showToast(`Student "${studentName}" registered successfully!`, 'success')
  } catch (err: any) {
    const errs = err.response?.data?.errors
    if (errs) {
      if (errs.email) formErrors.value.email = errs.email[0]
      if (errs.name) formErrors.value.name = errs.name[0]
      if (errs.phone) formErrors.value.phone = errs.phone[0]
      if (errs.username) formErrors.value.username = errs.username[0]
      if (errs.department_id) formErrors.value.department_id = errs.department_id[0]
      if (errs.id_no) formErrors.value.studentId = errs.id_no[0]
      if (errs.password) formErrors.value.password = errs.password[0]
    }
    const msg = err.response?.data?.message || 'Failed to create student.'
    showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

// ── Delete Student ────────────────────────────────────────────────────────────
const confirmDelete = (student: any) => {
  selectedStudent.value = student
  showDeleteModal.value = true
}

const deleteStudent = async () => {
  if (!selectedStudent.value) return
  isLoading.value = true
  try {
    await apiClient.delete(`/admin/users/${selectedStudent.value.id}`)
    await fetchStudents(true)
    if (viewingStudent.value?.id === selectedStudent.value.id) {
      viewingStudent.value = null
    }
    showDeleteModal.value = false
    showToast(`Student "${selectedStudent.value.name}" removed successfully.`, 'success')
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to remove student.'
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
    const params: Record<string, string> = {
      role: 'student',
      format,
    }
    if (deptFilter.value !== 'all') params.department = deptFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (yearFilter.value !== 'all') params.year = yearFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (debouncedSearch.value) params.search = debouncedSearch.value

    const token = localStorage.getItem('auth_token')
    const queryString = new URLSearchParams(params).toString()

    const response = await fetch(`http://localhost:8000/api/v1/admin/users-export?${queryString}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      }
    })

    if (!response.ok) {
      const text = await response.text()
      try {
        const json = JSON.parse(text)
        showToast(json.message || 'Export failed.', 'error')
      } catch {
        showToast('Export failed with server response error.', 'error')
      }
      return
    }

    const data = await response.json()
    if (!data.file || !data.filename) throw new Error('Invalid export format')

    const binary = atob(data.file)
    const array = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i++) array[i] = binary.charCodeAt(i)

    const blob = new Blob([array], { type: format === 'pdf' ? 'application/pdf' : 'text/csv' })
    const blobUrl = window.URL.createObjectURL(blob)

    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', data.filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)

    showToast(`Students exported successfully as ${format.toUpperCase()}.`, 'success')
  } catch (err) {
    showToast('Failed to export students. Please try again.', 'error')
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
const importedStudentsList = ref<any[]>([])

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
      importErrorList.value = ['Only .csv and .pdf file formats are supported.']
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
    formData.append('role', 'student')

    const res = await apiClient.post('/admin/users-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    showImportModal.value = false
    selectedImportFile.value = null
    importSuccessMessage.value = res.data.message || 'Successfully imported students.'
    importedStudentsList.value = res.data.students || []
    showImportSuccessModal.value = true
    await fetchStudents(true)
  } catch (err: any) {
    showImportModal.value = false
    const data = err.response?.data
    importErrorMessage.value = data?.message || 'Failed to import students.'
    importErrorList.value = Array.isArray(data?.errors)
      ? data.errors
      : (data?.message ? [data.message] : ['An unexpected error occurred during file import.'])
    showImportErrorModal.value = true
  } finally {
    isImporting.value = false
    if (modalFileInput.value) modalFileInput.value.value = ''
  }
}

const downloadSampleCsv = () => {
  const dept1 = allDepartments.value[0]?.name || 'Software Engineering'
  const dept2 = allDepartments.value[1]?.name || 'Computer Science'
  const headers = ['Full Name', 'Email Address', 'Department', 'Academic Year Level', 'Student ID', 'Phone Number', 'Gender', 'Section', 'Semester', 'Password']
  const row1 = ['Abebe Bikila', 'abebe.b@wu.edu.et', dept1, '1st Year', 'WU/0101/26', '0911223344', 'Male', 'Section A', '1st Semester', 'Password123!']
  const row2 = ['Tigist Assefa', 'tigist.a@wu.edu.et', dept2, '2nd Year', 'WU/0102/26', '0922334455', 'Female', 'Section B', '1st Semester', 'Password123!']
  const csvContent = [headers.join(','), row1.join(','), row2.join(',')].join('\n')
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'wollo_university_student_template.csv'
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

// ── Level Counts & Overview Donut Chart ───────────────────────────────────────
const levelCounts = computed(() => {
  const counts: Record<string, number> = {
    '1st Year': 0,
    '2nd Year': 0,
    '3rd Year': 0,
    '4th Year': 0,
    '5th Year': 0,
  }
  allStudents.value.forEach(s => {
    const y = s.year_level || s.year || '1st Year'
    if (counts[y] !== undefined) counts[y]++
    else counts['1st Year']++
  })
  return counts
})

const chartData = computed(() => ({
  labels: ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],
  datasets: [{
    backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#06b6d4', '#ec4899'],
    data: [
      levelCounts.value['1st Year'],
      levelCounts.value['2nd Year'],
      levelCounts.value['3rd Year'],
      levelCounts.value['4th Year'],
      levelCounts.value['5th Year'],
    ],
    borderWidth: 0,
    hoverOffset: 4,
  }]
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '74%',
  plugins: { legend: { display: false }, tooltip: { enabled: true } },
}

const topDepartments = computed(() => {
  if (allDepartments.value && allDepartments.value.length > 0) {
    const list = allDepartments.value.map(d => {
      const count = allStudents.value.filter(s => s.department_id === d.id || s.departmentName === d.name).length
      return { id: d.id, name: d.name, count }
    })
    return list.sort((a, b) => b.count - a.count).slice(0, 5)
  }
  const deptCounts: Record<string, number> = {}
  allStudents.value.forEach(s => {
    const dName = s.departmentName !== '—' && s.departmentName !== 'Unassigned' ? s.departmentName : 'Unassigned'
    deptCounts[dName] = (deptCounts[dName] || 0) + 1
  })
  return Object.entries(deptCounts)
    .map(([name, count]) => ({ id: name, name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})

const deptIcons = ['💻', '⚙️', '📊', '🗄️', '🌐']

const recentRegistrations = computed(() => {
  return [...allStudents.value]
    .sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())
    .slice(0, 5)
})

// ── Avatar Background Helper ──────────────────────────────────────────────────
const avatarBg = (id: number) => {
  const colors = [
    'bg-indigo-50 text-indigo-700 border-indigo-200',
    'bg-sky-50 text-sky-700 border-sky-200',
    'bg-emerald-50 text-emerald-700 border-emerald-200',
    'bg-amber-50 text-amber-700 border-amber-200',
    'bg-rose-50 text-rose-700 border-rose-200',
    'bg-purple-50 text-purple-700 border-purple-200',
    'bg-teal-50 text-teal-700 border-teal-200',
  ]
  return colors[id % colors.length]
}
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
    <!-- 1. MAIN STUDENT LIST VIEW                                              -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="!viewingStudent && !showAddPage" class="space-y-6 pb-16 min-w-0 w-full">

      <!-- Institutional Command Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span>Administration</span>
            <span class="text-slate-300">/</span>
            <span>Academic</span>
            <span class="text-slate-300">/</span>
            <span class="text-indigo-600 font-bold">Students</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
            Student Management
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
              {{ stats.total }} Registered
            </span>
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Manage student records, enrollment credentials, departmental cohorts, and sections.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 flex-wrap sm:flex-nowrap">
          <!-- Live Refresh Button -->
          <button
            @click="refreshData"
            :disabled="isRefreshing"
            class="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs border border-slate-200 transition-colors shadow-xs disabled:opacity-50"
            title="Refresh student records and statistics"
          >
            <svg class="w-4 h-4 text-slate-500" :class="{ 'animate-spin': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span class="hidden sm:inline">Refresh</span>
          </button>

          <!-- Import Students -->
          <button
            @click="triggerImport"
            class="flex items-center gap-2 px-3.5 py-2.5 bg-white hover:bg-slate-50 text-indigo-700 font-bold rounded-xl text-xs border border-indigo-200 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <span>Import</span>
          </button>

          <!-- Export Students Dropdown -->
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

          <!-- Add Student (Primary CTA) -->
          <button
            @click="openAdd"
            class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-colors shadow-xs shadow-indigo-200"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add New Student</span>
          </button>
        </div>
      </div>

      <!-- ── KPI Cards (100% Real Database Values & Interactive Filtering) ── -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total -->
        <div
          @click="handleKpiClick('total')"
          class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs hover:border-indigo-300 hover:shadow-md transition-all cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Students</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.total }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">All enrolled students</span>
            <span class="text-indigo-600 font-bold group-hover:underline text-[11px]">Show all →</span>
          </div>
        </div>

        <!-- Active -->
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
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Active Students</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.active }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-emerald-600 font-bold flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              {{ stats.total > 0 ? Math.round((stats.active / stats.total) * 100) : 0 }}% of total
            </span>
            <span class="text-emerald-700 font-bold group-hover:underline text-[11px]">Filter Active →</span>
          </div>
        </div>

        <!-- Inactive -->
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
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Inactive / Suspended</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.inactive }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">Inactive accounts</span>
            <span class="text-rose-600 font-bold group-hover:underline text-[11px]">Filter Inactive →</span>
          </div>
        </div>

        <!-- New This Month -->
        <div
          @click="handleKpiClick('new')"
          class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs hover:border-sky-300 hover:shadow-md transition-all cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-11 h-11 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">New Students</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.newStudents }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span v-if="stats.growth" class="font-bold flex items-center gap-1" :class="stats.growth.trend === 'up' ? 'text-emerald-600' : 'text-rose-600'">
              {{ stats.growth.formatted }} vs prior month
            </span>
            <span v-else class="text-slate-500 font-medium">Last 30 days</span>
            <span class="text-sky-600 font-bold group-hover:underline text-[11px]">Show New →</span>
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
              placeholder="Search by name, student ID, email, department..."
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
              v-model="yearFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Year Levels</option>
              <option v-for="lvl in academicYearLevels" :key="lvl" :value="lvl">{{ lvl }}</option>
            </select>

            <!-- Section -->
            <select
              v-model="sectionFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Sections</option>
              <option v-for="sec in sections" :key="sec" :value="sec">{{ sec }}</option>
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

            <!-- Semester -->
            <select
              v-model="semesterFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Semesters</option>
              <option value="1st Semester">1st Semester</option>
              <option value="2nd Semester">2nd Semester</option>
            </select>

            <!-- Join Date Preset -->
            <select
              v-model="dateFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Join Dates</option>
              <option value="7_days">Last 7 Days</option>
              <option value="30_days">Last 30 Days</option>
              <option value="90_days">Last 90 Days</option>
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
                  <input type="checkbox" v-model="visibleColumns.email" class="rounded text-indigo-600" />
                  Email Address
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.department" class="rounded text-indigo-600" />
                  Department
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.yearSection" class="rounded text-indigo-600" />
                  Year & Section
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.status" class="rounded text-indigo-600" />
                  Status
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.joined" class="rounded text-indigo-600" />
                  Registered Date
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.lastLogin" class="rounded text-indigo-600" />
                  Last Activity
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.phone" class="rounded text-indigo-600" />
                  Phone Number
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

      <!-- ── Modern Enterprise Data Table ── -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs flex flex-col min-w-0 w-full overflow-hidden">
        
        <!-- Table Scroll Container -->
        <div class="overflow-x-auto min-w-0 flex-1 min-h-[360px]">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/70 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider select-none">
                <th @click="handleSort('name')" class="px-5 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Student</span>
                    <span v-if="sortField === 'name'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th @click="handleSort('id_no')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Student ID</span>
                    <span v-if="sortField === 'id_no'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.email" @click="handleSort('email')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Email Address</span>
                    <span v-if="sortField === 'email'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.department" @click="handleSort('department')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Department</span>
                    <span v-if="sortField === 'department'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.yearSection" class="px-4 py-3.5 whitespace-nowrap">Year & Section</th>
                <th v-if="visibleColumns.status" @click="handleSort('status')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Status</span>
                    <span v-if="sortField === 'status'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.joined" @click="handleSort('joined')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Registered</span>
                    <span v-if="sortField === 'joined'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.lastLogin" class="px-4 py-3.5 whitespace-nowrap">Last Activity</th>
                <th v-if="visibleColumns.phone" class="px-4 py-3.5 whitespace-nowrap">Phone</th>
                <th class="px-5 py-3.5 text-right whitespace-nowrap">Actions</th>
              </tr>
            </thead>

            <!-- Skeleton Loading State -->
            <tbody v-if="isLoading" class="divide-y divide-slate-50">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-slate-200 shrink-0"></div>
                    <div class="space-y-1.5">
                      <div class="h-3.5 w-28 bg-slate-200 rounded"></div>
                      <div class="h-2.5 w-20 bg-slate-100 rounded"></div>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3.5"><div class="h-4 w-20 bg-slate-100 rounded-md"></div></td>
                <td v-if="visibleColumns.email" class="px-4 py-3.5"><div class="h-3 w-32 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.department" class="px-4 py-3.5"><div class="h-3 w-28 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.yearSection" class="px-4 py-3.5"><div class="h-3 w-20 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.status" class="px-4 py-3.5"><div class="h-5 w-16 bg-slate-100 rounded-full"></div></td>
                <td v-if="visibleColumns.joined" class="px-4 py-3.5"><div class="h-3 w-20 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.lastLogin" class="px-4 py-3.5"><div class="h-3 w-14 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.phone" class="px-4 py-3.5"><div class="h-3 w-16 bg-slate-100 rounded"></div></td>
                <td class="px-5 py-3.5 text-right"><div class="h-6 w-16 bg-slate-100 rounded ml-auto"></div></td>
              </tr>
            </tbody>

            <!-- Real Student Rows -->
            <tbody v-else class="divide-y divide-slate-50">
              <tr
                v-for="(st, index) in paginated"
                :key="st.id"
                class="hover:bg-indigo-50/30 transition-colors group"
              >
                <!-- Student Avatar + Name + Username -->
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <!-- Real Profile Picture or Initials Avatar -->
                    <img
                      v-if="st.profilePicture"
                      :src="st.profilePicture"
                      :alt="st.name"
                      class="w-9 h-9 rounded-full object-cover shrink-0 border border-slate-200 shadow-2xs"
                    />
                    <div
                      v-else
                      :class="avatarBg(st.id)"
                      class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-black border shadow-2xs shrink-0 select-none"
                    >
                      {{ st.avatar }}
                    </div>
                    <div class="min-w-0">
                      <p
                        @click="viewStudent(st)"
                        class="text-xs font-bold text-slate-900 group-hover:text-indigo-700 transition-colors truncate cursor-pointer hover:underline"
                      >
                        {{ st.name }}
                      </p>
                      <p class="text-[11px] text-slate-400 font-medium truncate">
                        {{ st.username !== '—' ? '@' + st.username : st.email }}
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Student ID (Mono Badge) -->
                <td class="px-4 py-3.5 whitespace-nowrap">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 font-mono text-[11px] font-bold text-slate-700">
                    {{ st.id_no }}
                  </span>
                </td>

                <!-- Email -->
                <td v-if="visibleColumns.email" class="px-4 py-3.5 text-xs text-slate-600 font-medium whitespace-nowrap">
                  {{ st.email }}
                </td>

                <!-- Department -->
                <td v-if="visibleColumns.department" class="px-4 py-3.5 whitespace-nowrap">
                  <span class="text-xs font-semibold text-slate-700">
                    {{ st.departmentName }}
                  </span>
                </td>

                <!-- Year & Section -->
                <td v-if="visibleColumns.yearSection" class="px-4 py-3.5 text-xs text-slate-600 whitespace-nowrap">
                  <span class="font-semibold text-slate-800">{{ st.year_level }}</span>
                  <span class="text-slate-300 mx-1">•</span>
                  <span class="text-slate-500 font-medium">{{ st.section }}</span>
                </td>

                <!-- Status Badge -->
                <td v-if="visibleColumns.status" class="px-4 py-3.5 whitespace-nowrap">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-full capitalize border"
                    :class="{
                      'bg-emerald-50 text-emerald-700 border-emerald-200': st.status === 'active',
                      'bg-slate-100 text-slate-600 border-slate-200': st.status === 'inactive',
                      'bg-rose-50 text-rose-700 border-rose-200': st.status === 'suspended',
                    }"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full"
                      :class="{
                        'bg-emerald-500': st.status === 'active',
                        'bg-slate-400': st.status === 'inactive',
                        'bg-rose-500': st.status === 'suspended',
                      }"
                    ></span>
                    {{ st.status }}
                  </span>
                </td>

                <!-- Joined -->
                <td v-if="visibleColumns.joined" class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                  {{ st.joined }}
                </td>

                <!-- Last Login / Activity -->
                <td v-if="visibleColumns.lastLogin" class="px-4 py-3.5 text-xs text-slate-400 font-medium whitespace-nowrap">
                  {{ st.lastLogin }}
                </td>

                <!-- Phone -->
                <td v-if="visibleColumns.phone" class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                  {{ st.phone }}
                </td>

                <!-- Actions -->
                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                  <div class="relative inline-block text-left">
                    <button
                      @click.stop="toggleActionDropdown(st.id)"
                      type="button"
                      class="w-8 h-8 rounded-xl inline-flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-100 transition-all focus:outline-none"
                      :class="{ 'bg-indigo-50 text-indigo-700 border-indigo-200': activeActionDropdown === st.id }"
                      title="Student Actions"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                      </svg>
                    </button>

                    <!-- Action Dropdown Menu -->
                    <div
                      v-if="activeActionDropdown === st.id"
                      @click.stop
                      class="absolute right-0 w-44 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-50 select-none animate-in fade-in zoom-in-95 duration-100"
                      :class="index >= paginated.length - 2 && paginated.length > 2 ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
                    >
                      <!-- View Details -->
                      <button
                        @click="handleActionClick(() => viewStudent(st))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>View Details</span>
                      </button>

                      <!-- Edit Student -->
                      <button
                        @click="handleActionClick(() => openEdit(st))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span>Edit Student</span>
                      </button>

                      <div class="h-px bg-slate-100 my-1"></div>

                      <!-- Delete Student -->
                      <button
                        @click="handleActionClick(() => confirmDelete(st))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-rose-500 group-hover/item:text-rose-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Delete Student</span>
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
                    <p class="text-sm font-bold text-slate-800">No students found</p>
                    <p class="text-xs text-slate-500 mt-1">There are no students matching your current search or filter criteria.</p>
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
              Showing <strong class="text-slate-800">{{ filtered.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }}</strong> to <strong class="text-slate-800">{{ Math.min(currentPage * perPage, filtered.length) }}</strong> of <strong class="text-slate-800">{{ filtered.length }}</strong> students
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
        <!-- Academic Level Distribution Chart -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs">
          <h3 class="text-sm font-bold text-slate-900 mb-4">Year Level Breakdown</h3>
          <div class="relative w-40 h-40 mx-auto mb-4">
            <Doughnut :data="chartData" :options="chartOptions" />
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <span class="text-2xl font-black text-slate-800 leading-none">{{ stats.total }}</span>
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Students</span>
            </div>
          </div>
          <div class="space-y-1.5 text-xs">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-indigo-500"></div><span class="text-slate-600">1st Year</span></div>
              <span class="font-bold text-slate-800">{{ levelCounts['1st Year'] }} <span class="text-[11px] text-slate-400 font-normal">({{ stats.total ? Math.round((levelCounts['1st Year'] / stats.total) * 100) : 0 }}%)</span></span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div><span class="text-slate-600">2nd Year</span></div>
              <span class="font-bold text-slate-800">{{ levelCounts['2nd Year'] }} <span class="text-[11px] text-slate-400 font-normal">({{ stats.total ? Math.round((levelCounts['2nd Year'] / stats.total) * 100) : 0 }}%)</span></span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div><span class="text-slate-600">3rd Year</span></div>
              <span class="font-bold text-slate-800">{{ levelCounts['3rd Year'] }} <span class="text-[11px] text-slate-400 font-normal">({{ stats.total ? Math.round((levelCounts['3rd Year'] / stats.total) * 100) : 0 }}%)</span></span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-cyan-500"></div><span class="text-slate-600">4th Year</span></div>
              <span class="font-bold text-slate-800">{{ levelCounts['4th Year'] }} <span class="text-[11px] text-slate-400 font-normal">({{ stats.total ? Math.round((levelCounts['4th Year'] / stats.total) * 100) : 0 }}%)</span></span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-pink-500"></div><span class="text-slate-600">5th Year</span></div>
              <span class="font-bold text-slate-800">{{ levelCounts['5th Year'] }} <span class="text-[11px] text-slate-400 font-normal">({{ stats.total ? Math.round((levelCounts['5th Year'] / stats.total) * 100) : 0 }}%)</span></span>
            </div>
          </div>
        </div>

        <!-- Top Departments by Enrollment -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Top Departments</h3>
            <div class="space-y-2.5">
              <div v-for="(dept, i) in topDepartments" :key="dept.name" class="flex items-center justify-between text-xs p-1.5 rounded-lg hover:bg-slate-50 transition-colors">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="text-sm">{{ deptIcons[i] || '📁' }}</span>
                  <span class="font-semibold text-slate-700 truncate">{{ dept.name }}</span>
                </div>
                <span class="font-bold text-indigo-700 shrink-0">{{ dept.count }}</span>
              </div>
              <div v-if="topDepartments.length === 0" class="text-xs text-slate-400 text-center py-4">No student department enrollments yet</div>
            </div>
          </div>
          <button @click="router.push('/admin/departments')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 text-left mt-3">
            Manage All Departments →
          </button>
        </div>

        <!-- Recent Registrations -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Recent Onboarding</h3>
            <div class="space-y-3">
              <div v-for="st in recentRegistrations" :key="st.id" class="flex items-center gap-3">
                <div :class="avatarBg(st.id)" class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 border">
                  {{ st.avatar }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-bold text-slate-800 truncate">{{ st.name }}</p>
                  <p class="text-[10px] text-slate-400 truncate">{{ st.departmentName }} • {{ st.year_level }}</p>
                </div>
                <span class="text-[10px] text-slate-400 whitespace-nowrap shrink-0">{{ st.joined }}</span>
              </div>
              <div v-if="recentRegistrations.length === 0" class="text-xs text-slate-400 text-center py-4">No student registrations recorded</div>
            </div>
          </div>
          <p class="text-[11px] text-slate-400 mt-3">Sorted by enrollment date</p>
        </div>

        <!-- Fast Shortcuts -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Student Operations</h3>
            <div class="grid grid-cols-2 gap-2.5">
              <button @click="openAdd" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 hover:border-indigo-300 hover:bg-indigo-50/40 group transition-all text-center">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-1.5 group-hover:scale-105 transition-transform">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                </div>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-indigo-700">Add Student</span>
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
            <span>Last refreshed: {{ lastRefreshedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
            <span class="font-bold text-indigo-600 cursor-pointer" @click="refreshData">Sync</span>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 2. STUDENT DETAIL VIEW                                                 -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="viewingStudent" class="space-y-6 pb-16 min-w-0 w-full">
      <!-- Breadcrumb Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <button
            @click="closeView"
            class="w-10 h-10 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-100 transition-colors text-slate-600"
            title="Back to Students Directory"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          </button>
          <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
              <span @click="closeView" class="hover:text-indigo-600 cursor-pointer">Students</span>
              <span>/</span>
              <span class="text-indigo-600 font-bold">Profile Details</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
              {{ viewingStudent.name }}
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold capitalize border"
                :class="{
                  'bg-emerald-50 text-emerald-700 border-emerald-200': viewingStudent.status === 'active',
                  'bg-slate-100 text-slate-700 border-slate-200': viewingStudent.status === 'inactive',
                  'bg-rose-50 text-rose-700 border-rose-200': viewingStudent.status === 'suspended',
                }"
              >
                {{ viewingStudent.status }}
              </span>
            </h1>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="openEdit(viewingStudent)"
            class="flex items-center gap-2 px-4 py-2.5 bg-white border border-indigo-300 text-indigo-700 font-bold rounded-xl text-xs hover:bg-indigo-50 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            Edit Profile
          </button>
          <button
            @click="confirmDelete(viewingStudent)"
            class="flex items-center gap-2 px-4 py-2.5 bg-white border border-rose-200 text-rose-600 font-bold rounded-xl text-xs hover:bg-rose-50 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Remove Student
          </button>
        </div>
      </div>

      <!-- Detail Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
        <div class="space-y-6 min-w-0">
          
          <!-- Personal Info Card -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-6 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Personal Information
            </h3>
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
              <img
                v-if="viewingStudent.profilePicture"
                :src="viewingStudent.profilePicture"
                class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl object-cover border-2 border-slate-100 shadow-sm shrink-0"
                :alt="viewingStudent.name"
              />
              <div
                v-else
                :class="avatarBg(viewingStudent.id)"
                class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl flex items-center justify-center text-3xl font-black border-2 shadow-sm shrink-0 select-none"
              >
                {{ viewingStudent.avatar }}
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 flex-1 w-full text-xs">
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Full Name</p>
                  <p class="text-sm font-black text-slate-800">{{ viewingStudent.name }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Student ID (WU / ID No)</p>
                  <p class="text-sm font-mono font-bold text-indigo-700">{{ viewingStudent.id_no }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Institutional Email</p>
                  <p class="text-xs font-semibold text-slate-800">{{ viewingStudent.email }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Phone Number</p>
                  <p class="text-xs font-semibold text-slate-800">{{ viewingStudent.phone }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Gender</p>
                  <p class="text-xs font-semibold text-slate-800">{{ viewingStudent.gender }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Date of Birth</p>
                  <p class="text-xs font-semibold text-slate-800">{{ viewingStudent.dob || '—' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Academic Placement Card -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-6 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
              Academic Placement & Enrollment
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5 text-xs">
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Department</p>
                <p class="text-sm font-black text-slate-800">{{ viewingStudent.departmentName }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Year Level</p>
                <p class="text-sm font-black text-slate-800">{{ viewingStudent.year_level }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Assigned Section</p>
                <p class="text-sm font-black text-slate-800">{{ viewingStudent.section }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Current Semester</p>
                <p class="text-xs font-bold text-slate-700">{{ viewingStudent.semester }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Academic Year</p>
                <p class="text-xs font-bold text-slate-700">{{ viewingStudent.academic_year }}</p>
              </div>
              <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Admission Number</p>
                <p class="text-xs font-mono font-bold text-slate-700">{{ viewingStudent.admission_number || '—' }}</p>
              </div>
            </div>
          </div>

        </div>

        <!-- Sidebar Summary -->
        <div class="space-y-6">
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-xs">
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-4">Account Overview</h4>
            
            <div class="space-y-3.5 text-xs">
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Account Status</span>
                <span
                  class="font-bold px-2 py-0.5 rounded-full text-[11px] capitalize"
                  :class="{
                    'bg-emerald-50 text-emerald-700': viewingStudent.status === 'active',
                    'bg-slate-100 text-slate-700': viewingStudent.status === 'inactive',
                    'bg-rose-50 text-rose-700': viewingStudent.status === 'suspended',
                  }"
                >
                  {{ viewingStudent.status }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Username</span>
                <span class="font-mono font-semibold text-slate-700">{{ viewingStudent.username }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Role</span>
                <span class="font-bold text-indigo-700 capitalize">{{ viewingStudent.role }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Registered On</span>
                <span class="font-semibold text-slate-700">{{ viewingStudent.joined }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Last Profile Sync</span>
                <span class="font-semibold text-slate-700">{{ viewingStudent.lastLogin }}</span>
              </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 space-y-2">
              <button
                @click="openEdit(viewingStudent)"
                class="w-full py-2.5 px-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl text-xs transition-colors flex items-center justify-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                Edit Student Details
              </button>
              <button
                @click="closeView"
                class="w-full py-2.5 px-4 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-xl text-xs transition-colors"
              >
                Back to Student Directory
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 3. ADD STUDENT VIEW                                                    -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="showAddPage" class="space-y-6 pb-16 min-w-0 w-full">
      <!-- Breadcrumb Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span class="hover:text-indigo-600 cursor-pointer" @click="closeAdd">Students</span>
            <span>/</span>
            <span class="text-indigo-600 font-bold">Add New Student</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Add New Student</h1>
        </div>
        <button
          @click="closeAdd"
          class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          Back to Students
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
        <!-- Main Form Column -->
        <div class="space-y-6 min-w-0">
          
          <!-- Personal Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs space-y-5">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Personal Information
            </h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Full Name <span class="text-rose-500">*</span></label>
                <input
                  id="field-name"
                  v-model="newStudent.name"
                  type="text"
                  placeholder="e.g. Temesgen Alemu"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.name ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('name')"
                />
                <p v-if="formErrors.name" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.name }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Email Address <span class="text-rose-500">*</span></label>
                <input
                  id="field-email"
                  v-model="newStudent.email"
                  type="email"
                  placeholder="student@wu.edu.et"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.email ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('email')"
                />
                <p v-if="formErrors.email" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.email }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Phone Number</label>
                <input
                  id="field-phone"
                  v-model="newStudent.phone"
                  type="text"
                  placeholder="e.g. 0911223344"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.phone ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('phone')"
                />
                <p v-if="formErrors.phone" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.phone }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 pt-2">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Gender <span class="text-rose-500">*</span></label>
                <select
                  id="field-gender"
                  v-model="newStudent.gender"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors bg-white"
                  :class="formErrors.gender ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @change="validateField('gender')"
                >
                  <option value="">Select gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
                <p v-if="formErrors.gender" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.gender }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Date of Birth <span class="text-slate-400 font-normal">(Optional)</span></label>
                <input
                  id="field-dob"
                  v-model="newStudent.dob"
                  type="date"
                  class="w-full border rounded-xl px-4 py-2 text-xs focus:outline-none transition-colors bg-white"
                  :class="formErrors.dob ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('dob')"
                />
                <p v-if="formErrors.dob" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.dob }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Profile Picture <span class="text-slate-400 font-normal">(Optional)</span></label>
                <div
                  class="border-2 border-dashed border-slate-200 rounded-xl p-3 flex flex-col items-center justify-center hover:border-indigo-600 hover:bg-slate-50 transition-colors cursor-pointer overflow-hidden relative"
                  @click="triggerFileInput"
                >
                  <input type="file" ref="fileInput" class="hidden" accept="image/png, image/jpeg, image/webp" @change="handleProfilePicture" />
                  <template v-if="previewUrl">
                    <img :src="previewUrl" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm mb-1" />
                    <span class="text-[11px] font-bold text-indigo-700">Change Photo</span>
                  </template>
                  <template v-else>
                    <svg class="w-5 h-5 text-slate-400 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                    <span class="text-[11px] font-bold text-slate-700">Upload Photo</span>
                    <span class="text-[9px] text-slate-400">PNG, JPG up to 2MB</span>
                  </template>
                </div>
              </div>
            </div>
          </div>

          <!-- Academic Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs space-y-5">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Academic Cohort & Section Assignment
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Department <span class="text-rose-500">*</span></label>
                <select
                  id="field-department_id"
                  v-model="newStudent.department_id"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors bg-white"
                  :class="formErrors.department_id ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @change="validateField('department_id')"
                >
                  <option value="">Select Department</option>
                  <option v-for="dept in allDepartments" :key="dept.id" :value="String(dept.id)">{{ dept.name }}</option>
                </select>
                <p v-if="formErrors.department_id" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ formErrors.department_id }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Academic Year Level <span class="text-rose-500">*</span></label>
                <select
                  id="field-year_level"
                  v-model="newStudent.year_level"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors bg-white"
                  :class="formErrors.year_level ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @change="validateField('year_level')"
                >
                  <option v-for="lvl in academicYearLevels" :key="lvl" :value="lvl">{{ lvl }}</option>
                </select>
                <p v-if="formErrors.year_level" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ formErrors.year_level }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Assigned Section <span class="text-rose-500">*</span></label>
                <select
                  id="field-section"
                  v-model="newStudent.section"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors bg-white"
                  :class="formErrors.section ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @change="validateField('section')"
                >
                  <option v-for="sec in sections" :key="sec" :value="sec">{{ sec }}</option>
                </select>
                <p v-if="formErrors.section" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ formErrors.section }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Student ID (WU / ID No)</label>
                <input
                  id="field-studentId"
                  v-model="newStudent.studentId"
                  type="text"
                  placeholder="e.g. WU/1042/26 (Auto if blank)"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.studentId ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('studentId')"
                />
                <p v-if="formErrors.studentId" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ formErrors.studentId }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Semester</label>
                <select
                  v-model="newStudent.semester"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-600 bg-white"
                >
                  <option value="1st Semester">1st Semester</option>
                  <option value="2nd Semester">2nd Semester</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Account Security -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs space-y-5">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Account Security & Access Credentials
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Username <span class="text-slate-400 font-normal">(Optional)</span></label>
                <input
                  id="field-username"
                  v-model="newStudent.username"
                  type="text"
                  placeholder="e.g. temesgen.a"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.username ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('username')"
                />
                <p v-if="formErrors.username" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ formErrors.username }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    id="field-password"
                    v-model="newStudent.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="Enter secure password"
                    class="w-full border rounded-xl pl-4 pr-10 py-2.5 text-xs focus:outline-none transition-colors"
                    :class="formErrors.password ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                    @input="validateField('password')"
                  />
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
                  >
                    <svg v-if="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                  </button>
                </div>
                <p v-if="formErrors.password" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ formErrors.password }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Confirm Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    id="field-confirmPassword"
                    v-model="newStudent.confirmPassword"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    placeholder="Confirm password"
                    class="w-full border rounded-xl pl-4 pr-10 py-2.5 text-xs focus:outline-none transition-colors"
                    :class="formErrors.confirmPassword ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                    @input="validateField('confirmPassword')"
                  />
                  <button
                    type="button"
                    @click="showConfirmPassword = !showConfirmPassword"
                    class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
                  >
                    <svg v-if="showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                  </button>
                </div>
                <p v-if="formErrors.confirmPassword" class="text-[11px] font-semibold text-rose-500 mt-1">
                  {{ formErrors.confirmPassword }}
                </p>
              </div>
            </div>
          </div>

          <!-- Bottom Action Buttons -->
          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              @click="closeAdd"
              class="px-6 py-2.5 border border-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="addStudent"
              :disabled="isLoading"
              class="flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-colors shadow-xs shadow-indigo-200 disabled:opacity-50"
            >
              <svg v-if="isLoading" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              <span>{{ isLoading ? 'Registering Student...' : 'Complete Registration' }}</span>
            </button>
          </div>

        </div>

        <!-- Right Instructions & Password Checklist -->
        <div class="space-y-6">
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-xs space-y-4">
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Password Requirements</h4>
            <p class="text-xs text-slate-500 leading-relaxed">
              Student accounts must adhere to institutional security guidelines.
            </p>

            <ul class="space-y-2 text-xs">
              <li class="flex items-center gap-2" :class="passwordCriteria.length ? 'font-semibold text-emerald-700' : 'text-slate-400'">
                <svg v-if="passwordCriteria.length" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300"></div>
                At least 8 characters long
              </li>
              <li class="flex items-center gap-2" :class="passwordCriteria.uppercase ? 'font-semibold text-emerald-700' : 'text-slate-400'">
                <svg v-if="passwordCriteria.uppercase" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300"></div>
                Include uppercase letter
              </li>
              <li class="flex items-center gap-2" :class="passwordCriteria.lowercase ? 'font-semibold text-emerald-700' : 'text-slate-400'">
                <svg v-if="passwordCriteria.lowercase" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300"></div>
                Include lowercase letter
              </li>
              <li class="flex items-center gap-2" :class="passwordCriteria.number ? 'font-semibold text-emerald-700' : 'text-slate-400'">
                <svg v-if="passwordCriteria.number" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300"></div>
                Include number
              </li>
              <li class="flex items-center gap-2" :class="passwordCriteria.special ? 'font-semibold text-emerald-700' : 'text-slate-400'">
                <svg v-if="passwordCriteria.special" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300"></div>
                Include special character
              </li>
            </ul>
          </div>

          <div class="bg-indigo-50/70 border border-indigo-100 rounded-2xl p-5 text-xs text-indigo-900 space-y-2">
            <h5 class="font-bold uppercase tracking-wider text-[11px] text-indigo-700">Student ID Formatting</h5>
            <p class="leading-relaxed">
              If left blank, the system automatically assigns the official Wollo University ID format (<code class="font-bold">WU/000X/26</code>).
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 4. MODALS (Delete, Edit, Import, Error, Success)                       -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <!-- Delete Modal -->
      <div v-if="showDeleteModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 border border-slate-100">
          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-rose-100">
              <svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 mb-2">Remove Student Record?</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
              Are you sure you want to remove <strong class="text-slate-800">{{ selectedStudent?.name }}</strong> (<span class="font-mono text-slate-700">{{ selectedStudent?.id_no }}</span>)? This action will revoke portal credentials.
            </p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="deleteStudent" :disabled="isLoading" class="flex-1 py-2.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors shadow-xs disabled:opacity-50">
              {{ isLoading ? 'Removing...' : 'Confirm Remove' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Modal -->
      <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 border border-slate-100">
          <div class="flex items-center justify-between px-6 py-4.5 border-b border-slate-100 shrink-0">
            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Edit Student Record</h3>
            <button @click="showEditModal = false" class="p-1 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="p-6 overflow-y-auto space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Full Name <span class="text-rose-500">*</span></label>
                <input v-model="editStudentForm.name" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email Address <span class="text-rose-500">*</span></label>
                <input v-model="editStudentForm.email" type="email" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number</label>
                <input v-model="editStudentForm.phone" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Gender</label>
                <select v-model="editStudentForm.gender" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="">Select gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Department</label>
                <select v-model="editStudentForm.department_id" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="">Select Department</option>
                  <option v-for="d in allDepartments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Academic Year Level</label>
                <select v-model="editStudentForm.year_level" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option v-for="lvl in academicYearLevels" :key="lvl" :value="lvl">{{ lvl }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Section</label>
                <select v-model="editStudentForm.section" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option v-for="sec in sections" :key="sec" :value="sec">{{ sec }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Student ID (WU / ID No)</label>
                <input v-model="editStudentForm.studentId" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                <select v-model="editStudentForm.status" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="suspended">Suspended</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">New Password (Optional)</label>
                <input v-model="editStudentForm.password" type="password" placeholder="Leave blank to preserve current" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>

              <!-- Profile Photo -->
              <div class="col-span-1 sm:col-span-2 pt-1">
                <label class="block text-xs font-bold text-slate-700 mb-1">Profile Photo</label>
                <div
                  @click="triggerEditFileInput"
                  class="border-2 border-dashed border-slate-200 rounded-xl p-3 flex items-center justify-center gap-3 hover:border-indigo-600 hover:bg-slate-50 transition-colors cursor-pointer"
                >
                  <input type="file" ref="editFileInput" class="hidden" accept="image/png, image/jpeg, image/webp" @change="handleEditProfilePicture" />
                  <img v-if="editPreviewUrl" :src="editPreviewUrl" class="w-10 h-10 rounded-full object-cover border" />
                  <span class="text-xs font-bold text-indigo-700">Change Photo (PNG, JPG)</span>
                </div>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50 shrink-0">
            <button @click="showEditModal = false" class="px-5 py-2 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="saveEdit" :disabled="isLoading" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-xs disabled:opacity-50">
              {{ isLoading ? 'Saving...' : 'Save Changes' }}
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
                <h3 class="text-base font-bold text-slate-800">Batch Import Students</h3>
                <p class="text-xs text-slate-500">Upload a CSV or PDF file to register cohorts of students.</p>
              </div>
            </div>
            <button @click="showImportModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="p-6 space-y-4">
            <!-- Format Guide Banner -->
            <div class="p-4 bg-indigo-50/70 border border-indigo-100 rounded-xl space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">Template & Required Columns</span>
                <button @click="downloadSampleCsv" class="px-2.5 py-1 bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 font-bold text-xs rounded-lg transition-colors shadow-2xs">
                  Download Sample CSV
                </button>
              </div>
              <p class="text-xs text-slate-600">
                Required columns: <strong class="text-slate-900">Full Name, Email Address, Department, Academic Year Level</strong>.
              </p>
            </div>

            <!-- Drag & Drop Area -->
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
              <span>{{ isImporting ? 'Importing Students...' : 'Upload & Import' }}</span>
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
              <h3 class="text-base font-bold text-slate-800">Students Imported Successfully!</h3>
              <p class="text-xs text-slate-500 mt-1">{{ importSuccessMessage }}</p>
            </div>
            <div v-if="importedStudentsList.length > 0" class="max-h-40 overflow-y-auto border border-slate-100 rounded-xl divide-y divide-slate-100 text-left">
              <div v-for="st in importedStudentsList" :key="st.id" class="p-2.5 flex items-center justify-between bg-slate-50/50 text-xs">
                <div>
                  <p class="font-bold text-slate-800">{{ st.name }}</p>
                  <p class="text-[10px] text-slate-400">{{ st.email }}</p>
                </div>
                <span class="px-2 py-0.5 font-bold rounded bg-indigo-50 text-indigo-700 text-[10px]">
                  {{ st.department || st.year_level || 'Enrolled' }}
                </span>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end bg-slate-50/50">
            <button @click="showImportSuccessModal = false" class="w-full sm:w-auto px-6 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors">
              Done & View Students
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