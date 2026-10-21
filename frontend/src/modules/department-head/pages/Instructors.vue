<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()

// ── View States ──
const currentView = ref<'list' | 'add' | 'edit'>('list')
const showDetailModal = ref(false)
const showStatusModal = ref(false)
const showDeleteModal = ref(false)
const showTeachingModal = ref(false)

// ── Data & Loading ──
const isLoading = ref(true)
const isExporting = ref(false)
const errorMessage = ref<string | null>(null)
const successToast = ref<string | null>(null)

const showToast = (msg: string) => {
  successToast.value = msg
  setTimeout(() => {
    successToast.value = null
  }, 4000)
}

// ── Search & Filter State ──
const search = ref('')
const searchDebounceTimer = ref<any>(null)
const statusFilter = ref('all')
const employmentFilter = ref('all')
const courseFilter = ref('all')
const yearFilter = ref('all')
const semesterFilter = ref('all')
const sectionFilter = ref('all')
const assignmentFilter = ref('all')

// ── Pagination State ──
const currentPage = ref(1)
const perPage = ref(10)
const totalItems = ref(0)
const lastPage = ref(1)
const fromItem = ref(0)
const toItem = ref(0)

// ── Sorting ──
const sortBy = ref('name')
const sortOrder = ref<'asc' | 'desc'>('asc')

// ── Instructors Dataset ──
const instructors = ref<any[]>([])
const selectedInstructor = ref<any>(null)
const instructorDetail = ref<any>(null)
const isLoadingDetail = ref(false)

// ── Active 3-Dot Dropdown Row ──
const openDropdownId = ref<number | null>(null)

const toggleDropdown = (id: number, e: Event) => {
  e.stopPropagation()
  if (openDropdownId.value === id) {
    openDropdownId.value = null
  } else {
    openDropdownId.value = id
  }
}

// ── Department & Stats Data ──
const department = ref({
  id: null as number | null,
  name: 'Department',
  code: 'DEPT',
  college: 'College of Computing and Informatics'
})

const stats = ref({
  total: 0,
  full_time: 0,
  part_time: 0,
  on_leave: 0,
  active: 0,
  inactive: 0,
  with_courses: 0,
  without_courses: 0,
})

const filterOptions = ref({
  courses: [] as any[],
  years: [] as string[],
  semesters: [] as string[],
  sections: [] as string[],
})

// ── Active Academic Term Badge ──
const activeTerm = computed(() => {
  const y = settingsStore.academicYear || '2026'
  const s = settingsStore.semester || 'Second Semester'
  return `${y} • ${s}`
})

// ── Fetch Instructors from Backend ──
const fetchInstructors = async () => {
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
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (employmentFilter.value !== 'all') params.employment_type = employmentFilter.value
    if (courseFilter.value !== 'all') params.course_id = courseFilter.value
    if (yearFilter.value !== 'all') params.year = yearFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (assignmentFilter.value !== 'all') params.assignment = assignmentFilter.value

    const res = await apiClient.get('/dept-head/instructors', { params })
    const resData = res.data

    instructors.value = resData.data || []

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
    console.error('Failed to load instructors:', err)
    errorMessage.value = err?.response?.data?.message || 'Failed to load department instructors. Please try again.'
  } finally {
    isLoading.value = false
  }
}

// Watch filters to trigger debounced or instant re-fetch
const onSearchInput = () => {
  clearTimeout(searchDebounceTimer.value)
  searchDebounceTimer.value = setTimeout(() => {
    currentPage.value = 1
    fetchInstructors()
  }, 350)
}

watch(
  [statusFilter, employmentFilter, courseFilter, yearFilter, semesterFilter, sectionFilter, assignmentFilter, perPage, sortBy, sortOrder],
  () => {
    currentPage.value = 1
    fetchInstructors()
  }
)

const resetAllFilters = () => {
  search.value = ''
  statusFilter.value = 'all'
  employmentFilter.value = 'all'
  courseFilter.value = 'all'
  yearFilter.value = 'all'
  semesterFilter.value = 'all'
  sectionFilter.value = 'all'
  assignmentFilter.value = 'all'
  currentPage.value = 1
  fetchInstructors()
}

const activeFiltersCount = computed(() => {
  let count = 0
  if (search.value.trim()) count++
  if (statusFilter.value !== 'all') count++
  if (employmentFilter.value !== 'all') count++
  if (courseFilter.value !== 'all') count++
  if (yearFilter.value !== 'all') count++
  if (semesterFilter.value !== 'all') count++
  if (sectionFilter.value !== 'all') count++
  if (assignmentFilter.value !== 'all') count++
  return count
})

// ── Avatar Helper ──
const resolveAvatarUrl = (url: string | null | undefined): string | null => {
  if (!url) return null
  if (url.startsWith('http://localhost/') && !url.startsWith('http://localhost:8000/')) {
    return url.replace('http://localhost/', 'http://localhost:8000/')
  }
  return url
}

// ── Open Instructor Detail ──
const openDetail = async (inst: any) => {
  selectedInstructor.value = inst
  showDetailModal.value = true
  isLoadingDetail.value = true
  instructorDetail.value = null

  try {
    const res = await apiClient.get(`/dept-head/instructors/${inst.id}`)
    instructorDetail.value = res.data?.data || inst
  } catch (err) {
    console.error('Failed to load detailed profile:', err)
    instructorDetail.value = inst
  } finally {
    isLoadingDetail.value = false
  }
}

// ── Open Teaching Assignments Modal ──
const openTeaching = (inst: any) => {
  selectedInstructor.value = inst
  showTeachingModal.value = true
}

// ── Status Change Handling ──
const statusToUpdate = ref<'active' | 'on_leave' | 'inactive'>('active')
const openStatusDialog = (inst: any, newStatus: 'active' | 'on_leave' | 'inactive') => {
  selectedInstructor.value = inst
  statusToUpdate.value = newStatus
  showStatusModal.value = true
  openDropdownId.value = null
}

const confirmStatusChange = async () => {
  if (!selectedInstructor.value) return
  isLoading.value = true
  try {
    await apiClient.patch(`/dept-head/instructors/${selectedInstructor.value.id}/status`, {
      status: statusToUpdate.value
    })
    showStatusModal.value = false
    showToast(`Instructor status updated to ${statusToUpdate.value.replace('_', ' ')}.`)
    await fetchInstructors()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to update instructor status.')
  } finally {
    isLoading.value = false
  }
}

// ── Delete / Deactivate Handling ──
const confirmDelete = (inst: any) => {
  selectedInstructor.value = inst
  showDeleteModal.value = true
  openDropdownId.value = null
}

const executeDelete = async () => {
  if (!selectedInstructor.value) return
  isLoading.value = true
  try {
    await apiClient.delete(`/dept-head/instructors/${selectedInstructor.value.id}`)
    showDeleteModal.value = false
    showToast('Instructor deleted successfully.')
    await fetchInstructors()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to delete instructor.')
  } finally {
    isLoading.value = false
  }
}

// ── Add Instructor Form State ──
const addForm = ref({
  fullName: '',
  email: '',
  phone: '',
  gender: '',
  profilePicture: null as File | null,
  employeeId: '',
  yearLevel: '',
  semester: '',
  section: '',
  username: '',
  passwordMode: 'manual' as 'manual' | 'auto',
  password: '',
  confirmPassword: '',
  employmentType: 'full_time',
  status: 'active',
})
const addPhotoPreview = ref<string | null>(null)
const addFileInput = ref<HTMLInputElement | null>(null)
const showAddPassword = ref(false)
const showAddConfirmPassword = ref(false)
const passwordTouched = ref(false)
const confirmPasswordTouched = ref(false)
const addFormErrors = ref<Record<string, string>>({})
const addFormExistingInstructor = ref<{ id: number; name: string; email: string } | null>(null)

const existingInstructorInDept = computed(() => {
  const email = addForm.value.email.trim().toLowerCase()
  if (!email) return null
  return instructors.value.find((i: any) => i.email?.toLowerCase() === email) || null
})

// ── Password Validation Criteria (aligned with backend regex) ──
const addPasswordCriteria = computed(() => {
  const pwd = addForm.value.password || ''
  return {
    length: pwd.length >= 8,
    uppercase: /[A-Z]/.test(pwd),
    lowercase: /[a-z]/.test(pwd),
    number: /[0-9]/.test(pwd),
    special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>/?`~]/.test(pwd),
  }
})

const isAddPasswordValid = computed(() => {
  const c = addPasswordCriteria.value
  return c.length && c.uppercase && c.lowercase && c.number && c.special
})

const addPasswordStrength = computed(() => {
  const c = addPasswordCriteria.value
  const metCount = [c.length, c.uppercase, c.lowercase, c.number, c.special].filter(Boolean).length
  if (!addForm.value.password) {
    return { count: 0, text: '', color: 'bg-slate-200', textClass: 'text-slate-400' }
  }
  if (metCount <= 2) {
    return { count: metCount, text: 'Weak', color: 'bg-rose-500', textClass: 'text-rose-600' }
  }
  if (metCount <= 4) {
    return { count: metCount, text: 'Medium', color: 'bg-amber-500', textClass: 'text-amber-600' }
  }
  return { count: 5, text: 'Strong', color: 'bg-emerald-500', textClass: 'text-emerald-600' }
})

const onPasswordInput = () => {
  passwordTouched.value = true
  if (!addForm.value.password) {
    addFormErrors.value.password = 'Password is required'
  } else if (!isAddPasswordValid.value) {
    addFormErrors.value.password = 'Password must meet all 5 security requirements'
  } else {
    delete addFormErrors.value.password
  }

  if (confirmPasswordTouched.value || addForm.value.confirmPassword) {
    onConfirmPasswordInput()
  }
}

const onConfirmPasswordInput = () => {
  confirmPasswordTouched.value = true
  if (!addForm.value.confirmPassword) {
    addFormErrors.value.confirmPassword = 'Confirm password is required'
  } else if (addForm.value.confirmPassword !== addForm.value.password) {
    addFormErrors.value.confirmPassword = 'Passwords do not match'
  } else {
    delete addFormErrors.value.confirmPassword
  }
}

const triggerAddFileInput = () => addFileInput.value?.click()

const handleAddPhotoUpload = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    if (file.size > 2 * 1024 * 1024) {
      addFormErrors.value.profilePicture = 'Photo must be under 2MB'
      return
    }
    delete addFormErrors.value.profilePicture
    addForm.value.profilePicture = file
    addPhotoPreview.value = URL.createObjectURL(file)
  }
}

const removeAddPhoto = () => {
  addForm.value.profilePicture = null
  if (addPhotoPreview.value) {
    URL.revokeObjectURL(addPhotoPreview.value)
    addPhotoPreview.value = null
  }
  if (addFileInput.value) addFileInput.value.value = ''
}

const resetAddForm = () => {
  addForm.value = {
    fullName: '',
    email: '',
    phone: '',
    gender: 'Male',
    profilePicture: null,
    employeeId: '',
    yearLevel: 'None',
    semester: settingsStore.semester || 'Second Semester',
    section: 'Section A',
    username: '',
    passwordMode: 'manual',
    password: '',
    confirmPassword: '',
    employmentType: 'full_time',
    status: 'active',
  }
  removeAddPhoto()
  addFormErrors.value = {}
  addFormExistingInstructor.value = null
  showAddPassword.value = false
  showAddConfirmPassword.value = false
  passwordTouched.value = false
  confirmPasswordTouched.value = false
}

const openAddView = async () => {
  try {
    await settingsStore.fetchSettings()
  } catch (e) {
    // ignore
  }
  resetAddForm()
  addForm.value.semester = settingsStore.semester || 'Second Semester'
  currentView.value = 'add'
}

const validateAddForm = (): boolean => {
  const errs: Record<string, string> = {}
  if (!addForm.value.fullName.trim()) errs.fullName = 'Full name is required'
  if (!addForm.value.email.trim()) errs.email = 'Email address is required'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addForm.value.email.trim())) errs.email = 'Enter a valid email'
  if (!addForm.value.phone.trim()) errs.phone = 'Phone number is required'
  if (!addForm.value.employeeId.trim()) errs.employeeId = 'Employee ID is required'
  if (!addForm.value.username.trim()) errs.username = 'Username is required'
  
  if (addForm.value.passwordMode === 'manual') {
    if (!addForm.value.password) {
      errs.password = 'Password is required'
    } else if (!isAddPasswordValid.value) {
      errs.password = 'Password must meet all 5 requirements below'
    }

    if (!addForm.value.confirmPassword) {
      errs.confirmPassword = 'Confirm password is required'
    } else if (addForm.value.password !== addForm.value.confirmPassword) {
      errs.confirmPassword = 'Passwords do not match'
    }
    passwordTouched.value = true
    confirmPasswordTouched.value = true
  }

  addFormErrors.value = errs
  return Object.keys(errs).length === 0
}

const submitAddInstructor = async () => {
  if (!validateAddForm()) return
  isLoading.value = true
  try {
    const formData = new FormData()
    formData.append('name', addForm.value.fullName.trim())
    formData.append('email', addForm.value.email.trim())
    formData.append('phone', addForm.value.phone.trim())
    formData.append('gender', addForm.value.gender)
    formData.append('id_no', addForm.value.employeeId.trim())
    formData.append('year_level', addForm.value.yearLevel)
    formData.append('semester', settingsStore.semester || addForm.value.semester || 'Second Semester')
    formData.append('section', addForm.value.section)
    formData.append('username', addForm.value.username.trim())
    formData.append('password_mode', addForm.value.passwordMode)
    if (addForm.value.passwordMode === 'manual') {
      formData.append('password', addForm.value.password)
    }
    formData.append('employment_type', addForm.value.employmentType)
    formData.append('status', addForm.value.status)
    if (addForm.value.profilePicture) {
      formData.append('profile_picture', addForm.value.profilePicture)
    }

    const res = await apiClient.post('/dept-head/instructors', formData)
    showToast(res.data?.message || 'Instructor created successfully in department.')
    currentView.value = 'list'
    await fetchInstructors()
  } catch (err: any) {
    const errData = err?.response?.data
    const serverErrors = errData?.errors
    if (serverErrors) {
      const fieldMap: Record<string, string> = {
        name: 'fullName',
        email: 'email',
        phone: 'phone',
        id_no: 'employeeId',
        username: 'username',
        password: 'password',
        gender: 'gender',
      }
      for (const [key, msgs] of Object.entries(serverErrors)) {
        const target = fieldMap[key] || key
        addFormErrors.value[target] = Array.isArray(msgs) ? msgs[0] : (msgs as string)
      }
    }

    if (errData?.error_type === 'instructor_already_in_department') {
      addFormExistingInstructor.value = {
        id: errData.instructor_id,
        name: errData.instructor_name || addForm.value.fullName,
        email: addForm.value.email.trim(),
      }
      showToast('This instructor is already registered in your department.')
    } else {
      const msg = errData?.message || (serverErrors ? Object.values(serverErrors).flat().join(' ') : 'Failed to create instructor.')
      showToast(msg)
    }
  } finally {
    isLoading.value = false
  }
}

// ── Resend Credentials State & Handler ──
const isResendingCredentials = ref<number | null>(null)

const handleResendCredentials = async (inst: any) => {
  if (!confirm(`Are you sure you want to generate a new secure password and dispatch it via email to ${inst.name} (${inst.email})?`)) {
    return
  }

  isResendingCredentials.value = inst.id
  try {
    const res = await apiClient.post(`/dept-head/instructors/${inst.id}/resend-credentials`)
    showToast(res.data?.message || `A new password was generated and emailed to ${inst.email}.`)
    await fetchInstructors()
    if (selectedInstructor.value && selectedInstructor.value.id === inst.id) {
      selectedInstructor.value.password_delivery_status = 'delivered'
    }
    if (instructorDetail.value && instructorDetail.value.id === inst.id) {
      instructorDetail.value.password_delivery_status = 'delivered'
    }
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to resend credentials.')
  } finally {
    isResendingCredentials.value = null
  }
}

// ── Edit Instructor Form State ──
const editForm = ref({
  id: null as number | null,
  fullName: '',
  email: '',
  phone: '',
  gender: '',
  employeeId: '',
  yearLevel: '',
  semester: '',
  section: '',
  employmentType: 'full_time',
  status: 'active',
  profilePicture: null as File | null,
  password: '',
})
const editPhotoPreview = ref<string | null>(null)
const editFileInput = ref<HTMLInputElement | null>(null)
const showEditPassword = ref(false)

const triggerEditFileInput = () => editFileInput.value?.click()

const handleEditPhotoUpload = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    if (file.size > 2 * 1024 * 1024) {
      alert('Photo must be less than 2MB')
      return
    }
    editForm.value.profilePicture = file
    editPhotoPreview.value = URL.createObjectURL(file)
  }
}

const openEditView = (inst: any) => {
  selectedInstructor.value = inst
  editForm.value = {
    id: inst.id,
    fullName: inst.name || '',
    email: inst.email || '',
    phone: inst.phone === '—' ? '' : (inst.phone || ''),
    gender: inst.gender || 'Male',
    employeeId: inst.id_no || '',
    yearLevel: inst.year_level === '—' || !inst.year_level ? 'None' : inst.year_level,
    semester: inst.semester === '—' ? 'First Semester' : inst.semester,
    section: inst.section === '—' ? 'Section A' : inst.section,
    employmentType: inst.employment_type || 'full_time',
    status: inst.status || 'active',
    profilePicture: null,
    password: '',
  }
  editPhotoPreview.value = resolveAvatarUrl(inst.profile_picture_url)
  currentView.value = 'edit'
  openDropdownId.value = null
}

const submitEditInstructor = async () => {
  if (!editForm.value.id || !editForm.value.fullName.trim() || !editForm.value.email.trim()) {
    alert('Full name and email are required.')
    return
  }
  isLoading.value = true
  try {
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('name', editForm.value.fullName.trim())
    formData.append('email', editForm.value.email.trim())
    formData.append('phone', editForm.value.phone.trim())
    formData.append('gender', editForm.value.gender)
    formData.append('id_no', editForm.value.employeeId.trim())
    formData.append('year_level', editForm.value.yearLevel)
    formData.append('semester', editForm.value.semester)
    formData.append('section', editForm.value.section)
    formData.append('employment_type', editForm.value.employmentType)
    formData.append('status', editForm.value.status)
    if (editForm.value.password) {
      const pwd = editForm.value.password
      const isStrong = pwd.length >= 8 &&
        /[A-Z]/.test(pwd) &&
        /[a-z]/.test(pwd) &&
        /[0-9]/.test(pwd) &&
        /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>/?`~]/.test(pwd)
      if (!isStrong) {
        alert('Password must be at least 8 characters and include uppercase, lowercase, number, and special character.')
        isLoading.value = false
        return
      }
      formData.append('password', editForm.value.password)
    }
    if (editForm.value.profilePicture) {
      formData.append('profile_picture', editForm.value.profilePicture)
    }

    await apiClient.post(`/dept-head/instructors/${editForm.value.id}`, formData)
    showToast('Instructor updated successfully.')
    currentView.value = 'list'
    await fetchInstructors()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to update instructor.')
  } finally {
    isLoading.value = false
  }
}

// ── Export Handling ──
const showExportMenu = ref(false)

const handleExport = async (format: 'pdf' | 'excel' | 'csv') => {
  showExportMenu.value = false
  isExporting.value = true

  try {
    const params: Record<string, any> = { format }
    if (search.value.trim()) params.search = search.value.trim()
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (employmentFilter.value !== 'all') params.employment_type = employmentFilter.value
    if (courseFilter.value !== 'all') params.course_id = courseFilter.value
    if (yearFilter.value !== 'all') params.year = yearFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value

    const res = await apiClient.get('/dept-head/instructors/export', { params })
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

    showToast(`Exported instructors as ${format.toUpperCase()}.`)
  } catch (err: any) {
    console.error('Export error:', err)
    alert(err?.response?.data?.message || 'Failed to export instructors. Please try again.')
  } finally {
    isExporting.value = false
  }
}

// Close outside click for 3-dot dropdown and export menu
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
  fetchInstructors()
  settingsStore.fetchSettings()
  window.addEventListener('click', handleGlobalClick)
})
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
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div>
          <p class="text-sm font-bold text-rose-900">Failed to Load Instructors</p>
          <p class="text-xs text-rose-700 mt-0.5">{{ errorMessage }}</p>
        </div>
      </div>
      <button
        @click="fetchInstructors()"
        class="px-3 py-1.5 bg-rose-600 text-white text-xs font-semibold rounded-lg hover:bg-rose-700 transition cursor-pointer shrink-0"
      >
        Retry
      </button>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW: ADD INSTRUCTOR FORM                                                 -->
    <!-- ========================================================================= -->
    <div v-if="currentView === 'add'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6 space-y-6">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-[#5138ed]">Faculty Registration</span>
          <h2 class="text-xl font-black text-slate-900 tracking-tight">Add Department Instructor</h2>
          <p class="text-xs text-slate-500 mt-0.5">Assigned to: <strong class="text-slate-800">{{ department.name }} ({{ department.code }})</strong></p>
        </div>
        <button
          @click="currentView = 'list'"
          class="px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer"
        >
          Cancel & Return
        </button>
      </div>

      <div class="space-y-6">
        <!-- Banner if Instructor is already registered in Department -->
        <div
          v-if="addFormExistingInstructor || existingInstructorInDept"
          class="p-4 rounded-2xl bg-amber-50/80 border border-amber-200/90 text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs"
        >
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="text-xs space-y-0.5">
              <p class="font-bold text-slate-900">
                Instructor Already Registered in Department
              </p>
              <p class="text-slate-600 text-[11px]">
                <strong>{{ (addFormExistingInstructor || existingInstructorInDept)?.name }}</strong>
                ({{ (addFormExistingInstructor || existingInstructorInDept)?.email }}) is already in your department faculty roster.
                You can generate and dispatch a new secure password directly to their email address right now.
              </p>
            </div>
          </div>
          <button
            type="button"
            @click="handleResendCredentials(addFormExistingInstructor || existingInstructorInDept)"
            :disabled="isResendingCredentials === (addFormExistingInstructor || existingInstructorInDept)?.id"
            class="px-4 py-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition cursor-pointer shrink-0 shadow-sm flex items-center gap-1.5 disabled:opacity-50"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span>{{ isResendingCredentials === (addFormExistingInstructor || existingInstructorInDept)?.id ? 'Sending...' : 'Resend Password Email Now' }}</span>
          </button>
        </div>

        <!-- Section 1: Personal Details -->
        <div>
          <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3">1. Personal Information</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Full Name <span class="text-rose-500">*</span></label>
              <input
                v-model="addForm.fullName"
                type="text"
                placeholder="e.g. Dr. Abebe Bikila"
                @input="delete addFormErrors.fullName"
                class="w-full px-3.5 py-2 text-xs border rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
                :class="addFormErrors.fullName ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200'"
              />
              <p v-if="addFormErrors.fullName" class="text-[10px] text-rose-600 mt-0.5">{{ addFormErrors.fullName }}</p>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Email Address <span class="text-rose-500">*</span></label>
              <input
                v-model="addForm.email"
                type="email"
                placeholder="instructor@wollo.edu.et"
                @input="delete addFormErrors.email; addFormExistingInstructor = null"
                class="w-full px-3.5 py-2 text-xs border rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
                :class="addFormErrors.email ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200'"
              />
              <p v-if="addFormErrors.email" class="text-[10px] text-rose-600 mt-0.5">{{ addFormErrors.email }}</p>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number <span class="text-rose-500">*</span></label>
              <input
                v-model="addForm.phone"
                type="text"
                placeholder="+251 9XX XXX XXX"
                @input="delete addFormErrors.phone"
                class="w-full px-3.5 py-2 text-xs border rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
                :class="addFormErrors.phone ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200'"
              />
              <p v-if="addFormErrors.phone" class="text-[10px] text-rose-600 mt-0.5">{{ addFormErrors.phone }}</p>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Gender</label>
              <select
                v-model="addForm.gender"
                class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
              >
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Profile Photo</label>
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                  <img v-if="addPhotoPreview" :src="addPhotoPreview" class="w-full h-full object-cover" />
                  <svg v-else class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                </div>
                <input ref="addFileInput" type="file" accept="image/*" class="hidden" @change="handleAddPhotoUpload" />
                <button
                  type="button"
                  @click="triggerAddFileInput"
                  class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer"
                >
                  {{ addPhotoPreview ? 'Change Photo' : 'Upload Photo' }}
                </button>
                <button
                  v-if="addPhotoPreview"
                  type="button"
                  @click="removeAddPhoto"
                  class="text-xs text-rose-500 font-bold hover:underline cursor-pointer"
                >
                  Remove
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 2: Employment & Department Info -->
        <div class="pt-4 border-t border-slate-100">
          <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3">2. Employment & Teaching Information</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Employee / Staff ID <span class="text-rose-500">*</span></label>
              <input
                v-model="addForm.employeeId"
                type="text"
                placeholder="e.g. WU-INS-042"
                @input="delete addFormErrors.employeeId"
                class="w-full px-3.5 py-2 text-xs border rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
                :class="addFormErrors.employeeId ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200'"
              />
              <p v-if="addFormErrors.employeeId" class="text-[10px] text-rose-600 mt-0.5">{{ addFormErrors.employeeId }}</p>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Employment Type</label>
              <select
                v-model="addForm.employmentType"
                class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
              >
                <option value="full_time">Full-Time Faculty</option>
                <option value="part_time">Part-Time / Adjunct</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Initial Status</label>
              <select
                v-model="addForm.status"
                class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
              >
                <option value="active">Active</option>
                <option value="on_leave">On Leave</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Assigned Year Level</label>
              <select
                v-model="addForm.yearLevel"
                class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
              >
                <option value="None">None (All Academic Years)</option>
                <option value="1st Year">1st Year</option>
                <option value="2nd Year">2nd Year</option>
                <option value="3rd Year">3rd Year</option>
                <option value="4th Year">4th Year</option>
                <option value="5th Year">5th Year</option>
              </select>
              <p class="text-[10px] text-slate-400 mt-1">Select 'None' to allow course assignments across all academic year levels.</p>
            </div>
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-bold text-slate-700">Semester</label>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-[#5138ed] border border-indigo-100">
                  <span class="w-1.5 h-1.5 rounded-full bg-[#5138ed]"></span>
                  System Assigned
                </span>
              </div>
              <div class="w-full px-3.5 py-2 text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl text-slate-800 flex items-center justify-between shadow-2xs select-none">
                <span class="flex items-center gap-2 truncate">
                  <svg class="w-4 h-4 text-[#5138ed] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <span class="font-bold text-slate-900 truncate">{{ settingsStore.semester || 'Second Semester' }}</span>
                </span>
                <span class="text-[10px] text-slate-400 font-medium shrink-0">Admin Defined</span>
              </div>
              <p class="text-[10px] text-slate-400 mt-1">Configured globally by Super Admin</p>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Assigned Section</label>
              <select
                v-model="addForm.section"
                class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
              >
                <option value="Section A">Section A</option>
                <option value="Section B">Section B</option>
                <option value="Both Sections">Both Sections</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Section 3: Account Credentials -->
        <div class="pt-4 border-t border-slate-100">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div>
              <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">3. Account Credentials</h3>
              <p class="text-xs text-slate-400 mt-0.5">Select how you want to provision initial login credentials for this instructor.</p>
            </div>
          </div>

          <!-- Password Creation Method Selector (Two Radio Cards) -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mb-5">
            <!-- Option A: Manually Set Password -->
            <label
              class="relative flex items-start gap-3.5 p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200 select-none"
              :class="addForm.passwordMode === 'manual'
                ? 'border-[#5138ed] bg-indigo-50/20 shadow-xs ring-2 ring-[#5138ed]/10'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'"
            >
              <input
                type="radio"
                name="passwordMode"
                value="manual"
                v-model="addForm.passwordMode"
                class="mt-1 h-4 w-4 text-[#5138ed] focus:ring-[#5138ed] border-slate-300 cursor-pointer"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <div
                    class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0"
                    :class="addForm.passwordMode === 'manual' ? 'bg-[#5138ed] text-white' : 'bg-slate-100 text-slate-600'"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                  </div>
                  <span class="text-xs font-bold text-slate-900">Manually Set Password</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                  Enter and confirm an initial password manually. Requires satisfying all 5 security policy rules.
                </p>
              </div>
            </label>

            <!-- Option B: Automatically Generate & Email -->
            <label
              class="relative flex items-start gap-3.5 p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200 select-none"
              :class="addForm.passwordMode === 'auto'
                ? 'border-[#5138ed] bg-indigo-50/20 shadow-xs ring-2 ring-[#5138ed]/10'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'"
            >
              <input
                type="radio"
                name="passwordMode"
                value="auto"
                v-model="addForm.passwordMode"
                class="mt-1 h-4 w-4 text-[#5138ed] focus:ring-[#5138ed] border-slate-300 cursor-pointer"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <div
                    class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0"
                    :class="addForm.passwordMode === 'auto' ? 'bg-[#5138ed] text-white' : 'bg-slate-100 text-slate-600'"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                  </div>
                  <span class="text-xs font-bold text-slate-900">Auto-Generate &amp; Send by Email</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                  Generates a secure 14+ character temporary password and emails it directly to the instructor's address.
                </p>
              </div>
            </label>
          </div>

          <!-- OPTION A: MANUAL PASSWORD FIELDS -->
          <div v-if="addForm.passwordMode === 'manual'" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <!-- Username Field -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.username"
                  type="text"
                  placeholder="username"
                  @input="delete addFormErrors.username"
                  class="w-full px-3.5 py-2 text-xs border rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition"
                  :class="addFormErrors.username ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200'"
                />
                <p v-if="addFormErrors.username" class="text-[10px] text-rose-600 mt-0.5">{{ addFormErrors.username }}</p>
              </div>

              <!-- Password Field with Eye Toggle Icon -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="block text-xs font-bold text-slate-700">Password <span class="text-rose-500">*</span></label>
                  <span
                    v-if="addForm.password"
                    class="text-[10px] font-bold px-1.5 py-0.5 rounded transition-colors"
                    :class="isAddPasswordValid ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                  >
                    {{ isAddPasswordValid ? '✓ Strong' : addPasswordStrength.text }}
                  </span>
                </div>
                <div class="relative">
                  <input
                    v-model="addForm.password"
                    :type="showAddPassword ? 'text' : 'password'"
                    placeholder="Min 8 chars, mixed case & symbols"
                    @input="onPasswordInput"
                    class="w-full pl-3.5 pr-10 py-2 text-xs border rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition"
                    :class="addFormErrors.password ? 'border-rose-300 bg-rose-50/30' : (isAddPasswordValid ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-200')"
                  />
                  <button
                    type="button"
                    @click="showAddPassword = !showAddPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-0.5 cursor-pointer"
                    :title="showAddPassword ? 'Hide password' : 'Show password'"
                    tabindex="-1"
                  >
                    <svg v-if="!showAddPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                  </button>
                </div>
                <p v-if="addFormErrors.password" class="text-[10px] text-rose-600 mt-0.5 font-medium">{{ addFormErrors.password }}</p>
              </div>

              <!-- Confirm Password Field with Eye Toggle Icon -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="block text-xs font-bold text-slate-700">Confirm Password <span class="text-rose-500">*</span></label>
                  <span
                    v-if="addForm.confirmPassword && addForm.password"
                    class="text-[10px] font-bold px-1.5 py-0.5 rounded transition-colors"
                    :class="addForm.password === addForm.confirmPassword ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200'"
                  >
                    {{ addForm.password === addForm.confirmPassword ? '✓ Matches' : '✗ Mismatch' }}
                  </span>
                </div>
                <div class="relative">
                  <input
                    v-model="addForm.confirmPassword"
                    :type="showAddConfirmPassword ? 'text' : 'password'"
                    placeholder="Confirm password"
                    @input="onConfirmPasswordInput"
                    class="w-full pl-3.5 pr-10 py-2 text-xs border rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition"
                    :class="addFormErrors.confirmPassword ? 'border-rose-300 bg-rose-50/30' : (addForm.confirmPassword && addForm.password === addForm.confirmPassword ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-200')"
                  />
                  <button
                    type="button"
                    @click="showAddConfirmPassword = !showAddConfirmPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-0.5 cursor-pointer"
                    :title="showAddConfirmPassword ? 'Hide password' : 'Show password'"
                    tabindex="-1"
                  >
                    <svg v-if="!showAddConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                  </button>
                </div>
                <p v-if="addFormErrors.confirmPassword" class="text-[10px] text-rose-600 mt-0.5 font-medium">{{ addFormErrors.confirmPassword }}</p>
              </div>
            </div>

            <!-- Password Requirements Checklist & Strength Feedback Bar -->
            <div v-if="addForm.password || passwordTouched" class="mt-3.5 p-3.5 bg-slate-50/90 rounded-xl border border-slate-200/80 space-y-2.5">
              <div class="flex items-center justify-between text-xs">
                <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">Password Requirements</span>
                <div class="flex items-center gap-2">
                  <span class="text-[11px] font-bold" :class="addPasswordStrength.textClass">
                    Strength: {{ addPasswordStrength.text || 'None' }}
                  </span>
                  <span
                    v-if="isAddPasswordValid"
                    class="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 rounded-full"
                  >
                    ✓ Ready
                  </span>
                </div>
              </div>

              <!-- Strength Meter Progress Bar (5 bars) -->
              <div class="grid grid-cols-5 gap-1.5 h-1.5 w-full">
                <div
                  v-for="idx in 5"
                  :key="idx"
                  class="h-full rounded-full transition-all duration-300"
                  :class="idx <= addPasswordStrength.count ? addPasswordStrength.color : 'bg-slate-200'"
                ></div>
              </div>

              <!-- 5 Checklist Criteria -->
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 pt-1 text-[11px]">
                <!-- 1. Length -->
                <div
                  class="flex items-center gap-1.5 py-1 px-2 rounded-lg transition-colors border"
                  :class="addPasswordCriteria.length ? 'bg-emerald-50/80 text-emerald-800 border-emerald-200 font-semibold' : 'bg-white text-slate-500 border-slate-200/70'"
                >
                  <svg v-if="addPasswordCriteria.length" class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0 flex items-center justify-center text-[9px] text-slate-400">•</span>
                  <span class="truncate">8+ Characters</span>
                </div>

                <!-- 2. Uppercase -->
                <div
                  class="flex items-center gap-1.5 py-1 px-2 rounded-lg transition-colors border"
                  :class="addPasswordCriteria.uppercase ? 'bg-emerald-50/80 text-emerald-800 border-emerald-200 font-semibold' : 'bg-white text-slate-500 border-slate-200/70'"
                >
                  <svg v-if="addPasswordCriteria.uppercase" class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0 flex items-center justify-center text-[9px] text-slate-400">•</span>
                  <span class="truncate">Uppercase (A-Z)</span>
                </div>

                <!-- 3. Lowercase -->
                <div
                  class="flex items-center gap-1.5 py-1 px-2 rounded-lg transition-colors border"
                  :class="addPasswordCriteria.lowercase ? 'bg-emerald-50/80 text-emerald-800 border-emerald-200 font-semibold' : 'bg-white text-slate-500 border-slate-200/70'"
                >
                  <svg v-if="addPasswordCriteria.lowercase" class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0 flex items-center justify-center text-[9px] text-slate-400">•</span>
                  <span class="truncate">Lowercase (a-z)</span>
                </div>

                <!-- 4. Number -->
                <div
                  class="flex items-center gap-1.5 py-1 px-2 rounded-lg transition-colors border"
                  :class="addPasswordCriteria.number ? 'bg-emerald-50/80 text-emerald-800 border-emerald-200 font-semibold' : 'bg-white text-slate-500 border-slate-200/70'"
                >
                  <svg v-if="addPasswordCriteria.number" class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0 flex items-center justify-center text-[9px] text-slate-400">•</span>
                  <span class="truncate">Number (0-9)</span>
                </div>

                <!-- 5. Special Character -->
                <div
                  class="flex items-center gap-1.5 py-1 px-2 rounded-lg transition-colors border"
                  :class="addPasswordCriteria.special ? 'bg-emerald-50/80 text-emerald-800 border-emerald-200 font-semibold' : 'bg-white text-slate-500 border-slate-200/70'"
                >
                  <svg v-if="addPasswordCriteria.special" class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0 flex items-center justify-center text-[9px] text-slate-400">•</span>
                  <span class="truncate">Special (!@#$%^&*)</span>
                </div>
              </div>
            </div>
          </div>

          <!-- OPTION B: AUTOMATICALLY GENERATE & EMAIL -->
          <div v-else class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Username input -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Account Username <span class="text-rose-500">*</span>
                </label>
                <input
                  v-model="addForm.username"
                  type="text"
                  placeholder="e.g. j.doe or employee username"
                  @input="delete addFormErrors.username"
                  class="w-full px-3.5 py-2 text-xs border rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition bg-white"
                  :class="addFormErrors.username ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200'"
                />
                <p v-if="addFormErrors.username" class="text-[10px] text-rose-600 mt-0.5">{{ addFormErrors.username }}</p>
                <p class="text-[11px] text-slate-400 mt-1">Instructor will use this username to sign in to the portal.</p>
              </div>

              <!-- Recipient Email Preview -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Password Recipient (Registered Email)
                </label>
                <div class="px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 flex items-center justify-between">
                  <span class="truncate">{{ addForm.email.trim() || 'Enter instructor email in Section 1' }}</span>
                  <span v-if="addForm.email.trim()" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200 shrink-0">
                    Recipient
                  </span>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Credentials will be emailed directly to this address upon creation.</p>
              </div>
            </div>

            <!-- Explanation & Delivery Notice Box -->
            <div class="p-4 bg-indigo-50/40 rounded-2xl border border-indigo-100 space-y-3">
              <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-100 text-[#5138ed] flex items-center justify-center shrink-0 mt-0.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="text-xs text-slate-700 space-y-1">
                  <p class="font-bold text-slate-900">Automatically Generate Secure Password</p>
                  <p class="text-slate-600 leading-relaxed text-[11px]">
                    The system will generate a unique temporary password (minimum 14 characters, combining uppercase, lowercase, numbers, and symbols), securely create the instructor's account, and email the credentials directly to <strong class="text-slate-900">{{ addForm.email || 'the instructor’s registered email' }}</strong>. The password will not be displayed to the Department Head.
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2 border-t border-indigo-100/70 text-[11px]">
                <div class="p-2.5 bg-white/80 rounded-xl border border-indigo-100/50">
                  <span class="text-slate-400 block text-[10px] font-bold uppercase">Password Privacy</span>
                  <span class="font-bold text-slate-800 mt-0.5 block">Not displayed to Dept Head</span>
                </div>
                <div class="p-2.5 bg-white/80 rounded-xl border border-indigo-100/50">
                  <span class="text-slate-400 block text-[10px] font-bold uppercase">First Login Policy</span>
                  <span class="font-bold text-amber-700 mt-0.5 block">Password change enforced</span>
                </div>
                <div class="p-2.5 bg-white/80 rounded-xl border border-indigo-100/50">
                  <span class="text-slate-400 block text-[10px] font-bold uppercase">Delivery Status</span>
                  <span class="font-bold text-emerald-700 mt-0.5 block">Emailed automatically</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
          <button
            type="button"
            @click="currentView = 'list'"
            class="px-4 py-2 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="submitAddInstructor"
            :disabled="isLoading"
            class="px-5 py-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition cursor-pointer shadow-sm disabled:opacity-50 flex items-center gap-1.5"
          >
            <svg v-if="isLoading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>
              {{
                isLoading
                  ? (addForm.passwordMode === 'auto' ? 'Creating & Sending Password...' : 'Creating Instructor...')
                  : (addForm.passwordMode === 'auto' ? 'Create Instructor & Send Password' : 'Save & Register Instructor')
              }}
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW: EDIT INSTRUCTOR FORM                                                -->
    <!-- ========================================================================= -->
    <div v-else-if="currentView === 'edit'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-6 space-y-6">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-[#5138ed]">Faculty Management</span>
          <h2 class="text-xl font-black text-slate-900 tracking-tight">Edit Instructor Profile</h2>
          <p class="text-xs text-slate-500 mt-0.5">Editing: <strong class="text-slate-800">{{ editForm.fullName }}</strong> (ID: {{ editForm.employeeId }})</p>
        </div>
        <button
          @click="currentView = 'list'"
          class="px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer"
        >
          Cancel & Return
        </button>
      </div>

      <div class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label>
            <input
              v-model="editForm.fullName"
              type="text"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
            <input
              v-model="editForm.email"
              type="email"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number</label>
            <input
              v-model="editForm.phone"
              type="text"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Employee ID</label>
            <input
              v-model="editForm.employeeId"
              type="text"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Employment Type</label>
            <select
              v-model="editForm.employmentType"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            >
              <option value="full_time">Full-Time Faculty</option>
              <option value="part_time">Part-Time / Adjunct</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
            <select
              v-model="editForm.status"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            >
              <option value="active">Active</option>
              <option value="on_leave">On Leave</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Year Level</label>
            <select
              v-model="editForm.yearLevel"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            >
              <option value="None">None (All Academic Years)</option>
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
              <option value="5th Year">5th Year</option>
            </select>
            <p class="text-[10px] text-slate-400 mt-1">Select 'None' to allow course assignments across all academic year levels.</p>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Semester</label>
            <select
              v-model="editForm.semester"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            >
              <option value="First Semester">First Semester</option>
              <option value="Second Semester">Second Semester</option>
              <option value="Summer Term">Summer Term</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Section</label>
            <select
              v-model="editForm.section"
              class="w-full px-3.5 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            >
              <option value="Section A">Section A</option>
              <option value="Section B">Section B</option>
              <option value="Both Sections">Both Sections</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Update Password (optional)</label>
            <div class="relative">
              <input
                v-model="editForm.password"
                :type="showEditPassword ? 'text' : 'password'"
                placeholder="Leave blank to preserve current"
                class="w-full pl-3.5 pr-10 py-2 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition"
              />
              <button
                type="button"
                @click="showEditPassword = !showEditPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-0.5 cursor-pointer"
                :title="showEditPassword ? 'Hide password' : 'Show password'"
                tabindex="-1"
              >
                <svg v-if="!showEditPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Profile Photo</label>
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                <img v-if="editPhotoPreview" :src="editPhotoPreview" class="w-full h-full object-cover" />
                <svg v-else class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
              </div>
              <input ref="editFileInput" type="file" accept="image/*" class="hidden" @change="handleEditPhotoUpload" />
              <button
                type="button"
                @click="triggerEditFileInput"
                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer"
              >
                Change Photo
              </button>
            </div>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
          <button
            type="button"
            @click="currentView = 'list'"
            class="px-4 py-2 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="submitEditInstructor"
            :disabled="isLoading"
            class="px-5 py-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition cursor-pointer shadow-sm disabled:opacity-50"
          >
            {{ isLoading ? 'Saving Changes...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW: MAIN INSTRUCTORS LIST & DIRECTORY                                   -->
    <!-- ========================================================================= -->
    <div v-else class="space-y-6">

      <!-- Institutional Header & Actions -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          
          <!-- Left: Title & Badges -->
          <div class="min-w-0 space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-indigo-50 text-[#5138ed] border border-indigo-100">
                <span class="w-1.5 h-1.5 rounded-full bg-[#5138ed]"></span>
                Faculty Directory • {{ department.name }}
              </span>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                Code: {{ department.code }}
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                {{ activeTerm }}
              </span>
            </div>

            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
              Department Instructor Management Center
            </h1>
            <p class="text-xs sm:text-[13px] text-slate-500">
              Manage faculty members, teaching assignments, workload credits, and academic staff information.
            </p>
          </div>

          <!-- Right: Header Actions -->
          <div class="flex flex-wrap items-center gap-2.5 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
            
            <!-- Add Instructor Button -->
            <button
              @click="openAddView"
              class="px-4 py-2.5 bg-[#5138ed] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-sm cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <span>Add Instructor</span>
            </button>

            <!-- Export Dropdown -->
            <div class="relative export-menu-container">
              <button
                @click.stop="showExportMenu = !showExportMenu"
                :disabled="isExporting"
                class="px-3.5 py-2.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-2xs cursor-pointer disabled:opacity-50"
              >
                <svg v-if="!isExporting" class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <svg v-else class="w-4 h-4 text-[#5138ed] animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Export</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div
                v-if="showExportMenu"
                class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-xl shadow-lg py-1.5 z-30"
              >
                <button
                  @click="handleExport('excel')"
                  class="w-full px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer"
                >
                  <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                  Export as Excel (.xlsx)
                </button>
                <button
                  @click="handleExport('pdf')"
                  class="w-full px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer"
                >
                  <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                  Export as PDF (.pdf)
                </button>
                <button
                  @click="handleExport('csv')"
                  class="w-full px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer"
                >
                  <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                  Export as CSV (.csv)
                </button>
              </div>
            </div>

            <!-- Refresh Button -->
            <button
              @click="fetchInstructors()"
              class="p-2.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-xl transition cursor-pointer shadow-2xs"
              title="Refresh instructors list"
            >
              <svg class="w-4 h-4" :class="{ 'animate-spin text-[#5138ed]': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>

          </div>

        </div>
      </div>

      <!-- Top Summary KPI Cards (Real Data Only) -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
        
        <!-- Total Instructors -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Faculty</span>
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
          </div>
          <p class="text-2xl font-black text-slate-900 leading-none">{{ stats.total }}</p>
          <p class="text-[11px] text-slate-400 font-medium mt-1">All department staff</p>
        </div>

        <!-- Full Time -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Full-Time</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
          </div>
          <p class="text-2xl font-black text-slate-900 leading-none">{{ stats.full_time }}</p>
          <p class="text-[11px] text-slate-400 font-medium mt-1">Full-time faculty</p>
        </div>

        <!-- Part Time -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Part-Time</span>
            <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
          <p class="text-2xl font-black text-slate-900 leading-none">{{ stats.part_time }}</p>
          <p class="text-[11px] text-slate-400 font-medium mt-1">Adjunct & part-time</p>
        </div>

        <!-- On Leave -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">On Leave</span>
            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
          <p class="text-2xl font-black text-slate-900 leading-none">{{ stats.on_leave }}</p>
          <p class="text-[11px] text-slate-400 font-medium mt-1">Approved leave</p>
        </div>

        <!-- Active Teaching -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Assigned Courses</span>
            <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </div>
          </div>
          <p class="text-2xl font-black text-slate-900 leading-none">{{ stats.with_courses }}</p>
          <p class="text-[11px] text-slate-400 font-medium mt-1">Teaching this semester</p>
        </div>

        <!-- Without Courses -->
        <div
          class="bg-white border rounded-2xl p-4 shadow-xs"
          :class="stats.without_courses > 0 ? 'border-amber-200 bg-amber-50/10' : 'border-slate-200/80'"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Unassigned</span>
            <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
          </div>
          <p class="text-2xl font-black text-slate-900 leading-none">{{ stats.without_courses }}</p>
          <p class="text-[11px] text-slate-400 font-medium mt-1">No course assignment</p>
        </div>

      </div>

      <!-- Search & Filters Toolbar -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          
          <!-- Search Input -->
          <div class="relative flex-1 max-w-lg">
            <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="search"
              @input="onSearchInput"
              type="text"
              placeholder="Search by instructor name, employee ID, email, or phone..."
              class="w-full pl-10 pr-10 py-2.5 text-xs border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] bg-slate-50/50 hover:bg-white focus:bg-white transition"
            />
            <button
              v-if="search"
              @click="search = ''; fetchInstructors()"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Quick Dropdown Filters -->
          <div class="flex flex-wrap items-center gap-2">
            
            <!-- Status Filter -->
            <select
              v-model="statusFilter"
              class="px-3 py-2 text-xs font-semibold border border-slate-200 rounded-xl text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] cursor-pointer"
            >
              <option value="all">All Status</option>
              <option value="active">Active</option>
              <option value="on_leave">On Leave</option>
              <option value="inactive">Inactive</option>
            </select>

            <!-- Employment Type Filter -->
            <select
              v-model="employmentFilter"
              class="px-3 py-2 text-xs font-semibold border border-slate-200 rounded-xl text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] cursor-pointer"
            >
              <option value="all">All Employment</option>
              <option value="full_time">Full-Time</option>
              <option value="part_time">Part-Time</option>
            </select>

            <!-- Course Filter -->
            <select
              v-model="courseFilter"
              class="px-3 py-2 text-xs font-semibold border border-slate-200 rounded-xl text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] cursor-pointer max-w-[160px] truncate"
            >
              <option value="all">All Courses</option>
              <option
                v-for="c in filterOptions.courses"
                :key="c.id"
                :value="c.id"
              >
                {{ c.code }} - {{ c.title }}
              </option>
            </select>

            <!-- Year Level Filter -->
            <select
              v-model="yearFilter"
              class="px-3 py-2 text-xs font-semibold border border-slate-200 rounded-xl text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] cursor-pointer"
            >
              <option value="all">All Years</option>
              <option
                v-for="y in filterOptions.years"
                :key="y"
                :value="y"
              >
                {{ y === 'None' ? 'None (All Academic Years)' : y }}
              </option>
            </select>

            <!-- Section Filter -->
            <select
              v-model="sectionFilter"
              class="px-3 py-2 text-xs font-semibold border border-slate-200 rounded-xl text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] cursor-pointer"
            >
              <option value="all">All Sections</option>
              <option
                v-for="sec in filterOptions.sections"
                :key="sec"
                :value="sec"
              >
                {{ sec }}
              </option>
            </select>

          </div>

        </div>

        <!-- Active Filter Tags & Reset -->
        <div v-if="activeFiltersCount > 0" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 text-xs">
          <span class="text-slate-400 font-semibold">Active Filters:</span>
          
          <span v-if="search" class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg flex items-center gap-1.5 font-medium">
            Search: "{{ search }}"
            <button @click="search = ''; fetchInstructors()" class="hover:text-rose-500 font-bold">&times;</button>
          </span>

          <span v-if="statusFilter !== 'all'" class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg flex items-center gap-1.5 font-medium capitalize">
            Status: {{ statusFilter.replace('_', ' ') }}
            <button @click="statusFilter = 'all'" class="hover:text-rose-500 font-bold">&times;</button>
          </span>

          <span v-if="employmentFilter !== 'all'" class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg flex items-center gap-1.5 font-medium">
            Type: {{ employmentFilter.replace('_', ' ') }}
            <button @click="employmentFilter = 'all'" class="hover:text-rose-500 font-bold">&times;</button>
          </span>

          <span v-if="courseFilter !== 'all'" class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg flex items-center gap-1.5 font-medium">
            Course Selected
            <button @click="courseFilter = 'all'" class="hover:text-rose-500 font-bold">&times;</button>
          </span>

          <button
            @click="resetAllFilters"
            class="text-[#5138ed] font-bold hover:underline cursor-pointer ml-auto"
          >
            Clear All ({{ activeFiltersCount }})
          </button>
        </div>
      </div>

      <!-- SKELETON LOADER STATE -->
      <div v-if="isLoading" class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-xs space-y-4 animate-pulse">
        <div class="h-8 bg-slate-200/60 rounded-xl w-1/4"></div>
        <div v-for="i in 5" :key="i" class="h-16 bg-slate-100 rounded-xl"></div>
      </div>

      <!-- EMPTY STATE: NO INSTRUCTORS FOUND -->
      <div
        v-else-if="instructors.length === 0"
        class="bg-white border border-slate-200/80 rounded-2xl p-12 shadow-xs text-center space-y-3"
      >
        <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-[#5138ed] mx-auto flex items-center justify-center">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        
        <div v-if="activeFiltersCount > 0">
          <h3 class="text-base font-bold text-slate-800">No Matching Faculty Found</h3>
          <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">
            No instructors match your current search and filter combination. Try adjusting or clearing your filters.
          </p>
          <button
            @click="resetAllFilters"
            class="mt-4 px-4 py-2 bg-[#5138ed] text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition cursor-pointer shadow-sm"
          >
            Clear All Filters
          </button>
        </div>

        <div v-else>
          <h3 class="text-base font-bold text-slate-800">No Instructors in this Department Yet</h3>
          <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">
            There are currently no instructors assigned to the {{ department.name }} department. Click the button below to register a faculty member.
          </p>
          <button
            @click="openAddView"
            class="mt-4 px-4 py-2 bg-[#5138ed] text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition cursor-pointer shadow-sm"
          >
            + Add First Instructor
          </button>
        </div>
      </div>

      <!-- INSTRUCTORS DATA TABLE (DESKTOP & TABLET) -->
      <div v-else class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        
        <!-- Table view for desktop / large screens -->
        <div class="overflow-x-auto hidden md:block">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/70 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                <th class="py-3 px-4">Instructor</th>
                <th class="py-3 px-4">Employee ID</th>
                <th class="py-3 px-4">Contact</th>
                <th class="py-3 px-4">Employment</th>
                <th class="py-3 px-4">Assigned Courses</th>
                <th class="py-3 px-4">Workload</th>
                <th class="py-3 px-4">Status</th>
                <th class="py-3 px-4">Year & Sec</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr
                v-for="inst in instructors"
                :key="inst.id"
                class="hover:bg-slate-50/70 transition-colors group"
              >
                <!-- Instructor Name & Avatar -->
                <td class="py-3.5 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                      <img
                        v-if="inst.profile_picture_url"
                        :src="resolveAvatarUrl(inst.profile_picture_url) || ''"
                        class="w-full h-full object-cover"
                        alt="Profile photo"
                      />
                      <span v-else class="font-bold text-[#5138ed] text-xs">
                        {{ inst.name.slice(0, 2).toUpperCase() }}
                      </span>
                    </div>

                    <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <button
                          @click="openDetail(inst)"
                          class="font-bold text-slate-900 group-hover:text-[#5138ed] transition text-left truncate cursor-pointer hover:underline"
                        >
                          {{ inst.name }}
                        </button>
                        <span
                          v-if="inst.is_self"
                          class="px-1.5 py-0.5 rounded text-[10px] font-black bg-indigo-50 text-[#5138ed] border border-indigo-100"
                        >
                          You (Head)
                        </span>
                      </div>
                      <p class="text-[11px] text-slate-400 truncate">
                        {{ inst.role === 'dept_head' ? 'Department Head & Faculty' : 'Faculty Instructor' }}
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Employee ID -->
                <td class="py-3.5 px-4 whitespace-nowrap">
                  <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200">
                    {{ inst.id_no }}
                  </span>
                </td>

                <!-- Contact Info -->
                <td class="py-3.5 px-4">
                  <p class="font-semibold text-slate-800 truncate max-w-[170px]">{{ inst.email }}</p>
                  <p class="text-[11px] text-slate-400 mt-0.5">{{ inst.phone }}</p>
                  <div v-if="inst.password_delivery_status && inst.password_delivery_status !== 'manual'" class="mt-1">
                    <span
                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :class="{
                        'bg-emerald-50 text-emerald-700 border border-emerald-200': inst.password_delivery_status === 'delivered',
                        'bg-amber-50 text-amber-700 border border-amber-200': inst.password_delivery_status === 'pending',
                        'bg-rose-50 text-rose-700 border border-rose-200': inst.password_delivery_status === 'failed',
                      }"
                    >
                      <svg v-if="inst.password_delivery_status === 'delivered'" class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                      <svg v-else-if="inst.password_delivery_status === 'failed'" class="w-3 h-3 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                      <span>{{ inst.password_delivery_status === 'delivered' ? 'Password Sent' : (inst.password_delivery_status === 'failed' ? 'Email Failed' : 'Pending') }}</span>
                    </span>
                  </div>
                </td>

                <!-- Employment Type -->
                <td class="py-3.5 px-4 whitespace-nowrap">
                  <span
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold"
                    :class="inst.employment_type === 'part_time' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200'"
                  >
                    {{ inst.employment_type === 'part_time' ? 'Part-Time' : 'Full-Time' }}
                  </span>
                </td>

                <!-- Assigned Courses -->
                <td class="py-3.5 px-4">
                  <div v-if="inst.assigned_courses.length === 0" class="flex items-center gap-1.5 text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                    <span class="text-xs">No Courses</span>
                  </div>
                  <div v-else class="flex flex-wrap items-center gap-1.5">
                    <button
                      @click="openTeaching(inst)"
                      v-for="c in inst.assigned_courses.slice(0, 2)"
                      :key="c.id"
                      class="px-2 py-0.5 bg-slate-100 hover:bg-indigo-50 hover:text-[#5138ed] text-slate-700 font-semibold rounded text-[11px] border border-slate-200 transition cursor-pointer"
                      title="Click to view course details"
                    >
                      {{ c.code }}: {{ c.title }}
                    </button>
                    <button
                      v-if="inst.assigned_courses.length > 2"
                      @click="openTeaching(inst)"
                      class="px-1.5 py-0.5 bg-indigo-50 text-[#5138ed] font-bold text-[10px] rounded cursor-pointer hover:bg-indigo-100"
                    >
                      +{{ inst.assigned_courses.length - 2 }}
                    </button>
                  </div>
                </td>

                <!-- Workload / Credits -->
                <td class="py-3.5 px-4 whitespace-nowrap">
                  <span
                    class="px-2.5 py-1 rounded-lg text-xs font-black inline-flex items-center gap-1"
                    :class="inst.total_credits > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200'"
                  >
                    {{ inst.total_credits }} Cr
                  </span>
                </td>

                <!-- Status -->
                <td class="py-3.5 px-4 whitespace-nowrap">
                  <span
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold capitalize inline-flex items-center gap-1.5"
                    :class="{
                      'bg-emerald-50 text-emerald-700 border border-emerald-200': inst.status === 'active',
                      'bg-amber-50 text-amber-700 border border-amber-200': inst.status === 'on_leave',
                      'bg-slate-100 text-slate-600 border border-slate-200': inst.status === 'inactive' || inst.status === 'suspended'
                    }"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full"
                      :class="{
                        'bg-emerald-500': inst.status === 'active',
                        'bg-amber-500': inst.status === 'on_leave',
                        'bg-slate-400': inst.status === 'inactive' || inst.status === 'suspended'
                      }"
                    ></span>
                    {{ inst.status.replace('_', ' ') }}
                  </span>
                </td>

                <!-- Year & Section -->
                <td class="py-3.5 px-4 whitespace-nowrap text-slate-600">
                  <p class="font-semibold">{{ inst.year_level === 'None' ? 'None (All Years)' : (inst.year_level || '—') }}</p>
                  <p class="text-[11px] text-slate-400">{{ inst.section }}</p>
                </td>

                <!-- 3-Dot Action Column -->
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                  <div class="relative inline-block text-left dropdown-container">
                    
                    <!-- 3-Dot Button -->
                    <button
                      type="button"
                      @click="toggleDropdown(inst.id, $event)"
                      class="w-8 h-8 rounded-lg hover:bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center transition cursor-pointer"
                      title="Manage Instructor"
                    >
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                      </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                      v-if="openDropdownId === inst.id"
                      class="origin-top-right absolute right-0 mt-1 w-48 rounded-xl shadow-xl bg-white border border-slate-200 py-1.5 z-40 text-left"
                    >
                      <!-- View Details -->
                      <button
                        @click="openDetail(inst); openDropdownId = null"
                        class="w-full px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 cursor-pointer"
                      >
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>View Profile</span>
                      </button>

                      <!-- Teaching Assignments -->
                      <button
                        @click="openTeaching(inst); openDropdownId = null"
                        class="w-full px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 cursor-pointer"
                      >
                        <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>Teaching Courses</span>
                      </button>

                      <!-- Edit Instructor -->
                      <button
                        v-if="inst.can_edit"
                        @click="openEditView(inst)"
                        class="w-full px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 cursor-pointer"
                      >
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>Edit Instructor</span>
                      </button>

                      <!-- Resend Password Email -->
                      <button
                        v-if="!inst.is_self"
                        @click="handleResendCredentials(inst); openDropdownId = null"
                        :disabled="isResendingCredentials === inst.id"
                        class="w-full px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 cursor-pointer disabled:opacity-50"
                      >
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>{{ isResendingCredentials === inst.id ? 'Sending Email...' : 'Resend Password Email' }}</span>
                      </button>

                      <div class="my-1 border-t border-slate-100"></div>

                      <!-- Status Change Actions -->
                      <button
                        v-if="inst.can_deactivate && inst.status !== 'active'"
                        @click="openStatusDialog(inst, 'active')"
                        class="w-full px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50 flex items-center gap-2 cursor-pointer"
                      >
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Set to Active</span>
                      </button>

                      <button
                        v-if="inst.can_deactivate && inst.status !== 'on_leave'"
                        @click="openStatusDialog(inst, 'on_leave')"
                        class="w-full px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50 flex items-center gap-2 cursor-pointer"
                      >
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>Place On Leave</span>
                      </button>

                      <button
                        v-if="inst.can_deactivate && inst.status !== 'inactive'"
                        @click="openStatusDialog(inst, 'inactive')"
                        class="w-full px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer"
                      >
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        <span>Deactivate Account</span>
                      </button>

                      <!-- Delete Action -->
                      <button
                        v-if="inst.can_delete"
                        @click="confirmDelete(inst)"
                        class="w-full px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 flex items-center gap-2 cursor-pointer"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Delete Instructor</span>
                      </button>

                    </div>

                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Responsive Card View for Mobile -->
        <div class="md:hidden divide-y divide-slate-100">
          <div
            v-for="inst in instructors"
            :key="inst.id"
            class="p-4 space-y-3"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                  <img
                    v-if="inst.profile_picture_url"
                    :src="resolveAvatarUrl(inst.profile_picture_url) || ''"
                    class="w-full h-full object-cover"
                  />
                  <span v-else class="font-bold text-[#5138ed] text-sm">
                    {{ inst.name.slice(0, 2).toUpperCase() }}
                  </span>
                </div>
                <div>
                  <h4 class="font-bold text-slate-900 leading-snug">{{ inst.name }}</h4>
                  <p class="text-xs text-slate-400 font-mono">{{ inst.id_no }}</p>
                </div>
              </div>

              <span
                class="px-2.5 py-0.5 rounded-full text-[10px] font-bold capitalize"
                :class="{
                  'bg-emerald-50 text-emerald-700 border border-emerald-200': inst.status === 'active',
                  'bg-amber-50 text-amber-700 border border-amber-200': inst.status === 'on_leave',
                  'bg-slate-100 text-slate-600 border border-slate-200': inst.status === 'inactive'
                }"
              >
                {{ inst.status.replace('_', ' ') }}
              </span>
            </div>

            <div class="text-xs text-slate-600 space-y-1">
              <p>Email: <strong class="text-slate-800">{{ inst.email }}</strong></p>
              <p>Workload: <strong class="text-emerald-700">{{ inst.total_credits }} Credits</strong> ({{ inst.courses_count }} courses)</p>
              <p v-if="inst.password_delivery_status && inst.password_delivery_status !== 'manual'">
                Credentials Delivery:
                <strong
                  :class="{
                    'text-emerald-600': inst.password_delivery_status === 'delivered',
                    'text-rose-600': inst.password_delivery_status === 'failed',
                    'text-amber-600': inst.password_delivery_status === 'pending'
                  }"
                >
                  {{ inst.password_delivery_status === 'delivered' ? 'Password Sent' : (inst.password_delivery_status === 'failed' ? 'Email Failed' : 'Pending') }}
                </strong>
              </p>
            </div>

            <div class="pt-2 flex items-center gap-2">
              <button
                @click="openDetail(inst)"
                class="flex-1 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition"
              >
                View Profile
              </button>
              <button
                v-if="inst.can_edit"
                @click="openEditView(inst)"
                class="flex-1 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-[#5138ed] text-xs font-bold rounded-lg transition"
              >
                Edit
              </button>
              <button
                v-if="!inst.is_self"
                @click="handleResendCredentials(inst)"
                :disabled="isResendingCredentials === inst.id"
                class="py-1.5 px-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold rounded-lg transition disabled:opacity-50"
                title="Resend Credentials Email"
              >
                {{ isResendingCredentials === inst.id ? 'Sending...' : 'Resend' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination Controls -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500">
          <div>
            Showing <strong class="text-slate-800">{{ fromItem }}</strong> to <strong class="text-slate-800">{{ toItem }}</strong> of <strong class="text-slate-800">{{ totalItems }}</strong> department faculty
          </div>

          <div class="flex items-center gap-2">
            <span class="text-slate-400">Per page:</span>
            <select
              v-model="perPage"
              class="px-2 py-1 border border-slate-200 rounded-lg text-slate-700 bg-white focus:outline-none"
            >
              <option :value="5">5</option>
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>

            <button
              @click="currentPage = Math.max(1, currentPage - 1); fetchInstructors()"
              :disabled="currentPage <= 1"
              class="px-3 py-1.5 border border-slate-200 rounded-lg text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-40 transition font-bold cursor-pointer"
            >
              Previous
            </button>

            <span class="px-2 font-semibold text-slate-700">
              Page {{ currentPage }} of {{ lastPage }}
            </span>

            <button
              @click="currentPage = Math.min(lastPage, currentPage + 1); fetchInstructors()"
              :disabled="currentPage >= lastPage"
              class="px-3 py-1.5 border border-slate-200 rounded-lg text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-40 transition font-bold cursor-pointer"
            >
              Next
            </button>
          </div>
        </div>

      </div>

    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: INSTRUCTOR DETAILS DRAWER                                          -->
    <!-- ========================================================================= -->
    <div
      v-if="showDetailModal"
      class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-2xs flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-100 overflow-hidden space-y-5 p-6 animate-scale-in">
        
        <!-- Header -->
        <div class="flex items-start justify-between pb-4 border-b border-slate-100">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100 overflow-hidden flex items-center justify-center shrink-0">
              <img
                v-if="selectedInstructor?.profile_picture_url"
                :src="resolveAvatarUrl(selectedInstructor.profile_picture_url) || ''"
                class="w-full h-full object-cover"
              />
              <span v-else class="text-lg font-black text-[#5138ed]">
                {{ selectedInstructor?.name?.slice(0, 2).toUpperCase() }}
              </span>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-lg font-black text-slate-900">{{ selectedInstructor?.name }}</h3>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold capitalize"
                  :class="selectedInstructor?.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                >
                  {{ selectedInstructor?.status }}
                </span>
              </div>
              <p class="text-xs text-slate-500 font-mono mt-0.5">ID: {{ selectedInstructor?.id_no }} • {{ department.name }}</p>
            </div>
          </div>

          <button
            @click="showDetailModal = false"
            class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 cursor-pointer"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="isLoadingDetail" class="py-12 text-center text-slate-400 animate-pulse">
          <p class="text-xs font-semibold">Loading detailed faculty records...</p>
        </div>

        <div v-else class="space-y-5">
          <!-- Overview Cards -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase">Employment</span>
              <p class="text-xs font-bold text-slate-800 capitalize mt-0.5">{{ (instructorDetail?.employment_type || 'full_time').replace('_', ' ') }}</p>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase">Assigned Courses</span>
              <p class="text-xs font-bold text-slate-800 mt-0.5">{{ instructorDetail?.courses_count || 0 }} Courses</p>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase">Teaching Credits</span>
              <p class="text-xs font-bold text-emerald-700 mt-0.5">{{ instructorDetail?.total_credits || 0 }} Credits</p>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase">Exams Administered</span>
              <p class="text-xs font-bold text-slate-800 mt-0.5">{{ instructorDetail?.exams_count || 0 }} Assessments</p>
            </div>
          </div>

          <!-- Contact & Academic Information -->
          <div class="space-y-2 text-xs">
            <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">Academic & Contact Records</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 bg-slate-50/70 rounded-xl border border-slate-100">
              <p><span class="text-slate-400">Official Email:</span> <strong class="text-slate-700 ml-1">{{ instructorDetail?.email }}</strong></p>
              <p><span class="text-slate-400">Phone:</span> <strong class="text-slate-700 ml-1">{{ instructorDetail?.phone }}</strong></p>
              <p><span class="text-slate-400">Assigned Year:</span> <strong class="text-slate-700 ml-1">{{ instructorDetail?.year_level === 'None' ? 'None (All Academic Years)' : (instructorDetail?.year_level || '—') }}</strong></p>
              <p><span class="text-slate-400">Assigned Section:</span> <strong class="text-slate-700 ml-1">{{ instructorDetail?.section }}</strong></p>
              <p><span class="text-slate-400">Registered By:</span> <strong class="text-slate-700 ml-1">{{ instructorDetail?.creator_name }}</strong></p>
              <p><span class="text-slate-400">Date Joined:</span> <strong class="text-slate-700 ml-1">{{ instructorDetail?.joined_formatted }}</strong></p>
              <p class="sm:col-span-2 pt-1 border-t border-slate-200/60 flex items-center justify-between">
                <span class="text-slate-400">Password Delivery:</span>
                <span
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="{
                    'bg-emerald-50 text-emerald-700 border border-emerald-200': instructorDetail?.password_delivery_status === 'delivered',
                    'bg-amber-50 text-amber-700 border border-amber-200': instructorDetail?.password_delivery_status === 'pending',
                    'bg-rose-50 text-rose-700 border border-rose-200': instructorDetail?.password_delivery_status === 'failed',
                    'bg-slate-100 text-slate-600 border border-slate-200': !instructorDetail?.password_delivery_status || instructorDetail?.password_delivery_status === 'manual'
                  }"
                >
                  {{ (instructorDetail?.password_delivery_status || 'manual').toUpperCase() }}
                  <span v-if="instructorDetail?.must_change_password" class="text-amber-600 font-semibold">• Change Enforced</span>
                </span>
              </p>
            </div>
          </div>

          <!-- Assigned Courses Section -->
          <div class="space-y-2 text-xs">
            <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">Assigned Courses & Curriculum Units</h4>
            <div v-if="!instructorDetail?.assigned_courses || instructorDetail.assigned_courses.length === 0" class="p-4 bg-slate-50 rounded-xl text-center text-slate-400">
              No active courses currently assigned to this instructor.
            </div>
            <div v-else class="space-y-2 max-h-48 overflow-y-auto pr-1">
              <div
                v-for="c in instructorDetail.assigned_courses"
                :key="c.id"
                class="p-3 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-between"
              >
                <div>
                  <span class="font-bold text-slate-800">{{ c.title }}</span>
                  <span class="font-mono text-[10px] ml-2 text-slate-500 bg-white px-1.5 py-0.5 rounded border border-slate-200">{{ c.code }}</span>
                  <p class="text-[11px] text-slate-400 mt-0.5">{{ c.level || 'General' }} • {{ c.semester || 'Current Term' }} • {{ c.section || 'All Sections' }}</p>
                </div>
                <span class="px-2 py-1 rounded bg-emerald-50 text-emerald-800 font-black text-xs">
                  {{ c.credits }} Credits
                </span>
              </div>
            </div>
          </div>

          <!-- Exams Section -->
          <div v-if="instructorDetail?.exams && instructorDetail.exams.length > 0" class="space-y-2 text-xs">
            <h4 class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">Exams Created by Instructor</h4>
            <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
              <div
                v-for="e in instructorDetail.exams"
                :key="e.id"
                class="p-2.5 bg-slate-50 rounded-lg flex items-center justify-between text-[11px]"
              >
                <div>
                  <span class="font-bold text-slate-800">{{ e.title }}</span>
                  <span class="text-slate-400 ml-1 font-mono">({{ e.course_code }})</span>
                </div>
                <span class="capitalize text-slate-600 font-semibold">{{ e.status }} • {{ e.duration_minutes }}m</span>
              </div>
            </div>
          </div>

        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
          <div>
            <button
              v-if="!instructorDetail?.is_self"
              @click="handleResendCredentials(instructorDetail)"
              :disabled="isResendingCredentials === instructorDetail?.id"
              class="px-3 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5 disabled:opacity-50 border border-purple-200"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              <span>{{ isResendingCredentials === instructorDetail?.id ? 'Sending...' : 'Resend Credentials Email' }}</span>
            </button>
          </div>
          <button
            @click="showDetailModal = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer"
          >
            Close Profile
          </button>
        </div>

      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: TEACHING ASSIGNMENTS VIEWER                                        -->
    <!-- ========================================================================= -->
    <div
      v-if="showTeachingModal"
      class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-2xs flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-100 overflow-hidden space-y-4 p-6 animate-scale-in">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Teaching Assignments</h3>
            <p class="text-xs text-slate-500">{{ selectedInstructor?.name }} ({{ selectedInstructor?.id_no }})</p>
          </div>
          <button @click="showTeachingModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="!selectedInstructor?.assigned_courses || selectedInstructor.assigned_courses.length === 0" class="py-8 text-center text-slate-400 text-xs">
          No courses currently assigned to this instructor.
        </div>

        <div v-else class="space-y-3 max-h-72 overflow-y-auto pr-1">
          <div
            v-for="c in selectedInstructor.assigned_courses"
            :key="c.id"
            class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-1.5"
          >
            <div class="flex items-center justify-between">
              <h4 class="font-bold text-slate-900 text-xs">{{ c.title }}</h4>
              <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-white text-slate-700 border border-slate-200">
                {{ c.code }}
              </span>
            </div>
            <div class="flex items-center justify-between text-[11px] text-slate-500">
              <span>Section: {{ c.section || 'All Sections' }} • {{ c.level || 'All Levels' }}</span>
              <strong class="text-emerald-700 font-bold">{{ c.credits }} Credits</strong>
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
          <span class="text-slate-500 font-semibold">Total Workload: <strong class="text-slate-900">{{ selectedInstructor?.total_credits }} Credits</strong></span>
          <button
            @click="showTeachingModal = false"
            class="px-4 py-1.5 bg-[#5138ed] text-white font-bold rounded-xl hover:bg-indigo-700 transition cursor-pointer"
          >
            Done
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: STATUS CHANGE CONFIRMATION                                         -->
    <!-- ========================================================================= -->
    <div
      v-if="showStatusModal"
      class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-2xs flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-100 p-6 space-y-4 animate-scale-in">
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>

        <div>
          <h3 class="text-base font-black text-slate-900">Change Instructor Status?</h3>
          <p class="text-xs text-slate-500 mt-1">
            Are you sure you want to change the status of <strong class="text-slate-800">{{ selectedInstructor?.name }}</strong> to
            <span class="uppercase font-bold text-[#5138ed] ml-1">{{ statusToUpdate.replace('_', ' ') }}</span>?
          </p>
          <p v-if="statusToUpdate === 'on_leave'" class="text-[11px] text-amber-700 bg-amber-50 p-2.5 rounded-lg border border-amber-200 mt-2">
            Notice: Setting an instructor to 'On Leave' indicates they are temporarily not actively teaching.
          </p>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
          <button
            @click="showStatusModal = false"
            class="px-4 py-2 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="confirmStatusChange"
            :disabled="isLoading"
            class="px-4 py-2 bg-[#5138ed] text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition cursor-pointer shadow-sm disabled:opacity-50"
          >
            {{ isLoading ? 'Updating...' : 'Confirm Status Change' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: DELETE CONFIRMATION                                                -->
    <!-- ========================================================================= -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-2xs flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-100 p-6 space-y-4 animate-scale-in">
        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </div>

        <div>
          <h3 class="text-base font-black text-slate-900">Delete Instructor?</h3>
          <p class="text-xs text-slate-500 mt-1">
            Are you sure you want to permanently delete <strong class="text-slate-800">{{ selectedInstructor?.name }}</strong>?
          </p>
          <p class="text-[11px] text-rose-700 bg-rose-50 p-2.5 rounded-lg border border-rose-200 mt-2">
            Warning: This action cannot be undone. Instructors with course assignments or exam attempts should be marked Inactive instead.
          </p>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
          <button
            @click="showDeleteModal = false"
            class="px-4 py-2 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="executeDelete"
            :disabled="isLoading"
            class="px-4 py-2 bg-rose-600 text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition cursor-pointer shadow-sm disabled:opacity-50"
          >
            {{ isLoading ? 'Deleting...' : 'Delete Instructor' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
