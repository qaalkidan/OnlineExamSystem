<script setup lang="ts">
import { ref, computed } from 'vue'

import apiClient from '../../../core/api/apiClient'

const search = ref('')
const statusFilter = ref('all')
const headFilter = ref('all')
const sortBy = ref('name')
const currentPage = ref(1)
const perPage = 8
const showAddForm = ref(false)
const showDeleteModal = ref(false)
const showEditModal = ref(false)
const editData = ref({ id: null as number|null, name: '', code: '', established: '', college: '', status: 'active' })
const selectedDept = ref<any>(null)
const isLoading = ref(false)

// Detail view state
const showDetailView = ref(false)
const viewingDept = ref<any>(null)
const detailActiveTab = ref('info')
const detailData = ref<any>(null)
const isDetailLoading = ref(false)

const viewDeptDetail = async (dept: any) => {
  viewingDept.value = dept
  detailActiveTab.value = 'info'
  showDetailView.value = true
  
  isDetailLoading.value = true
  try {
    const res = await apiClient.get(`/admin/departments/${dept.id}`)
    detailData.value = res.data.data
  } catch (err) {
    console.error('Failed to fetch department details:', err)
  } finally {
    isDetailLoading.value = false
  }
}

const backToDepartments = () => {
  showDetailView.value = false
  viewingDept.value = null
  detailData.value = null
}

const detailTabs = [
  { key: 'info', label: 'Department Information', icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'head', label: 'Department Head', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { key: 'instructors', label: 'Instructors', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { key: 'courses', label: 'Courses', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { key: 'students', label: 'Students', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { key: 'settings', label: 'Settings', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
  { key: 'activity', label: 'Activity Log', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
]

// Assign head from list view
const showListAssignHeadModal = ref(false)
const assignHeadTarget = ref<any>(null)
const assignHeadSearch = ref('')
const availableInstructors = ref<any[]>([])
const isAssigningHead = ref(false)
const assignHeadStatus = ref<{type: 'success' | 'error' | null, message: string}>({ type: null, message: '' })

const allDepts = ref<any[]>([])

// Add Department form data
const newDeptForm = ref({
  name: '',
  code: '',
  college: '',
})

const deptFormErrors = ref<Record<string, string>>({})
const deptFormTouched = ref<Record<string, boolean>>({})

const collegeOptions = [
  'College of Computing and Informatics'
]

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
  deptFormTouched.value = {
    name: true,
    code: true,
    college: true
  }

  const isNameValid = validateDeptField('name')
  const isCodeValid = validateDeptField('code')
  const isCollegeValid = validateDeptField('college')

  return isNameValid && isCodeValid && isCollegeValid
}

const resetDeptValidation = () => {
  deptFormErrors.value = {}
  deptFormTouched.value = {}
}

const deptFieldCls = (field: string, extra = '') => {
  const base = `w-full rounded-lg px-4 py-2.5 text-[13px] text-slate-700 transition-all focus:outline-none ${extra}`
  if (deptFormErrors.value[field]) {
    return `${base} border border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 placeholder:text-rose-300`
  }
  if (deptFormTouched.value[field] && !deptFormErrors.value[field]) {
    return `${base} border border-emerald-400 bg-emerald-50/10 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500`
  }
  return `${base} border border-slate-200 focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] placeholder:text-slate-400`
}

// Success toast state
const successToast = ref<{ show: boolean; message: string }>({ show: false, message: '' })
let toastTimer: ReturnType<typeof setTimeout> | null = null

function showSuccessToast(message: string) {
  if (toastTimer) clearTimeout(toastTimer)
  successToast.value = { show: true, message }
  toastTimer = setTimeout(() => { successToast.value.show = false }, 4000)
}

const resetAddForm = () => {
  newDeptForm.value = {
    name: '',
    code: '',
    college: '',
  }
  resetDeptValidation()
}

// Removed mock data

// Fetch departments from backend
const fetchDepartments = async () => {
  try {
    const res = await apiClient.get('/admin/departments')
    allDepts.value = res.data.data.map((d: any) => ({
      id: d.id,
      name: d.name,
      subLabel: d.college || 'General',
      head: d.head?.name || 'N/A',
      headEmail: d.head?.email || '',
      code: d.code,
      college: d.college || '',
      faculty: d.college || 'N/A',
      instructors: d.instructors_count || 0,
      students: d.students_count || 0,
      courses: d.courses_count || 0,
      status: d.status,
      established: d.established || 'N/A'
    }))
  } catch (err) {
    console.error('Failed to fetch departments:', err)
  }
}
fetchDepartments()

const allHeads = computed(() => {
  const set = new Set(allDepts.value.map(d => d.head).filter(h => h && h !== 'N/A'))
  return Array.from(set)
})

const filtered = computed(() =>
  allDepts.value.filter(d => {
    const matchSearch = d.name.toLowerCase().includes(search.value.toLowerCase()) ||
                        d.head.toLowerCase().includes(search.value.toLowerCase()) ||
                        d.code.toLowerCase().includes(search.value.toLowerCase())
    const matchStatus = statusFilter.value === 'all' || d.status === statusFilter.value
    const matchHead = headFilter.value === 'all' || d.head === headFilter.value
    return matchSearch && matchStatus && matchHead
  }).sort((a: any, b: any) => {
    if (sortBy.value === 'name') return a.name.localeCompare(b.name)
    if (sortBy.value === 'students') return b.students - a.students
    if (sortBy.value === 'code') return a.code.localeCompare(b.code)
    return 0
  })
)

const totalPages = computed(() => Math.ceil(filtered.value.length / perPage))
const paginated  = computed(() => filtered.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage))

const stats = computed(() => ({
  total:       allDepts.value.length,
  active:      allDepts.value.filter(d => d.status === 'active').length,
  students:    allDepts.value.reduce((a, d) => a + d.students, 0),
  instructors: allDepts.value.reduce((a, d) => a + d.instructors, 0),
  courses:     allDepts.value.reduce((a, d) => a + d.courses, 0),
}))

const getAvatarUrl = (name: string) => {
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=e0e7ff&color=4338ca&size=80`
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
    })
    await fetchDepartments()
    const createdName = newDeptForm.value.name
    showAddForm.value = false
    resetAddForm()
    showSuccessToast(`Department "${createdName}" created successfully!`)
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

// Assign Head from List View
const openAssignHeadFromList = async (dept: any) => {
  assignHeadTarget.value = dept
  assignHeadSearch.value = ''
  assignHeadStatus.value = { type: null, message: '' }
  showListAssignHeadModal.value = true
  try {
    const res = await apiClient.get(`/admin/instructors?department_id=${dept.id}`)
    availableInstructors.value = res.data.data
  } catch (err) {
    console.error('Failed to fetch instructors:', err)
    availableInstructors.value = []
  }
}

const filteredInstructorsForAssign = computed(() => {
  if (!assignHeadSearch.value) return availableInstructors.value
  const q = assignHeadSearch.value.toLowerCase()
  return availableInstructors.value.filter((i: any) =>
    i.name.toLowerCase().includes(q) || i.email.toLowerCase().includes(q)
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
    assignHeadStatus.value = { type: 'success', message: `${instructor.name} assigned as head successfully!` }
    await fetchDepartments()
    if (showDetailView.value && viewingDept.value?.id === assignHeadTarget.value.id) {
      await viewDeptDetail(viewingDept.value)
    }
    setTimeout(() => {
      showListAssignHeadModal.value = false
    }, 1500)
  } catch (err: any) {
    assignHeadStatus.value = {
      type: 'error',
      message: err.response?.data?.message || 'Failed to assign head.'
    }
  } finally {
    isAssigningHead.value = false
  }
}

const updateDept = async () => {
  if (!editData.value.name || !editData.value.code) return
  isLoading.value = true
  try {
    await apiClient.put(`/admin/departments/${editData.value.id}`, {
      name: editData.value.name,
      code: editData.value.code,
      college: editData.value.college,
      status: editData.value.status
    })
    await fetchDepartments()
    showEditModal.value = false
  } catch (err: any) {
    console.error('Error updating department:', err)
    let errorMessage = err.response?.data?.message || 'Failed to update department.'
    if (err.response?.data?.errors) {
      errorMessage += '\n' + Object.values(err.response.data.errors).flat().join('\n')
    }
    alert(errorMessage)
  } finally {
    isLoading.value = false
  }
}

const confirmDelete = (d: any) => { selectedDept.value = d; showDeleteModal.value = true }
const deleteDept = async () => { 
  if (!selectedDept.value) return
  isLoading.value = true
  try {
    await apiClient.delete(`/admin/departments/${selectedDept.value.id}`)
    await fetchDepartments()
    showDeleteModal.value = false 
  } catch (err) {
    console.error('Error deleting department:', err)
  } finally {
    isLoading.value = false
  }
}



</script>

<template>
  <div class="space-y-6">

    <!-- ── SUCCESS TOAST ── -->
    <Transition name="toast">
      <div v-if="successToast.show"
        class="fixed top-6 right-6 z-[9999] flex items-center gap-3 bg-white border border-emerald-200 shadow-xl rounded-xl px-5 py-4 min-w-[300px] max-w-sm">
        <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-[13px] font-bold text-slate-800">Department Created!</p>
          <p class="text-[12px] text-slate-500 mt-0.5 truncate">{{ successToast.message }}</p>
        </div>
        <button @click="successToast.show = false" class="text-slate-300 hover:text-slate-500 transition-colors shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    </Transition>

    <!-- ==================== DEPARTMENT DETAIL VIEW ====================  -->
    <template v-if="showDetailView && viewingDept">
      
      <!-- Breadcrumbs -->
      <div class="flex items-center gap-2 text-[13px]">
        <button @click="backToDepartments" class="text-slate-500 hover:text-[#4338ca] transition-colors">Departments</button>
        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        <span class="text-slate-700 font-semibold">Department Details</span>
      </div>

      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-[22px] font-bold text-slate-800">Department Details</h1>
          <p class="text-[13px] text-slate-500 mt-1">View and manage department information and settings</p>
        </div>
        <button @click="backToDepartments" class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 rounded-lg text-[13px] font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Departments
        </button>
      </div>

      <!-- Top Profile Card -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
        <div class="flex items-start gap-8">
          <!-- Avatar & Info -->
          <div class="flex items-start gap-6 flex-1">
            <div class="w-[100px] h-[100px] rounded-2xl bg-indigo-50 flex items-center justify-center shrink-0 border-2 border-indigo-100">
              <svg class="w-12 h-12 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div class="space-y-2">
              <div class="flex items-center gap-3">
                <h2 class="text-[20px] font-bold text-slate-800">{{ viewingDept.name }}</h2>
                <span :class="[viewingDept.status === 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200', 'text-[11px] font-bold px-2.5 py-0.5 rounded-full border capitalize']">{{ viewingDept.status }}</span>
              </div>
              <div class="space-y-1.5">
                <p class="text-[13px] text-slate-600">Department Code: <span class="font-bold text-[#4338ca]">{{ viewingDept.code }}</span></p>
                <p class="text-[13px] text-slate-600">Faculty: <span class="font-medium text-slate-700">{{ viewingDept.faculty }}</span></p>
                <p class="text-[13px] text-slate-500 max-w-[500px] leading-relaxed">Description: The {{ viewingDept.name }} department focuses on developing skilled software professionals through quality education and practical training.</p>
              </div>
            </div>
          </div>

          <!-- Stats -->
          <div class="flex items-center gap-4 shrink-0">
            <div v-for="(stat, si) in [
              { label: 'Students', val: detailData?.students?.length || 0, sub: 'Active Students', bg: 'bg-indigo-50', ic: 'text-[#4338ca]', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
              { label: 'Instructors', val: detailData?.instructors?.length || 0, sub: 'Total Instructors', bg: 'bg-blue-50', ic: 'text-blue-500', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
              { label: 'Courses', val: detailData?.courses?.length || 0, sub: 'Total Courses', bg: 'bg-amber-50', ic: 'text-amber-500', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
              { label: 'Exams', val: 0, sub: 'Total Exams', bg: 'bg-purple-50', ic: 'text-purple-500', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
            ]" :key="si" class="border border-slate-100 rounded-xl p-4 min-w-[110px] text-center">
              <div :class="[stat.bg, 'w-8 h-8 rounded-lg flex items-center justify-center mx-auto mb-2']">
                <svg class="w-4 h-4" :class="stat.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"></path></svg>
              </div>
              <p class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">{{ stat.label }}</p>
              <p v-if="isDetailLoading" class="h-6 w-8 bg-slate-100 rounded animate-pulse mx-auto mt-1"></p>
              <p v-else class="text-[22px] font-bold text-slate-800">{{ stat.val }}</p>
              <p class="text-[10px] text-slate-400">{{ stat.sub }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Area with Sidebar Tabs -->
      <div class="grid grid-cols-[220px_1fr] gap-6">
        
        <!-- Sidebar Navigation -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-3 self-start">
          <nav class="space-y-1">
            <button v-for="tab in detailTabs" :key="tab.key" @click="detailActiveTab = tab.key"
              :class="[detailActiveTab === tab.key ? 'bg-indigo-50 text-[#4338ca] font-bold' : 'text-slate-600 hover:bg-slate-50', 'flex items-center gap-3 w-full px-4 py-3 rounded-lg text-[13px] transition-colors text-left']">
              <svg class="w-4.5 h-4.5 shrink-0" :class="detailActiveTab === tab.key ? 'text-[#4338ca]' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"></path></svg>
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Main Content -->
        <div class="space-y-6">

          <!-- Department Information Tab -->
          <template v-if="detailActiveTab === 'info'">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
              <h3 class="text-[16px] font-bold text-slate-800 mb-6">Department Information</h3>
              <div v-if="isDetailLoading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4338ca]"></div>
              </div>
              <div v-else class="grid grid-cols-2 gap-x-16 gap-y-5">
                <div class="grid grid-cols-[140px_1fr] gap-2 items-start">
                  <span class="text-[13px] text-slate-500">Department Code</span>
                  <span class="text-[13px] font-semibold text-slate-800">{{ viewingDept.code }}</span>
                </div>
                <div class="grid grid-cols-[140px_1fr] gap-2 items-start">
                  <span class="text-[13px] text-slate-500">Head of Department</span>
                  <span class="text-[13px] font-semibold text-slate-800">{{ detailData?.department?.head?.name || 'Not assigned' }}</span>
                </div>
                <div class="grid grid-cols-[140px_1fr] gap-2 items-start">
                  <span class="text-[13px] text-slate-500">Faculty/College</span>
                  <span class="text-[13px] font-semibold text-slate-800">{{ viewingDept.college || viewingDept.faculty || 'N/A' }}</span>
                </div>
                <div class="grid grid-cols-[140px_1fr] gap-2 items-start">
                  <span class="text-[13px] text-slate-500">Head Email</span>
                  <span class="text-[13px] font-semibold text-slate-800">{{ detailData?.department?.head?.email || 'N/A' }}</span>
                </div>
                <div class="grid grid-cols-[140px_1fr] gap-2 items-start">
                  <span class="text-[13px] text-slate-500">Established Date</span>
                  <span class="text-[13px] font-semibold text-slate-800">{{ viewingDept.established !== 'N/A' ? viewingDept.established : 'N/A' }}</span>
                </div>
                <div class="grid grid-cols-[140px_1fr] gap-2 items-start">
                  <span class="text-[13px] text-slate-500">Head Phone</span>
                  <span class="text-[13px] font-semibold text-slate-800">{{ detailData?.department?.head?.phone || 'N/A' }}</span>
                </div>
                <div class="grid grid-cols-[140px_1fr] gap-2 items-start">
                  <span class="text-[13px] text-slate-500">Status</span>
                  <span><span :class="[viewingDept.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500', 'text-[11px] font-bold px-2.5 py-1 rounded-full capitalize']">{{ viewingDept.status }}</span></span>
                </div>
              </div>
            </div>

            <!-- Description, Vision, Mission -->
            <div class="grid grid-cols-3 gap-6">
              <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                <h4 class="text-[14px] font-bold text-slate-800 mb-3">Description</h4>
                <p class="text-[13px] text-slate-500 leading-relaxed">The {{ viewingDept.name }} department focuses on developing skilled professionals through quality education and practical training.</p>
              </div>
              <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                <h4 class="text-[14px] font-bold text-slate-800 mb-3">Vision</h4>
                <p class="text-[13px] text-slate-500 leading-relaxed">To become a leading center of excellence in {{ viewingDept.name.toLowerCase() }} education and research in East Africa.</p>
              </div>
              <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                <h4 class="text-[14px] font-bold text-slate-800 mb-3">Mission</h4>
                <p class="text-[13px] text-slate-500 leading-relaxed">To produce competent and ethical professionals who can contribute to the nation's technological advancement.</p>
              </div>
            </div>
          </template>

          <!-- Department Head Tab -->
          <template v-if="detailActiveTab === 'head'">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
              <div class="flex items-start justify-between mb-6">
                <div>
                  <h3 class="text-[16px] font-bold text-slate-800">Department Head</h3>
                  <p class="text-[13px] text-slate-500 mt-1">Assign an instructor as the department head will additional privileges to manage the department.</p>
                </div>
                <button @click="openAssignHeadFromList(viewingDept)" class="flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white text-[13px] font-bold px-4 py-2.5 rounded-lg shadow-sm transition-all">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                  Change Department Head
                </button>
              </div>

              <!-- Current Head Card -->
              <div v-if="isDetailLoading" class="flex justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4338ca]"></div>
              </div>
              <div v-else-if="detailData?.department?.head" class="border border-slate-200 rounded-xl p-6 bg-slate-50/50">
                <h4 class="text-[14px] font-bold text-slate-800 mb-4">Current Department Head</h4>
                <div class="flex items-center gap-4">
                  <img :src="getAvatarUrl(detailData.department.head.name)" :alt="detailData.department.head.name" class="w-16 h-16 rounded-full border-3 border-white shadow-sm" />
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <h5 class="text-[15px] font-bold text-slate-800">{{ detailData.department.head.name }}</h5>
                      <span class="text-[10px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Current Head</span>
                    </div>
                    <p class="text-[12px] text-slate-500">{{ detailData.department.head.email }}</p>
                    <p class="text-[12px] text-slate-500">{{ detailData.department.head.phone || 'No phone number' }}</p>
                    <p class="text-[12px] text-slate-400 mt-1">Joined: {{ new Date(detailData.department.head.created_at).toLocaleDateString() }}</p>
                  </div>
                </div>
              </div>
              <div v-else class="border border-slate-200 border-dashed rounded-xl p-10 text-center">
                <div class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-3">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <h4 class="text-[14px] font-bold text-slate-800 mb-1">No Department Head</h4>
                <p class="text-[12px] text-slate-500">This department currently has no assigned head.</p>
               
              </div>
            </div>
          </template>

          <!-- Instructors Tab -->
          <template v-if="detailActiveTab === 'instructors'">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-[16px] font-bold text-slate-800">Instructors <span class="text-slate-400 font-normal">({{ viewingDept.instructors }})</span></h3>
              </div>
              <div v-if="isDetailLoading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4338ca]"></div>
              </div>
              <div v-else-if="!detailData?.instructors?.length" class="py-12 flex flex-col items-center justify-center text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 mt-2">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mb-3">
                  <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <h4 class="text-[14px] font-bold text-slate-700">No Instructors</h4>
                <p class="text-[13px] text-slate-500 mt-1">There are no instructors assigned to this department yet.</p>
              </div>
              <table v-else class="w-full">
                <thead>
                  <tr class="border-b border-slate-100">
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Instructor</th>
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email</th>
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Role</th>
                    <th class="text-center py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="inst in detailData?.instructors || []" :key="inst.id" class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                    <td class="py-3.5 px-4">
                      <div class="flex items-center gap-3">
                        <img :src="getAvatarUrl(inst.name)" :alt="inst.name" class="w-8 h-8 rounded-full border border-slate-100" />
                        <span class="text-[13px] font-semibold text-slate-700">{{ inst.name }}</span>
                      </div>
                    </td>
                    <td class="py-3.5 px-4 text-[12px] text-slate-500">{{ inst.email }}</td>
                    <td class="py-3.5 px-4 text-[12px] text-slate-500 capitalize">{{ inst.role === 'dept_head' ? 'Department Head' : 'Instructor' }}</td>
                    <td class="py-3.5 px-4 text-center"><span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600">Active</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Courses Tab -->
          <template v-if="detailActiveTab === 'courses'">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-[16px] font-bold text-slate-800">Courses <span class="text-slate-400 font-normal">({{ viewingDept.courses }})</span></h3>
              </div>
              <div v-if="isDetailLoading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4338ca]"></div>
              </div>
              <div v-else-if="!detailData?.courses?.length" class="py-12 flex flex-col items-center justify-center text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 mt-2">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mb-3">
                  <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </div>
                <h4 class="text-[14px] font-bold text-slate-700">No Courses</h4>
                <p class="text-[13px] text-slate-500 mt-1">There are no courses assigned to this department yet.</p>
              </div>
              <table v-else class="w-full">
                <thead>
                  <tr class="border-b border-slate-100">
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Course Code</th>
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Course Title</th>
                    <th class="text-center py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Credits</th>
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Instructor</th>
                    <th class="text-center py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Semester</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="course in detailData?.courses || []" :key="course.id" class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                    <td class="py-3.5 px-4 text-[13px] font-semibold font-mono text-slate-700">{{ course.code }}</td>
                    <td class="py-3.5 px-4 text-[13px] text-slate-600">{{ course.title }}</td>
                    <td class="py-3.5 px-4 text-center text-[13px] font-bold text-slate-700">{{ course.credits }}</td>
                    <td class="py-3.5 px-4 text-[12px] text-slate-500">{{ course.instructor?.name || 'Not assigned' }}</td>
                    <td class="py-3.5 px-4 text-center text-[13px] text-slate-600">{{ course.semester }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Students Tab -->
          <template v-if="detailActiveTab === 'students'">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-[16px] font-bold text-slate-800">Students <span class="text-slate-400 font-normal">({{ viewingDept.students }})</span></h3>
              </div>
              <div v-if="isDetailLoading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4338ca]"></div>
              </div>
              <div v-else-if="!detailData?.students?.length" class="py-12 flex flex-col items-center justify-center text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 mt-2">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mb-3">
                  <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <h4 class="text-[14px] font-bold text-slate-700">No Students</h4>
                <p class="text-[13px] text-slate-500 mt-1">There are no students enrolled in this department yet.</p>
              </div>
              <table v-else class="w-full">
                <thead>
                  <tr class="border-b border-slate-100">
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Student</th>
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email</th>
                    <th class="text-left py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Joined Date</th>
                    <th class="text-center py-3 px-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="student in detailData?.students || []" :key="student.id" class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                    <td class="py-3.5 px-4">
                      <div class="flex items-center gap-3">
                        <img :src="getAvatarUrl(student.name)" :alt="student.name" class="w-8 h-8 rounded-full border border-slate-100" />
                        <span class="text-[13px] font-semibold text-slate-700">{{ student.name }}</span>
                      </div>
                    </td>
                    <td class="py-3.5 px-4 text-[12px] text-slate-500">{{ student.email }}</td>
                    <td class="py-3.5 px-4 text-[12px] text-slate-600">{{ new Date(student.created_at).toLocaleDateString() }}</td>
                    <td class="py-3.5 px-4 text-center"><span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600">Active</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Settings Tab -->
          <template v-if="detailActiveTab === 'settings'">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
              <h3 class="text-[16px] font-bold text-slate-800 mb-6">Department Settings</h3>
              <div class="space-y-6">
                <div class="flex items-center justify-between p-4 border border-slate-100 rounded-lg">
                  <div>
                    <p class="text-[13px] font-bold text-slate-700">Department Status</p>
                    <p class="text-[12px] text-slate-500 mt-0.5">Enable or disable this department</p>
                  </div>
                  <span :class="[viewingDept.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500', 'text-[11px] font-bold px-3 py-1.5 rounded-full capitalize']">{{ viewingDept.status }}</span>
                </div>
                <div class="flex items-center justify-between p-4 border border-slate-100 rounded-lg">
                  <div>
                    <p class="text-[13px] font-bold text-slate-700">Accept New Students</p>
                    <p class="text-[12px] text-slate-500 mt-0.5">Allow new student enrollments</p>
                  </div>
                  <span class="text-[11px] font-bold px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-600">Enabled</span>
                </div>
                <div class="flex items-center justify-between p-4 border border-rose-100 rounded-lg bg-rose-50/30">
                  <div>
                    <p class="text-[13px] font-bold text-rose-700">Danger Zone</p>
                    <p class="text-[12px] text-rose-500 mt-0.5">Permanently delete this department and all associated data</p>
                  </div>
                  <button class="px-4 py-2 text-[12px] font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-lg transition-colors">Delete Department</button>
                </div>
              </div>
            </div>
          </template>

          <!-- Activity Log Tab -->
          <template v-if="detailActiveTab === 'activity'">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
              <h3 class="text-[16px] font-bold text-slate-800 mb-6">Activity Log</h3>
              
              <div v-if="isDetailLoading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4338ca]"></div>
              </div>
              <div v-else-if="!detailData?.activities?.length" class="py-12 flex flex-col items-center justify-center text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mb-3">
                  <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h4 class="text-[14px] font-bold text-slate-700">No Activity</h4>
                <p class="text-[13px] text-slate-500 mt-1">There are no activities logged for this department yet.</p>
              </div>
              <div v-else class="space-y-4">
                <div v-for="activity in detailData.activities" :key="activity.id" class="flex items-start gap-4 p-4 border border-slate-100 rounded-lg hover:bg-slate-50/50 transition-colors">
                  <div :class="[
                    activity.type === 'created' ? 'bg-indigo-50 text-[#4338ca]' : 
                    activity.type === 'head_assigned' ? 'bg-blue-50 text-blue-500' : 
                    activity.type === 'status_changed' ? 'bg-amber-50 text-amber-500' : 'bg-slate-50 text-slate-500', 
                    'w-9 h-9 rounded-lg flex items-center justify-center shrink-0'
                  ]">
                    <svg v-if="activity.type === 'created'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"></path></svg>
                    <svg v-else-if="activity.type === 'head_assigned'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    <svg v-else-if="activity.type === 'status_changed'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  </div>
                  <div class="flex-1">
                    <p class="text-[13px] font-semibold text-slate-700">{{ activity.action }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">by {{ activity.user?.name || 'System' }} · {{ new Date(activity.created_at).toLocaleString() }}</p>
                  </div>
                </div>
              </div>
            </div>
          </template>

        </div>
      </div>

      <!-- Removed mock Assign Head Modal -->


    </template>

    <!-- ==================== ADD DEPARTMENT FORM VIEW ==================== -->
    <template v-if="showAddForm && !showDetailView">
      
      <!-- Breadcrumbs -->
      <div class="flex items-center gap-2 text-[13px]">
        <button @click="showAddForm = false; resetAddForm()" class="text-slate-500 hover:text-[#4338ca] transition-colors">Departments</button>
        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        <span class="text-slate-700 font-semibold">Add Department</span>
      </div>

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-[22px] font-bold text-slate-800">Add Department</h1>
          <p class="text-[13px] text-slate-500 mt-1">Create a new academic department in the university.</p>
        </div>
        <button
          @click="showAddForm = false; resetAddForm()"
          class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-[#4338ca] transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Departments
        </button>
      </div>

      <!-- Department Information Section -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8">
        <div class="flex items-center gap-3 mb-8">
          <div class="w-9 h-9 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          </div>
          <h2 class="text-[16px] font-bold text-slate-800">Department Information</h2>
        </div>

        <div class="space-y-6">
          <!-- Row 1: Name, Code, College -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-[12px] font-semibold text-slate-700 mb-2">Department Name <span class="text-rose-500">*</span></label>
              <input
                v-model="newDeptForm.name"
                type="text"
                placeholder="e.g., Computer Science"
                :class="deptFieldCls('name')"
                @blur="touchDeptField('name')"
                @input="deptFormTouched.name && validateDeptField('name')"
              />
              <p v-if="deptFormErrors.name" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1 font-medium">
                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ deptFormErrors.name }}
              </p>
              <p v-else-if="deptFormTouched.name && !deptFormErrors.name" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1 font-medium">
                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Looks good!
              </p>
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-slate-700 mb-2">Department Code <span class="text-rose-500">*</span></label>
              <input
                v-model="newDeptForm.code"
                type="text"
                placeholder="e.g., CS"
                :class="deptFieldCls('code', 'font-mono uppercase')"
                @blur="touchDeptField('code')"
                @input="deptFormTouched.code && validateDeptField('code')"
              />
              <p v-if="deptFormErrors.code" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1 font-medium">
                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ deptFormErrors.code }}
              </p>
              <p v-else-if="deptFormTouched.code && !deptFormErrors.code" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1 font-medium">
                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Looks good!
              </p>
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-slate-700 mb-2">College/School <span class="text-rose-500">*</span></label>
              <div class="relative">
                <select
                  v-model="newDeptForm.college"
                  :class="deptFieldCls('college', 'appearance-none bg-white pr-9')"
                  @blur="touchDeptField('college')"
                  @change="touchDeptField('college')"
                >
                  <option value="">Select College/School</option>
                  <option v-for="col in collegeOptions" :key="col" :value="col">{{ col }}</option>
                </select>
                <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </div>
              <p v-if="deptFormErrors.college" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1 font-medium">
                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ deptFormErrors.college }}
              </p>
              <p v-else-if="deptFormTouched.college && !deptFormErrors.college" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1 font-medium">
                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Looks good!
              </p>
            </div>
          </div>

          <!-- Server Error Banner -->
          <div v-if="deptFormErrors._server" class="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-3">
            <div class="w-5 h-5 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div class="flex-1 text-[13px] text-rose-800 font-medium whitespace-pre-line leading-relaxed">
              {{ deptFormErrors._server }}
            </div>
            <button @click="delete deptFormErrors._server" class="text-rose-400 hover:text-rose-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <!-- Tip: Head is assigned after creation -->
          <div class="p-3 bg-blue-50 text-blue-600 border border-blue-100 rounded-lg text-[12px] flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Department Head can be assigned after creating the department using the assign button in the department list.
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="flex items-center justify-end gap-3 pt-2 pb-4">
        <button @click="showAddForm = false; resetAddForm()" class="px-6 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
          Cancel
        </button>
        <button @click="saveDepartment" :disabled="isLoading" class="flex items-center gap-2 px-6 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-700 rounded-lg shadow-sm shadow-indigo-200 transition-all disabled:opacity-70 disabled:cursor-not-allowed">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
          {{ isLoading ? 'Saving...' : 'Save Department' }}
        </button>
      </div>

    </template>

    <!-- ==================== DEPARTMENTS LIST VIEW ==================== -->
    <template v-if="!showAddForm && !showDetailView">

      <!-- Page Actions -->
      <div class="flex items-center justify-end">
        <button @click="showAddForm = true; resetAddForm()" class="flex items-center gap-2 bg-[#4338ca] hover:bg-indigo-700 text-white text-[13px] font-bold px-5 py-2.5 rounded-lg shadow-sm shadow-indigo-200 transition-all">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          Add Department
        </button>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-5 gap-4">
        <div v-for="(item, i) in [
          { label:'TOTAL DEPARTMENTS', val: stats.total,       bg:'bg-indigo-50', ic:'text-[#4338ca]',  icon:'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', borderColor:'border-indigo-100' },
          { label:'ACTIVE DEPARTMENTS', val: stats.active,      bg:'bg-emerald-50',ic:'text-emerald-500', icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', borderColor:'border-emerald-100' },
          { label:'TOTAL STUDENTS',     val: stats.students.toLocaleString(),    bg:'bg-green-50',  ic:'text-green-500',   icon:'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', borderColor:'border-green-100' },
          { label:'TOTAL INSTRUCTORS',  val: stats.instructors, bg:'bg-blue-50',   ic:'text-blue-500',    icon:'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', borderColor:'border-blue-100' },
          { label:'TOTAL COURSES',      val: stats.courses,     bg:'bg-purple-50', ic:'text-purple-500',  icon:'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', borderColor:'border-purple-100' },
        ]" :key="i" :class="[item.borderColor, 'bg-white border rounded-xl shadow-sm p-5 flex items-center gap-4']">
          <div :class="[item.bg, 'w-11 h-11 rounded-xl flex items-center justify-center shrink-0']">
            <svg class="w-5 h-5" :class="item.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path></svg>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ item.label }}</p>
            <p class="text-[22px] font-bold text-slate-800 mt-0.5">{{ item.val }}</p>
          </div>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        
        <!-- Search & Filters -->
        <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-100">
          <div class="relative flex-1 max-w-[420px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input v-model="search" placeholder="Search departments by name or code..." class="w-full pl-9 pr-4 py-2.5 text-[13px] border border-slate-200 rounded-lg focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] placeholder:text-slate-400">
          </div>
          
          <div class="relative">
            <select v-model="headFilter" class="pl-4 pr-8 py-2.5 text-[13px] border border-slate-200 rounded-lg text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] w-[180px]">
              <option value="all">All Department Heads</option>
              <option v-for="h in allHeads" :key="h" :value="h">{{ h }}</option>
            </select>
            <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
          
          <div class="relative">
            <select v-model="statusFilter" class="pl-4 pr-8 py-2.5 text-[13px] border border-slate-200 rounded-lg text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] w-[140px]">
              <option value="all">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
          
          <div class="relative">
            <select v-model="sortBy" class="pl-4 pr-8 py-2.5 text-[13px] border border-slate-200 rounded-lg text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] w-[150px]">
              <option value="name">Sort by Name</option>
              <option value="students">Sort by Students</option>
              <option value="code">Sort by Code</option>
            </select>
            <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
          
          <button class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 rounded-lg text-[13px] text-slate-600 hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Filter
          </button>
        </div>

        <!-- Table -->
        <table class="w-full">
          <thead>
            <tr class="border-b border-slate-100">
              <th class="text-left px-6 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Department</th>
              <th class="text-left px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Department Code</th>
                            <th class="text-left px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Head of Department</th>
              <th class="text-center px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Students</th>
              <th class="text-center px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Instructors</th>
              <th class="text-center px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Courses</th>
              <th class="text-center px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
              <th class="text-center px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="dept in paginated" :key="dept.id" class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors group">
              <!-- Department Name -->
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                  </div>
                  <div>
                    <p class="text-[13px] font-bold text-slate-800 leading-snug">{{ dept.name }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">{{ dept.subLabel }}</p>
                  </div>
                </div>
              </td>
              
              <!-- Department Code -->
              <td class="px-4 py-4">
                <span class="text-[13px] font-semibold font-mono text-slate-700">{{ dept.code }}</span>
              </td>
              
              
              <!-- Head of Department -->
              <td class="px-4 py-4">
                <div class="flex items-center gap-3">
                  <img :src="getAvatarUrl(dept.head)" :alt="dept.head" class="w-9 h-9 rounded-full object-cover border-2 border-slate-100" />
                  <div>
                    <p class="text-[12px] font-semibold text-slate-700">{{ dept.head }}</p>
                    <p class="text-[11px] text-slate-400">{{ dept.headEmail }}</p>
                  </div>
                </div>
              </td>
              
              <!-- Students -->
              <td class="px-4 py-4 text-center"><span class="text-[13px] font-bold text-slate-800">{{ dept.students }}</span></td>
              
              <!-- Instructors -->
              <td class="px-4 py-4 text-center"><span class="text-[13px] font-bold text-slate-800">{{ dept.instructors }}</span></td>
              
              <!-- Courses -->
              <td class="px-4 py-4 text-center"><span class="text-[13px] font-bold text-slate-800">{{ dept.courses }}</span></td>
              
              <!-- Status -->
              <td class="px-4 py-4 text-center">
                <span :class="[dept.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500', 'text-[11px] font-bold px-2.5 py-1 rounded-full capitalize']">
                  {{ dept.status }}
                </span>
              </td>
              
              <!-- Actions -->
              <td class="px-4 py-4">
                <div class="flex items-center justify-center gap-1">
                  <button @click="openAssignHeadFromList(dept)" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors" title="Assign Department Head">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                  </button>
                  <button @click="viewDeptDetail(dept)" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-emerald-500 hover:bg-emerald-50 transition-colors" title="View Details">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>
                  <button @click="openEditModal(dept)" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-[#4338ca] hover:bg-indigo-50 transition-colors" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                  </button>
                  <button @click="confirmDelete(dept)" class="w-8 h-8 rounded-lg flex items-center justify-center text-rose-400 hover:text-rose-500 hover:bg-rose-50 transition-colors" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </div>
              </td>
            </tr>
            
            <!-- Empty State -->
            <tr v-if="paginated.length === 0">
              <td colspan="9" class="py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                  <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center"><svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></div>
                  <p class="text-[14px] font-bold text-slate-600">No departments found</p>
                  <p class="text-[12px] text-slate-400">Try adjusting your search or filter criteria.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100">
          <p class="text-[13px] text-slate-500">Showing {{ Math.min((currentPage-1)*perPage+1, filtered.length) }} to {{ Math.min(currentPage*perPage, filtered.length) }} of {{ filtered.length }} departments</p>
          <div class="flex items-center gap-1">
            <button @click="currentPage = Math.max(1, currentPage-1)" :disabled="currentPage===1" class="w-8 h-8 rounded-lg flex items-center justify-center border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <button v-for="p in totalPages" :key="p" @click="currentPage = p" :class="[currentPage===p?'bg-[#4338ca] text-white shadow-sm':'text-slate-600 border border-slate-200 hover:bg-slate-50','w-8 h-8 rounded-lg flex items-center justify-center text-[13px] font-semibold transition-colors']">{{ p }}</button>
            <button @click="currentPage = Math.min(totalPages, currentPage+1)" :disabled="currentPage===totalPages || totalPages===0" class="w-8 h-8 rounded-lg flex items-center justify-center border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>
        </div>
      </div>

    </template>

    <!-- Delete Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4"><svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg></div>
            <h3 class="text-[16px] font-bold text-slate-800 mb-2">Delete Department?</h3>
            <p class="text-[13px] text-slate-500">Delete <span class="font-bold text-slate-700">{{ selectedDept?.name }}</span>? Ensure no active courses or instructors are assigned first.</p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="deleteDept" :disabled="isLoading" class="flex-1 py-2.5 text-[13px] font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-xl transition-colors disabled:opacity-70 disabled:cursor-not-allowed">
              {{ isLoading ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Assign Head from List Modal -->
    <Teleport to="body">
      <div v-if="showListAssignHeadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
          <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div>
              <h3 class="text-[16px] font-bold text-slate-800">Assign Department Head</h3>
              <p class="text-[12px] text-slate-500 mt-0.5">{{ assignHeadTarget?.name }} ({{ assignHeadTarget?.code }})</p>
            </div>
            <button @click="showListAssignHeadModal = false" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
          </div>
          <div class="px-6 py-5 space-y-4">

            <div v-if="assignHeadStatus.type" :class="assignHeadStatus.type === 'success' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100'" class="p-3 text-[12px] font-medium border rounded-xl flex items-center gap-2">
              <svg v-if="assignHeadStatus.type === 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              {{ assignHeadStatus.message }}
            </div>

            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              <input v-model="assignHeadSearch" type="text" placeholder="Search instructors by name or email..." class="w-full pl-9 pr-4 py-2.5 border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] placeholder:text-slate-400" />
            </div>
            <div class="border border-slate-200 rounded-lg overflow-hidden max-h-[280px] overflow-y-auto">
              <div v-if="filteredInstructorsForAssign.length === 0" class="py-8 text-center">
                <p class="text-[13px] text-slate-400">No instructors found. Add instructors first.</p>
              </div>
              <div v-for="inst in filteredInstructorsForAssign" :key="inst.id"
                @click="doAssignHead(inst)"
                class="flex items-center gap-3 px-4 py-3 hover:bg-indigo-50 cursor-pointer transition-colors border-b border-slate-50 last:border-b-0"
                :class="assignHeadTarget?.head === inst.name ? 'bg-indigo-50' : ''">
                <img :src="getAvatarUrl(inst.name)" :alt="inst.name" class="w-9 h-9 rounded-full border-2 border-slate-100" />
                <div class="flex-1 min-w-0">
                  <p class="text-[13px] font-semibold text-slate-700 truncate">{{ inst.name }}</p>
                  <p class="text-[11px] text-slate-400 truncate">{{ inst.email }}</p>
                </div>
                <span v-if="inst.role === 'dept_head'" class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full shrink-0">Dept Head</span>
                <span v-if="assignHeadTarget?.head === inst.name" class="text-[10px] font-bold text-[#4338ca] bg-indigo-100 px-2 py-0.5 rounded-full shrink-0">Current</span>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <button @click="showListAssignHeadModal = false" class="px-5 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Modal -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
          <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div><h3 class="text-[16px] font-bold text-slate-800">Edit Department</h3><p class="text-[12px] text-slate-500 mt-0.5">Update department details.</p></div>
            <button @click="showEditModal = false" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
          </div>
          <div class="px-6 py-5 space-y-4 overflow-y-auto max-h-[60vh]">
            <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Department Name <span class="text-rose-500">*</span></label><input v-model="editData.name" type="text" placeholder="e.g. Computer Science" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca]"></div>
            
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Dept Code <span class="text-rose-500">*</span></label><input v-model="editData.code" type="text" placeholder="e.g. CS" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] font-mono focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca]"></div>
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">College/School</label>
                <select v-model="editData.college" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca]">
                  <option value="">Select College/School</option>
                  <option v-for="col in collegeOptions" :key="'edit-'+col" :value="col">{{ col }}</option>
                  <option v-if="editData.college && !collegeOptions.includes(editData.college)" :value="editData.college">{{ editData.college }}</option>
                </select>
              </div>
            </div>

            <div class="flex items-center justify-between p-4 border border-slate-200 rounded-xl">
              <div>
                <label class="block text-[13px] font-bold text-slate-700">Department Status</label>
                <p class="text-[11px] text-slate-500 mt-0.5">Toggle to set department as active or inactive.</p>
              </div>
              <button 
                @click="editData.status = editData.status === 'active' ? 'inactive' : 'active'" 
                :class="[
                  editData.status === 'active' ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-slate-300 hover:bg-slate-400',
                  'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none'
                ]" 
                role="switch" 
                :aria-checked="editData.status === 'active'"
              >
                <span 
                  aria-hidden="true" 
                  :class="[
                    editData.status === 'active' ? 'translate-x-5' : 'translate-x-0',
                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                  ]"
                ></span>
              </button>
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <button @click="showEditModal = false" class="px-5 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors">Cancel</button>
            <button @click="updateDept" :disabled="isLoading" class="px-5 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-700 rounded-xl shadow-sm transition-all disabled:opacity-70 disabled:cursor-not-allowed">
              {{ isLoading ? 'Saving...' : 'Save Changes' }}
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
  transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(2rem);
}
</style>
