<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useSettingsStore } from '../../../store/settingsStore'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { Doughnut } from 'vue-chartjs'

ChartJS.register(ArcElement, Tooltip, Legend)

const search = ref('')
const deptFilter = ref('all')
const statusFilter = ref('all')
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
const isLoading = ref(false)

const sectionOptions = ['Section A', 'Section B', 'Both Sections']

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
  const name = coInst.name
  return `${name} is currently assigned (Section B)`
})

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
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
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
      avatar: user.avatar || null
    })
  }

  // 1. Direct assigned_instructors relationship from backend
  const fromRelation = course.assigned_instructors || course.assignedInstructors || []
  if (Array.isArray(fromRelation)) {
    fromRelation.forEach((u: any) => addInstructor(u, u.section || 'Section A'))
  }

  // 2. Primary instructor
  if (course.instructor && typeof course.instructor === 'object' && course.instructor.id) {
    const defaultSec = course.section === 'Section B' ? 'Section B' : 'Section A'
    addInstructor(course.instructor, course.instructor.section || defaultSec)
  } else if (course.instructor_id) {
    const inst = allInstructors.value.find(i => i.id === course.instructor_id)
    if (inst) addInstructor(inst, inst.section || 'Section A')
  }

  // 3. Co-instructor
  const co = course.co_instructor || course.coInstructor
  if (co && typeof co === 'object' && co.id) {
    addInstructor(co, co.section || 'Section B')
  } else if (course.co_instructor_id) {
    const coInst = allInstructors.value.find(i => i.id === course.co_instructor_id)
    if (coInst) addInstructor(coInst, coInst.section || 'Section B')
  }

  // 4. Any instructors in allInstructors whose course_code matches course.code
  if (course.code && allInstructors.value?.length) {
    const cCode = String(course.code).trim().toUpperCase()
    allInstructors.value.forEach((inst: any) => {
      if (inst.course_code && String(inst.course_code).trim().toUpperCase() === cCode) {
        addInstructor(inst, inst.section || 'Section A')
      }
    })
  }

  const list = Array.from(map.values())
  // Differentiate section if two instructors got the same fallback section
  if (list.length === 2 && list[0].section === list[1].section) {
    const coId = course.co_instructor_id || (course.co_instructor?.id || course.coInstructor?.id)
    if (coId && list[1].id === coId) {
      list[1].section = 'Section B'
    } else if (list[0].id === coId) {
      list[0].section = 'Section B'
    } else {
      list[1].section = 'Section B'
    }
  }

  list.sort((a, b) => (a.section || '').localeCompare(b.section || '') || (a.name || '').localeCompare(b.name || ''))
  return list
}

const selectedCourseInstructors = computed(() => {
  return getCourseInstructors(selectedCourse.value)
})

const settingsStore = useSettingsStore()

const allCourses = ref<any[]>([])
const allDepartments = ref<any[]>([])
const allInstructors = ref<any[]>([])

const newCourseForm = ref({
  code: '',
  title: '',
  type: '',
  department_id: '',
  program: '',
  level: '',
  semester: '',
  credits: '',
  language: '',
  short_description: '',
  full_description: '',
  instructor_id: '',
  co_instructors: '',
  capacity: '',
  enrollment_status: 'Open for Enrollment',
  visibility: 'Visible to Students',
  start_date: '',
  end_date: '',
  status: 'active'
})

const currentPage = ref(1)

const fetchCourses = async () => {
  try {
    const res = await apiClient.get('/admin/courses')
    allCourses.value = (res.data.data || []).map((c: any) => {
      const instructors = getCourseInstructors(c)
      const primaryInstructor = instructors.length > 0 ? instructors[0].name : (c.instructor?.name || 'Unassigned')
      return {
        ...c,
        name: c.title,
        dept: c.department?.name || '—',
        departmentName: c.department?.name || '—',
        instructor: primaryInstructor,
        instructorsCount: instructors.length, 
        students: 0, 
        exams: 0,
        status: c.status || 'active',
        semester: c.semester,
        credits: c.credits || '—',
        level: c.level,
        created_by: c.creator?.role === 'dept_head' || c.creator?.role === 'department_head' ? 'Dept. Head' : (c.creator?.role === 'admin' ? 'Admin' : 'Unknown'),
        created_on: c.created_at ? formatDate(c.created_at) : '—'
      }
    })
  } catch (err) { console.error('Failed to fetch courses:', err) }
}

const fetchDepartments = async () => {
  try {
    const res = await apiClient.get('/admin/departments')
    allDepartments.value = res.data.data || []
  } catch (err) { console.error('Failed to fetch departments:', err) }
}

const fetchInstructors = async () => {
  try {
    const res = await apiClient.get('/admin/instructors')
    allInstructors.value = res.data.data || []
  } catch (err) { console.error('Failed to fetch instructors:', err) }
}

onMounted(async () => {
  await Promise.all([fetchDepartments(), fetchInstructors()])
  await fetchCourses()
})

const formAvailableInstructors = computed(() => {
  if (!newCourseForm.value.department_id) return []
  return allInstructors.value.filter(i => i.department_id === newCourseForm.value.department_id)
})

const instructorsForAssign = computed(() => {
  if (!courseToAssign.value?.department_id) return []
  return allInstructors.value.filter(i => i.department_id === courseToAssign.value.department_id)
})

const availableInstructors = computed(() => {
  return instructorsForAssign.value.filter(inst => inst.id !== assignCoInstructorId.value)
})

const availableCoInstructors = computed(() => {
  return instructorsForAssign.value.filter(inst => inst.id !== assignInstructorId.value)
})

const levelFilter = ref('all')

const filtered = computed(() =>
  allCourses.value.filter(c => {
    const matchSearch = (c.name && c.name.toLowerCase().includes(search.value.toLowerCase())) ||
                        (c.code && c.code.toLowerCase().includes(search.value.toLowerCase())) ||
                        (c.instructor && c.instructor.toLowerCase().includes(search.value.toLowerCase()))
    const matchDept = deptFilter.value === 'all' || c.departmentName === deptFilter.value
    const matchStatus = statusFilter.value === 'all' || c.status === statusFilter.value
    const matchLevel = levelFilter.value === 'all' || c.level === levelFilter.value
    return matchSearch && matchDept && matchStatus && matchLevel
  })
)

const perPage = 10
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

const visiblePages = computed(() => {
  const pages: (number | string)[] = []
  const total = totalPages.value
  const current = currentPage.value
  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    if (current <= 4) {
      for (let i = 1; i <= 5; i++) pages.push(i)
      pages.push('...')
      pages.push(total)
    } else if (current >= total - 3) {
      pages.push(1)
      pages.push('...')
      for (let i = total - 4; i <= total; i++) pages.push(i)
    } else {
      pages.push(1)
      pages.push('...')
      pages.push(current - 1)
      pages.push(current)
      pages.push(current + 1)
      pages.push('...')
      pages.push(total)
    }
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
watch([search, deptFilter, statusFilter, levelFilter], () => {
  currentPage.value = 1
})

// Ensure currentPage stays within valid bounds if dataset changes
watch(filtered, (newVal) => {
  const max = Math.max(1, Math.ceil(newVal.length / perPage))
  if (currentPage.value > max) {
    currentPage.value = max
  }
})

const stats = computed(() => {
  const total = allCourses.value.length || 0
  const active = allCourses.value.filter(c => c.status === 'active').length || 0
  return {
    total,
    active,
    inactive: total - active,
    newCourses: allCourses.value.filter(c => {
      const createdDate = new Date(c.created_at || c.created_on)
      const now = new Date()
      return (now.getTime() - createdDate.getTime()) < 30 * 24 * 60 * 60 * 1000
    }).length,
    published: active 
  }
})


// ── Course Form Validation State ──
const courseFormErrors = ref<Record<string, string>>({})
const courseFormTouched = ref<Record<string, boolean>>({})

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
        e.department_id = 'Department is required. Please select one.'
      } else {
        clear()
      }
      break

    case 'level':
      if (!f.level) {
        e.level = 'Academic Year Level is required. Please select one.'
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
        e.credits = 'Credits must be a whole number between 1 and 30.'
      } else {
        clear()
      }
      break
    }

    case 'end_date':
      if (f.start_date && f.end_date) {
        if (new Date(f.end_date) < new Date(f.start_date)) {
          e.end_date = 'End date must be on or after start date.'
        } else {
          clear()
        }
      } else {
        clear()
      }
      break

    case 'start_date':
      if (f.start_date && f.end_date) {
        if (new Date(f.end_date) < new Date(f.start_date)) {
          e.end_date = 'End date must be on or after start date.'
        } else {
          delete e.end_date
        }
      }
      clear()
      break
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
  if (newCourseForm.value.start_date || newCourseForm.value.end_date) {
    validateCourseField('end_date')
  }
  const hasErrors = Object.keys(courseFormErrors.value).some(k => k !== '_server')
  return !hasErrors
}

const courseFieldCls = (field: string, extra = '') => {
  const base = `w-full border rounded-xl px-4 py-3 text-[13px] text-slate-700 focus:outline-none transition-colors ${extra}`
  if (courseFormTouched.value[field]) {
    if (courseFormErrors.value[field]) return base + ' border-rose-400 bg-rose-50/30 focus:border-rose-500'
    return base + ' border-emerald-400 bg-emerald-50/20 focus:border-emerald-500'
  }
  return base + ' border-slate-200 focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca]'
}

const openAddPage = () => {
  isEditing.value = false
  resetCourseValidation()
  newCourseForm.value = {
    code: '', title: '', type: '', department_id: '', program: '', level: '',
    semester: settingsStore.semester || 'Semester 1',
    credits: '3', language: '', short_description: '', full_description: '',
    instructor_id: '', co_instructors: '', capacity: '',
    enrollment_status: 'Open for Enrollment', visibility: 'Visible to Students',
    start_date: '', end_date: '', status: 'active'
  }
  showAddPage.value = true
}

const openEditPage = (c: any) => {
  isEditing.value = true
  resetCourseValidation()
  selectedCourse.value = c
  newCourseForm.value = {
    ...newCourseForm.value,
    code: c.code || '',
    title: c.name || c.title || '',
    department_id: c.department_id || '',
    semester: c.semester || settingsStore.semester || 'Semester 1',
    level: c.level || '',
    credits: String(c.credits !== '—' && c.credits ? c.credits : '3'),
    instructor_id: c.instructor_id || '',
    enrollment_status: c.enrollment_status || 'Open for Enrollment',
    visibility: c.visibility || 'Visible to Students',
    start_date: c.start_date ? c.start_date.substring(0, 10) : '',
    end_date: c.end_date ? c.end_date.substring(0, 10) : '',
    status: c.status || 'active'
  }
  showEditModal.value = true
}

const openDetailsPage = async (c: any) => {
  selectedCourse.value = c
  showDetailsPage.value = true
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
          instructor: instructors.length > 0 ? instructors[0].name : (item.instructor?.name || 'Unassigned')
        }
      }
    } catch (e) {
      console.error('Failed to load fresh course details:', e)
    }
  }
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
      semester: newCourseForm.value.semester || settingsStore.semester || 'Semester 1',
      level: newCourseForm.value.level,
      start_date: newCourseForm.value.start_date || null,
      end_date: newCourseForm.value.end_date || null,
      visibility: newCourseForm.value.visibility || 'Visible to Students',
      enrollment_status: newCourseForm.value.enrollment_status || 'Open for Enrollment',
      status: newCourseForm.value.status || 'active'
    }

    if (isEditing.value && selectedCourse.value) {
      await apiClient.put(`/admin/courses/${selectedCourse.value.id}`, payload)
    } else {
      await apiClient.post('/admin/courses', payload)
    }
    await fetchCourses()
    showAddPage.value = false
    showEditModal.value = false
    resetCourseValidation()
  } catch (err: any) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      const backendErrors = err.response.data.errors
      Object.keys(backendErrors).forEach(key => {
        courseFormTouched.value[key] = true
        courseFormErrors.value[key] = Array.isArray(backendErrors[key]) ? backendErrors[key][0] : backendErrors[key]
      })
    } else {
      courseFormErrors.value._server = err.response?.data?.message || (isEditing.value ? 'Failed to update course.' : 'Failed to create course.')
    }
  } finally {
    isLoading.value = false
  }
}

const confirmDelete = (c: any) => { selectedCourse.value = c; showDeleteModal.value = true }
const deleteCourse  = async () => {
  if (!selectedCourse.value) return
  isLoading.value = true
  try {
    await apiClient.delete(`/admin/courses/${selectedCourse.value.id}`)
    await fetchCourses()
    showDeleteModal.value = false
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete course.')
  } finally {
    isLoading.value = false
  }
}


const openAssign = (course: any) => {
  courseToAssign.value = course
  assignSection.value = course.section || 'Both Sections'
  assignInstructorId.value = course.instructor_id || (course.instructor?.id || '')
  assignCoInstructorId.value = course.co_instructor_id || (course.co_instructor?.id || course.coInstructor?.id || '')
  showAssignModal.value = true
}

const assignInstructor = async () => {
  if (!courseToAssign.value) return
  isLoading.value = true
  try {
    const res = await apiClient.put(`/admin/courses/${courseToAssign.value.id}`, {
      instructor_id: assignInstructorId.value || null,
      co_instructor_id: assignCoInstructorId.value || null,
      section: assignSection.value || null
    })
    await fetchInstructors()
    await fetchCourses()
    if (selectedCourse.value && selectedCourse.value.id === courseToAssign.value.id) {
      const refreshed = allCourses.value.find(c => c.id === selectedCourse.value.id)
      if (refreshed) {
        selectedCourse.value = refreshed
      } else if (res.data?.data) {
        const item = res.data.data
        selectedCourse.value = {
          ...selectedCourse.value,
          ...item,
          name: item.title || selectedCourse.value.name,
          dept: item.department?.name || selectedCourse.value.dept,
          departmentName: item.department?.name || selectedCourse.value.departmentName
        }
      }
    }
    showAssignModal.value = false
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to assign instructor.')
  } finally {
    isLoading.value = false
  }
}

const chartPalette = ['#4338ca', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4']

const topDepartments = computed(() => {
  if (allDepartments.value && allDepartments.value.length > 0) {
    const list = allDepartments.value.map(d => {
      const count = allCourses.value.filter(c => c.department_id === d.id || c.departmentName === d.name || c.dept === d.name).length
      return {
        id: d.id,
        name: d.name,
        count
      }
    })
    return list.sort((a, b) => b.count - a.count).slice(0, 5)
  }
  const deptCounts: Record<string, number> = {}
  allCourses.value.forEach(c => {
    const dName = c.departmentName && c.departmentName !== '—' ? c.departmentName : (c.dept && c.dept !== '—' ? c.dept : 'General')
    deptCounts[dName] = (deptCounts[dName] || 0) + 1
  })
  return Object.entries(deptCounts)
    .map(([name, count]) => ({ id: name, name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5)
})

const recentCourses = computed(() => {
  return [...allCourses.value]
    .sort((a, b) => {
      const timeA = a.created_at ? new Date(a.created_at).getTime() : (a.id || 0)
      const timeB = b.created_at ? new Date(b.created_at).getTime() : (b.id || 0)
      return timeB - timeA
    })
    .slice(0, 5)
    .map(c => ({
      ...c,
      id: c.id,
      name: c.name || c.title,
      code: c.code,
      departmentName: c.departmentName && c.departmentName !== '—' ? c.departmentName : (c.dept && c.dept !== '—' ? c.dept : 'General'),
      date: c.created_at ? new Date(c.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : (c.created_on || 'Recently added')
    }))
})

const chartData = computed(() => {
  const deptsWithCourses = topDepartments.value.filter(d => d.count > 0)
  if (deptsWithCourses.length === 0) {
    return {
      labels: ['No Courses'],
      datasets: [{
        backgroundColor: ['#e2e8f0'],
        data: [1],
        borderWidth: 0
      }]
    }
  }
  return {
    labels: deptsWithCourses.map(d => d.name),
    datasets: [{
      backgroundColor: chartPalette.slice(0, deptsWithCourses.length),
      data: deptsWithCourses.map(d => d.count),
      borderWidth: 0,
      hoverOffset: 4
    }]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '75%',
  plugins: {
    legend: {
      display: false
    },
    tooltip: { enabled: true }
  }
}
// ── Import Modal, Validation & Results State ──
const showImportModal = ref(false)
const selectedImportFile = ref<File | null>(null)
const isImporting = ref(false)
const importDragOver = ref(false)
const modalFileInput = ref<HTMLInputElement | null>(null)

// Error / Format Issues Modal State
const showImportErrorModal = ref(false)
const importErrorMessage = ref('')
const importErrorList = ref<string[]>([])
const importFormatGuide = ref<any>(null)

// Success Modal State
const showImportSuccessModal = ref(false)
const importSuccessMessage = ref('')
const importedCoursesList = ref<any[]>([])

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

    const res = await apiClient.post('/admin/courses-import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    showImportModal.value = false
    selectedImportFile.value = null
    importSuccessMessage.value = res.data.message || `Successfully imported courses from ${file.name}.`
    importedCoursesList.value = res.data.courses || []
    showImportSuccessModal.value = true

    await fetchCourses()
  } catch (err: any) {
    showImportModal.value = false
    const data = err.response?.data
    importErrorMessage.value = data?.message || 'Failed to import courses.'
    importErrorList.value = Array.isArray(data?.errors)
      ? data.errors
      : (data?.message ? [data.message] : ['An unexpected error occurred during file import.'])
    importFormatGuide.value = data?.format_guide || null
    showImportErrorModal.value = true
  } finally {
    isImporting.value = false
    if (modalFileInput.value) modalFileInput.value.value = ''
  }
}

const downloadSampleCsv = () => {
  const deptExample1 = allDepartments.value.length > 0 ? (allDepartments.value[0].name || allDepartments.value[0]) : 'Software Engineering'
  const deptExample2 = allDepartments.value.length > 1 ? (allDepartments.value[1].name || allDepartments.value[1]) : 'Computer Science'

  const headers = ['Course Code', 'Course Title', 'Department', 'Academic Year Level', 'Credits', 'Semester', 'Section', 'Status', 'Description']
  const row1 = ['CS-301', 'Compiler Design', deptExample1, '3rd Year', '4', '1st Semester', 'Section A', 'Active', 'Study of compiler principles and construction techniques']
  const row2 = ['SE-204', 'Software Architecture', deptExample2, '2nd Year', '3', '1st Semester', 'Section B', 'Active', 'Fundamental software architecture styles and patterns']

  const csvContent = [headers.join(','), row1.join(','), row2.join(',')].join('\n')
  const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'course_import_template.csv'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

const showExportDropdown = ref(false)
const handleExport = async (format: string) => {
  showExportDropdown.value = false;
  isLoading.value = true;
  try {
    const token = localStorage.getItem('auth_token');
    const params = new URLSearchParams();
    params.set('format', format);
    if (deptFilter.value && deptFilter.value !== 'all') params.set('department', deptFilter.value);
    if (statusFilter.value && statusFilter.value !== 'all') params.set('status', statusFilter.value);
    if (levelFilter.value && levelFilter.value !== 'all') params.set('level', levelFilter.value);
    if (search.value) params.set('search', search.value);

    const res = await fetch(`http://localhost:8000/api/v1/admin/courses-export?${params.toString()}`, {
      headers: { Authorization: `Bearer ${token}` }
    });
    if (!res.ok) throw new Error('Export failed');
    const data = await res.json();
    const byteChars = atob(data.file);
    const byteNums = new Array(byteChars.length);
    for (let i = 0; i < byteChars.length; i++) byteNums[i] = byteChars.charCodeAt(i);
    const blob = new Blob([new Uint8Array(byteNums)], {
      type: format === 'pdf' ? 'application/pdf' : 'text/csv'
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = data.filename || `courses_export_${new Date().toISOString().slice(0, 10)}.${format}`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (err: any) {
    alert('Failed to export courses. Please try again.');
  } finally {
    isLoading.value = false;
  }
}
</script>

<template>
  <div class="w-full">
    <!-- List View -->
    <div v-if="!showAddPage && !showDetailsPage" class="space-y-6 min-w-0 w-full">
      <!-- Page Actions -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-end gap-3">
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
          <button @click="triggerImport" class="flex items-center gap-2 px-3.5 sm:px-4 py-2 sm:py-2.5 bg-white border border-slate-200 text-[#4338ca] font-bold rounded-xl text-[12px] sm:text-[13px] hover:bg-slate-50 transition-colors shadow-sm whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> Import Courses
          </button>
          
          <div class="relative">
            <button @click="showExportDropdown = !showExportDropdown" class="flex items-center gap-2 px-3.5 sm:px-4 py-2 sm:py-2.5 bg-white border border-slate-200 text-[#4338ca] font-bold rounded-xl text-[12px] sm:text-[13px] hover:bg-slate-50 transition-colors shadow-sm whitespace-nowrap">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg> Export Courses
            </button>
            <div v-if="showExportDropdown" class="absolute right-0 mt-2 w-36 bg-white rounded-xl shadow-lg border border-slate-100 py-2 z-50">
              <button @click="handleExport('csv')" class="w-full text-left px-4 py-2 text-[13px] text-slate-600 hover:bg-slate-50 hover:text-[#4338ca] transition-colors">Export as CSV</button>
              <button @click="handleExport('pdf')" class="w-full text-left px-4 py-2 text-[13px] text-slate-600 hover:bg-slate-50 hover:text-[#4338ca] transition-colors">Export as PDF</button>
            </div>
          </div>
          <button @click="openAddPage" class="flex items-center gap-2 px-4 sm:px-5 py-2 sm:py-2.5 bg-[#4338ca] text-white font-bold rounded-xl text-[12px] sm:text-[13px] hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add New Course
          </button>
        </div>
      </div>

    <!-- 5 Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
      <div v-for="(item, i) in [
        { label:'Total Courses', val: stats.total,      icon:'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', bg:'bg-indigo-50', ic:'text-[#4338ca]', trend:'↑ 5.2%', tc:'text-emerald-500', sub:'All courses' },
        { label:'Active Courses',val: stats.active,     icon:'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', bg:'bg-emerald-50',ic:'text-emerald-500', trend:'↑ 6.7%', tc:'text-emerald-500', sub:'Active courses'},
        { label:'Inactive Courses',val: stats.inactive, icon:'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', bg:'bg-rose-50', ic:'text-rose-500', trend:'↓ 7.7%', tc:'text-rose-500', sub:'Inactive courses'},
        { label:'New Courses',   val: stats.newCourses, icon:'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z', bg:'bg-sky-50', ic:'text-sky-500', trend:'↑ 14.3%', tc:'text-emerald-500', sub:'This month'},
        { label:'Published Courses',val: stats.published, icon:'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', bg:'bg-amber-50', ic:'text-amber-500', trend:'↑ 4.3%', tc:'text-emerald-500', sub:'Published courses'},
      ]" :key="i" class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-5 flex flex-col justify-between">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[11px] font-semibold text-slate-500 tracking-wide">{{ item.label }}</p>
            <p class="text-[22px] sm:text-[24px] font-bold text-slate-800 mt-1">{{ item.val }}</p>
          </div>
          <div :class="[item.bg, 'w-10 h-10 rounded-xl flex items-center justify-center shrink-0']">
            <svg class="w-5 h-5" :class="item.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path></svg>
          </div>
        </div>
        <div class="flex items-center gap-2 mt-4 text-[11px]">
          <span :class="item.tc" class="font-bold">{{ item.trend }}</span>
          <span class="text-slate-400">{{ item.sub }}</span>
        </div>
      </div>
    </div>

    <!-- Main Layout: Table Full Width + Bottom Cards -->
    <div class="flex flex-col gap-6 w-full">
      
      <!-- Top: Table Area -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm flex flex-col min-w-0 overflow-hidden w-full">
        <!-- Table Toolbar -->
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 bg-slate-50/50">
          <div class="relative flex-1 min-w-[200px] w-full sm:w-auto">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input v-model="search" placeholder="Search Courses..." class="w-full pl-9 pr-4 py-2 text-[12px] border border-slate-200 rounded-lg focus:outline-none focus:border-[#4338ca] bg-white">
          </div>
          <select v-model="deptFilter" class="flex-1 sm:flex-initial min-w-[130px] text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Departments</option>
            <option v-for="d in allDepartments" :key="d.id" :value="d.name">{{ d.name }}</option>
          </select>
          <select v-model="statusFilter" class="flex-1 sm:flex-initial min-w-[110px] text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <select v-model="levelFilter" class="flex-1 sm:flex-initial min-w-[120px] text-[12px] border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#4338ca] text-slate-600 bg-white">
            <option value="all">All Levels</option>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
            <option value="5th Year">5th Year</option>
          </select>
          <input type="text" disabled :value="settingsStore.semester" class="flex-1 sm:flex-initial min-w-[120px] text-[12px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-500 cursor-not-allowed text-center font-bold">
          <button class="flex items-center gap-1.5 px-3 py-2 text-[12px] font-bold text-[#4338ca] border border-indigo-100 bg-white rounded-lg hover:bg-indigo-50 ml-auto">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Filter
          </button>
        </div>

        <!-- Data Table -->
        <div class="overflow-x-auto flex-1 min-w-0">
          <table class="w-full text-left whitespace-nowrap min-w-max">
            <thead>
              <tr class="border-b border-slate-100 bg-white">
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Course Code</th>
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Course Title</th>
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Department</th>
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Level</th>
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">Instructors</th>
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">Credits</th>
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">Status</th>
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">Created By</th>
                <th class="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="course in paginated" :key="course.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors group">
                <td class="px-5 py-4">
                  <span class="text-[11px] font-bold text-[#4338ca] bg-indigo-50 px-2.5 py-1 rounded-md tracking-wide">{{ course.code }}</span>
                </td>
                <td class="px-5 py-4">
                  <p class="text-[13px] font-bold text-slate-800">{{ course.name }}</p>
                </td>
                <td class="px-5 py-4">
                  <p class="text-[12px] text-slate-600">{{ course.departmentName }}</p>
                </td>
                <td class="px-5 py-4">
                  <p class="text-[12px] text-slate-600">{{ course.level || '—' }}</p>
                </td>
                <td class="px-5 py-4 text-center">
                  <div class="inline-flex items-center justify-center">
                    <span 
                      v-if="getCourseInstructors(course).length > 0"
                      class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-[#4338ca] border border-indigo-100 hover:bg-indigo-100 transition-colors cursor-pointer"
                      :title="getCourseInstructors(course).map(i => `${i.name} (${i.section || 'Instructor'})`).join(', ')"
                      @click="openDetailsPage(course)"
                    >
                      <svg class="w-3 h-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                      {{ getCourseInstructors(course).length }}
                    </span>
                    <span v-else class="text-[12px] text-slate-400 font-medium">0</span>
                  </div>
                </td>
                <td class="px-5 py-4 text-center">
                  <p class="text-[12px] font-semibold text-slate-700">{{ course.credits }}</p>
                </td>
                <td class="px-5 py-4 text-center">
                  <span :class="course.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500'" class="text-[10px] font-bold px-2.5 py-1 rounded-lg capitalize">{{ course.status }}</span>
                </td>
                <td class="px-5 py-4 text-center">
                  <span class="text-[11px] font-bold text-sky-600 bg-sky-50 px-2.5 py-1 rounded-md">{{ course.created_by }}</span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center justify-center gap-1.5 transition-opacity">
                    <button @click="openAssign(course)" class="w-7 h-7 rounded bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-100 transition-colors" title="Assign Instructor"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg></button>
                    <button @click="openDetailsPage(course)" class="w-7 h-7 rounded bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition-colors" title="View Details"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                    <button @click="openEditPage(course)" class="w-7 h-7 rounded bg-indigo-50 text-[#4338ca] flex items-center justify-center hover:bg-indigo-100 transition-colors" title="Edit"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                    <button @click="confirmDelete(course)" class="w-7 h-7 rounded bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-100 transition-colors" title="Delete"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                  </div>
                </td>
              </tr>
              <tr v-if="filtered.length === 0">
                <td colspan="9" class="px-5 py-16 text-center text-slate-500 text-[13px]">No courses found matching your criteria.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/30">
          <p class="text-[12px] text-slate-500">
            Showing <span class="font-bold text-slate-700">{{ filtered.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }}</span> to <span class="font-bold text-slate-700">{{ Math.min(currentPage * perPage, filtered.length) }}</span> of <span class="font-bold text-slate-700">{{ filtered.length }}</span> courses
          </p>
          <div class="flex items-center gap-1.5">
            <!-- Previous Button -->
            <button
              @click="prevPage"
              :disabled="currentPage <= 1"
              title="Previous page"
              class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-white hover:text-[#4338ca] hover:border-[#4338ca] disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-500 disabled:hover:border-slate-200 disabled:cursor-not-allowed transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>

            <!-- Page Number Buttons with Ellipsis -->
            <template v-for="(page, idx) in visiblePages" :key="idx">
              <span
                v-if="page === '...'"
                class="w-8 h-8 flex items-center justify-center text-[12px] text-slate-400 select-none"
              >
                …
              </span>
              <button
                v-else
                @click="goToPage(page)"
                :class="currentPage === page ? 'bg-[#4338ca] text-white border-[#4338ca] shadow-sm' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'"
                class="w-8 h-8 flex items-center justify-center rounded-lg border text-[12px] font-bold transition-colors"
              >
                {{ page }}
              </button>
            </template>

            <!-- Next Button -->
            <button
              @click="nextPage"
              :disabled="currentPage >= totalPages"
              title="Next page"
              class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-white hover:text-[#4338ca] hover:border-[#4338ca] disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-500 disabled:hover:border-slate-200 disabled:cursor-not-allowed transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom: Dashboard Cards (was Sidebar) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 w-full">
        <!-- Course Overview -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6">
          <h3 class="text-[14px] font-bold text-slate-800 mb-6">Course Overview</h3>
          <div class="relative h-48 w-full flex items-center justify-center">
            <Doughnut :data="chartData" :options="chartOptions" />
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-2">
              <span class="text-[24px] font-black text-slate-800 leading-none">{{ stats.total }}</span>
              <span class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wide">Total Courses</span>
            </div>
          </div>
          <!-- HTML Legend -->
          <div class="grid grid-cols-2 gap-2 mt-6">
            <div v-for="(label, i) in chartData.labels" :key="i" class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: chartData.datasets[0].backgroundColor[i] }"></span>
              <span class="text-[10px] text-slate-600 truncate" :title="label">{{ label }}</span>
            </div>
          </div>
        </div>

        <!-- Top Departments -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6">
          <h3 class="text-[14px] font-bold text-slate-800 mb-4">Top Departments</h3>
          <div class="space-y-3">
            <div
              v-for="dept in topDepartments"
              :key="dept.id || dept.name"
              class="flex items-center justify-between text-[12px] group cursor-pointer hover:bg-slate-50/80 p-1.5 -mx-1.5 rounded-lg transition-colors"
              @click="deptFilter = dept.name"
              :title="'Filter courses by ' + dept.name"
            >
              <div class="flex items-center gap-2 text-slate-600 group-hover:text-[#4338ca] transition-colors">
                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span class="truncate max-w-[140px] font-medium">{{ dept.name }}</span>
              </div>
              <span class="font-bold text-slate-800 bg-slate-50 px-2 py-0.5 rounded group-hover:bg-indigo-50 group-hover:text-[#4338ca] transition-colors">{{ dept.count }}</span>
            </div>
            <div v-if="topDepartments.length === 0" class="text-[12px] text-slate-400 text-center py-4">No department data available.</div>
          </div>
        </div>

        <!-- Recent Courses -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6">
          <h3 class="text-[14px] font-bold text-slate-800 mb-4">Recent Courses</h3>
          <div class="space-y-3">
            <div
              v-for="course in recentCourses"
              :key="course.id"
              class="flex items-start gap-3 p-1.5 -mx-1.5 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors group"
              @click="openDetailsPage(course)"
              :title="'View ' + course.name"
            >
              <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#4338ca] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-[12px] font-bold text-slate-800 truncate group-hover:text-[#4338ca] transition-colors">{{ course.name }}</p>
                <p class="text-[10px] text-slate-400 mt-0.5 truncate">{{ course.code }} · {{ course.date }}</p>
              </div>
            </div>
            <div v-if="recentCourses.length === 0" class="text-[12px] text-slate-400 text-center py-4">No recent courses.</div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6">
          <h3 class="text-[14px] font-bold text-slate-800 mb-4">Quick Actions</h3>
          <div class="grid grid-cols-2 gap-3">
            <button @click="openAddPage" class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50 transition-colors text-[#4338ca]">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
              <span class="text-[10px] font-bold text-center">Add New<br>Course</span>
            </button>
            <button @click="triggerImport" class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              <span class="text-[10px] font-bold text-center">Import<br>Courses</span>
            </button>
            <button class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
              <span class="text-[10px] font-bold text-center">Export<br>Courses</span>
            </button>
            <button class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
              <span class="text-[10px] font-bold text-center">Manage<br>Departments</span>
            </button>
          </div>
        </div>
      </div>
    </div>
    </div> <!-- Close List View -->

    <!-- Add Course Form (Full Page) -->
    <div v-if="showAddPage" class="space-y-6 pb-12 w-full min-w-0">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-white rounded-2xl shadow-sm border border-slate-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
          </div>
          <div>
            <h1 class="text-[20px] sm:text-[24px] font-bold text-slate-800 leading-tight">{{ isEditing ? 'Edit Course' : 'Add New Course' }}</h1>
            <p class="text-[12px] sm:text-[13px] text-slate-500 mt-0.5">{{ isEditing ? 'Update the course information.' : 'Create a new course and set all the necessary information.' }}</p>
            <div class="flex items-center gap-2 text-[11px] font-bold text-slate-400 mt-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
              <span>Courses</span>
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              <span class="text-slate-600">{{ isEditing ? 'Edit Course' : 'Add New Course' }}</span>
            </div>
          </div>
        </div>
        <button @click="showAddPage = false" class="flex items-center self-start sm:self-auto gap-2 px-4 py-2 sm:py-2.5 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl text-[12px] sm:text-[13px] hover:bg-slate-50 transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg> Back to Courses
        </button>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6 items-start">
        <!-- Main Form Area -->
        <div class="space-y-6">
          
          <!-- Course Information -->
          <div class="bg-white p-5 sm:p-8 rounded-2xl border border-slate-100 shadow-sm">
            <h2 class="text-[15px] font-bold text-slate-800 mb-6">Course Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6">
              <!-- Course Code -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Course Code <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.code"
                  type="text"
                  placeholder="Enter course code (e.g., CS-301)"
                  :class="courseFieldCls('code')"
                  @blur="touchCourseField('code')"
                  @input="courseFormTouched.code && validateCourseField('code')"
                >
                <p v-if="courseFormErrors.code" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.code }}
                </p>
                <p v-else-if="courseFormTouched.code && !courseFormErrors.code" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Course Title -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Course Title <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.title"
                  type="text"
                  placeholder="Enter course title"
                  :class="courseFieldCls('title')"
                  @blur="touchCourseField('title')"
                  @input="courseFormTouched.title && validateCourseField('title')"
                >
                <p v-if="courseFormErrors.title" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.title }}
                </p>
                <p v-else-if="courseFormTouched.title && !courseFormErrors.title" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Department -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Department <span class="text-rose-500">*</span></label>
                <select
                  v-model="newCourseForm.department_id"
                  :class="courseFieldCls('department_id', 'appearance-none bg-white')"
                  @blur="touchCourseField('department_id')"
                  @change="touchCourseField('department_id')"
                >
                  <option value="">Select department</option>
                  <option v-for="d in allDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <p v-if="courseFormErrors.department_id" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.department_id }}
                </p>
                <p v-else-if="courseFormTouched.department_id && !courseFormErrors.department_id" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Academic Year Level -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Academic Year Level <span class="text-rose-500">*</span></label>
                <select
                  v-model="newCourseForm.level"
                  :class="courseFieldCls('level', 'appearance-none bg-white')"
                  @blur="touchCourseField('level')"
                  @change="touchCourseField('level')"
                >
                  <option value="">Select Academic Year</option>
                  <option value="1st Year">1st Year</option>
                  <option value="2nd Year">2nd Year</option>
                  <option value="3rd Year">3rd Year</option>
                  <option value="4th Year">4th Year</option>
                  <option value="5th Year">5th Year</option>
                </select>
                <p v-if="courseFormErrors.level" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.level }}
                </p>
                <p v-else-if="courseFormTouched.level && !courseFormErrors.level" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Semester -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Semester <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.semester"
                  type="text"
                  disabled
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-500 bg-slate-50 cursor-not-allowed font-medium"
                >
                <p class="mt-1.5 text-[11px] text-slate-400">Current academic semester (auto-assigned)</p>
              </div>

              <!-- Credits -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Credits <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.credits"
                  type="number"
                  min="1"
                  max="30"
                  placeholder="Enter credit hours (e.g., 3)"
                  :class="courseFieldCls('credits')"
                  @blur="touchCourseField('credits')"
                  @input="courseFormTouched.credits && validateCourseField('credits')"
                >
                <p v-if="courseFormErrors.credits" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.credits }}
                </p>
                <p v-else-if="courseFormTouched.credits && !courseFormErrors.credits" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>
            </div>
          </div>


          <!-- Course Settings (All Optional) -->
          <div class="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-6">
              <div>
                <h2 class="text-[15px] font-bold text-slate-800">Course Settings</h2>
                <p class="text-[12px] text-slate-400 mt-0.5">Configuration and scheduling options (all optional).</p>
              </div>
              <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-[11px] font-bold rounded-lg uppercase tracking-wider">Optional</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Enrollment Status <span class="text-[11px] text-slate-400 font-medium">(Optional)</span></label>
                <select v-model="newCourseForm.enrollment_status" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none">
                  <option value="Open for Enrollment">Open for Enrollment</option>
                  <option value="Closed">Closed</option>
                </select>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Visibility <span class="text-[11px] text-slate-400 font-medium">(Optional)</span></label>
                <select v-model="newCourseForm.visibility" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none">
                  <option value="Visible to Students">Visible to Students</option>
                  <option value="Hidden">Hidden</option>
                </select>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Start Date <span class="text-[11px] text-slate-400 font-medium">(Optional)</span></label>
                <input
                  v-model="newCourseForm.start_date"
                  type="date"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none"
                  @change="touchCourseField('start_date')"
                >
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">End Date <span class="text-[11px] text-slate-400 font-medium">(Optional)</span></label>
                <input
                  v-model="newCourseForm.end_date"
                  type="date"
                  :class="courseFieldCls('end_date', 'appearance-none bg-white')"
                  @change="touchCourseField('end_date')"
                >
                <p v-if="courseFormErrors.end_date" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.end_date }}
                </p>
                <p v-else-if="courseFormTouched.end_date && newCourseForm.end_date && !courseFormErrors.end_date" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Valid date
                </p>
              </div>
            </div>

            <!-- Server Error Banner -->
            <div v-if="courseFormErrors._server" class="mt-6 p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-3">
              <div class="w-5 h-5 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
              </div>
              <div class="flex-1 text-[13px] text-rose-800 font-medium whitespace-pre-line leading-relaxed">
                {{ courseFormErrors._server }}
              </div>
              <button @click="delete courseFormErrors._server" class="text-rose-400 hover:text-rose-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
              </button>
            </div>
            
            <div class="mt-8 flex items-center justify-between border-t border-slate-100 pt-6">
              <button @click="showAddPage = false" class="px-6 py-3 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl text-[13px] hover:bg-slate-50 transition-colors shadow-sm">Cancel</button>
              <button @click="saveCourse" :disabled="isLoading" class="flex items-center gap-2 px-8 py-3 bg-[#4338ca] text-white font-bold rounded-xl text-[13px] hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200 disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer">
                <svg v-if="isLoading" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                {{ isLoading ? 'Saving Course...' : (isEditing ? 'Save Changes' : 'Create Course') }}
              </button>
            </div>
          </div>
        </div>
        
        <!-- Sidebar -->
        <div class="space-y-6">
          <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Course Summary</h3>
            <div class="flex items-start gap-4 mb-6 pb-6 border-b border-slate-50">
              <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
              </div>
              <div>
                <h4 class="text-[13px] font-bold text-slate-800">New Course</h4>
                <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">Course will be created with the details provided</p>
              </div>
            </div>
            <div class="space-y-4">
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>Course Code</div><span class="font-bold text-slate-800">{{ newCourseForm.code || 'Not Set' }}</span></div>
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>Course Title</div><span class="font-bold text-slate-800 truncate max-w-[120px]">{{ newCourseForm.title || 'Not Set' }}</span></div>
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>Department</div><span class="font-bold text-slate-800">{{ allDepartments.find(d => d.id === newCourseForm.department_id)?.name || 'Not Set' }}</span></div>
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Credits</div><span class="font-bold text-slate-800">{{ newCourseForm.credits || '0' }}</span></div>
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Status</div><span class="font-bold text-amber-500 bg-amber-50 px-2 py-0.5 rounded">Draft</span></div>
            </div>
          </div>
          
          <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Course Features</h3>
            <div class="space-y-3">
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Online Exams</div>
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Question Banks</div>
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Grade Management</div>
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Reports & Analytics</div>
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Student Progress Tracking</div>
            </div>
          </div>
          
          <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Quick Actions</h3>
            <div class="space-y-2">
              <button class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 text-[12px] text-slate-700 transition-colors">
                <div class="flex items-center gap-2 text-[#4338ca]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg><span class="text-slate-700 font-semibold">Import Course Data</span></div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 text-[12px] text-slate-700 transition-colors">
                <div class="flex items-center gap-2 text-[#4338ca]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg><span class="text-slate-700 font-semibold">Manage Departments</span></div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button @click="openAssign(selectedCourse)" class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 text-[12px] text-slate-700 transition-colors">
                <div class="flex items-center gap-2 text-[#4338ca]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg><span class="text-slate-700 font-semibold">Manage Instructors</span></div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 text-[12px] text-slate-700 transition-colors">
                <div class="flex items-center gap-2 text-[#4338ca]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg><span class="text-slate-700 font-semibold">Course Templates</span></div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div> <!-- Close Add Page -->

    <!-- Course Details (Full Page) -->
    <div v-if="showDetailsPage" class="space-y-6 pb-12 w-full min-w-0">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-white rounded-2xl shadow-sm border border-slate-100 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
          </div>
          <div>
            <h1 class="text-[20px] sm:text-[24px] font-bold text-slate-800 leading-tight">Course Details</h1>
            <p class="text-[12px] sm:text-[13px] text-slate-500 mt-0.5">View complete information about this course.</p>
            <div class="flex items-center gap-2 text-[11px] font-bold text-slate-400 mt-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
              <span>Courses</span>
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              <span class="text-slate-600">Course List</span>
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              <span class="text-slate-600">Course Details</span>
            </div>
          </div>
        </div>
        <button @click="showDetailsPage = false" class="flex items-center self-start sm:self-auto gap-2 px-4 py-2 sm:py-2.5 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl text-[12px] sm:text-[13px] hover:bg-slate-50 transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg> Back to Course List
        </button>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6 items-start">
        <!-- Main Details Area -->
        <div class="space-y-6">
          
          <!-- Course Information -->
          <div class="bg-white p-5 sm:p-8 rounded-2xl border border-slate-100 shadow-sm">
            <h2 class="text-[15px] font-bold text-slate-800 mb-6">Course Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-y-6 sm:gap-y-8 gap-x-4 sm:gap-x-6">
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Course Code</p>
                <p class="text-[14px] font-bold text-[#4338ca]">{{ selectedCourse?.code || '—' }}</p>
              </div>
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Course Title</p>
                <p class="text-[14px] font-bold text-[#4338ca]">{{ selectedCourse?.name || selectedCourse?.title || '—' }}</p>
              </div>
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Department</p>
                <p class="text-[14px] font-bold text-[#4338ca]">{{ selectedCourse?.departmentName || selectedCourse?.department?.name || '—' }}</p>
              </div>
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Semester</p>
                <p class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.semester || '—' }}</p>
              </div>
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Credits</p>
                <p class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.credits || '—' }}</p>
              </div>
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Level</p>
                <p class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.level || '—' }}</p>
              </div>
            </div>
          </div>

          <!-- Course Settings & Assigned Instructors -->
          <div class="bg-white p-5 sm:p-8 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
              <div>
                <h2 class="text-[15px] font-bold text-slate-800">Course Settings & Assigned Instructors</h2>
                <p class="text-[12px] text-slate-500 mt-0.5">Assigned instructors per section and enrollment configuration.</p>
              </div>
              <button @click="openAssign(selectedCourse)" class="inline-flex items-center self-start sm:self-auto gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-[#4338ca] text-[12px] font-bold rounded-xl transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Manage Instructors
              </button>
            </div>

            <!-- Assigned Instructors List -->
            <div class="mb-8">
              <div class="flex items-center justify-between mb-3">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                  Course Instructors ({{ selectedCourseInstructors.length }})
                </p>
                <span v-if="selectedCourseInstructors.length > 0" class="text-[11px] font-medium text-slate-500">
                  Assigned across {{ selectedCourseInstructors.length > 1 ? 'all sections (Section A & Section B)' : 'current section' }}
                </span>
              </div>

              <div v-if="selectedCourseInstructors.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div 
                  v-for="inst in selectedCourseInstructors" 
                  :key="inst.id" 
                  class="flex items-center gap-3.5 p-4 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-slate-50 transition-colors"
                >
                  <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-[#4338ca] to-indigo-500 text-white font-bold text-[14px] flex items-center justify-center shrink-0 uppercase shadow-xs">
                    {{ inst.name ? inst.name.charAt(0) : 'I' }}
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                      <p class="text-[13px] font-bold text-slate-800 truncate">{{ inst.name }}</p>
                      <span 
                        class="px-2.5 py-0.5 text-[10px] font-bold rounded-full uppercase tracking-wider shrink-0"
                        :class="inst.section === 'Section B' ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-blue-100 text-blue-700 border border-blue-200'"
                      >
                        {{ inst.section || 'Section A' }}
                      </span>
                    </div>
                    <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ inst.email || 'No email provided' }}</p>
                    <p class="text-[10px] text-slate-400 capitalize mt-0.5">{{ inst.role ? inst.role.replace('_', ' ') : 'Instructor' }}</p>
                  </div>
                </div>
              </div>

              <div v-else class="p-5 rounded-xl border border-dashed border-slate-200 text-center bg-slate-50/50">
                <p class="text-[13px] text-slate-500">No instructors assigned to this course yet.</p>
                <button @click="openAssign(selectedCourse)" class="mt-2 text-[12px] font-bold text-[#4338ca] hover:underline inline-flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                  Assign instructors for Section A and Section B
                </button>
              </div>
            </div>

            <!-- Configuration Grid -->
            <div>
              <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3">Course Configuration</p>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pt-1">
                <div>
                  <p class="text-[12px] font-bold text-slate-500 mb-1">Enrollment Status</p>
                  <span 
                    :class="selectedCourse?.enrollment_status === 'Closed' ? 'text-amber-600 bg-amber-50' : 'text-emerald-600 bg-emerald-50'"
                    class="inline-flex text-[11px] font-bold px-2.5 py-1 rounded-lg"
                  >
                    {{ selectedCourse?.enrollment_status || 'Open for Enrollment' }}
                  </span>
                </div>
                <div>
                  <p class="text-[12px] font-bold text-slate-500 mb-1">Visibility</p>
                  <p class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.visibility || 'Visible to Students' }}</p>
                </div>
                <div>
                  <p class="text-[12px] font-bold text-slate-500 mb-1">Start Date</p>
                  <p class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.start_date ? formatDate(selectedCourse.start_date) : 'Not scheduled' }}</p>
                </div>
                <div>
                  <p class="text-[12px] font-bold text-slate-500 mb-1">End Date</p>
                  <p class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.end_date ? formatDate(selectedCourse.end_date) : 'Not scheduled' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Course Description -->
          <div class="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm">
            <h2 class="text-[15px] font-bold text-slate-800 mb-4">Course Description</h2>
            <p class="text-[13px] text-slate-600 leading-relaxed whitespace-pre-line">
              {{ selectedCourse?.description || selectedCourse?.short_description || selectedCourse?.full_description || 'No description provided for this course.' }}
            </p>
          </div>

          <!-- Created & Updated Information -->
          <div class="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm">
            <h2 class="text-[15px] font-bold text-slate-800 mb-6">Created & Updated Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Created By</p>
                <p class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.created_by || selectedCourse?.creator?.name || 'Super Admin' }}</p>
              </div>
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Created Date</p>
                <p class="text-[13px] font-bold text-slate-800">{{ formatDateTime(selectedCourse?.created_at) }}</p>
              </div>
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Last Updated By</p>
                <p class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.created_by || selectedCourse?.creator?.name || 'Super Admin' }}</p>
              </div>
              <div>
                <p class="text-[12px] font-bold text-slate-500 mb-1">Last Updated</p>
                <p class="text-[13px] font-bold text-slate-800">{{ formatDateTime(selectedCourse?.updated_at || selectedCourse?.created_at) }}</p>
              </div>
            </div>
          </div>

        </div>
        
        <!-- Sidebar -->
        <div class="space-y-6">
          <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Course Summary</h3>
            <div class="flex items-start gap-4 mb-6 pb-6 border-b border-slate-50">
              <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-[#4338ca]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
              </div>
              <div>
                <h4 class="text-[13px] font-bold text-slate-800">{{ selectedCourse?.name || selectedCourse?.title || 'Course Details' }}</h4>
                <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">{{ selectedCourse?.code }}</p>
              </div>
            </div>
            <div class="space-y-4">
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>Department</div><span class="font-bold text-slate-800">{{ selectedCourse?.departmentName || selectedCourse?.department?.name || '—' }}</span></div>
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>Credits</div><span class="font-bold text-slate-800">{{ selectedCourse?.credits || '—' }}</span></div>
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>Semester</div><span class="font-bold text-slate-800">{{ selectedCourse?.semester || '—' }}</span></div>
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Status</div><span :class="selectedCourse?.status === 'active' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'" class="font-bold px-2 py-0.5 rounded capitalize">{{ selectedCourse?.status || 'active' }}</span></div>
              <div class="flex items-center justify-between text-[12px]"><div class="flex items-center gap-2 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>Visibility</div><span class="font-bold text-slate-800">{{ selectedCourse?.visibility || 'Visible to Students' }}</span></div>
            </div>
          </div>
          
          <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Course Features</h3>
            <div class="space-y-3">
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Online Exams</div>
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Question Banks</div>
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Grade Management</div>
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Reports & Analytics</div>
              <div class="flex items-center gap-3 text-[12px] text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Student Progress Tracking</div>
            </div>
          </div>
          
          <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Quick Actions</h3>
            <div class="space-y-2">
              <button @click="openEditPage(selectedCourse); showDetailsPage = false" class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 text-[12px] text-slate-700 transition-colors">
                <div class="flex items-center gap-2 text-[#4338ca]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg><span class="text-slate-700 font-semibold">Edit Course</span></div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button @click="openAssign(selectedCourse)" class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 text-[12px] text-slate-700 transition-colors">
                <div class="flex items-center gap-2 text-[#4338ca]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg><span class="text-slate-700 font-semibold">Manage Instructors</span></div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 text-[12px] text-slate-700 transition-colors">
                <div class="flex items-center gap-2 text-[#4338ca]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg><span class="text-slate-700 font-semibold">Manage Enrollments</span></div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
              <button class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border border-slate-100 hover:bg-slate-50 text-[12px] text-slate-700 transition-colors">
                <div class="flex items-center gap-2 text-[#4338ca]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg><span class="text-slate-700 font-semibold">Duplicate Course</span></div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div> <!-- Close Details Page -->

    <!-- Delete Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-sm overflow-hidden">
          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center mx-auto mb-4"><svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg></div>
            <h3 class="text-[16px] font-bold text-slate-800 mb-2">Delete Course?</h3>
            <p class="text-[13px] text-slate-500">Delete <span class="font-bold text-slate-700">{{ selectedCourse?.name }}</span>? This cannot be undone.</p>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showDeleteModal = false" class="flex-1 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="deleteCourse" class="flex-1 py-2.5 text-[13px] font-bold text-white bg-rose-500 hover:bg-rose-600 rounded-xl transition-colors">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Assign Modal -->
    <Teleport to="body">
      <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-sm overflow-hidden">
          <div class="p-6">
            <h3 class="text-[16px] font-bold text-slate-800 mb-2">Assign Instructors</h3>
            <p class="text-[13px] text-slate-500 mb-4">Select instructors for <span class="font-bold text-slate-700">{{ courseToAssign?.name }}</span>.</p>
            <div class="space-y-4">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Section</label>
                <div class="relative">
                  <select v-model="assignSection" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none">
                    <option value="">Select section</option>
                    <option v-for="sec in sectionOptions" :key="sec" :value="sec">{{ sec }}</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Course Instructor <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="assignInstructorId" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none">
                    <option value="">Unassigned</option>
                    <option v-for="inst in availableInstructors" :key="inst.id" :value="inst.id">{{ inst.name }}</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <p v-if="currentInstructorInfo" class="mt-1.5 text-[11px] text-slate-400 italic">{{ currentInstructorInfo }}</p>
              </div>
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Co-Instructor</label>
                <div class="relative">
                  <select v-model="assignCoInstructorId" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none">
                    <option value="">Select co-instructor (optional)</option>
                    <option v-for="inst in availableCoInstructors" :key="'co'+inst.id" :value="inst.id">{{ inst.name }}</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <p v-if="currentCoInstructorInfo" class="mt-1.5 text-[11px] text-slate-400 italic">{{ currentCoInstructorInfo }}</p>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-3 px-6 pb-6">
            <button @click="showAssignModal = false" class="flex-1 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
            <button @click="assignInstructor" :disabled="isLoading" class="flex-1 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-600 rounded-xl transition-colors disabled:opacity-70">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Course Modal -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
          <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
            <div>
              <h3 class="text-[18px] font-bold text-slate-800">Edit Course: {{ selectedCourse?.name }}</h3>
              <p class="text-[13px] text-slate-500 mt-0.5">Update course details and settings.</p>
            </div>
            <button @click="showEditModal = false" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <div class="p-4 sm:p-6 overflow-y-auto space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
              <!-- Course Code -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Course Code <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.code"
                  type="text"
                  placeholder="e.g., CS-301"
                  :class="courseFieldCls('code')"
                  @blur="touchCourseField('code')"
                  @input="courseFormTouched.code && validateCourseField('code')"
                >
                <p v-if="courseFormErrors.code" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.code }}
                </p>
                <p v-else-if="courseFormTouched.code && !courseFormErrors.code" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Course Title -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Course Title <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.title"
                  type="text"
                  placeholder="Enter course title"
                  :class="courseFieldCls('title')"
                  @blur="touchCourseField('title')"
                  @input="courseFormTouched.title && validateCourseField('title')"
                >
                <p v-if="courseFormErrors.title" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.title }}
                </p>
                <p v-else-if="courseFormTouched.title && !courseFormErrors.title" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Department -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Department <span class="text-rose-500">*</span></label>
                <select
                  v-model="newCourseForm.department_id"
                  :class="courseFieldCls('department_id', 'appearance-none bg-white')"
                  @blur="touchCourseField('department_id')"
                  @change="touchCourseField('department_id')"
                >
                  <option value="">Select department</option>
                  <option v-for="d in allDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <p v-if="courseFormErrors.department_id" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.department_id }}
                </p>
                <p v-else-if="courseFormTouched.department_id && !courseFormErrors.department_id" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Academic Year Level -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Academic Year Level <span class="text-rose-500">*</span></label>
                <select
                  v-model="newCourseForm.level"
                  :class="courseFieldCls('level', 'appearance-none bg-white')"
                  @blur="touchCourseField('level')"
                  @change="touchCourseField('level')"
                >
                  <option value="">Select Academic Year</option>
                  <option value="1st Year">1st Year</option>
                  <option value="2nd Year">2nd Year</option>
                  <option value="3rd Year">3rd Year</option>
                  <option value="4th Year">4th Year</option>
                  <option value="5th Year">5th Year</option>
                </select>
                <p v-if="courseFormErrors.level" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.level }}
                </p>
                <p v-else-if="courseFormTouched.level && !courseFormErrors.level" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Semester -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Semester <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.semester"
                  type="text"
                  disabled
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-500 bg-slate-50 cursor-not-allowed font-medium"
                >
                <p class="mt-1.5 text-[11px] text-slate-400">Current academic semester (auto-assigned)</p>
              </div>

              <!-- Credits -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Credits <span class="text-rose-500">*</span></label>
                <input
                  v-model="newCourseForm.credits"
                  type="number"
                  min="1"
                  max="30"
                  placeholder="Enter credit hours"
                  :class="courseFieldCls('credits')"
                  @blur="touchCourseField('credits')"
                  @input="courseFormTouched.credits && validateCourseField('credits')"
                >
                <p v-if="courseFormErrors.credits" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.credits }}
                </p>
                <p v-else-if="courseFormTouched.credits && !courseFormErrors.credits" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Looks good!
                </p>
              </div>

              <!-- Start Date (Optional) -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Start Date <span class="text-[11px] text-slate-400 font-medium">(Optional)</span></label>
                <input
                  v-model="newCourseForm.start_date"
                  type="date"
                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none"
                  @change="touchCourseField('start_date')"
                >
              </div>

              <!-- End Date (Optional) -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">End Date <span class="text-[11px] text-slate-400 font-medium">(Optional)</span></label>
                <input
                  v-model="newCourseForm.end_date"
                  type="date"
                  :class="courseFieldCls('end_date', 'appearance-none bg-white')"
                  @change="touchCourseField('end_date')"
                >
                <p v-if="courseFormErrors.end_date" class="mt-1.5 text-[11px] text-rose-500 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                  {{ courseFormErrors.end_date }}
                </p>
                <p v-else-if="courseFormTouched.end_date && newCourseForm.end_date && !courseFormErrors.end_date" class="mt-1.5 text-[11px] text-emerald-600 flex items-center gap-1">
                  <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  Valid date
                </p>
              </div>

              <!-- Visibility (Optional) -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Visibility <span class="text-[11px] text-slate-400 font-medium">(Optional)</span></label>
                <select v-model="newCourseForm.visibility" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none">
                  <option value="Visible to Students">Visible to Students</option>
                  <option value="Hidden">Hidden</option>
                </select>
              </div>

              <!-- Enrollment Status (Optional) -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-2">Enrollment Status <span class="text-[11px] text-slate-400 font-medium">(Optional)</span></label>
                <select v-model="newCourseForm.enrollment_status" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-[13px] text-slate-600 focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] bg-white appearance-none">
                  <option value="Open for Enrollment">Open for Enrollment</option>
                  <option value="Closed">Closed</option>
                </select>
              </div>
            </div>

            <!-- Server Error Banner -->
            <div v-if="courseFormErrors._server" class="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-3 mt-4">
              <div class="w-5 h-5 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
              </div>
              <div class="flex-1 text-[13px] text-rose-800 font-medium whitespace-pre-line leading-relaxed">
                {{ courseFormErrors._server }}
              </div>
              <button @click="delete courseFormErrors._server" class="text-rose-400 hover:text-rose-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
              </button>
            </div>

            <!-- Status Toggle -->
            <div class="flex items-center justify-between p-4 border border-slate-200 rounded-xl bg-slate-50 mt-4">
              <div>
                <label class="block text-[13px] font-bold text-slate-700">Course Status</label>
                <p class="text-[11px] text-slate-500 mt-0.5">Toggle to set course as active or inactive.</p>
              </div>
              <button 
                @click="newCourseForm.status = newCourseForm.status === 'active' ? 'inactive' : 'active'" 
                :class="[
                  newCourseForm.status === 'active' ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-slate-300 hover:bg-slate-400',
                  'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none'
                ]" 
                role="switch" 
                :aria-checked="newCourseForm.status === 'active'"
              >
                <span 
                  aria-hidden="true" 
                  :class="[
                    newCourseForm.status === 'active' ? 'translate-x-5' : 'translate-x-0',
                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                  ]"
                ></span>
              </button>
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
            <button @click="showEditModal = false" class="px-5 py-2.5 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors">Cancel</button>
            <button @click="saveCourse" :disabled="isLoading" class="flex items-center gap-2 px-5 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-700 rounded-xl shadow-sm transition-all disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer">
              <svg v-if="isLoading" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              {{ isLoading ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════ IMPORT COURSES MODAL ════════════════ -->
    <Teleport to="body">
      <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-lg overflow-hidden flex flex-col my-8 border border-slate-100 max-h-[90vh]">
          <!-- Modal Header -->
          <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-[17px] font-bold text-slate-800">Import Courses</h3>
              <p class="text-[12px] text-slate-500 mt-0.5">Upload a CSV or PDF file to batch register courses.</p>
            </div>
            <button @click="showImportModal = false" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <div class="p-4 sm:p-6 space-y-5 overflow-y-auto">
            <!-- Format Requirements Banner -->
            <div class="p-4 bg-indigo-50/60 border border-indigo-100 rounded-xl space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-bold text-[#4338ca] uppercase tracking-wide flex items-center gap-1.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  Required Format: Add Course Form
                </span>
                <button @click="downloadSampleCsv" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-indigo-200 text-[#4338ca] hover:bg-indigo-50 font-bold text-[11px] rounded-lg transition-colors shadow-xs">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                  Download Sample CSV
                </button>
              </div>
              <p class="text-[12px] text-slate-600 leading-relaxed">
                The file (CSV or PDF) must fulfill the Add Course form format.
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 text-[11px]">
                <div class="bg-white p-2 rounded-lg border border-indigo-100">
                  <span class="font-bold text-slate-700 block mb-0.5">Required Fields:</span>
                  <span class="text-rose-600 font-medium">Course Code, Course Title, Department</span>
                </div>
                <div class="bg-white p-2 rounded-lg border border-indigo-100">
                  <span class="font-bold text-slate-700 block mb-0.5">Optional Fields:</span>
                  <span class="text-slate-500">Level <em class="text-indigo-500">(defaults to 1st Year)</em>, Credits <em class="text-indigo-500">(defaults to 3)</em>, Semester, Section, Status, Start Date, End Date, Description</span>
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
              class="flex items-center gap-2 px-6 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-700 rounded-xl transition-colors shadow-sm disabled:opacity-50 cursor-pointer"
            >
              <svg v-if="isImporting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              {{ isImporting ? 'Importing Courses...' : 'Upload & Import' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════ FORMAT ISSUES / ERROR POPUP MODAL ════════════════ -->
    <Teleport to="body">
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

            <!-- Required Add Course Format Reference -->
            <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-4 space-y-2 text-[12px]">
              <p class="font-bold text-[#4338ca]">How to fix this file:</p>
              <ul class="list-disc list-inside text-slate-600 space-y-1 text-[11px]">
                <li>File must include columns: <strong class="text-slate-800">Course Code, Course Title, Department</strong></li>
                <li>Academic Year Level is <strong class="text-slate-800">optional</strong> — if blank, defaults to <strong class="text-slate-800">1st Year</strong></li>
                <li>Credits is <strong class="text-slate-800">optional</strong> — if blank, defaults to <strong class="text-slate-800">3</strong></li>
                <li>Department must match one of the system departments: <strong class="text-slate-800" v-for="d in allDepartments" :key="d.id">{{ d.name }}, </strong></li>
                <li>If Academic Year Level is provided it must be: <strong class="text-slate-800">1st Year, 2nd Year, 3rd Year, 4th Year, or 5th Year</strong></li>
                <li>Course Code must be unique and not already exist in the system</li>
              </ul>
              <div class="pt-2">
                <button @click="downloadSampleCsv" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#4338ca] text-[#4338ca] hover:bg-indigo-50 font-bold text-[12px] rounded-lg transition-colors shadow-xs cursor-pointer">
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
              class="px-5 py-2.5 text-[13px] font-bold text-white bg-[#4338ca] hover:bg-indigo-700 rounded-xl transition-colors shadow-sm cursor-pointer"
            >
              Try Again
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════════════════ SUCCESS CONFIRMATION POPUP MODAL ════════════════ -->
    <Teleport to="body">
      <div v-if="showImportSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col my-8 border border-emerald-200">
          <div class="p-6 text-center space-y-4">
            <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto shadow-sm">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div>
              <h3 class="text-[18px] font-bold text-slate-800">Courses Imported Successfully!</h3>
              <p class="text-[13px] text-slate-500 mt-1">{{ importSuccessMessage }}</p>
            </div>

            <!-- List of imported courses -->
            <div v-if="importedCoursesList.length > 0" class="max-h-48 overflow-y-auto border border-slate-100 rounded-xl divide-y divide-slate-100 text-left">
              <div v-for="course in importedCoursesList" :key="course.id" class="p-3 flex items-center justify-between bg-slate-50/50">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded bg-indigo-50 text-[#4338ca]">{{ course.code }}</span>
                    <p class="text-[13px] font-bold text-slate-800">{{ course.title }}</p>
                  </div>
                  <p class="text-[11px] text-slate-400 mt-0.5">{{ course.level }} · {{ course.credits }} Credits</p>
                </div>
                <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-md bg-slate-100 text-slate-700">
                  {{ course.department }}
                </span>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end bg-slate-50/50">
            <button
              @click="showImportSuccessModal = false"
              class="w-full sm:w-auto px-6 py-2.5 text-[13px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors shadow-sm cursor-pointer"
            >
              Done & View Courses
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
