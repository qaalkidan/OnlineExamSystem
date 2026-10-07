<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useCalendarStore, type AcademicEvent, type EventCategory } from '../../../store/calendarStore'
import { useSettingsStore } from '../../../store/settingsStore'
import apiClient from '../../../core/api/apiClient'

const calendarStore = useCalendarStore()
const settingsStore = useSettingsStore()

// ── Navigation & Tabs ────────────────────────────────────────────────────────
const activeTab = ref<'Calendar View' | 'Event List'>('Calendar View')
const tabs: ('Calendar View' | 'Event List')[] = ['Calendar View', 'Event List']
const showSettings = ref(false)
const settingsTab = ref<'Event Categories' | 'Term Settings'>('Event Categories')

// ── Academic Period Selectors (Real DB Data) ─────────────────────────────────
const selectedAcademicYear = ref('')
const selectedSemester = ref('')
const selectedCategoryFilter = ref<number | 'all'>('all')

// ── Export & Toast State ─────────────────────────────────────────────────────
const showExportDropdown = ref(false)
const isExporting = ref(false)
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'info'
})
let toastTimer: any = null

function showToast(message: string, type: 'success' | 'error' | 'info' = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 3500)
}

// ── Calendar Grid Navigation ────────────────────────────────────────────────
const today = new Date()
const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
const calendarYear = ref(today.getFullYear())
const calendarMonth = ref(today.getMonth() + 1) // 1-indexed
const calendarViewMode = ref<'month' | 'week' | 'list'>('month')

const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const calendarTitle = computed(() => {
  const d = new Date(calendarYear.value, calendarMonth.value - 1, 1)
  return d.toLocaleString('en-US', { month: 'long', year: 'numeric' })
})

function prevMonth() {
  if (calendarMonth.value === 1) {
    calendarMonth.value = 12
    calendarYear.value--
  } else {
    calendarMonth.value--
  }
}

function nextMonth() {
  if (calendarMonth.value === 12) {
    calendarMonth.value = 1
    calendarYear.value++
  } else {
    calendarMonth.value++
  }
}

function goToday() {
  calendarYear.value = today.getFullYear()
  calendarMonth.value = today.getMonth() + 1
}

// ── Calendar Cells (Month View) ─────────────────────────────────────────────
const calendarCells = computed(() => {
  const y = calendarYear.value
  const m = calendarMonth.value
  const firstDay = new Date(y, m - 1, 1).getDay() // 0=Sun
  const daysInMonth = new Date(y, m, 0).getDate()
  const daysInPrev = new Date(y, m - 1, 0).getDate()
  const cells: { day: number; month: 'prev' | 'current' | 'next'; dateStr: string }[] = []

  // Previous month trailing days
  for (let i = firstDay - 1; i >= 0; i--) {
    const d = daysInPrev - i
    const pm = m === 1 ? 12 : m - 1
    const py = m === 1 ? y - 1 : y
    cells.push({
      day: d,
      month: 'prev',
      dateStr: `${py}-${String(pm).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    })
  }

  // Current month days
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push({
      day: d,
      month: 'current',
      dateStr: `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    })
  }

  // Next month leading days to complete 35 or 42 grid cells
  const remaining = (cells.length > 35 ? 42 : 35) - cells.length
  for (let d = 1; d <= remaining; d++) {
    const nm = m === 12 ? 1 : m + 1
    const ny = m === 12 ? y + 1 : y
    cells.push({
      day: d,
      month: 'next',
      dateStr: `${ny}-${String(nm).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    })
  }

  return cells
})

// ── Week View Logic ─────────────────────────────────────────────────────────
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
    return {
      date: d,
      dateStr: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`,
      label: d.toLocaleString('en-US', { weekday: 'short' }),
      dayNum: d.getDate()
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

function prevWeek() {
  const d = new Date(weekStart.value)
  d.setDate(d.getDate() - 7)
  weekStart.value = d
}

function nextWeek() {
  const d = new Date(weekStart.value)
  d.setDate(d.getDate() + 7)
  weekStart.value = d
}

function goTodayWeek() {
  const d = new Date()
  const day = d.getDay()
  const diff = day === 0 ? -6 : 1 - day
  const mon = new Date(d)
  mon.setDate(d.getDate() + diff)
  mon.setHours(0, 0, 0, 0)
  weekStart.value = mon
}

// ── Filtered Events for Calendar & List Views ───────────────────────────────
const displayedEvents = computed(() => {
  let list = calendarStore.events
  if (selectedAcademicYear.value && selectedAcademicYear.value !== 'all') {
    list = list.filter(e => e.academic_year === selectedAcademicYear.value)
  }
  if (selectedSemester.value && selectedSemester.value !== 'all') {
    list = list.filter(e => e.semester === selectedSemester.value)
  }
  if (selectedCategoryFilter.value !== 'all') {
    list = list.filter(e => e.category_id === selectedCategoryFilter.value)
  }
  return list
})

function eventsOnDay(dateStr: string) {
  return displayedEvents.value.filter(e => e.start_date <= dateStr && e.end_date >= dateStr)
}

const listViewEvents = computed(() => {
  const y = calendarYear.value
  const m = calendarMonth.value
  const pad = (n: number) => String(n).padStart(2, '0')
  const monthStr = `${y}-${pad(m)}`
  return displayedEvents.value
    .filter(e => e.start_date.startsWith(monthStr) || e.end_date.startsWith(monthStr))
    .slice()
    .sort((a, b) => a.start_date.localeCompare(b.start_date))
})

// ── Event Details Modal State ───────────────────────────────────────────────
const viewingEvent = ref<AcademicEvent | null>(null)
const showDetailsModal = ref(false)

function openDetailsModal(ev: AcademicEvent) {
  viewingEvent.value = ev
  showDetailsModal.value = true
}

function closeDetailsModal() {
  showDetailsModal.value = false
  viewingEvent.value = null
}

// ── Add / Edit Event Modal State ────────────────────────────────────────────
const showEventFormModal = ref(false)
const isEditingEvent = ref(false)
const editingEventId = ref<number | null>(null)
const eventSaveError = ref<string | null>(null)
const isCustomTitle = ref(false)

const eventForm = ref({
  title: '',
  category_id: '' as string | number,
  academic_year: '',
  semester: '',
  start_date: todayStr,
  end_date: todayStr,
  all_day: true,
  start_time: '',
  end_time: '',
  description: '',
  status: 'upcoming' as 'upcoming' | 'ongoing' | 'completed' | 'cancelled',
  color: '#4338ca',
  is_recurring: false
})

function openAddEventModal(prefilledDate?: string) {
  isEditingEvent.value = false
  editingEventId.value = null
  isCustomTitle.value = false
  eventSaveError.value = null

  const targetDate = prefilledDate || todayStr
  eventForm.value = {
    title: '',
    category_id: calendarStore.categories.length > 0 ? calendarStore.categories[0].id : '',
    academic_year: selectedAcademicYear.value && selectedAcademicYear.value !== 'all'
      ? selectedAcademicYear.value
      : (settingsStore.academicYear || '2026/2027'),
    semester: selectedSemester.value && selectedSemester.value !== 'all'
      ? selectedSemester.value
      : (settingsStore.semester || 'First Semester'),
    start_date: targetDate,
    end_date: targetDate,
    all_day: true,
    start_time: '',
    end_time: '',
    description: '',
    status: 'upcoming',
    color: '#4338ca',
    is_recurring: false
  }

  showEventFormModal.value = true
}

function openEditEventModal(ev: AcademicEvent) {
  closeDetailsModal()
  isEditingEvent.value = true
  editingEventId.value = ev.id
  isCustomTitle.value = true
  eventSaveError.value = null

  eventForm.value = {
    title: ev.title,
    category_id: ev.category_id || (calendarStore.categories.length > 0 ? calendarStore.categories[0].id : ''),
    academic_year: ev.academic_year || settingsStore.academicYear,
    semester: ev.semester || settingsStore.semester,
    start_date: ev.start_date,
    end_date: ev.end_date,
    all_day: ev.all_day,
    start_time: ev.start_time || '',
    end_time: ev.end_time || '',
    description: ev.description || '',
    status: ev.status,
    color: ev.color || '#4338ca',
    is_recurring: ev.is_recurring
  }

  showEventFormModal.value = true
}

function closeEventFormModal() {
  showEventFormModal.value = false
  editingEventId.value = null
  eventSaveError.value = null
}

async function handleSaveEvent() {
  eventSaveError.value = null
  if (!eventForm.value.title.trim()) {
    eventSaveError.value = 'Please provide an event title.'
    return
  }
  if (!eventForm.value.start_date) {
    eventSaveError.value = 'Start date is required.'
    return
  }
  if (!eventForm.value.end_date) {
    eventSaveError.value = 'End date is required.'
    return
  }
  if (eventForm.value.start_date > eventForm.value.end_date) {
    eventSaveError.value = 'Start date cannot be after end date.'
    return
  }

  const payload: any = {
    title: eventForm.value.title.trim(),
    description: eventForm.value.description.trim() || null,
    category_id: eventForm.value.category_id || null,
    academic_year: eventForm.value.academic_year,
    semester: eventForm.value.semester,
    start_date: eventForm.value.start_date,
    end_date: eventForm.value.end_date,
    all_day: eventForm.value.all_day,
    start_time: eventForm.value.all_day ? null : (eventForm.value.start_time || null),
    end_time: eventForm.value.all_day ? null : (eventForm.value.end_time || null),
    status: eventForm.value.status,
    color: eventForm.value.color,
    is_recurring: eventForm.value.is_recurring
  }

  if (isEditingEvent.value && editingEventId.value) {
    const res = await calendarStore.updateEvent(editingEventId.value, payload)
    if (res.success) {
      closeEventFormModal()
      showToast('Academic event updated successfully.')
    } else {
      eventSaveError.value = res.message || 'Failed to update event.'
    }
  } else {
    const res = await calendarStore.addEvent(payload)
    if (res.success) {
      closeEventFormModal()
      showToast('Academic event scheduled successfully.')
    } else {
      eventSaveError.value = res.message || 'Failed to schedule event.'
    }
  }
}

// ── Delete Confirmation Modal State ─────────────────────────────────────────
const eventToDelete = ref<AcademicEvent | null>(null)
const showDeleteEventModal = ref(false)
const isDeleting = ref(false)

function confirmDeleteEvent(ev: AcademicEvent) {
  closeDetailsModal()
  eventToDelete.value = ev
  showDeleteEventModal.value = true
}

async function handleDeleteEvent() {
  if (!eventToDelete.value) return
  isDeleting.value = true
  const res = await calendarStore.deleteEvent(eventToDelete.value.id)
  isDeleting.value = false
  if (res.success) {
    showDeleteEventModal.value = false
    eventToDelete.value = null
    showToast('Academic event removed successfully.')
  } else {
    showToast(res.message || 'Failed to remove event.', 'error')
  }
}

// ── Event List Tab Filtering & Search ───────────────────────────────────────
const eventListSearch = ref('')
const eventListCategory = ref('')
const eventListStatus = ref('')
const eventListSemester = ref('')

const tableFilteredEvents = computed(() => {
  let list = calendarStore.events

  if (eventListSearch.value.trim()) {
    const q = eventListSearch.value.toLowerCase()
    list = list.filter(e =>
      e.title.toLowerCase().includes(q) ||
      (e.description && e.description.toLowerCase().includes(q)) ||
      (e.category_name && e.category_name.toLowerCase().includes(q))
    )
  }
  if (eventListCategory.value) {
    list = list.filter(e => String(e.category_id) === eventListCategory.value)
  }
  if (eventListStatus.value) {
    list = list.filter(e => e.status === eventListStatus.value)
  }
  if (eventListSemester.value) {
    list = list.filter(e => e.semester === eventListSemester.value)
  }
  return list
})

function resetTableFilters() {
  eventListSearch.value = ''
  eventListCategory.value = ''
  eventListStatus.value = ''
  eventListSemester.value = ''
}

// ── Category Settings Management State ──────────────────────────────────────
const showAddCategoryModal = ref(false)
const editingCategory = ref<EventCategory | null>(null)
const deletingCategory = ref<EventCategory | null>(null)
const showDeleteCategoryModal = ref(false)
const catSaveError = ref<string | null>(null)
const catDeleteError = ref<string | null>(null)

const catForm = ref({
  name: '',
  description: '',
  color: '#4338ca',
  type: 'custom' as 'system' | 'custom',
  status: 'active' as 'active' | 'inactive'
})

function openAddCategoryModal() {
  editingCategory.value = null
  catForm.value = { name: '', description: '', color: '#4338ca', type: 'custom', status: 'active' }
  catSaveError.value = null
  showAddCategoryModal.value = true
}

function openEditCategoryModal(cat: EventCategory) {
  editingCategory.value = cat
  catForm.value = {
    name: cat.name,
    description: cat.description || '',
    color: cat.color,
    type: cat.type,
    status: cat.status
  }
  catSaveError.value = null
  showAddCategoryModal.value = true
}

async function handleSaveCategory() {
  catSaveError.value = null
  if (!catForm.value.name.trim()) {
    catSaveError.value = 'Category name is required.'
    return
  }
  const payload = {
    name: catForm.value.name.trim(),
    description: catForm.value.description.trim() || undefined,
    color: catForm.value.color,
    type: catForm.value.type,
    status: catForm.value.status
  }
  const result = editingCategory.value
    ? await calendarStore.updateCategory(editingCategory.value.id, payload)
    : await calendarStore.addCategory(payload)

  if (result.success) {
    showAddCategoryModal.value = false
    showToast(editingCategory.value ? 'Category updated.' : 'Category created.')
  } else {
    catSaveError.value = result.message || 'An error occurred.'
  }
}

function confirmDeleteCategory(cat: EventCategory) {
  deletingCategory.value = cat
  catDeleteError.value = null
  showDeleteCategoryModal.value = true
}

async function handleDeleteCategory() {
  if (!deletingCategory.value) return
  const result = await calendarStore.deleteCategory(deletingCategory.value.id)
  if (result.success) {
    showDeleteCategoryModal.value = false
    deletingCategory.value = null
    showToast('Category deleted successfully.')
  } else {
    catDeleteError.value = result.message || 'Cannot delete category.'
  }
}

// ── Active Term Settings Configuration ──────────────────────────────────────
const configAcademicYear = ref('')
const configSemester = ref('')
const isSavingTerm = ref(false)

async function handleSaveTermConfig() {
  if (!configAcademicYear.value.trim() || !configSemester.value.trim()) {
    showToast('Both Academic Year and Semester are required.', 'error')
    return
  }
  isSavingTerm.value = true
  try {
    await settingsStore.updateTerm(configAcademicYear.value.trim(), configSemester.value.trim())
    await calendarStore.fetchEvents()
    showToast('Current institutional term updated successfully.')
  } catch (err: any) {
    showToast(err?.response?.data?.message || 'Failed to update term.', 'error')
  } finally {
    isSavingTerm.value = false
  }
}

// ── Export Handling ─────────────────────────────────────────────────────────
async function handleExport(format: 'csv' | 'pdf') {
  showExportDropdown.value = false
  isExporting.value = true
  try {
    const params: Record<string, any> = { format }
    if (selectedAcademicYear.value && selectedAcademicYear.value !== 'all') {
      params.academic_year = selectedAcademicYear.value
    }
    if (selectedSemester.value && selectedSemester.value !== 'all') {
      params.semester = selectedSemester.value
    }
    if (selectedCategoryFilter.value !== 'all') {
      params.category_id = selectedCategoryFilter.value
    }

    if (format === 'csv') {
      const response = await apiClient.get('/admin/calendar/events/export', {
        params,
        responseType: 'blob'
      })
      const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `wollo_university_calendar_${new Date().toISOString().slice(0, 10)}.csv`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
      showToast('Academic calendar exported as CSV.')
    } else {
      const queryStr = new URLSearchParams(params).toString()
      const url = `${apiClient.defaults.baseURL || 'http://localhost:8000/api/v1'}/admin/calendar/events/export?${queryStr}`
      window.open(url, '_blank')
    }
  } catch (err) {
    console.error('Export failed:', err)
    showToast('Failed to export academic calendar.', 'error')
  } finally {
    isExporting.value = false
  }
}

// ── Days Remaining & Status Helpers ─────────────────────────────────────────
function daysUntil(dateStr: string): string {
  const diff = Math.ceil((new Date(dateStr + 'T00:00:00').getTime() - new Date(todayStr + 'T00:00:00').getTime()) / 86400000)
  if (diff < 0) return `${Math.abs(diff)}d ago`
  if (diff === 0) return 'Today'
  return `In ${diff} days`
}

function daysUntilBadgeColor(dateStr: string): string {
  const diff = Math.ceil((new Date(dateStr + 'T00:00:00').getTime() - new Date(todayStr + 'T00:00:00').getTime()) / 86400000)
  if (diff < 0) return 'bg-slate-100 text-slate-500'
  if (diff <= 3) return 'bg-rose-50 text-rose-600 font-bold'
  if (diff <= 7) return 'bg-amber-50 text-amber-600 font-bold'
  return 'bg-emerald-50 text-emerald-600 font-bold'
}

function getStatusBadge(status: string) {
  switch (status) {
    case 'upcoming': return 'bg-indigo-50 text-[#4338ca] border-indigo-200'
    case 'ongoing': return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'completed': return 'bg-slate-100 text-slate-600 border-slate-200'
    case 'cancelled': return 'bg-rose-50 text-rose-700 border-rose-200'
    default: return 'bg-slate-50 text-slate-600 border-slate-200'
  }
}

// ── Real Academic Milestones ────────────────────────────────────────────────
const realMilestones = computed(() => {
  const list = calendarStore.events
  return [
    {
      title: 'Current Academic Term',
      date: `${settingsStore.academicYear} ${settingsStore.semester}`,
      icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
      color: 'bg-indigo-50 text-[#4338ca]'
    },
    {
      title: 'Classes / Teaching Period',
      date: list.find(e => e.title.toLowerCase().includes('class') || e.title.toLowerCase().includes('teach'))
        ? `${list.find(e => e.title.toLowerCase().includes('class') || e.title.toLowerCase().includes('teach'))?.start_date} to ${list.find(e => e.title.toLowerCase().includes('class') || e.title.toLowerCase().includes('teach'))?.end_date}`
        : (calendarStore.eventStats.period_start && calendarStore.eventStats.period_end ? `${calendarStore.eventStats.period_start} to ${calendarStore.eventStats.period_end}` : 'Not scheduled yet'),
      icon: 'M12 14l9-5-9-5-9 5 9 5z',
      color: 'bg-emerald-50 text-emerald-600'
    },
    {
      title: 'Examination Periods',
      date: list.find(e => e.category_name?.toLowerCase().includes('exam') || e.title.toLowerCase().includes('exam'))
        ? `${list.find(e => e.category_name?.toLowerCase().includes('exam') || e.title.toLowerCase().includes('exam'))?.start_date} to ${list.find(e => e.category_name?.toLowerCase().includes('exam') || e.title.toLowerCase().includes('exam'))?.end_date}`
        : 'Not scheduled yet',
      icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
      color: 'bg-blue-50 text-blue-600'
    },
    {
      title: 'Holidays & University Breaks',
      date: list.find(e => e.category_name?.toLowerCase().includes('holiday') || e.title.toLowerCase().includes('break'))
        ? `${list.find(e => e.category_name?.toLowerCase().includes('holiday'))?.start_date}`
        : 'Not scheduled yet',
      icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
      color: 'bg-purple-50 text-purple-600'
    }
  ]
})

// ── Initial Mount & Lifecycle ───────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([
    settingsStore.fetchSettings(),
    calendarStore.fetchEvents(),
    calendarStore.fetchCategories()
  ])
  configAcademicYear.value = settingsStore.academicYear
  configSemester.value = settingsStore.semester
  selectedAcademicYear.value = settingsStore.academicYear
  selectedSemester.value = settingsStore.semester
})
</script>

<template>
  <div class="space-y-6 min-w-0 w-full pb-10">

    <!-- Floating Toast Notification -->
    <Teleport to="body">
      <div
        v-if="toast.show"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-[13px] font-semibold transition-all animate-in fade-in slide-in-from-bottom-5"
        :class="toast.type === 'success' ? 'bg-emerald-900/95 text-white border-emerald-700' : toast.type === 'error' ? 'bg-rose-900/95 text-white border-rose-700' : 'bg-slate-900/95 text-white border-slate-700'"
      >
        <span v-if="toast.type === 'success'">✓</span>
        <span v-else-if="toast.type === 'error'">✕</span>
        <span>{{ toast.message }}</span>
      </div>
    </Teleport>

    <!-- ── Institutional Header & Breadcrumbs ───────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        
        <!-- Left: Titles & Institutional Term Badge -->
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[12px] font-semibold text-slate-400">
            <span>Administration</span>
            <span>/</span>
            <span>System</span>
            <span>/</span>
            <span class="text-[#4338ca] font-bold">Academic Calendar</span>
          </div>

          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">University Academic Calendar & Planning Center</h1>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-[#4338ca] border border-indigo-200/60 shadow-xs">
              <span class="w-1.5 h-1.5 rounded-full bg-[#4338ca] animate-pulse"></span>
              {{ settingsStore.formattedAcademicTerm }}
            </span>
          </div>
          <p class="text-[13px] text-slate-500 font-medium">
            Manage academic years, semester terms, registration deadlines, examinations, and official university events.
          </p>
        </div>

        <!-- Right: Actions Toolbar -->
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
          
          <button
            @click="goToday(); goTodayWeek()"
            class="px-3.5 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 text-[12px] font-bold rounded-xl transition-colors shadow-xs"
          >
            Today
          </button>

          <button
            @click="calendarStore.fetchEvents(); calendarStore.fetchCategories(); showToast('Calendar refreshed.')"
            :disabled="calendarStore.isLoadingEvents"
            class="flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-[12px] font-bold rounded-xl transition-colors shadow-xs disabled:opacity-50"
            title="Reload from server"
          >
            <svg
              class="w-4 h-4 text-slate-500"
              :class="{ 'animate-spin': calendarStore.isLoadingEvents }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span>Refresh</span>
          </button>

          <!-- Calendar Settings Button -->
          <button
            @click="showSettings = !showSettings"
            class="flex items-center gap-1.5 px-3.5 py-2 border rounded-xl text-[12px] font-bold transition-all shadow-xs"
            :class="showSettings ? 'bg-indigo-50 border-indigo-300 text-[#4338ca]' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>{{ showSettings ? 'Close Settings' : 'Calendar Settings' }}</span>
          </button>

          <!-- Export Dropdown -->
          <div class="relative">
            <button
              @click="showExportDropdown = !showExportDropdown"
              class="flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-[12px] font-bold rounded-xl transition-colors shadow-xs"
            >
              <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              <span>Export</span>
              <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div
              v-if="showExportDropdown"
              class="absolute right-0 mt-2 w-52 bg-white border border-slate-200 rounded-xl shadow-xl py-2 z-50 text-[12px] font-medium animate-in fade-in zoom-in-95"
            >
              <button
                @click="handleExport('csv')"
                class="w-full flex items-center gap-2.5 px-4 py-2 text-left text-slate-700 hover:bg-slate-50 hover:text-[#4338ca] transition-colors"
              >
                <span class="w-6 h-6 rounded bg-emerald-50 text-emerald-600 font-bold text-[10px] flex items-center justify-center">CSV</span>
                <span>Export as CSV</span>
              </button>
              <button
                @click="handleExport('pdf')"
                class="w-full flex items-center gap-2.5 px-4 py-2 text-left text-slate-700 hover:bg-slate-50 hover:text-[#4338ca] transition-colors"
              >
                <span class="w-6 h-6 rounded bg-indigo-50 text-[#4338ca] font-bold text-[10px] flex items-center justify-center">PDF</span>
                <span>Printable Official Schedule</span>
              </button>
            </div>
          </div>

          <!-- + Add Event Button -->
          <button
            @click="openAddEventModal()"
            class="flex items-center gap-2 px-4 py-2 bg-[#4338ca] hover:bg-indigo-700 text-white text-[13px] font-bold rounded-xl transition-colors shadow-sm shadow-indigo-200"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Add Event</span>
          </button>

        </div>

      </div>
    </div>

    <!-- ── Academic Period Summary KPI Cards (100% Real Database Data) ─────── -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
      
      <!-- Total Events -->
      <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs space-y-1">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Events</span>
          <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#4338ca] flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{{ calendarStore.eventStats.total }}</div>
        <p class="text-[11px] text-slate-400 font-medium">Scheduled activities</p>
      </div>

      <!-- Academic Weeks -->
      <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs space-y-1">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Academic Weeks</span>
          <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
          {{ calendarStore.eventStats.academic_weeks ? calendarStore.eventStats.academic_weeks : 'N/A' }}
        </div>
        <p class="text-[11px] text-slate-400 font-medium">
          {{ calendarStore.eventStats.academic_weeks ? 'Calculated term duration' : 'Awaiting scheduled dates' }}
        </p>
      </div>

      <!-- Holidays -->
      <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs space-y-1">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Holidays</span>
          <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{{ calendarStore.eventStats.holidays }}</div>
        <p class="text-[11px] text-slate-400 font-medium">Scheduled institutional breaks</p>
      </div>

      <!-- Exam Periods -->
      <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs space-y-1">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Exam Periods</span>
          <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{{ calendarStore.eventStats.exams }}</div>
        <p class="text-[11px] text-slate-400 font-medium">Midterm & final milestones</p>
      </div>

      <!-- Upcoming Deadlines -->
      <div class="col-span-2 sm:col-span-1 p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs space-y-1">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Upcoming Events</span>
          <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
        </div>
        <div class="text-2xl sm:text-3xl font-black text-amber-600 tracking-tight">{{ calendarStore.eventStats.upcoming }}</div>
        <p class="text-[11px] text-slate-400 font-medium">Pending milestone dates</p>
      </div>

    </div>

    <!-- ── Settings View ────────────────────────────────────────────────────── -->
    <div v-if="showSettings" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-6">
      
      <!-- Sub-Tabs inside settings -->
      <div class="flex items-center justify-between border-b border-slate-200 pb-3">
        <div class="flex items-center gap-4">
          <button
            @click="settingsTab = 'Event Categories'"
            class="text-[13px] font-bold pb-2 transition-colors border-b-2"
            :class="settingsTab === 'Event Categories' ? 'border-[#4338ca] text-[#4338ca]' : 'border-transparent text-slate-500 hover:text-slate-700'"
          >
            Event Categories ({{ calendarStore.categories.length }})
          </button>
          <button
            @click="settingsTab = 'Term Settings'"
            class="text-[13px] font-bold pb-2 transition-colors border-b-2"
            :class="settingsTab === 'Term Settings' ? 'border-[#4338ca] text-[#4338ca]' : 'border-transparent text-slate-500 hover:text-slate-700'"
          >
            Academic Term Configuration
          </button>
        </div>

        <button
          @click="showSettings = false"
          class="text-[12px] font-bold text-slate-500 hover:text-slate-800"
        >
          ✕ Close Settings
        </button>
      </div>

      <!-- 1. Event Categories Tab -->
      <div v-if="settingsTab === 'Event Categories'" class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-base font-black text-slate-900">Manage Event Categories</h3>
            <p class="text-[12px] text-slate-500">Configure visual tags and category types for academic calendar events.</p>
          </div>
          <button
            @click="openAddCategoryModal"
            class="px-3.5 py-1.5 bg-[#4338ca] hover:bg-indigo-700 text-white rounded-xl text-[12px] font-bold shadow-xs"
          >
            + New Category
          </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="w-full text-left whitespace-nowrap text-[12px]">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
              <tr>
                <th class="px-4 py-3">Category Name</th>
                <th class="px-4 py-3">Description</th>
                <th class="px-4 py-3">Color</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="cat in calendarStore.categories" :key="cat.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2 font-bold text-slate-800">
                    <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: cat.color }"></span>
                    {{ cat.name }}
                  </div>
                </td>
                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ cat.description || '—' }}</td>
                <td class="px-4 py-3 font-mono font-bold text-slate-600">{{ cat.color }}</td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold capitalize" :class="cat.type === 'system' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'">
                    {{ cat.type }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold capitalize" :class="cat.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                    {{ cat.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button
                      @click="openEditCategoryModal(cat)"
                      class="text-blue-600 hover:text-blue-800 font-bold text-[11px]"
                    >
                      Edit
                    </button>
                    <button
                      v-if="cat.type !== 'system'"
                      @click="confirmDeleteCategory(cat)"
                      class="text-rose-600 hover:text-rose-800 font-bold text-[11px]"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 2. Academic Term Configuration Tab -->
      <div v-else class="max-w-xl space-y-4">
        <div>
          <h3 class="text-base font-black text-slate-900">Current Academic Term</h3>
          <p class="text-[12px] text-slate-500">Update the university-wide active Academic Year and Semester in the database.</p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-[12px] font-bold text-slate-700 mb-1">Active Academic Year</label>
            <input
              v-model="configAcademicYear"
              type="text"
              placeholder="e.g. 2026/2027"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
            />
          </div>

          <div>
            <label class="block text-[12px] font-bold text-slate-700 mb-1">Active Semester</label>
            <select
              v-model="configSemester"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
            >
              <option value="First Semester">First Semester</option>
              <option value="Second Semester">Second Semester</option>
              <option value="Summer Term">Summer Term</option>
            </select>
          </div>

          <button
            @click="handleSaveTermConfig"
            :disabled="isSavingTerm"
            class="px-5 py-2.5 bg-[#4338ca] hover:bg-indigo-700 text-white text-[13px] font-bold rounded-xl shadow-xs disabled:opacity-50"
          >
            {{ isSavingTerm ? 'Updating Term…' : 'Save Institutional Term' }}
          </button>
        </div>
      </div>

    </div>

    <!-- ── Main Planning Center (Calendar vs Event List) ────────────────────── -->
    <template v-if="!showSettings">
      
      <!-- Top Sub-Bar: View Tabs + Real Period Selectors -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-3">
        
        <!-- Tabs -->
        <div class="flex items-center gap-6">
          <button
            v-for="t in tabs"
            :key="t"
            @click="activeTab = t"
            class="text-[13px] font-bold pb-2 transition-colors border-b-2"
            :class="activeTab === t ? 'border-[#4338ca] text-[#4338ca]' : 'border-transparent text-slate-500 hover:text-slate-800'"
          >
            {{ t }}
          </button>
        </div>

        <!-- Academic Period Filtering Dropdowns -->
        <div class="flex flex-wrap items-center gap-2.5">
          
          <!-- Academic Year Filter -->
          <div class="flex items-center gap-1.5 text-[12px] text-slate-500 font-medium">
            <span>Year:</span>
            <select
              v-model="selectedAcademicYear"
              class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 font-semibold focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Years</option>
              <option v-for="yr in calendarStore.eventStats.available_years" :key="yr" :value="yr">{{ yr }}</option>
            </select>
          </div>

          <!-- Semester Filter -->
          <div class="flex items-center gap-1.5 text-[12px] text-slate-500 font-medium">
            <span>Semester:</span>
            <select
              v-model="selectedSemester"
              class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 font-semibold focus:outline-none focus:border-[#4338ca]"
            >
              <option value="all">All Semesters</option>
              <option v-for="sem in calendarStore.eventStats.available_semesters" :key="sem" :value="sem">{{ sem }}</option>
            </select>
          </div>

          <!-- Reset Filter -->
          <button
            v-if="selectedAcademicYear !== settingsStore.academicYear || selectedSemester !== settingsStore.semester || selectedCategoryFilter !== 'all'"
            @click="selectedAcademicYear = settingsStore.academicYear; selectedSemester = settingsStore.semester; selectedCategoryFilter = 'all'"
            class="text-[11px] font-bold text-rose-600 hover:underline ml-1"
          >
            Reset
          </button>

        </div>

      </div>

      <!-- ── TAB 1: CALENDAR VIEW ───────────────────────────────────────────── -->
      <div v-if="activeTab === 'Calendar View'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left: Interactive Calendar Container (Span 8) -->
        <div class="lg:col-span-8 space-y-6">
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-4">
            
            <!-- Calendar Header Toolbar -->
            <div class="flex flex-wrap items-center justify-between gap-3">
              
              <!-- Month Navigation -->
              <div class="flex items-center gap-2">
                <div class="flex bg-slate-100 border border-slate-200 rounded-xl p-0.5">
                  <button
                    @click="calendarViewMode === 'week' ? prevWeek() : prevMonth()"
                    class="p-1.5 rounded-lg text-slate-600 hover:bg-white hover:shadow-xs transition-all"
                    title="Previous"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                  </button>
                  <button
                    @click="calendarViewMode === 'week' ? nextWeek() : nextMonth()"
                    class="p-1.5 rounded-lg text-slate-600 hover:bg-white hover:shadow-xs transition-all"
                    title="Next"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  </button>
                </div>

                <button
                  @click="calendarViewMode === 'week' ? goTodayWeek() : goToday()"
                  class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-[12px] font-bold text-slate-700 hover:bg-white hover:shadow-xs transition-all"
                >
                  Today
                </button>
              </div>

              <!-- Month / Week Title -->
              <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
                {{ calendarViewMode === 'week' ? weekTitle : calendarTitle }}
              </h2>

              <!-- View Switcher -->
              <div class="flex bg-slate-100 border border-slate-200 rounded-xl p-0.5 text-[11px] font-bold">
                <button
                  @click="calendarViewMode = 'month'"
                  class="px-3 py-1 rounded-lg transition-all"
                  :class="calendarViewMode === 'month' ? 'bg-white text-[#4338ca] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                  Month
                </button>
                <button
                  @click="calendarViewMode === 'week'"
                  class="px-3 py-1 rounded-lg transition-all"
                  :class="calendarViewMode === 'week' ? 'bg-white text-[#4338ca] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                  Week
                </button>
                <button
                  @click="calendarViewMode === 'list'"
                  class="px-3 py-1 rounded-lg transition-all"
                  :class="calendarViewMode === 'list' ? 'bg-white text-[#4338ca] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                  List
                </button>
              </div>

            </div>

            <!-- 1. MONTH VIEW GRID -->
            <div v-if="calendarViewMode === 'month'" class="overflow-x-auto min-w-0">
              <div class="min-w-[580px] grid grid-cols-7 gap-px bg-slate-200 border border-slate-200 rounded-xl overflow-hidden">
                
                <!-- Day of week headers -->
                <div
                  v-for="day in daysOfWeek"
                  :key="day"
                  class="bg-slate-50 py-2.5 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wide"
                >
                  {{ day }}
                </div>

                <!-- Calendar Day Cells -->
                <div
                  v-for="(cell, idx) in calendarCells"
                  :key="idx"
                  class="bg-white min-h-[92px] p-1.5 relative transition-colors hover:bg-slate-50/70 cursor-pointer overflow-hidden flex flex-col"
                  :class="[
                    cell.month !== 'current' ? 'opacity-35 bg-slate-50/30' : '',
                    cell.dateStr === todayStr ? 'ring-2 ring-inset ring-[#4338ca] bg-indigo-50/10' : ''
                  ]"
                  @click="openAddEventModal(cell.dateStr)"
                >
                  <!-- Day Number Badge -->
                  <div class="flex items-center justify-between mb-1">
                    <span
                      class="text-[12px] font-bold w-6 h-6 rounded-full flex items-center justify-center shrink-0"
                      :class="cell.dateStr === todayStr ? 'bg-[#4338ca] text-white shadow-xs' : 'text-slate-700'"
                    >
                      {{ cell.day }}
                    </span>
                  </div>

                  <!-- Events for this day -->
                  <div class="space-y-1 flex-1 overflow-y-auto max-h-[68px] scrollbar-hide">
                    <div
                      v-for="ev in eventsOnDay(cell.dateStr)"
                      :key="ev.id"
                      class="px-1.5 py-1 text-[10px] font-bold rounded-md truncate cursor-pointer transition-transform hover:scale-[1.02] shadow-2xs border"
                      :style="{
                        backgroundColor: ev.color + '18',
                        color: ev.color,
                        borderColor: ev.color + '33'
                      }"
                      @click.stop="openDetailsModal(ev)"
                      :title="ev.title + ' (' + ev.status + ')'"
                    >
                      <span class="inline-block w-1.5 h-1.5 rounded-full mr-1 align-middle" :style="{ backgroundColor: ev.color }"></span>
                      <span class="align-middle">{{ ev.title }}</span>
                    </div>
                  </div>

                </div>

              </div>
            </div>

            <!-- 2. WEEK VIEW GRID -->
            <div v-else-if="calendarViewMode === 'week'" class="overflow-x-auto min-w-0">
              <div class="min-w-[580px] grid grid-cols-7 gap-2">
                <div v-for="wd in weekDays" :key="wd.dateStr" class="flex flex-col">
                  
                  <!-- Day Header -->
                  <div
                    class="text-center py-2 rounded-t-xl text-[11px] font-bold uppercase tracking-wide border border-b-0"
                    :class="wd.dateStr === todayStr ? 'bg-[#4338ca] text-white border-[#4338ca]' : 'bg-slate-50 text-slate-500 border-slate-200'"
                  >
                    <div>{{ wd.label }}</div>
                    <div class="text-sm font-black mt-0.5">{{ wd.dayNum }}</div>
                  </div>

                  <!-- Events column -->
                  <div
                    class="min-h-[160px] border border-slate-200 rounded-b-xl p-1.5 space-y-1 bg-white flex-1"
                    :class="wd.dateStr === todayStr ? 'border-[#4338ca]/30 bg-indigo-50/5' : ''"
                  >
                    <div
                      v-for="ev in eventsOnDay(wd.dateStr)"
                      :key="ev.id"
                      class="p-1.5 text-[10px] font-bold rounded-lg cursor-pointer truncate border shadow-2xs"
                      :style="{
                        backgroundColor: ev.color + '18',
                        color: ev.color,
                        borderColor: ev.color + '33'
                      }"
                      @click="openDetailsModal(ev)"
                    >
                      <span class="inline-block w-1.5 h-1.5 rounded-full mr-1" :style="{ backgroundColor: ev.color }"></span>
                      {{ ev.title }}
                    </div>
                    <div v-if="eventsOnDay(wd.dateStr).length === 0" class="h-full flex items-center justify-center">
                      <span class="text-[11px] text-slate-300 font-bold">—</span>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <!-- 3. LIST / AGENDA VIEW -->
            <div v-else-if="calendarViewMode === 'list'" class="space-y-3">
              <div v-if="listViewEvents.length === 0" class="py-12 text-center text-slate-400 text-[13px] font-medium">
                No events scheduled for {{ calendarTitle }}.
              </div>

              <div v-else class="space-y-2">
                <div
                  v-for="ev in listViewEvents"
                  :key="ev.id"
                  class="flex items-start justify-between gap-4 p-3.5 rounded-xl border border-slate-200/80 bg-white hover:bg-slate-50/60 transition-colors cursor-pointer"
                  @click="openDetailsModal(ev)"
                >
                  <div class="flex items-start gap-3 min-w-0">
                    <!-- Date badge -->
                    <div class="shrink-0 w-12 text-center bg-slate-50 border border-slate-200 rounded-xl py-1">
                      <div class="text-[10px] font-bold text-slate-400 uppercase">
                        {{ new Date(ev.start_date + 'T00:00:00').toLocaleString('en-US', { month: 'short' }) }}
                      </div>
                      <div class="text-base font-black text-slate-800 leading-none">
                        {{ new Date(ev.start_date + 'T00:00:00').getDate() }}
                      </div>
                    </div>

                    <!-- Content -->
                    <div class="min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[13px] font-bold text-slate-900">{{ ev.title }}</span>
                        <span v-if="ev.category_name" class="px-2 py-0.5 rounded text-[10px] font-bold" :style="{ backgroundColor: ev.color + '18', color: ev.color }">
                          {{ ev.category_name }}
                        </span>
                        <span :class="getStatusBadge(ev.status)" class="px-2 py-0.5 rounded text-[10px] font-bold border capitalize">
                          {{ ev.status }}
                        </span>
                      </div>
                      <p v-if="ev.description" class="text-[12px] text-slate-500 mt-0.5 truncate">{{ ev.description }}</p>
                      <p class="text-[11px] text-slate-400 font-medium mt-1">
                        {{ ev.start_date }} &rarr; {{ ev.end_date }} &bull; {{ ev.academic_year }} ({{ ev.semester }})
                      </p>
                    </div>
                  </div>

                  <!-- Relative days badge -->
                  <div class="shrink-0">
                    <span :class="daysUntilBadgeColor(ev.start_date)" class="px-2.5 py-1 rounded-lg text-[11px]">
                      {{ daysUntil(ev.start_date) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Right: Upcoming Events & Categories Sidebar (Span 4) -->
        <div class="lg:col-span-4 space-y-6">
          
          <!-- Upcoming Events Card -->
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-base font-black text-slate-900">Upcoming Events</h3>
              <span class="text-[11px] font-bold text-slate-400">{{ calendarStore.upcomingEvents.length }} Scheduled</span>
            </div>

            <div v-if="calendarStore.upcomingEvents.length === 0" class="py-6 text-center text-slate-400 text-[12px] font-medium">
              No upcoming events found.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="ev in calendarStore.upcomingEvents"
                :key="ev.id"
                class="flex items-start justify-between gap-3 p-3 rounded-xl border border-slate-100 hover:border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition-colors cursor-pointer"
                @click="openDetailsModal(ev)"
              >
                <div class="flex items-start gap-2.5 min-w-0">
                  <span class="w-2.5 h-2.5 rounded-full mt-1 shrink-0" :style="{ backgroundColor: ev.color }"></span>
                  <div class="min-w-0">
                    <p class="text-[13px] font-bold text-slate-900 truncate">{{ ev.title }}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-0.5">{{ ev.start_date }}</p>
                    <span v-if="ev.category_name" class="inline-block text-[10px] font-bold text-slate-400 mt-0.5">
                      {{ ev.category_name }}
                    </span>
                  </div>
                </div>

                <span :class="daysUntilBadgeColor(ev.start_date)" class="px-2 py-0.5 rounded text-[10px] shrink-0">
                  {{ daysUntil(ev.start_date) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Event Categories Filter Card -->
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-base font-black text-slate-900">Event Categories</h3>
              <button
                v-if="selectedCategoryFilter !== 'all'"
                @click="selectedCategoryFilter = 'all'"
                class="text-[11px] font-bold text-[#4338ca] hover:underline"
              >
                Show All
              </button>
            </div>

            <div class="space-y-2">
              <button
                v-for="cat in calendarStore.categories"
                :key="cat.id"
                @click="selectedCategoryFilter = selectedCategoryFilter === cat.id ? 'all' : cat.id"
                class="w-full flex items-center justify-between p-2.5 rounded-xl border transition-all text-left"
                :class="selectedCategoryFilter === cat.id ? 'bg-indigo-50 border-[#4338ca] shadow-2xs' : 'bg-slate-50/50 border-slate-100 hover:border-slate-200'"
              >
                <div class="flex items-center gap-2.5 min-w-0">
                  <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: cat.color }"></span>
                  <span class="text-[12px] font-bold text-slate-800 truncate">{{ cat.name }}</span>
                </div>
                <span class="text-[11px] font-bold text-slate-500 bg-white px-2 py-0.5 rounded border border-slate-200">
                  {{ calendarStore.events.filter(e => e.category_id === cat.id).length }}
                </span>
              </button>
            </div>
          </div>

          <!-- Real Academic Milestones Card -->
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-base font-black text-slate-900">Academic Milestones</h3>
              <span class="text-[11px] font-bold text-[#4338ca] bg-indigo-50 px-2 py-0.5 rounded">{{ settingsStore.academicYear }}</span>
            </div>

            <div class="space-y-3">
              <div v-for="(item, i) in realMilestones" :key="i" class="flex items-start gap-3 p-2.5 rounded-xl bg-slate-50/60 border border-slate-100">
                <div :class="[item.color, 'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5']">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-[12px] font-bold text-slate-800">{{ item.title }}</p>
                  <p class="text-[11px] text-slate-500 font-medium mt-0.5">{{ item.date }}</p>
                </div>
              </div>
            </div>
          </div>

        </div>

      </div>

      <!-- ── TAB 2: EVENT LIST VIEW ─────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'Event List'" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        
        <!-- Filter Toolbar -->
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/40">
          
          <!-- Search -->
          <div class="relative flex-1 min-w-[200px] sm:min-w-[260px]">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input
              v-model="eventListSearch"
              placeholder="Search by event title, category, or description..."
              class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-[12px] focus:outline-none focus:border-[#4338ca] text-slate-800 font-medium"
            />
          </div>

          <!-- Dropdowns -->
          <div class="flex flex-wrap items-center gap-2">
            <select v-model="eventListCategory" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-[12px] font-medium text-slate-700 focus:outline-none">
              <option value="">All Categories</option>
              <option v-for="cat in calendarStore.categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
            </select>

            <select v-model="eventListStatus" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-[12px] font-medium text-slate-700 focus:outline-none">
              <option value="">All Statuses</option>
              <option value="upcoming">Upcoming</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>

            <select v-model="eventListSemester" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-[12px] font-medium text-slate-700 focus:outline-none">
              <option value="">All Semesters</option>
              <option value="First Semester">First Semester</option>
              <option value="Second Semester">Second Semester</option>
              <option value="Summer Term">Summer Term</option>
            </select>

            <button
              v-if="eventListSearch || eventListCategory || eventListStatus || eventListSemester"
              @click="resetTableFilters"
              class="text-[12px] font-bold text-rose-600 hover:underline px-2"
            >
              Clear
            </button>
          </div>

        </div>

        <!-- Table -->
        <div class="overflow-x-auto min-w-0">
          <table class="w-full text-left whitespace-nowrap text-[12px]">
            <thead class="bg-slate-50 border-b border-slate-200/80 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
              <tr>
                <th class="px-5 py-3.5">Event Title</th>
                <th class="px-4 py-3.5">Category</th>
                <th class="px-4 py-3.5">Academic Period</th>
                <th class="px-4 py-3.5">Start Date</th>
                <th class="px-4 py-3.5">End Date</th>
                <th class="px-4 py-3.5">Status</th>
                <th class="px-4 py-3.5 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="tableFilteredEvents.length === 0">
                <td colspan="7" class="px-5 py-12 text-center text-slate-400 font-medium">
                  No academic events match your search filters.
                </td>
              </tr>
              <tr
                v-for="ev in tableFilteredEvents"
                :key="ev.id"
                class="hover:bg-slate-50/70 transition-colors cursor-pointer"
                @click="openDetailsModal(ev)"
              >
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-2.5 font-bold text-slate-900">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: ev.color }"></span>
                    <span>{{ ev.title }}</span>
                  </div>
                </td>
                <td class="px-4 py-3.5">
                  <span v-if="ev.category_name" class="px-2 py-0.5 rounded text-[10px] font-bold" :style="{ backgroundColor: ev.color + '18', color: ev.color }">
                    {{ ev.category_name }}
                  </span>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="px-4 py-3.5 text-slate-600 font-semibold">
                  {{ ev.academic_year }} &bull; {{ ev.semester }}
                </td>
                <td class="px-4 py-3.5 text-slate-700 font-bold">{{ ev.start_date }}</td>
                <td class="px-4 py-3.5 text-slate-700 font-bold">{{ ev.end_date }}</td>
                <td class="px-4 py-3.5">
                  <span :class="getStatusBadge(ev.status)" class="px-2.5 py-1 rounded text-[10px] font-bold border capitalize">
                    {{ ev.status }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-center" @click.stop>
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      @click="openDetailsModal(ev)"
                      class="p-1.5 rounded-lg text-slate-500 hover:text-[#4338ca] hover:bg-indigo-50 transition-colors"
                      title="View Details"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                    <button
                      @click="openEditEventModal(ev)"
                      class="p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                      title="Edit Event"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button
                      @click="confirmDeleteEvent(ev)"
                      class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                      title="Delete Event"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="p-4 border-t border-slate-100 flex items-center justify-between text-[12px] text-slate-500">
          <span>Showing {{ tableFilteredEvents.length }} of {{ calendarStore.events.length }} events</span>
        </div>

      </div>

    </template>

    <!-- ── MODAL 1: EVENT DETAILS MODAL ────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showDetailsModal && viewingEvent" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" @click="closeDetailsModal"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95">
          
          <!-- Header -->
          <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between gap-3 bg-slate-50/60">
            <div class="flex items-start gap-3">
              <span class="w-3.5 h-3.5 rounded-full mt-1.5 shrink-0" :style="{ backgroundColor: viewingEvent.color }"></span>
              <div>
                <h3 class="text-lg font-black text-slate-900">{{ viewingEvent.title }}</h3>
                <div class="flex items-center gap-2 mt-1">
                  <span v-if="viewingEvent.category_name" class="px-2 py-0.5 rounded text-[10px] font-bold" :style="{ backgroundColor: viewingEvent.color + '18', color: viewingEvent.color }">
                    {{ viewingEvent.category_name }}
                  </span>
                  <span :class="getStatusBadge(viewingEvent.status)" class="px-2 py-0.5 rounded text-[10px] font-bold border capitalize">
                    {{ viewingEvent.status }}
                  </span>
                </div>
              </div>
            </div>
            <button @click="closeDetailsModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">✕</button>
          </div>

          <!-- Body -->
          <div class="p-6 space-y-4 text-[13px]">
            <div class="grid grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-100">
              <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Start Date</span>
                <span class="font-bold text-slate-800">{{ viewingEvent.start_date }}</span>
              </div>
              <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">End Date</span>
                <span class="font-bold text-slate-800">{{ viewingEvent.end_date }}</span>
              </div>
              <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Academic Year</span>
                <span class="font-bold text-slate-800">{{ viewingEvent.academic_year }}</span>
              </div>
              <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Semester</span>
                <span class="font-bold text-slate-800">{{ viewingEvent.semester }}</span>
              </div>
            </div>

            <div v-if="!viewingEvent.all_day && (viewingEvent.start_time || viewingEvent.end_time)" class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Time Range</span>
              <span class="font-bold text-slate-800">{{ viewingEvent.start_time || '—' }} to {{ viewingEvent.end_time || '—' }}</span>
            </div>

            <div class="p-3.5 rounded-xl border border-slate-200 space-y-1">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Event Description</span>
              <p class="text-slate-700 font-medium leading-relaxed">
                {{ viewingEvent.description || 'No description provided for this academic event.' }}
              </p>
            </div>
          </div>

          <!-- Footer Actions -->
          <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
            <button
              @click="confirmDeleteEvent(viewingEvent)"
              class="px-4 py-2 text-rose-600 hover:bg-rose-50 rounded-xl text-[12px] font-bold transition-colors"
            >
              Delete Event
            </button>
            <div class="flex items-center gap-2">
              <button
                @click="openEditEventModal(viewingEvent)"
                class="px-4 py-2 bg-indigo-50 text-[#4338ca] hover:bg-indigo-100 rounded-xl text-[12px] font-bold transition-colors"
              >
                Edit Event
              </button>
              <button
                @click="closeDetailsModal"
                class="px-4 py-2 bg-slate-900 text-white rounded-xl text-[12px] font-bold transition-colors"
              >
                Close
              </button>
            </div>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- ── MODAL 2: ADD / EDIT EVENT MODAL ─────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showEventFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" @click="closeEventFormModal"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl max-h-[92vh] overflow-hidden flex flex-col border border-slate-200 animate-in fade-in zoom-in-95 my-auto">
          
          <!-- Header -->
          <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/60 shrink-0">
            <div>
              <h2 class="text-lg font-black text-slate-900">{{ isEditingEvent ? 'Edit Academic Event' : 'Schedule Academic Event' }}</h2>
              <p class="text-[12px] text-slate-500">Plan and register events into the university academic calendar.</p>
            </div>
            <button @click="closeEventFormModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">✕</button>
          </div>

          <!-- Body -->
          <div class="p-6 space-y-4 overflow-y-auto">
            
            <div v-if="eventSaveError" class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-[12px] font-bold rounded-xl">
              {{ eventSaveError }}
            </div>

            <!-- Title -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Event Title <span class="text-rose-500">*</span></label>
              <div v-if="!isCustomTitle" class="flex gap-2">
                <select
                  v-model="eventForm.title"
                  @change="if (eventForm.title === 'Other') { isCustomTitle = true; eventForm.title = '' }"
                  class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
                >
                  <option value="" disabled>Select a standard title or choose Other</option>
                  <option value="Class Start">Class Start</option>
                  <option value="Registration Period">Registration Period</option>
                  <option value="Add / Drop Period">Add / Drop Period</option>
                  <option value="Midterm Examination Period">Midterm Examination Period</option>
                  <option value="Final Examination Period">Final Examination Period</option>
                  <option value="Grade Submission Deadline">Grade Submission Deadline</option>
                  <option value="Official Term Break">Official Term Break</option>
                  <option value="Other">Other (Custom title...)</option>
                </select>
              </div>
              <div v-else class="flex gap-2">
                <input
                  v-model="eventForm.title"
                  type="text"
                  placeholder="Enter custom event title"
                  class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
                />
                <button
                  type="button"
                  @click="isCustomTitle = false; eventForm.title = ''"
                  class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-[12px] font-bold"
                >
                  Preset List
                </button>
              </div>
            </div>

            <!-- Category & Color -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1">Category <span class="text-rose-500">*</span></label>
                <select
                  v-model="eventForm.category_id"
                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
                >
                  <option value="" disabled>Select category</option>
                  <option v-for="cat in calendarStore.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>

              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1">Color Tag</label>
                <div class="flex items-center gap-2">
                  <input
                    v-model="eventForm.color"
                    type="color"
                    class="w-10 h-10 rounded-xl border border-slate-200 p-0.5 cursor-pointer shrink-0"
                  />
                  <div class="flex gap-1.5 flex-wrap">
                    <button
                      v-for="c in ['#4338ca', '#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6']"
                      :key="c"
                      type="button"
                      @click="eventForm.color = c"
                      class="w-6 h-6 rounded-full border-2 transition-transform hover:scale-110"
                      :style="{ backgroundColor: c, borderColor: eventForm.color === c ? '#1e1b4b' : 'transparent' }"
                    ></button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Academic Year & Semester -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1">Academic Year</label>
                <input
                  v-model="eventForm.academic_year"
                  type="text"
                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
                />
              </div>

              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1">Semester</label>
                <select
                  v-model="eventForm.semester"
                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
                >
                  <option value="First Semester">First Semester</option>
                  <option value="Second Semester">Second Semester</option>
                  <option value="Summer Term">Summer Term</option>
                </select>
              </div>
            </div>

            <!-- Date Range -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1">Start Date <span class="text-rose-500">*</span></label>
                <input
                  v-model="eventForm.start_date"
                  type="date"
                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
                />
              </div>

              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1">End Date <span class="text-rose-500">*</span></label>
                <input
                  v-model="eventForm.end_date"
                  type="date"
                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
                />
              </div>
            </div>

            <!-- Status -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Event Status</label>
              <select
                v-model="eventForm.status"
                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-semibold text-slate-800 focus:outline-none focus:border-[#4338ca]"
              >
                <option value="upcoming">Upcoming</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1">Description</label>
              <textarea
                v-model="eventForm.description"
                rows="3"
                placeholder="Details, locations, or notes regarding this milestone..."
                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-[13px] font-medium text-slate-800 focus:outline-none focus:border-[#4338ca] resize-none"
              ></textarea>
            </div>

          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50 shrink-0">
            <button
              @click="closeEventFormModal"
              class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-[12px] font-bold hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              @click="handleSaveEvent"
              :disabled="calendarStore.isSaving"
              class="px-6 py-2.5 rounded-xl bg-[#4338ca] hover:bg-indigo-700 text-white text-[12px] font-bold shadow-xs disabled:opacity-50"
            >
              {{ calendarStore.isSaving ? 'Saving…' : (isEditingEvent ? 'Update Event' : 'Save Event') }}
            </button>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- ── MODAL 3: DELETE CONFIRMATION MODAL ──────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showDeleteEventModal && eventToDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" @click="showDeleteEventModal = false"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6 text-center space-y-4 border border-slate-200 animate-in fade-in zoom-in-95">
          <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </div>
          <div>
            <h3 class="text-base font-black text-slate-900">Delete Academic Event?</h3>
            <p class="text-[12px] text-slate-500 mt-1">
              Are you sure you want to remove <strong>"{{ eventToDelete.title }}"</strong> from the academic calendar?
            </p>
          </div>
          <div class="flex items-center justify-center gap-3 pt-2">
            <button
              @click="showDeleteEventModal = false; eventToDelete = null"
              class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-[12px] font-bold"
            >
              Cancel
            </button>
            <button
              @click="handleDeleteEvent"
              :disabled="isDeleting"
              class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-[12px] font-bold shadow-xs disabled:opacity-50"
            >
              {{ isDeleting ? 'Deleting…' : 'Delete Event' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── MODAL 4: ADD / EDIT CATEGORY MODAL ──────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showAddCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" @click="showAddCategoryModal = false"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-6 space-y-4 border border-slate-200 animate-in fade-in zoom-in-95">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-black text-slate-900">{{ editingCategory ? 'Edit Category' : 'Create Category' }}</h3>
            <button @click="showAddCategoryModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
          </div>

          <div v-if="catSaveError" class="p-2.5 bg-rose-50 text-rose-700 text-[12px] font-bold rounded-lg">
            {{ catSaveError }}
          </div>

          <div class="space-y-3 text-[12px]">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Category Name <span class="text-rose-500">*</span></label>
              <input v-model="catForm.name" type="text" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-slate-800 font-semibold focus:outline-none focus:border-[#4338ca]" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Description</label>
              <textarea v-model="catForm.description" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-slate-800 resize-none focus:outline-none focus:border-[#4338ca]"></textarea>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Color</label>
              <div class="flex items-center gap-2">
                <input v-model="catForm.color" type="color" class="w-8 h-8 rounded-lg border border-slate-200 cursor-pointer p-0.5" />
                <input v-model="catForm.color" type="text" class="w-28 px-2 py-1.5 rounded-lg border border-slate-200 font-mono font-bold text-slate-800 uppercase" />
              </div>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Status</label>
              <select v-model="catForm.status" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-semibold text-slate-800 focus:outline-none">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button @click="showAddCategoryModal = false" class="px-4 py-2 border border-slate-200 rounded-xl text-[12px] font-bold text-slate-700">Cancel</button>
            <button @click="handleSaveCategory" class="px-5 py-2 bg-[#4338ca] text-white rounded-xl text-[12px] font-bold hover:bg-indigo-700">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── MODAL 5: DELETE CATEGORY CONFIRMATION ──────────────────────────── -->
    <Teleport to="body">
      <div v-if="showDeleteCategoryModal && deletingCategory" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-xs" @click="showDeleteCategoryModal = false"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6 text-center space-y-4 border border-slate-200 animate-in fade-in zoom-in-95">
          <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </div>
          <div>
            <h3 class="text-base font-black text-slate-900">Delete Category?</h3>
            <p class="text-[12px] text-slate-500 mt-1">
              Delete <strong>"{{ deletingCategory.name }}"</strong>? This action cannot be undone.
            </p>
          </div>
          <div v-if="catDeleteError" class="p-2.5 bg-rose-50 text-rose-700 text-[12px] font-bold rounded-lg">
            {{ catDeleteError }}
          </div>
          <div class="flex items-center justify-center gap-3 pt-2">
            <button @click="showDeleteCategoryModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-[12px] font-bold">Cancel</button>
            <button @click="handleDeleteCategory" class="px-5 py-2 bg-rose-600 text-white rounded-xl text-[12px] font-bold hover:bg-rose-700">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
