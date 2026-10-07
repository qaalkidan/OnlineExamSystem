<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'

const router = useRouter()

// ── Search & Filter State ───────────────────────────────────────────────────
const search = ref('')
const debouncedSearch = ref('')
let searchTimeout: any = null

watch(search, (newVal) => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    debouncedSearch.value = newVal.trim()
    currentPage.value = 1
  }, 250)
})

const statusFilter = ref('all')
const headFilter = ref('all')
const collegeFilter = ref('all')
const sortBy = ref('name')
const currentPage = ref(1)
const perPage = ref(10)

const isLoading = ref(false)
const isExporting = ref(false)
const showExportDropdown = ref(false)
const activeActionDropdown = ref<number | null>(null)

// ── Views & Modals State ────────────────────────────────────────────────────
const showAddForm = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const showStatusConfirmModal = ref(false)
const showListAssignHeadModal = ref(false)

const showDetailView = ref(false)
const viewingDept = ref<any>(null)
const detailActiveTab = ref('info')
const detailData = ref<any>(null)
const isDetailLoading = ref(false)

const selectedDept = ref<any>(null)
const targetStatus = ref<'active' | 'inactive'>('active')
const isTogglingStatus = ref(false)

// ── Form State ──────────────────────────────────────────────────────────────
const editData = ref({
  id: null as number | null,
  name: '',
  code: '',
  established: '',
  college: '',
  status: 'active'
})

const newDeptForm = ref({
  name: '',
  code: '',
  college: '',
  established: '',
  status: 'active'
})

const deptFormErrors = ref<Record<string, string>>({})
const deptFormTouched = ref<Record<string, boolean>>({})

// ── Assign Head State ───────────────────────────────────────────────────────
const assignHeadTarget = ref<any>(null)
const assignHeadSearch = ref('')
const availableInstructors = ref<any[]>([])
const isLoadingInstructors = ref(false)
const isAssigningHead = ref(false)
const assignHeadStatus = ref<{ type: 'success' | 'error' | null; message: string }>({ type: null, message: '' })

// ── Backend Data ────────────────────────────────────────────────────────────
const allDepts = ref<any[]>([])
const backendColleges = ref<string[]>([])
const backendStats = ref({
  total: 0,
  active: 0,
  inactive: 0,
  students: 0,
  instructors: 0,
  courses: 0,
  exams: 0,
  new_this_year: 0
})

// ── Toast Notification State ────────────────────────────────────────────────
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'info'
})
let toastTimer: ReturnType<typeof setTimeout> | null = null

const showToast = (message: string, type: 'success' | 'error' | 'info' = 'info') => {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 3500)
}

// ── Detail Tabs Configuration ───────────────────────────────────────────────
const detailTabs = [
  { key: 'info', label: 'Department Overview', icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'head', label: 'Department Head & Leadership', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { key: 'instructors', label: 'Instructors & Faculty', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { key: 'courses', label: 'Academic Courses', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { key: 'students', label: 'Enrolled Students', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { key: 'exams', label: 'Department Exams', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
  { key: 'settings', label: 'Settings & Status', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
  { key: 'activity', label: 'Activity Audit Log', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
]

// ── Dropdown Filters (Strictly Real Data) ────────────────────────────────────
const allHeads = computed(() => {
  const set = new Set(allDepts.value.map(d => d.head).filter(h => h && h !== 'N/A' && h !== 'Not Assigned'))
  return Array.from(set).sort()
})

const allColleges = computed(() => {
  const fromDepts = allDepts.value.map(d => d.college).filter(Boolean)
  const set = new Set([...fromDepts, ...backendColleges.value])
  return Array.from(set).sort()
})

// ── Filtering & Sorting Logic ───────────────────────────────────────────────
const filtered = computed(() => {
  return allDepts.value.filter(d => {
    // 1. Debounced Search
    if (debouncedSearch.value) {
      const q = debouncedSearch.value.toLowerCase()
      const matchName = (d.name || '').toLowerCase().includes(q)
      const matchHead = (d.head || '').toLowerCase().includes(q)
      const matchCode = (d.code || '').toLowerCase().includes(q)
      const matchCollege = (d.college || d.subLabel || '').toLowerCase().includes(q)
      if (!matchName && !matchHead && !matchCode && !matchCollege) return false
    }

    // 2. Status Filter
    if (statusFilter.value !== 'all' && d.status !== statusFilter.value) {
      return false
    }

    // 3. Department Head Filter
    if (headFilter.value !== 'all' && d.head !== headFilter.value) {
      return false
    }

    // 4. College / School Filter
    if (collegeFilter.value !== 'all' && d.college !== collegeFilter.value) {
      return false
    }

    return true
  }).sort((a: any, b: any) => {
    if (sortBy.value === 'name') return (a.name || '').localeCompare(b.name || '')
    if (sortBy.value === 'students') return (b.students || 0) - (a.students || 0)
    if (sortBy.value === 'instructors') return (b.instructors || 0) - (a.instructors || 0)
    if (sortBy.value === 'courses') return (b.courses || 0) - (a.courses || 0)
    if (sortBy.value === 'code') return (a.code || '').localeCompare(b.code || '')
    return 0
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filtered.value.slice(start, start + perPage.value)
})

// ── Real Statistics for KPI Cards ───────────────────────────────────────────
const stats = computed(() => {
  return {
    total: backendStats.value.total || allDepts.value.length,
    active: backendStats.value.active || allDepts.value.filter(d => d.status === 'active').length,
    inactive: backendStats.value.inactive || allDepts.value.filter(d => d.status === 'inactive').length,
    students: backendStats.value.students || allDepts.value.reduce((a, d) => a + (d.students || 0), 0),
    instructors: backendStats.value.instructors || allDepts.value.reduce((a, d) => a + (d.instructors || 0), 0),
    courses: backendStats.value.courses || allDepts.value.reduce((a, d) => a + (d.courses || 0), 0),
    exams: backendStats.value.exams || allDepts.value.reduce((a, d) => a + (d.exams || 0), 0),
  }
})

// ── Active Filter Chips ─────────────────────────────────────────────────────
const activeFilterChips = computed(() => {
  const chips: Array<{ id: string; label: string; clear: () => void }> = []
  if (debouncedSearch.value) {
    chips.push({ id: 'search', label: `Search: "${debouncedSearch.value}"`, clear: () => { search.value = ''; debouncedSearch.value = '' } })
  }
  if (statusFilter.value !== 'all') {
    chips.push({ id: 'status', label: `Status: ${statusFilter.value}`, clear: () => { statusFilter.value = 'all' } })
  }
  if (headFilter.value !== 'all') {
    chips.push({ id: 'head', label: `Head: ${headFilter.value}`, clear: () => { headFilter.value = 'all' } })
  }
  if (collegeFilter.value !== 'all') {
    chips.push({ id: 'college', label: `College: ${collegeFilter.value}`, clear: () => { collegeFilter.value = 'all' } })
  }
  return chips
})

const clearAllFilters = () => {
  search.value = ''
  debouncedSearch.value = ''
  statusFilter.value = 'all'
  headFilter.value = 'all'
  collegeFilter.value = 'all'
  sortBy.value = 'name'
  currentPage.value = 1
}

// ── Interactive KPI Card Action ─────────────────────────────────────────────
const selectKpiStatus = (status: string) => {
  if (statusFilter.value === status) {
    statusFilter.value = 'all'
  } else {
    statusFilter.value = status
  }
  currentPage.value = 1
}

// ── Dropdown Control ────────────────────────────────────────────────────────
const toggleActionDropdown = (id: number) => {
  activeActionDropdown.value = activeActionDropdown.value === id ? null : id
}

const handleActionClick = (action: () => void) => {
  activeActionDropdown.value = null
  action()
}

const closeDropdowns = () => {
  activeActionDropdown.value = null
  showExportDropdown.value = false
}

// ── Backend API Calls ───────────────────────────────────────────────────────
const fetchDepartments = async (silent = false) => {
  if (!silent) isLoading.value = true
  try {
    const res = await apiClient.get('/admin/departments')
    allDepts.value = (res.data.data || []).map((d: any) => ({
      id: d.id,
      name: d.name,
      subLabel: d.college || 'General College',
      head: d.head?.name || 'Not Assigned',
      headEmail: d.head?.email || '',
      headPhone: d.head?.phone || '',
      code: d.code,
      college: d.college || '',
      faculty: d.college || 'N/A',
      instructors: d.instructors_count || 0,
      students: d.students_count || 0,
      courses: d.courses_count || 0,
      exams: d.exams_count || 0,
      status: d.status || 'active',
      established: d.established || 'N/A',
      created_at: d.created_at,
    }))
    if (res.data.stats) {
      backendStats.value = res.data.stats
    }
    if (res.data.colleges) {
      backendColleges.value = res.data.colleges
    }
  } catch (err) {
    console.error('Failed to fetch departments:', err)
    showToast('Failed to load department records.', 'error')
  } finally {
    isLoading.value = false
  }
}

// ── View Details Action ─────────────────────────────────────────────────────
const viewDeptDetail = async (dept: any) => {
  viewingDept.value = dept
  detailActiveTab.value = 'info'
  showDetailView.value = true
  showAddForm.value = false
  isDetailLoading.value = true
  try {
    const res = await apiClient.get(`/admin/departments/${dept.id}`)
    detailData.value = res.data.data
    if (detailData.value?.department) {
      viewingDept.value = {
        ...viewingDept.value,
        ...detailData.value.department,
        head: detailData.value.department.head?.name || 'Not Assigned',
        headEmail: detailData.value.department.head?.email || '',
        headPhone: detailData.value.department.head?.phone || '',
        instructors: detailData.value.instructors?.length ?? viewingDept.value.instructors,
        students: detailData.value.students?.length ?? viewingDept.value.students,
        courses: detailData.value.courses?.length ?? viewingDept.value.courses,
        exams: detailData.value.exams?.length ?? viewingDept.value.exams,
      }
    }
  } catch (err) {
    console.error('Failed to fetch department details:', err)
    showToast('Could not load detailed department information.', 'error')
  } finally {
    isDetailLoading.value = false
  }
}

const backToDepartments = () => {
  showDetailView.value = false
  viewingDept.value = null
  detailData.value = null
}

// ── Relationship Navigation ─────────────────────────────────────────────────
const navigateToModule = (route: string, deptId: number, deptName: string) => {
  if (route === 'students') {
    router.push({ path: '/admin/students', query: { department_id: String(deptId), department: deptName } })
  } else if (route === 'instructors') {
    router.push({ path: '/admin/instructors', query: { department_id: String(deptId), department: deptName } })
  } else if (route === 'courses') {
    router.push({ path: '/admin/courses', query: { department_id: String(deptId), department: deptName } })
  } else if (route === 'exams') {
    router.push({ path: '/admin/exams', query: { department: deptName } })
  }
}

// ── Form Validation & Actions ───────────────────────────────────────────────
const touchDeptField = (field: string) => {
  deptFormTouched.value[field] = true
  validateDeptField(field)
}

const validateDeptField = (field: string): boolean => {
  delete deptFormErrors.value[field]
  delete deptFormErrors.value._server

  if (field === 'name') {
    const val = (newDeptForm.value.name || '').trim()
    if (!val) {
      deptFormErrors.value.name = 'Department Name is required.'
    } else if (val.length < 2) {
      deptFormErrors.value.name = 'Department Name must be at least 2 characters.'
    } else {
      const exists = allDepts.value.some(d => d.name && d.name.trim().toLowerCase() === val.toLowerCase())
      if (exists) {
        deptFormErrors.value.name = 'A department with this name already exists.'
      }
    }
  }

  if (field === 'code') {
    const val = (newDeptForm.value.code || '').trim()
    if (!val) {
      deptFormErrors.value.code = 'Department Code is required.'
    } else if (val.length < 2) {
      deptFormErrors.value.code = 'Department Code must be at least 2 characters.'
    } else if (val.length > 20) {
      deptFormErrors.value.code = 'Department Code must not exceed 20 characters.'
    } else {
      const exists = allDepts.value.some(d => d.code && d.code.trim().toUpperCase() === val.toUpperCase())
      if (exists) {
        deptFormErrors.value.code = 'A department with this code already exists.'
      }
    }
  }

  if (field === 'college') {
    const val = (newDeptForm.value.college || '').trim()
    if (!val) {
      deptFormErrors.value.college = 'College/School is required.'
    }
  }

  return !deptFormErrors.value[field]
}

const validateAddDeptForm = (): boolean => {
  deptFormTouched.value = { name: true, code: true, college: true }
  const isNameValid = validateDeptField('name')
  const isCodeValid = validateDeptField('code')
  const isCollegeValid = validateDeptField('college')
  return isNameValid && isCodeValid && isCollegeValid
}

const resetAddForm = () => {
  newDeptForm.value = { name: '', code: '', college: '', established: '', status: 'active' }
  deptFormErrors.value = {}
  deptFormTouched.value = {}
}

const saveDepartment = async () => {
  delete deptFormErrors.value._server
  if (!validateAddDeptForm()) return
  isLoading.value = true
  try {
    await apiClient.post('/admin/departments', {
      name: newDeptForm.value.name.trim(),
      code: newDeptForm.value.code.trim().toUpperCase(),
      college: newDeptForm.value.college.trim(),
      established: newDeptForm.value.established ? newDeptForm.value.established.trim() : null,
      status: newDeptForm.value.status,
    })
    await fetchDepartments(true)
    const createdName = newDeptForm.value.name
    showAddForm.value = false
    resetAddForm()
    showToast(`Department "${createdName}" created successfully!`, 'success')
  } catch (err: any) {
    console.error('Error creating department:', err)
    if (err.response?.status === 422 && err.response?.data?.errors) {
      const backendErrors = err.response.data.errors
      Object.keys(backendErrors).forEach(key => {
        deptFormTouched.value[key] = true
        deptFormErrors.value[key] = Array.isArray(backendErrors[key]) ? backendErrors[key][0] : backendErrors[key]
      })
    } else {
      deptFormErrors.value._server = err.response?.data?.message || 'Failed to create department.'
    }
  } finally {
    isLoading.value = false
  }
}

// ── Edit Department ─────────────────────────────────────────────────────────
const openEditModal = (d: any) => {
  editData.value = {
    id: d.id,
    name: d.name,
    code: d.code,
    established: d.established === 'N/A' ? '' : d.established,
    college: d.college || '',
    status: d.status || 'active'
  }
  showEditModal.value = true
}

const updateDept = async () => {
  if (!editData.value.name || !editData.value.code) return
  isLoading.value = true
  try {
    await apiClient.put(`/admin/departments/${editData.value.id}`, {
      name: editData.value.name.trim(),
      code: editData.value.code.trim().toUpperCase(),
      college: editData.value.college ? editData.value.college.trim() : null,
      established: editData.value.established ? editData.value.established.trim() : null,
      status: editData.value.status
    })
    await fetchDepartments(true)
    if (showDetailView.value && viewingDept.value?.id === editData.value.id) {
      await viewDeptDetail(editData.value)
    }
    showEditModal.value = false
    showToast(`Department "${editData.value.name}" updated successfully.`, 'success')
  } catch (err: any) {
    console.error('Error updating department:', err)
    const msg = err.response?.data?.message || 'Failed to update department.'
    showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

// ── Activate / Deactivate Toggle ────────────────────────────────────────────
const promptToggleStatus = (dept: any) => {
  selectedDept.value = dept
  targetStatus.value = dept.status === 'active' ? 'inactive' : 'active'
  showStatusConfirmModal.value = true
}

const executeToggleStatus = async () => {
  if (!selectedDept.value) return
  isTogglingStatus.value = true
  try {
    const res = await apiClient.post(`/admin/departments/${selectedDept.value.id}/toggle-status`, {
      status: targetStatus.value
    })
    showToast(res.data?.message || `Department marked as ${targetStatus.value} successfully.`, 'success')
    showStatusConfirmModal.value = false
    await fetchDepartments(true)
    if (showDetailView.value && viewingDept.value?.id === selectedDept.value.id) {
      await viewDeptDetail(selectedDept.value)
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Failed to change department status.'
    showToast(msg, 'error')
  } finally {
    isTogglingStatus.value = false
  }
}

// ── Delete Department ───────────────────────────────────────────────────────
const confirmDelete = (d: any) => {
  selectedDept.value = d
  showDeleteModal.value = true
}

const deleteDept = async () => {
  if (!selectedDept.value) return
  isLoading.value = true
  try {
    await apiClient.delete(`/admin/departments/${selectedDept.value.id}`)
    showToast(`Department "${selectedDept.value.name}" deleted successfully.`, 'success')
    await fetchDepartments(true)
    showDeleteModal.value = false
    if (showDetailView.value) {
      backToDepartments()
    }
  } catch (err: any) {
    console.error('Error deleting department:', err)
    const msg = err.response?.data?.message || 'Failed to delete department. Ensure no active students or courses are linked.'
    showToast(msg, 'error')
  } finally {
    isLoading.value = false
  }
}

// ── Assign Department Head ──────────────────────────────────────────────────
const openAssignHeadFromList = async (dept: any) => {
  assignHeadTarget.value = dept
  assignHeadSearch.value = ''
  assignHeadStatus.value = { type: null, message: '' }
  availableInstructors.value = []
  isLoadingInstructors.value = true
  showListAssignHeadModal.value = true
  try {
    const res = await apiClient.get(`/admin/instructors?department_id=${dept.id}`)
    availableInstructors.value = res.data.data || []
  } catch (err) {
    console.error('Failed to fetch instructors:', err)
    availableInstructors.value = []
  } finally {
    isLoadingInstructors.value = false
  }
}

const filteredInstructorsForAssign = computed(() => {
  if (!assignHeadSearch.value) return availableInstructors.value
  const q = assignHeadSearch.value.toLowerCase()
  return availableInstructors.value.filter((i: any) =>
    (i.name || '').toLowerCase().includes(q) || (i.email || '').toLowerCase().includes(q)
  )
})

const doAssignHead = async (instructor: any) => {
  if (!assignHeadTarget.value) return
  isAssigningHead.value = true
  assignHeadStatus.value = { type: null, message: '' }
  try {
    await apiClient.post(`/admin/departments/${assignHeadTarget.value.id}/assign-head`, {
      instructor_id: instructor.id
    })
    assignHeadStatus.value = { type: 'success', message: `${instructor.name} appointed as Department Head successfully!` }
    showToast(`${instructor.name} assigned as Head of ${assignHeadTarget.value.name}.`, 'success')
    await fetchDepartments(true)
    if (showDetailView.value && viewingDept.value?.id === assignHeadTarget.value.id) {
      await viewDeptDetail(viewingDept.value)
    }
    setTimeout(() => {
      showListAssignHeadModal.value = false
    }, 1200)
  } catch (err: any) {
    assignHeadStatus.value = {
      type: 'error',
      message: err.response?.data?.message || 'Failed to assign department head.'
    }
  } finally {
    isAssigningHead.value = false
  }
}

// ── Export Handling (CSV / PDF) ─────────────────────────────────────────────
const handleExport = async (format: 'csv' | 'pdf') => {
  showExportDropdown.value = false
  isExporting.value = true
  try {
    const token = localStorage.getItem('auth_token')
    const params = new URLSearchParams()
    params.set('format', format)
    if (statusFilter.value !== 'all') params.set('status', statusFilter.value)
    if (collegeFilter.value !== 'all') params.set('college', collegeFilter.value)
    if (debouncedSearch.value) params.set('search', debouncedSearch.value)

    const res = await fetch(`http://localhost:8000/api/v1/admin/departments-export?${params.toString()}`, {
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
      type: format === 'pdf' ? 'text/html' : 'text/csv',
    })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = data.filename || `wollo_departments_${new Date().toISOString().slice(0, 10)}.${format === 'pdf' ? 'html' : 'csv'}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)

    showToast(`Departments exported successfully as ${format.toUpperCase()}.`, 'success')
  } catch (err) {
    showToast('Failed to export departments report.', 'error')
  } finally {
    isExporting.value = false
  }
}

onMounted(() => {
  fetchDepartments()
  document.addEventListener('click', closeDropdowns)
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdowns)
})
</script>

<template>
  <div class="space-y-6 min-w-0 w-full font-sans pb-12">

    <!-- ────────────────────────────────────────────────────────────────────────
         VIEW 1: DEPARTMENT LIST (MAIN ERP TABLE VIEW)
    ──────────────────────────────────────────────────────────────────────── -->
    <div v-if="!showAddForm && !showDetailView" class="space-y-6 min-w-0 w-full animate-in fade-in duration-200">

      <!-- Institutional Header & Actions -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span class="hover:text-indigo-600 cursor-pointer">Admin Portal</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="hover:text-indigo-600 cursor-pointer">Academic</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-700 font-bold">Departments</span>
          </div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">University Department Management Center</h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-[#4338ca] border border-indigo-100">
              {{ stats.total }} Departments Registered
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Manage academic departments, college affiliations, appointed leadership, students, and courses.</p>
        </div>

        <!-- Header Actions: Refresh, Export, Add Department -->
        <div class="flex items-center gap-2.5">
          <!-- Refresh -->
          <button
            @click="fetchDepartments(false)"
            :disabled="isLoading"
            class="p-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition-colors shadow-sm disabled:opacity-50"
            title="Refresh Data"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>

          <!-- Export Dropdown -->
          <div class="relative">
            <button
              @click.stop="showExportDropdown = !showExportDropdown"
              class="flex items-center gap-2 px-3.5 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-sm"
            >
              <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <span>Export</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7 7" /></svg>
            </button>

            <div
              v-if="showExportDropdown"
              @click.stop
              class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-40 animate-in fade-in duration-100"
            >
              <button
                @click="handleExport('csv')"
                :disabled="isExporting"
                class="w-full text-left px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2 transition-colors"
              >
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <span>Export as CSV</span>
              </button>
              <button
                @click="handleExport('pdf')"
                :disabled="isExporting"
                class="w-full text-left px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2 transition-colors"
              >
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                <span>Export as PDF (Report)</span>
              </button>
            </div>
          </div>

          <!-- Add Department Button -->
          <button
            @click="showAddForm = true; resetAddForm()"
            class="flex items-center gap-2 px-4 py-2.5 bg-[#4338ca] text-white font-bold rounded-xl text-xs hover:bg-indigo-800 transition-colors shadow-sm shadow-indigo-200"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>+ Add Department</span>
          </button>
        </div>
      </div>

      <!-- Real Data Interactive KPI Cards (5 Cards) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
        <!-- 1. Total Departments -->
        <div
          @click="selectKpiStatus('all')"
          class="bg-white p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer shadow-sm group hover:border-indigo-300"
          :class="statusFilter === 'all' ? 'border-[#4338ca] ring-2 ring-indigo-100' : 'border-slate-100'"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Departments</span>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#4338ca] flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ stats.total }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
            <span>All academic units</span>
          </p>
        </div>

        <!-- 2. Active Departments -->
        <div
          @click="selectKpiStatus('active')"
          class="bg-white p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer shadow-sm group hover:border-emerald-300"
          :class="statusFilter === 'active' ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-100'"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Active Departments</span>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ stats.active }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span>Operational departments</span>
          </p>
        </div>

        <!-- 3. Inactive Departments -->
        <div
          @click="selectKpiStatus('inactive')"
          class="bg-white p-4 sm:p-5 rounded-2xl border transition-all cursor-pointer shadow-sm group hover:border-slate-300"
          :class="statusFilter === 'inactive' ? 'border-slate-500 ring-2 ring-slate-100' : 'border-slate-100'"
        >
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Inactive Departments</span>
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ stats.inactive }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
            <span>Dormant / Archived</span>
          </p>
        </div>

        <!-- 4. Total Students -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-100 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Students</span>
            <div class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ stats.students.toLocaleString() }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
            <span>Enrolled across departments</span>
          </p>
        </div>

        <!-- 5. Total Faculty -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-100 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Instructors</span>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ stats.instructors }}</h3>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
            <span>Instructors & Department Heads</span>
          </p>
        </div>
      </div>

      <!-- Main Layout: Search, Toolbar, Table, Pagination -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm flex flex-col min-w-0 overflow-hidden">

        <!-- Toolbar Section -->
        <div class="p-4 sm:p-5 border-b border-slate-100 space-y-3">
          <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
            <!-- Search Input -->
            <div class="relative flex-1 min-w-[220px]">
              <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
              <input
                v-model="search"
                type="text"
                placeholder="Search departments by name, code, faculty, or head..."
                class="w-full pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-[#4338ca] bg-slate-50/50 focus:bg-white transition-colors"
              />
            </div>

            <!-- College/School Filter -->
            <select
              v-model="collegeFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Colleges & Faculties</option>
              <option v-for="col in allColleges" :key="col" :value="col">{{ col }}</option>
            </select>

            <!-- Head of Department Filter -->
            <select
              v-model="headFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Department Heads</option>
              <option v-for="h in allHeads" :key="h" :value="h">{{ h }}</option>
            </select>

            <!-- Status Filter -->
            <select
              v-model="statusFilter"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>

            <!-- Sort By Filter -->
            <select
              v-model="sortBy"
              class="text-xs border border-slate-200 rounded-xl px-3 py-2 text-slate-600 bg-white focus:outline-none focus:border-[#4338ca]"
            >
              <option value="name">Sort by Name</option>
              <option value="students">Sort by Students</option>
              <option value="instructors">Sort by Instructors</option>
              <option value="courses">Sort by Courses</option>
              <option value="code">Sort by Code</option>
            </select>

            <!-- Reset Filters -->
            <button
              v-if="activeFilterChips.length > 0"
              @click="clearAllFilters"
              class="px-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors ml-auto sm:ml-0"
            >
              Clear Filters
            </button>
          </div>

          <!-- Active Filter Chips Bar -->
          <div v-if="activeFilterChips.length > 0" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Filtered By:</span>
            <div
              v-for="chip in activeFilterChips"
              :key="chip.id"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-lg text-xs font-semibold"
            >
              <span>{{ chip.label }}</span>
              <button @click="chip.clear" class="hover:text-indigo-900 font-bold ml-1">×</button>
            </div>
          </div>
        </div>

        <!-- Real Data Table -->
        <div class="overflow-x-auto min-w-0">
          <table class="w-full text-left whitespace-nowrap min-w-max">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/60">
                <th class="px-6 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Department & College</th>
                <th class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Code</th>
                <th class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Head of Department</th>
                <th class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Students</th>
                <th class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Instructors</th>
                <th class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Courses</th>
                <th class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Exams</th>
                <th class="px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Status</th>
                <th class="px-6 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(dept, index) in paginated"
                :key="dept.id"
                class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors group"
              >
                <!-- Department Name & College -->
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 text-[#4338ca] flex items-center justify-center font-bold text-xs shrink-0">
                      {{ (dept.code || dept.name || 'DP').slice(0, 2).toUpperCase() }}
                    </div>
                    <div>
                      <p
                        class="text-xs font-bold text-slate-800 leading-snug hover:text-indigo-600 cursor-pointer transition-colors"
                        @click="viewDeptDetail(dept)"
                      >
                        {{ dept.name }}
                      </p>
                      <p class="text-[11px] text-slate-400 mt-0.5">{{ dept.college || dept.subLabel || 'General College' }}</p>
                    </div>
                  </div>
                </td>

                <!-- Department Code -->
                <td class="px-4 py-4">
                  <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">
                    {{ dept.code }}
                  </span>
                </td>

                <!-- Head of Department -->
                <td class="px-4 py-4">
                  <div v-if="dept.head && dept.head !== 'Not Assigned' && dept.head !== 'N/A'" class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-indigo-100 text-[#4338ca] flex items-center justify-center text-[10px] font-bold shrink-0">
                      {{ dept.head.slice(0, 2).toUpperCase() }}
                    </div>
                    <div>
                      <p class="text-xs font-semibold text-slate-800 leading-tight">{{ dept.head }}</p>
                      <p class="text-[10px] text-slate-400 mt-0.5">{{ dept.headEmail || 'Leadership' }}</p>
                    </div>
                  </div>
                  <div v-else class="flex items-center gap-2">
                    <span class="text-xs text-slate-400 italic">Not Assigned</span>
                    <button
                      @click="openAssignHeadFromList(dept)"
                      class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
                    >
                      + Assign
                    </button>
                  </div>
                </td>

                <!-- Students Count -->
                <td class="px-4 py-4 text-center">
                  <button
                    @click="navigateToModule('students', dept.id, dept.name)"
                    class="text-xs font-bold text-slate-700 hover:text-indigo-600 transition-colors"
                    title="View Students in Department"
                  >
                    {{ dept.students }}
                  </button>
                </td>

                <!-- Instructors Count -->
                <td class="px-4 py-4 text-center">
                  <button
                    @click="navigateToModule('instructors', dept.id, dept.name)"
                    class="text-xs font-bold text-slate-700 hover:text-indigo-600 transition-colors"
                    title="View Instructors in Department"
                  >
                    {{ dept.instructors }}
                  </button>
                </td>

                <!-- Courses Count -->
                <td class="px-4 py-4 text-center">
                  <button
                    @click="navigateToModule('courses', dept.id, dept.name)"
                    class="text-xs font-bold text-slate-700 hover:text-indigo-600 transition-colors"
                    title="View Courses in Department"
                  >
                    {{ dept.courses }}
                  </button>
                </td>

                <!-- Exams Count -->
                <td class="px-4 py-4 text-center">
                  <button
                    @click="navigateToModule('exams', dept.id, dept.name)"
                    class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors"
                    title="View Exams in Department"
                  >
                    {{ dept.exams }}
                  </button>
                </td>

                <!-- Status Badge -->
                <td class="px-4 py-4 text-center">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-full capitalize border"
                    :class="dept.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full"
                      :class="dept.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"
                    ></span>
                    <span>{{ dept.status }}</span>
                  </span>
                </td>

                <!-- Actions Column (Three-Dot Menu ⋮) -->
                <td class="px-6 py-4 text-right whitespace-nowrap">
                  <div class="relative inline-block text-left">
                    <button
                      @click.stop="toggleActionDropdown(dept.id)"
                      type="button"
                      class="w-8 h-8 rounded-xl inline-flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-100 transition-all focus:outline-none"
                      :class="{ 'bg-indigo-50 text-indigo-700 border-indigo-200': activeActionDropdown === dept.id }"
                      title="Department Actions"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                      </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                      v-if="activeActionDropdown === dept.id"
                      @click.stop
                      class="absolute right-0 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-50 select-none animate-in fade-in zoom-in-95 duration-100"
                      :class="index >= paginated.length - 2 && paginated.length > 2 ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
                    >
                      <!-- View Details -->
                      <button
                        @click="handleActionClick(() => viewDeptDetail(dept))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>View Details</span>
                      </button>

                      <!-- Assign Department Head -->
                      <button
                        @click="handleActionClick(() => openAssignHeadFromList(dept))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-amber-50 hover:text-amber-800 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-amber-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span>Assign Head</span>
                      </button>

                      <!-- Edit Department -->
                      <button
                        @click="handleActionClick(() => openEditModal(dept))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span>Edit Department</span>
                      </button>

                      <!-- Activate / Deactivate Toggle -->
                      <button
                        @click="handleActionClick(() => promptToggleStatus(dept))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold flex items-center gap-2.5 transition-colors group/item"
                        :class="dept.status === 'active' ? 'text-slate-600 hover:bg-slate-50' : 'text-emerald-700 hover:bg-emerald-50'"
                      >
                        <svg class="w-4 h-4 text-slate-400 group-hover/item:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>{{ dept.status === 'active' ? 'Deactivate' : 'Activate' }}</span>
                      </button>

                      <div class="h-px bg-slate-100 my-1"></div>

                      <!-- Delete Department -->
                      <button
                        @click="handleActionClick(() => confirmDelete(dept))"
                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 flex items-center gap-2.5 transition-colors group/item"
                      >
                        <svg class="w-4 h-4 text-rose-400 group-hover/item:text-rose-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Delete Department</span>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="paginated.length === 0">
                <td :colspan="9" class="py-16 text-center">
                  <div class="max-w-xs mx-auto space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    </div>
                    <p class="text-sm font-bold text-slate-700">No Departments Found</p>
                    <p class="text-xs text-slate-400">Try adjusting your search criteria, clearing active filters, or creating a new department.</p>
                    <button
                      v-if="activeFilterChips.length > 0"
                      @click="clearAllFilters"
                      class="px-4 py-2 bg-indigo-50 text-indigo-700 font-bold text-xs rounded-xl hover:bg-indigo-100 transition-colors"
                    >
                      Clear All Filters
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/40">
          <div class="text-xs text-slate-500">
            Showing <span class="font-bold text-slate-700">{{ filtered.length > 0 ? (currentPage - 1) * perPage + 1 : 0 }}</span> to
            <span class="font-bold text-slate-700">{{ Math.min(currentPage * perPage, filtered.length) }}</span> of
            <span class="font-bold text-slate-700">{{ filtered.length }}</span> departments
          </div>

          <div class="flex items-center gap-1.5">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1"
              class="p-2 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>

            <button
              v-for="p in totalPages"
              :key="p"
              @click="currentPage = p"
              class="w-8 h-8 rounded-xl border text-xs font-bold transition-colors"
              :class="currentPage === p ? 'bg-[#4338ca] text-white border-[#4338ca]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
            >
              {{ p }}
            </button>

            <button
              @click="currentPage = Math.min(totalPages, currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="p-2 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- ────────────────────────────────────────────────────────────────────────
         VIEW 2: ADD DEPARTMENT FORM (MODERN ENTERPRISE FORM)
    ──────────────────────────────────────────────────────────────────────── -->
    <div v-if="showAddForm && !showDetailView" class="space-y-6 pb-12 min-w-0 w-full animate-in fade-in duration-200">
      
      <!-- Institutional Breadcrumbs & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span @click="showAddForm = false; resetAddForm()" class="hover:text-indigo-600 cursor-pointer">Departments</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-700 font-bold">Add Department</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Add Academic Department</h1>
          <p class="text-xs text-slate-500 mt-0.5">Register a new academic department in the university.</p>
        </div>

        <button
          @click="showAddForm = false; resetAddForm()"
          class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm self-start sm:self-auto"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          <span>Back to Departments</span>
        </button>
      </div>

      <!-- Department Information Section Form -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 sm:p-8 space-y-6">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#4338ca] flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-800">Department Specifications</h2>
            <p class="text-xs text-slate-400 mt-0.5">Provide foundational academic identity and affiliation</p>
          </div>
        </div>

        <div class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Department Name -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Department Name <span class="text-rose-500">*</span></label>
              <input
                v-model="newDeptForm.name"
                type="text"
                placeholder="e.g., Computer Science"
                class="w-full border rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:outline-none transition-colors"
                :class="deptFormErrors.name ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                @blur="touchDeptField('name')"
                @input="deptFormTouched.name && validateDeptField('name')"
              />
              <p v-if="deptFormErrors.name" class="mt-1 text-[11px] text-rose-500 font-medium">{{ deptFormErrors.name }}</p>
            </div>

            <!-- Department Code -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Department Code <span class="text-rose-500">*</span></label>
              <input
                v-model="newDeptForm.code"
                type="text"
                placeholder="e.g., CS"
                class="w-full border rounded-xl px-4 py-2.5 text-xs text-slate-800 font-mono uppercase focus:outline-none transition-colors"
                :class="deptFormErrors.code ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                @blur="touchDeptField('code')"
                @input="deptFormTouched.code && validateDeptField('code')"
              />
              <p v-if="deptFormErrors.code" class="mt-1 text-[11px] text-rose-500 font-medium">{{ deptFormErrors.code }}</p>
            </div>

            <!-- College/School Affiliation -->
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">College / Faculty <span class="text-rose-500">*</span></label>
              <input
                v-model="newDeptForm.college"
                type="text"
                placeholder="e.g., College of Computing and Informatics"
                class="w-full border rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:outline-none transition-colors"
                :class="deptFormErrors.college ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                @blur="touchDeptField('college')"
                @input="deptFormTouched.college && validateDeptField('college')"
              />
              <p v-if="deptFormErrors.college" class="mt-1 text-[11px] text-rose-500 font-medium">{{ deptFormErrors.college }}</p>
            </div>
          </div>

          <!-- Established Year & Status -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Established Year (Optional)</label>
              <input
                v-model="newDeptForm.established"
                type="text"
                placeholder="e.g., 2012"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#4338ca]"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1.5">Initial Status</label>
              <select
                v-model="newDeptForm.status"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 bg-white focus:outline-none focus:border-[#4338ca]"
              >
                <option value="active">Active (Operational)</option>
                <option value="inactive">Inactive (Dormant)</option>
              </select>
            </div>
          </div>

          <!-- Notice box -->
          <div class="p-3.5 bg-indigo-50/60 border border-indigo-100 rounded-xl text-xs text-indigo-700 flex items-center gap-2.5">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>Department Head can be assigned after creating the department using the assign action in the department list.</span>
          </div>

          <!-- Server Error Banner -->
          <div v-if="deptFormErrors._server" class="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <div class="flex-1 text-xs text-rose-800 font-medium leading-relaxed">{{ deptFormErrors._server }}</div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
          <button
            @click="showAddForm = false; resetAddForm()"
            class="px-5 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="saveDepartment"
            :disabled="isLoading"
            class="flex items-center gap-2 px-6 py-2.5 text-xs font-bold text-white bg-[#4338ca] hover:bg-indigo-800 rounded-xl shadow-sm transition-all disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ isLoading ? 'Saving...' : 'Save Department' }}</span>
          </button>
        </div>
      </div>

    </div>

    <!-- ────────────────────────────────────────────────────────────────────────
         VIEW 3: REAL DEPARTMENT DETAILS & RELATIONSHIPS VIEW
    ──────────────────────────────────────────────────────────────────────── -->
    <div v-if="showDetailView && viewingDept" class="space-y-6 pb-12 min-w-0 w-full animate-in fade-in duration-200">
      
      <!-- Institutional Breadcrumbs & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
            <span @click="backToDepartments" class="hover:text-indigo-600 cursor-pointer">Departments</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-700 font-bold">{{ viewingDept.name }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">{{ viewingDept.name }}</h1>
            <span
              class="text-xs font-bold px-2.5 py-0.5 rounded-full border capitalize"
              :class="viewingDept.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
            >
              {{ viewingDept.status }}
            </span>
            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-lg bg-indigo-50 text-[#4338ca] border border-indigo-100">
              {{ viewingDept.code }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            @click="openEditModal(viewingDept)"
            class="flex items-center gap-2 px-3.5 py-2.5 bg-indigo-50 border border-indigo-200 text-[#4338ca] font-bold rounded-xl text-xs hover:bg-indigo-100 transition-colors shadow-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            <span>Edit Department</span>
          </button>
          <button
            @click="backToDepartments"
            class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl text-xs hover:bg-slate-50 transition-colors shadow-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            <span>Back to List</span>
          </button>
        </div>
      </div>

      <!-- Top Summary Profile Card & Real Metrics -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-6">
        <div class="flex flex-col lg:flex-row items-start justify-between gap-6">
          <div class="flex items-start gap-4">
            <div class="w-16 h-16 rounded-2xl bg-indigo-50 border-2 border-indigo-100 text-[#4338ca] flex items-center justify-center font-black text-xl shrink-0">
              {{ (viewingDept.code || viewingDept.name || 'DP').slice(0, 2).toUpperCase() }}
            </div>
            <div>
              <h2 class="text-lg font-bold text-slate-800">{{ viewingDept.name }}</h2>
              <p class="text-xs font-semibold text-slate-500 mt-0.5">{{ viewingDept.college || viewingDept.subLabel }}</p>
              <div class="flex flex-wrap items-center gap-4 mt-2 text-xs text-slate-600">
                <span class="flex items-center gap-1.5">
                  <span class="text-slate-400">Head:</span>
                  <strong class="text-slate-800">{{ viewingDept.head }}</strong>
                </span>
                <span class="flex items-center gap-1.5">
                  <span class="text-slate-400">Established:</span>
                  <strong class="text-slate-800">{{ viewingDept.established !== 'N/A' ? viewingDept.established : 'N/A' }}</strong>
                </span>
              </div>
            </div>
          </div>

          <!-- 4 Real Relationship KPI Badges -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 w-full lg:w-auto shrink-0">
            <div
              @click="detailActiveTab = 'students'"
              class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-indigo-50/50 cursor-pointer transition-colors text-center"
            >
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Students</span>
              <p class="text-xl font-black text-slate-800 mt-0.5">{{ detailData?.students?.length ?? viewingDept.students }}</p>
              <span class="text-[10px] text-indigo-600 font-semibold">Enrolled</span>
            </div>

            <div
              @click="detailActiveTab = 'instructors'"
              class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-indigo-50/50 cursor-pointer transition-colors text-center"
            >
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Faculty</span>
              <p class="text-xl font-black text-slate-800 mt-0.5">{{ detailData?.instructors?.length ?? viewingDept.instructors }}</p>
              <span class="text-[10px] text-indigo-600 font-semibold">Instructors</span>
            </div>

            <div
              @click="detailActiveTab = 'courses'"
              class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-indigo-50/50 cursor-pointer transition-colors text-center"
            >
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Courses</span>
              <p class="text-xl font-black text-slate-800 mt-0.5">{{ detailData?.courses?.length ?? viewingDept.courses }}</p>
              <span class="text-[10px] text-indigo-600 font-semibold">Active</span>
            </div>

            <div
              @click="detailActiveTab = 'exams'"
              class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-indigo-50/50 cursor-pointer transition-colors text-center"
            >
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Exams</span>
              <p class="text-xl font-black text-slate-800 mt-0.5">{{ detailData?.exams?.length ?? viewingDept.exams }}</p>
              <span class="text-[10px] text-indigo-600 font-semibold">Scheduled</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabbed Navigation and Dynamic Views -->
      <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6 items-start">
        
        <!-- Sidebar Tabs -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-2.5 space-y-1">
          <button
            v-for="tab in detailTabs"
            :key="tab.key"
            @click="detailActiveTab = tab.key"
            class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-colors text-left"
            :class="detailActiveTab === tab.key ? 'bg-indigo-50 text-[#4338ca]' : 'text-slate-600 hover:bg-slate-50'"
          >
            <svg class="w-4 h-4 shrink-0" :class="detailActiveTab === tab.key ? 'text-[#4338ca]' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
            </svg>
            <span>{{ tab.label }}</span>
          </button>
        </div>

        <!-- Main Tab Content Area -->
        <div class="space-y-6">

          <!-- 1. Overview Tab -->
          <div v-if="detailActiveTab === 'info'" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-6">
            <h3 class="text-sm font-bold text-slate-800 pb-2 border-b border-slate-100">Academic Structure & Specifications</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
              <div class="p-3 bg-slate-50/60 rounded-xl">
                <span class="text-slate-400 block mb-0.5">Department Name</span>
                <strong class="text-slate-800 text-sm">{{ viewingDept.name }}</strong>
              </div>
              <div class="p-3 bg-slate-50/60 rounded-xl">
                <span class="text-slate-400 block mb-0.5">Department Code</span>
                <strong class="text-[#4338ca] font-mono text-sm">{{ viewingDept.code }}</strong>
              </div>
              <div class="p-3 bg-slate-50/60 rounded-xl">
                <span class="text-slate-400 block mb-0.5">College / School</span>
                <strong class="text-slate-800">{{ viewingDept.college || viewingDept.faculty }}</strong>
              </div>
              <div class="p-3 bg-slate-50/60 rounded-xl">
                <span class="text-slate-400 block mb-0.5">Established Year</span>
                <strong class="text-slate-800">{{ viewingDept.established !== 'N/A' ? viewingDept.established : 'N/A' }}</strong>
              </div>
              <div class="p-3 bg-slate-50/60 rounded-xl">
                <span class="text-slate-400 block mb-0.5">Head of Department</span>
                <strong class="text-slate-800">{{ detailData?.department?.head?.name || viewingDept.head }}</strong>
              </div>
              <div class="p-3 bg-slate-50/60 rounded-xl">
                <span class="text-slate-400 block mb-0.5">Status</span>
                <strong class="capitalize" :class="viewingDept.status === 'active' ? 'text-emerald-700' : 'text-slate-600'">{{ viewingDept.status }}</strong>
              </div>
            </div>
          </div>

          <!-- 2. Leadership & Department Head Tab -->
          <div v-if="detailActiveTab === 'head'" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-6">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h3 class="text-sm font-bold text-slate-800">Department Head Leadership</h3>
                <p class="text-xs text-slate-400 mt-0.5">Designated academic leader supervising this department</p>
              </div>
              <button
                @click="openAssignHeadFromList(viewingDept)"
                class="px-4 py-2 bg-indigo-50 border border-indigo-200 text-[#4338ca] text-xs font-bold rounded-xl hover:bg-indigo-100 transition-colors"
              >
                {{ detailData?.department?.head ? 'Change Department Head' : 'Appoint Department Head' }}
              </button>
            </div>

            <!-- Current Head Card -->
            <div v-if="detailData?.department?.head" class="p-5 border border-slate-100 rounded-xl bg-slate-50/50 flex items-center gap-4">
              <div class="w-14 h-14 rounded-2xl bg-indigo-100 text-[#4338ca] flex items-center justify-center font-bold text-lg shrink-0">
                {{ detailData.department.head.name.slice(0, 2).toUpperCase() }}
              </div>
              <div class="space-y-1">
                <h4 class="text-sm font-bold text-slate-800">{{ detailData.department.head.name }}</h4>
                <p class="text-xs text-slate-500">{{ detailData.department.head.email }}</p>
                <p v-if="detailData.department.head.phone" class="text-xs text-slate-400">Phone: {{ detailData.department.head.phone }}</p>
                <span class="inline-block mt-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                  Head of Department
                </span>
              </div>
            </div>

            <div v-else class="text-center py-10 border border-dashed border-slate-200 rounded-xl space-y-2">
              <div class="w-10 h-10 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
              </div>
              <p class="text-xs font-bold text-slate-700">No Department Head Appointed</p>
              <p class="text-[11px] text-slate-400 max-w-sm mx-auto">Appoint an instructor belonging to this department to assume the Department Head role.</p>
            </div>
          </div>

          <!-- 3. Instructors Tab -->
          <div v-if="detailActiveTab === 'instructors'" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h3 class="text-sm font-bold text-slate-800">Faculty & Instructors</h3>
                <p class="text-xs text-slate-400 mt-0.5">Faculty members assigned to {{ viewingDept.name }}</p>
              </div>
              <button
                @click="navigateToModule('instructors', viewingDept.id, viewingDept.name)"
                class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
              >
                Open in Instructor Center →
              </button>
            </div>

            <div v-if="!detailData?.instructors?.length" class="text-center py-12 text-xs text-slate-400">
              No faculty or instructors assigned to this department yet.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left whitespace-nowrap text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
                    <th class="py-2.5 px-3">Instructor</th>
                    <th class="py-2.5 px-3">Email</th>
                    <th class="py-2.5 px-3">Role</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="inst in detailData.instructors" :key="inst.id" class="border-b border-slate-50 hover:bg-slate-50/60">
                    <td class="py-3 px-3 font-semibold text-slate-800">{{ inst.name }}</td>
                    <td class="py-3 px-3 text-slate-500">{{ inst.email }}</td>
                    <td class="py-3 px-3 capitalize">
                      <span :class="inst.role === 'dept_head' ? 'font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded' : 'text-slate-600'">
                        {{ inst.role === 'dept_head' ? 'Department Head' : 'Instructor' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 4. Courses Tab -->
          <div v-if="detailActiveTab === 'courses'" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h3 class="text-sm font-bold text-slate-800">Academic Courses</h3>
                <p class="text-xs text-slate-400 mt-0.5">Course offerings registered under {{ viewingDept.name }}</p>
              </div>
              <button
                @click="navigateToModule('courses', viewingDept.id, viewingDept.name)"
                class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
              >
                Open in Course Center →
              </button>
            </div>

            <div v-if="!detailData?.courses?.length" class="text-center py-12 text-xs text-slate-400">
              No courses registered under this department yet.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left whitespace-nowrap text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
                    <th class="py-2.5 px-3">Code</th>
                    <th class="py-2.5 px-3">Course Title</th>
                    <th class="py-2.5 px-3 text-center">Credits</th>
                    <th class="py-2.5 px-3">Instructor</th>
                    <th class="py-2.5 px-3 text-center">Semester</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in detailData.courses" :key="c.id" class="border-b border-slate-50 hover:bg-slate-50/60">
                    <td class="py-3 px-3 font-mono font-bold text-indigo-600">{{ c.code }}</td>
                    <td class="py-3 px-3 font-semibold text-slate-800">{{ c.title }}</td>
                    <td class="py-3 px-3 text-center font-bold text-slate-700">{{ c.credits }} CR</td>
                    <td class="py-3 px-3 text-slate-600">{{ c.instructor?.name || 'Unassigned' }}</td>
                    <td class="py-3 px-3 text-center text-slate-500">{{ c.semester || 'Semester 1' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 5. Students Tab -->
          <div v-if="detailActiveTab === 'students'" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h3 class="text-sm font-bold text-slate-800">Enrolled Students</h3>
                <p class="text-xs text-slate-400 mt-0.5">Undergraduate and graduate students registered in {{ viewingDept.name }}</p>
              </div>
              <button
                @click="navigateToModule('students', viewingDept.id, viewingDept.name)"
                class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
              >
                Open in Student Center →
              </button>
            </div>

            <div v-if="!detailData?.students?.length" class="text-center py-12 text-xs text-slate-400">
              No students enrolled in this department yet.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left whitespace-nowrap text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
                    <th class="py-2.5 px-3">Student Name</th>
                    <th class="py-2.5 px-3">Email</th>
                    <th class="py-2.5 px-3">Joined Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="st in detailData.students" :key="st.id" class="border-b border-slate-50 hover:bg-slate-50/60">
                    <td class="py-3 px-3 font-semibold text-slate-800">{{ st.name }}</td>
                    <td class="py-3 px-3 text-slate-500">{{ st.email }}</td>
                    <td class="py-3 px-3 text-slate-400">{{ new Date(st.created_at).toLocaleDateString() }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 6. Exams Tab -->
          <div v-if="detailActiveTab === 'exams'" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h3 class="text-sm font-bold text-slate-800">Department Examinations</h3>
                <p class="text-xs text-slate-400 mt-0.5">Exams scheduled for courses under {{ viewingDept.name }}</p>
              </div>
              <button
                @click="navigateToModule('exams', viewingDept.id, viewingDept.name)"
                class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
              >
                Open in Exam Center →
              </button>
            </div>

            <div v-if="!detailData?.exams?.length" class="text-center py-12 text-xs text-slate-400">
              No examinations scheduled for this department yet.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left whitespace-nowrap text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-wider">
                    <th class="py-2.5 px-3">Exam Title</th>
                    <th class="py-2.5 px-3">Course</th>
                    <th class="py-2.5 px-3 text-center">Duration</th>
                    <th class="py-2.5 px-3 text-center">Marks</th>
                    <th class="py-2.5 px-3 text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ex in detailData.exams" :key="ex.id" class="border-b border-slate-50 hover:bg-slate-50/60">
                    <td class="py-3 px-3 font-semibold text-slate-800">{{ ex.title }}</td>
                    <td class="py-3 px-3 text-slate-600">{{ ex.course?.title || ex.course_code }}</td>
                    <td class="py-3 px-3 text-center">{{ ex.duration_minutes }}m</td>
                    <td class="py-3 px-3 text-center font-bold">{{ ex.total_marks }}</td>
                    <td class="py-3 px-3 text-center capitalize">
                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
                        {{ ex.status }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 7. Settings Tab -->
          <div v-if="detailActiveTab === 'settings'" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-6">
            <h3 class="text-sm font-bold text-slate-800 pb-2 border-b border-slate-100">Department Lifecycle & Status</h3>
            
            <div class="space-y-4">
              <div class="flex items-center justify-between p-4 border border-slate-100 rounded-xl bg-slate-50/40">
                <div>
                  <p class="text-xs font-bold text-slate-800">Operational Status</p>
                  <p class="text-[11px] text-slate-400 mt-0.5">Toggle department state between active and dormant</p>
                </div>
                <button
                  @click="promptToggleStatus(viewingDept)"
                  class="px-4 py-2 text-xs font-bold rounded-xl border transition-colors"
                  :class="viewingDept.status === 'active' ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'"
                >
                  {{ viewingDept.status === 'active' ? 'Deactivate Department' : 'Activate Department' }}
                </button>
              </div>

              <!-- Danger Zone -->
              <div class="p-4 border border-rose-100 rounded-xl bg-rose-50/30 flex items-center justify-between">
                <div>
                  <p class="text-xs font-bold text-rose-800">Danger Zone</p>
                  <p class="text-[11px] text-rose-500 mt-0.5">Permanently remove this department from the registry</p>
                </div>
                <button
                  @click="confirmDelete(viewingDept)"
                  class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors shadow-sm"
                >
                  Delete Department
                </button>
              </div>
            </div>
          </div>

          <!-- 8. Activity Log Tab -->
          <div v-if="detailActiveTab === 'activity'" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-4">
            <h3 class="text-sm font-bold text-slate-800 pb-2 border-b border-slate-100">Audit Trail & Administrative History</h3>
            
            <div v-if="!detailData?.activities?.length" class="text-center py-12 text-xs text-slate-400">
              No activity logs recorded for this department yet.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="log in detailData.activities"
                :key="log.id"
                class="p-3.5 border border-slate-100 rounded-xl bg-slate-50/40 flex items-start gap-3"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="flex-1 text-xs">
                  <p class="font-bold text-slate-800">{{ log.action }}</p>
                  <p class="text-slate-500 mt-0.5">{{ log.description || log.action }}</p>
                  <p class="text-[10px] text-slate-400 mt-1">Logged by {{ log.user?.name || 'Administrator' }} • {{ new Date(log.created_at).toLocaleString() }}</p>
                </div>
              </div>
            </div>
          </div>

        </div>

      </div>

    </div>

    <!-- ────────────────────────────────────────────────────────────────────────
         MODALS & DIALOGS
    ──────────────────────────────────────────────────────────────────────── -->

    <!-- 1. Edit Department Modal -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-base font-bold text-slate-800">Edit Department</h3>
              <p class="text-xs text-slate-400 mt-0.5">Update academic specifications and affiliation</p>
            </div>
            <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">×</button>
          </div>

          <div class="p-6 space-y-4 text-xs">
            <div>
              <label class="font-bold text-slate-700 block mb-1">Department Name *</label>
              <input
                v-model="editData.name"
                type="text"
                class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#4338ca]"
              />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="font-bold text-slate-700 block mb-1">Department Code *</label>
                <input
                  v-model="editData.code"
                  type="text"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-mono uppercase text-slate-800 focus:outline-none focus:border-[#4338ca]"
                />
              </div>
              <div>
                <label class="font-bold text-slate-700 block mb-1">Established Year</label>
                <input
                  v-model="editData.established"
                  type="text"
                  placeholder="e.g. 2012"
                  class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#4338ca]"
                />
              </div>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">College / School</label>
              <input
                v-model="editData.college"
                type="text"
                class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#4338ca]"
              />
            </div>

            <div class="flex items-center justify-between p-3.5 border border-slate-200 rounded-xl">
              <div>
                <label class="block text-xs font-bold text-slate-700">Status</label>
                <p class="text-[10px] text-slate-400">Set department active or inactive</p>
              </div>
              <button
                type="button"
                @click="editData.status = editData.status === 'active' ? 'inactive' : 'active'"
                class="px-3 py-1 rounded-full text-xs font-bold border transition-colors capitalize"
                :class="editData.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
              >
                {{ editData.status }}
              </button>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-6 border-t border-slate-100 bg-slate-50/50">
            <button
              @click="showEditModal = false"
              class="px-4 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="updateDept"
              :disabled="isLoading"
              class="px-5 py-2.5 text-xs font-bold text-white bg-[#4338ca] hover:bg-indigo-800 rounded-xl transition-colors shadow-sm disabled:opacity-50"
            >
              {{ isLoading ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 2. Assign Head Modal -->
    <Teleport to="body">
      <div v-if="showListAssignHeadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100 shrink-0">
            <div>
              <h3 class="text-sm font-bold text-slate-800">Appoint Department Head</h3>
              <p class="text-xs text-slate-400 mt-0.5">{{ assignHeadTarget?.name }} ({{ assignHeadTarget?.code }})</p>
            </div>
            <button @click="showListAssignHeadModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">×</button>
          </div>

          <div class="px-6 py-4 space-y-4 text-xs">
            <!-- Notification -->
            <div
              v-if="assignHeadStatus.type"
              class="p-3 text-xs font-medium border rounded-xl flex items-center gap-2"
              :class="assignHeadStatus.type === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
            >
              <span>{{ assignHeadStatus.message }}</span>
            </div>

            <!-- Loading Instructors -->
            <div v-if="isLoadingInstructors" class="py-12 flex flex-col items-center justify-center text-center">
              <div class="w-8 h-8 border-2 border-indigo-200 border-t-[#4338ca] rounded-full animate-spin mb-3"></div>
              <p class="text-xs font-semibold text-slate-700">Loading department instructors...</p>
            </div>

            <!-- Empty: No instructors in department -->
            <div v-else-if="availableInstructors.length === 0" class="py-6 px-2 text-center space-y-3">
              <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center mx-auto">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              </div>
              <p class="text-sm font-bold text-slate-800">No Instructors In This Department</p>
              <p class="text-xs text-slate-500 max-w-sm mx-auto">
                You cannot assign a Department Head until instructors are registered under this department.
              </p>
              <button
                @click="showListAssignHeadModal = false; router.push('/admin/instructors')"
                class="w-full py-2.5 bg-[#4338ca] text-white text-xs font-bold rounded-xl hover:bg-indigo-800 transition-colors shadow-sm"
              >
                Go to Instructor Management
              </button>
            </div>

            <!-- List of Instructors -->
            <template v-else>
              <div class="relative">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                <input
                  v-model="assignHeadSearch"
                  type="text"
                  placeholder="Filter instructors by name or email..."
                  class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-[#4338ca]"
                />
              </div>

              <div class="border border-slate-200 rounded-xl overflow-hidden max-h-60 overflow-y-auto">
                <div
                  v-for="inst in filteredInstructorsForAssign"
                  :key="inst.id"
                  @click="!isAssigningHead && doAssignHead(inst)"
                  class="flex items-center gap-3 p-3 hover:bg-indigo-50/70 cursor-pointer transition-colors border-b border-slate-50 last:border-b-0"
                  :class="{ 'bg-indigo-50/80': assignHeadTarget?.head === inst.name }"
                >
                  <div class="w-8 h-8 rounded-full bg-indigo-100 text-[#4338ca] flex items-center justify-center font-bold text-xs shrink-0">
                    {{ inst.name.slice(0, 2).toUpperCase() }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-800 truncate">{{ inst.name }}</p>
                    <p class="text-[11px] text-slate-400 truncate">{{ inst.email }}</p>
                  </div>
                  <span v-if="assignHeadTarget?.head === inst.name" class="text-[10px] font-bold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded-full shrink-0">
                    Current Head
                  </span>
                </div>
              </div>
            </template>
          </div>

          <div class="flex items-center justify-end px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <button
              @click="showListAssignHeadModal = false"
              class="px-4 py-2 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 3. Toggle Status Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showStatusConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div class="p-6 text-center space-y-3">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto" :class="targetStatus === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 capitalize">{{ targetStatus }} Department?</h3>
            <p class="text-xs text-slate-500">
              Are you sure you want to mark <span class="font-bold text-slate-800">{{ selectedDept?.name }}</span> as <strong class="capitalize">{{ targetStatus }}</strong>?
            </p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button
              @click="showStatusConfirmModal = false"
              class="flex-1 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="executeToggleStatus"
              :disabled="isTogglingStatus"
              class="flex-1 py-2.5 text-xs font-bold text-white rounded-xl transition-colors shadow-sm disabled:opacity-50"
              :class="targetStatus === 'active' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-600 hover:bg-amber-700'"
            >
              {{ isTogglingStatus ? 'Updating...' : `Confirm ${targetStatus === 'active' ? 'Activation' : 'Deactivation'}` }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 4. Delete Department Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-rose-500">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 mb-2">Delete Department?</h3>
            <p class="text-xs text-slate-500">
              Permanently delete <span class="font-bold text-slate-800">{{ selectedDept?.name }}</span>? Ensure no active courses or students depend on this department.
            </p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="deleteDept" :disabled="isLoading" class="flex-1 py-2.5 text-xs font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-xl transition-colors shadow-sm disabled:opacity-50">
              {{ isLoading ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 5. Global Floating Toast Notification -->
    <Teleport to="body">
      <div
        v-if="toast.show"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-2xl text-xs font-bold transition-all border animate-in slide-in-from-bottom duration-200"
        :class="{
          'bg-emerald-600 text-white border-emerald-700': toast.type === 'success',
          'bg-rose-600 text-white border-rose-700': toast.type === 'error',
          'bg-slate-800 text-white border-slate-900': toast.type === 'info',
        }"
      >
        <span>{{ toast.message }}</span>
      </div>
    </Teleport>

  </div>
</template>
