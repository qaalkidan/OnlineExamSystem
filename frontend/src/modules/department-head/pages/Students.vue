<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '../../../core/api/apiClient'

// ── View State ──
const currentView = ref<'list' | 'add' | 'detail' | 'edit'>('list')

const search = ref('')
const statusFilter = ref('all')
const yearFilter = ref('all')
const sectionFilter = ref('all')
const currentPage = ref(1)
const perPage = 8

// ── Add Student Form ──
const addForm = ref({
  fullName: '',
  email: '',
  phone: '',
  dateOfBirth: '',
  gender: '',
  profilePicture: null as File | null,
  studentId: '',
  admissionNumber: '',
  department: '',
  year: '',
  semester: '',
  section: '',
  username: '',
  password: '',
  confirmPassword: '',
})
const isLoading = ref(false)

const resetAddForm = () => {
  addForm.value = {
    fullName: '', email: '', phone: '', dateOfBirth: '', gender: '',
    profilePicture: null, studentId: '', admissionNumber: '', department: '',
    year: '', semester: '', section: '', username: '', password: '', confirmPassword: ''
  }
}

const openAddPage = () => { resetAddForm(); currentView.value = 'add' }
const backToList  = () => { currentView.value = 'list' }

const handleFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) addForm.value.profilePicture = target.files[0]
}

const saveStudent = () => {
  isLoading.value = true
  setTimeout(() => { isLoading.value = false; currentView.value = 'list' }, 1000)
}

// ── Detail & Delete & Edit ──
const selectedStudent = ref<any>(null)
const showDeleteModal = ref(false)

const openView = (stu: any) => {
  selectedStudent.value = stu
  currentView.value = 'detail'
}

const confirmDelete = (stu: any) => {
  selectedStudent.value = stu
  showDeleteModal.value = true
}

const deleteStudent = async () => {
  if (!selectedStudent.value?._rawId) return
  isLoading.value = true
  try {
    await apiClient.delete(`/dept-head/students/${selectedStudent.value._rawId}`)
    showDeleteModal.value = false
    await fetchStudents()
  } catch (err: any) {
    console.error('Failed to delete student:', err)
    alert(err?.response?.data?.message || 'Failed to remove student')
  } finally {
    isLoading.value = false
  }
}

// ── Edit Student Modal ──
const showEditModal = ref(false)
const editPhotoPreview = ref<string | null>(null)
const editFileInput = ref<HTMLInputElement | null>(null)
const editForm = ref({
  fullName: '',
  email: '',
  phone: '',
  dateOfBirth: '',
  gender: '',
  studentId: '',
  admissionNumber: '',
  yearLevel: '',
  section: '',
  status: 'active',
  username: '',
  password: '',
  confirmPassword: '',
  profilePicture: null as File | null,
})

const handleEditFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    editForm.value.profilePicture = file
    const reader = new FileReader()
    reader.onload = (re) => {
      editPhotoPreview.value = re.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const triggerEditFileInput = () => {
  editFileInput.value?.click()
}

const removeEditPhoto = () => {
  editForm.value.profilePicture = null
  editPhotoPreview.value = null
  if (editFileInput.value) editFileInput.value.value = ''
}

const openEditPage = (stu: any) => {
  selectedStudent.value = stu
  editPhotoPreview.value = stu.avatarUrl || null
  editForm.value = {
    fullName: stu.name || '',
    email: stu.email || '',
    phone: stu.phone || '',
    dateOfBirth: stu.date_of_birth || '',
    gender: stu.gender || '',
    studentId: stu.id_no || stu.id || '',
    admissionNumber: stu.id_no || '',
    yearLevel: stu.year_level || stu.year || '',
    section: stu.section || '',
    status: stu.status || 'active',
    username: stu.username || '',
    password: '',
    confirmPassword: '',
    profilePicture: null,
  }
  showEditModal.value = true
}

const editError = ref('')

const saveEditStudent = async () => {
  editError.value = ''
  if (editForm.value.password && editForm.value.password !== editForm.value.confirmPassword) {
    editError.value = 'Passwords do not match'
    return
  }
  isLoading.value = true
  try {
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('name', editForm.value.fullName)
    formData.append('email', editForm.value.email)
    if (editForm.value.phone) formData.append('phone', editForm.value.phone)
    if (editForm.value.dateOfBirth) formData.append('date_of_birth', editForm.value.dateOfBirth)
    if (editForm.value.gender) formData.append('gender', editForm.value.gender)
    if (editForm.value.studentId) formData.append('id_no', editForm.value.studentId)
    if (editForm.value.yearLevel) formData.append('year_level', editForm.value.yearLevel)
    if (editForm.value.section) formData.append('section', editForm.value.section)
    if (editForm.value.status) formData.append('status', editForm.value.status)
    if (editForm.value.username) formData.append('username', editForm.value.username)
    if (editForm.value.password) formData.append('password', editForm.value.password)
    if (editForm.value.profilePicture) formData.append('profile_picture', editForm.value.profilePicture)

    await apiClient.post(`/dept-head/students/${selectedStudent.value._rawId}`, formData)
    showEditModal.value = false
    await fetchStudents()
  } catch (err: any) {
    editError.value = err?.response?.data?.message || 'Failed to update student'
    console.error('Failed to update student:', err)
  } finally {
    isLoading.value = false
  }
}

// ── Avatar URL Resolver ──
const resolveAvatarUrl = (url: string | null | undefined): string | null => {
  if (!url) return null
  if (url.startsWith('http://localhost/') || url.startsWith('http://127.0.0.1/')) {
    return url.replace('http://localhost/', 'http://localhost:8000/').replace('http://127.0.0.1/', 'http://localhost:8000/')
  }
  return url
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
  } catch (err: any) {
    console.error('Export error:', err)
    alert(err?.response?.data?.message || 'Failed to export students. Please try again.')
  } finally {
    isExporting.value = false
  }
}

const allStudents = ref<any[]>([])
const serverStats = ref<{
  total: number
  active: number
  inactive: number
  new_this_semester: number
} | null>(null)

const fetchStudents = async () => {
  try {
    const res = await apiClient.get('/dept-head/students')
    if (res.data?.stats) {
      serverStats.value = res.data.stats
    }
    allStudents.value = (res.data?.data || []).map((s: any) => ({
      _rawId: s.id,
      id: s.id_no || `${s.id}`,
      name: s.name,
      email: s.email,
      phone: s.phone || '',
      gender: s.gender || '',
      date_of_birth: s.date_of_birth || '',
      id_no: s.id_no || '',
      username: s.username || '',
      year_level: s.year_level || '',
      section: s.section || '—',
      year: s.year_level || '—',
      status: s.status || 'active',
      profile_picture: s.profile_picture,
      profile_picture_url: s.profile_picture_url,
      avatarUrl: resolveAvatarUrl(s.profile_picture_url),
      created_at: s.created_at,
      admissionDate: s.created_at ? new Date(s.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—',
      avatar: (s.name || '').split(' ').map((n: string) => n[0]).join('').substring(0, 2).toUpperCase()
    }))
  } catch (err) {
    console.error('Failed to fetch students:', err)
  }
}

onMounted(() => {
  fetchStudents()
  const handleOutsideClick = (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.export-dropdown-container')) {
      showExportDropdown.value = false
    }
  }
  window.addEventListener('click', handleOutsideClick)
})

const filtered = computed(() => {
  return allStudents.value.filter(s => {
    const matchSearch  = s.name.toLowerCase().includes(search.value.toLowerCase()) ||
                         s.id.toLowerCase().includes(search.value.toLowerCase()) ||
                         s.email.toLowerCase().includes(search.value.toLowerCase())
    const matchStatus  = statusFilter.value === 'all' || s.status === statusFilter.value
    const matchYear    = yearFilter.value === 'all' || s.year === yearFilter.value
    const matchSection = sectionFilter.value === 'all' || s.section === sectionFilter.value
    return matchSearch && matchStatus && matchYear && matchSection
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated  = computed(() => filtered.value.slice((currentPage.value - 1) * perPage, currentPage.value * perPage))

// ── Top Stats Cards (Real Data Integration) ──
const stats = computed(() => {
  const total = serverStats.value?.total ?? allStudents.value.length
  const active = serverStats.value?.active ?? allStudents.value.filter(s => (s.status || 'active') === 'active').length
  const inactive = serverStats.value?.inactive ?? allStudents.value.filter(s => s.status === 'inactive').length
  const newCount = serverStats.value?.new_this_semester ?? allStudents.value.filter(s => {
    return s.created_at && (Date.now() - new Date(s.created_at).getTime()) < 180 * 24 * 60 * 60 * 1000
  }).length

  return [
    {
      label: 'Total Students',
      value: total,
      change: newCount > 0 ? `↑ ${newCount} this semester` : (total > 0 ? 'Enrolled students' : 'No students'),
      bg: 'bg-indigo-50',
      ic: 'text-[#5138ed]',
      color: 'text-emerald-500',
      icon: 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'
    },
    {
      label: 'Active Students',
      value: active,
      change: total > 0 ? `${Math.round((active / total) * 100)}% active rate` : '0 active',
      bg: 'bg-emerald-50',
      ic: 'text-emerald-500',
      color: 'text-emerald-500',
      icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
    },
    {
      label: 'New Students',
      value: newCount,
      change: newCount > 0 ? `↑ ${newCount} this semester` : '0 this semester',
      bg: 'bg-amber-50',
      ic: 'text-amber-500',
      color: 'text-emerald-500',
      icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'
    },
    {
      label: 'Inactive Students',
      value: inactive,
      change: inactive > 0 ? `↓ ${inactive} this semester` : '0 inactive',
      bg: 'bg-rose-50',
      ic: 'text-rose-500',
      color: 'text-rose-500',
      icon: 'M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6'
    },
  ]
})

const avatarColor = (name: string) => {
  const colors = ['bg-indigo-500','bg-sky-500','bg-emerald-500','bg-violet-500','bg-amber-500','bg-rose-500','bg-teal-500','bg-orange-500','bg-cyan-500','bg-purple-500']
  return colors[name.charCodeAt(0) % colors.length]
}

const statusBadge = (s: string) =>
  s === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500'

const years    = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year']
const sections = ['A', 'B', 'C', 'D']
const departments = ['Computer Science', 'Software Engineering', 'Information Systems', 'Data Science', 'Cybersecurity']

const displayPages = computed(() => {
  const tp = totalPages.value
  if (tp <= 5) return Array.from({ length: tp }, (_, i) => i + 1)
  const pages: (number | '...')[] = [1, 2, 3]
  if (currentPage.value > 4) pages.push('...')
  if (currentPage.value > 3 && currentPage.value < tp - 1) pages.push(currentPage.value)
  pages.push('...')
  pages.push(tp)
  return pages
})
</script>

<template>
  <div class="space-y-6">

    <!-- ══════════════════════════ ADD STUDENT VIEW ══════════════════════════ -->
    <template v-if="currentView === 'add'">

      <!-- Header with back button -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Add New Student</h1>
        </div>
        <button @click="backToList" class="flex items-center justify-center gap-2 text-[13px] font-bold text-slate-500 border border-slate-200 hover:bg-slate-50 hover:text-slate-700 px-4 py-2.5 min-h-[44px] rounded-xl transition-colors bg-white shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Students
        </button>
      </div>

      <!-- Form Sections -->
      <div class="space-y-6">

        <!-- Personal Information -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <h2 class="text-[15px] font-bold text-slate-800 mb-6">Personal Information</h2>

          <div class="space-y-5">
            <!-- Row 1: Full Name | Email | Phone -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Full Name <span class="text-rose-500">*</span></label>
                <input v-model="addForm.fullName" type="text" placeholder="Enter full name" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Email Address <span class="text-rose-500">*</span></label>
                <input v-model="addForm.email" type="email" placeholder="Enter email address" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Phone Number <span class="text-rose-500">*</span></label>
                <input v-model="addForm.phone" type="text" placeholder="Enter phone number" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
            </div>

            <!-- Row 2: Date of Birth | Gender | Profile Picture -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Date of Birth <span class="text-rose-500">*</span></label>
                <input v-model="addForm.dateOfBirth" type="date" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Gender <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="addForm.gender" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Profile Picture</label>
                <label class="flex flex-col items-center justify-center w-full min-h-[50px] p-2 border-2 border-dashed border-slate-200 rounded-xl cursor-pointer hover:border-[#5138ed] hover:bg-indigo-50/30 transition-colors group">
                  <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-400 group-hover:text-[#5138ed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    <span class="text-[12px] font-bold text-slate-600 group-hover:text-[#5138ed] truncate max-w-[180px]">{{ addForm.profilePicture ? addForm.profilePicture.name : 'Upload Photo' }}</span>
                  </div>
                  <p class="text-[10px] text-slate-400">PNG, JPG up to 2MB</p>
                  <input type="file" accept="image/*" @change="handleFileUpload" class="hidden" />
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Academic Information -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <h2 class="text-[15px] font-bold text-slate-800 mb-6">Academic Information</h2>

          <div class="space-y-5">
            <!-- Row 1: Student ID | Admission Number | Department -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Student ID <span class="text-rose-500">*</span></label>
                <input v-model="addForm.studentId" type="text" placeholder="Enter student ID" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Admission Number <span class="text-rose-500">*</span></label>
                <input v-model="addForm.admissionNumber" type="text" placeholder="Enter admission no." class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Department <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="addForm.department" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select department</option>
                    <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
            </div>

            <!-- Row 2: Year | Semester | Section -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Year <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="addForm.year" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
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
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Semester <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="addForm.semester" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-shadow">
                    <option value="">Select semester</option>
                    <option value="Semester 1">Semester 1</option>
                    <option value="Semester 2">Semester 2</option>
                    <option value="Summer">Summer</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Section <span class="text-rose-500">*</span></label>
                <input v-model="addForm.section" type="text" placeholder="Enter section" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
            </div>
          </div>
        </div>

        <!-- Account Information -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-7 md:p-8">
          <h2 class="text-[15px] font-bold text-slate-800 mb-6">Account Information</h2>
          <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Username <span class="text-rose-500">*</span></label>
                <input v-model="addForm.username" type="text" placeholder="Enter username" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Password <span class="text-rose-500">*</span></label>
                <input v-model="addForm.password" type="password" placeholder="Enter password" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
              <div>
                <label class="block text-[12px] font-semibold text-slate-700 mb-2">Confirm Password <span class="text-rose-500">*</span></label>
                <input v-model="addForm.confirmPassword" type="password" placeholder="Confirm password" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400 transition-shadow" />
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center justify-end gap-3 sm:gap-4 pb-4">
          <button @click="backToList" class="flex items-center gap-2 px-5 sm:px-6 py-2.5 sm:py-3 min-h-[44px] text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            Cancel
          </button>
          <button @click="saveStudent" :disabled="isLoading" class="flex items-center justify-center gap-2 px-5 sm:px-6 py-2.5 sm:py-3 min-h-[44px] text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl shadow-sm shadow-indigo-200 transition-all disabled:opacity-70 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            {{ isLoading ? 'Saving...' : 'Save Student' }}
          </button>
        </div>

      </div>
    </template>

    <!-- ══════════════════════════ DETAIL VIEW ══════════════════════════ -->
    <template v-else-if="currentView === 'detail' && selectedStudent">
      <div class="space-y-6">
        
        <!-- Breadcrumbs & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Student Details</h1>
            <div class="flex items-center gap-1 text-[12px] text-slate-400 mt-1">
              <button @click="backToList" class="hover:text-[#5138ed] transition-colors">Students</button>
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              <span class="text-slate-600 font-medium">Student Details</span>
            </div>
          </div>
          <button @click="backToList" class="flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] text-[12px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors bg-white shadow-xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Students
          </button>
        </div>

        <!-- Top Profile Section -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-6 md:p-8">
          <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 sm:gap-6">
              <div class="relative">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden bg-slate-100 border-4 border-white shadow-md flex items-center justify-center shrink-0">
                  <img
                    v-if="selectedStudent.avatarUrl || selectedStudent.profile_picture_url"
                    :src="resolveAvatarUrl(selectedStudent.avatarUrl || selectedStudent.profile_picture_url) || ''"
                    :alt="selectedStudent.name"
                    class="w-full h-full object-cover"
                    @error="(e: any) => { e.target.style.display = 'none'; (e.target.nextElementSibling as HTMLElement)?.classList.remove('hidden') }"
                  />
                  <div
                    :class="[avatarColor(selectedStudent.name), 'w-full h-full flex items-center justify-center text-[24px] sm:text-[28px] font-bold text-white', (selectedStudent.avatarUrl || selectedStudent.profile_picture_url) ? 'hidden' : '']"
                  >
                    {{ selectedStudent.avatar }}
                  </div>
                </div>
                <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 flex items-center gap-1 bg-white border border-slate-100 shadow-sm px-2.5 py-0.5 rounded-full">
                  <div :class="[selectedStudent.status === 'active' ? 'bg-emerald-500' : 'bg-rose-500', 'w-1.5 h-1.5 rounded-full']"></div>
                  <span :class="[selectedStudent.status === 'active' ? 'text-emerald-600' : 'text-rose-500', 'text-[10px] font-bold capitalize']">{{ selectedStudent.status }}</span>
                </div>
              </div>
              <div>
                <h2 class="text-xl sm:text-[24px] font-bold text-slate-800 leading-tight">{{ selectedStudent.name }}</h2>
                <p class="text-[14px] text-slate-500 mb-3">{{ selectedStudent.id }}</p>
                <div class="flex flex-col gap-2">
                  <div class="flex items-center gap-2 text-[13px] text-slate-600">
                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span class="break-all">{{ selectedStudent.email }}</span>
                  </div>
                  <div class="flex items-center gap-2 text-[13px] text-slate-600">
                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    {{ selectedStudent.phone || '—' }}
                  </div>
                </div>
              </div>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6 w-full lg:w-auto pt-4 lg:pt-0 border-t lg:border-t-0 border-slate-100 pr-0 lg:pr-6">
              <div class="flex items-start gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-indigo-50 flex items-center justify-center mt-1 shrink-0">
                  <svg class="w-4 h-4 sm:w-5 sm:h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <div>
                  <p class="text-[11px] font-semibold text-slate-500 mb-0.5">Year Level</p>
                  <p class="text-[13.5px] font-bold text-slate-800">{{ selectedStudent.year_level || '—' }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">Current Level</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-50 flex items-center justify-center mt-1 shrink-0">
                  <svg class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                </div>
                <div>
                  <p class="text-[11px] font-semibold text-slate-500 mb-0.5">Section</p>
                  <p class="text-[13.5px] font-bold text-slate-800">{{ selectedStudent.section || '—' }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">Section</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-amber-50 flex items-center justify-center mt-1 shrink-0">
                  <svg class="w-4 h-4 sm:w-5 sm:h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                  <p class="text-[11px] font-semibold text-slate-500 mb-0.5">Admission</p>
                  <p class="text-[13.5px] font-bold text-slate-800 truncate">{{ selectedStudent.admissionDate }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">Enrolled</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-sky-50 flex items-center justify-center mt-1 shrink-0">
                  <svg class="w-4 h-4 sm:w-5 sm:h-5 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                  <p class="text-[11px] font-semibold text-slate-500 mb-0.5">Status</p>
                  <p class="text-[13.5px] font-bold text-slate-800 capitalize">{{ selectedStudent.status }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">Status</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 3 Columns Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
          
          <!-- Personal Information -->
          <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-6">
            <div class="flex items-center gap-2 mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
              <h3 class="text-[14px] font-bold text-slate-800">Personal Information</h3>
            </div>
            <div class="space-y-4">
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Full Name</span>
                <span class="text-[13px] font-semibold text-slate-800 text-right">{{ selectedStudent.name }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Email Address</span>
                <span class="text-[13px] font-medium text-slate-700 text-right break-all">{{ selectedStudent.email }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Phone Number</span>
                <span class="text-[13px] font-medium text-slate-700 text-right">{{ selectedStudent.phone || '—' }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Date of Birth</span>
                <span class="text-[13px] font-medium text-slate-700 text-right">{{ selectedStudent.date_of_birth || '—' }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Gender</span>
                <span class="text-[13px] font-medium text-slate-700 text-right">{{ selectedStudent.gender || '—' }}</span>
              </div>
              <div class="flex justify-between items-center gap-2">
                <span class="text-[12px] text-slate-500">Profile Picture</span>
                <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-white shadow-xs shrink-0 flex items-center justify-center bg-slate-100">
                  <img
                    v-if="selectedStudent.avatarUrl || selectedStudent.profile_picture_url"
                    :src="resolveAvatarUrl(selectedStudent.avatarUrl || selectedStudent.profile_picture_url) || ''"
                    :alt="selectedStudent.name"
                    class="w-full h-full object-cover"
                    @error="(e: any) => { e.target.style.display = 'none'; (e.target.nextElementSibling as HTMLElement)?.classList.remove('hidden') }"
                  />
                  <div
                    :class="[avatarColor(selectedStudent.name), 'w-full h-full flex items-center justify-center text-[12px] font-bold text-white', (selectedStudent.avatarUrl || selectedStudent.profile_picture_url) ? 'hidden' : '']"
                  >
                    {{ selectedStudent.avatar }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Academic Information -->
          <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-6">
            <div class="flex items-center gap-2 mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
              <h3 class="text-[14px] font-bold text-slate-800">Academic Information</h3>
            </div>
            <div class="space-y-4">
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Student ID</span>
                <span class="text-[13px] font-semibold text-slate-800 text-right">{{ selectedStudent.id_no || selectedStudent.id }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Academic Year Level</span>
                <span class="text-[13px] font-medium text-slate-700 text-right">{{ selectedStudent.year_level || '—' }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Section</span>
                <span class="text-[13px] font-medium text-slate-700 text-right">{{ selectedStudent.section || '—' }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Admission Date</span>
                <span class="text-[13px] font-medium text-slate-700 text-right">{{ selectedStudent.admissionDate }}</span>
              </div>
            </div>
          </div>

          <!-- Account Information -->
          <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 sm:p-6 md:col-span-2 lg:col-span-1">
            <div class="flex items-center gap-2 mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
              <h3 class="text-[14px] font-bold text-slate-800">Account Information</h3>
            </div>
            <div class="space-y-4">
              <div class="flex justify-between items-center gap-2">
                <span class="text-[12px] text-slate-500">Account Status</span>
                <div>
                  <span :class="[selectedStudent.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500', 'text-[10px] font-bold px-2 py-0.5 rounded capitalize']">{{ selectedStudent.status }}</span>
                </div>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Username</span>
                <span class="text-[13px] font-medium text-slate-700 text-right break-all">{{ selectedStudent.username || '—' }}</span>
              </div>
              <div class="flex justify-between items-start gap-2">
                <span class="text-[12px] text-slate-500">Member Since</span>
                <span class="text-[13px] font-medium text-slate-700 text-right">{{ selectedStudent.admissionDate }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </template>



    <!-- ══════════════════════════ LIST VIEW ══════════════════════════ -->
    <template v-else>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Students</h1>
          <p class="text-xs sm:text-[13px] text-slate-500 mt-1">Manage and monitor students in your department.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <div class="relative export-dropdown-container">
            <button
              type="button"
              @click="showExportDropdown = !showExportDropdown"
              :disabled="isExporting"
              class="flex items-center gap-2 text-[13px] font-bold text-[#5138ed] border border-indigo-200 hover:bg-indigo-50 px-4 py-2.5 min-h-[44px] rounded-xl transition-colors bg-white shadow-xs cursor-pointer disabled:opacity-60"
            >
              <svg v-if="!isExporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
              <svg v-else class="animate-spin w-4 h-4 text-[#5138ed]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <span>{{ isExporting ? 'Exporting...' : 'Export' }}</span>
              <svg class="w-3.5 h-3.5 text-[#5138ed]/70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
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

      <!-- KPI Cards -->
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

        <!-- Filter Row -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 px-4 sm:px-6 py-4 border-b border-slate-100">
          <div class="relative w-full lg:max-w-xs">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input v-model="search" type="text" placeholder="Search students by name, email or ID..." class="w-full pl-9 pr-4 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400">
          </div>
          <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
            <div class="relative flex-1 sm:flex-initial min-w-[120px]">
              <select v-model="statusFilter" class="w-full pl-4 pr-8 py-2.5 text-[13px] font-medium border border-slate-200 rounded-xl text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed]">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div class="relative flex-1 sm:flex-initial min-w-[120px]">
              <select v-model="yearFilter" class="w-full pl-4 pr-8 py-2.5 text-[13px] font-medium border border-slate-200 rounded-xl text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed]">
                <option value="all">All Years</option>
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div class="relative flex-1 sm:flex-initial min-w-[120px]">
              <select v-model="sectionFilter" class="w-full pl-4 pr-8 py-2.5 text-[13px] font-medium border border-slate-200 rounded-xl text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed]">
                <option value="all">All Sections</option>
                <option v-for="s in sections" :key="s" :value="s">{{ s }}</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto min-w-0 w-full">
          <table class="w-full min-w-[700px]">
            <thead>
              <tr class="border-b border-slate-100 bg-white">
                <th class="text-left px-5 lg:px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Student</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">ID</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Email</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Section</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Year</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                <th class="text-left px-3 lg:px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Admission Date</th>
                <th class="text-center px-5 lg:px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="stu in paginated" :key="stu.id" class="hover:bg-slate-50/40 transition-colors group">
                <td class="px-5 lg:px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 border border-slate-200/80 shadow-xs flex items-center justify-center bg-slate-100">
                      <img
                        v-if="stu.avatarUrl"
                        :src="stu.avatarUrl"
                        :alt="stu.name"
                        class="w-full h-full object-cover"
                        @error="(e: any) => { e.target.style.display = 'none'; (e.target.nextElementSibling as HTMLElement)?.classList.remove('hidden') }"
                      />
                      <div
                        :class="[avatarColor(stu.name), 'w-full h-full flex items-center justify-center text-[11px] font-bold text-white', stu.avatarUrl ? 'hidden' : '']"
                      >
                        {{ stu.avatar }}
                      </div>
                    </div>
                    <p class="text-[13px] font-bold text-slate-800">{{ stu.name }}</p>
                  </div>
                </td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] font-semibold text-slate-600">{{ stu.id }}</span></td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] text-slate-600 truncate max-w-[150px] block">{{ stu.email }}</span></td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] text-slate-600">{{ stu.section }}</span></td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] font-semibold text-slate-600">{{ stu.year }}</span></td>
                <td class="px-3 lg:px-4 py-4">
                  <span :class="[statusBadge(stu.status), 'text-[11px] font-bold px-2.5 py-1 rounded-md capitalize']">{{ stu.status === 'active' ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="px-3 lg:px-4 py-4"><span class="text-[12px] text-slate-500">{{ stu.admissionDate }}</span></td>
                <td class="px-5 lg:px-6 py-4">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="openView(stu)" title="View Details" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                    <button @click="openEditPage(stu)" title="Edit Student" class="w-8 h-8 rounded-lg flex items-center justify-center text-sky-500 bg-sky-50 hover:bg-sky-100 transition-colors"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                    <button @click="confirmDelete(stu)" title="Remove Student" class="w-8 h-8 rounded-lg flex items-center justify-center text-rose-500 bg-rose-50 hover:bg-rose-100 transition-colors"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                  </div>
                </td>
              </tr>
              <tr v-if="paginated.length === 0">
                <td colspan="8" class="px-6 py-12 text-center text-[13px] text-slate-400">No students found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-for="stu in paginated" :key="stu.id" class="p-4 hover:bg-slate-50/60 transition-colors space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 border border-slate-200/80 shadow-xs flex items-center justify-center bg-slate-100">
                  <img
                    v-if="stu.avatarUrl"
                    :src="stu.avatarUrl"
                    :alt="stu.name"
                    class="w-full h-full object-cover"
                    @error="(e: any) => { e.target.style.display = 'none'; (e.target.nextElementSibling as HTMLElement)?.classList.remove('hidden') }"
                  />
                  <div
                    :class="[avatarColor(stu.name), 'w-full h-full flex items-center justify-center text-[12px] font-bold text-white', stu.avatarUrl ? 'hidden' : '']"
                  >
                    {{ stu.avatar }}
                  </div>
                </div>
                <div class="min-w-0">
                  <p class="text-[14px] font-bold text-slate-800 truncate">{{ stu.name }}</p>
                  <p class="text-[11px] font-medium text-slate-400 truncate">{{ stu.email }}</p>
                  <p class="text-[11px] font-semibold text-slate-500 mt-0.5">{{ stu.id }}</p>
                </div>
              </div>
              <span :class="[statusBadge(stu.status), 'text-[10px] font-bold px-2 py-0.5 rounded capitalize shrink-0']">
                {{ stu.status === 'active' ? 'Active' : 'Inactive' }}
              </span>
            </div>

            <!-- Details chips -->
            <div class="grid grid-cols-3 gap-2 text-[11px] bg-slate-50/80 rounded-xl p-2.5 border border-slate-100">
              <div>
                <span class="text-slate-400 block text-[10px]">Year</span>
                <span class="font-bold text-slate-700">{{ stu.year || '—' }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px]">Section</span>
                <span class="font-bold text-slate-700">{{ stu.section || '—' }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px]">Enrolled</span>
                <span class="font-bold text-slate-700 truncate block">{{ stu.admissionDate }}</span>
              </div>
            </div>

            <!-- Mobile Actions -->
            <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
              <button @click="openView(stu)" class="flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-lg min-h-[38px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Details
              </button>
              <button @click="openEditPage(stu)" class="flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-bold text-sky-600 bg-sky-50 hover:bg-sky-100 rounded-lg min-h-[38px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                Edit
              </button>
              <button @click="confirmDelete(stu)" class="flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg min-h-[38px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Remove
              </button>
            </div>
          </div>
          <div v-if="paginated.length === 0" class="p-8 text-center text-[13px] text-slate-400">
            No students found.
          </div>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-4 sm:px-6 py-4 sm:py-5 border-t border-slate-100 bg-white">
          <p class="text-xs sm:text-[13px] text-slate-500 font-medium text-center sm:text-left">
            Showing {{ Math.min((currentPage - 1) * perPage + 1, filtered.length) }} to {{ Math.min(currentPage * perPage, filtered.length) }} of {{ filtered.length }} students
          </p>
          <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap justify-center">
            <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <template v-for="p in displayPages" :key="p">
              <span v-if="p === '...'" class="w-8 h-8 flex items-center justify-center text-slate-400 text-[13px]">...</span>
              <button v-else @click="currentPage = (p as number)" :class="[currentPage === p ? 'bg-[#5138ed] text-white border border-[#5138ed]' : 'text-slate-500 border border-slate-200 hover:bg-slate-50', 'w-8 h-8 rounded-lg text-[13px] font-bold transition-colors']">{{ p }}</button>
            </template>
            <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>
        </div>
      </div>

    </template>

    <!-- ── Delete Modal ── -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-sm overflow-hidden text-center p-5 sm:p-6">
          <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4"><svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg></div>
          <h3 class="text-[16px] font-bold text-slate-800 mb-2">Remove Student?</h3>
          <p class="text-[13px] text-slate-500 mb-6">Are you sure you want to remove <span class="font-bold text-slate-700">{{ selectedStudent?.name }}</span>?</p>
          <div class="flex gap-3">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 min-h-[44px] text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="deleteStudent" :disabled="isLoading" class="flex-1 py-2.5 min-h-[44px] text-[13px] font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-xl transition-colors disabled:opacity-70">
              {{ isLoading ? 'Removing...' : 'Remove' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Edit Modal ── -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-3xl overflow-hidden p-5 sm:p-7 md:p-8 max-h-[92vh] overflow-y-auto">
          <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-4">
            <h2 class="text-lg sm:text-[20px] font-bold text-slate-800">Edit Student</h2>
            <button @click="showEditModal = false" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <div v-if="editError" class="mb-4 p-3 bg-rose-50 text-rose-600 text-sm rounded-lg border border-rose-100">
            {{ editError }}
          </div>

          <div class="space-y-6">
            <!-- Personal Information -->
            <div>
              <h3 class="text-[15px] font-bold text-slate-800 mb-4 flex items-center gap-2"><svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>Personal Information</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Full Name</label>
                  <input v-model="editForm.fullName" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Email</label>
                  <input v-model="editForm.email" type="email" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Phone Number</label>
                  <input v-model="editForm.phone" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Date of Birth</label>
                  <input v-model="editForm.dateOfBirth" type="date" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Gender</label>
                  <select v-model="editForm.gender" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] bg-white focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Status</label>
                  <select v-model="editForm.status" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] bg-white focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="col-span-1 md:col-span-2">
                  <div class="flex items-center justify-between mb-1.5">
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
                    class="relative flex items-center gap-4 p-3.5 border-2 border-dashed border-slate-200 hover:border-[#5138ed] hover:bg-indigo-50/20 rounded-xl cursor-pointer transition-colors"
                  >
                    <input
                      ref="editFileInput"
                      type="file"
                      accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                      @change="handleEditFileUpload"
                      class="hidden"
                    />
                    <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-white shadow-xs shrink-0 flex items-center justify-center bg-slate-100">
                      <img
                        v-if="editPhotoPreview"
                        :src="editPhotoPreview"
                        alt="Photo Preview"
                        class="w-full h-full object-cover"
                      />
                      <svg
                        v-else
                        class="w-6 h-6 text-slate-300"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <template v-if="editPhotoPreview">
                        <p class="text-[12.5px] font-bold text-slate-800 truncate">{{ editForm.profilePicture?.name || 'Current Profile Photo' }}</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Click to choose a different photo</p>
                      </template>
                      <template v-else>
                        <p class="text-[12.5px] font-bold text-slate-700">Upload Profile Photo</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">PNG, JPG, WEBP up to 2MB (Optional)</p>
                      </template>
                    </div>
                    <span class="px-3 py-1.5 rounded-lg border border-slate-200 text-[11.5px] font-bold text-slate-600 bg-white shadow-xs">
                      {{ editPhotoPreview ? 'Change' : 'Browse' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Academic Information -->
            <div>
              <h3 class="text-[15px] font-bold text-slate-800 mb-4 flex items-center gap-2 mt-6"><svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>Academic Information</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Student ID / Admission Number</label>
                  <input v-model="editForm.studentId" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Academic Year</label>
                  <input v-model="editForm.yearLevel" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Section</label>
                  <input v-model="editForm.section" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
              </div>
            </div>

            <!-- Account Information -->
            <div>
              <h3 class="text-[15px] font-bold text-slate-800 mb-4 flex items-center gap-2 mt-6"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>Account Information</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Username</label>
                  <input v-model="editForm.username" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Password</label>
                  <input v-model="editForm.password" type="password" placeholder="Leave blank to keep" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
                <div>
                  <label class="block text-[12px] font-semibold text-slate-700 mb-1">Confirm Password</label>
                  <input v-model="editForm.confirmPassword" type="password" placeholder="Leave blank to keep" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]" />
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
              <button @click="showEditModal = false" class="px-6 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
              <button @click="saveEditStudent" :disabled="isLoading" class="flex items-center gap-2 px-6 py-2.5 text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-700 rounded-xl transition-all disabled:opacity-70">
                <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ isLoading ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
