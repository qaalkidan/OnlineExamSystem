<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../../modules/auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()

// ── View States ─────────────────────────────────────────────────────────────
const activeMainView = ref<'calendar' | 'table'>('calendar')
const calendarSubView = ref<'month' | 'week' | 'agenda'>('month')

// ── Loading & Error States ──────────────────────────────────────────────────
const isLoading = ref(true)
const isRefreshing = ref(false)
const errorMessage = ref<string | null>(null)

// ── Real Data Collections ───────────────────────────────────────────────────
const exams = ref<any[]>([])
const academicEvents = ref<any[]>([])
const availableCourses = ref<any[]>([])
const availableInstructors = ref<any[]>([])
const availableSemesters = ref<string[]>(['First Semester', 'Second Semester', 'Summer Term'])

// ── Real KPI Stats (Zero Fake Mock Trends) ───────────────────────────────────
const stats = ref({
  total: 0,
  scheduled: 0,
  upcoming: 0,
  conflicts: 0,
  completed: 0,
})

// ── Active Academic Period (From Backend / Store) ───────────────────────────
const academicYear = computed(() => {
  const yr = settingsStore.academicYear
  if (!yr) return '2028'
  if (/^\d{4}$/.test(yr)) return `${yr}`
  return yr
})

const currentSemester = computed(() => settingsStore.semester || 'Second Semester')

const departmentInfo = computed(() => {
  const dept = authStore.user?.department
  return {
    name: dept?.name || 'Computer Science',
    code: dept?.code || 'CS',
    college: dept?.college || 'College of Informatics'
  }
})

// ── Search & Filter State ───────────────────────────────────────────────────
const search = ref('')
const semesterFilter = ref('all')
const courseFilter = ref('all')
const statusFilter = ref('all')
const activeFilterCount = computed(() => {
  let count = 0
  if (search.value.trim()) count++
  if (semesterFilter.value !== 'all') count++
  if (courseFilter.value !== 'all') count++
  if (statusFilter.value !== 'all') count++
  return count
})

const resetFilters = () => {
  search.value = ''
  semesterFilter.value = 'all'
  courseFilter.value = 'all'
  statusFilter.value = 'all'
  currentPage.value = 1
}

// ── Pagination (Table View) ─────────────────────────────────────────────────
const currentPage = ref(1)
const perPage = ref(10)

// ── Calendar Navigation ─────────────────────────────────────────────────────
const currentDate = new Date()
const calendarYear = ref(currentDate.getFullYear())
const calendarMonth = ref(currentDate.getMonth() + 1) // 1-indexed

const calendarTitle = computed(() => {
  const d = new Date(calendarYear.value, calendarMonth.value - 1, 1)
  return d.toLocaleString('en-US', { month: 'long', year: 'numeric' })
})

const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function prevCalendarPeriod() {
  if (calendarSubView.value === 'month') {
    if (calendarMonth.value === 1) {
      calendarMonth.value = 12
      calendarYear.value--
    } else {
      calendarMonth.value--
    }
  } else if (calendarSubView.value === 'week') {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() - 7)
    weekStart.value = d
  }
}

function nextCalendarPeriod() {
  if (calendarSubView.value === 'month') {
    if (calendarMonth.value === 12) {
      calendarMonth.value = 1
      calendarYear.value++
    } else {
      calendarMonth.value++
    }
  } else if (calendarSubView.value === 'week') {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + 7)
    weekStart.value = d
  }
}

function goToday() {
  const now = new Date()
  calendarYear.value = now.getFullYear()
  calendarMonth.value = now.getMonth() + 1
  
  const day = now.getDay()
  const diff = day === 0 ? -6 : 1 - day
  const mon = new Date(now)
  mon.setDate(now.getDate() + diff)
  mon.setHours(0, 0, 0, 0)
  weekStart.value = mon
}

// ── Week View Setup ─────────────────────────────────────────────────────────
const weekStart = ref((() => {
  const d = new Date()
  const day = d.getDay()
  const diff = day === 0 ? -6 : 1 - day // Start Monday
  const mon = new Date(d)
  mon.setDate(d.getDate() + diff)
  mon.setHours(0, 0, 0, 0)
  return mon
})())

const weekDays = computed(() => {
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart.value)
    d.setDate(weekStart.value.getDate() + i)
    const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    return {
      date: d,
      dateStr,
      label: d.toLocaleString('en-US', { weekday: 'short' }),
      dayNum: d.getDate(),
      isToday: isDateToday(dateStr)
    }
  })
})

const weekTitle = computed(() => {
  const start = weekDays.value[0].date
  const end = weekDays.value[6].date
  if (start.getMonth() === end.getMonth()) {
    return `${start.toLocaleString('en-US', { month: 'long' })} ${start.getDate()}–${end.getDate()}, ${start.getFullYear()}`
  }
  return `${start.toLocaleString('en-US', { month: 'short' })} ${start.getDate()} – ${end.toLocaleString('en-US', { month: 'short' })} ${end.getDate()}, ${start.getFullYear()}`
})

function isDateToday(dateStr: string): boolean {
  const now = new Date()
  const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
  return dateStr === todayStr
}

// ── Unified Calendar Events Mapping ─────────────────────────────────────────
interface UnifiedCalendarItem {
  id: string
  sourceType: 'exam' | 'academic_event'
  title: string
  code?: string
  courseCode?: string
  courseName?: string
  date: string // YYYY-MM-DD
  endDate?: string
  time?: string
  endTime?: string
  room?: string
  instructor?: string
  status: string
  hasConflict?: boolean
  conflictReason?: string
  color: string
  category?: string
  rawItem: any
}

const allCalendarItems = computed<UnifiedCalendarItem[]>(() => {
  const list: UnifiedCalendarItem[] = []

  // Add Department Exams
  exams.value.forEach(exam => {
    if (!exam.date || exam.date === 'Unscheduled') return
    const d = exam.scheduled_at ? exam.scheduled_at.split('T')[0] : null
    if (!d) return

    list.push({
      id: `exam-${exam.id}`,
      sourceType: 'exam',
      title: exam.title,
      code: exam.code,
      courseCode: exam.course_code || exam.courseCode,
      courseName: exam.course_name || exam.courseName || exam.course,
      date: d,
      time: exam.time,
      endTime: exam.endTime,
      room: exam.room || 'Room 101',
      instructor: exam.instructor_name || exam.instructor?.name || 'Unassigned',
      status: exam.status,
      hasConflict: exam.has_conflict,
      conflictReason: exam.conflict_reason,
      color: exam.has_conflict ? '#ef4444' : '#5138ed',
      rawItem: exam
    })
  })

  // Add General University Academic Events
  academicEvents.value.forEach(event => {
    if (!event.start_date) return
    list.push({
      id: `event-${event.id}`,
      sourceType: 'academic_event',
      title: event.title,
      date: event.start_date,
      endDate: event.end_date,
      time: event.all_day ? 'All Day' : (event.start_time || 'All Day'),
      endTime: event.end_time,
      status: event.status || 'Active',
      color: event.color || event.category_color || '#3b82f6',
      category: event.category || event.category_name || 'Academic Event',
      rawItem: event
    })
  })

  return list
})

// Filtered items based on active toolbar filters
const filteredCalendarItems = computed(() => {
  return allCalendarItems.value.filter(item => {
    if (search.value.trim()) {
      const q = search.value.trim().toLowerCase()
      const match =
        item.title.toLowerCase().includes(q) ||
        (item.code && item.code.toLowerCase().includes(q)) ||
        (item.courseCode && item.courseCode.toLowerCase().includes(q)) ||
        (item.courseName && item.courseName.toLowerCase().includes(q)) ||
        (item.room && item.room.toLowerCase().includes(q))
      if (!match) return false
    }

    if (item.sourceType === 'exam') {
      const exam = item.rawItem
      if (semesterFilter.value !== 'all' && exam.semester !== semesterFilter.value) {
        return false
      }
      if (courseFilter.value !== 'all') {
        const cCode = exam.course_code || exam.courseCode
        if (cCode !== courseFilter.value) return false
      }
      if (statusFilter.value !== 'all') {
        if (exam.status.toLowerCase() !== statusFilter.value.toLowerCase()) return false
      }
    }

    return true
  })
})

// Map of items grouped by date string (YYYY-MM-DD)
const itemsByDate = computed(() => {
  const map: Record<string, UnifiedCalendarItem[]> = {}
  filteredCalendarItems.value.forEach(item => {
    if (!map[item.date]) {
      map[item.date] = []
    }
    map[item.date].push(item)
  })
  return map
})

// ── Calendar Cells (Month View) ─────────────────────────────────────────────
const calendarCells = computed(() => {
  const y = calendarYear.value
  const m = calendarMonth.value
  const firstDay = new Date(y, m - 1, 1).getDay() // 0=Sun
  const daysInMonth = new Date(y, m, 0).getDate()
  const daysInPrev = new Date(y, m - 1, 0).getDate()
  const cells: { day: number; month: 'prev' | 'current' | 'next'; dateStr: string; isToday: boolean; items: UnifiedCalendarItem[] }[] = []

  // Prev month filler
  for (let i = firstDay - 1; i >= 0; i--) {
    const d = daysInPrev - i
    const pm = m === 1 ? 12 : m - 1
    const py = m === 1 ? y - 1 : y
    const dateStr = `${py}-${String(pm).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    cells.push({
      day: d,
      month: 'prev',
      dateStr,
      isToday: isDateToday(dateStr),
      items: itemsByDate.value[dateStr] || []
    })
  }

  // Current month
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    cells.push({
      day: d,
      month: 'current',
      dateStr,
      isToday: isDateToday(dateStr),
      items: itemsByDate.value[dateStr] || []
    })
  }

  // Next month filler
  const remaining = (cells.length > 35 ? 42 : 35) - cells.length
  for (let d = 1; d <= remaining; d++) {
    const nm = m === 12 ? 1 : m + 1
    const ny = m === 12 ? y + 1 : y
    const dateStr = `${ny}-${String(nm).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    cells.push({
      day: d,
      month: 'next',
      dateStr,
      isToday: isDateToday(dateStr),
      items: itemsByDate.value[dateStr] || []
    })
  }

  return cells
})

// ── Filtered & Paginated Exams for Table View ───────────────────────────────
const filteredExams = computed(() => {
  return exams.value.filter(exam => {
    if (search.value.trim()) {
      const q = search.value.trim().toLowerCase()
      const match =
        (exam.title && exam.title.toLowerCase().includes(q)) ||
        (exam.code && exam.code.toLowerCase().includes(q)) ||
        (exam.course && exam.course.toLowerCase().includes(q)) ||
        (exam.courseName && exam.courseName.toLowerCase().includes(q)) ||
        (exam.course_code && exam.course_code.toLowerCase().includes(q)) ||
        (exam.room && exam.room.toLowerCase().includes(q))
      if (!match) return false
    }

    if (semesterFilter.value !== 'all' && exam.semester !== semesterFilter.value) {
      return false
    }

    if (courseFilter.value !== 'all') {
      const cCode = exam.course_code || exam.courseCode || exam.code
      if (cCode !== courseFilter.value) return false
    }

    if (statusFilter.value !== 'all') {
      if ((exam.status || '').toLowerCase() !== statusFilter.value.toLowerCase()) return false
    }

    return true
  })
})

const totalTableItems = computed(() => filteredExams.value.length)
const totalTablePages = computed(() => Math.max(1, Math.ceil(totalTableItems.value / perPage.value)))
const paginatedExams = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredExams.value.slice(start, start + perPage.value)
})

watch([search, semesterFilter, courseFilter, statusFilter], () => {
  currentPage.value = 1
})

// ── Agenda / Timeline Sorted View ───────────────────────────────────────────
const agendaGroups = computed(() => {
  const map: Record<string, UnifiedCalendarItem[]> = {}
  filteredCalendarItems.value.forEach(item => {
    if (!map[item.date]) map[item.date] = []
    map[item.date].push(item)
  })

  const sortedDates = Object.keys(map).sort()
  return sortedDates.map(dateStr => {
    const d = new Date(dateStr + 'T00:00:00')
    const formatted = d.toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    })
    return {
      dateStr,
      formatted,
      isToday: isDateToday(dateStr),
      items: map[dateStr]
    }
  })
})

// ── Data Fetching ───────────────────────────────────────────────────────────
const fetchCalendarData = async () => {
  isLoading.value = true
  errorMessage.value = null
  try {
    const res = await apiClient.get('/dept-head/calendar-events')
    const d = res.data

    exams.value = d.exams || []
    academicEvents.value = d.academic_events || []
    availableCourses.value = d.courses || []
    availableInstructors.value = d.instructors || []

    if (d.stats) {
      stats.value = {
        total: d.stats.total || 0,
        scheduled: d.stats.scheduled || 0,
        upcoming: d.stats.upcoming || 0,
        conflicts: d.stats.conflicts || 0,
        completed: d.stats.completed || 0,
      }
    }
  } catch (err: any) {
    console.error('Failed to load academic calendar data:', err)
    errorMessage.value = err?.response?.data?.message || 'Unable to load examination schedule and academic dates.'
  } finally {
    isLoading.value = false
    isRefreshing.value = false
  }
}

const refreshData = async () => {
  isRefreshing.value = true
  await fetchCalendarData()
}

onMounted(async () => {
  await fetchCalendarData()
  if (!settingsStore.academicYear) {
    await settingsStore.fetchSettings()
  }
})

// ── Modals State ────────────────────────────────────────────────────────────
const showScheduleModal = ref(false)
const showDetailsModal = ref(false)
const showRescheduleModal = ref(false)
const showCancelModal = ref(false)
const showExportDropdown = ref(false)
const selectedItem = ref<UnifiedCalendarItem | null>(null)
const selectedExamForAction = ref<any | null>(null)

// Toast Feedback State
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'info'
})
let toastTimer: any = null
function showToast(message: string, type: 'success' | 'error' | 'info' = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// ── Schedule Exam Form & Real-Time Conflict Checking ────────────────────────
const scheduleForm = ref({
  title: '',
  course_code: '',
  instructor_id: '',
  exam_type: 'Midterm',
  scheduled_date: '',
  scheduled_time: '09:00',
  duration_minutes: 60,
  room: 'Room 101',
  total_marks: 100,
  description: ''
})

const scheduleFormErrors = ref<Record<string, string>>({})
const isSubmittingSchedule = ref(false)
const detectedConflictWarning = ref<string | null>(null)
const isCheckingConflict = ref(false)

// Pre-flight Conflict Detection
const runConflictCheck = async () => {
  if (!scheduleForm.value.course_code || !scheduleForm.value.scheduled_date || !scheduleForm.value.scheduled_time) {
    detectedConflictWarning.value = null
    return
  }

  isCheckingConflict.value = true
  try {
    const fullDateTime = `${scheduleForm.value.scheduled_date} ${scheduleForm.value.scheduled_time}:00`
    const res = await apiClient.post('/dept-head/exams/check-conflict', {
      course_code: scheduleForm.value.course_code,
      scheduled_at: fullDateTime,
      duration_minutes: scheduleForm.value.duration_minutes,
      room: scheduleForm.value.room,
      exclude_id: selectedExamForAction.value?.id || null
    })

    if (res.data?.has_conflict) {
      detectedConflictWarning.value = res.data.message
    } else {
      detectedConflictWarning.value = null
    }
  } catch (err) {
    detectedConflictWarning.value = null
  } finally {
    isCheckingConflict.value = false
  }
}

watch(
  () => [
    scheduleForm.value.course_code,
    scheduleForm.value.scheduled_date,
    scheduleForm.value.scheduled_time,
    scheduleForm.value.duration_minutes,
    scheduleForm.value.room
  ],
  () => {
    runConflictCheck()
  }
)

const openScheduleModal = (dateStr?: string) => {
  scheduleForm.value = {
    title: '',
    course_code: availableCourses.value[0]?.code || '',
    instructor_id: availableCourses.value[0]?.instructor_id || '',
    exam_type: 'Midterm',
    scheduled_date: dateStr || new Date().toISOString().split('T')[0],
    scheduled_time: '09:00',
    duration_minutes: 60,
    room: 'Room 101',
    total_marks: 100,
    description: ''
  }
  scheduleFormErrors.value = {}
  detectedConflictWarning.value = null
  showScheduleModal.value = true
}

const submitScheduleExam = async () => {
  scheduleFormErrors.value = {}
  if (!scheduleForm.value.title.trim()) {
    scheduleFormErrors.value.title = 'Exam title is required'
    return
  }
  if (!scheduleForm.value.course_code) {
    scheduleFormErrors.value.course_code = 'Please select a course'
    return
  }
  if (!scheduleForm.value.scheduled_date) {
    scheduleFormErrors.value.scheduled_date = 'Exam date is required'
    return
  }
  if (!scheduleForm.value.scheduled_time) {
    scheduleFormErrors.value.scheduled_time = 'Start time is required'
    return
  }

  isSubmittingSchedule.value = true
  try {
    const fullDateTime = `${scheduleForm.value.scheduled_date} ${scheduleForm.value.scheduled_time}:00`
    await apiClient.post('/dept-head/exams', {
      title: scheduleForm.value.title.trim(),
      course_code: scheduleForm.value.course_code,
      instructor_id: scheduleForm.value.instructor_id || null,
      exam_type: scheduleForm.value.exam_type,
      scheduled_at: fullDateTime,
      duration_minutes: scheduleForm.value.duration_minutes,
      room: scheduleForm.value.room,
      total_marks: scheduleForm.value.total_marks,
      description: scheduleForm.value.description
    })

    showToast('Examination successfully scheduled and added to the academic calendar!', 'success')
    showScheduleModal.value = false
    await fetchCalendarData()
  } catch (err: any) {
    const msg = err?.response?.data?.message || 'Failed to schedule exam. Please check fields.'
    showToast(msg, 'error')
  } finally {
    isSubmittingSchedule.value = false
  }
}

// ── Reschedule Exam Workflow ────────────────────────────────────────────────
const rescheduleForm = ref({
  date: '',
  time: '09:00',
  duration_minutes: 60,
  room: 'Room 101'
})
const isSubmittingReschedule = ref(false)
const rescheduleConflictWarning = ref<string | null>(null)

const openRescheduleModal = (exam: any) => {
  selectedExamForAction.value = exam
  const d = exam.scheduled_at ? exam.scheduled_at.split('T')[0] : ''
  const t = exam.scheduled_at ? exam.scheduled_at.split('T')[1].slice(0, 5) : '09:00'
  rescheduleForm.value = {
    date: d || new Date().toISOString().split('T')[0],
    time: t || '09:00',
    duration_minutes: exam.duration_minutes || 60,
    room: exam.room || 'Room 101'
  }
  rescheduleConflictWarning.value = null
  showRescheduleModal.value = true
}

const checkRescheduleConflict = async () => {
  if (!selectedExamForAction.value || !rescheduleForm.value.date || !rescheduleForm.value.time) return
  try {
    const fullDateTime = `${rescheduleForm.value.date} ${rescheduleForm.value.time}:00`
    const res = await apiClient.post('/dept-head/exams/check-conflict', {
      course_code: selectedExamForAction.value.course_code || selectedExamForAction.value.courseCode,
      scheduled_at: fullDateTime,
      duration_minutes: rescheduleForm.value.duration_minutes,
      room: rescheduleForm.value.room,
      exclude_id: selectedExamForAction.value.id
    })
    rescheduleConflictWarning.value = res.data?.has_conflict ? res.data.message : null
  } catch {
    rescheduleConflictWarning.value = null
  }
}

watch(
  () => [rescheduleForm.value.date, rescheduleForm.value.time, rescheduleForm.value.room, rescheduleForm.value.duration_minutes],
  () => checkRescheduleConflict()
)

const submitReschedule = async () => {
  if (!selectedExamForAction.value) return
  isSubmittingReschedule.value = true
  try {
    const fullDateTime = `${rescheduleForm.value.date} ${rescheduleForm.value.time}:00`
    await apiClient.put(`/dept-head/exams/${selectedExamForAction.value.id}`, {
      scheduled_at: fullDateTime,
      duration_minutes: rescheduleForm.value.duration_minutes,
      room: rescheduleForm.value.room
    })

    showToast(`Exam "${selectedExamForAction.value.title}" successfully rescheduled!`, 'success')
    showRescheduleModal.value = false
    showDetailsModal.value = false
    await fetchCalendarData()
  } catch (err: any) {
    const msg = err?.response?.data?.message || 'Failed to reschedule examination.'
    showToast(msg, 'error')
  } finally {
    isSubmittingReschedule.value = false
  }
}

// ── Cancel Exam Workflow ────────────────────────────────────────────────────
const cancelReason = ref('')
const isSubmittingCancel = ref(false)

const openCancelModal = (exam: any) => {
  selectedExamForAction.value = exam
  cancelReason.value = ''
  showCancelModal.value = true
}

const submitCancelExam = async () => {
  if (!selectedExamForAction.value) return
  isSubmittingCancel.value = true
  try {
    await apiClient.post(`/dept-head/exams/${selectedExamForAction.value.id}/cancel`, {
      reason: cancelReason.value.trim() || 'Cancelled by Department Head'
    })
    showToast(`Exam "${selectedExamForAction.value.title}" has been cancelled.`, 'success')
    showCancelModal.value = false
    showDetailsModal.value = false
    await fetchCalendarData()
  } catch (err: any) {
    showToast(err?.response?.data?.message || 'Failed to cancel examination.', 'error')
  } finally {
    isSubmittingCancel.value = false
  }
}

// ── Item Details Modal ──────────────────────────────────────────────────────
const openItemDetails = (item: UnifiedCalendarItem) => {
  selectedItem.value = item
  selectedExamForAction.value = item.sourceType === 'exam' ? item.rawItem : null
  showDetailsModal.value = true
}

const openExamDetailsFromTable = (exam: any) => {
  const d = exam.scheduled_at ? exam.scheduled_at.split('T')[0] : (exam.date || '')
  selectedItem.value = {
    id: `exam-${exam.id}`,
    sourceType: 'exam',
    title: exam.title,
    code: exam.code,
    courseCode: exam.course_code || exam.courseCode,
    courseName: exam.course_name || exam.courseName || exam.course,
    date: d,
    time: exam.time,
    endTime: exam.endTime,
    room: exam.room || 'Room 101',
    instructor: exam.instructor_name || exam.instructor?.name || 'Unassigned',
    status: exam.status,
    hasConflict: exam.has_conflict,
    conflictReason: exam.conflict_reason,
    color: exam.has_conflict ? '#ef4444' : '#5138ed',
    rawItem: exam
  }
  selectedExamForAction.value = exam
  showDetailsModal.value = true
}

// ── Export and Print Functions ──────────────────────────────────────────────
const isExporting = ref(false)
const handleExport = async (format: 'xlsx' | 'csv' | 'pdf') => {
  isExporting.value = true
  showExportDropdown.value = false
  try {
    const response = await apiClient.get('/dept-head/exams/export', {
      params: {
        format,
        search: search.value || undefined,
        semester: semesterFilter.value !== 'all' ? semesterFilter.value : undefined,
        course_code: courseFilter.value !== 'all' ? courseFilter.value : undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
      },
      responseType: 'blob'
    })

    const blob = new Blob([response.data], {
      type: format === 'pdf' ? 'application/pdf' : format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv'
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `wollo_cs_exam_schedule_${new Date().toISOString().slice(0, 10)}.${format}`
    link.click()
    window.URL.revokeObjectURL(url)
    showToast(`Schedule exported as ${format.toUpperCase()} successfully!`, 'success')
  } catch (error) {
    console.error('Export failed:', error)
    showToast('Failed to export examination schedule.', 'error')
  } finally {
    isExporting.value = false
  }
}

const printSchedule = () => {
  showExportDropdown.value = false
  window.print()
}

// ── Status Badges Styling ───────────────────────────────────────────────────
const getStatusBadgeClass = (status: string) => {
  const s = (status || '').toLowerCase()
  if (s === 'scheduled' || s === 'published') return 'text-emerald-700 bg-emerald-50 border border-emerald-200'
  if (s === 'active') return 'text-indigo-700 bg-indigo-50 border border-indigo-200'
  if (s === 'completed') return 'text-sky-700 bg-sky-50 border border-sky-200'
  if (s === 'cancelled') return 'text-rose-700 bg-rose-50 border border-rose-200'
  if (s === 'draft') return 'text-slate-600 bg-slate-100 border border-slate-200'
  return 'text-amber-700 bg-amber-50 border border-amber-200'
}
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-12 print:p-0 print:m-0 print:max-w-none">

    <!-- ══════════════════════════════════════════════════
         PAGE HEADER
    ══════════════════════════════════════════════════ -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 print:hidden">
      <div>
        <div class="flex items-center gap-2.5 mb-1">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-indigo-100 text-[#5138ed]">
            {{ departmentInfo.code }} Department
          </span>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700">
            {{ academicYear }} {{ currentSemester }}
          </span>
        </div>
        <h1 class="text-2xl sm:text-[26px] font-extrabold text-slate-900 tracking-tight leading-snug">
          Academic Calendar &amp; Exam Scheduling Center
        </h1>
        <p class="text-[13px] text-slate-500 font-medium">
          Manage, monitor examination schedules, and track important university academic dates for {{ departmentInfo.name }}.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center flex-wrap gap-2.5 shrink-0">
        <!-- View Toggle Buttons -->
        <div class="inline-flex p-1 bg-slate-100/90 rounded-xl border border-slate-200/80 shadow-xs">
          <button
            @click="activeMainView = 'calendar'"
            :class="[
              'flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-[13px] font-bold transition-all cursor-pointer',
              activeMainView === 'calendar' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900'
            ]"
            title="Interactive Visual Calendar"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            Calendar View
          </button>
          <button
            @click="activeMainView = 'table'"
            :class="[
              'flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-[13px] font-bold transition-all cursor-pointer',
              activeMainView === 'table' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900'
            ]"
            title="Tabular Exam Schedule"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            Schedule Table
          </button>
        </div>

        <!-- Export Dropdown -->
        <div class="relative">
          <button
            @click="showExportDropdown = !showExportDropdown"
            :disabled="isExporting"
            class="min-h-[42px] px-3.5 py-2 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-xl text-[13px] font-bold flex items-center gap-2 transition-all shadow-2xs hover:bg-slate-50 disabled:opacity-50 cursor-pointer"
            title="Export examination schedule"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            <span>Export</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showExportDropdown"
            class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-40"
          >
            <button
              @click="handleExport('pdf')"
              class="w-full text-left px-4 py-2 text-[13px] text-slate-700 hover:bg-indigo-50 hover:text-[#5138ed] flex items-center gap-2 font-medium cursor-pointer"
            >
              <span class="w-2 h-2 rounded-full bg-rose-500"></span>
              Export as PDF
            </button>
            <button
              @click="handleExport('xlsx')"
              class="w-full text-left px-4 py-2 text-[13px] text-slate-700 hover:bg-indigo-50 hover:text-[#5138ed] flex items-center gap-2 font-medium cursor-pointer"
            >
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              Export as Excel (.xlsx)
            </button>
            <button
              @click="handleExport('csv')"
              class="w-full text-left px-4 py-2 text-[13px] text-slate-700 hover:bg-indigo-50 hover:text-[#5138ed] flex items-center gap-2 font-medium cursor-pointer"
            >
              <span class="w-2 h-2 rounded-full bg-sky-500"></span>
              Export as CSV (.csv)
            </button>
            <div class="h-px bg-slate-100 my-1"></div>
            <button
              @click="printSchedule"
              class="w-full text-left px-4 py-2 text-[13px] text-slate-700 hover:bg-slate-50 flex items-center gap-2 font-medium cursor-pointer"
            >
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
              Print Schedule
            </button>
          </div>
        </div>

        <!-- Refresh Button -->
        <button
          @click="refreshData"
          :disabled="isRefreshing"
          class="min-h-[42px] w-[42px] bg-white border border-slate-200 hover:border-slate-300 text-slate-600 rounded-xl flex items-center justify-center transition-all hover:bg-slate-50 disabled:opacity-50 cursor-pointer shadow-2xs"
          title="Refresh calendar & exams"
        >
          <svg :class="['w-4 h-4', isRefreshing && 'animate-spin text-[#5138ed]']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        </button>

        <!-- Schedule Exam Button -->
        <button
          @click="openScheduleModal()"
          class="min-h-[42px] px-5 py-2 bg-[#5138ed] hover:bg-[#432ec7] active:bg-[#3826a6] text-white rounded-xl text-[13px] font-bold flex items-center gap-2 transition-all shadow-sm hover:shadow-md cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
          Schedule Exam
        </button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         4 REAL KPI SUMMARY CARDS (ZERO FAKE MOCK TRENDS)
    ══════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 print:hidden">

      <!-- Card 1: Total Exams -->
      <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          </div>
          <div>
            <p class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Total Exams</p>
            <h3 class="text-2xl font-black text-slate-800 leading-tight mt-0.5">
              <span v-if="isLoading" class="inline-block w-8 h-6 bg-slate-100 animate-pulse rounded"></span>
              <span v-else>{{ stats.total }}</span>
            </h3>
            <p class="text-[11px] font-semibold text-slate-500 mt-0.5">Department Exams</p>
          </div>
        </div>
      </div>

      <!-- Card 2: Scheduled Exams -->
      <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          </div>
          <div>
            <p class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Scheduled Exams</p>
            <h3 class="text-2xl font-black text-slate-800 leading-tight mt-0.5">
              <span v-if="isLoading" class="inline-block w-8 h-6 bg-slate-100 animate-pulse rounded"></span>
              <span v-else>{{ stats.scheduled }}</span>
            </h3>
            <p class="text-[11px] font-semibold text-emerald-600 mt-0.5">Active &amp; Published</p>
          </div>
        </div>
      </div>

      <!-- Card 3: Upcoming Exams -->
      <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          </div>
          <div>
            <p class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Upcoming Exams</p>
            <h3 class="text-2xl font-black text-slate-800 leading-tight mt-0.5">
              <span v-if="isLoading" class="inline-block w-8 h-6 bg-slate-100 animate-pulse rounded"></span>
              <span v-else>{{ stats.upcoming }}</span>
            </h3>
            <p class="text-[11px] font-semibold text-amber-600 mt-0.5">Future examination dates</p>
          </div>
        </div>
      </div>

      <!-- Card 4: Scheduling Conflicts -->
      <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
          <div :class="[stats.conflicts > 0 ? 'bg-rose-100 text-rose-600 animate-pulse' : 'bg-rose-50 text-rose-500', 'w-12 h-12 rounded-xl flex items-center justify-center shrink-0']">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
          </div>
          <div>
            <p class="text-[12px] font-bold text-slate-500 uppercase tracking-wider">Conflicts</p>
            <h3 class="text-2xl font-black text-slate-800 leading-tight mt-0.5">
              <span v-if="isLoading" class="inline-block w-8 h-6 bg-slate-100 animate-pulse rounded"></span>
              <span v-else :class="stats.conflicts > 0 ? 'text-rose-600' : 'text-slate-800'">{{ stats.conflicts }}</span>
            </h3>
            <p :class="[stats.conflicts > 0 ? 'text-rose-600 font-bold' : 'text-slate-500', 'text-[11px] mt-0.5']">
              {{ stats.conflicts > 0 ? `${stats.conflicts} time collisions detected` : 'None detected' }}
            </p>
          </div>
        </div>
      </div>

    </div>

    <!-- ══════════════════════════════════════════════════
         SEARCH & FILTERS TOOLBAR
    ══════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs print:hidden">
      <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 sm:gap-4">
        
        <!-- Search input -->
        <div class="relative w-full lg:w-80">
          <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          <input
            v-model="search"
            type="text"
            placeholder="Search exams by title, course or code..."
            class="w-full pl-10 pr-4 py-2.5 min-h-[42px] bg-slate-50 border border-slate-200 rounded-xl text-[13px] text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition-all"
          />
          <button
            v-if="search"
            @click="search = ''"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Filter Dropdowns -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1">
          <!-- Semester -->
          <div class="relative">
            <select
              v-model="semesterFilter"
              class="w-full appearance-none px-4 py-2.5 min-h-[42px] bg-white border border-slate-200 rounded-xl text-[13px] font-medium text-slate-700 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 transition-all cursor-pointer"
            >
              <option value="all">All Semesters</option>
              <option v-for="sem in availableSemesters" :key="sem" :value="sem">{{ sem }}</option>
            </select>
            <svg class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>

          <!-- Courses -->
          <div class="relative">
            <select
              v-model="courseFilter"
              class="w-full appearance-none px-4 py-2.5 min-h-[42px] bg-white border border-slate-200 rounded-xl text-[13px] font-medium text-slate-700 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 transition-all cursor-pointer"
            >
              <option value="all">All Courses</option>
              <option v-for="c in availableCourses" :key="c.id" :value="c.code">
                {{ c.code }} - {{ c.title }}
              </option>
            </select>
            <svg class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>

          <!-- Status -->
          <div class="relative">
            <select
              v-model="statusFilter"
              class="w-full appearance-none px-4 py-2.5 min-h-[42px] bg-white border border-slate-200 rounded-xl text-[13px] font-medium text-slate-700 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 transition-all cursor-pointer"
            >
              <option value="all">All Status</option>
              <option value="scheduled">Scheduled</option>
              <option value="published">Published</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
              <option value="draft">Draft</option>
            </select>
            <svg class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>

        <!-- Filter Count & Reset Button -->
        <div class="flex items-center gap-2">
          <button
            v-if="activeFilterCount > 0"
            @click="resetFilters"
            class="min-h-[42px] px-3.5 py-2 text-[12px] font-bold text-rose-600 bg-rose-50 border border-rose-200 hover:bg-rose-100 rounded-xl transition-colors flex items-center gap-1.5 cursor-pointer shrink-0"
            title="Reset active filters"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            Reset ({{ activeFilterCount }})
          </button>
        </div>

      </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         MAIN VIEW: INTERACTIVE CALENDAR
    ══════════════════════════════════════════════════ -->
    <div v-if="activeMainView === 'calendar'" class="space-y-4 print:hidden">

      <!-- Calendar Controls & Legend Toolbar -->
      <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
        
        <!-- Navigation Buttons -->
        <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-start">
          <div class="flex items-center gap-1">
            <button
              @click="prevCalendarPeriod"
              class="w-9 h-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center text-slate-600 transition-colors cursor-pointer"
              title="Previous"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <button
              @click="goToday"
              class="px-3 py-1.5 text-[12px] font-bold text-slate-700 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors cursor-pointer"
            >
              Today
            </button>
            <button
              @click="nextCalendarPeriod"
              class="w-9 h-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center text-slate-600 transition-colors cursor-pointer"
              title="Next"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>

          <h2 class="text-base sm:text-lg font-bold text-slate-800 tracking-tight">
            {{ calendarSubView === 'month' ? calendarTitle : (calendarSubView === 'week' ? weekTitle : 'Agenda &amp; Timeline') }}
          </h2>
        </div>

        <!-- Sub-view switcher & Legend -->
        <div class="flex items-center flex-wrap gap-3 w-full md:w-auto justify-between md:justify-end">
          
          <!-- Legend -->
          <div class="hidden xl:flex items-center gap-3 text-[11px] font-semibold text-slate-500 mr-2">
            <div class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-[#5138ed]"></span>
              <span>Exam</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
              <span>Academic Event</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
              <span>Conflict</span>
            </div>
          </div>

          <!-- Sub-view switcher pills -->
          <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200/80">
            <button
              @click="calendarSubView = 'month'"
              :class="[
                'px-3 py-1 rounded-lg text-[12px] font-bold transition-all cursor-pointer',
                calendarSubView === 'month' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              Month
            </button>
            <button
              @click="calendarSubView = 'week'"
              :class="[
                'px-3 py-1 rounded-lg text-[12px] font-bold transition-all cursor-pointer',
                calendarSubView === 'week' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              Week
            </button>
            <button
              @click="calendarSubView = 'agenda'"
              :class="[
                'px-3 py-1 rounded-lg text-[12px] font-bold transition-all cursor-pointer',
                calendarSubView === 'agenda' ? 'bg-white text-[#5138ed] shadow-xs' : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              Agenda
            </button>
          </div>
        </div>

      </div>

      <!-- ── MONTH VIEW GRID ── -->
      <div v-if="calendarSubView === 'month'" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        
        <!-- Day header columns -->
        <div class="grid grid-cols-7 border-b border-slate-200/80 bg-slate-50 text-center">
          <div
            v-for="d in daysOfWeek"
            :key="d"
            class="py-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider"
          >
            {{ d }}
          </div>
        </div>

        <!-- Cells Grid -->
        <div class="grid grid-cols-7 divide-x divide-y divide-slate-100 border-b border-slate-200/80">
          <div
            v-for="(cell, idx) in calendarCells"
            :key="idx"
            :class="[
              'min-h-[110px] sm:min-h-[125px] p-1.5 sm:p-2 transition-colors relative flex flex-col',
              cell.month === 'current' ? 'bg-white' : 'bg-slate-50/60 text-slate-400',
              cell.isToday && 'bg-indigo-50/20'
            ]"
          >
            <!-- Date Number & Day indicators -->
            <div class="flex items-center justify-between mb-1">
              <span
                :class="[
                  'w-6 h-6 rounded-full flex items-center justify-center text-[12px] font-bold',
                  cell.isToday ? 'bg-[#5138ed] text-white shadow-xs' : (cell.month === 'current' ? 'text-slate-700' : 'text-slate-400')
                ]"
              >
                {{ cell.day }}
              </span>

              <!-- Quick Add Exam on cell hover -->
              <button
                @click.stop="openScheduleModal(cell.dateStr)"
                class="opacity-0 hover:opacity-100 transition-opacity p-1 text-slate-400 hover:text-[#5138ed] cursor-pointer"
                title="Schedule Exam on this date"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
              </button>
            </div>

            <!-- Items list for cell -->
            <div class="space-y-1 flex-1 overflow-hidden">
              <div
                v-for="item in cell.items.slice(0, 3)"
                :key="item.id"
                @click.stop="openItemDetails(item)"
                :class="[
                  'px-2 py-1 rounded-md text-[11px] font-semibold truncate cursor-pointer transition-transform hover:scale-[1.02] flex items-center gap-1.5',
                  item.hasConflict 
                    ? 'bg-rose-100 text-rose-800 border border-rose-300' 
                    : (item.sourceType === 'exam' ? 'bg-indigo-50 text-[#5138ed] border border-indigo-100 hover:bg-indigo-100' : 'bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100')
                ]"
                :title="`${item.title} (${item.time})`"
              >
                <span
                  class="w-1.5 h-1.5 rounded-full shrink-0"
                  :style="{ backgroundColor: item.color }"
                ></span>
                <span v-if="item.time" class="text-[10px] opacity-75 shrink-0">{{ item.time }}</span>
                <span class="truncate">{{ item.courseCode ? `${item.courseCode}: ` : '' }}{{ item.title }}</span>
              </div>

              <!-- More indicator -->
              <div
                v-if="cell.items.length > 3"
                class="text-[10px] font-bold text-slate-500 pl-1"
              >
                +{{ cell.items.length - 3 }} more
              </div>
            </div>
          </div>
        </div>

      </div><!-- End Month Grid -->

      <!-- ── WEEK VIEW ── -->
      <div v-else-if="calendarSubView === 'week'" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-7 divide-y md:divide-y-0 md:divide-x divide-slate-100">
          <div
            v-for="col in weekDays"
            :key="col.dateStr"
            :class="['p-3 min-h-[300px] flex flex-col', col.isToday && 'bg-indigo-50/20']"
          >
            <!-- Day Header -->
            <div class="text-center pb-3 border-b border-slate-100 mb-3">
              <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">{{ col.label }}</span>
              <span
                :class="[
                  'inline-flex items-center justify-center w-8 h-8 rounded-full text-[14px] font-bold mt-1',
                  col.isToday ? 'bg-[#5138ed] text-white shadow-xs' : 'text-slate-800'
                ]"
              >
                {{ col.dayNum }}
              </span>
            </div>

            <!-- Items -->
            <div class="space-y-2 flex-1">
              <div
                v-for="item in (itemsByDate[col.dateStr] || [])"
                :key="item.id"
                @click="openItemDetails(item)"
                :class="[
                  'p-2.5 rounded-xl border text-[12px] cursor-pointer transition-all hover:shadow-xs',
                  item.hasConflict 
                    ? 'bg-rose-50 border-rose-200 text-rose-900' 
                    : (item.sourceType === 'exam' ? 'bg-indigo-50/60 border-indigo-100 text-slate-800' : 'bg-blue-50/60 border-blue-100 text-slate-800')
                ]"
              >
                <div class="flex items-center justify-between gap-1 mb-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider" :style="{ color: item.color }">
                    {{ item.sourceType === 'exam' ? (item.courseCode || 'EXAM') : 'UNIVERSITY' }}
                  </span>
                  <span class="text-[10px] text-slate-500 font-medium">{{ item.time }}</span>
                </div>
                <h4 class="font-bold leading-tight">{{ item.title }}</h4>
                <div v-if="item.room" class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                  <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                  <span>{{ item.room }}</span>
                </div>
              </div>

              <div
                v-if="!(itemsByDate[col.dateStr]?.length)"
                class="h-full flex flex-col items-center justify-center text-center py-8 text-slate-300"
              >
                <span class="text-[12px]">No events</span>
              </div>
            </div>
          </div>
        </div>
      </div><!-- End Week View -->

      <!-- ── AGENDA / TIMELINE VIEW ── -->
      <div v-else-if="calendarSubView === 'agenda'" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6">
        <div v-if="agendaGroups.length === 0" class="py-12 text-center text-slate-400">
          <p class="text-base font-bold text-slate-600">No scheduled exams or events found</p>
          <p class="text-[13px] text-slate-400 mt-1">Try clearing filters or click "Schedule Exam" to add a new schedule.</p>
        </div>

        <div v-else class="space-y-6">
          <div v-for="group in agendaGroups" :key="group.dateStr" class="space-y-3">
            <!-- Date header banner -->
            <div class="flex items-center gap-3">
              <span
                :class="[
                  'px-3 py-1 rounded-xl text-[12px] font-bold uppercase tracking-wider',
                  group.isToday ? 'bg-[#5138ed] text-white' : 'bg-slate-100 text-slate-700'
                ]"
              >
                {{ group.formatted }}
              </span>
              <div class="flex-1 h-px bg-slate-200/80"></div>
            </div>

            <!-- List of items for that day -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pl-2">
              <div
                v-for="item in group.items"
                :key="item.id"
                @click="openItemDetails(item)"
                :class="[
                  'p-4 rounded-xl border transition-all hover:shadow-xs cursor-pointer flex items-start justify-between gap-3',
                  item.hasConflict 
                    ? 'bg-rose-50 border-rose-200' 
                    : (item.sourceType === 'exam' ? 'bg-white border-slate-200 hover:border-indigo-200' : 'bg-blue-50/40 border-blue-100')
                ]"
              >
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold font-mono uppercase"
                      :class="item.sourceType === 'exam' ? 'bg-indigo-100 text-[#5138ed]' : 'bg-blue-100 text-blue-700'"
                    >
                      {{ item.sourceType === 'exam' ? (item.courseCode || 'EXAM') : 'UNIVERSITY EVENT' }}
                    </span>
                    <span v-if="item.time" class="text-[12px] font-medium text-slate-500">{{ item.time }}</span>
                  </div>
                  <h4 class="text-[14px] font-bold text-slate-800">{{ item.title }}</h4>
                  <p v-if="item.courseName" class="text-[12px] text-slate-500 font-medium">{{ item.courseName }}</p>
                  
                  <div class="flex items-center gap-4 text-[12px] text-slate-500 pt-1">
                    <span v-if="item.room" class="flex items-center gap-1 font-medium">
                      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                      {{ item.room }}
                    </span>
                    <span v-if="item.instructor" class="flex items-center gap-1">
                      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                      {{ item.instructor }}
                    </span>
                  </div>
                </div>

                <span :class="[getStatusBadgeClass(item.status), 'text-[11px] font-bold px-2.5 py-1 rounded-md capitalize shrink-0']">
                  {{ item.status }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div><!-- End Agenda View -->

    </div><!-- End Calendar Main View -->


    <!-- ══════════════════════════════════════════════════
         MAIN VIEW: EXAM SCHEDULE TABLE (EXACT SCREENSHOT LAYOUT)
    ══════════════════════════════════════════════════ -->
    <div v-show="activeMainView === 'table'" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
      
      <!-- Table Header Bar -->
      <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between gap-3">
        <div>
          <h3 class="text-base font-bold text-slate-800">Exam Schedule Table</h3>
          <p class="text-[12px] text-slate-500 mt-0.5">Real departmental exam schedules matching Wollo University records.</p>
        </div>
        <div class="text-[12px] font-bold text-slate-500">
          Showing {{ paginatedExams.length }} of {{ totalTableItems }} exams
        </div>
      </div>

      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto min-w-0 w-full">
        <table class="w-full min-w-[950px]">
          <thead class="bg-slate-50 border-b border-slate-200/80">
            <tr>
              <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Exam Title</th>
              <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course Code</th>
              <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course</th>
              <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Date &amp; Time</th>
              <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Room</th>
              <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
              <th class="text-center px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <!-- Loading Skeleton -->
            <tr v-if="isLoading" v-for="i in 5" :key="'sk-'+i">
              <td colspan="7" class="px-6 py-4">
                <div class="h-4 bg-slate-100 rounded animate-pulse w-full"></div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="paginatedExams.length === 0">
              <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                <p class="text-base font-bold text-slate-600">NO EXAMS SCHEDULED</p>
                <p class="text-[12px] text-slate-400 mt-1">There are no exams matching your search criteria.</p>
                <button
                  @click="openScheduleModal()"
                  class="mt-3 px-4 py-2 bg-[#5138ed] text-white text-[12px] font-bold rounded-xl hover:bg-indigo-700 transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                  Schedule New Exam
                </button>
              </td>
            </tr>

            <!-- Real Table Rows -->
            <tr
              v-else
              v-for="exam in paginatedExams"
              :key="exam.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <!-- Exam Title -->
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <span
                    v-if="exam.has_conflict"
                    class="w-2 h-2 rounded-full bg-rose-500 shrink-0"
                    title="Conflict detected!"
                  ></span>
                  <div>
                    <span class="text-[13px] font-bold text-slate-800 block">{{ exam.title }}</span>
                    <span class="text-[10px] text-slate-400 font-mono font-bold">{{ exam.code }}</span>
                  </div>
                </div>
              </td>

              <!-- Course Code -->
              <td class="px-6 py-4">
                <span class="inline-block px-2 py-0.5 rounded text-[12px] font-mono font-bold bg-slate-100 text-slate-700">
                  {{ exam.course_code || exam.courseCode }}
                </span>
              </td>

              <!-- Course -->
              <td class="px-6 py-4">
                <span class="text-[13px] font-medium text-slate-700 block">{{ exam.course || exam.courseName }}</span>
                <span class="text-[11px] text-slate-400 font-semibold">{{ exam.type || 'Midterm' }}</span>
              </td>

              <!-- Date & Time -->
              <td class="px-6 py-4">
                <div class="flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                  <span class="text-[13px] font-bold text-slate-700">{{ exam.date }}</span>
                </div>
                <span class="text-[11px] text-slate-500 pl-5 block">{{ exam.time }} - {{ exam.endTime }}</span>
              </td>

              <!-- Room -->
              <td class="px-6 py-4 text-[13px] font-medium text-slate-700">
                {{ exam.room || 'Room 101' }}
              </td>

              <!-- Status -->
              <td class="px-6 py-4">
                <span :class="[getStatusBadgeClass(exam.status), 'text-[11px] font-bold px-2.5 py-1 rounded-md capitalize']">
                  {{ exam.status }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4">
                <div class="flex items-center justify-center gap-1.5">
                  <!-- View Details -->
                  <button
                    @click="openExamDetailsFromTable(exam)"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 transition-colors cursor-pointer"
                    title="View Details"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>

                  <!-- Reschedule -->
                  <button
                    v-if="exam.can_edit"
                    @click="openRescheduleModal(exam)"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors cursor-pointer"
                    title="Reschedule Exam"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  </button>

                  <!-- Cancel / Delete -->
                  <button
                    v-if="exam.can_cancel"
                    @click="openCancelModal(exam)"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                    title="Cancel Exam"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Responsive Cards -->
      <div class="md:hidden divide-y divide-slate-100">
        <div v-if="paginatedExams.length === 0" class="p-8 text-center text-slate-400">
          <p class="text-base font-bold text-slate-600">No exams scheduled found</p>
        </div>
        <div
          v-for="exam in paginatedExams"
          :key="'mb-'+exam.id"
          class="p-4 space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="inline-block px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-100 text-slate-700 mb-1">
                {{ exam.course_code || exam.courseCode }}
              </span>
              <h4 class="text-[15px] font-bold text-slate-900 leading-snug">{{ exam.title }}</h4>
              <p class="text-[12px] text-slate-500 font-medium">{{ exam.course || exam.courseName }}</p>
            </div>
            <span :class="[getStatusBadgeClass(exam.status), 'text-[11px] font-bold px-2.5 py-0.5 rounded-md capitalize shrink-0']">
              {{ exam.status }}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2 text-[12px] bg-slate-50 p-3 rounded-xl">
            <div>
              <span class="text-slate-400 block text-[10px] uppercase font-bold">Date &amp; Time</span>
              <span class="font-bold text-slate-800">{{ exam.date }}</span>
              <span class="text-slate-500 block text-[11px]">{{ exam.time }}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px] uppercase font-bold">Room</span>
              <span class="font-bold text-slate-800">{{ exam.room || 'Room 101' }}</span>
              <span class="text-slate-500 block text-[11px]">{{ exam.duration }}</span>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button
              @click="openExamDetailsFromTable(exam)"
              class="px-3 py-1.5 text-[12px] font-bold text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50"
            >
              Details
            </button>
            <button
              v-if="exam.can_edit"
              @click="openRescheduleModal(exam)"
              class="px-3 py-1.5 text-[12px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100"
            >
              Reschedule
            </button>
            <button
              v-if="exam.can_cancel"
              @click="openCancelModal(exam)"
              class="px-3 py-1.5 text-[12px] font-bold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
        <p class="text-[12px] sm:text-[13px] font-medium text-slate-500 text-center sm:text-left">
          Showing {{ paginatedExams.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, totalTableItems) }} of {{ totalTableItems }} exams
        </p>
        <div class="flex items-center gap-1">
          <button
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage === 1"
            class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-600 hover:shadow-2xs transition-all border border-transparent hover:border-slate-200 disabled:opacity-40 cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
          </button>
          <button
            v-for="page in totalTablePages"
            :key="page"
            @click="currentPage = page"
            :class="[
              'w-9 h-9 flex items-center justify-center rounded-lg text-[13px] font-bold transition-all cursor-pointer',
              currentPage === page ? 'bg-[#5138ed] text-white shadow-xs' : 'text-slate-600 hover:bg-white hover:shadow-2xs border border-transparent hover:border-slate-200'
            ]"
          >
            {{ page }}
          </button>
          <button
            @click="currentPage = Math.min(totalTablePages, currentPage + 1)"
            :disabled="currentPage === totalTablePages"
            class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-600 hover:shadow-2xs transition-all border border-transparent hover:border-slate-200 disabled:opacity-40 cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>
      </div>

    </div><!-- End Table View -->


    <!-- ══════════════════════════════════════════════════
         SCHEDULE EXAM MODAL
    ══════════════════════════════════════════════════ -->
    <div
      v-if="showScheduleModal"
      class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 overflow-y-auto"
      @click.self="showScheduleModal = false"
    >
      <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 my-8 space-y-5 animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-lg font-bold text-slate-900">Schedule Department Examination</h3>
            <p class="text-[12px] text-slate-500">Configure schedule timing and venue for {{ departmentInfo.name }}.</p>
          </div>
          <button
            @click="showScheduleModal = false"
            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Real-Time Conflict Warning Banner -->
        <div
          v-if="detectedConflictWarning"
          class="bg-rose-50 border border-rose-200 p-3.5 rounded-xl flex items-start gap-3 text-rose-800 animate-in fade-in"
        >
          <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
          <div class="text-[12px]">
            <span class="font-bold block">Scheduling Conflict Detected!</span>
            <p class="mt-0.5 leading-relaxed">{{ detectedConflictWarning }}</p>
          </div>
        </div>

        <!-- Form fields -->
        <div class="space-y-4">
          <!-- Title -->
          <div>
            <label class="block text-[12px] font-bold text-slate-700 mb-1">
              Examination Title <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="scheduleForm.title"
              type="text"
              placeholder="e.g. Midterm Examination Semester I"
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            />
            <p v-if="scheduleFormErrors.title" class="text-rose-500 text-[11px] mt-1">{{ scheduleFormErrors.title }}</p>
          </div>

          <!-- Course & Instructor -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">
                Course <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="scheduleForm.course_code"
                class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
              >
                <option v-for="c in availableCourses" :key="c.id" :value="c.code">
                  {{ c.code }} - {{ c.title }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Lead Instructor</label>
              <select
                v-model="scheduleForm.instructor_id"
                class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
              >
                <option value="">Unassigned</option>
                <option v-for="ins in availableInstructors" :key="ins.id" :value="ins.id">
                  {{ ins.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Date & Start Time -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">
                Exam Date <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="scheduleForm.scheduled_date"
                type="date"
                class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
              />
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">
                Start Time <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="scheduleForm.scheduled_time"
                type="time"
                class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
              />
            </div>
          </div>

          <!-- Duration, Room, Exam Type -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Duration (Min)</label>
              <input
                v-model.number="scheduleForm.duration_minutes"
                type="number"
                min="10"
                max="360"
                class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
              />
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Room / Venue</label>
              <input
                v-model="scheduleForm.room"
                type="text"
                placeholder="e.g. Room 101, Lab 2"
                class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
              />
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Exam Type</label>
              <select
                v-model="scheduleForm.exam_type"
                class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
              >
                <option value="Midterm">Midterm</option>
                <option value="Final">Final</option>
                <option value="Quiz">Quiz</option>
                <option value="Practical">Practical</option>
                <option value="Assignment">Assignment</option>
              </select>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-[12px] font-bold text-slate-700 mb-1">Description / Notes (Optional)</label>
            <textarea
              v-model="scheduleForm.description"
              rows="2"
              placeholder="Instructions for faculty invigilators or students..."
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20"
            ></textarea>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <button
            @click="showScheduleModal = false"
            type="button"
            class="px-4 py-2.5 text-[13px] font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="submitScheduleExam"
            :disabled="isSubmittingSchedule || !!detectedConflictWarning"
            type="button"
            class="px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-700 text-white text-[13px] font-bold rounded-xl shadow-xs transition-colors disabled:opacity-50 flex items-center gap-2 cursor-pointer"
          >
            <span v-if="isSubmittingSchedule" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            Confirm &amp; Schedule
          </button>
        </div>

      </div>
    </div>


    <!-- ══════════════════════════════════════════════════
         RESCHEDULE EXAM MODAL
    ══════════════════════════════════════════════════ -->
    <div
      v-if="showRescheduleModal && selectedExamForAction"
      class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4"
      @click.self="showRescheduleModal = false"
    >
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-5 animate-in fade-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-lg font-bold text-slate-900">Reschedule Examination</h3>
            <p class="text-[12px] text-slate-500 font-mono">{{ selectedExamForAction.code }} • {{ selectedExamForAction.title }}</p>
          </div>
          <button @click="showRescheduleModal = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div v-if="rescheduleConflictWarning" class="bg-rose-50 border border-rose-200 p-3 rounded-xl text-rose-800 text-[12px]">
          <span class="font-bold block">Collision Detected:</span>
          {{ rescheduleConflictWarning }}
        </div>

        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">New Date</label>
              <input v-model="rescheduleForm.date" type="date" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700" />
            </div>
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Start Time</label>
              <input v-model="rescheduleForm.time" type="time" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Duration (Min)</label>
              <input v-model.number="rescheduleForm.duration_minutes" type="number" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700" />
            </div>
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Room / Venue</label>
              <input v-model="rescheduleForm.room" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-[13px] text-slate-700" />
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <button @click="showRescheduleModal = false" class="px-4 py-2 text-[13px] font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
            Cancel
          </button>
          <button
            @click="submitReschedule"
            :disabled="isSubmittingReschedule || !!rescheduleConflictWarning"
            class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-[13px] font-bold rounded-xl shadow-xs transition-colors disabled:opacity-50"
          >
            Confirm Reschedule
          </button>
        </div>
      </div>
    </div>


    <!-- ══════════════════════════════════════════════════
         CANCEL EXAM MODAL
    ══════════════════════════════════════════════════ -->
    <div
      v-if="showCancelModal && selectedExamForAction"
      class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4"
      @click.self="showCancelModal = false"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>

        <div>
          <h3 class="text-lg font-bold text-slate-900">Cancel Examination Schedule?</h3>
          <p class="text-[13px] text-slate-500 mt-1">
            Are you sure you want to cancel <strong class="text-slate-800">{{ selectedExamForAction.title }}</strong>? This will notify faculty invigilators and change status to Cancelled.
          </p>
        </div>

        <div>
          <label class="block text-[12px] font-bold text-slate-700 mb-1">Reason for Cancellation</label>
          <textarea
            v-model="cancelReason"
            rows="2"
            placeholder="e.g. Rescheduled due to University Academic Senate directive..."
            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button @click="showCancelModal = false" class="px-4 py-2 text-[13px] font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
            Go Back
          </button>
          <button
            @click="submitCancelExam"
            :disabled="isSubmittingCancel"
            class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-[13px] font-bold rounded-xl shadow-xs transition-colors"
          >
            Confirm Cancellation
          </button>
        </div>
      </div>
    </div>


    <!-- ══════════════════════════════════════════════════
         DETAILS DRAWER / MODAL
    ══════════════════════════════════════════════════ -->
    <div
      v-if="showDetailsModal && selectedItem"
      class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4"
      @click.self="showDetailsModal = false"
    >
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-5 animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-4">
          <div>
            <span
              class="px-2.5 py-0.5 rounded text-[11px] font-bold font-mono uppercase inline-block mb-1.5"
              :class="selectedItem.sourceType === 'exam' ? 'bg-indigo-100 text-[#5138ed]' : 'bg-blue-100 text-blue-700'"
            >
              {{ selectedItem.sourceType === 'exam' ? 'DEPARTMENT EXAM' : (selectedItem.category || 'ACADEMIC EVENT') }}
            </span>
            <h3 class="text-lg font-bold text-slate-900 leading-snug">{{ selectedItem.title }}</h3>
            <p v-if="selectedItem.courseName" class="text-[13px] text-slate-500">{{ selectedItem.courseName }}</p>
          </div>
          <button @click="showDetailsModal = false" class="text-slate-400 hover:text-slate-600 p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Info Grid -->
        <div class="grid grid-cols-2 gap-3 text-[12px] bg-slate-50/70 p-4 rounded-xl border border-slate-100">
          <div>
            <span class="text-slate-400 font-bold block uppercase text-[10px]">Date</span>
            <span class="font-bold text-slate-800 text-[13px]">{{ selectedItem.date }}</span>
          </div>
          <div>
            <span class="text-slate-400 font-bold block uppercase text-[10px]">Time Window</span>
            <span class="font-bold text-slate-800 text-[13px]">
              {{ selectedItem.time }} <span v-if="selectedItem.endTime">— {{ selectedItem.endTime }}</span>
            </span>
          </div>
          <div v-if="selectedItem.room">
            <span class="text-slate-400 font-bold block uppercase text-[10px]">Room / Venue</span>
            <span class="font-bold text-slate-800">{{ selectedItem.room }}</span>
          </div>
          <div v-if="selectedItem.instructor">
            <span class="text-slate-400 font-bold block uppercase text-[10px]">Lead Faculty</span>
            <span class="font-bold text-slate-800">{{ selectedItem.instructor }}</span>
          </div>
          <div>
            <span class="text-slate-400 font-bold block uppercase text-[10px]">Status</span>
            <span :class="[getStatusBadgeClass(selectedItem.status), 'px-2 py-0.5 rounded text-[11px] font-bold inline-block capitalize mt-0.5']">
              {{ selectedItem.status }}
            </span>
          </div>
          <div v-if="selectedItem.sourceType === 'exam' && selectedItem.rawItem?.total_marks">
            <span class="text-slate-400 font-bold block uppercase text-[10px]">Total Marks</span>
            <span class="font-bold text-slate-800">{{ selectedItem.rawItem.total_marks }} pts</span>
          </div>
        </div>

        <!-- Conflict notification if present -->
        <div v-if="selectedItem.hasConflict" class="bg-rose-50 border border-rose-200 p-3 rounded-xl text-rose-800 text-[12px]">
          <span class="font-bold block">Scheduling Conflict Warning:</span>
          {{ selectedItem.conflictReason || 'Another exam is scheduled at overlapping times in this department.' }}
        </div>

        <!-- Action Controls for Exams -->
        <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
          <button
            @click="showDetailsModal = false"
            class="px-4 py-2 text-[13px] font-bold text-slate-600 hover:bg-slate-100 rounded-xl"
          >
            Close
          </button>
          
          <template v-if="selectedItem.sourceType === 'exam'">
            <button
              v-if="selectedItem.rawItem?.can_edit"
              @click="openRescheduleModal(selectedItem.rawItem)"
              class="px-4 py-2 text-[13px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-xl transition-colors"
            >
              Reschedule
            </button>
            <button
              v-if="selectedItem.rawItem?.can_cancel"
              @click="openCancelModal(selectedItem.rawItem)"
              class="px-4 py-2 text-[13px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition-colors"
            >
              Cancel Exam
            </button>
          </template>
        </div>

      </div>
    </div>


    <!-- ══════════════════════════════════════════════════
         TOAST NOTIFICATION COMPONENT
    ══════════════════════════════════════════════════ -->
    <transition
      enter-active-class="transform transition ease-out duration-300"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toast.show"
        class="fixed bottom-5 right-5 z-50 max-w-md bg-slate-900 text-white px-4 py-3 rounded-2xl shadow-2xl flex items-center gap-3 border border-slate-800"
      >
        <span
          class="w-2.5 h-2.5 rounded-full shrink-0"
          :class="toast.type === 'success' ? 'bg-emerald-400' : (toast.type === 'error' ? 'bg-rose-400' : 'bg-blue-400')"
        ></span>
        <p class="text-[13px] font-medium leading-snug">{{ toast.message }}</p>
        <button @click="toast.show = false" class="text-slate-400 hover:text-white ml-auto">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
    </transition>

  </div>
</template>

<style scoped>
@media print {
  body {
    background-color: white !important;
  }
}
</style>
