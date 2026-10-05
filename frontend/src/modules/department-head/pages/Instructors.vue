<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '../../../core/api/apiClient'

// ── View State ──
const currentView = ref<'list' | 'add' | 'detail' | 'edit'>('list')

// ── State ──
const deptName = ref('Loading...')
const search = ref('')
const statusFilter = ref('all')
const yearFilter = ref('all')
const sectionFilter = ref('all')
const currentPage = ref(1)
const perPage = 8
const showDeleteModal = ref(false)
const selectedInstructor = ref<any>(null)
const isLoading = ref(false)
const allInstructors = ref<any[]>([])

const addForm = ref({
  fullName: '',
  email: '',
  phone: '',
  gender: '',
  profilePicture: null as File | null,
  employeeId: '',
  yearLevel: '',
  username: '',
  password: '',
  confirmPassword: ''
})
const showPassword = ref(false)
const showConfirmPassword = ref(false)

const addFormErrors = ref<Record<string, string>>({})
const addFormTouched = ref<Record<string, boolean>>({})

// Professional password requirement rules
const addPasswordRules = computed(() => {
  const pwd = addForm.value.password || ''
  return {
    minLength: pwd.length >= 8,
    uppercase: /[A-Z]/.test(pwd),
    lowercase: /[a-z]/.test(pwd),
    number: /[0-9]/.test(pwd),
    special: /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?`~]/.test(pwd),
  }
})

const isAddPasswordValid = computed(() => {
  const r = addPasswordRules.value
  return r.minLength && r.uppercase && r.lowercase && r.number && r.special
})

const addPasswordStrength = computed(() => {
  const r = addPasswordRules.value
  const metCount = [r.minLength, r.uppercase, r.lowercase, r.number, r.special].filter(Boolean).length
  if (metCount <= 1) return { score: 1, label: 'Weak', barClass: 'bg-rose-500', textClass: 'text-rose-500', width: '25%' }
  if (metCount <= 3) return { score: 2, label: 'Medium', barClass: 'bg-amber-500', textClass: 'text-amber-500', width: '50%' }
  if (metCount === 4) return { score: 3, label: 'Good', barClass: 'bg-blue-500', textClass: 'text-blue-500', width: '75%' }
  return { score: 4, label: 'Strong', barClass: 'bg-emerald-500', textClass: 'text-emerald-500', width: '100%' }
})

const validateAddFormField = (field: string) => {
  addFormTouched.value[field] = true

  if (field === 'fullName') {
    if (!addForm.value.fullName || !addForm.value.fullName.trim()) {
      addFormErrors.value.fullName = 'Full name is required'
    } else {
      delete addFormErrors.value.fullName
    }
  } else if (field === 'email') {
    if (!addForm.value.email || !addForm.value.email.trim()) {
      addFormErrors.value.email = 'Email address is required'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addForm.value.email.trim())) {
      addFormErrors.value.email = 'Please enter a valid email address'
    } else {
      delete addFormErrors.value.email
    }
  } else if (field === 'phone') {
    if (!addForm.value.phone || !addForm.value.phone.trim()) {
      addFormErrors.value.phone = 'Phone number is required'
    } else {
      delete addFormErrors.value.phone
    }
  } else if (field === 'gender') {
    if (!addForm.value.gender) {
      addFormErrors.value.gender = 'Please select a gender'
    } else {
      delete addFormErrors.value.gender
    }
  } else if (field === 'employeeId') {
    if (!addForm.value.employeeId || !addForm.value.employeeId.trim()) {
      addFormErrors.value.employeeId = 'Employee ID is required'
    } else {
      delete addFormErrors.value.employeeId
    }
  } else if (field === 'username') {
    if (!addForm.value.username || !addForm.value.username.trim()) {
      addFormErrors.value.username = 'Username is required'
    } else {
      delete addFormErrors.value.username
    }
  } else if (field === 'password') {
    if (!addForm.value.password) {
      addFormErrors.value.password = 'Password is required'
    } else if (!addPasswordRules.value.minLength) {
      addFormErrors.value.password = 'Password must be at least 8 characters'
    } else if (!addPasswordRules.value.uppercase || !addPasswordRules.value.lowercase) {
      addFormErrors.value.password = 'Password must include uppercase and lowercase letters'
    } else if (!addPasswordRules.value.number) {
      addFormErrors.value.password = 'Password must include at least one number (0-9)'
    } else if (!addPasswordRules.value.special) {
      addFormErrors.value.password = 'Password must include at least one special character (!@#$%^&*)'
    } else {
      delete addFormErrors.value.password
    }
    if (addFormTouched.value.confirmPassword) {
      validateAddFormField('confirmPassword')
    }
  } else if (field === 'confirmPassword') {
    if (!addForm.value.confirmPassword) {
      addFormErrors.value.confirmPassword = 'Confirm password is required'
    } else if (addForm.value.confirmPassword !== addForm.value.password) {
      addFormErrors.value.confirmPassword = 'Passwords do not match'
    } else {
      delete addFormErrors.value.confirmPassword
    }
  }
}

const validateAddForm = (): boolean => {
  const fields = ['fullName', 'email', 'phone', 'gender', 'employeeId', 'username', 'password', 'confirmPassword']
  fields.forEach(f => validateAddFormField(f))
  return Object.keys(addFormErrors.value).length === 0
}

const addPhotoPreview = ref<string | null>(null)
const addFileInput = ref<HTMLInputElement | null>(null)
const editPhotoPreview = ref<string | null>(null)
const editFileInput = ref<HTMLInputElement | null>(null)

const resolveAvatarUrl = (url: string | null | undefined): string | null => {
  if (!url) return null
  if (url.startsWith('http://localhost/') && !url.startsWith('http://localhost:8000/')) {
    return url.replace('http://localhost/', 'http://localhost:8000/')
  }
  return url
}

const triggerAddFileInput = () => {
  addFileInput.value?.click()
}

const triggerEditFileInput = () => {
  editFileInput.value?.click()
}

const handleFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    if (file.size > 2 * 1024 * 1024) {
      addFormErrors.value.profilePicture = 'Profile photo must be less than 2MB'
      return
    }
    if (!['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'image/gif'].includes(file.type)) {
      addFormErrors.value.profilePicture = 'Only PNG, JPG, WEBP, or GIF images are supported'
      return
    }
    delete addFormErrors.value.profilePicture
    addForm.value.profilePicture = file
    addPhotoPreview.value = URL.createObjectURL(file)
  }
}

const removeAddPhoto = (e?: Event) => {
  if (e) e.stopPropagation()
  addForm.value.profilePicture = null
  if (addPhotoPreview.value) {
    URL.revokeObjectURL(addPhotoPreview.value)
    addPhotoPreview.value = null
  }
  if (addFileInput.value) {
    addFileInput.value.value = ''
  }
  delete addFormErrors.value.profilePicture
}

const resetAddForm = () => {
  addForm.value = {
    fullName: '', email: '', phone: '', gender: '', profilePicture: null,
    employeeId: '', yearLevel: '',
    username: '', password: '', confirmPassword: ''
  }
  if (addPhotoPreview.value) {
    URL.revokeObjectURL(addPhotoPreview.value)
    addPhotoPreview.value = null
  }
  if (addFileInput.value) {
    addFileInput.value.value = ''
  }
  addFormErrors.value = {}
  addFormTouched.value = {}
}

const openAddPage = () => { resetAddForm(); currentView.value = 'add' }
const backToList = () => { currentView.value = 'list' }

// ── Edit Instructor Form ──
const editForm = ref({
  fullName: '',
  email: '',
  phone: '',
  gender: '',
  profilePicture: null as File | null,
  course: '',
  section: '',
  employeeId: '',
  semester: '',
  year: '',
  username: '',
  password: '',
  confirmPassword: '',
  employmentType: '',
  qualification: '',
  permissions: {
    createExams: false,
    viewResults: false,
    manageResults: false,
    manageQuestions: false,
    gradeExams: false,
    generateReports: false,
  }
})
const showEditPassword = ref(false)
const showEditConfirmPassword = ref(false)

const serverStats = ref<{
  total: number
  full_time: number
  part_time: number
  on_leave: number
  new_this_semester: number
} | null>(null)

const openEditPage = (instructor: any) => {
  if (!instructor.can_edit) {
    alert('This instructor was created by Super Admin and cannot be edited by Department Head.')
    return
  }
  selectedInstructor.value = instructor
  editPhotoPreview.value = resolveAvatarUrl(instructor.profile_picture_url) || null
  editForm.value = {
    fullName: instructor.name || '',
    email: instructor.email || '',
    phone: instructor.phone || '+251 9XX XXX XXX',
    gender: instructor.gender || 'Male',
    profilePicture: null,
    course: instructor.course_code || 'CS-301',
    section: instructor.section || 'Section A',
    employeeId: instructor.id_code || '',
    semester: instructor.semester || 'Semester 1',
    year: instructor.year || '',
    username: instructor.username || (instructor.name ? instructor.name.toLowerCase().replace(' ', '.') : ''),
    password: '',
    confirmPassword: '',
    employmentType: (instructor.employment_type === 'part_time' || instructor.status === 'part time') ? 'Part Time' : 'Full Time',
    qualification: 'Faculty Instructor',
    permissions: {
      createExams: true,
      viewResults: true,
      manageResults: true,
      manageQuestions: true,
      gradeExams: true,
      generateReports: true,
    }
  }
  currentView.value = 'edit'
}

const handleEditFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    if (file.size > 2 * 1024 * 1024) {
      alert('Profile photo must be less than 2MB')
      return
    }
    editForm.value.profilePicture = file
    editPhotoPreview.value = URL.createObjectURL(file)
  }
}

const removeEditPhoto = (e?: Event) => {
  if (e) e.stopPropagation()
  editForm.value.profilePicture = null
  editPhotoPreview.value = null
  if (editFileInput.value) editFileInput.value.value = ''
}

const saveEditInstructor = async () => {
  if (!editForm.value.fullName || !editForm.value.email) return
  if (!selectedInstructor.value?.can_edit) {
    alert('This instructor was created by Super Admin and cannot be edited by Department Head.')
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
    if (editForm.value.year) formData.append('year_level', editForm.value.year)
    if (editForm.value.semester) formData.append('semester', editForm.value.semester)
    formData.append('employment_type', editForm.value.employmentType === 'Part Time' ? 'part_time' : 'full_time')
    if (editForm.value.password) formData.append('password', editForm.value.password)
    if (editForm.value.profilePicture) {
      formData.append('profile_picture', editForm.value.profilePicture)
    }

    await apiClient.post(`/dept-head/instructors/${selectedInstructor.value.id}`, formData)
    await fetchInstructors()
    currentView.value = 'list'
  } catch (err: any) {
    let msg = err.response?.data?.message || 'Failed to update instructor.'
    if (err.response?.data?.errors) msg += '\n' + Object.values(err.response.data.errors).flat().join('\n')
    alert(msg)
  } finally { isLoading.value = false }
}

// ── Fetch Department Info ──
const fetchDeptInfo = async () => {
  try {
    const res = await apiClient.get('/user')
    deptName.value = res.data?.department?.name || 'My Department'
  } catch { deptName.value = 'My Department' }
}

const years = ['2018', '2019', '2020', '2021', '2022', '2023', '2024']
// ── Fetch Instructors ──
const fetchInstructors = async () => {
  try {
    const res = await apiClient.get('/dept-head/instructors')
    if (res.data?.stats) {
      serverStats.value = res.data.stats
    }
    allInstructors.value = (res.data.data || []).map((i: any) => ({
      ...i,
      avatarUrl: resolveAvatarUrl(i.profile_picture_url),
      avatar: i.name ? i.name.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2) : '??',
      id_code: i.id_no || 'N/A',
      courses: i.assigned_courses?.length ? i.assigned_courses.map((c: any) => c.title).join(', ') : 'No Courses',
      coCourses: i.co_instructor_courses?.length ? i.co_instructor_courses.map((c: any) => c.title).join(', ') : 'None',
      credit: i.assigned_courses?.length ? i.assigned_courses.reduce((sum: number, c: any) => sum + (Number(c.credits) || 0), 0) : 0,
      year: i.year_level || 'N/A',
      section: i.section || 'N/A',
      status: i.status || 'active',
      employment_type: i.employment_type || 'full_time',
      can_edit: Boolean(i.can_edit),
      can_delete: Boolean(i.can_delete),
      is_admin_created: Boolean(i.is_admin_created),
      creator_name: i.creator_name || (i.is_admin_created ? 'Super Admin' : 'Department Head'),
      joined: i.created_at ? new Date(i.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : 'N/A',
    }))
  } catch (err) { console.error('Failed to fetch instructors:', err) }
}

// ── Export Handling ──
const showExportDropdown = ref(false)
const isExporting = ref(false)

const handleExport = async (format: 'pdf' | 'excel' | 'csv') => {
  showExportDropdown.value = false
  isExporting.value = true
  try {
    const params: Record<string, string> = { format }
    if (search.value) params.search = search.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (yearFilter.value !== 'all') params.year = yearFilter.value
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
  } catch (err: any) {
    console.error('Export error:', err)
    alert(err?.response?.data?.message || 'Failed to export instructors. Please try again.')
  } finally {
    isExporting.value = false
  }
}

onMounted(async () => {
  await fetchDeptInfo()
  await fetchInstructors()
  const handleOutsideClick = (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.export-dropdown-container')) {
      showExportDropdown.value = false
    }
  }
  window.addEventListener('click', handleOutsideClick)
})

// ── Computed ──
const filtered = computed(() => {
  return allInstructors.value.filter(i => {
    const matchSearch = i.name.toLowerCase().includes(search.value.toLowerCase()) ||
                        i.email.toLowerCase().includes(search.value.toLowerCase()) ||
                        i.id_code.toLowerCase().includes(search.value.toLowerCase())
    const matchStatus = statusFilter.value === 'all' || i.status === statusFilter.value
    const matchYear = yearFilter.value === 'all' || i.year === yearFilter.value
    const matchSection = sectionFilter.value === 'all' || i.section === sectionFilter.value
    return matchSearch && matchStatus && matchYear && matchSection
  })
})
const totalPages  = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated   = computed(() => filtered.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage))

const stats = computed(() => {
  const total = serverStats.value?.total ?? allInstructors.value.length
  const fullTime = serverStats.value?.full_time ?? allInstructors.value.filter(i => {
    const et = (i.employment_type || '').toLowerCase()
    const st = (i.status || '').toLowerCase()
    return (et === 'full_time' || et === 'full time' || (!et && st === 'active')) && st !== 'on_leave'
  }).length
  const partTime = serverStats.value?.part_time ?? allInstructors.value.filter(i => {
    const et = (i.employment_type || '').toLowerCase()
    const st = (i.status || '').toLowerCase()
    return et === 'part_time' || et === 'part time' || st === 'part time'
  }).length
  const onLeave = serverStats.value?.on_leave ?? allInstructors.value.filter(i => {
    const st = (i.status || '').toLowerCase()
    return st === 'on_leave' || st === 'on leave' || st === 'leave'
  }).length

  const newCount = serverStats.value?.new_this_semester ?? 0

  return [
    {
      label: 'Total Instructors',
      value: total,
      change: newCount > 0 ? `↑ ${newCount} this semester` : (total > 0 ? 'Active department staff' : 'No instructors'),
      bg: 'bg-indigo-50',
      ic: 'text-[#5138ed]',
      icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
      color: 'text-emerald-500'
    },
    {
      label: 'Full Time',
      value: fullTime,
      change: fullTime > 0 ? 'Full-time faculty' : 'No full-time staff',
      bg: 'bg-emerald-50',
      ic: 'text-emerald-500',
      icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
      color: 'text-emerald-500'
    },
    {
      label: 'Part Time',
      value: partTime,
      change: partTime > 0 ? 'Part-time faculty' : 'No part-time staff',
      bg: 'bg-sky-50',
      ic: 'text-sky-500',
      icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
      color: 'text-emerald-500'
    },
    {
      label: 'On Leave',
      value: onLeave,
      change: onLeave > 0 ? 'Currently on leave' : 'No change',
      bg: 'bg-amber-50',
      ic: 'text-amber-500',
      icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
      color: 'text-slate-400'
    }
  ]
})

// ── Helpers ──
const avatarColor = (id: number) => {
  const colors = ['bg-indigo-500','bg-sky-500','bg-emerald-500','bg-violet-500','bg-amber-500','bg-rose-500','bg-teal-500','bg-orange-500','bg-cyan-500','bg-purple-500']
  return colors[id % colors.length]
}
const statusBadge = (s: string) => s === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'

// ── Save Instructor ──
const saveInstructor = async () => {
  if (!validateAddForm()) return
  isLoading.value = true
  try {
    const formData = new FormData()
    formData.append('name', addForm.value.fullName.trim())
    formData.append('email', addForm.value.email.trim())
    formData.append('phone', addForm.value.phone.trim())
    formData.append('gender', addForm.value.gender)
    formData.append('id_no', addForm.value.employeeId.trim())
    if (addForm.value.yearLevel) {
      formData.append('year_level', addForm.value.yearLevel)
    }
    formData.append('username', addForm.value.username.trim())
    formData.append('password', addForm.value.password)
    formData.append('employment_type', 'full_time')
    formData.append('status', 'active')
    if (addForm.value.profilePicture) {
      formData.append('profile_picture', addForm.value.profilePicture)
    }

    await apiClient.post('/dept-head/instructors', formData)
    if (addPhotoPreview.value) {
      URL.revokeObjectURL(addPhotoPreview.value)
      addPhotoPreview.value = null
    }
    if (addFileInput.value) {
      addFileInput.value.value = ''
    }
    await fetchInstructors()
    currentView.value = 'list'
  } catch (err: any) {
    if (err.response?.data?.errors) {
      const serverErrors = err.response.data.errors
      if (serverErrors.name) addFormErrors.value.fullName = serverErrors.name[0]
      if (serverErrors.email) addFormErrors.value.email = serverErrors.email[0]
      if (serverErrors.phone) addFormErrors.value.phone = serverErrors.phone[0]
      if (serverErrors.gender) addFormErrors.value.gender = serverErrors.gender[0]
      if (serverErrors.id_no) addFormErrors.value.employeeId = serverErrors.id_no[0]
      if (serverErrors.year_level) addFormErrors.value.yearLevel = serverErrors.year_level[0]
      if (serverErrors.username) addFormErrors.value.username = serverErrors.username[0]
      if (serverErrors.password) addFormErrors.value.password = serverErrors.password[0]
      if (serverErrors.profile_picture) addFormErrors.value.profilePicture = serverErrors.profile_picture[0]

      const msg = Object.values(serverErrors).flat().join('\n')
      alert(msg)
    } else {
      const msg = err.response?.data?.message || 'Failed to create instructor.'
      alert(msg)
    }
  } finally {
    isLoading.value = false
  }
}

// ── Actions ──
const openView = (instructor: any) => { selectedInstructor.value = instructor; currentView.value = 'detail' }
const confirmDelete = (instructor: any) => {
  if (!instructor.can_delete) {
    alert('This instructor was created by Super Admin and cannot be deleted by Department Head.')
    return
  }
  selectedInstructor.value = instructor
  showDeleteModal.value = true
}
const deleteInstructor = async () => {
  if (!selectedInstructor.value) return
  if (!selectedInstructor.value.can_delete) {
    alert('This instructor was created by Super Admin and cannot be deleted by Department Head.')
    showDeleteModal.value = false
    return
  }
  isLoading.value = true
  try {
    await apiClient.delete(`/dept-head/instructors/${selectedInstructor.value.id}`)
    await fetchInstructors()
    showDeleteModal.value = false
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete instructor.')
  } finally { isLoading.value = false }
}

// Extract distinct years for dropdown
const uniqueYears = computed(() => {
  const set = new Set(allInstructors.value.map(i => i.year))
  return Array.from(set).filter(Boolean)
})

const uniqueSections = computed(() => {
  const set = new Set(allInstructors.value.map(i => i.section).filter(s => s && s !== 'N/A'))
  return Array.from(set).sort()
})
</script>

<template>
  <div class="space-y-6">

    <!-- ══════════════════════════ ADD INSTRUCTOR VIEW ══════════════════════════ -->
    <template v-if="currentView === 'add'">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Add Instructor</h1>
          <p class="text-xs sm:text-[13px] text-slate-500 mt-1">Create a new instructor profile. Fill in the details and assign to the course.</p>
        </div>
        <button @click="backToList" class="px-4 sm:px-5 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors flex items-center justify-center gap-2 min-h-[44px]">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Instructors
        </button>
      </div>

      <!-- Form Sections -->
      <div class="space-y-6">

        <!-- Personal Information -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <div class="flex items-center gap-3 mb-6 sm:mb-7">
            <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </div>
            <h2 class="text-[16px] font-bold text-slate-800">Personal Information</h2>
          </div>

          <div class="space-y-5">
            <!-- Row 1: Full Name | Email | Phone -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Full Name <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.fullName"
                  type="text"
                  placeholder="Enter full name"
                  @input="validateAddFormField('fullName')"
                  @blur="validateAddFormField('fullName')"
                  :class="addFormErrors.fullName ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]'"
                  class="w-full border rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:ring-1 placeholder:text-slate-400 transition-shadow"
                />
                <p v-if="addFormErrors.fullName" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.fullName }}</p>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Email Address <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.email"
                  type="email"
                  placeholder="Enter email address"
                  @input="validateAddFormField('email')"
                  @blur="validateAddFormField('email')"
                  :class="addFormErrors.email ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]'"
                  class="w-full border rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:ring-1 placeholder:text-slate-400 transition-shadow"
                />
                <p v-if="addFormErrors.email" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.email }}</p>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Phone Number <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.phone"
                  type="text"
                  placeholder="Enter phone number"
                  @input="validateAddFormField('phone')"
                  @blur="validateAddFormField('phone')"
                  :class="addFormErrors.phone ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]'"
                  class="w-full border rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:ring-1 placeholder:text-slate-400 transition-shadow"
                />
                <p v-if="addFormErrors.phone" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.phone }}</p>
              </div>
            </div>

            <!-- Row 2: Gender | Employee ID | Academic Year Level -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Gender <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select
                    v-model="addForm.gender"
                    @change="validateAddFormField('gender')"
                    @blur="validateAddFormField('gender')"
                    :class="addFormErrors.gender ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]'"
                    class="w-full border rounded-xl px-4 py-3 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:ring-1 transition-shadow"
                  >
                    <option value="">Select gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <p v-if="addFormErrors.gender" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.gender }}</p>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Employee ID <span class="text-rose-500">*</span></label>
                <input
                  v-model="addForm.employeeId"
                  type="text"
                  placeholder="Enter employee ID"
                  @input="validateAddFormField('employeeId')"
                  @blur="validateAddFormField('employeeId')"
                  :class="addFormErrors.employeeId ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]'"
                  class="w-full border rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:ring-1 placeholder:text-slate-400 transition-shadow"
                />
                <p v-if="addFormErrors.employeeId" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.employeeId }}</p>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Academic Year Level <span class="text-[11px] font-normal text-slate-400">(Optional)</span></label>
                <div class="relative">
                  <select v-model="addForm.yearLevel" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select year level (Optional)</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                    <option value="5th Year">5th Year</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
            </div>

            <!-- Row 3: Profile Picture -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="block text-[12px] font-semibold text-slate-700">Profile Picture <span class="text-[11px] font-normal text-slate-400">(Optional)</span></label>
                <button
                  v-if="addPhotoPreview"
                  type="button"
                  @click="removeAddPhoto"
                  class="text-[11px] font-bold text-rose-500 hover:text-rose-700 flex items-center gap-1 transition-colors cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  Remove Photo
                </button>
              </div>

              <div
                @click="triggerAddFileInput"
                :class="[
                  'relative flex items-center gap-4 p-4 border-2 border-dashed rounded-xl cursor-pointer transition-colors',
                  addFormErrors.profilePicture
                    ? 'border-rose-400 bg-rose-50/20'
                    : addPhotoPreview
                      ? 'border-indigo-300 bg-indigo-50/20 hover:border-[#5138ed]'
                      : 'border-slate-200 hover:border-[#5138ed] hover:bg-indigo-50/30'
                ]"
              >
                <input
                  ref="addFileInput"
                  type="file"
                  accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                  @change="handleFileUpload"
                  class="hidden"
                />

                <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-white shadow-xs shrink-0 flex items-center justify-center bg-slate-100">
                  <img
                    v-if="addPhotoPreview"
                    :src="addPhotoPreview"
                    alt="Photo Preview"
                    class="w-full h-full object-cover"
                  />
                  <svg
                    v-else
                    class="w-7 h-7 text-slate-300"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>

                <div class="flex-1 min-w-0">
                  <template v-if="addPhotoPreview">
                    <p class="text-[13px] font-bold text-slate-800 truncate">{{ addForm.profilePicture?.name || 'Photo Selected' }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Click to choose a different photo</p>
                  </template>
                  <template v-else>
                    <p class="text-[13px] font-bold text-slate-700">Upload Instructor Photo</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">PNG, JPG, WEBP up to 2MB (Optional)</p>
                  </template>
                </div>

                <span class="px-3.5 py-1.5 rounded-lg border border-slate-200 text-[12px] font-bold text-slate-600 bg-white shadow-xs">
                  {{ addPhotoPreview ? 'Change' : 'Browse' }}
                </span>
              </div>
              <p v-if="addFormErrors.profilePicture" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.profilePicture }}</p>
            </div>
          </div>
        </div>


        <!-- Account Information -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <div class="flex items-center gap-3 mb-6 sm:mb-7">
            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h2 class="text-[16px] font-bold text-slate-800">Account Information</h2>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            <div>
              <label class="block text-[12px] font-semibold text-slate-700 mb-2">Username <span class="text-rose-500">*</span></label>
              <input
                v-model="addForm.username"
                type="text"
                placeholder="Enter username"
                @input="validateAddFormField('username')"
                @blur="validateAddFormField('username')"
                :class="addFormErrors.username ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]'"
                class="w-full border rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:ring-1 placeholder:text-slate-400 transition-shadow"
              />
              <p v-if="addFormErrors.username" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.username }}</p>
            </div>
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="block text-[12px] font-semibold text-slate-700">Password <span class="text-rose-500">*</span></label>
                <span v-if="addForm.password" class="text-[11px] font-bold" :class="addPasswordStrength.textClass">
                  Strength: {{ addPasswordStrength.label }}
                </span>
              </div>
              <div class="relative">
                <input
                  v-model="addForm.password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Min 8 chars with Aa, 1, #"
                  @input="validateAddFormField('password')"
                  @blur="validateAddFormField('password')"
                  :class="[
                    'w-full border rounded-xl px-4 py-3 pr-11 text-[13px] text-slate-700 focus:outline-none focus:ring-1 placeholder:text-slate-400 transition-shadow',
                    addFormErrors.password
                      ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200'
                      : isAddPasswordValid
                        ? 'border-emerald-300 focus:border-emerald-500 focus:ring-emerald-500'
                        : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]'
                  ]"
                />
                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                </button>
              </div>
              <!-- Password Strength Bar -->
              <div v-if="addForm.password" class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
                <div class="h-full transition-all duration-300 rounded-full" :class="addPasswordStrength.barClass" :style="{ width: addPasswordStrength.width }"></div>
              </div>
              <p v-if="addFormErrors.password" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.password }}</p>
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-slate-700 mb-2">Confirm Password <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input
                  v-model="addForm.confirmPassword"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  placeholder="Confirm password"
                  @input="validateAddFormField('confirmPassword')"
                  @blur="validateAddFormField('confirmPassword')"
                  :class="addFormErrors.confirmPassword ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-200 focus:border-[#5138ed] focus:ring-[#5138ed]'"
                  class="w-full border rounded-xl px-4 py-3 pr-11 text-[13px] text-slate-700 focus:outline-none focus:ring-1 placeholder:text-slate-400 transition-shadow"
                />
                <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <svg v-if="!showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                </button>
              </div>
              <p v-if="addFormErrors.confirmPassword" class="text-rose-500 text-[11px] mt-1 font-medium">{{ addFormErrors.confirmPassword }}</p>
            </div>
          </div>

          <!-- Real-time Password Requirements Checklist -->
          <div class="mt-4 p-3.5 rounded-xl bg-slate-50 border border-slate-100 space-y-2">
            <p class="text-[11px] font-bold text-slate-600 flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
              Password Requirements:
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div class="flex items-center gap-1.5 text-[11px] transition-colors" :class="addPasswordRules.minLength ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="addPasswordRules.minLength" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                </svg>
                <span>At least 8 characters</span>
              </div>
              <div class="flex items-center gap-1.5 text-[11px] transition-colors" :class="addPasswordRules.uppercase && addPasswordRules.lowercase ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="addPasswordRules.uppercase && addPasswordRules.lowercase" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                </svg>
                <span>Uppercase & lowercase</span>
              </div>
              <div class="flex items-center gap-1.5 text-[11px] transition-colors" :class="addPasswordRules.number ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="addPasswordRules.number" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                </svg>
                <span>At least one number (0-9)</span>
              </div>
              <div class="flex items-center gap-1.5 text-[11px] transition-colors" :class="addPasswordRules.special ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="addPasswordRules.special" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                </svg>
                <span>Special char (!@#$%^&*)</span>
              </div>
            </div>
          </div>
        </div>


        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center justify-end gap-3 sm:gap-4 pb-4">
          <button @click="backToList" class="px-5 sm:px-6 py-2.5 sm:py-3 min-h-[44px] text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
          <button @click="saveInstructor" :disabled="isLoading" class="flex items-center justify-center gap-2 px-5 sm:px-6 py-2.5 sm:py-3 min-h-[44px] text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all disabled:opacity-70 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            {{ isLoading ? 'Saving...' : 'Save Instructor' }}
          </button>
        </div>

      </div>
    </template>

    <!-- ══════════════════════════ EDIT INSTRUCTOR VIEW ══════════════════════════ -->
    <template v-else-if="currentView === 'edit' && selectedInstructor">

      <!-- Header -->
      <div class="flex items-start gap-3 sm:gap-4">
        <button @click="backToList" class="mt-1 w-9 h-9 rounded-xl flex items-center justify-center border border-slate-200 text-slate-500 hover:bg-slate-50 hover:border-slate-300 hover:text-slate-700 transition-all shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Edit Instructor</h1>
          <p class="text-xs sm:text-[13px] text-slate-500 mt-1">Update the instructor profile for <span class="font-bold text-slate-700">{{ selectedInstructor.name }}</span>.</p>
        </div>
      </div>

      <!-- Form Sections -->
      <div class="space-y-6">

        <!-- Personal Information -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <div class="flex items-center gap-3 mb-6 sm:mb-7">
            <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </div>
            <h2 class="text-[16px] font-bold text-slate-800">Personal Information</h2>
          </div>

          <div class="space-y-5">
            <!-- Row 1: Full Name | Email | Phone -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Full Name <span class="text-rose-500">*</span></label>
                <input v-model="editForm.fullName" type="text" placeholder="Enter full name" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Email Address <span class="text-rose-500">*</span></label>
                <input v-model="editForm.email" type="email" placeholder="Enter email address" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Phone Number <span class="text-rose-500">*</span></label>
                <input v-model="editForm.phone" type="text" placeholder="Enter phone number" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
            </div>

            <!-- Row 2: Gender | Profile Picture -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Gender</label>
                <div class="relative">
                  <select v-model="editForm.gender" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
              <div class="sm:col-span-2">
                <div class="flex items-center justify-between mb-2">
                  <label class="block text-[12px] font-semibold text-slate-700">Profile Picture <span class="text-[11px] font-normal text-slate-400">(Optional)</span></label>
                  <button
                    v-if="editPhotoPreview"
                    type="button"
                    @click="removeEditPhoto"
                    class="text-[11px] font-bold text-rose-500 hover:text-rose-700 flex items-center gap-1 transition-colors cursor-pointer"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Remove Photo
                  </button>
                </div>
                <div
                  @click="triggerEditFileInput"
                  class="relative flex items-center gap-4 p-4 border-2 border-dashed border-slate-200 hover:border-[#5138ed] hover:bg-indigo-50/20 rounded-xl cursor-pointer transition-colors"
                >
                  <input
                    ref="editFileInput"
                    type="file"
                    accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                    @change="handleEditFileUpload"
                    class="hidden"
                  />
                  <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-white shadow-xs shrink-0 flex items-center justify-center bg-slate-100">
                    <img
                      v-if="editPhotoPreview"
                      :src="editPhotoPreview"
                      alt="Photo Preview"
                      class="w-full h-full object-cover"
                    />
                    <svg
                      v-else
                      class="w-7 h-7 text-slate-300"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <template v-if="editPhotoPreview">
                      <p class="text-[13px] font-bold text-slate-800 truncate">{{ editForm.profilePicture?.name || 'Current Profile Photo' }}</p>
                      <p class="text-[11px] text-slate-400 mt-0.5">Click to choose a new photo</p>
                    </template>
                    <template v-else>
                      <p class="text-[13px] font-bold text-slate-700">Upload New Photo</p>
                      <p class="text-[11px] text-slate-400 mt-0.5">PNG, JPG, WEBP up to 2MB (Optional)</p>
                    </template>
                  </div>
                  <span class="px-3.5 py-1.5 rounded-lg border border-slate-200 text-[12px] font-bold text-slate-600 bg-white shadow-xs">
                    {{ editPhotoPreview ? 'Change' : 'Browse' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Professional Information -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <div class="flex items-center gap-3 mb-6 sm:mb-7">
            <div class="w-10 h-10 bg-sky-50 rounded-xl flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            </div>
            <h2 class="text-[16px] font-bold text-slate-800">Professional Information</h2>
          </div>

          <div class="space-y-5">
            <!-- Row 1: Course | Section | Employee ID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Course <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="editForm.course" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select course</option>
                    <option value="CS-101">CS-101 Introduction to Programming</option>
                    <option value="CS-201">CS-201 Data Structures</option>
                    <option value="CS-301">CS-301 Algorithms</option>
                    <option value="CS-401">CS-401 Database Systems</option>
                    <option value="CS-501">CS-501 Software Engineering</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Section <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="editForm.section" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select section</option>
                    <option value="Section A">Section A</option>
                    <option value="Section B">Section B</option>
                    <option value="Section C">Section C</option>
                    <option value="Section D">Section D</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Employee ID</label>
                <input v-model="editForm.employeeId" type="text" placeholder="Enter employee ID" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
            </div>

            <!-- Row 2: Semester | Year | Employment Type -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Semester</label>
                <div class="relative">
                  <select v-model="editForm.semester" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select semester</option>
                    <option value="Semester 1">Semester 1</option>
                    <option value="Semester 2">Semester 2</option>
                    <option value="Summer">Summer</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Year</label>
                <div class="relative">
                  <select v-model="editForm.year" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select year</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                    <option value="5th Year">5th Year</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Employment Type</label>
                <div class="relative">
                  <select v-model="editForm.employmentType" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select type</option>
                    <option value="Full Time">Full Time</option>
                    <option value="Part Time">Part Time</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
            </div>

            <!-- Row 3: Qualification -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Qualification</label>
                <input v-model="editForm.qualification" type="text" placeholder="Enter qualification" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
            </div>
          </div>
        </div>

        <!-- Account Information -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <div class="flex items-center gap-3 mb-6 sm:mb-7">
            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h2 class="text-[16px] font-bold text-slate-800">Account Information</h2>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            <div>
              <label class="block text-[12px] font-semibold text-slate-700 mb-2">Username <span class="text-rose-500">*</span></label>
              <input v-model="editForm.username" type="text" placeholder="Enter username" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-slate-700 mb-2">New Password <span class="text-[11px] text-slate-400 font-normal">(leave blank to keep current)</span></label>
              <div class="relative">
                <input v-model="editForm.password" :type="showEditPassword ? 'text' : 'password'" placeholder="Enter new password" class="w-full border border-slate-200 rounded-xl px-4 py-3 pr-11 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
                <button type="button" @click="showEditPassword = !showEditPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <svg v-if="!showEditPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-[12px] font-semibold text-slate-700 mb-2">Confirm New Password</label>
              <div class="relative">
                <input v-model="editForm.confirmPassword" :type="showEditConfirmPassword ? 'text' : 'password'" placeholder="Confirm new password" class="w-full border border-slate-200 rounded-xl px-4 py-3 pr-11 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
                <button type="button" @click="showEditConfirmPassword = !showEditConfirmPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <svg v-if="!showEditConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Instructor Permissions -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <div class="flex items-center gap-3 mb-6 sm:mb-7">
            <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <h2 class="text-[16px] font-bold text-slate-800">Instructor Permissions</h2>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5">
            <label class="flex items-start gap-3 p-4 border border-slate-100 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/20 transition-colors cursor-pointer group">
              <input type="checkbox" v-model="editForm.permissions.createExams" class="mt-0.5 w-4 h-4 accent-[#5138ed] rounded cursor-pointer" />
              <div>
                <p class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Create Exams</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Create and manage exams</p>
              </div>
            </label>
            <label class="flex items-start gap-3 p-4 border border-slate-100 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/20 transition-colors cursor-pointer group">
              <input type="checkbox" v-model="editForm.permissions.viewResults" class="mt-0.5 w-4 h-4 accent-[#5138ed] rounded cursor-pointer" />
              <div>
                <p class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">View &amp; Results</p>
                <p class="text-[11px] text-slate-400 mt-0.5">View student results and analytics</p>
              </div>
            </label>
            <label class="flex items-start gap-3 p-4 border border-slate-100 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/20 transition-colors cursor-pointer group">
              <input type="checkbox" v-model="editForm.permissions.manageResults" class="mt-0.5 w-4 h-4 accent-[#5138ed] rounded cursor-pointer" />
              <div>
                <p class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Manage Results</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Create and manage results</p>
              </div>
            </label>
            <label class="flex items-start gap-3 p-4 border border-slate-100 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/20 transition-colors cursor-pointer group">
              <input type="checkbox" v-model="editForm.permissions.manageQuestions" class="mt-0.5 w-4 h-4 accent-[#5138ed] rounded cursor-pointer" />
              <div>
                <p class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Manage Questions</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Add, edit and manage questions</p>
              </div>
            </label>
            <label class="flex items-start gap-3 p-4 border border-slate-100 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/20 transition-colors cursor-pointer group">
              <input type="checkbox" v-model="editForm.permissions.gradeExams" class="mt-0.5 w-4 h-4 accent-[#5138ed] rounded cursor-pointer" />
              <div>
                <p class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Grade Exams</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Grade student submissions</p>
              </div>
            </label>
            <label class="flex items-start gap-3 p-4 border border-slate-100 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/20 transition-colors cursor-pointer group">
              <input type="checkbox" v-model="editForm.permissions.generateReports" class="mt-0.5 w-4 h-4 accent-[#5138ed] rounded cursor-pointer" />
              <div>
                <p class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">Generate Reports</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Create and export reports</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center justify-end gap-3 sm:gap-4 pb-4">
          <button @click="backToList" class="px-5 sm:px-6 py-2.5 sm:py-3 min-h-[44px] text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
          <button @click="saveEditInstructor" :disabled="isLoading" class="flex items-center justify-center gap-2 px-5 sm:px-6 py-2.5 sm:py-3 min-h-[44px] text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all disabled:opacity-70 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ isLoading ? 'Saving...' : 'Update Instructor' }}
          </button>
        </div>

      </div>
    </template>

    <!-- ══════════════════════════ DETAIL VIEW ══════════════════════════ -->
    <template v-else-if="currentView === 'detail' && selectedInstructor">
      <div class="space-y-6">
        <!-- Breadcrumbs & Header -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 text-[13px] font-medium text-[#5138ed] mb-2">
              <span class="cursor-pointer hover:underline" @click="backToList">Instructors</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              <span class="text-slate-600">Instructor Details</span>
            </div>
            <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Instructor Details</h1>
            <p class="text-xs sm:text-[13px] text-slate-500 mt-1">View complete information about the instructor.</p>
          </div>
          <div class="flex flex-col items-start sm:items-end gap-3">
            <div class="flex items-center gap-2 text-[12px] font-semibold text-slate-500">
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
              May 27, 2026 <span class="text-slate-400 font-normal">Tuesday</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button v-if="selectedInstructor.can_edit" @click="openEditPage(selectedInstructor)" class="flex items-center gap-2 text-[12px] font-bold text-sky-600 bg-sky-50 border border-sky-200 hover:bg-sky-100 px-4 py-2.5 min-h-[44px] rounded-xl transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                Edit Instructor
              </button>
              <button @click="backToList" class="flex items-center gap-2 text-[12px] font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 px-4 py-2.5 min-h-[44px] rounded-xl transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Instructors
              </button>
            </div>
          </div>
        </div>

        <!-- Top Profile Card -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-7 md:p-8 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 sm:gap-6">
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-slate-100 border-4 border-white shadow-md overflow-hidden flex items-center justify-center shrink-0">
              <img
                v-if="selectedInstructor.profile_picture_url"
                :src="resolveAvatarUrl(selectedInstructor.profile_picture_url) || ''"
                :alt="selectedInstructor.name"
                class="w-full h-full object-cover"
                @error="(e: any) => { e.target.style.display = 'none'; (e.target.nextElementSibling as HTMLElement)?.classList.remove('hidden') }"
              />
              <div
                :class="[avatarColor(selectedInstructor.id), 'w-full h-full flex items-center justify-center text-[28px] sm:text-[32px] font-bold text-white', selectedInstructor.profile_picture_url ? 'hidden' : '']"
              >
                {{ selectedInstructor.avatar }}
              </div>
            </div>
            <div>
              <h2 class="text-lg sm:text-[20px] font-bold text-slate-800">{{ selectedInstructor.name }}</h2>
              <p class="text-[13px] font-semibold text-slate-500 mt-1 mb-3">{{ selectedInstructor.id_code }}</p>
              <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-[13px] text-slate-500 font-medium">
                <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg> <span class="break-all">{{ selectedInstructor.email }}</span></div>
                <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg> {{ selectedInstructor.phone }}</div>
              </div>
              <div class="mt-4 flex flex-wrap items-center gap-2">
                <div class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold capitalize bg-emerald-50 text-emerald-600 gap-1.5 border border-emerald-100">
                  <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                  {{ selectedInstructor.status }}
                </div>
                <div :class="[selectedInstructor.is_admin_created ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-indigo-50 text-[#5138ed] border-indigo-200', 'inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-semibold border']">
                  {{ selectedInstructor.is_admin_created ? 'Created by Super Admin (View only)' : 'Created by Department Head' }}
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-around w-full md:w-auto gap-6 sm:gap-12 pt-4 md:pt-0 border-t md:border-t-0 border-slate-100 pr-0 md:pr-6">
            <div class="text-center">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center mx-auto mb-3">
                <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
              </div>
              <p class="text-lg sm:text-[20px] font-bold text-slate-800">{{ selectedInstructor.courses }}</p>
              <p class="text-[11px] font-semibold text-slate-500 leading-tight">Courses<br>Assigned</p>
            </div>

            <div class="text-center">
              <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center mx-auto mb-3">
                <svg class="w-5 h-5 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
              </div>
              <p class="text-[15px] sm:text-[16px] font-bold text-slate-800 capitalize">{{ selectedInstructor.status === 'active' ? 'Full Time' : 'Part Time' }}</p>
              <p class="text-[11px] font-semibold text-slate-500 leading-tight">Employment<br>Status</p>
            </div>
          </div>
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
          <!-- Personal Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
              <h3 class="text-[15px] font-bold text-slate-800">Personal Information</h3>
            </div>
            <div class="space-y-4">
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Full Name</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">{{ selectedInstructor.name }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Email Address</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right break-all">{{ selectedInstructor.email }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Phone Number</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">+251 9XX XXX XXX</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Gender</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">Male</span>
              </div>
              <div class="flex justify-between items-center mt-2">
                <span class="text-[12px] font-semibold text-slate-500">Profile Picture</span>
                <div class="w-10 h-10 rounded-lg bg-indigo-50 overflow-hidden flex items-center justify-center">
                  <div :class="[avatarColor(selectedInstructor.id), 'w-full h-full flex items-center justify-center text-[12px] font-bold text-white']">{{ selectedInstructor.avatar }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Professional Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
              <h3 class="text-[15px] font-bold text-slate-800">Professional Information</h3>
            </div>
            <div class="space-y-4">
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Courses Taught</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">{{ selectedInstructor.courses }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Courses Taught (Co-Instructor)</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">{{ selectedInstructor.coCourses }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Section</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">{{ selectedInstructor.section }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Employee ID</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">{{ selectedInstructor.id_code }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Semester</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">--</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Year Level</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">{{ selectedInstructor.year }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Employment Type</span>
                <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 capitalize text-right">{{ selectedInstructor.status === 'active' ? 'Full Time' : 'Part Time' }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] font-semibold text-slate-500">Qualification</span>
                <span class="text-[12.5px] font-bold text-slate-800 text-right">PhD in Computer Science</span>
              </div>
            </div>
          </div>

          <!-- Account & Permissions -->
          <div class="flex flex-col gap-6 md:col-span-2 lg:col-span-1">
            <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-sm flex-1">
              <div class="flex items-center gap-3 mb-6">
                <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <h3 class="text-[15px] font-bold text-slate-800">Account Information</h3>
              </div>
              <div class="space-y-4">
                <div class="flex justify-between items-start gap-2">
                  <span class="text-[12px] font-semibold text-slate-500">Username</span>
                  <span class="text-[12.5px] font-bold text-slate-800 text-right break-all">{{ selectedInstructor.name.toLowerCase().replace(' ', '.') }}</span>
                </div>
                <div class="flex justify-between items-start gap-2">
                  <span class="text-[12px] font-semibold text-slate-500">Account Status</span>
                  <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 text-right capitalize">{{ selectedInstructor.status }}</span>
                </div>
                <div class="flex justify-between items-start gap-2">
                  <span class="text-[12px] font-semibold text-slate-500">Member Since</span>
                  <span class="text-[12.5px] font-bold text-slate-800 text-right">{{ selectedInstructor.joined }}</span>
                </div>
                <div class="flex justify-between items-start gap-2">
                  <span class="text-[12px] font-semibold text-slate-500">Last Updated</span>
                  <span class="text-[12.5px] font-bold text-slate-800 text-right">May 27, 2026</span>
                </div>
              </div>
            </div>
            
            <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-sm flex-1">
              <div class="flex items-center gap-3 mb-5">
                <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                <h3 class="text-[15px] font-bold text-slate-800">Permissions</h3>
              </div>
              <div class="grid grid-cols-2 gap-y-3 gap-x-2">
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg><span class="text-[12px] font-bold text-slate-600">Create Exams</span></div>
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg><span class="text-[12px] font-bold text-slate-600">Manage Results</span></div>
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg><span class="text-[12px] font-bold text-slate-600">Manage Questions</span></div>
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg><span class="text-[12px] font-bold text-slate-600">Grade Students</span></div>
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg><span class="text-[12px] font-bold text-slate-600">View & Results</span></div>
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg><span class="text-[12px] font-bold text-slate-600">Generate Reports</span></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Course Assignments Table -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden mt-6">
          <div class="flex items-center gap-3 px-5 sm:px-6 py-4 sm:py-5 border-b border-slate-100">
            <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            <h3 class="text-[15px] font-bold text-slate-800">Course Assignments (4)</h3>
          </div>
          <div class="overflow-x-auto min-w-0 w-full">
            <table class="w-full min-w-[550px]">
              <thead>
                <tr class="bg-white border-b border-slate-100">
                  <th class="text-left px-5 sm:px-6 py-3.5 sm:py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course Code</th>
                  <th class="text-left px-5 sm:px-6 py-3.5 sm:py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course Title</th>
                  <th class="text-left px-5 sm:px-6 py-3.5 sm:py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Section</th>
                  <th class="text-left px-5 sm:px-6 py-3.5 sm:py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Semester</th>
                  <th class="text-left px-5 sm:px-6 py-3.5 sm:py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr class="hover:bg-slate-50/40 transition-colors">
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-semibold text-slate-700">CS-301</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Data Structures</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Section A</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Spring 2026</td>
                  <td class="px-5 sm:px-6 py-4"><span class="text-[11px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-600">Active</span></td>
                </tr>
                <tr class="hover:bg-slate-50/40 transition-colors">
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-semibold text-slate-700">CS-302</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Algorithms</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Section B</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Spring 2026</td>
                  <td class="px-5 sm:px-6 py-4"><span class="text-[11px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-600">Active</span></td>
                </tr>
                <tr class="hover:bg-slate-50/40 transition-colors">
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-semibold text-slate-700">CS-401</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Database Systems</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Section A</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Spring 2026</td>
                  <td class="px-5 sm:px-6 py-4"><span class="text-[11px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-600">Active</span></td>
                </tr>
                <tr class="hover:bg-slate-50/40 transition-colors">
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-semibold text-slate-700">CS-402</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Software Engineering</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Section C</td>
                  <td class="px-5 sm:px-6 py-4 text-[12.5px] font-medium text-slate-600">Spring 2026</td>
                  <td class="px-5 sm:px-6 py-4"><span class="text-[11px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-600">Active</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <!-- ══════════════════════════ LIST VIEW ══════════════════════════ -->
    <template v-else>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Instructors</h1>
          <p class="text-xs sm:text-[13px] text-slate-500 mt-1">Manage and monitor department instructors.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button @click="openAddPage" class="flex items-center gap-2 bg-[#5138ed] hover:bg-indigo-700 text-white text-[13px] font-bold px-4 py-2.5 min-h-[44px] rounded-xl shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Instructor
          </button>
          <div class="relative export-dropdown-container">
            <button
              type="button"
              @click="showExportDropdown = !showExportDropdown"
              :disabled="isExporting"
              class="flex items-center gap-2 bg-white border border-slate-200 hover:border-[#5138ed] hover:text-[#5138ed] text-slate-600 text-[13px] font-bold px-4 py-2.5 min-h-[44px] rounded-xl shadow-xs transition-all cursor-pointer disabled:opacity-60"
            >
              <svg v-if="!isExporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
              <svg v-else class="animate-spin w-4 h-4 text-[#5138ed]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <span>{{ isExporting ? 'Exporting...' : 'Export' }}</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>

            <!-- Export Options Dropdown -->
            <div
              v-if="showExportDropdown"
              class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-50 overflow-hidden"
            >
              <div class="px-3 py-1.5 border-b border-slate-100 mb-1">
                <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Export Format</p>
              </div>
              <button
                type="button"
                @click="handleExport('pdf')"
                class="w-full text-left px-3.5 py-2 text-[12.5px] font-medium text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors flex items-center gap-2.5 cursor-pointer"
              >
                <div class="w-7 h-7 rounded-lg bg-rose-50 flex items-center justify-center text-rose-500 shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                  <p class="font-bold leading-tight">Export as PDF</p>
                  <p class="text-[10px] text-slate-400">Printable document (.pdf)</p>
                </div>
              </button>
              <button
                type="button"
                @click="handleExport('excel')"
                class="w-full text-left px-3.5 py-2 text-[12.5px] font-medium text-slate-700 hover:bg-emerald-50/70 hover:text-emerald-600 transition-colors flex items-center gap-2.5 cursor-pointer"
              >
                <div class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div>
                  <p class="font-bold leading-tight">Export as Excel</p>
                  <p class="text-[10px] text-slate-400">Spreadsheet file (.xlsx)</p>
                </div>
              </button>
              <button
                type="button"
                @click="handleExport('csv')"
                class="w-full text-left px-3.5 py-2 text-[12.5px] font-medium text-slate-700 hover:bg-sky-50/70 hover:text-sky-600 transition-colors flex items-center gap-2.5 cursor-pointer"
              >
                <div class="w-7 h-7 rounded-lg bg-sky-50 flex items-center justify-center text-sky-500 shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                </div>
                <div>
                  <p class="font-bold leading-tight">Export as CSV</p>
                  <p class="text-[10px] text-slate-400">Comma-separated (.csv)</p>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- KPIs -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div v-for="s in stats" :key="s.label" class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6 flex items-center gap-4 sm:gap-5 hover:shadow-md transition-shadow">
          <div :class="[s.bg, 'w-12 h-12 sm:w-14 sm:h-14 rounded-2xl flex items-center justify-center shrink-0']">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" :class="s.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="s.icon"></path></svg>
          </div>
          <div>
            <p class="text-[12px] font-semibold text-slate-500">{{ s.label }}</p>
            <p class="text-xl sm:text-[24px] font-bold text-slate-800 leading-tight mt-0.5">{{ s.value }}</p>
            <p :class="[s.color, 'text-[11px] font-bold mt-1']">{{ s.change }}</p>
          </div>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
        <!-- Filters -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 px-4 sm:px-6 py-4 border-b border-slate-100">
          <div class="relative w-full lg:max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input v-model="search" type="text" placeholder="Search instructors by name, email or ID..." class="w-full pl-9 pr-4 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]">
          </div>
          <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
            <div class="relative flex-1 sm:flex-initial min-w-[120px]">
              <select v-model="statusFilter" class="w-full text-[13px] font-medium border border-slate-200 rounded-xl pl-4 pr-8 py-2.5 focus:outline-none focus:border-[#5138ed] text-slate-600 bg-white appearance-none">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="part time">Part Time</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div class="relative flex-1 sm:flex-initial min-w-[110px]">
              <select v-model="yearFilter" class="w-full text-[13px] font-medium border border-slate-200 rounded-xl pl-4 pr-8 py-2.5 focus:outline-none focus:border-[#5138ed] text-slate-600 bg-white appearance-none">
                <option value="all">All Years</option>
                <option v-for="y in uniqueYears" :key="y" :value="y">{{ y }}</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div class="relative flex-1 sm:flex-initial min-w-[120px]">
              <select v-model="sectionFilter" class="w-full text-[13px] font-medium border border-slate-200 rounded-xl pl-4 pr-8 py-2.5 focus:outline-none focus:border-[#5138ed] text-slate-600 bg-white appearance-none">
                <option value="all">All Sections</option>
                <option v-for="s in uniqueSections" :key="s" :value="s">{{ s }}</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <button class="flex items-center justify-center gap-2 text-[13px] font-bold text-[#5138ed] border border-indigo-100 hover:bg-indigo-50 px-4 py-2.5 min-h-[42px] rounded-xl transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
              Filter
            </button>
          </div>
        </div>

        <!-- Desktop TABLE -->
        <div class="hidden md:block overflow-x-auto min-w-0 w-full">
          <table class="w-full min-w-[700px]">
            <thead>
              <tr class="bg-white border-b border-slate-100">
                <th class="text-left px-5 lg:px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Instructor</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">ID</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Email</th>
                <th class="text-center px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Courses</th>
                <th class="text-center px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Credit</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Year</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Section</th>
                <th class="text-center px-5 lg:px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="inst in paginated" :key="inst.id" class="hover:bg-slate-50/40 transition-colors group">
                <td class="px-5 lg:px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 border border-slate-200/80 shadow-xs flex items-center justify-center bg-slate-100">
                      <img
                        v-if="inst.avatarUrl"
                        :src="inst.avatarUrl"
                        :alt="inst.name"
                        class="w-full h-full object-cover"
                        @error="(e: any) => { e.target.style.display = 'none'; (e.target.nextElementSibling as HTMLElement)?.classList.remove('hidden') }"
                      />
                      <div
                        :class="[avatarColor(inst.id), 'w-full h-full flex items-center justify-center text-[12px] font-bold text-white', inst.avatarUrl ? 'hidden' : '']"
                      >
                        {{ inst.avatar }}
                      </div>
                    </div>
                    <div>
                      <p class="text-[13px] font-bold text-slate-800">{{ inst.name }}</p>
                      <p class="text-[11px] font-medium text-slate-400 mt-0.5">{{ inst.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] font-semibold text-slate-600">{{ inst.id_code }}</span></td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] font-medium text-slate-600 truncate max-w-[150px] block">{{ inst.email }}</span></td>
                <td class="px-3 lg:px-4 py-4 text-center"><span class="text-[13px] font-semibold text-slate-700">{{ inst.courses }}</span></td>
                <td class="px-3 lg:px-4 py-4 text-center"><span class="text-[13px] font-semibold text-slate-700">{{ inst.credit }}</span></td>
                <td class="px-3 lg:px-4 py-4">
                  <span :class="[statusBadge(inst.status), 'text-[11px] font-bold px-2.5 py-1 rounded-md capitalize']">{{ inst.status === 'active' ? 'Active' : 'Part Time' }}</span>
                </td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] text-slate-600">{{ inst.year }}</span></td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] text-slate-600">{{ inst.section }}</span></td>
                <td class="px-5 lg:px-6 py-4">
                  <div class="flex items-center justify-center gap-2">
                    <!-- View Details: ALWAYS available -->
                    <button @click="openView(inst)" title="View Details" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    </button>

                    <!-- Edit & Delete: ONLY available if instructor was created by Department Head -->
                    <template v-if="inst.can_edit">
                      <button @click="openEditPage(inst)" title="Edit Instructor" class="w-8 h-8 rounded-lg flex items-center justify-center text-sky-500 bg-sky-50 hover:bg-sky-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                      </button>
                      <button @click="confirmDelete(inst)" title="Delete Instructor" class="w-8 h-8 rounded-lg flex items-center justify-center text-rose-500 bg-rose-50 hover:bg-rose-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                      </button>
                    </template>

                    <!-- If created by Super Admin: Cannot be edited or deleted by Department Head -->
                    <template v-else>
                      <span class="text-[10.5px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded border border-slate-200 select-none" title="Created by Super Admin. Only Super Admin can edit or delete this instructor.">
                        Super Admin
                      </span>
                    </template>
                  </div>
                </td>
              </tr>
              <tr v-if="paginated.length === 0">
                <td colspan="9" class="px-6 py-12 text-center text-[13px] text-slate-400">No instructors found. Click "Add Instructor" to create one.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-for="inst in paginated" :key="inst.id" class="p-4 hover:bg-slate-50/60 transition-colors space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-full overflow-hidden shrink-0 border border-slate-200/80 shadow-xs flex items-center justify-center bg-slate-100">
                  <img
                    v-if="inst.avatarUrl"
                    :src="inst.avatarUrl"
                    :alt="inst.name"
                    class="w-full h-full object-cover"
                    @error="(e: any) => { e.target.style.display = 'none'; (e.target.nextElementSibling as HTMLElement)?.classList.remove('hidden') }"
                  />
                  <div
                    :class="[avatarColor(inst.id), 'w-full h-full flex items-center justify-center text-[13px] font-bold text-white', inst.avatarUrl ? 'hidden' : '']"
                  >
                    {{ inst.avatar }}
                  </div>
                </div>
                <div class="min-w-0">
                  <p class="text-[14px] font-bold text-slate-800 truncate">{{ inst.name }}</p>
                  <p class="text-[11px] font-medium text-slate-400 truncate">{{ inst.email }}</p>
                  <p class="text-[11px] font-semibold text-slate-500 mt-0.5">{{ inst.id_code }}</p>
                </div>
              </div>
              <span :class="[statusBadge(inst.status), 'text-[10px] font-bold px-2 py-0.5 rounded capitalize shrink-0']">
                {{ inst.status === 'active' ? 'Active' : 'Part Time' }}
              </span>
            </div>

            <!-- Details chips -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] bg-slate-50/80 rounded-xl p-2.5 border border-slate-100">
              <div>
                <span class="text-slate-400 block text-[10px]">Courses</span>
                <span class="font-bold text-slate-700">{{ inst.courses }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px]">Credits</span>
                <span class="font-bold text-slate-700">{{ inst.credit }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px]">Year Level</span>
                <span class="font-bold text-slate-700">{{ inst.year || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px]">Section</span>
                <span class="font-bold text-slate-700">{{ inst.section || 'N/A' }}</span>
              </div>
            </div>

            <!-- Mobile Actions -->
            <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
              <button @click="openView(inst)" class="flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-lg min-h-[38px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Details
              </button>
              <template v-if="inst.can_edit">
                <button @click="openEditPage(inst)" class="flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-bold text-sky-600 bg-sky-50 hover:bg-sky-100 rounded-lg min-h-[38px] transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  Edit
                </button>
                <button @click="confirmDelete(inst)" class="flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg min-h-[38px] transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  Delete
                </button>
              </template>
              <template v-else>
                <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-2.5 py-1.5 rounded-lg border border-slate-200">
                  Super Admin
                </span>
              </template>
            </div>
          </div>
          <div v-if="paginated.length === 0" class="p-8 text-center text-[13px] text-slate-400">
            No instructors found. Click "Add Instructor" to create one.
          </div>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-4 sm:px-6 py-4 sm:py-5 border-t border-slate-100 bg-white">
          <p class="text-xs sm:text-[13px] text-slate-500 font-medium text-center sm:text-left">Showing {{ Math.min((currentPage - 1) * perPage + 1, filtered.length) }} to {{ Math.min(currentPage * perPage, filtered.length) }} of {{ filtered.length }} instructors</p>
          <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap justify-center">
            <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
            <button v-for="p in totalPages" :key="p" @click="currentPage = p" :class="[currentPage === p ? 'bg-[#5138ed] text-white border border-[#5138ed]' : 'text-slate-500 border border-slate-200 hover:bg-slate-50', 'w-8 h-8 rounded-lg text-[13px] font-bold transition-colors']">{{ p }}</button>
            <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
          </div>
        </div>
      </div>

      <!-- ── Delete Modal ── -->
      <Teleport to="body">
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-sm overflow-hidden text-center p-5 sm:p-6">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4"><svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg></div>
            <h3 class="text-[16px] font-bold text-slate-800 mb-2">Remove Instructor?</h3>
            <p class="text-[13px] text-slate-500 mb-6">Are you sure you want to remove <span class="font-bold text-slate-700">{{ selectedInstructor?.name }}</span>?</p>
            <div class="flex gap-3">
              <button @click="showDeleteModal = false" class="flex-1 py-2.5 min-h-[44px] text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
              <button @click="deleteInstructor" :disabled="isLoading" class="flex-1 py-2.5 min-h-[44px] text-[13px] font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-xl transition-colors disabled:opacity-70">
                {{ isLoading ? 'Removing...' : 'Remove' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

    </template>

  </div>
</template>
