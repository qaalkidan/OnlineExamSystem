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
const dateFilter = ref<'all' | '7_days' | '30_days' | '90_days'>('all')

// Sorting State
type SortField = 'name' | 'department' | 'status' | 'joined' | 'email'
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

// Column Visibility Management
const showColumnDropdown = ref(false)
const visibleColumns = ref({
  email: true,
  department: true,
  section: true,
  year: true,
  status: true,
  joined: true,
  lastLogin: true,
})

// Pagination
const currentPage = ref(1)
const perPage = ref(10)

// View Management
const showAddPage = ref(false)
const showDeleteModal = ref(false)
const showEditModal = ref(false)
const editInstructorForm = ref<any>({})
const viewingInstructor = ref<any>(null)
const selectedInstructor = ref<any>(null)
const isLoading = ref(true)
const isRefreshing = ref(false)
const lastRefreshedAt = ref<Date>(new Date())

// Raw Data
const allInstructors = ref<any[]>([])
const allDepartments = ref<any[]>([])
const backendStats = ref<{
  total: number
  active: number
  inactive: number
  new_instructors: number
  growth: { rate: number; formatted: string; trend: 'up' | 'down' } | null
} | null>(null)

// ── Add Instructor Form State ─────────────────────────────────────────────────
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const newInstructor = ref({
  name: '',
  email: '',
  phone: '',
  gender: '',
  department_id: '',
  semester: '',
  year: '',
  employeeId: '',
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
  employeeId: '',
  username: '',
  password: '',
  confirmPassword: '',
  profilePicture: '',
})

const isSubmitted = ref(false)

const passwordCriteria = computed(() => {
  const pwd = newInstructor.value.password || ''
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
      if (!newInstructor.value.name.trim()) {
        formErrors.value.name = 'Full Name is required.'
      } else if (newInstructor.value.name.trim().length < 2) {
        formErrors.value.name = 'Full Name must be at least 2 characters.'
      } else {
        formErrors.value.name = ''
      }
      break

    case 'email':
      const emailTrim = newInstructor.value.email.trim()
      if (!emailTrim) {
        formErrors.value.email = 'Email Address is required.'
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailTrim)) {
        formErrors.value.email = 'Please enter a valid email address (e.g. name@wu.edu.et).'
      } else {
        formErrors.value.email = ''
      }
      break

    case 'phone':
      const phoneTrim = newInstructor.value.phone.trim()
      if (!phoneTrim) {
        formErrors.value.phone = 'Phone Number is required.'
      } else if (!/^\+?[0-9\s\-()]{9,18}$/.test(phoneTrim)) {
        formErrors.value.phone = 'Please enter a valid phone number (e.g. 0911223344 or +251911223344).'
      } else {
        formErrors.value.phone = ''
      }
      break

    case 'gender':
      if (!newInstructor.value.gender) {
        formErrors.value.gender = 'Please select a gender.'
      } else {
        formErrors.value.gender = ''
      }
      break

    case 'department_id':
      if (!newInstructor.value.department_id) {
        formErrors.value.department_id = 'Please select a department.'
      } else {
        formErrors.value.department_id = ''
      }
      break

    case 'employeeId':
      const empIdTrim = newInstructor.value.employeeId.trim()
      if (!empIdTrim) {
        formErrors.value.employeeId = 'Employee ID is required.'
      } else if (empIdTrim.length < 3) {
        formErrors.value.employeeId = 'Employee ID must be at least 3 characters.'
      } else {
        formErrors.value.employeeId = ''
      }
      break

    case 'username':
      const uTrim = newInstructor.value.username.trim()
      if (!uTrim) {
        formErrors.value.username = 'Username is required.'
      } else if (!/^[a-zA-Z0-9_.-]{3,30}$/.test(uTrim)) {
        formErrors.value.username = 'Username must be 3–30 characters (letters, numbers, _, ., -).'
      } else {
        formErrors.value.username = ''
      }
      break

    case 'password':
      if (!newInstructor.value.password) {
        formErrors.value.password = 'Password is required.'
      } else if (!isPasswordValid.value) {
        formErrors.value.password = 'Password must meet all 5 requirements in the checklist.'
      } else {
        formErrors.value.password = ''
      }
      if (newInstructor.value.confirmPassword) {
        validateField('confirmPassword')
      }
      break

    case 'confirmPassword':
      if (!newInstructor.value.confirmPassword) {
        formErrors.value.confirmPassword = 'Confirm Password is required.'
      } else if (newInstructor.value.confirmPassword !== newInstructor.value.password) {
        formErrors.value.confirmPassword = 'Passwords do not match.'
      } else {
        formErrors.value.confirmPassword = ''
      }
      break
  }
}

const validateAll = (): boolean => {
  isSubmitted.value = true
  validateField('name')
  validateField('email')
  validateField('phone')
  validateField('gender')
  validateField('department_id')
  validateField('employeeId')
  validateField('username')
  validateField('password')
  validateField('confirmPassword')

  return !Object.values(formErrors.value).some(err => err !== '')
}

// ── Fetch Instructors and Departments ─────────────────────────────────────────
const fetchInstructors = async (silent = false) => {
  if (!silent) isLoading.value = true
  try {
    const res = await apiClient.get('/admin/users?role=instructor,dept_head')
    if (res.data?.stats) {
      backendStats.value = res.data.stats
    }
    allInstructors.value = (res.data.data || []).map((u: any) => {
      const initials = u.name
        ? u.name
            .split(' ')
            .filter(Boolean)
            .map((w: string) => w[0])
            .join('')
            .toUpperCase()
            .slice(0, 2)
        : 'WU'
      
      return {
        ...u,
        avatar: initials,
        profilePicture: u.profile_picture ? `http://localhost:8000/storage/${u.profile_picture}` : (u.profile_picture_url || null),
        status: u.status || 'active',
        joined: new Date(u.created_at || Date.now()).toLocaleDateString('en-US', {
          month: 'short',
          day: '2-digit',
          year: 'numeric',
        }),
        departmentName: u.department?.name || '—',
        employeeId: u.id_no || u.employee_id || `WU-INS-${(u.id || 1).toString().padStart(4, '0')}`,
        lastLogin: u.last_login_at || 'Never',
        phone: u.phone || 'N/A',
        gender: u.gender || 'N/A',
        section: u.section || '—',
        year: u.year_level || '—',
        courses: u.assigned_courses?.length ? u.assigned_courses.map((c: any) => c.title).join(', ') : 'No Courses',
        coCourses: u.co_instructor_courses?.length ? u.co_instructor_courses.map((c: any) => c.title).join(', ') : 'None',
      }
    })
    lastRefreshedAt.value = new Date()
  } catch (err) {
    console.error('Failed to fetch instructors:', err)
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

const refreshData = async () => {
  isRefreshing.value = true
  await Promise.all([fetchInstructors(true), fetchDepartments()])
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
  await Promise.all([fetchInstructors(), fetchDepartments()])
})

onUnmounted(() => {
  window.removeEventListener('click', closeAllDropdowns)
})

// ── Computed Statistics (100% Real Database Metrics) ───────────────────────────
const stats = computed(() => {
  if (backendStats.value) {
    return {
      total: backendStats.value.total,
      active: backendStats.value.active,
      inactive: backendStats.value.inactive,
      newInst: backendStats.value.new_instructors,
      growth: backendStats.value.growth,
    }
  }

  const total = allInstructors.value.length
  const active = allInstructors.value.filter(i => i.status === 'active').length
  const inactive = allInstructors.value.filter(i => i.status === 'inactive' || i.status === 'suspended').length
  const thirtyDaysAgo = Date.now() - 30 * 24 * 60 * 60 * 1000
  const newInst = allInstructors.value.filter(i => {
    const created = new Date(i.created_at || Date.now()).getTime()
    return created >= thirtyDaysAgo
  }).length

  return { total, active, inactive, newInst, growth: null }
})

// Filter and Sort Pipeline
const filtered = computed(() => {
  const query = debouncedSearch.value
  const now = Date.now()
  const cutoffDays = {
    '7_days': 7 * 24 * 60 * 60 * 1000,
    '30_days': 30 * 24 * 60 * 60 * 1000,
    '90_days': 90 * 24 * 60 * 60 * 1000,
  }

  let list = allInstructors.value.filter(i => {
    const matchSearch = !query ||
      i.name.toLowerCase().includes(query) ||
      i.email.toLowerCase().includes(query) ||
      (i.employeeId && i.employeeId.toLowerCase().includes(query)) ||
      (i.departmentName && i.departmentName.toLowerCase().includes(query))

    const matchDept = deptFilter.value === 'all' || i.departmentName === deptFilter.value
    const matchStatus = statusFilter.value === 'all' || i.status === statusFilter.value
    const matchSection = sectionFilter.value === 'all' || i.section === sectionFilter.value
    const matchYear = yearFilter.value === 'all' || i.year === yearFilter.value

    let matchDate = true
    if (dateFilter.value !== 'all') {
      const createdTime = new Date(i.created_at || Date.now()).getTime()
      matchDate = (now - createdTime) <= cutoffDays[dateFilter.value]
    }

    return matchSearch && matchDept && matchStatus && matchSection && matchYear && matchDate
  })

  // Sort
  list.sort((a, b) => {
    let aVal = ''
    let bVal = ''

    if (sortField.value === 'name') {
      aVal = a.name.toLowerCase()
      bVal = b.name.toLowerCase()
    } else if (sortField.value === 'email') {
      aVal = a.email.toLowerCase()
      bVal = b.email.toLowerCase()
    } else if (sortField.value === 'department') {
      aVal = (a.departmentName || '').toLowerCase()
      bVal = (b.departmentName || '').toLowerCase()
    } else if (sortField.value === 'status') {
      aVal = a.status.toLowerCase()
      bVal = b.status.toLowerCase()
    } else if (sortField.value === 'joined') {
      const aTime = new Date(a.created_at || 0).getTime()
      const bTime = new Date(b.created_at || 0).getTime()
      return sortDirection.value === 'asc' ? aTime - bTime : bTime - aTime
    }

    if (aVal < bVal) return sortDirection.value === 'asc' ? -1 : 1
    if (aVal > bVal) return sortDirection.value === 'asc' ? 1 : -1
    return 0
  })

  return list
})

// Active Filter Chips
const activeFilterChips = computed(() => {
  const chips: Array<{ id: string; label: string; clear: () => void }> = []
  if (debouncedSearch.value) {
    chips.push({ id: 'search', label: `Search: "${debouncedSearch.value}"`, clear: clearSearch })
  }
  if (deptFilter.value !== 'all') {
    chips.push({ id: 'dept', label: `Dept: ${deptFilter.value}`, clear: () => { deptFilter.value = 'all' } })
  }
  if (statusFilter.value !== 'all') {
    chips.push({ id: 'status', label: `Status: ${statusFilter.value}`, clear: () => { statusFilter.value = 'all' } })
  }
  if (sectionFilter.value !== 'all') {
    chips.push({ id: 'section', label: `Section: ${sectionFilter.value}`, clear: () => { sectionFilter.value = 'all' } })
  }
  if (yearFilter.value !== 'all') {
    chips.push({ id: 'year', label: `Year: ${yearFilter.value}`, clear: () => { yearFilter.value = 'all' } })
  }
  if (dateFilter.value !== 'all') {
    const labelMap = { '7_days': 'Last 7 Days', '30_days': 'Last 30 Days', '90_days': 'Last 90 Days' }
    chips.push({ id: 'date', label: `Registered: ${labelMap[dateFilter.value]}`, clear: () => { dateFilter.value = 'all' } })
  }
  return chips
})

const clearAllFilters = () => {
  clearSearch()
  deptFilter.value = 'all'
  statusFilter.value = 'all'
  sectionFilter.value = 'all'
  yearFilter.value = 'all'
  dateFilter.value = 'all'
  currentPage.value = 1
}

// KPI Card Click Handlers
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

// Pagination Computations
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filtered.value.slice(start, start + perPage.value)
})

watch([debouncedSearch, deptFilter, statusFilter, sectionFilter, yearFilter, dateFilter, perPage], () => {
  currentPage.value = 1
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
  if (typeof page === 'number' && page >= 1 && page <= totalPages.value) currentPage.value = page
}

// Avatar Color Generator
const avatarBg = (id: number) => {
  const palettes = [
    'bg-indigo-50 text-indigo-700 border-indigo-200',
    'bg-sky-50 text-sky-700 border-sky-200',
    'bg-blue-50 text-blue-700 border-blue-200',
    'bg-emerald-50 text-emerald-700 border-emerald-200',
    'bg-violet-50 text-violet-700 border-violet-200',
    'bg-teal-50 text-teal-700 border-teal-200',
  ]
  return palettes[(id || 0) % palettes.length]
}

// ── CRUD Actions ──────────────────────────────────────────────────────────────
const viewInstructor = (inst: any) => { viewingInstructor.value = inst }
const closeView = () => { viewingInstructor.value = null }

const openAdd = () => {
  newInstructor.value = {
    name: '',
    email: '',
    phone: '',
    gender: '',
    department_id: '',
    semester: '',
    year: '',
    employeeId: '',
    username: '',
    password: '',
    confirmPassword: '',
    profilePicture: null,
  }
  isSubmitted.value = false
  Object.keys(formErrors.value).forEach(k => ((formErrors.value as any)[k] = ''))
  if (previewUrl.value) previewUrl.value = null
  showPassword.value = false
  showConfirmPassword.value = false
  showAddPage.value = true
}

const closeAdd = () => {
  showAddPage.value = false
  if (previewUrl.value) previewUrl.value = null
  isSubmitted.value = false
}

const fileInput = ref<HTMLInputElement | null>(null)
const previewUrl = ref<string | null>(null)
const triggerFileInput = () => { if (fileInput.value) fileInput.value.click() }

const handleProfilePicture = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) {
    if (file.size > 2 * 1024 * 1024) {
      formErrors.value.profilePicture = 'Profile photo must be less than 2MB.'
      return
    }
    if (!['image/jpeg', 'image/png', 'image/jpg', 'image/webp'].includes(file.type)) {
      formErrors.value.profilePicture = 'Only PNG, JPG or WEBP image formats are supported.'
      return
    }
    formErrors.value.profilePicture = ''
    newInstructor.value.profilePicture = file
    previewUrl.value = URL.createObjectURL(file)
  }
}

// Edit Modal
const editFileInput = ref<HTMLInputElement | null>(null)
const editPreviewUrl = ref<string | null>(null)
const triggerEditFileInput = () => { if (editFileInput.value) editFileInput.value.click() }

const handleEditProfilePicture = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) {
    editInstructorForm.value.profilePicture = file
    editPreviewUrl.value = URL.createObjectURL(file)
  }
}

const openEdit = (instructor: any) => {
  editInstructorForm.value = {
    ...instructor,
    gender: instructor.gender || '',
    employeeId: instructor.id_no || instructor.employeeId || '',
    username: instructor.username || '',
    password: '',
    profilePicture: null,
  }
  editPreviewUrl.value = instructor.profilePicture || null
  showEditModal.value = true
}

const saveEdit = async () => {
  isLoading.value = true
  try {
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('name', editInstructorForm.value.name)
    formData.append('email', editInstructorForm.value.email)
    if (editInstructorForm.value.username) formData.append('username', editInstructorForm.value.username)
    if (editInstructorForm.value.phone) formData.append('phone', editInstructorForm.value.phone)
    if (editInstructorForm.value.gender) formData.append('gender', editInstructorForm.value.gender)
    if (editInstructorForm.value.department_id) formData.append('department_id', editInstructorForm.value.department_id)
    if (editInstructorForm.value.employeeId) formData.append('id_no', editInstructorForm.value.employeeId)
    if (editInstructorForm.value.password) formData.append('password', editInstructorForm.value.password)

    if (editInstructorForm.value.profilePicture instanceof File) {
      formData.append('profile_picture', editInstructorForm.value.profilePicture)
    }

    await apiClient.post(`/admin/users/${editInstructorForm.value.id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    await fetchInstructors(true)
    showEditModal.value = false
    showToast('Instructor updated successfully.', 'success')

    if (viewingInstructor.value?.id === editInstructorForm.value.id) {
      const updated = allInstructors.value.find(i => i.id === editInstructorForm.value.id)
      if (updated) viewingInstructor.value = { ...updated }
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to update instructor.'
    showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

// Add Instructor
const addInstructor = async () => {
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
    const instructorName = newInstructor.value.name.trim()
    formData.append('name', instructorName)
    formData.append('email', newInstructor.value.email.trim())
    formData.append('role', 'instructor')
    formData.append('password', newInstructor.value.password)

    if (newInstructor.value.username) formData.append('username', newInstructor.value.username.trim())
    if (newInstructor.value.phone) formData.append('phone', newInstructor.value.phone.trim())
    if (newInstructor.value.gender) formData.append('gender', newInstructor.value.gender)
    if (newInstructor.value.department_id) formData.append('department_id', newInstructor.value.department_id)
    if (newInstructor.value.year) formData.append('year_level', newInstructor.value.year)
    if (settingsStore.semester) formData.append('semester', settingsStore.semester)
    if (newInstructor.value.employeeId) formData.append('id_no', newInstructor.value.employeeId.trim())
    if (newInstructor.value.profilePicture) {
      formData.append('profile_picture', newInstructor.value.profilePicture)
    }

    await apiClient.post('/admin/users', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    await fetchInstructors(true)
    showAddPage.value = false
    showToast(`Instructor "${instructorName}" was created successfully!`, 'success')
  } catch (err: any) {
    const errs = err.response?.data?.errors
    if (errs) {
      if (errs.email) formErrors.value.email = errs.email[0]
      if (errs.name) formErrors.value.name = errs.name[0]
      if (errs.phone) formErrors.value.phone = errs.phone[0]
      if (errs.username) formErrors.value.username = errs.username[0]
      if (errs.department_id) formErrors.value.department_id = errs.department_id[0]
      if (errs.id_no) formErrors.value.employeeId = errs.id_no[0]
      if (errs.password) formErrors.value.password = errs.password[0]
    }
    const msg = err.response?.data?.message || 'Failed to create instructor.'
    showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

// Delete Instructor
const confirmDelete = (instructor: any) => {
  selectedInstructor.value = instructor
  showDeleteModal.value = true
}

const deleteInstructor = async () => {
  if (!selectedInstructor.value) return
  isLoading.value = true
  try {
    await apiClient.delete(`/admin/users/${selectedInstructor.value.id}`)
    await fetchInstructors(true)
    if (viewingInstructor.value?.id === selectedInstructor.value.id) {
      viewingInstructor.value = null
    }
    showDeleteModal.value = false
    showToast(`Instructor "${selectedInstructor.value.name}" removed successfully.`, 'success')
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to remove instructor.'
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
      role: 'instructor,dept_head',
      format,
    }
    if (deptFilter.value !== 'all') params.department = deptFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (yearFilter.value !== 'all') params.year = yearFilter.value
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
      try { showToast(JSON.parse(text).message || 'Export failed', 'error') } catch { showToast('Export failed', 'error') }
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

    showToast(`Instructors exported successfully as ${format.toUpperCase()}.`, 'success')
  } catch (err) {
    showToast('Failed to export instructors. Please try again.', 'error')
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
const importedInstructorsList = ref<any[]>([])

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
    formData.append('role', 'instructor')

    const res = await apiClient.post('/admin/users-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    showImportModal.value = false
    selectedImportFile.value = null
    importSuccessMessage.value = res.data.message || 'Successfully imported instructors.'
    importedInstructorsList.value = res.data.instructors || []
    showImportSuccessModal.value = true
    await fetchInstructors(true)
  } catch (err: any) {
    showImportModal.value = false
    const data = err.response?.data
    importErrorMessage.value = data?.message || 'Failed to import instructors.'
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
  const headers = ['Full Name', 'Email', 'Phone', 'Department', 'Gender', 'Employee ID', 'Academic Year Level', 'Semester', 'Section', 'Password']
  const row1 = ['Dr. Kebede Tessema', 'kebede.t@wu.edu.et', '0911223344', dept1, 'Male', 'WU-INS-0101', '3rd Year', 'Second Semester', 'Section A', 'Password123!']
  const row2 = ['Sara Mohammed', 'sara.m@wu.edu.et', '0922334455', dept2, 'Female', 'WU-INS-0102', '2nd Year', 'Second Semester', 'Section B', 'Password123!']
  const csvContent = [headers.join(','), row1.join(','), row2.join(',')].join('\n')
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'wollo_university_instructor_template.csv'
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

// ── Overview Chart & Bottom Stats ─────────────────────────────────────────────
const chartData = computed(() => ({
  labels: ['Active Faculty', 'Inactive Faculty', 'New This Month'],
  datasets: [{
    backgroundColor: ['#4338ca', '#f43f5e', '#0284c7'],
    data: [stats.value.active, stats.value.inactive, stats.value.newInst],
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
      const count = allInstructors.value.filter(i => i.department_id === d.id || i.departmentName === d.name).length
      return { id: d.id, name: d.name, count }
    })
    return list.sort((a, b) => b.count - a.count).slice(0, 5)
  }
  return []
})

const deptIcons = ['💻', '⚙️', '📊', '🗄️', '🌐']

const recentRegistrations = computed(() => {
  return [...allInstructors.value]
    .sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())
    .slice(0, 5)
})
</script>

<template>
  <div class="w-full relative min-h-screen">

    <!-- Toast Notifications Stack -->
    <div class="fixed top-6 right-6 z-[120] flex flex-col gap-2.5 max-w-sm pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-xs font-semibold backdrop-blur-md transition-all"
          :class="{
            'bg-emerald-50 text-emerald-800 border-emerald-200': toast.type === 'success',
            'bg-rose-50 text-rose-800 border-rose-200': toast.type === 'error',
            'bg-amber-50 text-amber-800 border-amber-200': toast.type === 'warning',
          }"
        >
          <svg v-if="toast.type === 'success'" class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
          <svg v-else-if="toast.type === 'error'" class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
          <svg v-else class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
          <span class="flex-1">{{ toast.message }}</span>
        </div>
      </TransitionGroup>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 1. MAIN LIST VIEW                                                      -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="!viewingInstructor && !showAddPage" class="space-y-6 pb-16 min-w-0 w-full">

      <!-- Institutional Command Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span>Administration</span>
            <span class="text-slate-300">/</span>
            <span>Academic</span>
            <span class="text-slate-300">/</span>
            <span class="text-indigo-600 font-bold">Instructors</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
            Instructors Management
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
              {{ stats.total }} Registered
            </span>
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Manage instructors, academic assignments, departments, and credentials.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 flex-wrap sm:flex-nowrap">
          <!-- Live Refresh Button -->
          <button
            @click="refreshData"
            :disabled="isRefreshing"
            class="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs border border-slate-200 transition-colors shadow-xs disabled:opacity-50"
            title="Refresh instructors list and counts"
          >
            <svg class="w-4 h-4 text-slate-500" :class="{ 'animate-spin': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span class="hidden sm:inline">Refresh</span>
          </button>

          <!-- Import Instructors -->
          <button
            @click="triggerImport"
            class="flex items-center gap-2 px-3.5 py-2.5 bg-white hover:bg-slate-50 text-indigo-700 font-bold rounded-xl text-xs border border-indigo-200 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <span>Import</span>
          </button>

          <!-- Export Instructors Dropdown -->
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

          <!-- Add Instructor (Primary CTA) -->
          <button
            @click="openAdd"
            class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-colors shadow-xs shadow-indigo-200"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add New Instructor</span>
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Instructors</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.total }}</p>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">All registered instructors</span>
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
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Active Instructors</p>
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
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">New This Month</p>
              <p class="text-2xl font-black text-slate-800 leading-none">{{ stats.newInst }}</p>
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
              placeholder="Search by name, email, employee ID, department..."
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

            <!-- Status -->
            <select
              v-model="statusFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Status</option>
              <option value="active">Active Only</option>
              <option value="inactive">Inactive Only</option>
            </select>

            <!-- Section -->
            <select
              v-model="sectionFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Sections</option>
              <option value="Section A">Section A</option>
              <option value="Section B">Section B</option>
              <option value="Section C">Section C</option>
            </select>

            <!-- Year Level -->
            <select
              v-model="yearFilter"
              class="border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:border-indigo-500 transition-colors"
            >
              <option value="all">All Years</option>
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
              <option value="5th Year">5th Year</option>
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
                  <input type="checkbox" v-model="visibleColumns.section" class="rounded text-indigo-600" />
                  Section
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.year" class="rounded text-indigo-600" />
                  Academic Year
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.joined" class="rounded text-indigo-600" />
                  Join Date
                </label>
                <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer">
                  <input type="checkbox" v-model="visibleColumns.lastLogin" class="rounded text-indigo-600" />
                  Last Login
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
                    <span>Instructor</span>
                    <span v-if="sortField === 'name'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
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
                <th v-if="visibleColumns.section" class="px-4 py-3.5 whitespace-nowrap">Section</th>
                <th v-if="visibleColumns.year" class="px-4 py-3.5 whitespace-nowrap">Academic Year</th>
                <th v-if="visibleColumns.status" @click="handleSort('status')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Status</span>
                    <span v-if="sortField === 'status'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.joined" @click="handleSort('joined')" class="px-4 py-3.5 whitespace-nowrap cursor-pointer hover:text-slate-700 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Join Date</span>
                    <span v-if="sortField === 'joined'" class="text-indigo-600 font-black">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th v-if="visibleColumns.lastLogin" class="px-4 py-3.5 whitespace-nowrap">Last Login</th>
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
                <td v-if="visibleColumns.email" class="px-4 py-3.5"><div class="h-3 w-32 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.department" class="px-4 py-3.5"><div class="h-3 w-28 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.section" class="px-4 py-3.5"><div class="h-3 w-16 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.year" class="px-4 py-3.5"><div class="h-3 w-16 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.status" class="px-4 py-3.5"><div class="h-5 w-16 bg-slate-100 rounded-full"></div></td>
                <td v-if="visibleColumns.joined" class="px-4 py-3.5"><div class="h-3 w-20 bg-slate-100 rounded"></div></td>
                <td v-if="visibleColumns.lastLogin" class="px-4 py-3.5"><div class="h-3 w-14 bg-slate-100 rounded"></div></td>
                <td class="px-5 py-3.5 text-right"><div class="h-6 w-16 bg-slate-100 rounded ml-auto"></div></td>
              </tr>
            </tbody>

            <!-- Real Instructor Rows -->
            <tbody v-else class="divide-y divide-slate-50">
              <tr
                v-for="(inst, index) in paginated"
                :key="inst.id"
                class="hover:bg-indigo-50/30 transition-colors group"
              >
                <!-- Instructor Avatar + Name + ID -->
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <!-- Real Profile Picture or Initials Avatar -->
                    <img
                      v-if="inst.profilePicture"
                      :src="inst.profilePicture"
                      :alt="inst.name"
                      class="w-9 h-9 rounded-full object-cover shrink-0 border border-slate-200 shadow-2xs"
                    />
                    <div
                      v-else
                      :class="avatarBg(inst.id)"
                      class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-black border shadow-2xs shrink-0 select-none"
                    >
                      {{ inst.avatar }}
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-bold text-slate-900 group-hover:text-indigo-700 transition-colors truncate">
                        {{ inst.name }}
                      </p>
                      <p class="text-[11px] font-mono text-slate-400 font-medium tracking-tight">
                        {{ inst.employeeId }}
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Email -->
                <td v-if="visibleColumns.email" class="px-4 py-3.5 text-xs text-slate-600 font-medium whitespace-nowrap">
                  {{ inst.email }}
                </td>

                <!-- Department -->
                <td v-if="visibleColumns.department" class="px-4 py-3.5 whitespace-nowrap">
                  <span class="text-xs font-semibold text-slate-700">
                    {{ inst.departmentName }}
                  </span>
                </td>

                <!-- Section -->
                <td v-if="visibleColumns.section" class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                  {{ inst.section }}
                </td>

                <!-- Year Level -->
                <td v-if="visibleColumns.year" class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                  {{ inst.year }}
                </td>

                <!-- Status Badge -->
                <td v-if="visibleColumns.status" class="px-4 py-3.5 whitespace-nowrap">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-full capitalize border"
                    :class="{
                      'bg-emerald-50 text-emerald-700 border-emerald-200': inst.status === 'active',
                      'bg-slate-100 text-slate-600 border-slate-200': inst.status === 'inactive',
                      'bg-rose-50 text-rose-700 border-rose-200': inst.status === 'suspended',
                    }"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full"
                      :class="{
                        'bg-emerald-500': inst.status === 'active',
                        'bg-slate-400': inst.status === 'inactive',
                        'bg-rose-500': inst.status === 'suspended',
                      }"
                    ></span>
                    {{ inst.status }}
                  </span>
                </td>

                <!-- Joined -->
                <td v-if="visibleColumns.joined" class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                  {{ inst.joined }}
                </td>

                <!-- Last Login -->
                <td v-if="visibleColumns.lastLogin" class="px-4 py-3.5 text-xs text-slate-400 font-medium whitespace-nowrap">
                  {{ inst.lastLogin }}
                </td>

                <!-- Actions -->
                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                  <div class="relative inline-block text-left">
                    <button
                      @click.stop="toggleActionDropdown(inst.id)"
                      type="button"
                      class="w-8 h-8 rounded-xl inline-flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-100 transition-all focus:outline-none"
                      :class="{ 'bg-indigo-50 text-indigo-700 border-indigo-200': activeActionDropdown === inst.id }"
                      title="Instructor Actions"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                      </svg>
                    </button>

                    <!-- Action Dropdown Menu -->
                    <div
                      v-if="activeActionDropdown === inst.id"
                      @click.stop
                      class="absolute right-0 w-44 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-50 select-none animate-in fade-in zoom-in-95 duration-100"
                      :class="index >= paginated.length - 2 && paginated.length > 2 ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
                    >
                      <!-- View Details -->
                      <button
                        @click="handleActionClick(() => viewInstructor(inst))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>View Details</span>
                      </button>

                      <!-- Edit Instructor -->
                      <button
                        @click="handleActionClick(() => openEdit(inst))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span>Edit Instructor</span>
                      </button>

                      <div class="h-px bg-slate-100 my-1"></div>

                      <!-- Delete Instructor -->
                      <button
                        @click="handleActionClick(() => confirmDelete(inst))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-rose-500 group-hover/item:text-rose-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Delete Instructor</span>
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
                    <p class="text-sm font-bold text-slate-800">No instructors found</p>
                    <p class="text-xs text-slate-500 mt-1">There are no instructors matching your current search or filters.</p>
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
              Showing <strong class="text-slate-800">{{ filtered.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }}</strong> to <strong class="text-slate-800">{{ Math.min(currentPage * perPage, filtered.length) }}</strong> of <strong class="text-slate-800">{{ filtered.length }}</strong> instructors
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

      <!-- ── Bottom Overview Grid ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Overview Doughnut Chart -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs">
          <h3 class="text-sm font-bold text-slate-900 mb-4">Instructor Overview</h3>
          <div class="relative w-40 h-40 mx-auto mb-4">
            <Doughnut :data="chartData" :options="chartOptions" />
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <span class="text-2xl font-black text-slate-800 leading-none">{{ stats.total }}</span>
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Faculty</span>
            </div>
          </div>
          <div class="space-y-2 text-xs">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-indigo-700"></div><span class="text-slate-600">Active Faculty</span></div>
              <span class="font-bold text-slate-800">{{ stats.active }} <span class="text-[11px] text-slate-400 font-normal">({{ stats.total ? Math.round((stats.active / stats.total) * 100) : 0 }}%)</span></span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div><span class="text-slate-600">Inactive Faculty</span></div>
              <span class="font-bold text-slate-800">{{ stats.inactive }} <span class="text-[11px] text-slate-400 font-normal">({{ stats.total ? Math.round((stats.inactive / stats.total) * 100) : 0 }}%)</span></span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-sky-500"></div><span class="text-slate-600">New This Month</span></div>
              <span class="font-bold text-slate-800">{{ stats.newInst }} <span class="text-[11px] text-slate-400 font-normal">({{ stats.total ? Math.round((stats.newInst / stats.total) * 100) : 0 }}%)</span></span>
            </div>
          </div>
        </div>

        <!-- Top Departments -->
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
              <div v-if="topDepartments.length === 0" class="text-xs text-slate-400 text-center py-4">No department assignments yet</div>
            </div>
          </div>
          <button @click="router.push('/admin/departments')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 text-left mt-3">
            Manage All Departments →
          </button>
        </div>

        <!-- Recent Registrations -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Recent Faculty Onboarding</h3>
            <div class="space-y-3">
              <div v-for="inst in recentRegistrations" :key="inst.id" class="flex items-center gap-3">
                <div :class="avatarBg(inst.id)" class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 border">
                  {{ inst.avatar }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-bold text-slate-800 truncate">{{ inst.name }}</p>
                  <p class="text-[10px] text-slate-400 truncate">{{ inst.departmentName }}</p>
                </div>
                <span class="text-[10px] text-slate-400 whitespace-nowrap shrink-0">{{ inst.joined }}</span>
              </div>
              <div v-if="recentRegistrations.length === 0" class="text-xs text-slate-400 text-center py-4">No registrations recorded</div>
            </div>
          </div>
          <p class="text-[11px] text-slate-400 mt-3">Sorted by registration timestamp</p>
        </div>

        <!-- Fast Shortcuts -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3">Administrative Actions</h3>
            <div class="grid grid-cols-2 gap-2.5">
              <button @click="openAdd" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 hover:border-indigo-300 hover:bg-indigo-50/40 group transition-all text-center">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-1.5 group-hover:scale-105 transition-transform">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                </div>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-indigo-700">Add Instructor</span>
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
    <!-- 2. INSTRUCTOR DETAIL VIEW                                              -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="viewingInstructor" class="space-y-6 pb-16 min-w-0 w-full">
      <!-- Breadcrumb Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <button
            @click="closeView"
            class="w-10 h-10 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-100 transition-colors text-slate-600"
            title="Back to Instructors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          </button>
          <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
              <span @click="closeView" class="hover:text-indigo-600 cursor-pointer">Instructors</span>
              <span>/</span>
              <span class="text-indigo-600 font-bold">Profile Details</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
              {{ viewingInstructor.name }}
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold capitalize border"
                :class="{
                  'bg-emerald-50 text-emerald-700 border-emerald-200': viewingInstructor.status === 'active',
                  'bg-slate-100 text-slate-700 border-slate-200': viewingInstructor.status === 'inactive',
                  'bg-rose-50 text-rose-700 border-rose-200': viewingInstructor.status === 'suspended',
                }"
              >
                {{ viewingInstructor.status }}
              </span>
            </h1>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="openEdit(viewingInstructor)"
            class="flex items-center gap-2 px-4 py-2.5 bg-white border border-indigo-300 text-indigo-700 font-bold rounded-xl text-xs hover:bg-indigo-50 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            Edit Profile
          </button>
          <button
            @click="confirmDelete(viewingInstructor)"
            class="flex items-center gap-2 px-4 py-2.5 bg-white border border-rose-200 text-rose-600 font-bold rounded-xl text-xs hover:bg-rose-50 transition-colors shadow-xs"
          >
            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Remove Instructor
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
                v-if="viewingInstructor.profilePicture"
                :src="viewingInstructor.profilePicture"
                class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl object-cover border-2 border-slate-100 shadow-sm shrink-0"
                :alt="viewingInstructor.name"
              />
              <div
                v-else
                :class="avatarBg(viewingInstructor.id)"
                class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl flex items-center justify-center text-3xl font-black border-2 shadow-sm shrink-0 select-none"
              >
                {{ viewingInstructor.avatar }}
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 flex-1 w-full text-xs">
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Full Name</p>
                  <p class="text-sm font-black text-slate-800">{{ viewingInstructor.name }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Employee ID</p>
                  <p class="text-sm font-mono font-bold text-slate-800">{{ viewingInstructor.employeeId }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Email Address</p>
                  <p class="text-sm font-semibold text-slate-700">{{ viewingInstructor.email }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Phone Number</p>
                  <p class="text-sm font-semibold text-slate-700">{{ viewingInstructor.phone }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Gender</p>
                  <p class="text-sm font-semibold text-slate-700">{{ viewingInstructor.gender }}</p>
                </div>
                <div>
                  <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Office Location</p>
                  <p class="text-sm font-semibold text-slate-700">{{ viewingInstructor.office || 'Wollo University Main Campus' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Academic Assignment -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-6 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Academic Assignment
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 text-xs">
              <div class="p-3.5 rounded-xl bg-slate-50/70 border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Department</p>
                <p class="text-sm font-bold text-slate-800">{{ viewingInstructor.departmentName }}</p>
              </div>
              <div class="p-3.5 rounded-xl bg-slate-50/70 border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Section</p>
                <p class="text-sm font-bold text-slate-800">{{ viewingInstructor.section }}</p>
              </div>
              <div class="p-3.5 rounded-xl bg-slate-50/70 border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Year Level</p>
                <p class="text-sm font-bold text-slate-800">{{ viewingInstructor.year }}</p>
              </div>
              <div class="col-span-1 sm:col-span-2 lg:col-span-3 p-3.5 rounded-xl bg-slate-50/70 border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Assigned Course(s)</p>
                <p class="text-sm font-semibold text-slate-800">{{ viewingInstructor.courses }}</p>
              </div>
              <div class="col-span-1 sm:col-span-2 lg:col-span-3 p-3.5 rounded-xl bg-slate-50/70 border border-slate-100">
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Co-Instructor Course(s)</p>
                <p class="text-sm font-semibold text-slate-800">{{ viewingInstructor.coCourses }}</p>
              </div>
            </div>
          </div>

          <!-- Account & Security Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-6 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Account & Credentials
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 text-xs">
              <div>
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Username</p>
                <p class="text-sm font-bold text-slate-800">{{ viewingInstructor.username || viewingInstructor.email }}</p>
              </div>
              <div>
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Role Permission</p>
                <p class="text-sm font-bold text-indigo-700 capitalize">{{ viewingInstructor.role }}</p>
              </div>
              <div>
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Account Created</p>
                <p class="text-sm font-bold text-slate-800">{{ viewingInstructor.joined }}</p>
              </div>
            </div>
          </div>

        </div>

        <!-- Right Summary Sidebar -->
        <div class="space-y-6">
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-xs">
            <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-4">Summary</h3>
            <div class="space-y-3 text-xs">
              <div class="flex items-center justify-between pb-2 border-b border-slate-50">
                <span class="text-slate-400">Account Status</span>
                <span class="font-bold capitalize" :class="viewingInstructor.status === 'active' ? 'text-emerald-600' : 'text-rose-600'">
                  {{ viewingInstructor.status }}
                </span>
              </div>
              <div class="flex items-center justify-between pb-2 border-b border-slate-50">
                <span class="text-slate-400">Department</span>
                <span class="font-bold text-slate-800 truncate max-w-[150px]">{{ viewingInstructor.departmentName }}</span>
              </div>
              <div class="flex items-center justify-between pb-2 border-b border-slate-50">
                <span class="text-slate-400">Employee ID</span>
                <span class="font-mono font-bold text-slate-800">{{ viewingInstructor.employeeId }}</span>
              </div>
              <div class="flex items-center justify-between pb-2 border-b border-slate-50">
                <span class="text-slate-400">Section</span>
                <span class="font-bold text-slate-800">{{ viewingInstructor.section }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-400">Role</span>
                <span class="font-bold text-indigo-700 capitalize">{{ viewingInstructor.role }}</span>
              </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 space-y-2">
              <button
                @click="openEdit(viewingInstructor)"
                class="w-full py-2.5 px-4 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl text-xs transition-colors flex items-center justify-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                Edit Instructor
              </button>
              <button
                @click="closeView"
                class="w-full py-2.5 px-4 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-xl text-xs transition-colors"
              >
                Back to Instructors
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- 3. ADD INSTRUCTOR VIEW                                                 -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="showAddPage" class="space-y-6 pb-16 min-w-0 w-full">
      <!-- Breadcrumb Header -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-6 flex items-center justify-between">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span class="hover:text-indigo-600 cursor-pointer" @click="closeAdd">Instructors</span>
            <span>/</span>
            <span class="text-indigo-600 font-bold">Add New Instructor</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Add New Instructor</h1>
        </div>
        <button
          @click="closeAdd"
          class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          Back to Instructors
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
                  v-model="newInstructor.name"
                  type="text"
                  placeholder="Enter full name"
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
                  v-model="newInstructor.email"
                  type="email"
                  placeholder="name@wu.edu.et"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.email ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('email')"
                />
                <p v-if="formErrors.email" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.email }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Phone Number <span class="text-rose-500">*</span></label>
                <input
                  id="field-phone"
                  v-model="newInstructor.phone"
                  type="text"
                  placeholder="e.g. 0911223344 or +251911223344"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.phone ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('phone')"
                />
                <p v-if="formErrors.phone" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.phone }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Gender <span class="text-rose-500">*</span></label>
                <select
                  id="field-gender"
                  v-model="newInstructor.gender"
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
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Profile Picture <span class="text-slate-400 font-normal">(Optional)</span></label>
                <div
                  class="border-2 border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center hover:border-indigo-600 hover:bg-slate-50 transition-colors cursor-pointer overflow-hidden relative"
                  @click="triggerFileInput"
                >
                  <input type="file" ref="fileInput" class="hidden" accept="image/png, image/jpeg, image/webp" @change="handleProfilePicture" />
                  <template v-if="previewUrl">
                    <img :src="previewUrl" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow-sm mb-1" />
                    <span class="text-xs font-bold text-indigo-700">Change Photo</span>
                    <span class="text-[10px] text-slate-400 truncate max-w-[80%]">{{ newInstructor.profilePicture?.name }}</span>
                  </template>
                  <template v-else>
                    <svg class="w-6 h-6 text-slate-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                    <span class="text-xs font-bold text-slate-700">Upload Photo</span>
                    <span class="text-[10px] text-slate-400">PNG, JPG up to 2MB</span>
                  </template>
                </div>
              </div>
            </div>
          </div>

          <!-- Professional Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs space-y-5">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Professional Information
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Department <span class="text-rose-500">*</span></label>
                <select
                  id="field-department_id"
                  v-model="newInstructor.department_id"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors bg-white"
                  :class="formErrors.department_id ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @change="validateField('department_id')"
                >
                  <option value="">Select department</option>
                  <option v-for="d in allDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <p v-if="formErrors.department_id" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.department_id }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Employee ID <span class="text-rose-500">*</span></label>
                <input
                  id="field-employeeId"
                  v-model="newInstructor.employeeId"
                  type="text"
                  placeholder="Enter employee ID (e.g. WU-INS-0101)"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.employeeId ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('employeeId')"
                />
                <p v-if="formErrors.employeeId" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.employeeId }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Semester <span class="text-slate-400 font-normal">(Current Term)</span></label>
                <input
                  type="text"
                  disabled
                  :value="settingsStore.formattedAcademicTerm"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-500 bg-slate-50 cursor-not-allowed font-bold"
                />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Academic Year Level <span class="text-slate-400 font-normal">(Optional)</span></label>
                <select
                  v-model="newInstructor.year"
                  class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-600 bg-white"
                >
                  <option value="">Select level (optional)</option>
                  <option value="1st Year">1st Year</option>
                  <option value="2nd Year">2nd Year</option>
                  <option value="3rd Year">3rd Year</option>
                  <option value="4th Year">4th Year</option>
                  <option value="5th Year">5th Year</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Account Information & Password -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 sm:p-8 shadow-xs space-y-5">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              Account Credentials
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Username <span class="text-rose-500">*</span></label>
                <input
                  id="field-username"
                  v-model="newInstructor.username"
                  type="text"
                  placeholder="Enter username (min 3 chars)"
                  class="w-full border rounded-xl px-4 py-2.5 text-xs focus:outline-none transition-colors"
                  :class="formErrors.username ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                  @input="validateField('username')"
                />
                <p v-if="formErrors.username" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.username }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    id="field-password"
                    v-model="newInstructor.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="Enter password"
                    class="w-full border rounded-xl pl-4 pr-10 py-2.5 text-xs focus:outline-none transition-colors"
                    :class="formErrors.password ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                    @input="validateField('password')"
                  />
                  <button @click="showPassword = !showPassword" type="button" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                    <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                  </button>
                </div>
                <p v-if="formErrors.password" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.password }}
                </p>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Confirm Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    id="field-confirmPassword"
                    v-model="newInstructor.confirmPassword"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    placeholder="Confirm password"
                    class="w-full border rounded-xl pl-4 pr-10 py-2.5 text-xs focus:outline-none transition-colors"
                    :class="formErrors.confirmPassword ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-indigo-600'"
                    @input="validateField('confirmPassword')"
                  />
                  <button @click="showConfirmPassword = !showConfirmPassword" type="button" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                    <svg v-if="!showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                  </button>
                </div>
                <p v-if="formErrors.confirmPassword" class="text-[11px] font-semibold text-rose-500 mt-1 flex items-center gap-1">
                  {{ formErrors.confirmPassword }}
                </p>
              </div>
            </div>
          </div>

          <!-- Bottom Form Actions -->
          <div class="flex items-center justify-between pt-2">
            <button
              @click="closeAdd"
              class="px-6 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="addInstructor"
              :disabled="isLoading"
              class="flex items-center gap-2 px-6 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-xs shadow-indigo-200 disabled:opacity-50"
            >
              <svg v-if="isLoading" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
              <span>{{ isLoading ? 'Creating Instructor...' : 'Create Instructor' }}</span>
            </button>
          </div>
        </div>

        <!-- Right Sidebars: Account Summary & Password Checklist -->
        <div class="space-y-6">
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-xs">
            <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-4">Account Summary</h3>
            <div class="flex items-center gap-3 mb-5">
              <div class="w-12 h-12 bg-indigo-50 text-indigo-700 rounded-xl flex items-center justify-center font-black">
                {{ newInstructor.name ? newInstructor.name.slice(0, 2).toUpperCase() : 'WU' }}
              </div>
              <div class="min-w-0">
                <p class="text-xs font-bold text-slate-800 truncate">{{ newInstructor.name || 'New Instructor' }}</p>
                <p class="text-[11px] text-slate-400 font-mono truncate">{{ newInstructor.employeeId || 'ID pending' }}</p>
              </div>
            </div>
            <div class="space-y-3 text-xs">
              <div class="flex justify-between items-center pb-2 border-b border-slate-50">
                <span class="text-slate-400">Department</span>
                <span class="font-bold text-slate-800">{{ allDepartments.find(d => d.id === newInstructor.department_id)?.name || 'Not Selected' }}</span>
              </div>
              <div class="flex justify-between items-center pb-2 border-b border-slate-50">
                <span class="text-slate-400">Role</span>
                <span class="font-bold text-indigo-700">Instructor</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-slate-400">Account Status</span>
                <span class="font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded text-[11px]">Active</span>
              </div>
            </div>
          </div>

          <!-- Real-Time Password Checklist -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-xs">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Password Checklist</h3>
              <span v-if="isPasswordValid" class="px-2 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200">
                Valid
              </span>
            </div>
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
            <h3 class="text-base font-bold text-slate-800 mb-2">Remove Instructor?</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
              Are you sure you want to remove <strong class="text-slate-800">{{ selectedInstructor?.name }}</strong>? This action will revoke their credentials and unassign active courses.
            </p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="deleteInstructor" :disabled="isLoading" class="flex-1 py-2.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors shadow-xs disabled:opacity-50">
              {{ isLoading ? 'Removing...' : 'Confirm Remove' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Modal -->
      <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 border border-slate-100">
          <div class="flex items-center justify-between px-6 py-4.5 border-b border-slate-100 shrink-0">
            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Edit Instructor Details</h3>
            <button @click="showEditModal = false" class="p-1 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="p-6 overflow-y-auto space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Full Name <span class="text-rose-500">*</span></label>
                <input v-model="editInstructorForm.name" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email Address <span class="text-rose-500">*</span></label>
                <input v-model="editInstructorForm.email" type="email" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number</label>
                <input v-model="editInstructorForm.phone" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Gender</label>
                <select v-model="editInstructorForm.gender" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="">Select gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Department</label>
                <select v-model="editInstructorForm.department_id" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600 bg-white">
                  <option value="">Select Department</option>
                  <option v-for="d in allDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Employee ID</label>
                <input v-model="editInstructorForm.employeeId" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Username</label>
                <input v-model="editInstructorForm.username" type="text" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">New Password (Optional)</label>
                <input v-model="editInstructorForm.password" type="password" placeholder="Leave blank to keep current" class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs focus:outline-none focus:border-indigo-600" />
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
                <h3 class="text-base font-bold text-slate-800">Batch Import Instructors</h3>
                <p class="text-xs text-slate-500">Upload a CSV or PDF file to register multiple instructors.</p>
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
                Required columns: <strong class="text-slate-900">Full Name, Email, Phone, Department</strong>.
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
              <span>{{ isImporting ? 'Importing Instructors...' : 'Upload & Import' }}</span>
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
              <h3 class="text-base font-bold text-slate-800">Instructors Imported Successfully!</h3>
              <p class="text-xs text-slate-500 mt-1">{{ importSuccessMessage }}</p>
            </div>
            <div v-if="importedInstructorsList.length > 0" class="max-h-40 overflow-y-auto border border-slate-100 rounded-xl divide-y divide-slate-100 text-left">
              <div v-for="inst in importedInstructorsList" :key="inst.id" class="p-2.5 flex items-center justify-between bg-slate-50/50 text-xs">
                <div>
                  <p class="font-bold text-slate-800">{{ inst.name }}</p>
                  <p class="text-[10px] text-slate-400">{{ inst.email }}</p>
                </div>
                <span class="px-2 py-0.5 font-bold rounded bg-indigo-50 text-indigo-700 text-[10px]">
                  {{ inst.department }}
                </span>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end bg-slate-50/50">
            <button @click="showImportSuccessModal = false" class="w-full sm:w-auto px-6 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors">
              Done & View Instructors
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