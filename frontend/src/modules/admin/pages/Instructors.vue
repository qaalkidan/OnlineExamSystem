<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useSettingsStore } from '../../../store/settingsStore'

const router = useRouter()
const settingsStore = useSettingsStore()
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Doughnut } from 'vue-chartjs'

ChartJS.register(ArcElement, Tooltip, Legend)

const scrollToTop = () => {
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

// ── State ──
const search = ref('')
const deptFilter = ref('all')
const statusFilter = ref('all')
const sectionFilter = ref('all')
const yearFilter = ref('all')
const currentPage = ref(1)
const perPage = 10
const showAddPage = ref(false)
const showDeleteModal = ref(false)
const showEditModal = ref(false)
const editInstructorForm = ref<any>({})
const viewingInstructor = ref<any>(null)
const selectedInstructor = ref<any>(null)
const isLoading = ref(false)
const showPassword = ref(false)
const showConfirmPassword = ref(false)

const allInstructors = ref<any[]>([])
const allDepartments = ref<any[]>([])

// ── New Instructor Form ──
const newInstructor = ref({
  name: '', email: '', phone: '', gender: '', department_id: '', semester: '', year: '',
  employeeId: '', username: '', password: '', confirmPassword: '',
  profilePicture: null as File | null,
})

// Form Validation Errors
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

// Real-time password requirement checks (sidebar checklist)
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

// Field-by-field validation logic
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
        formErrors.value.password = 'Password must meet all 5 requirements in the sidebar.'
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

const permissions = ref([
  { key: 'create_exams', label: 'Create Exams', desc: 'Create and manage exams', checked: true },
  { key: 'view_results', label: 'View & Results', desc: 'View student results and analytics', checked: true },
  { key: 'manage_results', label: 'Manage Results', desc: 'Create and manage courses', checked: true },
  { key: 'manage_questions', label: 'Manage Questions', desc: 'Add, edit and manage questions', checked: true },
  { key: 'grade_exams', label: 'Grade Exams', desc: 'Grade student submissions', checked: true },
  { key: 'generate_reports', label: 'Generate Reports', desc: 'Generate and export reports', checked: true },
])

// ── Fetch Instructors ──
const fetchInstructors = async () => {
  try {
    const res = await apiClient.get('/admin/users?role=instructor,dept_head')
    allInstructors.value = (res.data.data || []).map((u: any) => ({
      ...u,
      avatar: u.name ? u.name.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2) : '??',
      profilePicture: u.profile_picture ? `http://localhost:8000/storage/${u.profile_picture}` : null,
      status: u.status || 'active',
      joined: new Date(u.created_at || Date.now()).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }),
      departmentName: u.department?.name || '—',
      employeeId: u.id_no || u.employee_id || `WU-INS-${(u.id || 1).toString().padStart(4, '0')}`,
      lastLogin: u.last_login_at || 'Never',
      phone: u.phone || 'N/A',
      gender: u.gender || 'N/A',
      section: u.section || 'N/A',
      year: u.year_level || 'N/A',
      courses: u.assigned_courses?.length ? u.assigned_courses.map((c: any) => c.title).join(', ') : 'No Courses',
      coCourses: u.co_instructor_courses?.length ? u.co_instructor_courses.map((c: any) => c.title).join(', ') : 'None',
    }))
  } catch (err) { console.error('Failed to fetch instructors:', err) }
}

// ── Fetch Departments ──
const fetchDepartments = async () => {
  try {
    const res = await apiClient.get('/admin/departments')
    allDepartments.value = res.data.data || []
  } catch (err) { console.error('Failed to fetch departments:', err) }
}

onMounted(async () => { await Promise.all([fetchInstructors(), fetchDepartments()]) })

// ── Computed ──
const filtered = computed(() => {
  return allInstructors.value.filter(i => {
    const matchSearch = i.name.toLowerCase().includes(search.value.toLowerCase()) ||
                        i.email.toLowerCase().includes(search.value.toLowerCase())
    const matchDept   = deptFilter.value === 'all' || i.departmentName === deptFilter.value
    const matchStatus = statusFilter.value === 'all' || i.status === statusFilter.value
    const matchSection= sectionFilter.value === 'all' || i.section === sectionFilter.value
    const matchYear   = yearFilter.value === 'all' || i.year === yearFilter.value
    return matchSearch && matchDept && matchStatus && matchSection && matchYear
  })
})

const totalPages  = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated   = computed(() => filtered.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage))

const stats = computed(() => {
  const total = allInstructors.value.length
  const active = allInstructors.value.filter(i => i.status === 'active').length
  const inactive = total - active
  const newInst = allInstructors.value.filter(i => {
    const created = new Date(i.created_at || Date.now())
    const now = new Date()
    return (now.getTime() - created.getTime()) < 30 * 24 * 60 * 60 * 1000
  }).length
  return { total, active, inactive, newInst }
})

// ── Donut Chart ──
const chartData = computed(() => ({
  labels: ['Active', 'Inactive', 'New This Month'],
  datasets: [{
    backgroundColor: ['#4338ca', '#F43F5E', '#06b6d4'],
    data: [stats.value.active, stats.value.inactive, stats.value.newInst],
    borderWidth: 0,
    hoverOffset: 4
  }]
}))
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '72%',
  plugins: { legend: { display: false }, tooltip: { enabled: true } }
}

// ── Top Departments (Max 5) ──
const topDepartments = computed(() => {
  if (allDepartments.value && allDepartments.value.length > 0) {
    const list = allDepartments.value.map(d => {
      const instCount = allInstructors.value.filter(i => i.department_id === d.id || i.departmentName === d.name).length
      return {
        id: d.id,
        name: d.name,
        count: instCount,
        created_at: d.created_at
      }
    })
    return list.sort((a, b) => b.count - a.count).slice(0, 5)
  }
  const map: Record<string, number> = {}
  allInstructors.value.forEach(i => {
    if (i.departmentName && i.departmentName !== '—') {
      map[i.departmentName] = (map[i.departmentName] || 0) + 1
    }
  })
  return Object.entries(map)
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})

const deptIcons = ['💻', '⚙️', '📊', '🗄️', '🌐']

// ── Recent Registrations (Max 5) ──
const recentRegistrations = computed(() => {
  return [...allInstructors.value]
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
    .slice(0, 5)
})

// ── Avatar Color ──
const avatarBg = (id: number) => {
  const colors = ['bg-indigo-100 text-indigo-600','bg-emerald-100 text-emerald-600','bg-sky-100 text-sky-600','bg-amber-100 text-amber-600','bg-rose-100 text-rose-600','bg-violet-100 text-violet-600','bg-teal-100 text-teal-600','bg-orange-100 text-orange-600','bg-cyan-100 text-cyan-600','bg-purple-100 text-purple-600']
  return colors[(id || 0) % colors.length]
}

// ── Page Numbers ──
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

// ── Pagination Controls ──
const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const goToPage = (page: number | string) => {
  if (typeof page === 'number' && page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Reset to first page when any search or filter criteria changes
watch([search, deptFilter, statusFilter, sectionFilter, yearFilter], () => {
  currentPage.value = 1
})

// Ensure currentPage stays within valid bounds if dataset changes
watch(filtered, (newVal) => {
  const max = Math.max(1, Math.ceil(newVal.length / perPage))
  if (currentPage.value > max) {
    currentPage.value = max
  }
})

// ── Actions ──
const viewInstructor = (inst: any) => { viewingInstructor.value = inst }
const closeView = () => { viewingInstructor.value = null }
const openAdd = () => {
  newInstructor.value = { name:'', email:'', phone:'', gender:'', department_id:'', semester:'', year:'', employeeId:'', username:'', password:'', confirmPassword:'', profilePicture: null }
  isSubmitted.value = false
  Object.keys(formErrors.value).forEach(k => (formErrors.value as any)[k] = '')
  if (previewUrl) previewUrl.value = null
  showPassword.value = false
  showConfirmPassword.value = false
  permissions.value.forEach(p => p.checked = true)
  showAddPage.value = true
}
const closeAdd = () => { 
  showAddPage.value = false 
  if (previewUrl) previewUrl.value = null
  isSubmitted.value = false
  Object.keys(formErrors.value).forEach(k => (formErrors.value as any)[k] = '')
}
const editPermissions = ref([
  { id: 'create_exams', key: 'createExams', label: 'Create Exams', desc: 'Create and manage exams', checked: true },
  { id: 'view_results', key: 'viewResults', label: 'View Results', desc: 'View student results and analytics', checked: true },
  { id: 'manage_results', key: 'manageResults', label: 'Manage Results', desc: 'Create and manage courses', checked: false },
  { id: 'manage_questions', key: 'manageQuestions', label: 'Manage Questions', desc: 'Add, edit and manage questions', checked: true },
  { id: 'grade_exams', key: 'gradeExams', label: 'Grade Exams', desc: 'Grade student submissions', checked: true },
  { id: 'generate_reports', key: 'generateReports', label: 'Generate Reports', desc: 'Generate and export reports', checked: true }
])
const fileInput = ref<HTMLInputElement | null>(null)
const previewUrl = ref<string | null>(null)
const triggerFileInput = () => { if (fileInput.value) fileInput.value.click() }

const editFileInput = ref<HTMLInputElement | null>(null)
const editPreviewUrl = ref<string | null>(null)
const triggerEditFileInput = () => { if (editFileInput.value) editFileInput.value.click() }

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
    employeeId: instructor.employeeId || '',
    username: instructor.username || '',
    password: '',
    profilePicture: null
  }
  if (editPreviewUrl) editPreviewUrl.value = instructor.profilePicture || null
  // Initialize editPermissions (in a real app, populate from instructor.permissions)
  editPermissions.value.forEach(p => p.checked = true)
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
    
    // Check if it's a File object (meaning a new image was uploaded)
    if (editInstructorForm.value.profilePicture instanceof File) {
      formData.append('profile_picture', editInstructorForm.value.profilePicture)
    }

    await apiClient.post(`/admin/users/${editInstructorForm.value.id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    await fetchInstructors()
    showEditModal.value = false
    
    // Update viewingInstructor if it was the one edited
    if (viewingInstructor.value?.id === editInstructorForm.value.id) {
      const updatedInst = allInstructors.value.find(i => i.id === editInstructorForm.value.id)
      if (updatedInst) viewingInstructor.value = { ...updatedInst }
    }
  } catch (err: any) {
    let msg = 'Failed to update instructor.'
    if (err.response?.data) {
      msg = err.response.data.message || msg
      if (err.response.data.errors) {
        msg += '\n' + Object.values(err.response.data.errors).flat().join('\n')
      }
    }
    alert(msg)
  } finally {
    isLoading.value = false
  }
}

// Toast state for instructor creation
const successToast = ref<{ show: boolean; message: string }>({ show: false, message: '' })
let toastTimer: ReturnType<typeof setTimeout> | null = null

function showSuccessToast(message: string) {
  if (toastTimer) clearTimeout(toastTimer)
  successToast.value = { show: true, message }
  toastTimer = setTimeout(() => { successToast.value.show = false }, 4000)
}

const addInstructor = async () => {
  if (!validateAll()) {
    // Smooth scroll to the first invalid field
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
    await fetchInstructors()
    showAddPage.value = false
    showSuccessToast(`Instructor "${instructorName}" was created successfully!`)
  } catch (err: any) {
    let msg = 'Failed to create instructor.'
    if (err.response?.data) {
      msg = err.response.data.message || msg
      if (err.response.data.errors) {
        const errs = err.response.data.errors
        if (errs.email) formErrors.value.email = errs.email[0]
        if (errs.name) formErrors.value.name = errs.name[0]
        if (errs.phone) formErrors.value.phone = errs.phone[0]
        if (errs.username) formErrors.value.username = errs.username[0]
        if (errs.department_id) formErrors.value.department_id = errs.department_id[0]
        if (errs.id_no) formErrors.value.employeeId = errs.id_no[0]
        if (errs.password) formErrors.value.password = errs.password[0]
        msg += '\n' + Object.values(err.response.data.errors).flat().join('\n')
      }
    }
    alert(msg)
  } finally { isLoading.value = false }
}

const confirmDelete = (instructor: any) => { selectedInstructor.value = instructor; showDeleteModal.value = true }
const deleteInstructor = async () => {
  if (!selectedInstructor.value) return
  isLoading.value = true
  try {
    await apiClient.delete(`/admin/users/${selectedInstructor.value.id}`)
    await fetchInstructors()
    if (viewingInstructor.value?.id === selectedInstructor.value.id) {
      viewingInstructor.value = null
    }
    showDeleteModal.value = false
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete instructor.')
  } finally {
    isLoading.value = false
  }
}

const showExportDropdown = ref(false)

const handleExport = async (format: string) => {
  showExportDropdown.value = false
  isLoading.value = true
  try {
    // Build query params matching current active filters
    const params: Record<string, string> = {
      role: 'instructor,dept_head',
      format,
    }
    if (deptFilter.value !== 'all') params.department = deptFilter.value
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (sectionFilter.value !== 'all') params.section = sectionFilter.value
    if (yearFilter.value !== 'all') params.year = yearFilter.value
    if (search.value) params.search = search.value

    const token = localStorage.getItem('auth_token')
    const queryString = new URLSearchParams(params).toString()
    
    // Fetch JSON containing base64 file data (bulletproof against CORS/Blob issues)
    const response = await fetch(`http://localhost:8000/api/v1/admin/users-export?${queryString}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      }
    })

    if (!response.ok) {
      const text = await response.text()
      try { alert(JSON.parse(text).message || 'Export failed') } catch { alert('Export failed') }
      return
    }

    const data = await response.json()
    if (!data.file || !data.filename) throw new Error('Invalid export format')

    // Convert base64 to Blob
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

  } catch (err: any) {
    alert('Failed to export instructors. Please try again.')
  } finally {
    isLoading.value = false
  }
}

// ── Import Modal, Validation & Results State ──
const showImportModal = ref(false)
const selectedImportFile = ref<File | null>(null)
const isImporting = ref(false)
const importDragOver = ref(false)
const importFileInput = ref<HTMLInputElement | null>(null)
const modalFileInput = ref<HTMLInputElement | null>(null)

// Error / Format Issues Modal State
const showImportErrorModal = ref(false)
const importErrorMessage = ref('')
const importErrorList = ref<string[]>([])
const importFormatGuide = ref<any>(null)

// Success Modal State
const showImportSuccessModal = ref(false)
const importSuccessMessage = ref('')
const importedInstructorsList = ref<any[]>([])

const triggerImport = () => {
  selectedImportFile.value = null
  showImportModal.value = true
}

const onModalFileSelect = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    selectedImportFile.value = file
  }
}

const onFileDrop = (event: DragEvent) => {
  importDragOver.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    const ext = file.name.split('.').pop()?.toLowerCase()
    if (ext === 'csv' || ext === 'pdf') {
      selectedImportFile.value = file
    } else {
      importErrorMessage.value = 'Invalid file type. Please upload a CSV (.csv) or PDF (.pdf) file.'
      importErrorList.value = ['Only .csv and .pdf file formats are supported.']
      showImportErrorModal.value = true
    }
  }
}

const handleImport = async (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    await executeImport(file)
  }
}

const executeImport = async (fileOverride?: File) => {
  const file = fileOverride || selectedImportFile.value
  if (!file) {
    importErrorMessage.value = 'Please select a file to import.'
    importErrorList.value = ['No file selected. Please choose a CSV or PDF file.']
    showImportErrorModal.value = true
    return
  }

  isImporting.value = true
  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('role', 'instructor')

    const res = await apiClient.post('/admin/users-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    showImportModal.value = false
    selectedImportFile.value = null
    importSuccessMessage.value = res.data.message || `Successfully imported instructors from ${file.name}.`
    importedInstructorsList.value = res.data.instructors || []
    showImportSuccessModal.value = true

    await fetchInstructors()
  } catch (err: any) {
    showImportModal.value = false
    const data = err.response?.data
    importErrorMessage.value = data?.message || 'Failed to import instructors.'
    importErrorList.value = Array.isArray(data?.errors)
      ? data.errors
      : (data?.message ? [data.message] : ['An unexpected error occurred during file import.'])
    importFormatGuide.value = data?.format_guide || null
    showImportErrorModal.value = true
  } finally {
    isImporting.value = false
    if (importFileInput.value) importFileInput.value.value = ''
    if (modalFileInput.value) modalFileInput.value.value = ''
  }
}

const downloadSampleCsv = () => {
  const deptExample1 = allDepartments.value.length > 0 ? allDepartments.value[0].name : 'Software Engineering'
  const deptExample2 = allDepartments.value.length > 1 ? allDepartments.value[1].name : 'Computer Science'

  const headers = ['Full Name', 'Email', 'Phone', 'Department', 'Gender', 'Employee ID', 'Academic Year Level', 'Semester', 'Section', 'Password']
  const row1 = ['Dr. Kebede Tessema', 'kebede.t@wu.edu.et', '0911223344', deptExample1, 'Male', 'WU-INS-0101', '3rd Year', 'Second Semester', 'Sec A', 'Password123!']
  const row2 = ['Sara Mohammed', 'sara.m@wu.edu.et', '0922334455', deptExample2, 'Female', 'WU-INS-0102', '2nd Year', 'Second Semester', 'Sec B', 'Password123!']

  const csvContent = [headers.join(','), row1.join(','), row2.join(',')].join('\n')
  const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'instructor_import_template.csv'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="w-full relative">

    <!-- Success Toast Notification -->
    <transition name="toast">
      <div v-if="successToast.show" class="fixed top-6 right-6 z-[100] flex items-center gap-3 bg-emerald-600 text-white px-5 py-3.5 rounded-xl shadow-xl border border-emerald-500">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-[13px] font-bold">{{ successToast.message }}</span>
        <button @click="successToast.show = false" class="ml-2 text-white/70 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
      </div>
    </transition>

    <!-- ════════════════ LIST VIEW ════════════════ -->
    <div v-if="!viewingInstructor && !showAddPage" class="space-y-6 pb-12 min-w-0 w-full">

      <!-- Page Actions -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-end gap-4">
        <div class="flex flex-wrap items-center gap-3">
          <input type="file" ref="importFileInput" class="hidden" accept=".csv,.pdf,application/pdf,text/csv" @change="handleImport">
          <button @click="triggerImport" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-[#4338ca] font-bold rounded-xl text-[13px] hover:bg-slate-50 transition-colors shadow-sm whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> Import Instructors
          </button>
          <div class="relative">
            <button @click="showExportDropdown = !showExportDropdown" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-[#4338ca] font-bold rounded-xl text-[13px] hover:bg-slate-50 transition-colors shadow-sm whitespace-nowrap">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg> Export Instructors
            </button>
            <div v-if="showExportDropdown" class="absolute right-0 mt-2 w-36 bg-white rounded-xl shadow-lg border border-slate-100 py-2 z-50">
              <button @click="handleExport('csv')" class="w-full text-left px-4 py-2 text-[13px] text-slate-600 hover:bg-slate-50 hover:text-[#4338ca] transition-colors">Export as CSV</button>
              <button @click="handleExport('pdf')" class="w-full text-left px-4 py-2 text-[13px] text-slate-600 hover:bg-slate-50 hover:text-[#4338ca] transition-colors">Export as PDF</button>
            </div>
          </div>
          <button @click="openAdd" class="flex items-center gap-2 px-4 py-2.5 bg-[#4338ca] text-white font-bold rounded-xl text-[13px] hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add New Instructor
          </button>
        </div>
      </div>

      <!-- ── Stats Cards ── -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Total -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-5 shadow-sm">
          <div class="flex items-start justify-between">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Instructors</p>
              <p class="text-[20px] sm:text-[24px] font-extrabold text-slate-800 leading-none">{{ stats.total }}</p>
            </div>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <span class="flex items-center text-[11px] font-bold text-emerald-500">
              <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>8.3%
            </span>
            <span class="text-[11px] text-slate-400">All registered instructors</span>
          </div>
        </div>
        <!-- Active -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-5 shadow-sm">
          <div class="flex items-start justify-between">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Active Instructors</p>
              <p class="text-[20px] sm:text-[24px] font-extrabold text-slate-800 leading-none">{{ stats.active }}</p>
            </div>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <span class="flex items-center text-[11px] font-bold text-emerald-500">
              <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>9.6%
            </span>
            <span class="text-[11px] text-slate-400">Active instructors</span>
          </div>
        </div>
        <!-- Inactive -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-5 shadow-sm">
          <div class="flex items-start justify-between">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-rose-50 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"></path></svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Inactive Instructors</p>
              <p class="text-[20px] sm:text-[24px] font-extrabold text-slate-800 leading-none">{{ stats.inactive }}</p>
            </div>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <span class="flex items-center text-[11px] font-bold text-rose-500">
              <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>12.5%
            </span>
            <span class="text-[11px] text-slate-400">Inactive instructors</span>
          </div>
        </div>
        <!-- New -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-5 shadow-sm">
          <div class="flex items-start justify-between">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-sky-50 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
            </div>
            <div class="text-right">
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">New Instructors</p>
              <p class="text-[20px] sm:text-[24px] font-extrabold text-slate-800 leading-none">{{ stats.newInst }}</p>
            </div>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <span class="flex items-center text-[11px] font-bold text-emerald-500">
              <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>12.5%
            </span>
            <span class="text-[11px] text-slate-400">This month</span>
          </div>
        </div>
      </div>

      <!-- ── Main Content Grid ── -->
      <div class="flex flex-col gap-6 w-full">

        <!-- Top Column: Table -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm flex flex-col min-w-0 w-full overflow-hidden">

          <!-- Table Filters -->
          <div class="p-3.5 sm:p-4 border-b border-slate-100 flex flex-wrap items-center gap-2.5 sm:gap-3">
            <div class="relative flex-1 min-w-[200px] max-w-full sm:max-w-xs">
              <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              <input v-model="search" type="text" placeholder="Search Instructors..." class="w-full border border-slate-200 rounded-lg pl-9 pr-4 py-2 text-[12px] focus:outline-none focus:border-[#4338ca] bg-white">
            </div>
            <select v-model="deptFilter" class="border border-slate-200 rounded-lg px-3 py-2 text-[12px] font-medium text-slate-600 focus:outline-none focus:border-[#4338ca] min-w-[130px] shrink-0 bg-white">
              <option value="all">All Departments</option>
              <option v-for="d in allDepartments" :key="d.id" :value="d.name">{{ d.name }}</option>
            </select>
            <select v-model="statusFilter" class="border border-slate-200 rounded-lg px-3 py-2 text-[12px] font-medium text-slate-600 focus:outline-none focus:border-[#4338ca] min-w-[110px] shrink-0 bg-white">
              <option value="all">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <select v-model="sectionFilter" class="border border-slate-200 rounded-lg px-3 py-2 text-[12px] font-medium text-slate-600 focus:outline-none focus:border-[#4338ca] min-w-[110px] shrink-0 bg-white">
              <option value="all">All Sections</option>
              <option value="Section A">Section A</option>
              <option value="Section B">Section B</option>
              <option value="Section C">Section C</option>
            </select>
            <select v-model="yearFilter" class="border border-slate-200 rounded-lg px-3 py-2 text-[12px] font-medium text-slate-600 focus:outline-none focus:border-[#4338ca] min-w-[110px] shrink-0 bg-white">
              <option value="all">All Years</option>
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
              <option value="5th Year">5th Year</option>
            </select>
            <div class="relative shrink-0">
              <input type="text" value="Join Date" readonly class="w-[110px] border border-slate-200 rounded-lg px-3 py-2 text-[12px] font-medium text-slate-600 bg-white cursor-pointer">
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto min-w-0 flex-1">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50/60 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                  <th class="px-5 py-3.5 whitespace-nowrap">Instructor</th>
                  <th class="px-4 py-3.5 whitespace-nowrap">Email</th>
                  <th class="px-4 py-3.5 whitespace-nowrap">Department</th>
                  <th class="px-4 py-3.5 whitespace-nowrap">Section</th>
                  <th class="px-4 py-3.5 whitespace-nowrap">Academic Year Level</th>
                  <th class="px-4 py-3.5 whitespace-nowrap">Status</th>
                  <th class="px-4 py-3.5 whitespace-nowrap">Join Date</th>
                  <th class="px-4 py-3.5 whitespace-nowrap">Last Login</th>
                  <th class="px-4 py-3.5 text-center whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="inst in paginated" :key="inst.id" class="hover:bg-slate-50/50 transition-colors">
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                      <img v-if="inst.profilePicture" :src="inst.profilePicture" class="w-8 h-8 rounded-full object-cover shrink-0 border border-slate-200">
                      <div v-else :class="avatarBg(inst.id)" class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0">
                        {{ inst.avatar }}
                      </div>
                      <span class="text-[12px] font-bold text-slate-800 whitespace-nowrap">{{ inst.name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-[12px] text-slate-500 whitespace-nowrap">{{ inst.email }}</td>
                  <td class="px-4 py-3 text-[12px] font-medium text-slate-600 whitespace-nowrap">{{ inst.departmentName }}</td>
                  <td class="px-4 py-3 text-[12px] text-slate-500 whitespace-nowrap">{{ inst.section }}</td>
                  <td class="px-4 py-3 text-[12px] text-slate-500 whitespace-nowrap">{{ inst.year }}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span :class="inst.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500'" class="px-2.5 py-1 text-[10px] font-bold rounded-md capitalize">
                      {{ inst.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-[12px] text-slate-500 whitespace-nowrap">{{ inst.joined }}</td>
                  <td class="px-4 py-3 text-[12px] text-slate-500 whitespace-nowrap">{{ inst.lastLogin }}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center justify-center gap-2 text-slate-400">
                      <button @click="viewInstructor(inst)" class="p-1 hover:text-[#4338ca] transition-colors" title="View"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                      <button @click="openEdit(inst)" class="p-1 hover:text-[#4338ca] transition-colors" title="Edit"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                      <button @click="confirmDelete(inst)" class="p-1 hover:text-rose-500 transition-colors" title="Delete"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="paginated.length === 0">
                  <td colspan="9" class="px-6 py-10 text-center text-slate-400 text-[13px]">No instructors found matching your criteria.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="px-5 py-3.5 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span class="text-[12px] text-slate-500">
              Showing <span class="font-bold text-slate-700">{{ filtered.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }}</span> to <span class="font-bold text-slate-700">{{ Math.min(currentPage * perPage, filtered.length) }}</span> of <span class="font-bold text-slate-700">{{ filtered.length }}</span> instructors
            </span>
            <div class="flex items-center gap-1.5">
              <button 
                @click="prevPage" 
                :disabled="currentPage <= 1" 
                title="Previous page"
                class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-[#4338ca] hover:border-[#4338ca] disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-500 disabled:hover:border-slate-200 disabled:cursor-not-allowed transition-colors"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
              </button>
              <template v-for="(page, idx) in visiblePages" :key="idx">
                <span v-if="page === '...'" class="w-8 h-8 flex items-center justify-center text-[12px] text-slate-400">…</span>
                <button 
                  v-else 
                  @click="goToPage(page)" 
                  :class="currentPage === page ? 'bg-[#4338ca] text-white border-[#4338ca] shadow-sm' : 'border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'" 
                  class="w-8 h-8 flex items-center justify-center rounded-lg border text-[12px] font-bold transition-colors"
                >
                  {{ page }}
                </button>
              </template>
              <button 
                @click="nextPage" 
                :disabled="currentPage >= totalPages" 
                title="Next page"
                class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-[#4338ca] hover:border-[#4338ca] disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-500 disabled:hover:border-slate-200 disabled:cursor-not-allowed transition-colors"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- ── Bottom Grid (was Right Sidebar) ── -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">

          <!-- Instructor Overview Chart -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Instructor Overview</h3>
            <div class="relative w-44 h-44 mx-auto mb-5">
              <Doughnut :data="chartData" :options="chartOptions" />
              <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <span class="text-[24px] font-extrabold text-slate-800 leading-none">{{ stats.total }}</span>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-1">Total Instructors</span>
              </div>
            </div>
            <div class="space-y-2.5">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-[#4338ca]"></div><span class="text-[12px] text-slate-600">Active</span></div>
                <div><span class="text-[12px] font-bold text-slate-800">{{ stats.active }}</span> <span class="text-[11px] text-slate-400">({{ stats.total ? ((stats.active / stats.total) * 100).toFixed(1) : 0 }}%)</span></div>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div><span class="text-[12px] text-slate-600">Inactive</span></div>
                <div><span class="text-[12px] font-bold text-slate-800">{{ stats.inactive }}</span> <span class="text-[11px] text-slate-400">({{ stats.total ? ((stats.inactive / stats.total) * 100).toFixed(1) : 0 }}%)</span></div>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-cyan-500"></div><span class="text-[12px] text-slate-600">New This Month</span></div>
                <div><span class="text-[12px] font-bold text-slate-800">{{ stats.newInst }}</span> <span class="text-[11px] text-slate-400">({{ stats.total ? ((stats.newInst / stats.total) * 100).toFixed(1) : 0 }}%)</span></div>
              </div>
            </div>
          </div>

          <!-- Top Departments -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-4">Top Departments</h3>
            <div class="space-y-3">
              <div v-for="(dept, i) in topDepartments" :key="dept.name" class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <span class="text-[14px]">{{ deptIcons[i] || '📁' }}</span>
                  <span class="text-[12px] font-medium text-slate-700">{{ dept.name }}</span>
                </div>
                <span class="text-[12px] font-bold text-slate-800">{{ dept.count }}</span>
              </div>
              <div v-if="topDepartments.length === 0" class="text-[12px] text-slate-400 text-center py-2">No department data</div>
            </div>
            <button @click="router.push('/admin/departments')" class="text-[12px] font-bold text-[#4338ca] hover:underline mt-3">View All</button>
          </div>

          <!-- Recent Registrations -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-4">Recent Registrations</h3>
            <div class="space-y-4">
              <div v-for="inst in recentRegistrations" :key="inst.id" class="flex items-center gap-3">
                <div :class="avatarBg(inst.id)" class="w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0">
                  {{ inst.avatar }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[12px] font-bold text-slate-800 truncate">{{ inst.name }}</p>
                  <p class="text-[11px] text-slate-400 truncate">{{ inst.departmentName }}</p>
                </div>
                <span class="text-[10px] text-slate-400 whitespace-nowrap shrink-0">{{ inst.joined }}</span>
              </div>
              <div v-if="recentRegistrations.length === 0" class="text-[12px] text-slate-400 text-center py-2">No recent registrations</div>
            </div>
            <button @click="scrollToTop" class="text-[12px] font-bold text-[#4338ca] hover:underline mt-3">View All</button>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 gap-3">
              <button @click="openAdd" class="flex items-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-[#4338ca] group transition-colors text-left">
                <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#4338ca]">Add Instructor</span>
              </button>
              <button @click="triggerImport" class="flex items-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-[#4338ca] group transition-colors text-left">
                <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#4338ca]">Import Instructors</span>
              </button>
              <button class="flex items-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-[#4338ca] group transition-colors text-left">
                <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#4338ca]">Export Instructors</span>
              </button>
              <button @click="router.push('/admin/departments')" class="flex items-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-[#4338ca] group transition-colors text-left">
                <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#4338ca]">Manage Departments</span>
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ════════════════ DETAIL VIEW ════════════════ -->
    <div v-else-if="viewingInstructor" class="space-y-6 pb-12 min-w-0 w-full">
      <!-- Detail Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <button @click="closeView" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-50 transition-colors text-slate-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          </button>
          <div>
            <h1 class="text-[22px] font-bold text-slate-800">Instructor Details</h1>
            <div class="flex items-center gap-2 text-[13px] text-slate-500 mt-0.5">
              <span class="hover:text-[#4338ca] cursor-pointer" @click="closeView">Instructors</span>
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              <span class="font-medium text-slate-700">{{ viewingInstructor.name }}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button @click="openEdit(viewingInstructor)" class="flex items-center gap-2 px-4 py-2 bg-white border border-[#4338ca] text-[#4338ca] font-bold rounded-xl text-[13px] hover:bg-indigo-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg> Edit
          </button>
          <button @click="confirmDelete(viewingInstructor)" class="flex items-center gap-2 px-4 py-2 bg-white border border-rose-300 text-rose-500 font-bold rounded-xl text-[13px] hover:bg-rose-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg> Delete
          </button>
        </div>
      </div>

      <!-- Detail Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
        <!-- Left -->
        <div class="space-y-6 min-w-0">
          <!-- Personal Info -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-8 shadow-sm">
            <h3 class="text-[15px] font-bold text-slate-800 mb-6">Personal Information</h3>
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 sm:gap-8">
              <div class="flex flex-col items-center gap-3 shrink-0">
                <img
                  v-if="viewingInstructor.profilePicture"
                  :src="viewingInstructor.profilePicture"
                  class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl object-cover border-2 border-slate-100 shadow-sm"
                  :alt="viewingInstructor.name"
                />
                <div v-else :class="avatarBg(viewingInstructor.id)" class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl flex items-center justify-center text-[28px] font-bold">
                  {{ viewingInstructor.avatar }}
                </div>
                <span :class="viewingInstructor.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500'" class="px-3 py-1 text-[11px] font-bold rounded-full capitalize flex items-center gap-1.5">
                  <div :class="viewingInstructor.status === 'active' ? 'bg-emerald-500' : 'bg-rose-500'" class="w-1.5 h-1.5 rounded-full"></div> {{ viewingInstructor.status }}
                </span>
              </div>
              <div class="flex-1 w-full grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 sm:gap-y-5">
                <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Full Name</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.name }}</p></div>
                <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Gender</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.gender }}</p></div>
                <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Email</p><p class="text-[14px] font-bold text-slate-800 break-all">{{ viewingInstructor.email }}</p></div>
                <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Phone</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.phone }}</p></div>
                <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Department</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.departmentName }}</p></div>
                <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Section</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.section }}</p></div>
                <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Year Level</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.year }}</p></div>
                <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Employee ID</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.employeeId }}</p></div>
              </div>
            </div>
          </div>
          <!-- Account Info -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-8 shadow-sm">
            <h3 class="text-[15px] font-bold text-slate-800 mb-6">Account Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
              <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Username</p><p class="text-[14px] font-bold text-slate-800 break-all">{{ viewingInstructor.email }}</p></div>
              <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Account Status</p><p :class="viewingInstructor.status === 'active' ? 'text-emerald-500' : 'text-rose-500'" class="text-[14px] font-bold capitalize">{{ viewingInstructor.status }}</p></div>
              <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Role</p><p class="text-[14px] font-bold text-slate-800">Instructor</p></div>
              <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Account Created</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.joined }}</p></div>
              <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Last Login</p><p class="text-[14px] font-bold text-slate-800">{{ viewingInstructor.lastLogin }}</p></div>
              <div><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Updated By</p><p class="text-[14px] font-bold text-slate-800">Super Admin</p></div>
            </div>
          </div>
          <!-- Permissions -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-8 shadow-sm">
            <h3 class="text-[15px] font-bold text-slate-800 mb-6">Permissions & Access</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div v-for="perm in ['Create Exams','View Results','Manage Courses','Manage Questions','Grade Exams','Generate Reports']" :key="perm" class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded bg-emerald-50 text-emerald-500 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></div>
                <span class="text-[12px] font-bold text-slate-700">{{ perm }}</span>
              </div>
            </div>
          </div>
        </div>
        <!-- Right -->
        <div class="space-y-6">
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-4">Summary</h3>
            <div class="space-y-3.5">
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Status</span><span :class="viewingInstructor.status==='active'?'text-emerald-500':'text-rose-500'" class="text-[12px] font-bold capitalize">{{ viewingInstructor.status }}</span></div>
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Department</span><span class="text-[12px] font-bold text-slate-800">{{ viewingInstructor.departmentName }}</span></div>
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Employee ID</span><span class="text-[12px] font-bold text-slate-800">{{ viewingInstructor.employeeId }}</span></div>
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Section</span><span class="text-[12px] font-bold text-slate-800">{{ viewingInstructor.section }}</span></div>
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Year Level</span><span class="text-[12px] font-bold text-slate-800">{{ viewingInstructor.year }}</span></div>
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Courses Taught</span><span class="text-[12px] font-bold text-slate-800">{{ viewingInstructor.courses }}</span></div>
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Co-Instructor Courses</span><span class="text-[12px] font-bold text-slate-800">{{ viewingInstructor.coCourses }}</span></div>
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Role</span><span class="text-[12px] font-bold text-slate-800">Instructor</span></div>
              <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">Permissions</span><span class="text-[12px] font-bold text-slate-800">6 Modules</span></div>
            </div>
          </div>
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-4">Quick Actions</h3>
            <div class="space-y-2.5">
              <button @click="openEdit(viewingInstructor)" class="w-full flex items-center justify-between p-3 border border-slate-100 rounded-xl hover:border-[#4338ca] group transition-colors">
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg><span class="text-[12px] font-bold text-slate-700 group-hover:text-[#4338ca]">Edit Instructor</span></div>
                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button class="w-full flex items-center justify-between p-3 border border-slate-100 rounded-xl hover:border-[#4338ca] group transition-colors">
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg><span class="text-[12px] font-bold text-slate-700 group-hover:text-[#4338ca]">Reset Password</span></div>
                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button @click="confirmDelete(viewingInstructor)" class="w-full flex items-center justify-between p-3 border border-slate-100 rounded-xl hover:border-rose-500 group transition-colors">
                <div class="flex items-center gap-2"><svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg><span class="text-[12px] font-bold text-slate-700 group-hover:text-rose-500">Delete Instructor</span></div>
                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ════════════════ ADD FORM VIEW ════════════════ -->
    <div v-else-if="showAddPage" class="space-y-8 pb-12 min-w-0 w-full">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-[22px] font-bold text-slate-800">Add New Instructor</h1>
          <div class="flex items-center gap-2 text-[13px] text-slate-500 mt-0.5">
            <span class="hover:text-[#4338ca] cursor-pointer" @click="closeAdd">Instructors</span>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="font-medium text-slate-700">Add New Instructor</span>
          </div>
        </div>
        <button @click="closeAdd" class="flex items-center gap-2 px-4 py-2 border border-slate-200 text-slate-600 font-bold rounded-xl text-[13px] hover:bg-slate-50 transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg> Back to Instructors
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
        <!-- Main Form -->
        <div class="space-y-6 min-w-0">
          
          <!-- Personal Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-8 shadow-sm space-y-6">
            <h3 class="text-[15px] font-bold text-slate-800">Personal Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Full Name <span class="text-rose-500">*</span></label>
                <input
                  id="field-name"
                  v-model="newInstructor.name"
                  type="text"
                  placeholder="Enter full name"
                  class="w-full border rounded-xl px-4 py-2.5 text-[13px] focus:outline-none transition-colors"
                  :class="formErrors.name ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                  @input="validateField('name')"
                  @blur="validateField('name')"
                >
                <p v-if="formErrors.name" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.name }}
                </p>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Email Address <span class="text-rose-500">*</span></label>
                <input
                  id="field-email"
                  v-model="newInstructor.email"
                  type="email"
                  placeholder="Enter email address"
                  class="w-full border rounded-xl px-4 py-2.5 text-[13px] focus:outline-none transition-colors"
                  :class="formErrors.email ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                  @input="validateField('email')"
                  @blur="validateField('email')"
                >
                <p v-if="formErrors.email" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.email }}
                </p>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Phone Number <span class="text-rose-500">*</span></label>
                <input
                  id="field-phone"
                  v-model="newInstructor.phone"
                  type="text"
                  placeholder="e.g. 0911223344 or +251911223344"
                  class="w-full border rounded-xl px-4 py-2.5 text-[13px] focus:outline-none transition-colors"
                  :class="formErrors.phone ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                  @input="validateField('phone')"
                  @blur="validateField('phone')"
                >
                <p v-if="formErrors.phone" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.phone }}
                </p>
              </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Gender <span class="text-rose-500">*</span></label>
                <select
                  id="field-gender"
                  v-model="newInstructor.gender"
                  class="w-full border rounded-xl px-4 py-2.5 text-[13px] focus:outline-none transition-colors bg-white"
                  :class="formErrors.gender ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                  @change="validateField('gender')"
                >
                  <option value="">Select gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
                <p v-if="formErrors.gender" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.gender }}
                </p>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">
                  Profile Picture <span class="text-slate-400 font-normal text-[11px]">(Optional)</span>
                </label>
                <div class="relative border-2 border-dashed border-slate-200 rounded-xl p-6 flex flex-col items-center justify-center hover:border-[#4338ca] hover:bg-slate-50 transition-colors cursor-pointer overflow-hidden" @click="triggerFileInput">
                  <input type="file" ref="fileInput" class="hidden" accept="image/png, image/jpeg, image/webp" @change="handleProfilePicture">
                  <template v-if="previewUrl">
                    <img :src="previewUrl" class="w-full h-full object-cover absolute inset-0 opacity-20">
                    <img :src="previewUrl" class="w-16 h-16 rounded-full object-cover z-10 border-2 border-white shadow-sm mb-2">
                    <span class="text-[13px] font-bold text-[#4338ca] mb-1 z-10">Change Photo</span>
                    <span class="text-[11px] text-slate-500 z-10 text-center truncate max-w-[80%]">{{ newInstructor.profilePicture?.name }}</span>
                  </template>
                  <template v-else>
                    <svg class="w-6 h-6 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    <span class="text-[13px] font-bold text-slate-700 mb-1">Upload Photo</span>
                    <span class="text-[11px] text-slate-400">PNG, JPG up to 2MB (Optional)</span>
                  </template>
                </div>
                <p v-if="formErrors.profilePicture" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.profilePicture }}
                </p>
              </div>
            </div>
          </div>

          <!-- Professional Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-8 shadow-sm space-y-6">
            <h3 class="text-[15px] font-bold text-slate-800">Professional Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Department <span class="text-rose-500">*</span></label>
                <select
                  id="field-department_id"
                  v-model="newInstructor.department_id"
                  class="w-full border rounded-xl px-4 py-2.5 text-[13px] focus:outline-none transition-colors bg-white"
                  :class="formErrors.department_id ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                  @change="validateField('department_id')"
                >
                  <option value="">Select department</option>
                  <option v-for="d in allDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <p v-if="formErrors.department_id" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.department_id }}
                </p>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Employee ID <span class="text-rose-500">*</span></label>
                <input
                  id="field-employeeId"
                  v-model="newInstructor.employeeId"
                  type="text"
                  placeholder="Enter employee ID (e.g. WU-INS-0101)"
                  class="w-full border rounded-xl px-4 py-2.5 text-[13px] focus:outline-none transition-colors"
                  :class="formErrors.employeeId ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                  @input="validateField('employeeId')"
                  @blur="validateField('employeeId')"
                >
                <p v-if="formErrors.employeeId" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.employeeId }}
                </p>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">
                  Semester <span class="text-slate-400 font-normal text-[11px]">(Auto-set)</span>
                </label>
                <input type="text" disabled :value="settingsStore.semester" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-500 bg-slate-50 cursor-not-allowed font-bold">
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">
                  Academic Year Level <span class="text-slate-400 font-normal text-[11px]">(Optional)</span>
                </label>
                <select v-model="newInstructor.year" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca] bg-white">
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

          <!-- Account Information -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-8 shadow-sm space-y-6">
            <h3 class="text-[15px] font-bold text-slate-800">Account Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Username <span class="text-rose-500">*</span></label>
                <input
                  id="field-username"
                  v-model="newInstructor.username"
                  type="text"
                  placeholder="Enter username (min 3 chars)"
                  class="w-full border rounded-xl px-4 py-2.5 text-[13px] focus:outline-none transition-colors"
                  :class="formErrors.username ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                  @input="validateField('username')"
                  @blur="validateField('username')"
                >
                <p v-if="formErrors.username" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.username }}
                </p>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    id="field-password"
                    v-model="newInstructor.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="Enter password"
                    class="w-full border rounded-xl pl-4 pr-10 py-2.5 text-[13px] focus:outline-none transition-colors"
                    :class="formErrors.password ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                    @input="validateField('password')"
                    @blur="validateField('password')"
                  >
                  <button @click="showPassword = !showPassword" type="button" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                    <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>
                </div>
                <p v-if="formErrors.password" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.password }}
                </p>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Confirm Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    id="field-confirmPassword"
                    v-model="newInstructor.confirmPassword"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    placeholder="Confirm password"
                    class="w-full border rounded-xl pl-4 pr-10 py-2.5 text-[13px] focus:outline-none transition-colors"
                    :class="formErrors.confirmPassword ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500' : 'border-slate-200 focus:border-[#4338ca]'"
                    @input="validateField('confirmPassword')"
                    @blur="validateField('confirmPassword')"
                  >
                  <button @click="showConfirmPassword = !showConfirmPassword" type="button" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                    <svg v-if="!showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>
                </div>
                <p v-if="formErrors.confirmPassword" class="text-[11px] font-semibold text-rose-500 mt-1.5 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  {{ formErrors.confirmPassword }}
                </p>
              </div>
            </div>
          </div>

          <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-4">
            <button @click="closeAdd" class="px-6 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-center">Cancel</button>
            <button @click="addInstructor" :disabled="isLoading" class="flex items-center justify-center gap-2 px-6 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] rounded-xl hover:bg-indigo-700 transition-colors shadow-sm disabled:opacity-50">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
              Create Instructor
            </button>
          </div>

        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Account Summary</h3>
            <div class="flex items-center gap-4 mb-6">
              <div class="w-14 h-14 bg-indigo-50 text-[#4338ca] rounded-xl flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
              </div>
              <div>
                <p class="text-[14px] font-bold text-slate-800">New Instructor</p>
                <p class="text-[12px] text-slate-500">{{ newInstructor.name || 'Name not set' }}</p>
              </div>
            </div>
            <div class="space-y-4">
              <div class="flex justify-between items-center"><span class="text-[12px] text-slate-500">Department</span><span class="text-[12px] font-bold text-slate-800">{{ allDepartments.find(d => d.id === newInstructor.department_id)?.name || 'Not Selected' }}</span></div>
              <div class="flex justify-between items-center"><span class="text-[12px] text-slate-500">Status</span><span class="text-[11px] font-bold bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded">Active</span></div>
            </div>
          </div>

          <!-- Dynamic Password Requirements -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-[14px] font-bold text-slate-800">Password Requirements</h3>
              <span
                v-if="isPasswordValid"
                class="px-2 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-600 rounded-full"
              >
                All Met
              </span>
            </div>
            <ul class="space-y-2.5">
              <li class="flex items-center gap-2.5 text-[12px] transition-colors" :class="passwordCriteria.length ? 'font-semibold text-emerald-600' : 'text-slate-400'">
                <svg v-if="passwordCriteria.length" class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0"></div>
                At least 8 characters long
              </li>
              <li class="flex items-center gap-2.5 text-[12px] transition-colors" :class="passwordCriteria.uppercase ? 'font-semibold text-emerald-600' : 'text-slate-400'">
                <svg v-if="passwordCriteria.uppercase" class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0"></div>
                Include uppercase letter
              </li>
              <li class="flex items-center gap-2.5 text-[12px] transition-colors" :class="passwordCriteria.lowercase ? 'font-semibold text-emerald-600' : 'text-slate-400'">
                <svg v-if="passwordCriteria.lowercase" class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0"></div>
                Include lowercase letter
              </li>
              <li class="flex items-center gap-2.5 text-[12px] transition-colors" :class="passwordCriteria.number ? 'font-semibold text-emerald-600' : 'text-slate-400'">
                <svg v-if="passwordCriteria.number" class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0"></div>
                Include number
              </li>
              <li class="flex items-center gap-2.5 text-[12px] transition-colors" :class="passwordCriteria.special ? 'font-semibold text-emerald-600' : 'text-slate-400'">
                <svg v-if="passwordCriteria.special" class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 shrink-0"></div>
                Include special character
              </li>
            </ul>
          </div>

          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-4">Quick Actions</h3>
            <div class="space-y-3">
              <button class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-[#4338ca] group transition-colors text-left">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                  <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#4338ca]">Import Instructors</span>
                </div>
                <svg class="w-3 h-3 text-slate-400 group-hover:text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-[#4338ca] group transition-colors text-left">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                  <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#4338ca]">Manage Departments</span>
                </div>
                <svg class="w-3 h-3 text-slate-400 group-hover:text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-[#4338ca] group transition-colors text-left">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                  <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#4338ca]">Manage Roles</span>
                </div>
                <svg class="w-3 h-3 text-slate-400 group-hover:text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button class="w-full flex items-center justify-between p-3 rounded-xl border border-slate-100 hover:border-[#4338ca] group transition-colors text-left">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#4338ca]">View Instructor Guide</span>
                </div>
                <svg class="w-3 h-3 text-slate-400 group-hover:text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ════════════════ MODALS ════════════════ -->
    <Teleport to="body">
      <!-- Delete -->
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-sm overflow-hidden">
          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
            </div>
            <h3 class="text-[16px] font-bold text-slate-800 mb-2">Remove Instructor?</h3>
            <p class="text-[13px] text-slate-500 leading-relaxed">Are you sure you want to remove <span class="font-bold text-slate-700">{{ selectedInstructor?.name }}</span>? This action cannot be undone.</p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="deleteInstructor" class="flex-1 py-2.5 text-[13px] font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-xl transition-colors">Remove</button>
          </div>
        </div>
      </div>

      <!-- Edit -->
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-3 sm:p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
          <div class="flex items-center justify-between px-5 sm:px-6 py-4 sm:py-5 border-b border-slate-100 shrink-0">
            <h3 class="text-[16px] font-bold text-slate-800">Edit Instructor</h3>
            <button @click="showEditModal = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-500 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
          </div>
          <div class="p-4 sm:p-6 overflow-y-auto space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Full Name <span class="text-rose-500">*</span></label><input v-model="editInstructorForm.name" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca]"></div>
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Email Address <span class="text-rose-500">*</span></label><input v-model="editInstructorForm.email" type="email" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca]"></div>
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Phone Number <span class="text-rose-500">*</span></label><input v-model="editInstructorForm.phone" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca]"></div>
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Gender</label>
                <select v-model="editInstructorForm.gender" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca] bg-white">
                  <option value="">Select gender</option><option value="Male">Male</option><option value="Female">Female</option>
                </select>
              </div>
              <div class="col-span-1 sm:col-span-2">
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Profile Picture</label>
                <div class="relative border-2 border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center hover:border-[#4338ca] hover:bg-slate-50 transition-colors cursor-pointer overflow-hidden" @click="triggerEditFileInput">
                  <input type="file" ref="editFileInput" class="hidden" accept="image/png, image/jpeg" @change="handleEditProfilePicture">
                  <template v-if="editPreviewUrl">
                    <img :src="editPreviewUrl" class="w-full h-full object-cover absolute inset-0 opacity-20">
                    <img :src="editPreviewUrl" class="w-12 h-12 rounded-full object-cover z-10 border-2 border-white shadow-sm mb-1">
                    <span class="text-[12px] font-bold text-[#4338ca] z-10">Change Photo</span>
                    <span class="text-[10px] text-slate-500 z-10 text-center truncate max-w-[80%]">{{ editInstructorForm.profilePicture?.name || 'Current Photo' }}</span>
                  </template>
                  <template v-else>
                    <span class="text-[12px] font-bold text-slate-700">Upload Photo</span>
                  </template>
                </div>
              </div>
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Department <span class="text-rose-500">*</span></label>
                <select v-model="editInstructorForm.department_id" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca] bg-white">
                  <option value="">Select Department</option><option v-for="d in allDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Employee ID</label><input v-model="editInstructorForm.employeeId" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca]"></div>
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Username <span class="text-rose-500">*</span></label><input v-model="editInstructorForm.username" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca]"></div>
              <div><label class="block text-[12px] font-bold text-slate-700 mb-1.5">Password <span class="text-rose-500">*</span></label><input v-model="editInstructorForm.password" type="password" placeholder="Leave blank to keep current" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca]"></div>
            </div>
          </div>
          <div class="px-5 sm:px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50 shrink-0">
            <button @click="showEditModal = false" class="px-5 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="saveEdit" :disabled="isLoading" class="px-5 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] rounded-xl hover:bg-indigo-700 transition-colors shadow-sm disabled:opacity-50">Save Changes</button>
          </div>
        </div>
      </div>

      <!-- ════════════════ IMPORT INSTRUCTORS MODAL ════════════════ -->
      <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-xl overflow-hidden flex flex-col my-8">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#4338ca] flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              </div>
              <div>
                <h3 class="text-[16px] font-bold text-slate-800">Import Instructors</h3>
                <p class="text-[12px] text-slate-500">Upload a CSV or PDF file to batch import instructors.</p>
              </div>
            </div>
            <button @click="showImportModal = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <div class="p-6 space-y-5">
            <!-- Format Requirements Banner -->
            <div class="p-4 bg-indigo-50/60 border border-indigo-100 rounded-xl space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-bold text-[#4338ca] uppercase tracking-wide flex items-center gap-1.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  Required Format: Add Instructor Form
                </span>
                <button @click="downloadSampleCsv" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-indigo-200 text-[#4338ca] hover:bg-indigo-50 font-bold text-[11px] rounded-lg transition-colors shadow-xs">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                  Download Sample CSV
                </button>
              </div>
              <p class="text-[12px] text-slate-600 leading-relaxed">
                The file (CSV or PDF) must fulfill the Add Instructor form fields.
              </p>
              <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
                <div class="bg-white p-2 rounded-lg border border-indigo-100">
                  <span class="font-bold text-slate-700 block mb-0.5">Required Fields:</span>
                  <span class="text-rose-600 font-medium">Full Name, Email Address, Phone Number, Department</span>
                </div>
                <div class="bg-white p-2 rounded-lg border border-indigo-100">
                  <span class="font-bold text-slate-700 block mb-0.5">Optional Fields:</span>
                  <span class="text-slate-500">Gender, Employee ID, Year Level, Semester, Section, Password</span>
                </div>
              </div>
            </div>

            <!-- Upload Area -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-2">Select CSV or PDF File</label>
              <div
                class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-2"
                :class="importDragOver ? 'border-[#4338ca] bg-indigo-50/50' : 'border-slate-200 hover:border-[#4338ca] hover:bg-slate-50/50'"
                @dragover.prevent="importDragOver = true"
                @dragleave.prevent="importDragOver = false"
                @drop.prevent="onFileDrop"
                @click="modalFileInput?.click()"
              >
                <input
                  type="file"
                  ref="modalFileInput"
                  class="hidden"
                  accept=".csv,.pdf,application/pdf,text/csv"
                  @change="onModalFileSelect"
                />

                <template v-if="selectedImportFile">
                  <div class="w-12 h-12 rounded-xl flex items-center justify-center font-black text-[13px] uppercase shadow-sm"
                    :class="selectedImportFile.name.endsWith('.pdf') ? 'bg-rose-50 text-rose-600 border border-rose-200' : 'bg-emerald-50 text-emerald-600 border border-emerald-200'">
                    {{ selectedImportFile.name.split('.').pop() }}
                  </div>
                  <div>
                    <p class="text-[13px] font-bold text-slate-800">{{ selectedImportFile.name }}</p>
                    <p class="text-[11px] text-slate-500">{{ (selectedImportFile.size / 1024).toFixed(1) }} KB</p>
                  </div>
                  <button @click.stop="selectedImportFile = null" class="mt-1 text-[11px] font-bold text-rose-500 hover:underline">
                    Choose different file
                  </button>
                </template>

                <template v-else>
                  <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center mb-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                  </div>
                  <p class="text-[13px] font-bold text-slate-700">
                    Click to browse or drag and drop file here
                  </p>
                  <p class="text-[11px] text-slate-400">Supports CSV (.csv) or PDF (.pdf) files</p>
                </template>
              </div>
            </div>
          </div>

          <!-- Footer Actions -->
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50">
            <button
              @click="showImportModal = false"
              class="px-5 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="executeImport()"
              :disabled="!selectedImportFile || isImporting"
              class="flex items-center gap-2 px-6 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-700 rounded-xl transition-colors shadow-sm disabled:opacity-50"
            >
              <svg v-if="isImporting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              {{ isImporting ? 'Importing Instructors...' : 'Upload & Import' }}
            </button>
          </div>
        </div>
      </div>

      <!-- ════════════════ FORMAT ISSUES / ERROR POPUP MODAL ════════════════ -->
      <div v-if="showImportErrorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col my-8 border border-rose-200">
          <!-- Alert Header -->
          <div class="bg-rose-50 px-6 py-5 border-b border-rose-100 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-[16px] font-bold text-rose-900">Import Format Issues Detected</h3>
              <p class="text-[12px] text-rose-700 mt-0.5">{{ importErrorMessage }}</p>
            </div>
            <button @click="showImportErrorModal = false" class="text-rose-400 hover:text-rose-600 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
            <!-- Specific Error List -->
            <div>
              <p class="text-[12px] font-bold text-slate-700 uppercase tracking-wide mb-2">Detected Issue(s):</p>
              <div class="space-y-2 bg-slate-50 border border-slate-200 rounded-xl p-3.5">
                <div v-for="(err, idx) in importErrorList" :key="idx" class="flex items-start gap-2.5 text-[12px] text-slate-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0 mt-1.5"></span>
                  <span class="leading-relaxed">{{ err }}</span>
                </div>
              </div>
            </div>

            <!-- Required Add Instructor Format Reference -->
            <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-4 space-y-2 text-[12px]">
              <p class="font-bold text-[#4338ca]">How to fix this file:</p>
              <ul class="list-disc list-inside text-slate-600 space-y-1 text-[11px]">
                <li>File must include columns: <strong class="text-slate-800">Full Name, Email, Phone, Department</strong></li>
                <li>Department must match one of the system departments: <strong class="text-slate-800" v-for="d in allDepartments" :key="d.id">{{ d.name }}, </strong></li>
                <li>Email addresses must be valid and not already registered</li>
                <li>Phone number is required for every instructor</li>
              </ul>
              <div class="pt-2">
                <button @click="downloadSampleCsv" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#4338ca] text-[#4338ca] hover:bg-indigo-50 font-bold text-[12px] rounded-lg transition-colors shadow-xs">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                  Download Valid Template (.CSV)
                </button>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
            <span class="text-[12px] text-slate-500">Fix the file and try importing again</span>
            <button
              @click="showImportErrorModal = false; showImportModal = true"
              class="px-5 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-700 rounded-xl transition-colors shadow-sm"
            >
              Try Again
            </button>
          </div>
        </div>
      </div>

      <!-- ════════════════ SUCCESS CONFIRMATION POPUP MODAL ════════════════ -->
      <div v-if="showImportSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col my-8 border border-emerald-200">
          <div class="p-6 text-center space-y-4">
            <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto shadow-sm">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div>
              <h3 class="text-[18px] font-bold text-slate-800">Instructors Imported Successfully!</h3>
              <p class="text-[13px] text-slate-500 mt-1">{{ importSuccessMessage }}</p>
            </div>

            <!-- List of imported instructors -->
            <div v-if="importedInstructorsList.length > 0" class="max-h-48 overflow-y-auto border border-slate-100 rounded-xl divide-y divide-slate-100 text-left">
              <div v-for="inst in importedInstructorsList" :key="inst.id" class="p-3 flex items-center justify-between bg-slate-50/50">
                <div>
                  <p class="text-[13px] font-bold text-slate-800">{{ inst.name }}</p>
                  <p class="text-[11px] text-slate-400">{{ inst.email }}</p>
                </div>
                <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-md bg-indigo-50 text-[#4338ca]">
                  {{ inst.department }}
                </span>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end bg-slate-50/50">
            <button
              @click="showImportSuccessModal = false"
              class="w-full sm:w-auto px-6 py-2.5 text-[13px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors shadow-sm"
            >
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
  transition: all 0.3s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(30px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}
</style>