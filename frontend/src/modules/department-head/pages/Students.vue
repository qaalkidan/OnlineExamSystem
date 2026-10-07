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
const detailActiveTab = ref<'profile' | 'courses' | 'assessments'>('profile')

// ── Data & Loading ──
const isLoading = ref(true)
const isSaving = ref(false)
const isExporting = ref(false)
const isLoadingDetail = ref(false)
const errorMessage = ref<string | null>(null)
const successToast = ref<string | null>(null)
const copyToast = ref<string | null>(null)

const showToast = (msg: string) => {
  successToast.value = msg
  setTimeout(() => {
    successToast.value = null
  }, 4000)
}

const copyToClipboard = (text: string, label: string = 'Copied') => {
  if (!text) return
  navigator.clipboard?.writeText(text).then(() => {
    copyToast.value = `${label} copied to clipboard`
    setTimeout(() => {
      copyToast.value = null
    }, 2500)
  })
}

// ── Search & Filter State ──
const search = ref('')
const searchDebounceTimer = ref<any>(null)
const statusFilter = ref('all')
const yearFilter = ref('all')
const sectionFilter = ref('all')
const semesterFilter = ref('all')

// ── Pagination State ──
const currentPage = ref(1)
const perPage = ref(10)
const totalItems = ref(0)
const lastPage = ref(1)
const fromItem = ref(0)
const toItem = ref(0)

// ── Sorting ──
const sortBy = ref('created_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

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
  active: 0,
  inactive: 0,
  new_this_semester: 0,
  with_exam_records: 0
})

const filterOptions = ref({
  year_levels: [] as string[],
  sections: [] as string[],
  semesters: [] as string[],
  academic_years: [] as string[],
  statuses: ['active', 'inactive']
})

// ── Active Academic Term Badge ──
const activeTerm = computed(() => {
  const y = settingsStore.academicYear || '2026'
  const s = settingsStore.semester || 'Second Semester'
  return `${y} • ${s}`
})

// ── Students Dataset ──
const students = ref<any[]>([])
const selectedStudent = ref<any>(null)
const studentDetail = ref<{
  data: any
  exam_attempts: any[]
  performance: {
    total_attempts: number
    submitted_attempts: number
    passed_attempts: number
    average_percentage: number | null
    highest_percentage: number | null
  }
  enrolled_courses: any[]
  department: any
} | null>(null)

// ── Avatar Helper ──
const resolveAvatarUrl = (url: string | null | undefined): string | null => {
  if (!url) return null
  if (url.startsWith('http://localhost/') && !url.startsWith('http://localhost:8000/')) {
    return url.replace('http://localhost/', 'http://localhost:8000/')
  }
  if (url.startsWith('http://127.0.0.1/') && !url.startsWith('http://127.0.0.1:8000/')) {
    return url.replace('http://127.0.0.1/', 'http://127.0.0.1:8000/')
  }
  return url
}

const avatarColor = (name: string = '') => {
  const colors = [
    'bg-indigo-600 text-white',
    'bg-sky-600 text-white',
    'bg-emerald-600 text-white',
    'bg-violet-600 text-white',
    'bg-amber-600 text-white',
    'bg-rose-600 text-white',
    'bg-teal-600 text-white',
    'bg-cyan-600 text-white',
    'bg-purple-600 text-white'
  ]
  const charCode = (name || 'S').charCodeAt(0) || 0
  return colors[charCode % colors.length]
}

const getInitials = (name: string = '') => {
  if (!name) return 'ST'
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

// ── Fetch Students from Backend ──
const fetchStudents = async () => {
  isLoading.value = true
  errorMessage.value = null
  openDropdownId.value = null

  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
      sort_by: sortBy.value,
      sort_dir: sortOrder.value
    }

    if (search.value.trim()) params.search = search.value.trim()
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (yearFilter.value !== 'all') params.year = yearFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value

    const res = await apiClient.get('/dept-head/students', { params })
    const resData = res.data

    students.value = resData.data || []

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
    console.error('Failed to load students:', err)
    errorMessage.value = err?.response?.data?.message || 'Failed to load department students. Please try again.'
  } finally {
    isLoading.value = false
  }
}

// ── Search & Filter Watchers ──
const onSearchInput = () => {
  clearTimeout(searchDebounceTimer.value)
  searchDebounceTimer.value = setTimeout(() => {
    currentPage.value = 1
    fetchStudents()
  }, 350)
}

watch(
  [statusFilter, yearFilter, sectionFilter, semesterFilter, perPage, sortBy, sortOrder],
  () => {
    currentPage.value = 1
    fetchStudents()
  }
)

const toggleSort = (col: string) => {
  if (sortBy.value === col) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = col
    sortOrder.value = 'asc'
  }
}

const resetAllFilters = () => {
  search.value = ''
  statusFilter.value = 'all'
  yearFilter.value = 'all'
  sectionFilter.value = 'all'
  semesterFilter.value = 'all'
  currentPage.value = 1
  fetchStudents()
}

const hasActiveFilters = computed(() => {
  return (
    search.value.trim() !== '' ||
    statusFilter.value !== 'all' ||
    yearFilter.value !== 'all' ||
    sectionFilter.value !== 'all' ||
    semesterFilter.value !== 'all'
  )
})

// ── View Details Modal ──
const openDetailModal = async (stu: any) => {
  selectedStudent.value = stu
  detailActiveTab.value = 'profile'
  showDetailModal.value = true
  isLoadingDetail.value = true
  openDropdownId.value = null

  try {
    const res = await apiClient.get(`/dept-head/students/${stu.id}`)
    studentDetail.value = res.data
  } catch (err: any) {
    console.error('Failed to load student details:', err)
    showToast(err?.response?.data?.message || 'Failed to load full student details')
  } finally {
    isLoadingDetail.value = false
  }
}

// ── Add Student Form State ──
const addPhotoPreview = ref<string | null>(null)
const addFileInput = ref<HTMLInputElement | null>(null)
const addFormError = ref<string | null>(null)

const addForm = ref({
  name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  gender: '',
  id_no: '',
  year_level: '1st Year',
  section: 'Section A',
  semester: 'First Semester',
  academic_year: '2026',
  username: '',
  password: '',
  confirm_password: '',
  status: 'active',
  profile_picture: null as File | null
})

const resetAddForm = () => {
  addForm.value = {
    name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    gender: 'Male',
    id_no: '',
    year_level: '1st Year',
    section: 'Section A',
    semester: 'First Semester',
    academic_year: '2026',
    username: '',
    password: '',
    confirm_password: '',
    status: 'active',
    profile_picture: null
  }
  addPhotoPreview.value = null
  addFormError.value = null
  if (addFileInput.value) addFileInput.value.value = ''
}

const openAddView = () => {
  resetAddForm()
  currentView.value = 'add'
}

const handleAddPhotoUpload = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    addForm.value.profile_picture = file
    const reader = new FileReader()
    reader.onload = (re) => {
      addPhotoPreview.value = re.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const submitAddStudent = async () => {
  addFormError.value = null

  if (!addForm.value.name.trim()) {
    addFormError.value = 'Full name is required'
    return
  }
  if (!addForm.value.email.trim()) {
    addFormError.value = 'Email address is required'
    return
  }
  if (!addForm.value.id_no.trim()) {
    addFormError.value = 'Student ID is required'
    return
  }
  if (!addForm.value.password || addForm.value.password.length < 6) {
    addFormError.value = 'Password must be at least 6 characters long'
    return
  }
  if (addForm.value.password !== addForm.value.confirm_password) {
    addFormError.value = 'Passwords do not match'
    return
  }

  isSaving.value = true
  try {
    const formData = new FormData()
    formData.append('name', addForm.value.name.trim())
    formData.append('email', addForm.value.email.trim())
    formData.append('id_no', addForm.value.id_no.trim())
    formData.append('year_level', addForm.value.year_level)
    formData.append('section', addForm.value.section)
    formData.append('semester', addForm.value.semester)
    formData.append('academic_year', addForm.value.academic_year)
    formData.append('status', addForm.value.status)
    formData.append('password', addForm.value.password)

    if (addForm.value.username.trim()) formData.append('username', addForm.value.username.trim())
    if (addForm.value.phone.trim()) formData.append('phone', addForm.value.phone.trim())
    if (addForm.value.gender) formData.append('gender', addForm.value.gender)
    if (addForm.value.date_of_birth) formData.append('date_of_birth', addForm.value.date_of_birth)
    if (addForm.value.profile_picture) formData.append('profile_picture', addForm.value.profile_picture)

    await apiClient.post('/dept-head/students', formData)
    showToast(`Successfully enrolled student ${addForm.value.name}`)
    currentView.value = 'list'
    await fetchStudents()
  } catch (err: any) {
    console.error('Failed to save student:', err)
    addFormError.value = err?.response?.data?.message || 'Failed to create student. Please verify all fields.'
  } finally {
    isSaving.value = false
  }
}

// ── Edit Student Form State ──
const editPhotoPreview = ref<string | null>(null)
const editFileInput = ref<HTMLInputElement | null>(null)
const editFormError = ref<string | null>(null)

const editForm = ref({
  id: 0,
  name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  gender: '',
  id_no: '',
  year_level: '',
  section: '',
  semester: '',
  academic_year: '',
  username: '',
  password: '',
  confirm_password: '',
  status: 'active',
  profile_picture: null as File | null
})

const openEditView = (stu: any) => {
  selectedStudent.value = stu
  editFormError.value = null
  openDropdownId.value = null

  editForm.value = {
    id: stu.id,
    name: stu.name || '',
    email: stu.email || '',
    phone: stu.phone || '',
    date_of_birth: stu.date_of_birth || '',
    gender: stu.gender || 'Male',
    id_no: stu.id_no || '',
    year_level: stu.year_level || '1st Year',
    section: stu.section || 'Section A',
    semester: stu.semester || 'First Semester',
    academic_year: stu.academic_year || '2026',
    username: stu.username || '',
    password: '',
    confirm_password: '',
    status: stu.status || 'active',
    profile_picture: null
  }

  editPhotoPreview.value = resolveAvatarUrl(stu.profile_picture_url)
  currentView.value = 'edit'
}

const handleEditPhotoUpload = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    editForm.value.profile_picture = file
    const reader = new FileReader()
    reader.onload = (re) => {
      editPhotoPreview.value = re.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const submitEditStudent = async () => {
  editFormError.value = null

  if (!editForm.value.name.trim()) {
    editFormError.value = 'Full name is required'
    return
  }
  if (!editForm.value.email.trim()) {
    editFormError.value = 'Email address is required'
    return
  }
  if (!editForm.value.id_no.trim()) {
    editFormError.value = 'Student ID is required'
    return
  }
  if (editForm.value.password && editForm.value.password !== editForm.value.confirm_password) {
    editFormError.value = 'Passwords do not match'
    return
  }

  isSaving.value = true
  try {
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('name', editForm.value.name.trim())
    formData.append('email', editForm.value.email.trim())
    formData.append('id_no', editForm.value.id_no.trim())
    formData.append('year_level', editForm.value.year_level)
    formData.append('section', editForm.value.section)
    formData.append('semester', editForm.value.semester)
    formData.append('academic_year', editForm.value.academic_year)
    formData.append('status', editForm.value.status)

    if (editForm.value.username.trim()) formData.append('username', editForm.value.username.trim())
    if (editForm.value.phone) formData.append('phone', editForm.value.phone.trim())
    if (editForm.value.gender) formData.append('gender', editForm.value.gender)
    if (editForm.value.date_of_birth) formData.append('date_of_birth', editForm.value.date_of_birth)
    if (editForm.value.password) formData.append('password', editForm.value.password)
    if (editForm.value.profile_picture) formData.append('profile_picture', editForm.value.profile_picture)

    await apiClient.post(`/dept-head/students/${editForm.value.id}`, formData)
    showToast(`Updated student profile for ${editForm.value.name}`)
    currentView.value = 'list'
    await fetchStudents()
  } catch (err: any) {
    console.error('Failed to update student:', err)
    editFormError.value = err?.response?.data?.message || 'Failed to update student. Please check input fields.'
  } finally {
    isSaving.value = false
  }
}

// ── Status Toggle Modal ──
const openStatusModal = (stu: any) => {
  selectedStudent.value = stu
  showStatusModal.value = true
  openDropdownId.value = null
}

const toggleStudentStatus = async () => {
  if (!selectedStudent.value) return
  const newStatus = selectedStudent.value.status === 'active' ? 'inactive' : 'active'
  isSaving.value = true

  try {
    await apiClient.patch(`/dept-head/students/${selectedStudent.value.id}/status`, {
      status: newStatus
    })
    showStatusModal.value = false
    showToast(`Status of ${selectedStudent.value.name} changed to ${newStatus.toUpperCase()}`)
    await fetchStudents()
  } catch (err: any) {
    console.error('Failed to change status:', err)
    alert(err?.response?.data?.message || 'Failed to update student status')
  } finally {
    isSaving.value = false
  }
}

// ── Delete / Deactivate Guard Modal ──
const openDeleteModal = (stu: any) => {
  selectedStudent.value = stu
  showDeleteModal.value = true
  openDropdownId.value = null
}

const executeDelete = async (force: boolean = false) => {
  if (!selectedStudent.value) return
  isSaving.value = true

  try {
    await apiClient.delete(`/dept-head/students/${selectedStudent.value.id}${force ? '?force=1' : ''}`)
    showDeleteModal.value = false
    showToast(`Student ${selectedStudent.value.name} has been removed`)
    await fetchStudents()
  } catch (err: any) {
    console.error('Failed to delete student:', err)
    if (err?.response?.status === 422 && err?.response?.data?.has_attempts) {
      alert(err.response.data.message)
    } else {
      alert(err?.response?.data?.message || 'Failed to delete student')
    }
  } finally {
    isSaving.value = false
  }
}

const deactivateInsteadOfDelete = async () => {
  if (!selectedStudent.value) return
  isSaving.value = true
  try {
    await apiClient.patch(`/dept-head/students/${selectedStudent.value.id}/status`, {
      status: 'inactive'
    })
    showDeleteModal.value = false
    showToast(`Student ${selectedStudent.value.name} has been set to INACTIVE`)
    await fetchStudents()
  } catch (err: any) {
    console.error('Failed to deactivate student:', err)
    alert(err?.response?.data?.message || 'Failed to deactivate student')
  } finally {
    isSaving.value = false
  }
}

// ── Export Handling ──
const showExportDropdown = ref(false)

const handleExport = async (format: 'pdf' | 'excel' | 'csv') => {
  showExportDropdown.value = false
  isExporting.value = true

  try {
    const params: Record<string, string> = { format }
    if (search.value.trim()) params.search = search.value.trim()
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (yearFilter.value !== 'all') params.year = yearFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (semesterFilter.value !== 'all') params.semester = semesterFilter.value

    const res = await apiClient.get('/dept-head/students/export', { params })
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
    if (format === 'pdf') {
      mimeType = 'application/pdf'
    } else if (format === 'excel') {
      mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    } else if (format === 'csv') {
      mimeType = 'text/csv;charset=utf-8;'
    }

    const blob = new Blob([array], { type: mimeType })
    const blobUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)

    showToast(`Successfully exported students as ${format.toUpperCase()}`)
  } catch (err: any) {
    console.error('Export error:', err)
    alert(err?.response?.data?.message || 'Failed to export students. Please try again.')
  } finally {
    isExporting.value = false
  }
}

// ── Close Dropdowns on Click Outside ──
onMounted(() => {
  fetchStudents()

  const handleGlobalClick = (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.export-dropdown-container')) {
      showExportDropdown.value = false
    }
    if (!target.closest('.action-dropdown-container')) {
      openDropdownId.value = null
    }
  }

  window.addEventListener('click', handleGlobalClick)
})

// ── Display Pages for Pagination ──
const displayPages = computed(() => {
  const tp = lastPage.value
  if (tp <= 5) return Array.from({ length: tp }, (_, i) => i + 1)
  const pages: (number | '...')[] = [1]
  if (currentPage.value > 3) pages.push('...')
  const start = Math.max(2, currentPage.value - 1)
  const end = Math.min(tp - 1, currentPage.value + 1)
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  if (currentPage.value < tp - 2) pages.push('...')
  pages.push(tp)
  return pages
})
</script>

<template>
  <div class="space-y-6">

    <!-- ── Toast Alerts ── -->
    <Teleport to="body">
      <div v-if="successToast" class="fixed top-5 right-5 z-[999] flex items-center gap-3 bg-slate-900/95 text-white px-5 py-3.5 rounded-2xl shadow-2xl backdrop-blur-md border border-slate-700/50 animate-in fade-in slide-in-from-top-4 duration-300">
        <div class="w-7 h-7 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <p class="text-[13px] font-semibold">{{ successToast }}</p>
      </div>

      <div v-if="copyToast" class="fixed top-5 right-5 z-[999] flex items-center gap-3 bg-slate-900/95 text-white px-4 py-2.5 rounded-xl shadow-xl backdrop-blur-md border border-slate-700/50">
        <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
        <p class="text-[12px] font-medium">{{ copyToast }}</p>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW: ADD NEW STUDENT                                                 -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <template v-if="currentView === 'add'">
      <div class="space-y-6">
        <!-- Top Navigation & Title -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1 font-medium">
              <button @click="currentView = 'list'" class="hover:text-[#5138ed] transition-colors">Students</button>
              <span>/</span>
              <span class="text-slate-800 font-semibold">Enroll New Student</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Enroll Department Student</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Register a new student under {{ department.name || 'Department' }}.</p>
          </div>
          <button
            @click="currentView = 'list'"
            class="inline-flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-xs self-start sm:self-auto"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Directory
          </button>
        </div>

        <!-- Add Form Error Banner -->
        <div v-if="addFormError" class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-3">
          <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
          <div>
            <h4 class="text-xs font-bold text-rose-800">Enrollment Validation Error</h4>
            <p class="text-xs text-rose-600 mt-0.5">{{ addFormError }}</p>
          </div>
        </div>

        <form @submit.prevent="submitAddStudent" class="space-y-6">

          <!-- Section 1: Personal Details -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-7 space-y-6">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
              <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900">Personal Information</h3>
                <p class="text-xs text-slate-400">Student full identity and contact details</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Full Name <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.name"
                  type="text"
                  required
                  placeholder="e.g. Kalkidan Mengistu"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Email Address <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.email"
                  type="email"
                  required
                  placeholder="e.g. kalkidan@wollo.edu.et"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Phone Number</label>
                <input
                  v-model="addForm.phone"
                  type="tel"
                  placeholder="e.g. +251 91 123 4567"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Gender</label>
                <select
                  v-model="addForm.gender"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                >
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Date of Birth</label>
                <input
                  v-model="addForm.date_of_birth"
                  type="date"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <!-- Profile Picture Upload -->
              <div class="space-y-1.5 md:col-span-2 lg:col-span-1">
                <label class="block text-xs font-bold text-slate-700">Profile Photo</label>
                <div class="flex items-center gap-3">
                  <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                    <img v-if="addPhotoPreview" :src="addPhotoPreview" class="w-full h-full object-cover" />
                    <svg v-else class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  </div>
                  <input
                    ref="addFileInput"
                    type="file"
                    accept="image/*"
                    @change="handleAddPhotoUpload"
                    class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-[#5138ed] hover:file:bg-indigo-100 cursor-pointer"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Section 2: Academic Enrollment Details -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-7 space-y-6">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
              <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900">Academic Program & Enrollment</h3>
                <p class="text-xs text-slate-400">Department scoping, student ID, and cohort placement</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              <!-- Department (Locked to Dept Head's department) -->
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Assigned Department</label>
                <div class="px-4 py-2.5 text-xs sm:text-sm font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                  <span>{{ department.name || 'Department' }} ({{ department.code || 'DEPT' }})</span>
                  <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 bg-indigo-50 text-[#5138ed] rounded-md">Locked</span>
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Student ID / Registration No. <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.id_no"
                  type="text"
                  required
                  placeholder="e.g. UGR/1115/18"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none font-mono"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Year Level <span class="text-rose-500">*</span></label>
                <select
                  v-model="addForm.year_level"
                  required
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                >
                  <option value="1st Year">1st Year</option>
                  <option value="2nd Year">2nd Year</option>
                  <option value="3rd Year">3rd Year</option>
                  <option value="4th Year">4th Year</option>
                  <option value="5th Year">5th Year</option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Assigned Section</label>
                <select
                  v-model="addForm.section"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                >
                  <option value="Section A">Section A</option>
                  <option value="Section B">Section B</option>
                  <option value="Section C">Section C</option>
                  <option value="Section D">Section D</option>
                  <option value="A">Section A (Short)</option>
                  <option value="B">Section B (Short)</option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Current Semester</label>
                <select
                  v-model="addForm.semester"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                >
                  <option value="First Semester">First Semester</option>
                  <option value="Second Semester">Second Semester</option>
                  <option value="Summer">Summer</option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Academic Year</label>
                <input
                  v-model="addForm.academic_year"
                  type="text"
                  placeholder="e.g. 2026 or 2025/2026"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>
            </div>
          </div>

          <!-- Section 3: Credentials & Access -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-7 space-y-6">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
              <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900">Student Portal Credentials</h3>
                <p class="text-xs text-slate-400">Account login and examination platform access</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Portal Username <span class="text-slate-400 font-normal">(Optional)</span></label>
                <input
                  v-model="addForm.username"
                  type="text"
                  placeholder="Auto-generated from email"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Initial Password <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.password"
                  type="password"
                  required
                  placeholder="Min 6 characters"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Confirm Password <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.confirm_password"
                  type="password"
                  required
                  placeholder="Repeat password"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Initial Status</label>
                <select
                  v-model="addForm.status"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none font-semibold"
                >
                  <option value="active">Active (Permitted to sit exams)</option>
                  <option value="inactive">Inactive (Access suspended)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Bottom Actions -->
          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              @click="currentView = 'list'"
              class="px-5 py-2.5 text-xs sm:text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="inline-flex items-center gap-2 px-6 py-2.5 text-xs sm:text-sm font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all disabled:opacity-60 cursor-pointer"
            >
              <svg v-if="isSaving" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
              <span>{{ isSaving ? 'Enrolling Student...' : 'Confirm & Enroll Student' }}</span>
            </button>
          </div>
        </form>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW: EDIT STUDENT                                                    -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <template v-else-if="currentView === 'edit'">
      <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1 font-medium">
              <button @click="currentView = 'list'" class="hover:text-[#5138ed] transition-colors">Students</button>
              <span>/</span>
              <span class="text-slate-800 font-semibold">Edit Student Profile</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Edit Student: {{ editForm.name }}</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Update personal, academic enrollment, or credentials.</p>
          </div>
          <button
            @click="currentView = 'list'"
            class="inline-flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-xs self-start sm:self-auto"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Directory
          </button>
        </div>

        <div v-if="editFormError" class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-3">
          <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
          <div>
            <h4 class="text-xs font-bold text-rose-800">Update Validation Error</h4>
            <p class="text-xs text-rose-600 mt-0.5">{{ editFormError }}</p>
          </div>
        </div>

        <form @submit.prevent="submitEditStudent" class="space-y-6">

          <!-- Edit Personal Info -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-7 space-y-6">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
              <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </div>
              <h3 class="text-sm font-bold text-slate-900">Personal Information</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Full Name <span class="text-rose-500">*</span></label>
                <input
                  v-model="editForm.name"
                  type="text"
                  required
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Email Address <span class="text-rose-500">*</span></label>
                <input
                  v-model="editForm.email"
                  type="email"
                  required
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Phone Number</label>
                <input
                  v-model="editForm.phone"
                  type="tel"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Gender</label>
                <select
                  v-model="editForm.gender"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                >
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Date of Birth</label>
                <input
                  v-model="editForm.date_of_birth"
                  type="date"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5 md:col-span-2 lg:col-span-1">
                <label class="block text-xs font-bold text-slate-700">Change Profile Photo</label>
                <div class="flex items-center gap-3">
                  <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                    <img v-if="editPhotoPreview" :src="editPhotoPreview" class="w-full h-full object-cover" />
                    <svg v-else class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  </div>
                  <input
                    ref="editFileInput"
                    type="file"
                    accept="image/*"
                    @change="handleEditPhotoUpload"
                    class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-[#5138ed] hover:file:bg-indigo-100 cursor-pointer"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Edit Academic Info -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-7 space-y-6">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
              <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
              </div>
              <h3 class="text-sm font-bold text-slate-900">Academic Cohort & Section</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Student ID / Registration No. <span class="text-rose-500">*</span></label>
                <input
                  v-model="editForm.id_no"
                  type="text"
                  required
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none font-mono"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Year Level <span class="text-rose-500">*</span></label>
                <select
                  v-model="editForm.year_level"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                >
                  <option value="1st Year">1st Year</option>
                  <option value="2nd Year">2nd Year</option>
                  <option value="3rd Year">3rd Year</option>
                  <option value="4th Year">4th Year</option>
                  <option value="5th Year">5th Year</option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Assigned Section</label>
                <input
                  v-model="editForm.section"
                  type="text"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Semester</label>
                <select
                  v-model="editForm.semester"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                >
                  <option value="First Semester">First Semester</option>
                  <option value="Second Semester">Second Semester</option>
                  <option value="Summer">Summer</option>
                </select>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Academic Year</label>
                <input
                  v-model="editForm.academic_year"
                  type="text"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Account Status</label>
                <select
                  v-model="editForm.status"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl bg-white focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none font-bold"
                >
                  <option value="active">Active (Permitted)</option>
                  <option value="inactive">Inactive (Suspended)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Edit Credentials (Optional password change) -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 sm:p-7 space-y-6">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
              <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900">Security & Credentials</h3>
                <p class="text-xs text-slate-400">Leave passwords blank to keep existing password unchanged</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Username</label>
                <input
                  v-model="editForm.username"
                  type="text"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">New Password <span class="text-slate-400 font-normal">(Optional)</span></label>
                <input
                  v-model="editForm.password"
                  type="password"
                  placeholder="Leave blank to keep current"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700">Confirm New Password</label>
                <input
                  v-model="editForm.confirm_password"
                  type="password"
                  placeholder="Confirm new password"
                  class="w-full px-4 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
                />
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              @click="currentView = 'list'"
              class="px-5 py-2.5 text-xs sm:text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="inline-flex items-center gap-2 px-6 py-2.5 text-xs sm:text-sm font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all disabled:opacity-60 cursor-pointer"
            >
              <svg v-if="isSaving" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
              <span>{{ isSaving ? 'Saving Changes...' : 'Save Changes' }}</span>
            </button>
          </div>
        </form>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW: STUDENTS DIRECTORY & MANAGEMENT CENTER (DEFAULT LIST)            -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <template v-else>

      <!-- ── Branded Header ── -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
              Department Student Management Center
            </h1>
            <!-- Department Scoped Badge -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-indigo-50 text-[#5138ed] border border-indigo-200/60 shadow-2xs">
              <span class="w-1.5 h-1.5 rounded-full bg-[#5138ed]"></span>
              {{ department.name || 'Department' }} • {{ department.code || 'DEPT' }}
            </span>
            <!-- Active Term Badge -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200/80">
              <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              {{ activeTerm }}
            </span>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">
            Wollo University Academic Administration • Scoped student directory, curriculum enrollments, and real assessment records.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-3 self-start lg:self-auto">
          <!-- Export Dropdown -->
          <div class="relative export-dropdown-container">
            <button
              type="button"
              @click="showExportDropdown = !showExportDropdown"
              :disabled="isExporting"
              class="inline-flex items-center gap-2 px-4 py-2.5 text-xs sm:text-sm font-bold text-[#5138ed] bg-white border border-indigo-200 hover:bg-indigo-50/60 rounded-xl transition-all shadow-xs disabled:opacity-60 cursor-pointer"
            >
              <svg v-if="!isExporting" class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
              <svg v-else class="animate-spin w-4 h-4 text-[#5138ed]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
              <span>{{ isExporting ? 'Exporting...' : 'Export' }}</span>
              <svg class="w-3 h-3 text-[#5138ed]/70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <!-- Export Dropdown Menu -->
            <div
              v-if="showExportDropdown"
              class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-2xl border border-slate-100 py-2 z-50 animate-in fade-in slide-in-from-top-2 duration-150"
            >
              <div class="px-4 py-1.5 border-b border-slate-100 mb-1">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Export Student Registry</p>
              </div>
              <button
                type="button"
                @click="handleExport('pdf')"
                class="w-full px-4 py-2.5 text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors flex items-center gap-3 cursor-pointer"
              >
                <div class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <div>
                  <p class="font-bold">PDF Document</p>
                  <p class="text-[10px] text-slate-400">Printable official roster (.pdf)</p>
                </div>
              </button>
              <button
                type="button"
                @click="handleExport('excel')"
                class="w-full px-4 py-2.5 text-left text-xs font-semibold text-slate-700 hover:bg-emerald-50/70 hover:text-emerald-700 transition-colors flex items-center gap-3 cursor-pointer"
              >
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                  <p class="font-bold">Excel Workbook</p>
                  <p class="text-[10px] text-slate-400">Full data spreadsheet (.xlsx)</p>
                </div>
              </button>
              <button
                type="button"
                @click="handleExport('csv')"
                class="w-full px-4 py-2.5 text-left text-xs font-semibold text-slate-700 hover:bg-sky-50/70 hover:text-sky-700 transition-colors flex items-center gap-3 cursor-pointer"
              >
                <div class="w-7 h-7 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                </div>
                <div>
                  <p class="font-bold">CSV File</p>
                  <p class="text-[10px] text-slate-400">Universal comma-separated (.csv)</p>
                </div>
              </button>
            </div>
          </div>

          <!-- Add Student Button -->
          <button
            type="button"
            @click="openAddView"
            class="inline-flex items-center gap-2 px-5 py-2.5 text-xs sm:text-sm font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Enroll Student</span>
          </button>
        </div>
      </div>

      <!-- ── KPI Statistics Cards (STRICT REAL DATA) ── -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Students -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Enrolled</span>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
            </div>
          </div>
          <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2 tracking-tight">{{ stats.total }}</p>
          <p class="text-xs text-slate-400 mt-1 font-medium">Department registered students</p>
        </div>

        <!-- 2. Active Students -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Students</span>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
          </div>
          <p class="text-2xl sm:text-3xl font-black text-emerald-600 mt-2 tracking-tight">{{ stats.active }}</p>
          <p class="text-xs text-slate-400 mt-1 font-medium">Eligible to sit department exams</p>
        </div>

        <!-- 3. Inactive / Suspended -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Inactive Accounts</span>
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
          </div>
          <p class="text-2xl sm:text-3xl font-black text-slate-700 mt-2 tracking-tight">{{ stats.inactive }}</p>
          <p class="text-xs text-slate-400 mt-1 font-medium">Deactivated or suspended access</p>
        </div>

        <!-- 4. New This Semester -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Admitted Recently</span>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            </div>
          </div>
          <p class="text-2xl sm:text-3xl font-black text-amber-600 mt-2 tracking-tight">{{ stats.new_this_semester }}</p>
          <p class="text-xs text-slate-400 mt-1 font-medium">Enrolled within last 6 months</p>
        </div>
      </div>

      <!-- ── Search & Filter Toolbar Card ── -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs p-5 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">

          <!-- Search Input -->
          <div class="relative flex-1 max-w-md">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input
              v-model="search"
              @input="onSearchInput"
              type="text"
              placeholder="Search by name, student ID, email, username..."
              class="w-full pl-10 pr-9 py-2.5 text-xs sm:text-sm border border-slate-200 rounded-xl focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 transition-all outline-none"
            />
            <button
              v-if="search"
              @click="search = ''; onSearchInput()"
              class="w-5 h-5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 flex items-center justify-center cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Filter Dropdowns Row -->
          <div class="flex flex-wrap items-center gap-2.5">
            <!-- Status Filter -->
            <div class="relative min-w-[130px]">
              <select
                v-model="statusFilter"
                class="w-full pl-3 pr-8 py-2.5 text-xs font-bold border border-slate-200 rounded-xl bg-white text-slate-700 focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 outline-none appearance-none cursor-pointer"
              >
                <option value="all">Status: All</option>
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
              </select>
              <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <!-- Year Level Filter -->
            <div class="relative min-w-[130px]">
              <select
                v-model="yearFilter"
                class="w-full pl-3 pr-8 py-2.5 text-xs font-bold border border-slate-200 rounded-xl bg-white text-slate-700 focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 outline-none appearance-none cursor-pointer"
              >
                <option value="all">Year: All</option>
                <option v-for="y in (filterOptions.year_levels.length ? filterOptions.year_levels : ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'])" :key="y" :value="y">
                  {{ y }}
                </option>
              </select>
              <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <!-- Section Filter -->
            <div class="relative min-w-[130px]">
              <select
                v-model="sectionFilter"
                class="w-full pl-3 pr-8 py-2.5 text-xs font-bold border border-slate-200 rounded-xl bg-white text-slate-700 focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 outline-none appearance-none cursor-pointer"
              >
                <option value="all">Section: All</option>
                <option v-for="sec in (filterOptions.sections.length ? filterOptions.sections : ['Section A', 'Section B', 'Section C', 'Section D'])" :key="sec" :value="sec">
                  {{ sec }}
                </option>
              </select>
              <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <!-- Semester Filter -->
            <div class="relative min-w-[140px]">
              <select
                v-model="semesterFilter"
                class="w-full pl-3 pr-8 py-2.5 text-xs font-bold border border-slate-200 rounded-xl bg-white text-slate-700 focus:border-[#5138ed] focus:ring-2 focus:ring-[#5138ed]/20 outline-none appearance-none cursor-pointer"
              >
                <option value="all">Semester: All</option>
                <option value="First Semester">First Semester</option>
                <option value="Second Semester">Second Semester</option>
                <option value="Summer">Summer</option>
              </select>
              <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
          </div>
        </div>

        <!-- Active Filter Chips -->
        <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
          <span class="text-xs font-bold text-slate-400">Active Filters:</span>

          <span v-if="search.trim()" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-[#5138ed]">
            Search: "{{ search }}"
            <button @click="search = ''; onSearchInput()" class="hover:text-indigo-900 cursor-pointer">×</button>
          </span>

          <span v-if="statusFilter !== 'all'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700">
            Status: {{ statusFilter }}
            <button @click="statusFilter = 'all'" class="hover:text-slate-900 cursor-pointer">×</button>
          </span>

          <span v-if="yearFilter !== 'all'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700">
            Year: {{ yearFilter }}
            <button @click="yearFilter = 'all'" class="hover:text-slate-900 cursor-pointer">×</button>
          </span>

          <span v-if="sectionFilter !== 'all'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700">
            Section: {{ sectionFilter }}
            <button @click="sectionFilter = 'all'" class="hover:text-slate-900 cursor-pointer">×</button>
          </span>

          <span v-if="semesterFilter !== 'all'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700">
            Semester: {{ semesterFilter }}
            <button @click="semesterFilter = 'all'" class="hover:text-slate-900 cursor-pointer">×</button>
          </span>

          <button
            @click="resetAllFilters"
            class="text-xs font-bold text-rose-600 hover:text-rose-700 hover:underline ml-1 cursor-pointer"
          >
            Clear All
          </button>
        </div>
      </div>

      <!-- ── Data Table / Directory Card ── -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">

        <!-- Loading State -->
        <div v-if="isLoading" class="p-16 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-50 text-[#5138ed] mb-4">
            <svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
          </div>
          <h3 class="text-sm font-bold text-slate-800">Loading Student Registry</h3>
          <p class="text-xs text-slate-400 mt-1">Retrieving department records from database...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="errorMessage" class="p-12 text-center">
          <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <h3 class="text-sm font-bold text-slate-800">Failed to Load Students</h3>
          <p class="text-xs text-rose-600 mt-1 max-w-md mx-auto">{{ errorMessage }}</p>
          <button @click="fetchStudents" class="mt-4 px-4 py-2 bg-indigo-50 text-[#5138ed] rounded-xl text-xs font-bold hover:bg-indigo-100 transition-colors">
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="students.length === 0" class="p-16 text-center">
          <div class="w-16 h-16 rounded-2xl bg-indigo-50/60 text-[#5138ed] mx-auto flex items-center justify-center mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
          </div>
          <h3 class="text-base font-bold text-slate-800">No Students Found</h3>
          <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-sm mx-auto">
            {{ hasActiveFilters ? 'No students match your active filter criteria. Try resetting filters.' : 'No students have been enrolled in this department yet.' }}
          </p>
          <div class="mt-5 flex items-center justify-center gap-3">
            <button
              v-if="hasActiveFilters"
              @click="resetAllFilters"
              class="px-4 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors"
            >
              Reset Filters
            </button>
            <button
              @click="openAddView"
              class="px-4 py-2 text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl transition-colors shadow-xs"
            >
              Enroll First Student
            </button>
          </div>
        </div>

        <!-- ── Desktop Table ── -->
        <div v-else class="hidden md:block overflow-x-auto min-w-0 w-full">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 select-none">
                <th @click="toggleSort('name')" class="py-3.5 px-5 cursor-pointer hover:text-slate-600 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Student</span>
                    <span v-if="sortBy === 'name'" class="text-[#5138ed]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th @click="toggleSort('id_no')" class="py-3.5 px-4 cursor-pointer hover:text-slate-600 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Student ID</span>
                    <span v-if="sortBy === 'id_no'" class="text-[#5138ed]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-3.5 px-4">Contact Info</th>
                <th @click="toggleSort('year_level')" class="py-3.5 px-4 cursor-pointer hover:text-slate-600 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Cohort / Section</span>
                    <span v-if="sortBy === 'year_level'" class="text-[#5138ed]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-3.5 px-4">Exams Taken</th>
                <th @click="toggleSort('status')" class="py-3.5 px-4 cursor-pointer hover:text-slate-600 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Status</span>
                    <span v-if="sortBy === 'status'" class="text-[#5138ed]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th @click="toggleSort('created_at')" class="py-3.5 px-4 cursor-pointer hover:text-slate-600 transition-colors">
                  <div class="flex items-center gap-1.5">
                    <span>Enrolled On</span>
                    <span v-if="sortBy === 'created_at'" class="text-[#5138ed]">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="py-3.5 px-5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
              <tr
                v-for="stu in students"
                :key="stu.id"
                class="hover:bg-slate-50/70 transition-colors group"
              >
                <!-- Student Name & Avatar -->
                <td class="py-3.5 px-5">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl overflow-hidden shrink-0 border border-slate-200/80 shadow-2xs flex items-center justify-center bg-slate-100">
                      <img
                        v-if="stu.profile_picture_url"
                        :src="resolveAvatarUrl(stu.profile_picture_url) || ''"
                        :alt="stu.name"
                        class="w-full h-full object-cover"
                        @error="(e: any) => { e.target.style.display = 'none'; (e.target.nextElementSibling as HTMLElement)?.classList.remove('hidden') }"
                      />
                      <div
                        :class="[avatarColor(stu.name), 'w-full h-full flex items-center justify-center text-xs font-black', stu.profile_picture_url ? 'hidden' : '']"
                      >
                        {{ getInitials(stu.name) }}
                      </div>
                    </div>
                    <div class="min-w-0">
                      <p class="font-bold text-slate-900 group-hover:text-[#5138ed] transition-colors truncate">
                        {{ stu.name }}
                      </p>
                      <p class="text-[11px] text-slate-400 font-mono truncate">
                        @{{ stu.username || (stu.email ? stu.email.split('@')[0] : 'student') }}
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Student ID Pill -->
                <td class="py-3.5 px-4">
                  <span class="inline-flex items-center font-mono font-bold text-xs bg-slate-100 text-slate-800 px-2.5 py-1 rounded-lg border border-slate-200/70">
                    {{ stu.id_no || `WU-${stu.id}` }}
                  </span>
                </td>

                <!-- Email & Phone -->
                <td class="py-3.5 px-4">
                  <div class="space-y-0.5">
                    <p
                      @click="copyToClipboard(stu.email, 'Email')"
                      title="Click to copy email"
                      class="text-xs text-slate-700 hover:text-[#5138ed] cursor-pointer truncate max-w-[170px]"
                    >
                      {{ stu.email }}
                    </p>
                    <p v-if="stu.phone" class="text-[11px] text-slate-400">
                      {{ stu.phone }}
                    </p>
                  </div>
                </td>

                <!-- Year Level & Section -->
                <td class="py-3.5 px-4">
                  <div class="space-y-0.5">
                    <span class="font-bold text-slate-800 block text-xs">{{ stu.year_level || '1st Year' }}</span>
                    <span class="text-[11px] text-slate-400 block">{{ stu.section || 'Section A' }}</span>
                  </div>
                </td>

                <!-- Exam Attempts Count -->
                <td class="py-3.5 px-4">
                  <span
                    v-if="(stu.exam_attempts_count ?? 0) > 0"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-50 text-[#5138ed]"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-[#5138ed]"></span>
                    {{ stu.exam_attempts_count }} exam{{ stu.exam_attempts_count === 1 ? '' : 's' }}
                  </span>
                  <span v-else class="text-[11px] text-slate-400 italic">
                    None yet
                  </span>
                </td>

                <!-- Status Badge -->
                <td class="py-3.5 px-4">
                  <span
                    v-if="stu.status === 'active'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Active
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200/80"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                    Inactive
                  </span>
                </td>

                <!-- Admission Date -->
                <td class="py-3.5 px-4 text-slate-500 text-xs">
                  {{ stu.created_at ? new Date(stu.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }}
                </td>

                <!-- Actions 3-Dot Dropdown -->
                <td class="py-3.5 px-5 text-right">
                  <div class="relative inline-block text-left action-dropdown-container">
                    <button
                      type="button"
                      @click="toggleDropdown(stu.id, $event)"
                      class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                    </button>

                    <!-- Dropdown Options -->
                    <div
                      v-if="openDropdownId === stu.id"
                      class="absolute right-0 mt-1 w-52 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 z-40 animate-in fade-in slide-in-from-top-2 duration-150 text-left"
                    >
                      <button
                        type="button"
                        @click="openDetailModal(stu)"
                        class="w-full px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors flex items-center gap-2.5 cursor-pointer"
                      >
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>Profile & Exam Results</span>
                      </button>

                      <button
                        type="button"
                        @click="openEditView(stu)"
                        class="w-full px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                      >
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit Student</span>
                      </button>

                      <button
                        type="button"
                        @click="openStatusModal(stu)"
                        class="w-full px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                      >
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <span>{{ stu.status === 'active' ? 'Deactivate Account' : 'Activate Account' }}</span>
                      </button>

                      <div class="my-1 border-t border-slate-100"></div>

                      <button
                        type="button"
                        @click="openDeleteModal(stu)"
                        class="w-full px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                      >
                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>Remove Student</span>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ── Mobile Card View (< 768px) ── -->
        <div class="md:hidden divide-y divide-slate-100">
          <div
            v-for="stu in students"
            :key="stu.id"
            class="p-4 space-y-3.5 hover:bg-slate-50/60 transition-colors"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-2xl overflow-hidden shrink-0 border border-slate-200/80 shadow-2xs flex items-center justify-center bg-slate-100">
                  <img
                    v-if="stu.profile_picture_url"
                    :src="resolveAvatarUrl(stu.profile_picture_url) || ''"
                    :alt="stu.name"
                    class="w-full h-full object-cover"
                  />
                  <div
                    v-else
                    :class="[avatarColor(stu.name), 'w-full h-full flex items-center justify-center text-xs font-black']"
                  >
                    {{ getInitials(stu.name) }}
                  </div>
                </div>
                <div class="min-w-0">
                  <p class="font-bold text-sm text-slate-900 truncate">{{ stu.name }}</p>
                  <p class="text-[11px] font-mono text-slate-500 font-semibold mt-0.5">{{ stu.id_no || `WU-${stu.id}` }}</p>
                  <p class="text-[11px] text-slate-400 truncate">{{ stu.email }}</p>
                </div>
              </div>

              <!-- Status Badge -->
              <span
                v-if="stu.status === 'active'"
                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 shrink-0"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Active
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 shrink-0"
              >
                Inactive
              </span>
            </div>

            <!-- Meta Attributes Grid -->
            <div class="grid grid-cols-3 gap-2 bg-slate-50 p-2.5 rounded-xl text-center text-xs">
              <div>
                <p class="text-[10px] font-semibold text-slate-400">Cohort</p>
                <p class="font-bold text-slate-800 mt-0.5">{{ stu.year_level || '1st Year' }}</p>
              </div>
              <div>
                <p class="text-[10px] font-semibold text-slate-400">Section</p>
                <p class="font-bold text-slate-800 mt-0.5">{{ stu.section || '—' }}</p>
              </div>
              <div>
                <p class="text-[10px] font-semibold text-slate-400">Exams Taken</p>
                <p class="font-bold text-[#5138ed] mt-0.5">{{ stu.exam_attempts_count ?? 0 }}</p>
              </div>
            </div>

            <!-- Mobile Action Buttons -->
            <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
              <button
                type="button"
                @click="openDetailModal(stu)"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors cursor-pointer"
              >
                Results
              </button>
              <button
                type="button"
                @click="openEditView(stu)"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer"
              >
                Edit
              </button>
              <button
                type="button"
                @click="openStatusModal(stu)"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer"
              >
                Status
              </button>
              <button
                type="button"
                @click="openDeleteModal(stu)"
                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors cursor-pointer"
              >
                Remove
              </button>
            </div>
          </div>
        </div>

        <!-- ── Pagination Footer ── -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 sm:px-6 sm:py-4 border-t border-slate-100 bg-white">
          <div class="flex items-center gap-3 text-xs text-slate-500 font-medium">
            <span>
              Showing {{ fromItem }} to {{ toItem }} of {{ totalItems }} students
            </span>
            <div class="relative">
              <select
                v-model="perPage"
                class="pl-2 pr-6 py-1 text-xs font-bold border border-slate-200 rounded-lg bg-white text-slate-700 outline-none appearance-none cursor-pointer"
              >
                <option :value="10">10 / page</option>
                <option :value="25">25 / page</option>
                <option :value="50">50 / page</option>
              </select>
              <svg class="w-3 h-3 text-slate-400 absolute right-1.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
          </div>

          <div class="flex items-center gap-1">
            <button
              @click="currentPage = Math.max(1, currentPage - 1); fetchStudents()"
              :disabled="currentPage <= 1"
              class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <template v-for="p in displayPages" :key="p">
              <span v-if="p === '...'" class="w-8 h-8 flex items-center justify-center text-slate-400 text-xs">...</span>
              <button
                v-else
                @click="currentPage = (p as number); fetchStudents()"
                :class="[
                  currentPage === p
                    ? 'bg-[#5138ed] text-white border border-[#5138ed] font-bold shadow-xs'
                    : 'text-slate-600 border border-slate-200 hover:bg-slate-50 font-semibold',
                  'w-8 h-8 rounded-xl text-xs transition-colors'
                ]"
              >
                {{ p }}
              </button>
            </template>

            <button
              @click="currentPage = Math.min(lastPage, currentPage + 1); fetchStudents()"
              :disabled="currentPage >= lastPage"
              class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: STUDENT DETAILS & REAL ASSESSMENT RESULTS                        -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showDetailModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-3 sm:p-5"
      >
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">

          <!-- Modal Header -->
          <div class="p-6 bg-slate-900 text-white flex items-start justify-between gap-4">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl overflow-hidden bg-white/10 border-2 border-white/20 shadow-md flex items-center justify-center shrink-0">
                <img
                  v-if="selectedStudent?.profile_picture_url"
                  :src="resolveAvatarUrl(selectedStudent.profile_picture_url) || ''"
                  class="w-full h-full object-cover"
                />
                <span v-else class="text-lg font-black text-white">
                  {{ getInitials(selectedStudent?.name) }}
                </span>
              </div>
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h2 class="text-lg sm:text-xl font-black text-white leading-tight">
                    {{ selectedStudent?.name }}
                  </h2>
                  <span
                    :class="[
                      selectedStudent?.status === 'active' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                      'text-[10px] font-bold px-2 py-0.5 rounded-md border uppercase tracking-wider'
                    ]"
                  >
                    {{ selectedStudent?.status }}
                  </span>
                </div>
                <p class="text-xs text-slate-300 font-mono mt-1">
                  ID: {{ selectedStudent?.id_no || `WU-${selectedStudent?.id}` }} • {{ selectedStudent?.email }}
                </p>
              </div>
            </div>

            <button
              @click="showDetailModal = false"
              class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer shrink-0"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Navigation Tabs -->
          <div class="flex items-center px-6 border-b border-slate-100 bg-slate-50/60 overflow-x-auto">
            <button
              @click="detailActiveTab = 'profile'"
              :class="[
                detailActiveTab === 'profile'
                  ? 'border-[#5138ed] text-[#5138ed] font-extrabold'
                  : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold',
                'py-3.5 px-4 text-xs border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap cursor-pointer'
              ]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              <span>Profile & Bio</span>
            </button>

            <button
              @click="detailActiveTab = 'courses'"
              :class="[
                detailActiveTab === 'courses'
                  ? 'border-[#5138ed] text-[#5138ed] font-extrabold'
                  : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold',
                'py-3.5 px-4 text-xs border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap cursor-pointer'
              ]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              <span>Curriculum Courses ({{ studentDetail?.enrolled_courses?.length ?? 0 }})</span>
            </button>

            <button
              @click="detailActiveTab = 'assessments'"
              :class="[
                detailActiveTab === 'assessments'
                  ? 'border-[#5138ed] text-[#5138ed] font-extrabold'
                  : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold',
                'py-3.5 px-4 text-xs border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap cursor-pointer'
              ]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>Examination Records ({{ studentDetail?.exam_attempts?.length ?? 0 }})</span>
            </button>
          </div>

          <!-- Modal Body Content -->
          <div class="p-6 overflow-y-auto flex-1 space-y-6">

            <!-- Detail Loading -->
            <div v-if="isLoadingDetail" class="py-12 text-center">
              <svg class="animate-spin w-8 h-8 text-[#5138ed] mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
              <p class="text-xs font-bold text-slate-500 mt-3">Loading comprehensive student records...</p>
            </div>

            <!-- Tab 1: Profile & Bio -->
            <template v-else-if="detailActiveTab === 'profile'">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Academic Placement -->
                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-3.5">
                  <h4 class="text-xs font-extrabold uppercase text-slate-400 tracking-wider">Academic Placement</h4>
                  <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Department</span>
                      <span class="font-bold text-slate-800">{{ studentDetail?.department?.name || department.name }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">College</span>
                      <span class="font-bold text-slate-800">{{ studentDetail?.department?.college || department.college }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Cohort / Year Level</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.year_level || '1st Year' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Section</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.section || '—' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Semester</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.semester || '—' }}</span>
                    </div>
                    <div class="flex justify-between py-1">
                      <span class="text-slate-500 font-medium">Academic Year</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.academic_year || '2026' }}</span>
                    </div>
                  </div>
                </div>

                <!-- Personal Contact -->
                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-3.5">
                  <h4 class="text-xs font-extrabold uppercase text-slate-400 tracking-wider">Identity & Contact</h4>
                  <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Full Legal Name</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.name }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Student ID</span>
                      <span class="font-mono font-bold text-[#5138ed]">{{ selectedStudent?.id_no || `WU-${selectedStudent?.id}` }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Official Email</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.email }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Phone Number</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.phone || 'Not recorded' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60">
                      <span class="text-slate-500 font-medium">Gender</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.gender || '—' }}</span>
                    </div>
                    <div class="flex justify-between py-1">
                      <span class="text-slate-500 font-medium">Date of Birth</span>
                      <span class="font-bold text-slate-800">{{ selectedStudent?.date_of_birth || '—' }}</span>
                    </div>
                  </div>
                </div>

              </div>
            </template>

            <!-- Tab 2: Curriculum Courses -->
            <template v-else-if="detailActiveTab === 'courses'">
              <div v-if="!studentDetail?.enrolled_courses || studentDetail.enrolled_courses.length === 0" class="py-12 text-center">
                <svg class="w-10 h-10 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <p class="text-xs font-bold text-slate-500 mt-2">No courses configured for {{ selectedStudent?.year_level }} yet.</p>
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="crs in studentDetail.enrolled_courses"
                  :key="crs.id"
                  class="p-4 rounded-2xl bg-white border border-slate-100 shadow-2xs hover:border-indigo-200 transition-colors flex items-center justify-between gap-4"
                >
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] font-bold text-xs flex items-center justify-center shrink-0">
                      {{ crs.code || 'CRS' }}
                    </div>
                    <div>
                      <h5 class="text-xs sm:text-sm font-bold text-slate-900">{{ crs.title }}</h5>
                      <p class="text-[11px] text-slate-400 mt-0.5">
                        {{ crs.credits }} Credits • Instructor: <span class="text-slate-700 font-semibold">{{ crs.instructor?.name || 'Unassigned' }}</span>
                      </p>
                    </div>
                  </div>
                  <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 text-slate-600">
                    {{ crs.level || 'General' }}
                  </span>
                </div>
              </div>
            </template>

            <!-- Tab 3: Examination & Assessment Records (REAL DATA) -->
            <template v-else-if="detailActiveTab === 'assessments'">

              <!-- Performance Summary Cards -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-indigo-50/60 rounded-2xl p-3.5 border border-indigo-100 text-center">
                  <span class="text-[10px] font-extrabold uppercase text-indigo-500 tracking-wider">Total Attempts</span>
                  <p class="text-xl font-black text-indigo-950 mt-1">{{ studentDetail?.performance?.total_attempts ?? 0 }}</p>
                </div>
                <div class="bg-emerald-50/60 rounded-2xl p-3.5 border border-emerald-100 text-center">
                  <span class="text-[10px] font-extrabold uppercase text-emerald-600 tracking-wider">Passed</span>
                  <p class="text-xl font-black text-emerald-700 mt-1">{{ studentDetail?.performance?.passed_attempts ?? 0 }}</p>
                </div>
                <div class="bg-sky-50/60 rounded-2xl p-3.5 border border-sky-100 text-center">
                  <span class="text-[10px] font-extrabold uppercase text-sky-600 tracking-wider">Avg Score %</span>
                  <p class="text-xl font-black text-sky-950 mt-1">
                    {{ studentDetail?.performance?.average_percentage !== null ? `${studentDetail?.performance?.average_percentage}%` : 'N/A' }}
                  </p>
                </div>
                <div class="bg-amber-50/60 rounded-2xl p-3.5 border border-amber-100 text-center">
                  <span class="text-[10px] font-extrabold uppercase text-amber-600 tracking-wider">Best Score %</span>
                  <p class="text-xl font-black text-amber-800 mt-1">
                    {{ studentDetail?.performance?.highest_percentage !== null ? `${studentDetail?.performance?.highest_percentage}%` : 'N/A' }}
                  </p>
                </div>
              </div>

              <!-- List of Attempts -->
              <div v-if="!studentDetail?.exam_attempts || studentDetail.exam_attempts.length === 0" class="py-12 text-center">
                <svg class="w-10 h-10 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-xs font-bold text-slate-500 mt-2">No examination attempts on record for this student.</p>
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="att in studentDetail.exam_attempts"
                  :key="att.id"
                  class="p-4 rounded-2xl bg-white border border-slate-100 shadow-2xs hover:border-indigo-200 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                >
                  <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                      <h5 class="text-xs sm:text-sm font-bold text-slate-900">
                        {{ att.exam?.title || 'Examination Assessment' }}
                      </h5>
                      <span class="font-mono text-[10px] font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                        {{ att.exam?.course_code || 'EXAM' }}
                      </span>
                    </div>
                    <p class="text-[11px] text-slate-400">
                      Submitted: {{ att.submitted_at ? new Date(att.submitted_at).toLocaleString() : (att.started_at ? new Date(att.started_at).toLocaleString() : '—') }}
                    </p>
                  </div>

                  <div class="flex items-center gap-4 self-end sm:self-auto">
                    <!-- Score and Grade -->
                    <div class="text-right">
                      <p class="text-xs font-bold text-slate-900">
                        {{ att.score !== null ? `${att.score} / ${att.total_marks}` : 'Score pending' }}
                      </p>
                      <p class="text-[11px] font-extrabold text-[#5138ed]">
                        {{ att.percentage !== null ? `${att.percentage}%` : '' }}
                      </p>
                    </div>

                    <!-- Grade Badge -->
                    <span
                      v-if="att.grade"
                      :class="[
                        ['A+', 'A', 'A-', 'Pass'].includes(att.grade) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200',
                        'px-2.5 py-1 rounded-lg text-xs font-black border min-w-[36px] text-center'
                      ]"
                    >
                      {{ att.grade }}
                    </span>

                    <!-- Attempt Status -->
                    <span
                      :class="[
                        att.status === 'published' ? 'bg-emerald-50 text-emerald-700' : (att.status === 'submitted' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600'),
                        'text-[10px] font-bold px-2 py-0.5 rounded-md uppercase'
                      ]"
                    >
                      {{ att.status }}
                    </span>
                  </div>
                </div>
              </div>

            </template>

          </div>

          <!-- Modal Footer Actions -->
          <div class="p-4 px-6 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <button
                @click="showDetailModal = false; openEditView(selectedStudent)"
                class="px-3.5 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors shadow-2xs cursor-pointer"
              >
                Edit Profile
              </button>
              <button
                @click="showDetailModal = false; openStatusModal(selectedStudent)"
                class="px-3.5 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors shadow-2xs cursor-pointer"
              >
                Toggle Status
              </button>
            </div>
            <button
              @click="showDetailModal = false"
              class="px-5 py-2 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 rounded-xl transition-colors cursor-pointer"
            >
              Close
            </button>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: QUICK STATUS TOGGLE (ACTIVE / INACTIVE)                         -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showStatusModal && selectedStudent"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
      >
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6 text-center animate-in fade-in zoom-in-95 duration-150 space-y-4">
          <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-[#5138ed] mx-auto flex items-center justify-center">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">
              {{ selectedStudent.status === 'active' ? 'Deactivate Student Account?' : 'Activate Student Account?' }}
            </h3>
            <p class="text-xs text-slate-500 mt-1">
              {{ selectedStudent.status === 'active'
                ? `Deactivating ${selectedStudent.name} will suspend portal login and prevent taking examinations.`
                : `Activating ${selectedStudent.name} will restore examination access and portal privileges.`
              }}
            </p>
          </div>
          <div class="flex gap-3 pt-2">
            <button
              @click="showStatusModal = false"
              class="flex-1 py-2.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors"
            >
              Cancel
            </button>
            <button
              @click="toggleStudentStatus"
              :disabled="isSaving"
              :class="[
                selectedStudent.status === 'active' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700',
                'flex-1 py-2.5 text-xs font-bold text-white rounded-xl transition-colors disabled:opacity-60 cursor-pointer'
              ]"
            >
              {{ isSaving ? 'Updating...' : (selectedStudent.status === 'active' ? 'Deactivate' : 'Activate') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL: DELETE / DEACTIVATE CONFIRMATION (WITH INTEGRITY GUARD)         -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showDeleteModal && selectedStudent"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
      >
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-6 text-center animate-in fade-in zoom-in-95 duration-150 space-y-4">

          <!-- Notice if student has exam attempt records -->
          <template v-if="(selectedStudent.exam_attempts_count ?? 0) > 0">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 mx-auto flex items-center justify-center">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900">Academic Integrity Guard</h3>
              <p class="text-xs text-slate-500 mt-2 text-left leading-relaxed">
                Student <strong class="text-slate-800">{{ selectedStudent.name }}</strong> has
                <strong class="text-[#5138ed]">{{ selectedStudent.exam_attempts_count }} examination attempt record(s)</strong>
                in Wollo University's examination system.
              </p>
              <div class="p-3 bg-amber-50 border border-amber-200/60 rounded-xl text-left mt-3">
                <p class="text-[11px] font-semibold text-amber-800">
                  To protect historical grades, GPA calculation, and audit integrity, permanent record deletion is restricted. Deactivating the student will disable login and exam access while safely archiving their academic results.
                </p>
              </div>
            </div>
            <div class="flex gap-3 pt-2">
              <button
                @click="showDeleteModal = false"
                class="flex-1 py-2.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors"
              >
                Cancel
              </button>
              <button
                @click="deactivateInsteadOfDelete"
                :disabled="isSaving"
                class="flex-1 py-2.5 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl transition-colors disabled:opacity-60 cursor-pointer"
              >
                {{ isSaving ? 'Deactivating...' : 'Deactivate Instead' }}
              </button>
            </div>
          </template>

          <!-- Safe deletion if 0 exam records -->
          <template v-else>
            <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900">Remove Student Record?</h3>
              <p class="text-xs text-slate-500 mt-1">
                Are you sure you want to permanently remove <strong class="text-slate-800">{{ selectedStudent.name }}</strong> ({{ selectedStudent.id_no || selectedStudent.email }})? This student has no examination records on file.
              </p>
            </div>
            <div class="flex gap-3 pt-2">
              <button
                @click="showDeleteModal = false"
                class="flex-1 py-2.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors"
              >
                Cancel
              </button>
              <button
                @click="executeDelete(false)"
                :disabled="isSaving"
                class="flex-1 py-2.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors disabled:opacity-60 cursor-pointer"
              >
                {{ isSaving ? 'Removing...' : 'Permanently Remove' }}
              </button>
            </div>
          </template>

        </div>
      </div>
    </Teleport>

  </div>
</template>
