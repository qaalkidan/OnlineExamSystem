<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useCalendarStore } from '../../../store/calendarStore'
import { useSettingsStore } from '../../../store/settingsStore'

const calendarStore = useCalendarStore()
const settingsStore = useSettingsStore()

// ── Navigation ───────────────────────────────────────────
const activeTab = ref('Calendar View')
const tabs = ['Calendar View', 'Event List']
const showSettings = ref(false)

// ── Category modal state ─────────────────────────────────
const showAddCategoryModal = ref(false)
const editingCategory = ref<any>(null)
const deletingCategory = ref<any>(null)
const showDeleteConfirm = ref(false)
const saveError = ref<string | null>(null)
const deleteError = ref<string | null>(null)
const settingsTab = ref('Event Categories')
const settingsTabs = ['Event Categories', 'Other Settings']

const catForm = ref({
  name: '', description: '', color: '#6366F1',
  type: 'custom' as 'system' | 'custom',
  status: 'active' as 'active' | 'inactive',
})

function openAddModal() {
  editingCategory.value = null
  catForm.value = { name: '', description: '', color: '#6366F1', type: 'custom', status: 'active' }
  saveError.value = null
  showAddCategoryModal.value = true
}
function openEditModal(cat: any) {
  editingCategory.value = cat
  catForm.value = { name: cat.name, description: cat.description ?? '', color: cat.color, type: cat.type, status: cat.status }
  saveError.value = null
  showAddCategoryModal.value = true
}
function closeModal() { showAddCategoryModal.value = false; editingCategory.value = null; saveError.value = null }

async function saveCategory() {
  saveError.value = null
  const payload = { name: catForm.value.name.trim(), description: catForm.value.description.trim() || undefined, color: catForm.value.color, type: catForm.value.type, status: catForm.value.status }
  const result = editingCategory.value
    ? await calendarStore.updateCategory(editingCategory.value.id, payload)
    : await calendarStore.addCategory(payload)
  if (result.success) closeModal()
  else saveError.value = result.message ?? 'An error occurred.'
}
function confirmDelete(cat: any) { deletingCategory.value = cat; deleteError.value = null; showDeleteConfirm.value = true }
async function doDelete() {
  if (!deletingCategory.value) return
  const result = await calendarStore.deleteCategory(deletingCategory.value.id)
  if (result.success) { showDeleteConfirm.value = false; deletingCategory.value = null }
  else deleteError.value = result.message ?? 'Cannot delete this category.'
}

// Settings stats cards (live from store)
const settingsStats = computed(() => [
  { label: 'Total Categories',  value: String(calendarStore.stats.total),  sub: 'All event categories',      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>', color: 'text-[#4338ca]', bg: 'bg-indigo-50' },
  { label: 'Active Categories', value: String(calendarStore.stats.active), sub: 'Currently in use',          icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>', color: 'text-emerald-500', bg: 'bg-emerald-50' },
  { label: 'System Categories', value: String(calendarStore.stats.system), sub: 'Default categories',        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>', color: 'text-orange-500', bg: 'bg-orange-50' },
  { label: 'Custom Categories', value: String(calendarStore.stats.custom), sub: 'Created by admins',         icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>', color: 'text-rose-500', bg: 'bg-rose-50' },
])
watch(showSettings, v => { if (v) calendarStore.fetchCategories() })

// ── Add Event modal state ────────────────────────────────
const showAddEventModal = ref(false)
const eventSaveError = ref<string | null>(null)
const eventForm = ref({
  title: '',
  category_id: '' as string | number,
  academic_year: settingsStore.academicYear || '2025/2026',
  semester: settingsStore.semester || 'Second Semester',
  start_date: '',
  end_date: '',
  all_day: true,
  start_time: '',
  end_time: '',
  description: '',
  status: 'upcoming' as 'upcoming' | 'ongoing' | 'completed' | 'cancelled',
  color: '#6366F1',
  is_recurring: false,
})

const isCustomEventTitle = ref(false)

function openAddEventModal() {
  eventForm.value = {
    title: '',
    category_id: calendarStore.categories.length > 0 ? calendarStore.categories[0].id : '',
    academic_year: settingsStore.academicYear || '2025/2026',
    semester: settingsStore.semester || 'Second Semester',
    start_date: todayStr,
    end_date: todayStr,
    all_day: true,
    start_time: '',
    end_time: '',
    description: '',
    status: 'upcoming',
    color: '#6366F1',
    is_recurring: false,
  }
  isCustomEventTitle.value = false
  eventSaveError.value = null
  showAddEventModal.value = true
}
function closeEventModal() { showAddEventModal.value = false; eventSaveError.value = null }

async function saveEvent() {
  eventSaveError.value = null
  if (!eventForm.value.title.trim()) { eventSaveError.value = 'Event title is required.'; return }
  if (!eventForm.value.start_date)   { eventSaveError.value = 'Start date is required.'; return }
  if (!eventForm.value.end_date)     { eventSaveError.value = 'End date is required.'; return }

  const payload: any = {
    title:         eventForm.value.title.trim(),
    description:   eventForm.value.description.trim() || null,
    category_id:   eventForm.value.category_id || null,
    academic_year: eventForm.value.academic_year,
    semester:      eventForm.value.semester,
    start_date:    eventForm.value.start_date,
    end_date:      eventForm.value.end_date,
    all_day:       eventForm.value.all_day,
    start_time:    eventForm.value.all_day ? null : (eventForm.value.start_time || null),
    end_time:      eventForm.value.all_day ? null : (eventForm.value.end_time || null),
    status:        eventForm.value.status,
    color:         eventForm.value.color,
    is_recurring:  eventForm.value.is_recurring,
  }

  const result = await calendarStore.addEvent(payload)
  if (result.success) {
    closeEventModal()
  } else {
    eventSaveError.value = result.message ?? 'Failed to save event.'
  }
}

// ── Calendar grid logic ──────────────────────────────────
const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const today = new Date()
const calendarYear  = ref(today.getFullYear())
const calendarMonth = ref(today.getMonth() + 1) // 1-based

const calendarTitle = computed(() => {
  const d = new Date(calendarYear.value, calendarMonth.value - 1, 1)
  return d.toLocaleString('default', { month: 'long', year: 'numeric' })
})

function prevMonth() {
  if (calendarMonth.value === 1) { calendarMonth.value = 12; calendarYear.value-- }
  else calendarMonth.value--
}
function nextMonth() {
  if (calendarMonth.value === 12) { calendarMonth.value = 1; calendarYear.value++ }
  else calendarMonth.value++
}
function goToday() { calendarYear.value = today.getFullYear(); calendarMonth.value = today.getMonth() + 1 }

// Build calendar grid cells
const calendarCells = computed(() => {
  const y = calendarYear.value, m = calendarMonth.value
  const firstDay = new Date(y, m - 1, 1).getDay()  // 0=Sun
  const daysInMonth = new Date(y, m, 0).getDate()
  const daysInPrev  = new Date(y, m - 1, 0).getDate()
  const cells: { day: number; month: 'prev'|'current'|'next'; dateStr: string }[] = []

  for (let i = firstDay - 1; i >= 0; i--) {
    const d = daysInPrev - i
    const pm = m === 1 ? 12 : m - 1
    const py = m === 1 ? y - 1 : y
    cells.push({ day: d, month: 'prev', dateStr: `${py}-${String(pm).padStart(2,'0')}-${String(d).padStart(2,'0')}` })
  }
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push({ day: d, month: 'current', dateStr: `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}` })
  }
  const remaining = 42 - cells.length
  for (let d = 1; d <= remaining; d++) {
    const nm = m === 12 ? 1 : m + 1
    const ny = m === 12 ? y + 1 : y
    cells.push({ day: d, month: 'next', dateStr: `${ny}-${String(nm).padStart(2,'0')}-${String(d).padStart(2,'0')}` })
  }
  return cells
})

const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`

// ── Live stats cards (Calendar View) from store ──────────
const statsData = computed(() => [
  { label: 'Total Events',       value: String(calendarStore.eventStats.total),    sub: 'This Semester',      icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',           color: 'bg-indigo-50 text-[#4338ca]',   border: 'border-indigo-100' },
  { label: 'Academic Weeks',     value: '16',                                       sub: 'Active Period',      icon: 'M12 14l9-5-9-5-9 5 9 5z',                                                                          color: 'bg-emerald-50 text-emerald-600', border: 'border-emerald-100' },
  { label: 'Holidays',           value: String(calendarStore.eventStats.holidays),  sub: 'Scheduled Breaks',   icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',           color: 'bg-orange-50 text-orange-500',   border: 'border-orange-100' },
  { label: 'Exam Periods',       value: String(calendarStore.eventStats.exams),     sub: 'Scheduled Periods',  icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', color: 'bg-blue-50 text-blue-500', border: 'border-blue-100' },
  { label: 'Important Deadlines',value: String(calendarStore.eventStats.upcoming),  sub: 'Upcoming Events',    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', color: 'bg-rose-50 text-rose-500', border: 'border-rose-100' },
])

// Calendar view mode toggle
const calendarViewMode = ref<'month' | 'week' | 'list'>('month')

// Current week start (Monday) for week view
const weekStart = ref((() => {
  const d = new Date()
  const day = d.getDay() // 0=Sun
  const diff = day === 0 ? -6 : 1 - day
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
      dateStr: `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`,
      label: d.toLocaleString('default', { weekday: 'short' }),
      dayNum: d.getDate(),
    }
  })
})

const weekTitle = computed(() => {
  const end = weekDays.value[6].date
  const start = weekDays.value[0].date
  if (start.getMonth() === end.getMonth()) {
    return `${start.toLocaleString('default', { month: 'long' })} ${start.getDate()}–${end.getDate()}, ${start.getFullYear()}`
  }
  return `${start.toLocaleString('default', { month: 'short' })} ${start.getDate()} – ${end.toLocaleString('default', { month: 'short' })} ${end.getDate()}, ${start.getFullYear()}`
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

// List view — events for the current month sorted by date
const listViewEvents = computed(() => {
  const y = calendarYear.value, m = calendarMonth.value
  const pad = (n: number) => String(n).padStart(2, '0')
  const monthStr = `${y}-${pad(m)}`
  return calendarStore.events
    .filter(e => e.start_date.startsWith(monthStr) || e.end_date.startsWith(monthStr))
    .slice()
    .sort((a, b) => a.start_date.localeCompare(b.start_date))
})

// Event List tab — live events search/filter
const eventSearch = ref('')
const eventFilterCat = ref('')
const eventFilterStatus = ref('')
const eventFilterSemester = ref('')

const filteredEvents = computed(() => {
  let list = calendarStore.events
  if (eventSearch.value.trim()) {
    const q = eventSearch.value.toLowerCase()
    list = list.filter(e => e.title.toLowerCase().includes(q) || (e.category_name ?? '').toLowerCase().includes(q))
  }
  if (eventFilterCat.value) {
    list = list.filter(e => String(e.category_id) === eventFilterCat.value)
  }
  if (eventFilterStatus.value) {
    list = list.filter(e => e.status === eventFilterStatus.value)
  }
  if (eventFilterSemester.value) {
    list = list.filter(e => e.semester === eventFilterSemester.value)
  }
  return list
})

function resetFilters() {
  eventSearch.value = ''
  eventFilterCat.value = ''
  eventFilterStatus.value = ''
  eventFilterSemester.value = ''
}

// Status badge helpers
function statusBadge(status: string) {
  const map: Record<string, string> = {
    upcoming: 'bg-indigo-50 text-[#4338ca]',
    ongoing:  'bg-emerald-50 text-emerald-600',
    completed:'bg-slate-100 text-slate-500',
    cancelled:'bg-rose-50 text-rose-500',
  }
  return map[status] ?? 'bg-slate-100 text-slate-500'
}

// Days between two dates helper for "In X days"
function daysUntil(dateStr: string): string {
  const diff = Math.ceil((new Date(dateStr).getTime() - today.getTime()) / 86400000)
  if (diff < 0)  return `${Math.abs(diff)}d ago`
  if (diff === 0) return 'Today'
  return `In ${diff} days`
}
function daysUntilColor(dateStr: string): string {
  const diff = Math.ceil((new Date(dateStr).getTime() - today.getTime()) / 86400000)
  if (diff < 0) return 'text-slate-400'
  if (diff <= 7) return 'text-rose-500'
  return 'text-emerald-600'
}

// Academic summary (static — can be made dynamic later)
const academicSummary = computed(() => [
  { title: 'Academic Year',       date: settingsStore.academicYear || '2025/2026', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', color: 'text-[#4338ca] bg-indigo-50' },
  { title: settingsStore.semester || 'Second Semester', date: 'Active Semester', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', color: 'text-[#4338ca] bg-indigo-50' },
  { title: 'Registration Period', date: 'Apr 20, 2025 - Apr 30, 2025', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', color: 'text-emerald-500 bg-emerald-50' },
  { title: 'Classes Period',      date: 'May 5, 2025 - Aug 15, 2025', icon: 'M12 14l9-5-9-5-9 5 9 5z', color: 'text-emerald-500 bg-emerald-50' },
  { title: 'Midterm Exams',       date: 'May 12, 2025 - May 16, 2025', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', color: 'text-rose-500 bg-rose-50' },
  { title: 'Final Exams',         date: 'May 28, 2025 - Jun 10, 2025', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', color: 'text-blue-500 bg-blue-50' },
])

const eventListStats = computed(() => [
  { label: 'Total Events',       value: String(calendarStore.eventStats.total),    sub: 'This Semester',      icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',           color: 'bg-indigo-50 text-[#4338ca]',   border: 'border-indigo-100' },
  { label: 'Academic Weeks',     value: '16',                                       sub: 'Active Period',      icon: 'M12 14l9-5-9-5-9 5 9 5z',                                                                          color: 'bg-emerald-50 text-emerald-600', border: 'border-emerald-100' },
  { label: 'Holidays',           value: String(calendarStore.eventStats.holidays),  sub: 'Scheduled Breaks',   icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',           color: 'bg-orange-50 text-orange-500',   border: 'border-orange-100' },
  { label: 'Exam Periods',       value: String(calendarStore.eventStats.exams),     sub: 'Scheduled Periods',  icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', color: 'bg-blue-50 text-blue-500', border: 'border-blue-100' },
  { label: 'Important Deadlines',value: String(calendarStore.eventStats.upcoming),  sub: 'Upcoming Events',    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2m-6 9l2 2 4-4', color: 'bg-rose-50 text-rose-500', border: 'border-rose-100' },
])

const statusSummary = computed(() => [
  { name: 'Upcoming', count: calendarStore.events.filter(e => e.status === 'upcoming').length, color: 'bg-indigo-500' },
  { name: 'Ongoing', count: calendarStore.events.filter(e => e.status === 'ongoing').length, color: 'bg-emerald-500' },
  { name: 'Completed', count: calendarStore.events.filter(e => e.status === 'completed').length, color: 'bg-slate-400' },
  { name: 'Cancelled', count: calendarStore.events.filter(e => e.status === 'cancelled').length, color: 'bg-rose-500' },
])

const sidebarCategories = computed(() => {
  return calendarStore.categories.map(cat => ({
    id: cat.id,
    name: cat.name,
    color: cat.color,
    count: calendarStore.events.filter(e => e.category_id === cat.id).length
  }))
})

// Load data on mount
onMounted(() => {
  settingsStore.fetchSettings()
  calendarStore.fetchEvents()
  calendarStore.fetchCategories()
})
</script>

<template>
  <div class="space-y-6 min-w-0 w-full pb-10">
    <template v-if="!showSettings">
    <!-- Page Actions -->
    <div class="flex items-center justify-end">
      <div class="flex items-center gap-3">
        <span class="px-3 sm:px-4 py-1.5 sm:py-2 bg-indigo-50 text-[#4338ca] font-bold text-[11px] sm:text-[12px] rounded-full shadow-sm">
          {{ settingsStore.formattedAcademicTerm }}
        </span>
      </div>
    </div>

    <!-- Tabs and Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 gap-3">
      <div class="flex items-center gap-4 sm:gap-6 overflow-x-auto min-w-0 pb-1 sm:pb-0">
        <button v-for="tab in tabs" :key="tab" @click="activeTab = tab"
          :class="[activeTab === tab ? 'text-[#4338ca] border-b-2 border-[#4338ca] font-bold' : 'text-slate-500 font-medium hover:text-slate-700', 'pb-3 text-[13px] transition-colors whitespace-nowrap shrink-0']">
          {{ tab }}
        </button>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3 pb-3">
        <!-- Calendar View Buttons -->
        <template v-if="activeTab === 'Calendar View'">
          <button @click="showSettings = true" class="flex items-center gap-2 px-3 py-1.5 bg-white border border-slate-200 text-slate-600 font-bold rounded-lg text-[12px] hover:bg-slate-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg> Calendar Settings
          </button>
          <button @click="openAddEventModal" class="flex items-center gap-2 px-3 py-1.5 bg-[#4338ca] text-white font-bold rounded-lg text-[12px] hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add Event
          </button>
        </template>
        <!-- Event List Buttons -->
        <template v-else-if="activeTab === 'Event List'">
          <button @click="openAddEventModal" class="flex items-center gap-2 px-3 py-1.5 bg-[#4338ca] text-white font-bold rounded-lg text-[12px] hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add Event
          </button>
          <button class="flex items-center gap-2 px-3 py-1.5 bg-white border border-slate-200 text-slate-600 font-bold rounded-lg text-[12px] hover:bg-slate-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg> Export
            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </button>
        </template>
      </div>
    </div>

    <!-- CALENDAR VIEW -->
    <template v-if="activeTab === 'Calendar View'">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
        <div v-for="(stat, i) in statsData" :key="i" class="bg-white border rounded-xl shadow-sm p-4 flex items-start gap-4">
          <div :class="[stat.color, stat.border, 'w-10 h-10 border rounded-lg flex items-center justify-center shrink-0']">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"></path></svg>
          </div>
          <div>
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">{{ stat.label }}</p>
            <p class="text-[20px] font-black text-slate-800 leading-none mt-1">{{ stat.value }}</p>
            <p class="text-[10px] text-slate-400 font-medium mt-1">{{ stat.sub }}</p>
          </div>
        </div>
      </div>

      <!-- Main Layout Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column (Calendar & Table) -->
        <div class="lg:col-span-8 space-y-6">
          
          <!-- Calendar Container -->
          <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 sm:mb-6">
              <div class="flex items-center gap-2">
                <div class="flex bg-slate-50 border border-slate-200 rounded-lg p-0.5">
                  <button @click="calendarViewMode === 'week' ? prevWeek() : prevMonth()" class="px-2 py-1 rounded text-slate-500 hover:bg-white hover:shadow-sm hover:text-slate-700 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                  <button @click="calendarViewMode === 'week' ? nextWeek() : nextMonth()" class="px-2 py-1 rounded text-slate-500 hover:bg-white hover:shadow-sm hover:text-slate-700 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                </div>
                <button @click="calendarViewMode === 'week' ? goTodayWeek() : goToday()" class="px-3 py-1.5 border border-slate-200 bg-slate-50 rounded-lg text-[12px] font-bold text-slate-600 hover:bg-white hover:shadow-sm transition-all">Today</button>
              </div>

              <h2 class="text-[16px] sm:text-[18px] font-bold text-slate-800 order-last sm:order-none w-full sm:w-auto text-center sm:text-left">
                {{ calendarViewMode === 'week' ? weekTitle : calendarTitle }}
              </h2>

              <div class="flex bg-slate-50 border border-slate-200 rounded-lg p-0.5">
                <button @click="calendarViewMode = 'month'"
                  :class="calendarViewMode === 'month' ? 'bg-white shadow-sm text-[#4338ca]' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'"
                  class="px-2.5 sm:px-3 py-1 rounded-md text-[11px] sm:text-[12px] font-bold transition-colors">Month</button>
                <button @click="calendarViewMode = 'week'"
                  :class="calendarViewMode === 'week' ? 'bg-white shadow-sm text-[#4338ca]' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'"
                  class="px-2.5 sm:px-3 py-1 rounded-md text-[11px] sm:text-[12px] font-bold transition-colors">Week</button>
                <button @click="calendarViewMode = 'list'"
                  :class="calendarViewMode === 'list' ? 'bg-white shadow-sm text-[#4338ca]' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'"
                  class="px-2.5 sm:px-3 py-1 rounded-md text-[11px] sm:text-[12px] font-bold transition-colors">List</button>
              </div>
            </div>

            <!-- ── MONTH VIEW ── -->
            <div v-if="calendarViewMode === 'month'" class="overflow-x-auto min-w-0 w-full">
              <div class="min-w-[540px] sm:min-w-0 grid grid-cols-7 gap-px bg-slate-200 border border-slate-200 rounded-lg overflow-hidden">
                <div v-for="day in days" :key="day" class="bg-white py-2 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                  {{ day }}
                </div>

                <!-- Dynamic calendar cells -->
                <div v-for="(cell, idx) in calendarCells" :key="idx"
                  :class="[
                    'bg-white h-20 p-1.5 relative overflow-hidden',
                    cell.month !== 'current' ? 'opacity-40' : '',
                    cell.dateStr === todayStr ? 'ring-2 ring-inset ring-[#4338ca]' : ''
                  ]">
                  <span :class="[
                    'text-[12px] font-bold flex items-center justify-center w-6 h-6 rounded-full',
                    cell.dateStr === todayStr ? 'bg-[#4338ca] text-white' : 'text-slate-700'
                  ]">{{ cell.day }}</span>
                  <!-- Events on this day -->
                  <div v-for="ev in calendarStore.eventsOnDay(cell.dateStr).slice(0,1)" :key="ev.id"
                    class="mt-0.5 px-1.5 py-0.5 text-[9px] font-bold rounded truncate cursor-pointer"
                    :style="{ backgroundColor: ev.color + '22', color: ev.color }">
                    <span class="inline-block w-1 h-1 rounded-full mr-1 align-middle" :style="{ backgroundColor: ev.color }"></span>
                    <span class="align-middle">{{ ev.title }}</span>
                  </div>
                  <div v-if="calendarStore.eventsOnDay(cell.dateStr).length > 1" class="text-[9px] text-slate-400 font-medium mt-0.5 pl-1">
                    +{{ calendarStore.eventsOnDay(cell.dateStr).length - 1 }} more
                  </div>
                </div>
              </div>
            </div>

            <!-- ── WEEK VIEW ── -->
            <template v-else-if="calendarViewMode === 'week'">
              <!-- 7-column week grid -->
              <div class="overflow-x-auto min-w-0 w-full">
                <div class="min-w-[540px] sm:min-w-0 grid grid-cols-7 gap-1">
                  <div v-for="wd in weekDays" :key="wd.dateStr" class="flex flex-col">
                    <!-- Day header -->
                    <div :class="[
                      'text-center py-2 rounded-t-lg text-[11px] font-bold uppercase tracking-wide border border-b-0',
                      wd.dateStr === todayStr ? 'bg-[#4338ca] text-white border-[#4338ca]' : 'bg-slate-50 text-slate-500 border-slate-200'
                    ]">
                      <div>{{ wd.label }}</div>
                      <div class="text-[15px] font-black mt-0.5">{{ wd.dayNum }}</div>
                    </div>
                    <!-- Events column -->
                    <div :class="[
                      'min-h-[120px] border border-slate-200 rounded-b-lg p-1 space-y-1 bg-white',
                      wd.dateStr === todayStr ? 'border-[#4338ca]/30' : ''
                    ]">
                      <div v-for="ev in calendarStore.eventsOnDay(wd.dateStr)" :key="ev.id"
                        class="px-1.5 py-1 text-[10px] font-bold rounded cursor-pointer truncate"
                        :style="{ backgroundColor: ev.color + '22', color: ev.color }">
                        <span class="inline-block w-1.5 h-1.5 rounded-full mr-1 align-middle" :style="{ backgroundColor: ev.color }"></span>
                        {{ ev.title }}
                      </div>
                      <div v-if="calendarStore.eventsOnDay(wd.dateStr).length === 0" class="h-full flex items-center justify-center">
                        <span class="text-[10px] text-slate-300">—</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- ── LIST VIEW ── -->
            <template v-else-if="calendarViewMode === 'list'">
              <div v-if="listViewEvents.length === 0" class="py-12 text-center text-[13px] text-slate-400">
                No events found for {{ calendarTitle }}.
              </div>
              <div v-else class="space-y-2">
                <div v-for="ev in listViewEvents" :key="ev.id"
                  class="flex items-start gap-4 p-3 rounded-lg border border-slate-100 hover:bg-slate-50 transition-colors">
                  <!-- Date badge -->
                  <div class="shrink-0 w-12 text-center">
                    <div class="text-[10px] font-bold text-slate-400 uppercase">
                      {{ new Date(ev.start_date + 'T00:00').toLocaleString('default', { month: 'short' }) }}
                    </div>
                    <div :class="[
                      'text-[20px] font-black leading-none',
                      ev.start_date === todayStr ? 'text-[#4338ca]' : 'text-slate-700'
                    ]">{{ new Date(ev.start_date + 'T00:00').getDate() }}</div>
                  </div>
                  <!-- Color bar -->
                  <div class="w-1 self-stretch rounded-full shrink-0" :style="{ backgroundColor: ev.color }"></div>
                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <p class="text-[13px] font-bold text-slate-800">{{ ev.title }}</p>
                      <span v-if="ev.category_name" class="text-[10px] font-bold px-2 py-0.5 rounded"
                        :style="{ backgroundColor: (ev.category_color ?? ev.color) + '22', color: ev.category_color ?? ev.color }">{{ ev.category_name }}</span>
                      <span :class="[statusBadge(ev.status), 'text-[10px] font-bold px-2 py-0.5 rounded capitalize']">{{ ev.status }}</span>
                    </div>
                    <p v-if="ev.description" class="text-[11px] text-slate-500 mt-1 truncate">{{ ev.description }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                      {{ ev.start_date }}{{ ev.end_date !== ev.start_date ? ` → ${ev.end_date}` : '' }}
                    </p>
                  </div>
                  <!-- Days until -->
                  <span class="shrink-0 text-[11px] font-bold" :class="daysUntilColor(ev.start_date)">{{ daysUntil(ev.start_date) }}</span>
                </div>
              </div>
            </template>
          </div>


          <!-- Events Table -->
          <div class="bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 sm:p-4 border-b border-slate-100">
              <h3 class="text-[15px] font-bold text-slate-800">All Academic Events</h3>
              <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-none">
                  <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                  <input v-model="eventSearch" placeholder="Search events..." class="w-full sm:w-auto pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-[12px] focus:outline-none focus:border-[#4338ca] text-slate-700 min-w-[160px] sm:min-w-[200px]" />
                </div>
                <select v-model="eventFilterCat" class="flex-1 sm:flex-none px-3 py-2 bg-white border border-slate-200 rounded-lg text-[12px] text-slate-600 focus:outline-none font-medium">
                  <option value="">All Categories</option>
                  <option v-for="cat in calendarStore.categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
                </select>
              </div>
            </div>

            <!-- Loading -->
            <div v-if="calendarStore.isLoadingEvents" class="flex items-center justify-center py-12">
              <svg class="w-5 h-5 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              <span class="ml-2 text-[13px] text-slate-400">Loading events…</span>
            </div>

            <div v-else class="overflow-x-auto min-w-0 w-full">
              <table class="w-full text-left whitespace-nowrap min-w-max">
                <thead>
                  <tr class="border-b border-slate-100 bg-white">
                    <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Event Title</th>
                    <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Category</th>
                    <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Start Date</th>
                    <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">End Date</th>
                    <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="filteredEvents.length === 0">
                    <td colspan="7" class="px-4 py-10 text-center text-[13px] text-slate-400">No events yet. Click "+ Add Event" to create one.</td>
                  </tr>
                  <tr v-for="ev in filteredEvents" :key="ev.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: ev.color }"></span>
                        <p class="text-[13px] font-bold text-slate-800">{{ ev.title }}</p>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span v-if="ev.category_name" class="text-[10px] font-bold px-2 py-1 rounded" :style="{ backgroundColor: (ev.category_color ?? ev.color) + '22', color: ev.category_color ?? ev.color }">{{ ev.category_name }}</span>
                      <span v-else class="text-[10px] text-slate-400">—</span>
                    </td>
                    <td class="px-4 py-4 text-[12px] font-semibold text-slate-600">{{ ev.start_date }}</td>
                    <td class="px-4 py-4 text-[12px] font-semibold text-slate-600">{{ ev.end_date }}</td>
                    <td class="px-4 py-4 text-[12px] text-slate-500 truncate max-w-[180px]">{{ ev.description ?? '—' }}</td>
                    <td class="px-4 py-4"><span :class="[statusBadge(ev.status), 'text-[10px] font-bold px-2 py-1 rounded capitalize']">{{ ev.status }}</span></td>
                    <td class="px-4 py-4">
                      <div class="flex items-center justify-center gap-2">
                        <button class="text-blue-500 hover:bg-blue-50 p-1 rounded transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                        <button @click="calendarStore.deleteEvent(ev.id)" class="text-rose-500 hover:bg-rose-50 p-1 rounded transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-slate-100 bg-white">
              <p class="text-[12px] text-slate-500 font-medium text-center sm:text-left">Showing {{ filteredEvents.length }} of {{ calendarStore.events.length }} events</p>
              <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                <div class="flex items-center gap-1">
                  <button class="w-7 h-7 rounded flex items-center justify-center text-slate-400 hover:bg-slate-50"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                  <button class="w-7 h-7 rounded bg-[#4338ca] text-white text-[12px] font-bold">1</button>
                  <button class="w-7 h-7 rounded text-slate-600 hover:bg-slate-50 text-[12px] font-bold">2</button>
                  <button class="w-7 h-7 rounded text-slate-600 hover:bg-slate-50 text-[12px] font-bold">3</button>
                  <span class="text-slate-400 text-[12px]">...</span>
                  <button class="w-7 h-7 rounded text-slate-600 hover:bg-slate-50 text-[12px] font-bold">5</button>
                  <button class="w-7 h-7 rounded flex items-center justify-center text-slate-600 hover:bg-slate-50"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[12px] text-slate-500 font-medium">Rows per page:</span>
                  <select class="text-[12px] border border-slate-200 rounded px-2 py-1 text-slate-600 focus:outline-none">
                    <option>10</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column (Sidebar Widgets) -->
        <div class="lg:col-span-4 space-y-6">
          <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-5">
              <h3 class="text-[15px] font-bold text-slate-800">Upcoming Events</h3>
              <button class="text-[12px] font-bold text-[#4338ca] hover:underline">View All</button>
            </div>
            <div class="space-y-4">
              <div v-if="calendarStore.upcomingEvents.length === 0" class="text-[13px] text-slate-400 text-center py-4">No upcoming events.</div>
              <div v-for="ev in calendarStore.upcomingEvents" :key="ev.id" class="flex items-start justify-between relative pl-4 border-l border-slate-100">
                <!-- Timeline Dot -->
                <span class="w-2 h-2 rounded-full absolute -left-[4px] top-1.5 ring-2 ring-white" :style="{ backgroundColor: ev.color }"></span>
                <div>
                  <p class="text-[13px] font-bold text-slate-800">{{ ev.title }}</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">{{ ev.start_date }}</p>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded whitespace-nowrap mt-1" :class="daysUntilColor(ev.start_date)" :style="{ backgroundColor: ev.color + '18' }">{{ daysUntil(ev.start_date) }}</span>
              </div>
            </div>
          </div>

          <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
            <h3 class="text-[15px] font-bold text-slate-800 mb-5">Event Categories</h3>
            <div class="space-y-3">
              <div v-for="(cat, i) in sidebarCategories" :key="cat.id || i" class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-7 h-7 rounded flex items-center justify-center shrink-0" :style="{ backgroundColor: cat.color + '22', color: cat.color }">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                  </div>
                  <span class="text-[13px] font-bold text-slate-700">{{ cat.name }}</span>
                </div>
                <span class="text-[12px] font-bold text-slate-500 bg-slate-50 border border-slate-100 px-2 py-0.5 rounded">{{ cat.count }}</span>
              </div>
            </div>
          </div>

          <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex flex-col h-auto">
            <div class="flex items-center justify-between mb-5">
              <h3 class="text-[15px] font-bold text-slate-800">Academic Year Summary</h3>
              <span class="text-[11px] font-bold text-[#4338ca] bg-indigo-50 px-2 py-1 rounded">{{ settingsStore.academicYear }}</span>
            </div>
            <div class="space-y-4 flex-1">
              <div v-for="(block, i) in academicSummary" :key="i" class="flex items-start gap-3">
                <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5', block.color]">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="block.icon"></path></svg>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-800">{{ block.title }}</p>
                  <p class="text-[11px] text-slate-500 font-medium">{{ block.date }}</p>
                </div>
              </div>
            </div>
            <router-link to="/admin/settings" class="block text-center w-full mt-6 py-2.5 bg-[#4338ca] hover:bg-indigo-700 text-white text-[13px] font-bold rounded-lg transition-colors">
              Manage Academic Year
            </router-link>
          </div>
        </div>
      </div>
    </template>

    <!-- EVENT LIST VIEW -->
    <template v-else-if="activeTab === 'Event List'">
      
      <div class="mb-2">
        <h2 class="text-[18px] font-bold text-slate-800">All Academic Events</h2>
        <p class="text-[13px] text-slate-500 mt-0.5">View, search and manage all academic events in the system.</p>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
        <div v-for="(stat, i) in eventListStats" :key="i" class="bg-white border rounded-xl shadow-sm p-4 flex items-start gap-4">
          <div :class="[stat.color, stat.border, 'w-10 h-10 border rounded-lg flex items-center justify-center shrink-0']">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"></path></svg>
          </div>
          <div>
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">{{ stat.label }}</p>
            <p class="text-[20px] font-black text-slate-800 leading-none mt-1">{{ stat.value }}</p>
            <p class="text-[10px] text-slate-400 font-medium mt-1">{{ stat.sub }}</p>
          </div>
        </div>
      </div>

      <!-- Main Layout Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column (Table) -->
        <div class="lg:col-span-9 space-y-6">
          <div class="bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
            
            <!-- Filters Toolbar -->
            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 p-3.5 sm:p-4 border-b border-slate-100">
              <div class="relative flex-1 min-w-[180px] sm:min-w-[200px] w-full sm:w-auto">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input v-model="eventSearch" placeholder="Search events by title or description..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-[12px] focus:outline-none focus:border-[#4338ca] text-slate-700" />
              </div>
              <select v-model="eventFilterCat" class="flex-1 sm:flex-none px-3 py-2 bg-white border border-slate-200 rounded-lg text-[12px] text-slate-600 focus:outline-none font-medium min-w-[130px]">
                <option value="">All Categories</option>
                <option v-for="cat in calendarStore.categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
              </select>
              <select v-model="eventFilterStatus" class="flex-1 sm:flex-none px-3 py-2 bg-white border border-slate-200 rounded-lg text-[12px] text-slate-600 focus:outline-none font-medium min-w-[110px]">
                <option value="">All Status</option>
                <option value="upcoming">Upcoming</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
              <select v-model="eventFilterSemester" class="flex-1 sm:flex-none px-3 py-2 bg-white border border-slate-200 rounded-lg text-[12px] text-slate-600 focus:outline-none font-medium min-w-[130px]">
                <option value="">All Semesters</option>
                <option value="First Semester">First Semester</option>
                <option value="Second Semester">Second Semester</option>
                <option value="Summer">Summer</option>
              </select>
              <button @click="resetFilters" class="flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 text-slate-600 font-bold rounded-lg text-[12px] hover:bg-slate-50 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Reset
              </button>
            </div>
            
            <div class="overflow-x-auto min-w-0 w-full">
              <table class="w-full text-left whitespace-nowrap min-w-max">
                <thead>
                  <tr class="border-b border-slate-100 bg-indigo-50/30">
                    <th class="px-5 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Event Title</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Category</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Start Date</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">End Date</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Description</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Status</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="filteredEvents.length === 0">
                    <td colspan="7" class="px-5 py-10 text-center text-[13px] text-slate-400 font-medium">No events found.</td>
                  </tr>
                  <tr v-for="event in filteredEvents" :key="event.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                    <td class="px-5 py-4">
                      <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: event.color }"></span>
                        <p class="text-[13px] font-bold text-slate-800">{{ event.title }}</p>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span v-if="event.category_name" class="text-[10px] font-bold px-2 py-1 rounded" :style="{ backgroundColor: (event.category_color ?? event.color) + '22', color: event.category_color ?? event.color }">{{ event.category_name }}</span>
                      <span v-else class="text-[10px] text-slate-400">—</span>
                    </td>
                    <td class="px-4 py-4 text-[12px] font-semibold text-slate-600">{{ event.start_date }}</td>
                    <td class="px-4 py-4 text-[12px] font-semibold text-slate-600">{{ event.end_date }}</td>
                    <td class="px-4 py-4 text-[12px] text-slate-500 max-w-[200px] truncate">{{ event.description ?? '—' }}</td>
                    <td class="px-4 py-4"><span :class="[statusBadge(event.status), 'text-[10px] font-bold px-2 py-1 rounded capitalize']">{{ event.status }}</span></td>
                    <td class="px-4 py-4">
                      <div class="flex items-center justify-center gap-2">
                        <button @click="calendarStore.deleteEvent(event.id)" class="text-rose-500 hover:bg-rose-50 p-1.5 rounded transition-colors" title="Delete event"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-5 py-3 sm:py-4 border-t border-slate-100 bg-white">
              <p class="text-[13px] text-slate-500 font-medium text-center sm:text-left">Showing 1 to 10 of 24 events</p>
              <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                <div class="flex items-center gap-1.5">
                  <button class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-slate-50 border border-slate-200"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                  <button class="w-8 h-8 rounded-lg bg-[#4338ca] text-white text-[13px] font-bold">1</button>
                  <button class="w-8 h-8 rounded-lg text-slate-600 hover:bg-slate-50 border border-slate-200 text-[13px] font-bold transition-colors">2</button>
                  <button class="w-8 h-8 rounded-lg text-slate-600 hover:bg-slate-50 border border-slate-200 text-[13px] font-bold transition-colors">3</button>
                  <span class="text-slate-400 text-[13px] px-1">...</span>
                  <button class="w-8 h-8 rounded-lg text-slate-600 hover:bg-slate-50 border border-slate-200 text-[13px] font-bold transition-colors">5</button>
                  <button class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-600 hover:bg-slate-50 border border-slate-200 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[13px] text-slate-500 font-medium">Rows per page:</span>
                  <select class="text-[13px] border border-slate-200 rounded-lg px-2 py-1.5 text-slate-700 font-bold focus:outline-none">
                    <option>10</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column (Sidebar Widgets) -->
        <div class="lg:col-span-3 space-y-6">
          <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-6">
            <h3 class="text-[15px] font-bold text-slate-800 mb-6">Event Categories</h3>
            <div class="space-y-4">
              <div v-for="(cat, i) in sidebarCategories" :key="cat.id || i" class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :style="{ backgroundColor: cat.color + '22', color: cat.color }">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                  </div>
                  <span class="text-[13px] font-bold text-slate-700">{{ cat.name }}</span>
                </div>
                <span class="text-[12px] font-bold text-slate-600 bg-slate-50 border border-slate-100 px-2 py-0.5 rounded">{{ cat.count }}</span>
              </div>
            </div>
          </div>

          <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-6">
            <h3 class="text-[15px] font-bold text-slate-800 mb-6">Status</h3>
            <div class="space-y-4">
              <div v-for="(stat, i) in statusSummary" :key="i" class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <span :class="['w-1.5 h-1.5 rounded-full', stat.color]"></span>
                  <span class="text-[13px] font-bold text-slate-700">{{ stat.name }}</span>
                </div>
                <span class="text-[12px] font-bold text-slate-600 bg-slate-50 border border-slate-100 px-2 py-0.5 rounded">{{ stat.count }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
    </template>

    <!-- SETTINGS VIEW -->
    <template v-else>
      <div class="flex flex-col md:flex-row md:items-center justify-end gap-4">
        <button @click="showSettings = false" class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-600 font-bold rounded-lg text-[13px] hover:bg-slate-50 transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Calendar
        </button>
      </div>

      <!-- Settings Tabs -->
      <div class="flex items-center gap-6 sm:gap-8 border-b border-slate-200 mt-2 overflow-x-auto min-w-0">
        <button v-for="tab in settingsTabs" :key="tab" @click="settingsTab = tab"
          :class="[settingsTab === tab ? 'text-[#4338ca] border-b-2 border-[#4338ca] font-bold' : 'text-slate-500 font-medium hover:text-slate-700', 'pb-3 text-[13px] transition-colors flex items-center gap-2 whitespace-nowrap shrink-0']">
          <svg v-if="tab === 'Event Categories'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
          {{ tab }}
        </button>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mt-4">
        <div v-for="(stat, i) in settingsStats" :key="i" class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5 flex items-start gap-4">
          <div :class="[stat.color, stat.bg, 'w-12 h-12 rounded-xl flex items-center justify-center shrink-0']" v-html="`<svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'>${stat.icon}</svg>`">
          </div>
          <div>
            <p class="text-[12px] font-bold text-slate-500">{{ stat.label }}</p>
            <p class="text-[24px] font-black text-slate-800 leading-tight mt-0.5">{{ stat.value }}</p>
            <p class="text-[11px] text-slate-400 font-medium mt-1">{{ stat.sub }}</p>
          </div>
        </div>
      </div>

      <!-- Main Layout Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Column (Table) -->
        <div class="lg:col-span-12 space-y-6">
          <div class="bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5 border-b border-slate-100">
              <div>
                <h3 class="text-[15px] font-bold text-slate-800">Event Categories</h3>
                <p class="text-[12px] text-slate-500 mt-0.5">Manage event categories used in the academic calendar.</p>
              </div>
              <button @click="openAddModal" class="px-4 py-2 bg-[#4338ca] text-white font-bold rounded-lg text-[13px] hover:bg-indigo-700 transition-colors shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add Category
              </button>
            </div>

            <!-- Loading state -->
            <div v-if="calendarStore.isLoading" class="flex items-center justify-center py-16">
              <div class="flex items-center gap-3 text-slate-400">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span class="text-[13px] font-medium">Loading categories…</span>
              </div>
            </div>

            <div v-else class="overflow-x-auto min-w-0 w-full">
              <table class="w-full text-left whitespace-nowrap min-w-max">
                <thead>
                  <tr class="border-b border-slate-100 bg-white">
                    <th class="px-5 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Category Name</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Description</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Color</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Status</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider">Type</th>
                    <th class="px-4 py-4 text-[11px] font-black text-slate-500 capitalize tracking-wider text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Empty state -->
                  <tr v-if="calendarStore.categories.length === 0">
                    <td colspan="6" class="px-5 py-12 text-center text-[13px] text-slate-400 font-medium">No categories found. Click "+ Add Category" to create one.</td>
                  </tr>
                  <tr v-for="cat in calendarStore.categories" :key="cat.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                    <td class="px-5 py-4">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded flex items-center justify-center shrink-0" :style="{ backgroundColor: cat.color + '22', color: cat.color }">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        </div>
                        <span class="text-[13px] font-bold text-slate-800">{{ cat.name }}</span>
                      </div>
                    </td>
                    <td class="px-4 py-4 text-[12px] text-slate-500 whitespace-normal min-w-[200px] leading-relaxed max-w-xs">{{ cat.description ?? '—' }}</td>
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: cat.color }"></span>
                        <span class="text-[12px] font-bold text-slate-600">{{ cat.color }}</span>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <span :class="cat.status === 'active' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-500 bg-slate-100'" class="text-[10px] font-bold px-2.5 py-1 rounded capitalize">{{ cat.status }}</span>
                    </td>
                    <td class="px-4 py-4">
                      <span :class="cat.type === 'system' ? 'text-blue-600 bg-blue-50' : 'text-indigo-600 bg-indigo-50'" class="text-[10px] font-bold px-2.5 py-1 rounded capitalize">{{ cat.type }}</span>
                    </td>
                    <td class="px-4 py-4">
                      <div class="flex items-center justify-center gap-2">
                        <button @click="openEditModal(cat)" title="Edit" class="text-blue-500 hover:bg-blue-50 p-1.5 rounded transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                        <button @click="confirmDelete(cat)" :title="cat.type === 'system' ? 'System categories cannot be deleted' : 'Delete'" :disabled="cat.type === 'system'" :class="cat.type === 'system' ? 'text-slate-300 cursor-not-allowed' : 'text-rose-500 hover:bg-rose-50'" class="p-1.5 rounded transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-5 py-3 sm:py-4 border-t border-slate-100 bg-white">
              <p class="text-[13px] text-slate-500 font-medium text-center sm:text-left">Showing {{ calendarStore.categories.length }} of {{ calendarStore.categories.length }} categories</p>
              <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                <div class="flex items-center gap-1.5">
                  <button class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 border border-slate-200 hover:bg-slate-50"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                  <button class="w-8 h-8 rounded-lg bg-[#4338ca] text-white text-[13px] font-bold">1</button>
                  <button class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 border border-slate-200 hover:bg-slate-50"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[13px] text-slate-500 font-medium">Rows per page:</span>
                  <select class="text-[13px] border border-slate-200 rounded-lg px-2 py-1.5 text-slate-700 font-bold focus:outline-none">
                    <option>10</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 flex items-start gap-4">
            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-500 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
              <h4 class="text-[14px] font-bold text-slate-800">About Event Categories</h4>
              <p class="text-[13px] text-slate-500 mt-1 leading-relaxed">Event categories help you organize and identify different types of events in your academic calendar. System categories cannot be deleted but you can edit custom categories.</p>
            </div>
          </div>
        </div>


      </div>
    </template>

    <!-- ADD / EDIT CATEGORY MODAL -->
    <Teleport to="body">
      <div v-if="showAddCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-800/40 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-[95vw] sm:max-w-lg max-h-[90vh] overflow-hidden flex flex-col my-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between px-5 sm:px-6 py-4 sm:py-5 border-b border-slate-100">
            <div>
              <h2 class="text-[17px] sm:text-[18px] font-bold text-slate-800">{{ editingCategory ? 'Edit Category' : 'Add New Category' }}</h2>
              <p class="text-[12px] text-slate-500 mt-0.5">{{ editingCategory ? 'Update the event category details.' : 'Create a new event category for the calendar.' }}</p>
            </div>
            <button @click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 sm:p-6 space-y-4 sm:space-y-5 overflow-y-auto max-h-[calc(90vh-140px)]">
            <!-- Error Alert -->
            <div v-if="saveError" class="flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-600 rounded-lg px-4 py-3 text-[13px] font-medium">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              {{ saveError }}
            </div>

            <!-- Category Name -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Category Name <span class="text-rose-500">*</span></label>
              <input v-model="catForm.name" type="text" placeholder="Enter category name" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] text-slate-700 transition-all placeholder:text-slate-400" />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Description</label>
              <textarea v-model="catForm.description" rows="3" placeholder="Enter category description" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] text-slate-700 transition-all placeholder:text-slate-400 resize-none"></textarea>
              <div class="text-right text-[11px] text-slate-400 mt-1 font-medium">{{ catForm.description.length }} / 200</div>
            </div>

            <!-- Color -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Color <span class="text-rose-500">*</span></label>
              <div class="flex items-center gap-3">
                <div class="relative max-w-[220px] flex-1">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="w-3.5 h-3.5 rounded" :style="{ backgroundColor: catForm.color }"></span>
                  </div>
                  <input v-model="catForm.color" type="text" class="w-full pl-9 pr-3 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] font-bold focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] text-slate-700" />
                </div>
                <input v-model="catForm.color" type="color" class="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer p-0.5 bg-white" title="Pick color" />
              </div>
              <p class="text-[11px] text-slate-500 mt-1.5">Choose a color for this category</p>
            </div>

            <!-- Category Type -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-2.5">Category Type <span class="text-rose-500">*</span></label>
              <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                  <input type="radio" v-model="catForm.type" value="system" class="mt-1 accent-[#4338ca] cursor-pointer" />
                  <div>
                    <p class="text-[13px] font-bold text-slate-800">System Category</p>
                    <p class="text-[11px] text-slate-500">Default categories built into the system</p>
                  </div>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                  <input type="radio" v-model="catForm.type" value="custom" class="mt-1 accent-[#4338ca] cursor-pointer" />
                  <div>
                    <p class="text-[13px] font-bold text-slate-800">Custom Category</p>
                    <p class="text-[11px] text-slate-500">Categories created by administrators</p>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-end gap-3 px-5 sm:px-6 py-3.5 sm:py-4 border-t border-slate-100 bg-slate-50">
            <button @click="closeModal" class="px-4 sm:px-5 py-2 bg-white border border-slate-200 text-slate-600 font-bold rounded-lg text-[13px] hover:bg-slate-100 transition-colors shadow-sm">
              Cancel
            </button>
            <button @click="saveCategory" :disabled="calendarStore.isSaving" class="px-4 sm:px-5 py-2 bg-[#4338ca] text-white font-bold rounded-lg text-[13px] hover:bg-indigo-700 transition-colors shadow-sm flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
              <svg v-if="calendarStore.isSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              {{ calendarStore.isSaving ? 'Saving…' : (editingCategory ? 'Update Category' : 'Save Category') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- DELETE CONFIRM MODAL -->
    <Teleport to="body">
      <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-800/40 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-[95vw] sm:max-w-sm p-5 sm:p-6 flex flex-col gap-4 my-auto">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
              <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </div>
            <div>
              <h3 class="text-[16px] font-bold text-slate-800">Delete Category</h3>
              <p class="text-[13px] text-slate-500 mt-0.5">Are you sure you want to delete <strong>{{ deletingCategory?.name }}</strong>? This action cannot be undone.</p>
            </div>
          </div>
          <div v-if="deleteError" class="bg-rose-50 border border-rose-200 text-rose-600 rounded-lg px-4 py-3 text-[13px] font-medium">{{ deleteError }}</div>
          <div class="flex items-center justify-end gap-3">
            <button @click="showDeleteConfirm = false" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 font-bold rounded-lg text-[13px] hover:bg-slate-50">Cancel</button>
            <button @click="doDelete" class="px-4 py-2 bg-rose-500 text-white font-bold rounded-lg text-[13px] hover:bg-rose-600 transition-colors">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ADD EVENT MODAL -->
    <Teleport to="body">
      <div v-if="showAddEventModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-800/40 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-[95vw] sm:max-w-2xl max-h-[90vh] overflow-hidden flex flex-col my-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between px-5 sm:px-6 py-4 sm:py-5 border-b border-slate-100">
            <h2 class="text-[17px] sm:text-[18px] font-bold text-slate-800">Add Academic Event</h2>
            <button @click="closeEventModal" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <!-- Error Banner -->
          <div v-if="eventSaveError" class="mx-5 sm:mx-6 mt-4 px-4 py-2.5 bg-rose-50 border border-rose-200 text-rose-700 text-[12px] font-medium rounded-lg flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ eventSaveError }}
          </div>

          <!-- Modal Body -->
          <div class="p-5 sm:p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
              <!-- Event Title -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Event Title <span class="text-rose-500">*</span></label>
                <div v-if="!isCustomEventTitle" class="relative">
                  <select
                    v-model="eventForm.title"
                    @change="if (eventForm.title === 'Other') { isCustomEventTitle = true; eventForm.title = '' }"
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700 appearance-none"
                  >
                    <option value="" disabled>Select event title</option>
                    <option value="Class Start">Class Start</option>
                    <option value="Other">Other (Type custom title)</option>
                  </select>
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </div>
                </div>
                <div v-else class="flex gap-2">
                  <input v-model="eventForm.title" type="text" placeholder="Enter custom event title" class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] text-slate-700 transition-all placeholder:text-slate-400" />
                  <button type="button" @click="isCustomEventTitle = false; eventForm.title = ''" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-[12px] font-bold transition-colors flex items-center justify-center shrink-0" title="Back to predefined titles">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                  </button>
                </div>
              </div>

              <!-- Category -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Category <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <div class="w-5 h-5 rounded flex items-center justify-center bg-indigo-50 text-indigo-500">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                  </div>
                  <select v-model="eventForm.category_id" class="w-full pl-10 pr-10 py-2 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700 appearance-none">
                    <option value="">Select category</option>
                    <option v-for="cat in calendarStore.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                  </select>
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </div>
                </div>
              </div>

              <!-- Academic Year -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Academic Year <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="eventForm.academic_year" class="w-full px-3 py-2 pr-10 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700 appearance-none">
                    <option>2025/2026</option>
                    <option>2024/2025</option>
                    <option>2026/2027</option>
                  </select>
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </div>
                </div>
              </div>

              <!-- Semester -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Semester <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="eventForm.semester" class="w-full px-3 py-2 pr-10 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700 appearance-none">
                    <option>First Semester</option>
                    <option>Second Semester</option>
                    <option>Summer</option>
                  </select>
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </div>
                </div>
              </div>

              <!-- Start Date -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Start Date <span class="text-rose-500">*</span></label>
                <input v-model="eventForm.start_date" type="date" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700" />
              </div>

              <!-- End Date -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">End Date <span class="text-rose-500">*</span></label>
                <input v-model="eventForm.end_date" type="date" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700" />
              </div>

              <!-- All Day Event -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1">All Day Event</label>
                <p class="text-[11px] text-slate-500 mb-2">Enable if this event is for the whole day</p>
                <button type="button" @click="eventForm.all_day = !eventForm.all_day"
                  :class="[eventForm.all_day ? 'bg-[#4338ca]' : 'bg-slate-200', 'relative inline-flex h-5 w-9 items-center rounded-full transition-colors']">
                  <span :class="[eventForm.all_day ? 'translate-x-4' : 'translate-x-0.5', 'inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform']"></span>
                </button>
              </div>

              <!-- Event Time (hidden when all day) -->
              <div v-show="!eventForm.all_day">
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Event Time</label>
                <div class="flex items-center gap-2">
                  <input v-model="eventForm.start_time" type="time" placeholder="Start Time" class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700" />
                  <span class="text-slate-400 font-bold">-</span>
                  <input v-model="eventForm.end_time" type="time" placeholder="End Time" class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] text-slate-700" />
                </div>
              </div>

              <!-- Description -->
              <div class="md:col-span-2">
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Description</label>
                <textarea v-model="eventForm.description" rows="3" placeholder="Enter event description..." maxlength="500" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-[13px] focus:outline-none focus:border-[#4338ca] focus:ring-1 focus:ring-[#4338ca] text-slate-700 transition-all placeholder:text-slate-400 resize-none"></textarea>
                <div class="text-right text-[11px] text-slate-400 mt-1 font-medium">{{ eventForm.description.length }} / 500</div>
              </div>

              <!-- Status -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Status <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="w-2 h-2 rounded-full bg-[#4338ca]"></span>
                  </div>
                  <select v-model="eventForm.status" class="w-full pl-8 pr-10 py-2 bg-white border border-slate-200 rounded-lg text-[13px] font-medium focus:outline-none focus:border-[#4338ca] text-slate-700 appearance-none">
                    <option value="upcoming">Upcoming</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </div>
                </div>
              </div>

              <!-- Color -->
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Color <span class="text-rose-500">*</span></label>
                <div class="flex items-center gap-3">
                  <input type="color" v-model="eventForm.color" class="w-10 h-10 border border-slate-200 rounded-lg cursor-pointer p-0.5" />
                  <div class="flex gap-2 flex-wrap">
                    <button v-for="c in ['#6366F1','#22C55E','#3B82F6','#F59E0B','#EF4444','#8B5CF6']" :key="c" type="button" @click="eventForm.color = c"
                      class="w-6 h-6 rounded-full border-2 transition-all"
                      :style="{ backgroundColor: c, borderColor: eventForm.color === c ? '#1e1b4b' : 'transparent' }"></button>
                  </div>
                </div>
                <p class="text-[11px] text-slate-500 mt-1.5">Choose a color to display this event on calendar</p>
              </div>

              <!-- Repeat Event -->
              <div class="md:col-span-2">
                <label class="text-[12px] font-bold text-slate-700 mr-2">Repeat Event</label>
                <button type="button" @click="eventForm.is_recurring = !eventForm.is_recurring"
                  :class="[eventForm.is_recurring ? 'bg-[#4338ca]' : 'bg-slate-200', 'relative inline-flex h-5 w-9 items-center rounded-full transition-colors align-middle -mt-0.5']">
                  <span :class="[eventForm.is_recurring ? 'translate-x-4' : 'translate-x-0.5', 'inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform']"></span>
                </button>
                <p class="text-[11px] text-slate-500 mt-1">Enable if this is a recurring event</p>
              </div>

            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50">
            <button @click="closeEventModal" class="px-4 sm:px-5 py-2 bg-white border border-slate-200 text-slate-600 font-bold rounded-lg text-[13px] hover:bg-slate-50 transition-colors shadow-sm">
              Cancel
            </button>
            <button @click="saveEvent" :disabled="calendarStore.isSaving" class="px-4 sm:px-5 py-2 bg-[#4338ca] text-white font-bold rounded-lg text-[13px] hover:bg-indigo-700 transition-colors shadow-sm flex items-center gap-2 disabled:opacity-60">
              <svg v-if="calendarStore.isSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              {{ calendarStore.isSaving ? 'Saving…' : 'Save Event' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
