<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../../modules/auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()

const currentPage = ref(1)
const perPage = 10
const currentView = ref<'list' | 'schedule' | 'review'>('list')
const showAddCourseModal = ref(false)
const showSuccessModal = ref(false)
const successMessage = ref('')

// ── Search & Filter State ──
const search = ref('')
const semesterFilter = ref('all')
const courseFilter = ref('all')
const statusFilter = ref('all')

const stats = ref([
  { label: 'Total Exams',     value: '0', change: '↑ 5 this semester', color: 'text-emerald-500', bg: 'bg-indigo-50',  iconColor: 'text-[#5138ed]',    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { label: 'Scheduled Exams', value: '0', change: '↑ 3 this semester', color: 'text-emerald-500', bg: 'bg-emerald-50', iconColor: 'text-emerald-500', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { label: 'Upcoming Exams',  value: '0', change: '↑ 2 this week',     color: 'text-emerald-500', bg: 'bg-amber-50',   iconColor: 'text-amber-500',   icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { label: 'Conflicts',       value: '0', change: 'None detected',     color: 'text-slate-500',   bg: 'bg-rose-50',    iconColor: 'text-rose-500',    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
])

const exams = ref<any[]>([])
const availableCourses = ref<any[]>([])
const availableInstructors = ref<any[]>([])

// ── Real computed values from stores ──────────────────────────────
const academicYear = computed(() => {
  const yr = settingsStore.academicYear
  if (!yr) return '2026/2027'
  if (/^\d{4}$/.test(yr)) return `${yr}/${parseInt(yr) + 1}`
  return yr
})

const currentSemester = computed(() => settingsStore.semester || 'First Semester')

const departmentName = computed(() => {
  const dept = authStore.user?.department
  if (!dept) return 'computer scince'
  return dept.name || dept.code || 'computer scince'
})

const facultyName = 'College of Informatics'

const fetchExams = async () => {
  try {
    const response = await apiClient.get('/dept-head/exams')
    exams.value = response.data.data || []
    
    // Update stats from backend data
    const total = exams.value.length
    const scheduled = exams.value.filter((e: any) => ['Scheduled', 'Published'].includes(e.status)).length
    const upcoming = exams.value.filter((e: any) => e.status === 'Scheduled').length
    stats.value[0].value = total.toString()
    stats.value[1].value = scheduled.toString()
    stats.value[2].value = upcoming.toString()
  } catch (error) {
    console.error('Failed to fetch exams:', error)
  }
}

const fetchCourses = async () => {
  try {
    const response = await apiClient.get('/dept-head/courses')
    availableCourses.value = response.data?.data || []
  } catch (error) {
    console.error('Failed to fetch courses:', error)
  }
}

const fetchInstructors = async () => {
  try {
    const response = await apiClient.get('/dept-head/instructors')
    availableInstructors.value = response.data?.data || []
  } catch (error) {
    console.error('Failed to fetch instructors:', error)
  }
}

onMounted(async () => {
  fetchExams()
  fetchCourses()
  fetchInstructors()
  if (!settingsStore.academicYear) {
    await settingsStore.fetchSettings()
  }
  syncFormFromStores()
})

// ── Form State ──
const addForm = ref({
  title: 'Semester I Mid Examination Schedule',
  academic_year: '2026/2027',
  semester: 'First Semester',
  exam_type: 'Mid Examination',
  department: '',
  faculty: facultyName,
  start_date: '2026-06-02',
  end_date: '2026-06-10',
  description: 'Semester I Mid Examination Schedule for all undergraduate programs in the Department of Computer Science.',
  year_level: '1st Year',
  courses: [] as any[]
})

// Validation Errors
const formErrors = ref({
  academic_year: '',
  semester: '',
  exam_type: '',
  year_level: '',
  title: '',
  start_date: '',
  end_date: '',
  courses: '',
})

function syncFormFromStores() {
  addForm.value.academic_year = academicYear.value || '2026/2027'
  addForm.value.semester = currentSemester.value || 'First Semester'
  addForm.value.department = departmentName.value || 'computer scince'
  addForm.value.faculty = facultyName
}

watch([academicYear, currentSemester, departmentName], () => {
  syncFormFromStores()
}, { immediate: true })

// ── Course Modal State & Validation ──
const newCourse = ref({
  name: '',
  code: '',
  date: '',
  time: '',
  room: '',
  inv: '',
  notes: ''
})

const courseModalErrors = ref({
  name: '',
  code: '',
  date: '',
  time: '',
  room: '',
  inv: '',
})

const filteredCourses = computed(() => {
  if (!availableCourses.value.length) return []
  if (!addForm.value.year_level) return availableCourses.value
  
  const targetYear = addForm.value.year_level.replace(/[^0-9]/g, '')
  const matched = availableCourses.value.filter(c => {
    const lvl = (c.year_level || c.level || '').replace(/[^0-9]/g, '')
    return !lvl || lvl === targetYear
  })
  return matched.length ? matched : availableCourses.value
})

const selectedCourseId = ref('')

watch(selectedCourseId, (newId) => {
  const course = availableCourses.value.find(c => c.id == newId)
  if (course) {
    newCourse.value.name = course.title
    newCourse.value.code = course.code
    courseModalErrors.value.name = ''
    courseModalErrors.value.code = ''
    if (course.instructor?.name) {
      newCourse.value.inv = course.instructor.name
      courseModalErrors.value.inv = ''
    }
  }
})

const validateCourseModal = (): boolean => {
  let isValid = true
  courseModalErrors.value = { name: '', code: '', date: '', time: '', room: '', inv: '' }

  if (!newCourse.value.name.trim()) {
    courseModalErrors.value.name = 'Please select or enter course name'
    isValid = false
  }
  if (!newCourse.value.code.trim()) {
    courseModalErrors.value.code = 'Course code is required'
    isValid = false
  }
  if (!newCourse.value.date) {
    courseModalErrors.value.date = 'Exam date is required'
    isValid = false
  }
  if (!newCourse.value.time.trim()) {
    courseModalErrors.value.time = 'Exam time is required'
    isValid = false
  }
  if (!newCourse.value.room.trim()) {
    courseModalErrors.value.room = 'Room is required'
    isValid = false
  }
  if (!newCourse.value.inv.trim()) {
    courseModalErrors.value.inv = 'Invigilator is required'
    isValid = false
  }

  return isValid
}

const addCourse = () => {
  if (!validateCourseModal()) return
  addForm.value.courses.push({ ...newCourse.value })
  newCourse.value = { name: '', code: '', date: '', time: '', room: '', inv: '', notes: '' }
  selectedCourseId.value = ''
  formErrors.value.courses = ''
  showAddCourseModal.value = false
}

const removeCourse = (index: number) => {
  addForm.value.courses.splice(index, 1)
}

// ── Schedule Form Validation ──
const validateScheduleForm = (): boolean => {
  let isValid = true
  formErrors.value = {
    academic_year: '',
    semester: '',
    exam_type: '',
    year_level: '',
    title: '',
    start_date: '',
    end_date: '',
    courses: '',
  }

  if (!addForm.value.academic_year.trim()) {
    formErrors.value.academic_year = 'Academic Year is required'
    isValid = false
  }
  if (!addForm.value.semester.trim()) {
    formErrors.value.semester = 'Semester is required'
    isValid = false
  }
  if (!addForm.value.exam_type.trim()) {
    formErrors.value.exam_type = 'Exam Type is required'
    isValid = false
  }
  if (!addForm.value.year_level.trim()) {
    formErrors.value.year_level = 'Year Level is required'
    isValid = false
  }
  if (!addForm.value.title.trim()) {
    formErrors.value.title = 'Schedule Title is required'
    isValid = false
  }
  if (!addForm.value.start_date) {
    formErrors.value.start_date = 'Start Date is required'
    isValid = false
  }
  if (!addForm.value.end_date) {
    formErrors.value.end_date = 'End Date is required'
    isValid = false
  }
  if (addForm.value.start_date && addForm.value.end_date && addForm.value.end_date < addForm.value.start_date) {
    formErrors.value.end_date = 'End Date must be on or after Start Date'
    isValid = false
  }
  if (addForm.value.courses.length === 0) {
    formErrors.value.courses = 'Please add at least one course to the examination schedule before proceeding.'
    isValid = false
  }

  return isValid
}

const goToReview = () => {
  if (!validateScheduleForm()) {
    return
  }
  currentView.value = 'review'
}

// ── Submit & Review ──
const isSubmitting = ref(false)
const submitError = ref('')

const confirmChecks = ref({
  dates: false,
  rooms: false,
  invigilators: false,
  notify: false,
})

const allConfirmed = computed(() =>
  confirmChecks.value.dates &&
  confirmChecks.value.rooms &&
  confirmChecks.value.invigilators &&
  confirmChecks.value.notify
)

const submitSchedule = async () => {
  if (!allConfirmed.value) {
    submitError.value = 'Please confirm all 4 checkboxes before publishing.'
    return
  }
  isSubmitting.value = true
  submitError.value = ''
  try {
    await apiClient.post('/dept-head/exams', addForm.value)
    await fetchExams()
    
    // Show success popup
    successMessage.value = `The examination schedule "${addForm.value.title}" with ${addForm.value.courses.length} courses has been successfully published!`
    showSuccessModal.value = true
    
    // Reset form
    addForm.value.courses = []
    addForm.value.title = 'Semester I Mid Examination Schedule'
    addForm.value.description = ''
    confirmChecks.value = { dates: false, rooms: false, invigilators: false, notify: false }
  } catch (error: any) {
    submitError.value = error?.response?.data?.message || 'Failed to publish schedule. Please check all fields and try again.'
    console.error('Failed to create schedule:', error)
  } finally {
    isSubmitting.value = false
  }
}

const closeSuccessModal = () => {
  showSuccessModal.value = false
  currentView.value = 'list'
}

const deleteExam = async (id: number) => {
  if (!confirm('Are you sure you want to delete this exam schedule?')) return
  try {
    await apiClient.delete(`/dept-head/exams/${id}`)
    await fetchExams()
  } catch (error) {
    console.error('Failed to delete exam:', error)
    alert('Failed to delete exam. Please try again.')
  }
}

// ── Filtered & Paginated Exams for List View ──
const filteredExams = computed(() => {
  return exams.value.filter(exam => {
    const q = search.value.trim().toLowerCase()
    const matchSearch = !q ||
      (exam.title && exam.title.toLowerCase().includes(q)) ||
      (exam.code && exam.code.toLowerCase().includes(q)) ||
      (exam.course && exam.course.toLowerCase().includes(q)) ||
      (exam.courseName && exam.courseName.toLowerCase().includes(q))

    const matchSemester = semesterFilter.value === 'all' || exam.semester === semesterFilter.value
    const matchCourse = courseFilter.value === 'all' || (exam.courseCode === courseFilter.value || exam.code === courseFilter.value)
    const matchStatus = statusFilter.value === 'all' || (exam.status && exam.status.toLowerCase() === statusFilter.value.toLowerCase())

    return matchSearch && matchSemester && matchCourse && matchStatus
  })
})

const totalItems = computed(() => filteredExams.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / perPage)))
const paginatedExams = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredExams.value.slice(start, start + perPage)
})

watch([search, semesterFilter, courseFilter, statusFilter], () => {
  currentPage.value = 1
})

const statusBadge = (status: string) => {
  const s = (status || '').toLowerCase()
  if (s === 'scheduled' || s === 'published') return 'text-emerald-600 bg-emerald-50'
  if (s === 'conflict')  return 'text-rose-600 bg-rose-50'
  if (s === 'completed') return 'text-sky-600 bg-sky-50'
  if (s === 'cancelled') return 'text-rose-600 bg-rose-50'
  return 'text-amber-600 bg-amber-50'
}

const calIcon  = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
</script>

<template>
  <div>

    <!-- ══════════════════════════════════════════════════
         LIST VIEW
    ══════════════════════════════════════════════════ -->
    <div v-if="currentView === 'list'" class="space-y-6">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Exam Schedule</h1>
          <p class="text-[13px] text-slate-500 mt-0.5 sm:mt-1">Manage and monitor exam schedules for your department.</p>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
        <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center gap-4">
            <div :class="[stat.bg, stat.iconColor, 'w-12 h-12 rounded-xl flex items-center justify-center shrink-0']">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"></path></svg>
            </div>
            <div>
              <p class="text-[12px] font-bold text-slate-500">{{ stat.label }}</p>
              <h3 class="text-xl sm:text-[24px] font-bold text-slate-800 leading-tight mt-1">{{ stat.value }}</h3>
              <p :class="[stat.color, 'text-[11px] font-bold mt-1']">{{ stat.change }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden min-w-0 max-w-full">

        <!-- Toolbar -->
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3 sm:gap-4">
          <div class="flex flex-col md:flex-row items-stretch md:items-center gap-3 flex-1">
            <!-- Search -->
            <div class="relative w-full md:w-80">
              <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              <input v-model="search" type="text" placeholder="Search exams by title, course or code..." class="w-full pl-10 pr-4 py-2.5 min-h-[44px] bg-slate-50 border border-slate-200 rounded-xl text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-slate-600 placeholder:text-slate-400" />
            </div>
            <!-- Dropdowns -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1">
              <div class="relative w-full">
                <select v-model="semesterFilter" class="w-full appearance-none px-4 py-2.5 min-h-[44px] bg-white border border-slate-200 rounded-xl text-[13px] font-medium text-slate-700 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all cursor-pointer">
                  <option value="all">All Semesters</option>
                  <option value="Semester 1">Semester 1</option>
                  <option value="Semester 2">Semester 2</option>
                </select>
                <svg class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </div>
              <div class="relative w-full">
                <select v-model="courseFilter" class="w-full appearance-none px-4 py-2.5 min-h-[44px] bg-white border border-slate-200 rounded-xl text-[13px] font-medium text-slate-700 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all cursor-pointer">
                  <option value="all">All Courses</option>
                  <option v-for="c in availableCourses" :key="c.id" :value="c.code">{{ c.title }}</option>
                </select>
                <svg class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </div>
              <div class="relative w-full">
                <select v-model="statusFilter" class="w-full appearance-none px-4 py-2.5 min-h-[44px] bg-white border border-slate-200 rounded-xl text-[13px] font-medium text-slate-700 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all cursor-pointer">
                  <option value="all">All Status</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="published">Published</option>
                  <option value="completed">Completed</option>
                  <option value="draft">Draft</option>
                </select>
                <svg class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </div>
            </div>
            <!-- Filter btn -->
            <button @click="currentPage = 1" class="flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] border border-[#5138ed] text-[#5138ed] rounded-xl text-[13px] font-bold hover:bg-indigo-50 transition-colors shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
              Filter
            </button>
          </div>
          <!-- Schedule btn -->
          <button @click="currentView = 'schedule'" class="flex items-center justify-center gap-2 bg-[#5138ed] text-white px-5 py-2.5 min-h-[44px] rounded-xl text-[13px] font-bold hover:bg-indigo-600 transition-colors shadow-sm shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Schedule Exam
          </button>
        </div>

        <!-- Desktop / Tablet Table -->
        <div class="hidden md:block overflow-x-auto min-w-0 w-full">
          <table class="w-full min-w-[900px]">
            <thead class="bg-slate-50 border-b border-slate-100">
              <tr>
                <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Exam Title</th>
                <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course Code</th>
                <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course</th>
                <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Date</th>
                <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Room</th>
                <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                <th class="text-center px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="paginatedExams.length === 0">
                <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                  <p class="text-[14px] font-semibold text-slate-600">No scheduled exams found</p>
                  <p class="text-[12px] text-slate-400 mt-1">Click "Schedule Exam" to create a new department schedule.</p>
                </td>
              </tr>
              <tr v-for="exam in paginatedExams" :key="exam.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-6 py-4">
                  <span class="text-[13px] font-bold text-slate-700">{{ exam.title }}</span>
                </td>
                <td class="px-6 py-4 text-[13px] font-bold text-slate-600">{{ exam.code || exam.courseCode }}</td>
                <td class="px-6 py-4 text-[13px] text-slate-600 font-medium">{{ exam.course || exam.courseName }}</td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-1.5 mb-0.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="calIcon"></path></svg>
                    <span class="text-[13px] font-bold text-slate-700">{{ exam.date }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 text-[13px] text-slate-600 font-medium">{{ exam.room || 'Room 101' }}</td>
                <td class="px-6 py-4">
                  <span :class="[statusBadge(exam.status), 'text-[11px] font-bold px-2.5 py-1 rounded-md capitalize']">{{ exam.status }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="deleteExam(exam.id)" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors" title="Delete Schedule">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards List -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-if="paginatedExams.length === 0" class="p-8 text-center text-slate-400">
            <p class="text-[14px] font-semibold text-slate-600">No scheduled exams found</p>
            <p class="text-[12px] text-slate-400 mt-1">Click "Schedule Exam" to create one.</p>
          </div>
          <div v-for="exam in paginatedExams" :key="'m-'+exam.id" class="p-4 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <div>
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-100 text-slate-700 mb-1">
                  {{ exam.code || exam.courseCode }}
                </span>
                <h3 class="text-[15px] font-bold text-slate-800 leading-snug">{{ exam.title }}</h3>
                <p class="text-[12px] text-slate-500 font-medium">{{ exam.course || exam.courseName }}</p>
              </div>
              <span :class="[statusBadge(exam.status), 'text-[11px] font-bold px-2 py-0.5 rounded-md capitalize shrink-0']">
                {{ exam.status }}
              </span>
            </div>

            <div class="grid grid-cols-2 gap-2 text-[12px] bg-slate-50/70 p-3 rounded-xl">
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Exam Date</span>
                <span class="font-bold text-slate-700">{{ exam.date }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Room</span>
                <span class="font-medium text-slate-700">{{ exam.room || 'Room 101' }}</span>
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
              <button 
                @click="deleteExam(exam.id)" 
                class="min-h-[44px] px-4 py-2 text-[12px] font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition-colors flex items-center justify-center gap-1.5"
              >
                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Delete Schedule
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
          <p class="text-[12px] sm:text-[13px] font-medium text-slate-500 text-center sm:text-left">
            Showing {{ filteredExams.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, filteredExams.length) }} of {{ filteredExams.length }} exams
          </p>
          <div class="flex items-center gap-1">
            <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="w-9 h-9 min-h-[36px] flex items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-600 hover:shadow-sm transition-all border border-transparent hover:border-slate-200 disabled:opacity-40">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <button
              v-for="page in totalPages" :key="page"
              @click="currentPage = page"
              :class="['w-9 h-9 min-h-[36px] flex items-center justify-center rounded-lg text-[13px] font-bold transition-all', currentPage === page ? 'bg-[#5138ed] text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:shadow-sm border border-transparent hover:border-slate-200']"
            >{{ page }}</button>
            <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="w-9 h-9 min-h-[36px] flex items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-600 hover:shadow-sm transition-all border border-transparent hover:border-slate-200 disabled:opacity-40">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>
        </div>

      </div><!-- end Table Card -->

    </div><!-- end LIST VIEW -->


    <!-- ══════════════════════════════════════════════════
         SCHEDULE EXAM VIEW (STEP 1)
    ══════════════════════════════════════════════════ -->
    <div v-else-if="currentView === 'schedule'" class="space-y-6">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Schedule New Examination</h1>
          <p class="text-[13px] text-slate-500 mt-0.5 sm:mt-1">Create a new examination schedule for your department.</p>
        </div>
        <button @click="currentView = 'list'" class="w-full sm:w-auto min-h-[44px] flex items-center justify-center gap-2 px-4 py-2 text-[12px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Schedule
        </button>
      </div>

      <!-- 2-Step Stepper -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-4 sm:px-8 py-4 sm:py-5 flex items-center">
        <!-- Step 1 active -->
        <div class="flex items-center gap-2 sm:gap-3">
          <div class="w-8 h-8 rounded-full bg-[#5138ed] text-white flex items-center justify-center font-bold text-[13px] shadow-sm">1</div>
          <span class="text-[#5138ed] font-bold text-[13px] sm:text-[14px]">Exam Information</span>
        </div>
        <!-- Connector line -->
        <div class="flex-1 mx-3 sm:mx-6 h-px bg-slate-200"></div>
        <!-- Step 2 inactive -->
        <div class="flex items-center gap-2 sm:gap-3">
          <div class="w-8 h-8 rounded-full border-2 border-slate-200 text-slate-400 flex items-center justify-center font-bold text-[13px]">2</div>
          <span class="text-slate-400 font-bold text-[13px] sm:text-[14px]">Review &amp; Confirm</span>
        </div>
      </div>

      <!-- Two-column body -->
      <div class="flex flex-col xl:flex-row gap-6 items-start">

        <!-- ── Left: Form ── -->
        <div class="flex-1 w-full min-w-0 space-y-6">

          <!-- Exam Information card -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6">
            <div class="flex gap-4 mb-6">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
              </div>
              <div>
                <h3 class="text-[15px] font-bold text-slate-800">Exam Information</h3>
                <p class="text-[12px] text-slate-500 mt-0.5">Configure the details for this examination session.</p>
              </div>
            </div>

            <!-- Row 1: Academic Year / Semester / Exam Type / Year Level -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
              <div class="space-y-1.5">
                <label class="text-[12px] font-bold text-slate-700">Academic Year <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    type="text"
                    v-model="addForm.academic_year"
                    @input="formErrors.academic_year = ''"
                    placeholder="e.g. 2026/2027"
                    :class="[
                      'w-full px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors font-medium',
                      formErrors.academic_year ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                    ]"
                  />
                </div>
                <p v-if="formErrors.academic_year" class="text-[11px] text-rose-500 font-medium">{{ formErrors.academic_year }}</p>
              </div>

              <div class="space-y-1.5">
                <label class="text-[12px] font-bold text-slate-700">Semester <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select
                    v-model="addForm.semester"
                    @change="formErrors.semester = ''"
                    :class="[
                      'w-full appearance-none px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors cursor-pointer',
                      formErrors.semester ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                    ]"
                  >
                    <option value="First Semester">First Semester</option>
                    <option value="Second Semester">Second Semester</option>
                    <option value="Summer Semester">Summer Semester</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <p v-if="formErrors.semester" class="text-[11px] text-rose-500 font-medium">{{ formErrors.semester }}</p>
              </div>

              <div class="space-y-1.5">
                <label class="text-[12px] font-bold text-slate-700">Exam Type <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select
                    v-model="addForm.exam_type"
                    @change="formErrors.exam_type = ''"
                    :class="[
                      'w-full appearance-none px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors cursor-pointer',
                      formErrors.exam_type ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                    ]"
                  >
                    <option value="Mid Examination">Mid Examination</option>
                    <option value="Final Examination">Final Examination</option>
                    <option value="Quiz">Quiz</option>
                    <option value="Supplementary Examination">Supplementary Examination</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <p v-if="formErrors.exam_type" class="text-[11px] text-rose-500 font-medium">{{ formErrors.exam_type }}</p>
              </div>

              <div class="space-y-1.5">
                <label class="text-[12px] font-bold text-slate-700">Year Level <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select
                    v-model="addForm.year_level"
                    @change="formErrors.year_level = ''"
                    :class="[
                      'w-full appearance-none px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors cursor-pointer',
                      formErrors.year_level ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                    ]"
                  >
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                    <option value="5th Year">5th Year</option>
                  </select>
                  <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <p v-if="formErrors.year_level" class="text-[11px] text-rose-500 font-medium">{{ formErrors.year_level }}</p>
              </div>
            </div>

            <!-- Row 2: Department / Faculty -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
              <div class="space-y-1.5">
                <label class="text-[12px] font-bold text-slate-700">Department</label>
                <input type="text" v-model="addForm.department" disabled class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-200 rounded-lg text-[13px] text-slate-600 focus:outline-none cursor-not-allowed font-medium capitalize" />
              </div>
              <div class="space-y-1.5">
                <label class="text-[12px] font-bold text-slate-700">Faculty</label>
                <input type="text" v-model="addForm.faculty" disabled class="w-full px-3 py-2.5 min-h-[44px] bg-slate-50 border border-slate-200 rounded-lg text-[13px] text-slate-600 focus:outline-none cursor-not-allowed font-medium" />
              </div>
            </div>

            <!-- Row 3: Schedule Title -->
            <div class="space-y-1.5 mb-4">
              <label class="text-[12px] font-bold text-slate-700">Schedule Title <span class="text-rose-500">*</span></label>
              <input
                type="text"
                v-model="addForm.title"
                @input="formErrors.title = ''"
                placeholder="e.g. Semester I Mid Examination Schedule"
                :class="[
                  'w-full px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors font-medium',
                  formErrors.title ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                ]"
              />
              <p v-if="formErrors.title" class="text-[11px] text-rose-500 font-medium">{{ formErrors.title }}</p>
            </div>

            <!-- Row 4: Start Date / End Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
              <div class="space-y-1.5">
                <label class="text-[12px] font-bold text-slate-700">Start Date <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    type="date"
                    v-model="addForm.start_date"
                    @change="formErrors.start_date = ''"
                    :class="[
                      'w-full pl-3 pr-10 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors',
                      formErrors.start_date ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                    ]"
                  />
                </div>
                <p v-if="formErrors.start_date" class="text-[11px] text-rose-500 font-medium">{{ formErrors.start_date }}</p>
              </div>

              <div class="space-y-1.5">
                <label class="text-[12px] font-bold text-slate-700">End Date <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    type="date"
                    v-model="addForm.end_date"
                    @change="formErrors.end_date = ''"
                    :class="[
                      'w-full pl-3 pr-10 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors',
                      formErrors.end_date ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                    ]"
                  />
                </div>
                <p v-if="formErrors.end_date" class="text-[11px] text-rose-500 font-medium">{{ formErrors.end_date }}</p>
              </div>
            </div>

            <!-- Row 5: Description -->
            <div class="space-y-1.5">
              <label class="text-[12px] font-bold text-slate-700">Description <span class="text-slate-400 font-normal">(optional)</span></label>
              <textarea
                v-model="addForm.description"
                rows="3"
                placeholder="Enter description or remarks for this examination schedule..."
                class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] text-slate-700 focus:outline-none focus:border-indigo-500 transition-colors resize-none placeholder:text-slate-400"
              ></textarea>
            </div>
          </div>

          <!-- Scheduled Courses card -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6">
            <!-- Card header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
              <div class="flex gap-3 sm:gap-4 items-center">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
                <div>
                  <h3 class="text-[15px] font-bold text-slate-800">Scheduled Courses</h3>
                  <p class="text-[12px] text-slate-500 mt-0.5">Add all courses included in this examination session.</p>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <span class="px-3 py-1 bg-[#5138ed] text-white text-[11px] font-bold rounded-full uppercase tracking-wider">
                  ● {{ addForm.exam_type }}
                </span>
                <button
                  type="button"
                  @click="showAddCourseModal = true"
                  class="min-h-[44px] flex items-center gap-1.5 bg-[#5138ed] text-white px-4 py-2 rounded-xl text-[12px] font-bold hover:bg-indigo-600 transition-colors shadow-sm cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                  Add Course
                </button>
              </div>
            </div>

            <!-- Error banner if courses empty upon proceeding -->
            <div v-if="formErrors.courses" class="mb-4 flex items-center gap-3 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 text-rose-700">
              <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <span class="text-[13px] font-bold">{{ formErrors.courses }}</span>
            </div>

            <!-- Courses table -->
            <div class="overflow-x-auto min-w-0 w-full">
              <table class="w-full min-w-[700px]">
                <thead>
                  <tr class="border-b border-slate-100">
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course Title</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course Code</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Exam Date</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Time</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Invigilator</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Room</th>
                    <th class="text-center pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-if="addForm.courses.length === 0">
                    <td colspan="7" class="py-8 text-center text-[13px] text-slate-400">
                      No courses added yet. Click "+ Add Course" to add courses to this schedule.
                    </td>
                  </tr>
                  <tr v-for="(course, index) in addForm.courses" :key="index" class="hover:bg-slate-50/60 transition-colors">
                    <td class="py-3 text-[13px] font-semibold text-slate-700">{{ course.name }}</td>
                    <td class="py-3 text-[13px] text-slate-600 font-medium">{{ course.code }}</td>
                    <td class="py-3">
                      <div class="flex items-center gap-1.5 text-[12px] text-slate-600">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="calIcon"></path></svg>
                        {{ course.date }}
                      </div>
                    </td>
                    <td class="py-3">
                      <div class="flex items-center gap-1.5 text-[12px] text-slate-600">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ course.time }}
                      </div>
                    </td>
                    <td class="py-3 text-[12px] text-slate-600 font-medium">{{ course.inv ?? 'TBD' }}</td>
                    <td class="py-3 text-[12px] text-slate-600 font-medium">{{ course.room }}</td>
                    <td class="py-3 text-center">
                      <button
                        type="button"
                        @click="removeCourse(index)"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        title="Remove course"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Add more link -->
            <button type="button" @click="showAddCourseModal = true" class="mt-3 flex items-center gap-1.5 text-[#5138ed] text-[12px] font-bold hover:underline cursor-pointer py-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
              Add Course to schedule more
            </button>

            <!-- Status bar -->
            <div v-if="addForm.courses.length > 0" class="mt-4 flex items-center gap-3 bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3">
              <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <span class="text-[13px] font-bold text-emerald-700">No scheduling conflicts detected. All {{ addForm.courses.length }} courses ready!</span>
            </div>
          </div>

          <!-- Bottom action bar -->
          <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pb-6">
            <button type="button" @click="currentView = 'list'" class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 border border-slate-200 text-slate-700 rounded-xl text-[13px] font-bold hover:bg-slate-50 transition-colors flex items-center justify-center">
              Cancel
            </button>
            <div class="flex items-center gap-3">
              <button type="button" @click="goToReview" class="w-full sm:w-auto min-h-[44px] flex items-center justify-center gap-2 px-6 py-2.5 bg-[#5138ed] text-white rounded-xl text-[13px] font-bold hover:bg-indigo-600 transition-colors shadow-sm cursor-pointer">
                Next
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
              </button>
            </div>
          </div>

        </div><!-- end Left form column -->

        <!-- ── Right: Schedule Summary sidebar ── -->
        <div class="w-full xl:w-64 shrink-0 space-y-4">
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-5">
            <h3 class="text-[14px] font-bold text-slate-800 mb-4">Schedule Summary</h3>
            <div class="space-y-4">
              <!-- Exam Type -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                  </div>
                  <span class="text-[12px] text-slate-500 font-medium">Exam Type</span>
                </div>
                <span class="text-[11px] font-bold text-[#5138ed] bg-indigo-50 px-2.5 py-1 rounded-lg uppercase">
                  {{ addForm.exam_type }}
                </span>
              </div>
              <!-- Semester -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                  </div>
                  <span class="text-[12px] text-slate-500 font-medium">Semester</span>
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ addForm.semester }}</span>
              </div>
              <!-- Academic Year -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                  </div>
                  <span class="text-[12px] text-slate-500 font-medium">Academic Year</span>
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ addForm.academic_year }}</span>
              </div>
              <!-- Courses Added -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                  </div>
                  <span class="text-[12px] text-slate-500 font-medium">Courses Added</span>
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ addForm.courses.length }}</span>
              </div>
              <!-- Divider -->
              <div class="border-t border-slate-100"></div>
              <!-- Schedule Status -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  </div>
                  <span class="text-[12px] text-slate-500 font-medium">Schedule Status</span>
                </div>
                <span class="text-[11px] font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-lg">DRAFT</span>
              </div>
              <!-- Conflicts -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                  </div>
                  <span class="text-[12px] text-slate-500 font-medium">Conflicts</span>
                </div>
                <div class="flex items-center gap-1.5 text-emerald-600">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  <span class="text-[12px] font-bold">None</span>
                </div>
              </div>
            </div>
          </div>
        </div><!-- end Right sidebar -->

      </div><!-- end two-column body -->

    </div><!-- end SCHEDULE EXAM VIEW -->

    <!-- ══════════════════════════════════════════════════
         REVIEW & CONFIRM VIEW (STEP 2)
    ══════════════════════════════════════════════════ -->
    <div v-else-if="currentView === 'review'" class="space-y-6">
      
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Review &amp; Confirm Schedule</h1>
          <p class="text-[13px] text-slate-500 mt-0.5 sm:mt-1">Review every examination before publishing the schedule to students.</p>
        </div>
        <button @click="currentView = 'schedule'" class="w-full sm:w-auto min-h-[44px] flex items-center justify-center gap-2 px-4 py-2 text-[12px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Schedule
        </button>
      </div>

      <!-- 2-Step Stepper -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-4 sm:px-8 py-4 sm:py-5 flex items-center">
        <!-- Step 1 completed -->
        <div class="flex items-center gap-2 sm:gap-3">
          <div class="w-8 h-8 rounded-full bg-indigo-50 text-[#5138ed] flex items-center justify-center font-bold text-[13px] shadow-sm shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-slate-800 font-bold text-[13px] sm:text-[14px] leading-none">Exam Information</span>
            <span class="text-slate-500 text-[11px] mt-1.5 leading-none">Completed</span>
          </div>
        </div>
        <!-- Connector line -->
        <div class="flex-1 mx-3 sm:mx-8 h-[2px] bg-[#5138ed]"></div>
        <!-- Step 2 active -->
        <div class="flex items-center gap-2 sm:gap-3">
          <div class="w-8 h-8 rounded-full bg-[#5138ed] text-white flex items-center justify-center font-bold text-[13px] shadow-sm shrink-0">2</div>
          <div class="flex flex-col">
            <span class="text-[#5138ed] font-bold text-[13px] sm:text-[14px] leading-none">Review &amp; Confirm</span>
            <span class="text-slate-500 text-[11px] mt-1.5 leading-none">Current Step</span>
          </div>
        </div>
      </div>

      <!-- Two-column body -->
      <div class="flex flex-col xl:flex-row gap-6 items-start">
        
        <!-- ── Left: Review Forms ── -->
        <div class="flex-1 w-full min-w-0 space-y-6">
          
          <!-- Schedule Summary -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6">
            <div class="flex gap-3 items-center mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
              <h3 class="text-[15px] font-bold text-slate-800">Schedule Summary</h3>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 gap-y-5 sm:gap-y-7">
              <div class="space-y-1">
                <p class="text-[12px] font-bold text-slate-500">Academic Year</p>
                <p class="text-[13px] font-bold text-slate-800">{{ addForm.academic_year }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-[12px] font-bold text-slate-500">Semester</p>
                <p class="text-[13px] font-bold text-slate-800">{{ addForm.semester }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-[12px] font-bold text-slate-500">Exam Type</p>
                <p class="text-[11px] font-bold text-[#5138ed] bg-indigo-50 px-2.5 py-1 rounded-lg inline-block uppercase">{{ addForm.exam_type }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-[12px] font-bold text-slate-500">Year Level</p>
                <p class="text-[13px] font-bold text-slate-800">{{ addForm.year_level }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-[12px] font-bold text-slate-500">Department</p>
                <p class="text-[13px] font-bold text-slate-800 capitalize">{{ addForm.department }}</p>
              </div>
              
              <div class="space-y-1">
                <p class="text-[12px] font-bold text-slate-500">Faculty</p>
                <p class="text-[13px] font-bold text-slate-800">{{ addForm.faculty }}</p>
              </div>
              <div class="space-y-1 col-span-1 sm:col-span-2">
                <p class="text-[12px] font-bold text-slate-500">Schedule Title</p>
                <p class="text-[13px] font-bold text-slate-800">{{ addForm.title }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-[12px] font-bold text-slate-500">Total Courses</p>
                <p class="text-[13px] font-bold text-slate-800">{{ addForm.courses.length }}</p>
              </div>
              
              <div class="space-y-1 col-span-1 sm:col-span-2">
                <p class="text-[12px] font-bold text-slate-500">Schedule Period</p>
                <div class="flex items-center gap-1.5 mt-0.5 text-slate-800">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="calIcon"></path></svg>
                  <p class="text-[13px] font-bold">{{ addForm.start_date }} – {{ addForm.end_date }}</p>
                </div>
              </div>
              <div class="space-y-1">
                <p class="text-[12px] font-bold text-slate-500">Status</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <p class="text-[11px] font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-lg inline-block">DRAFT</p>
                  <span class="text-[12px] font-medium text-slate-500">(Not Published)</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Course Schedule Review -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 overflow-hidden">
            <div class="flex gap-3 items-center mb-5">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
              <h3 class="text-[15px] font-bold text-slate-800">Course Schedule Review</h3>
            </div>
            
            <div class="overflow-x-auto min-w-0 w-full">
              <table class="w-full min-w-[750px]">
                <thead>
                  <tr class="border-b border-slate-100">
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">#</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course Title</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course Code</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Exam Date</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Time</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Room</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Invigilator</th>
                    <th class="text-left pb-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="(row, i) in addForm.courses" :key="i" class="hover:bg-slate-50/60 transition-colors">
                    <td class="py-3 pr-2 text-[12px] font-bold text-slate-500">{{ i+1 }}</td>
                    <td class="py-3 pr-2 text-[12px] font-bold text-slate-700 whitespace-nowrap">{{ row.name }}</td>
                    <td class="py-3 pr-2 text-[12px] font-bold text-slate-600">{{ row.code }}</td>
                    <td class="py-3 pr-2 text-[12px] text-slate-600 whitespace-nowrap">{{ row.date }}</td>
                    <td class="py-3 pr-2 text-[12px] text-[#5138ed] font-medium whitespace-nowrap">{{ row.time }}</td>
                    <td class="py-3 pr-2 text-[12px] text-slate-600 whitespace-nowrap">{{ row.room }}</td>
                    <td class="py-3 pr-2 text-[12px] text-slate-600 whitespace-nowrap">{{ row.inv ?? 'TBD' }}</td>
                    <td class="py-3 pr-2">
                      <span class="text-[10px] font-bold text-emerald-600 border border-emerald-100 bg-emerald-50 px-2.5 py-1 rounded-lg inline-block">Ready</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Conflict Validation -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6">
            <div class="flex gap-3 items-center mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
              <h3 class="text-[15px] font-bold text-slate-800">Conflict Validation</h3>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
              <div class="flex flex-col items-center justify-center text-center gap-2 p-2">
                <div class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center border border-emerald-100">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="text-[11px] font-medium text-slate-600 leading-tight">No classroom<br/>conflicts</p>
              </div>
              <div class="flex flex-col items-center justify-center text-center gap-2 p-2 sm:border-l sm:border-slate-100">
                <div class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center border border-emerald-100">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="text-[11px] font-medium text-slate-600 leading-tight">No instructor<br/>conflicts</p>
              </div>
              <div class="flex flex-col items-center justify-center text-center gap-2 p-2 lg:border-l lg:border-slate-100">
                <div class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center border border-emerald-100">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="text-[11px] font-medium text-slate-600 leading-tight">No overlapping<br/>exams</p>
              </div>
              <div class="flex flex-col items-center justify-center text-center gap-2 p-2 sm:border-l sm:border-slate-100">
                <div class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center border border-emerald-100">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="text-[11px] font-medium text-slate-600 leading-tight">All rooms<br/>available</p>
              </div>
              <div class="flex flex-col items-center justify-center text-center gap-2 p-2 sm:border-l sm:border-slate-100">
                <div class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center border border-emerald-100">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="text-[11px] font-medium text-slate-600 leading-tight">All invigilators<br/>assigned</p>
              </div>
            </div>

            <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-100 px-4 py-3 rounded-xl">
              <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <span class="text-[13px] font-bold text-emerald-700">Great! No conflicts found. Your schedule is ready to publish.</span>
            </div>
          </div>

          <!-- Bottom Row: Notification Preview & Final Confirmation -->
          <div class="flex flex-col lg:flex-row gap-6">
            
            <!-- Notification Preview -->
            <div class="flex-1 bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6">
              <div class="flex gap-3 items-center mb-5">
                <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                <h3 class="text-[15px] font-bold text-slate-800">Notification Preview</h3>
              </div>

              <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-10">
                <div class="flex-1 space-y-4">
                  <div class="space-y-1">
                    <p class="text-[11px] font-bold text-slate-800">Title</p>
                    <p class="text-[12px] text-[#5138ed] font-bold">{{ addForm.title }}</p>
                  </div>
                  <div class="space-y-1">
                    <p class="text-[11px] font-bold text-slate-800">Message</p>
                    <p class="text-[12px] text-slate-600 leading-relaxed sm:pr-4">Your examination schedule has been published.<br/>Please review your exam dates, rooms and times carefully.</p>
                  </div>
                </div>

                <div class="space-y-4 mt-2 sm:mt-0">
                  <div class="space-y-1.5">
                    <p class="text-[11px] font-bold text-slate-800">Recipients</p>
                    <div class="flex items-center gap-1.5 text-[12px] font-bold text-slate-600">
                      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                      All Enrolled Students
                    </div>
                    <div class="flex items-center gap-1.5 text-[12px] font-bold text-slate-600">
                      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                      Department Instructors
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Final Confirmation -->
            <div class="flex-1 bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6">
              <div class="flex gap-3 items-center mb-5">
                <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <h3 class="text-[15px] font-bold text-slate-800">Final Confirmation</h3>
              </div>

              <div class="space-y-4 mb-6">
                <label class="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" v-model="confirmChecks.dates" class="w-4 h-4 rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" />
                  <span class="text-[12px] text-slate-700">I confirm all examination dates are correct.</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" v-model="confirmChecks.rooms" class="w-4 h-4 rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" />
                  <span class="text-[12px] text-slate-700">I confirm rooms have been assigned.</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" v-model="confirmChecks.invigilators" class="w-4 h-4 rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" />
                  <span class="text-[12px] text-slate-700">I confirm invigilators have been assigned.</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                  <input type="checkbox" v-model="confirmChecks.notify" class="w-4 h-4 rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed]" />
                  <span class="text-[12px] text-slate-700">I understand publishing will notify all students and instructors.</span>
                </label>
              </div>

              <!-- Error message -->
              <p v-if="submitError" class="text-[12px] text-rose-600 font-medium mb-3 bg-rose-50 border border-rose-100 px-3 py-2 rounded-lg">{{ submitError }}</p>

              <button
                @click="submitSchedule"
                :disabled="!allConfirmed || isSubmitting"
                :class="[
                  'w-full py-3 min-h-[44px] rounded-xl font-bold text-[13px] transition-all flex items-center justify-center',
                  allConfirmed && !isSubmitting
                    ? 'bg-[#5138ed] text-white hover:bg-indigo-600 shadow-sm cursor-pointer'
                    : 'bg-indigo-50 text-indigo-300 cursor-not-allowed'
                ]"
              >
                <span v-if="isSubmitting" class="flex items-center justify-center gap-2">
                  <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                  Publishing Schedule...
                </span>
                <span v-else>Publish Schedule</span>
              </button>
            </div>

          </div>

          <!-- Bottom Action Bar -->
          <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pb-6 mt-6 border-t border-slate-100 pt-6">
            <button @click="currentView = 'schedule'" class="w-full sm:w-auto min-h-[44px] flex items-center justify-center gap-2 px-6 py-2.5 border border-slate-200 text-slate-700 rounded-xl text-[13px] font-bold hover:bg-slate-50 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
              Back to Form
            </button>
            <button
              @click="submitSchedule"
              :disabled="!allConfirmed || isSubmitting"
              :class="[
                'w-full sm:w-auto min-h-[44px] flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-[13px] font-bold transition-all',
                allConfirmed && !isSubmitting
                  ? 'bg-[#5138ed] text-white hover:bg-indigo-600 shadow-sm cursor-pointer'
                  : 'bg-indigo-100 text-indigo-300 cursor-not-allowed'
              ]"
            >
              <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
              {{ isSubmitting ? 'Publishing...' : 'Publish Schedule' }}
            </button>
          </div>

        </div><!-- end Left: Review Forms -->
        
        <!-- ── Right: Schedule Overview sidebar ── -->
        <div class="w-full xl:w-64 shrink-0 space-y-4">
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-5">
            <h3 class="text-[14px] font-bold text-slate-800 mb-6">Schedule Overview</h3>
            <div class="space-y-5">
              <!-- Exam Type -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                  <span class="text-[12px] text-slate-600 font-medium">Exam Type</span>
                </div>
                <span class="text-[11px] font-bold text-[#5138ed] bg-indigo-50 px-2 py-0.5 rounded-lg uppercase">{{ addForm.exam_type }}</span>
              </div>
              <!-- Year Level -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
                  <span class="text-[12px] text-slate-600 font-medium">Year Level</span>
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ addForm.year_level }}</span>
              </div>
              <!-- Academic Year -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                  <span class="text-[12px] text-slate-600 font-medium">Academic Year</span>
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ addForm.academic_year }}</span>
              </div>
              <!-- Semester -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                  <span class="text-[12px] text-slate-600 font-medium">Semester</span>
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ addForm.semester }}</span>
              </div>
              
              <!-- Divider -->
              <div class="border-t border-slate-100 pt-2"></div>
              
              <!-- Courses -->
              <div class="flex items-center justify-between mt-2">
                <div class="flex items-center gap-2.5">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                  <span class="text-[12px] text-slate-600 font-medium">Courses</span>
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ addForm.courses.length }}</span>
              </div>
              
              <!-- Duration -->
              <div class="space-y-1 mt-2">
                <span class="text-[11px] font-medium text-slate-400 block">Schedule Period</span>
                <span class="text-[12px] font-bold text-slate-700 block">{{ addForm.start_date }} - {{ addForm.end_date }}</span>
              </div>
            </div>
          </div>
        </div><!-- end Right sidebar -->

      </div><!-- end two-column body -->

    </div><!-- end REVIEW & CONFIRM VIEW -->

    <!-- ══════════════════════════════════════════════════
         ADD COURSE MODAL
    ══════════════════════════════════════════════════ -->
    <div v-if="showAddCourseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showAddCourseModal = false"></div>
      
      <!-- Modal Content -->
      <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-[95vw] sm:max-w-[560px] overflow-hidden flex flex-col max-h-[92vh]">
        
        <!-- Header -->
        <div class="px-5 sm:px-6 py-4 sm:py-5 border-b border-slate-100 flex items-start justify-between">
          <div>
            <h3 class="text-base sm:text-[18px] font-bold text-slate-800">Add Course to Schedule</h3>
            <p class="text-[12px] sm:text-[13px] text-slate-500 mt-0.5">Search and add course details to the examination schedule.</p>
          </div>
          <button @click="showAddCourseModal = false" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Body -->
        <div class="p-4 sm:p-6 overflow-y-auto space-y-4">
          <!-- Course Name & Code -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-[12px] font-bold text-slate-700">Course Name <span class="text-rose-500">*</span></label>
              <div class="relative">
                <select
                  v-model="selectedCourseId"
                  :class="[
                    'w-full appearance-none px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors cursor-pointer',
                    courseModalErrors.name ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                  ]"
                >
                  <option value="" disabled>Select a course</option>
                  <option v-for="course in filteredCourses" :key="course.id" :value="course.id">
                    {{ course.title }} ({{ course.code }})
                  </option>
                </select>
                <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </div>
              <p v-if="courseModalErrors.name" class="text-[11px] text-rose-500 font-medium">{{ courseModalErrors.name }}</p>
            </div>

            <div class="space-y-1.5">
              <label class="text-[12px] font-bold text-slate-700">Course Code <span class="text-rose-500">*</span></label>
              <input
                type="text"
                v-model="newCourse.code"
                @input="courseModalErrors.code = ''"
                placeholder="e.g. CS-301"
                :class="[
                  'w-full px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors placeholder:text-slate-400 font-medium',
                  courseModalErrors.code ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                ]"
              />
              <p v-if="courseModalErrors.code" class="text-[11px] text-rose-500 font-medium">{{ courseModalErrors.code }}</p>
            </div>
          </div>

          <!-- Exam Date & Time -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-[12px] font-bold text-slate-700">Exam Date <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input
                  type="date"
                  v-model="newCourse.date"
                  @change="courseModalErrors.date = ''"
                  :class="[
                    'w-full px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors',
                    courseModalErrors.date ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                  ]"
                />
              </div>
              <p v-if="courseModalErrors.date" class="text-[11px] text-rose-500 font-medium">{{ courseModalErrors.date }}</p>
            </div>

            <div class="space-y-1.5">
              <label class="text-[12px] font-bold text-slate-700">Time <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input
                  type="text"
                  v-model="newCourse.time"
                  @input="courseModalErrors.time = ''"
                  placeholder="e.g. 09:00 AM - 11:00 AM"
                  :class="[
                    'w-full px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors placeholder:text-slate-400',
                    courseModalErrors.time ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                  ]"
                />
              </div>
              <p v-if="courseModalErrors.time" class="text-[11px] text-rose-500 font-medium">{{ courseModalErrors.time }}</p>
            </div>
          </div>

          <!-- Invigilator & Room -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-[12px] font-bold text-slate-700">Invigilator <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input
                  type="text"
                  v-model="newCourse.inv"
                  @input="courseModalErrors.inv = ''"
                  list="instructors-list"
                  placeholder="e.g. Dr. Abebe Kebede"
                  :class="[
                    'w-full px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors placeholder:text-slate-400',
                    courseModalErrors.inv ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                  ]"
                />
                <datalist id="instructors-list">
                  <option v-for="inst in availableInstructors" :key="inst.id" :value="inst.name">{{ inst.name }} ({{ inst.email }})</option>
                </datalist>
              </div>
              <p v-if="courseModalErrors.inv" class="text-[11px] text-rose-500 font-medium">{{ courseModalErrors.inv }}</p>
            </div>

            <div class="space-y-1.5">
              <label class="text-[12px] font-bold text-slate-700">Room <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input
                  type="text"
                  v-model="newCourse.room"
                  @input="courseModalErrors.room = ''"
                  placeholder="e.g. Room 101 / LH-02"
                  :class="[
                    'w-full px-3 py-2.5 min-h-[44px] bg-white border rounded-lg text-[13px] text-slate-700 focus:outline-none transition-colors placeholder:text-slate-400',
                    courseModalErrors.room ? 'border-rose-400 focus:border-rose-500 bg-rose-50/20' : 'border-slate-200 focus:border-indigo-500'
                  ]"
                />
              </div>
              <p v-if="courseModalErrors.room" class="text-[11px] text-rose-500 font-medium">{{ courseModalErrors.room }}</p>
            </div>
          </div>

          <!-- Notes -->
          <div class="space-y-1.5">
            <label class="text-[12px] font-bold text-slate-700">Notes <span class="text-slate-400 font-normal">(optional)</span></label>
            <div class="relative">
              <textarea v-model="newCourse.notes" rows="3" placeholder="Add any additional notes for this examination session..." class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] text-slate-700 focus:outline-none focus:border-indigo-500 transition-colors placeholder:text-slate-400 resize-none"></textarea>
              <span class="absolute bottom-2 right-3 text-[10px] font-medium text-slate-400">{{ (newCourse.notes || '').length }} / 200</span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 sm:px-6 py-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-slate-50/50">
          <button @click="showAddCourseModal = false" class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold text-[13px] rounded-xl hover:bg-slate-100 transition-colors flex items-center justify-center">
            Cancel
          </button>
          <button @click="addCourse" class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 bg-[#5138ed] text-white font-bold text-[13px] rounded-xl hover:bg-indigo-600 transition-colors shadow-sm flex items-center justify-center">
            Add Course
          </button>
        </div>

      </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         SUCCESS POPUP MODAL
    ══════════════════════════════════════════════════ -->
    <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-[95vw] sm:max-w-md p-5 sm:p-6 text-center space-y-4">
        <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto border-4 border-emerald-100">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <h3 class="text-base sm:text-[18px] font-bold text-slate-800">Examination Schedule Published!</h3>
        <p class="text-[13px] text-slate-500 leading-relaxed">{{ successMessage }}</p>
        <div class="pt-2">
          <button @click="closeSuccessModal" class="w-full py-2.5 min-h-[44px] bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl transition-all shadow-sm">
            View Schedules
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
